<?php

namespace App\Providers;

use App\Repositories\Contracts\OperationalLogRepository;
use App\Repositories\Contracts\SchedulerStateRepository;
use App\Repositories\Contracts\SettingsRepository;
use App\Repositories\Contracts\StoreProbe;
use App\Repositories\Contracts\StoreSchemaManager;
use App\Repositories\InMemory\InMemoryCellBackend;
use App\Repositories\Sheets\SheetsCellBackend;
use App\Repositories\Table\CellBackend;
use App\Repositories\Table\Table;
use App\Repositories\Table\TableOperationalLogRepository;
use App\Repositories\Table\TableSchedulerStateRepository;
use App\Repositories\Table\TableSchemaManager;
use App\Repositories\Table\TableSettingsRepository;
use App\Repositories\Table\TableStoreProbe;
use App\Support\Backoff;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Binds every storage contract from DATA_DRIVER (constitution V).
 * Feature code depends on the contracts only.
 */
final class StorageServiceProvider extends ServiceProvider
{
    private const DRIVERS = ['sheets', 'memory'];

    public function register(): void
    {
        $this->app->singleton(CellBackend::class, fn (Application $app): CellBackend => match (self::driver()) {
            'sheets' => new SheetsCellBackend(
                spreadsheetId: self::string('store.sheets.spreadsheet_id'),
                credentials: self::string('store.sheets.credentials'),
            ),
            default => new InMemoryCellBackend($app->make(CacheFactory::class)->store()),
        });

        $this->app->singleton(Table::class, fn (Application $app): Table => new Table(
            backend: $app->make(CellBackend::class),
            cache: $app->make(CacheFactory::class)->store(),
            backoff: $app->make(Backoff::class),
            lockName: self::driver() === 'sheets' ? 'sheets:write' : 'store:write',
            cacheSeconds: (int) config('store.read_cache_seconds', 60),
        ));

        $this->app->singleton(SettingsRepository::class, TableSettingsRepository::class);
        $this->app->singleton(SchedulerStateRepository::class, TableSchedulerStateRepository::class);
        $this->app->singleton(OperationalLogRepository::class, TableOperationalLogRepository::class);

        $this->app->singleton(StoreProbe::class, fn (Application $app): StoreProbe => new TableStoreProbe(
            $app->make(CellBackend::class),
            self::schema(),
        ));

        $this->app->singleton(StoreSchemaManager::class, fn (Application $app): StoreSchemaManager => new TableSchemaManager(
            $app->make(CellBackend::class),
            $app->make(Table::class),
            self::schema(),
        ));
    }

    public function boot(): void
    {
        if (! in_array(self::driver(), self::DRIVERS, true)) {
            throw new InvalidArgumentException(
                'Unknown DATA_DRIVER ['.self::driver().']. Use one of: '.implode(', ', self::DRIVERS).'.',
            );
        }
    }

    private static function driver(): string
    {
        return self::string('store.driver');
    }

    private static function string(string $key): string
    {
        $value = config($key);

        return is_string($value) ? $value : '';
    }

    /** @return array<string, list<string>> */
    private static function schema(): array
    {
        /** @var array<string, list<string>> */
        return config('store-schema', []);
    }
}
