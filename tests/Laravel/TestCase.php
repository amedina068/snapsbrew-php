<?php

declare(strict_types=1);

namespace Snapsbrew\Tests\Laravel;

use Orchestra\Testbench\TestCase as Orchestra;
use Snapsbrew\Laravel\Facades\Snapsbrew;
use Snapsbrew\Laravel\SnapsbrewServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [SnapsbrewServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Snapsbrew' => Snapsbrew::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('snapsbrew.url', 'https://snapsbrew.test');
        $app['config']->set('snapsbrew.token', 'test-token');
        $app['config']->set('snapsbrew.signing_key', 'test-signing-key');
    }
}
