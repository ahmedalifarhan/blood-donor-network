<?php

namespace App\Providers;

use App\Repositories\Contracts\OperationalLogRepository;
use App\Repositories\Contracts\SchedulerStateRepository;
use App\Repositories\Contracts\SettingsRepository;
use App\Repositories\Contracts\StoreProbe;
use App\Repositories\Eloquent\DatabaseStoreProbe;
use App\Repositories\Eloquent\EloquentOperationalLogRepository;
use App\Repositories\Eloquent\EloquentSchedulerStateRepository;
use App\Repositories\Eloquent\EloquentSettingsRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Binds every storage contract to the database implementation (constitution V).
 * The connection comes from Laravel's DB_CONNECTION; feature code depends on
 * the contracts only.
 */
final class StorageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsRepository::class, EloquentSettingsRepository::class);
        $this->app->singleton(SchedulerStateRepository::class, EloquentSchedulerStateRepository::class);
        $this->app->singleton(OperationalLogRepository::class, EloquentOperationalLogRepository::class);

        $this->app->singleton(StoreProbe::class, fn (Application $app): StoreProbe => new DatabaseStoreProbe(
            $app->make('db'),
            self::schema(),
        ));
    }

    /** @return array<string, list<string>> */
    private static function schema(): array
    {
        /** @var array<string, list<string>> */
        return config('store-schema', []);
    }
}
