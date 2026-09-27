<?php

declare(strict_types=1);

namespace Qmdb\Modules\ProductionHardening\Application;

use Qmdb\Modules\ProductionHardening\Domain\IntegrationSecretBox;

final readonly class WebhookSubscriptionSecretIssuer
{
    public function __construct(private IntegrationSecretBox $secrets)
    {
    }

    /** @return array{key_id:string,secret:string,ciphertext:string} */
    public function issue(): array
    {
        $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        return [
            'key_id' => 'whk_' . bin2hex(random_bytes(8)),
            'secret' => $secret,
            'ciphertext' => $this->secrets->encrypt($secret),
        ];
    }
}
