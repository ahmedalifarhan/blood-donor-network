<?php

namespace App\Repositories\Eloquent;

use App\Models\Setting;
use App\Repositories\Contracts\SettingsRepository;
use App\Support\Ids;
use Carbon\CarbonImmutable;

final class EloquentSettingsRepository implements SettingsRepository
{
    use GuardsStoreAccess;

    public function allOverrides(): array
    {
        return $this->guarded(static function (): array {
            $overrides = [];

            foreach (Setting::query()->get(['key', 'value']) as $setting) {
                $overrides[$setting->key] = $setting->value;
            }

            return $overrides;
        });
    }

    public function upsert(string $key, string $value, string $by): void
    {
        // One atomic INSERT … ON CONFLICT (key): concurrent writers cannot create duplicates,
        // and an existing row keeps its ID.
        $this->guarded(static fn (): int => Setting::query()->upsert(
            [[
                'id' => Ids::new(),
                'key' => $key,
                'value' => $value,
                'updated_at' => CarbonImmutable::now('UTC'),
                'updated_by' => $by,
            ]],
            ['key'],
            ['value', 'updated_at', 'updated_by'],
        ));
    }
}
