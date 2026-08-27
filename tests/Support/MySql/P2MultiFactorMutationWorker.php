<?php

declare(strict_types=1);

use Qmdb\Modules\IdentityMultiFactor\Domain\RecoveryCodeHash;
use Qmdb\Modules\IdentityMultiFactor\Domain\AuthenticationTransactionCookieParser;
use Qmdb\Modules\IdentityMultiFactor\Domain\StepUpAction;
use Qmdb\Modules\IdentityMultiFactor\Infrastructure\Persistence\MySqlIdentityMultiFactorRepository;
use Qmdb\Shared\Configuration\ApplicationEnvironment;
use Qmdb\Shared\Configuration\Database\DatabaseConfiguration;
use Qmdb\Shared\Configuration\Database\DatabaseTlsMode;
use Qmdb\Shared\Configuration\Database\MySqlDsnBuilder;
use Qmdb\Shared\Configuration\EnvironmentVariables;
use Qmdb\Shared\Database\Transaction\TransactionOptions;
use Qmdb\Shared\Database\Transaction\TransactionRetryPolicy;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\MySqlConnectionFactory;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\MySqlConnectionProvider;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\MySqlServerVerifier;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\MySqlSessionInitializer;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\MySqlSessionVerifier;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Connection\NativePdoConnector;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Transaction\ExponentialJitterRetryDelayStrategy;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Transaction\MySqlRetryableTransactionFailureClassifier;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Transaction\MySqlTransactionManager;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Transaction\NativeSleeper;
use Qmdb\Shared\Infrastructure\Persistence\MySql\Transaction\PdoMySqlTransactionDriver;
use Qmdb\Shared\Security\Secrets\EnvironmentSecretsProvider;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$payload = json_decode((string)stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (!is_array($payload)) {
    throw new RuntimeException('Worker payload is invalid.');
}
$accountId = $payload['account_id'] ?? null;
$operation = $payload['operation'] ?? null;
$nowValue = $payload['now'] ?? null;
if (!is_int($accountId) || !is_string($operation) || !is_string($nowValue)) {
    throw new RuntimeException('Worker payload is invalid.');
}
$now = new DateTimeImmutable($nowValue);
$variables = new EnvironmentVariables([
    'DB_PASSWORD' => (string)getenv('QMDB_TEST_DB_PASSWORD'),
]);
$configuration = new DatabaseConfiguration(
    (string)getenv('QMDB_TEST_DB_HOST'),
    (int)getenv('QMDB_TEST_DB_PORT'),
    (string)getenv('QMDB_TEST_DB_NAME'),
    (string)getenv('QMDB_TEST_DB_USERNAME'),
    DatabaseTlsMode::parse((string)getenv('QMDB_TEST_DB_TLS_MODE')),
    null,
    5,
    new TransactionRetryPolicy(3, 1, 10),
    ApplicationEnvironment::TEST,
);
$provider = new MySqlConnectionProvider(
    new MySqlConnectionFactory(
        $configuration,
        new MySqlDsnBuilder(),
        new EnvironmentSecretsProvider($variables),
        new NativePdoConnector(),
    ),
    new MySqlSessionInitializer(),
    new MySqlSessionVerifier(),
    new MySqlServerVerifier($configuration),
);
$manager = new MySqlTransactionManager(
    new PdoMySqlTransactionDriver($provider),
    new MySqlRetryableTransactionFailureClassifier(),
    new ExponentialJitterRetryDelayStrategy(),
    new NativeSleeper(),
    TransactionOptions::readWrite(retryPolicy: new TransactionRetryPolicy(3, 1, 10)),
);
$repository = new MySqlIdentityMultiFactorRepository($provider);
$mutated = match ($operation) {
    'recovery_code' => $manager->transactional(static function () use (
        $repository,
        $accountId,
        $payload,
        $now,
    ): bool {
        $hashHex = $payload['hash_hex'] ?? null;
        $hash = is_string($hashHex) ? hex2bin($hashHex) : false;
        if (!is_string($hash)) {
            throw new RuntimeException('Worker recovery-code hash is invalid.');
        }
        $set = $repository->findActiveRecoveryCodeSet($accountId, true);

        return $set !== null && $repository->consumeRecoveryCode($set, new RecoveryCodeHash($hash), $now);
    }),
    'totp_counter' => $manager->transactional(static function () use (
        $repository,
        $accountId,
        $payload,
        $now,
    ): bool {
        $counter = $payload['counter'] ?? null;
        if (!is_int($counter) || $counter < 0) {
            throw new RuntimeException('Worker TOTP counter is invalid.');
        }
        $authenticator = $repository->findActiveTotp($accountId, true);

        return $authenticator !== null && $repository->acceptTotpCounter($authenticator, $counter, $now);
    }),
    'authentication_transaction' => $manager->transactional(static function () use (
        $repository,
        $payload,
        $now,
    ): bool {
        $rawCookie = $payload['cookie'] ?? null;
        if (!is_string($rawCookie)) {
            throw new RuntimeException('Worker authentication-transaction cookie is invalid.');
        }
        $cookie = (new AuthenticationTransactionCookieParser())->parse($rawCookie);
        $transaction = $repository->findTransaction($cookie, true);

        return $transaction !== null
            && $transaction->usableAt($now)
            && $repository->completeTransaction($transaction, $now);
    }),
    'step_up_grant' => $manager->transactional(static function () use (
        $repository,
        $accountId,
        $payload,
        $now,
    ): bool {
        $sessionId = $payload['session_id'] ?? null;
        $actionValue = $payload['action'] ?? null;
        $action = is_string($actionValue) ? StepUpAction::tryFrom($actionValue) : null;
        if (!is_int($sessionId) || $action === null) {
            throw new RuntimeException('Worker step-up grant is invalid.');
        }
        $grant = $repository->findActiveGrant($accountId, $sessionId, $action, true);

        return $grant !== null
            && $grant->permits($accountId, $sessionId, $action, $now)
            && $repository->consumeGrant($grant, $now);
    }),
    'revoke_factor' => $manager->transactional(static function () use (
        $repository,
        $accountId,
        $payload,
        $now,
    ): bool {
        $factorType = $payload['factor_type'] ?? null;
        $publicId = $payload['public_id'] ?? null;
        if (!is_string($factorType) || !is_string($publicId)) {
            throw new RuntimeException('Worker factor revocation is invalid.');
        }
        $repository->findPolicy($accountId, true);
        if ($repository->activeStrongFactorCount($accountId) <= 1) {
            return false;
        }
        if ($factorType === 'totp') {
            $factor = $repository->findTotp($accountId, $publicId, true);

            return $factor !== null && $repository->revokeTotp($factor, $now);
        }
        if ($factorType === 'passkey') {
            $factor = $repository->findPasskey($accountId, $publicId, true);

            return $factor !== null && $repository->revokePasskey($factor, $now);
        }
        throw new RuntimeException('Worker factor type is invalid.');
    }),
    default => throw new RuntimeException('Worker operation is invalid.'),
};
fwrite(STDOUT, json_encode(['mutated' => $mutated], JSON_THROW_ON_ERROR));
