<?php

declare(strict_types=1);

namespace Qmdb\Bootstrap;

final readonly class RuntimeRequirements
{
    public const MINIMUM_PHP_VERSION = '8.5.0';

    /** @var list<string> */
    public const REQUIRED_EXTENSIONS = ['json', 'mbstring'];

    /**
     * @param list<string> $loadedExtensions
     */
    public function evaluate(string $phpVersion, array $loadedExtensions): RuntimeRequirementResult
    {
        $violations = [];

        if (version_compare($phpVersion, self::MINIMUM_PHP_VERSION, '<')) {
            $violations[] = new RuntimeViolation(
                code: 'PHP_VERSION_TOO_LOW',
                message: sprintf('PHP %s or newer is required.', self::MINIMUM_PHP_VERSION),
            );
        }

        $normalizedExtensions = array_map(
            static fn (string $extension): string => strtolower($extension),
            $loadedExtensions,
        );

        foreach (self::REQUIRED_EXTENSIONS as $requiredExtension) {
            if (!in_array($requiredExtension, $normalizedExtensions, true)) {
                $violations[] = new RuntimeViolation(
                    code: 'MISSING_EXTENSION',
                    message: sprintf('The %s PHP extension is required.', $requiredExtension),
                );
            }
        }

        return new RuntimeRequirementResult($violations);
    }

    public function evaluateCurrentRuntime(): RuntimeRequirementResult
    {
        return $this->evaluate(PHP_VERSION, get_loaded_extensions());
    }
}
