<?php

namespace Tests;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Tests wipe their database (RefreshDatabase), so they run only on SQLite
     * in memory or on a database whose name ends in "_test" (constitution:
     * tests never connect to a production database). The check uses the
     * resolved connection's driver and database name, not the connection's
     * label, and runs before any test trait touches the database.
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        // Resolving the connection object does not open a network connection.
        $connection = $app->make('db')->connection();
        $driver = $connection->getDriverName();
        $database = (string) $connection->getDatabaseName();

        $allowed = ($driver === 'sqlite' && $database === ':memory:') || str_ends_with($database, '_test');

        if (! $allowed) {
            throw new RuntimeException("Refusing to run tests on {$driver} database \"{$database}\": use SQLite in memory or a database whose name ends in \"_test\".");
        }

        return $app;
    }
}
