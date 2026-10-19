<?php

use Tests\TestCase;

/*
| Every test runs inside the Laravel test case so the container, config,
| and cache are available. Tests that touch the store use RefreshDatabase
| (SQLite in memory, from phpunit.xml).
*/
pest()->extend(TestCase::class)->in('Unit', 'Feature', 'Contract');
