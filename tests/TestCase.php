<?php

namespace Tests;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * @return Application
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        // Test-only tables (e.g. the tenant-isolation probe); never part of the real schema.
        $app->make(Migrator::class)->path(base_path('tests/Fixtures/Tenancy/migrations'));

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Views render without built assets (no public/build needed to run the suite).
        $this->withoutVite();
    }
}
