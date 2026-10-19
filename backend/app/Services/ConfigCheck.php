<?php

namespace App\Services;

/**
 * Names (never values) of required environment variables that are missing
 * for the active database connection (FR-031). Reads config, not env(),
 * so it keeps working after `php artisan config:cache`.
 */
final class ConfigCheck
{
    /** @return list<string> */
    public function missing(): array
    {
        $required = self::map(config('store.required.always'));

        if (config('database.default') === 'pgsql') {
            $required = [...$required, ...self::map(config('store.required.pgsql'))];
        }

        $missing = [];

        foreach ($required as $name => $configKey) {
            $value = config($configKey);

            if (! is_string($value) || trim($value) === '') {
                $missing[] = $name;
            }
        }

        return $missing;
    }

    public function isComplete(): bool
    {
        return $this->missing() === [];
    }

    /** @return array<string, string> env var name → config key */
    private static function map(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_filter(
            $value,
            static fn (mixed $key, mixed $name): bool => is_string($name) && is_string($key),
            ARRAY_FILTER_USE_BOTH,
        );
    }
}
