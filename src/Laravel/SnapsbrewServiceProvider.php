<?php

declare(strict_types=1);

namespace Snapsbrew\Laravel;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use Snapsbrew\Snapsbrew;
use Snapsbrew\Webhooks\Webhook;

final class SnapsbrewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/snapsbrew.php', 'snapsbrew');

        $this->app->singleton(Snapsbrew::class, static function (Application $app): Snapsbrew {
            $config = $app->make(Repository::class);

            return new Snapsbrew(
                url: self::string($config->get('snapsbrew.url')) ?? '',
                token: self::string($config->get('snapsbrew.token')),
                signingKey: self::string($config->get('snapsbrew.signing_key')),
                timeout: is_numeric($timeout = $config->get('snapsbrew.timeout')) ? (float) $timeout : 30.0,
                retries: is_numeric($retries = $config->get('snapsbrew.retries')) ? (int) $retries : 2,
            );
        });

        $this->app->alias(Snapsbrew::class, 'snapsbrew');

        $this->app->singleton(Webhook::class, static function (Application $app): Webhook {
            $config = $app->make(Repository::class);

            return new Webhook(
                secret: self::string($config->get('snapsbrew.webhook.secret')) ?? '',
                tolerance: is_numeric($tolerance = $config->get('snapsbrew.webhook.tolerance')) ? (int) $tolerance : 300,
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/snapsbrew.php' => $this->app->configPath('snapsbrew.php'),
            ], 'snapsbrew-config');
        }

        $this->callAfterResolving(BladeCompiler::class, static function (BladeCompiler $blade): void {
            $blade->componentNamespace('Snapsbrew\\Laravel\\View\\Components', 'snapsbrew');
        });
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
