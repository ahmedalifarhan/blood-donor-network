<?php

namespace App\Repositories\Table;

use App\Repositories\Contracts\SettingsRepository;
use App\Support\Ids;
use App\Support\Utc;

final class TableSettingsRepository implements SettingsRepository
{
    private const TAB = 'Settings';

    public function __construct(private readonly Table $table) {}

    public function allOverrides(): array
    {
        $overrides = [];

        foreach ($this->table->rows(self::TAB) as $row) {
            $key = $row['Key'] ?? '';

            if ($key !== '') {
                $overrides[$key] = $row['Value'] ?? '';
            }
        }

        return $overrides;
    }

    public function upsert(string $key, string $value, string $by): void
    {
        $this->table->upsert(
            self::TAB,
            'Key',
            ['Key' => $key, 'Value' => $value, 'UpdatedAt' => Utc::now(), 'UpdatedBy' => $by],
            ['ID' => Ids::new()],
        );
    }
}
