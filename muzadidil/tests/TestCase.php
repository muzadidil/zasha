<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Safety net: if a stale config cache or misconfigured env makes the
        // test suite target the dev database, abort instead of silently wiping
        // it via RefreshDatabase. The dev database carries seeded test users;
        // tests must run against zasha_order_test only.
        $current = DB::connection()->getDatabaseName();
        if ($current !== 'zasha_order_test') {
            throw new RuntimeException(
                "Test suite refused to run against database [{$current}]. "
                .'Expected [zasha_order_test]. Run `php artisan config:clear` '
                .'and re-run the tests.'
            );
        }
    }
}
