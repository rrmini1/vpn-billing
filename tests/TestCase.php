<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $database = config('database.connections.'.config('database.default').'.database');

        if (app()->environment('testing')
            && config('database.default') !== 'sqlite'
            && ! str_ends_with((string) $database, '_testing')
        ) {
            throw new \RuntimeException(
                'Tests must run against a *_testing database. Current database: '.$database,
            );
        }
    }
}
