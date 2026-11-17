<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use Snapsbrew\Exceptions\MissingCredentialsException;
use Snapsbrew\Laravel\Facades\Snapsbrew as SnapsbrewFacade;
use Snapsbrew\Laravel\SnapsbrewServiceProvider;
use Snapsbrew\Signing\UrlSigner;
use Snapsbrew\Snapsbrew;

it('merges the package configuration', function () {
    expect(config('snapsbrew.timeout'))->toBe(30.0)
        ->and(config('snapsbrew.retries'))->toBe(2)
        ->and(config('snapsbrew.url'))->toBe('https://snapsbrew.test');
});

it('binds one client for the application', function () {
    $client = app(Snapsbrew::class);

    expect($client)->toBeInstanceOf(Snapsbrew::class)
        ->and(app('snapsbrew'))->toBe($client)
        ->and($client->url)->toBe('https://snapsbrew.test');
});

it('resolves the facade', function () {
    expect(SnapsbrewFacade::getFacadeRoot())->toBe(app(Snapsbrew::class));
});

it('signs URLs with the configured key', function () {
    $url = SnapsbrewFacade::signedUrl('tpl')->text('title', 'Hi')->url();
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    $signature = $query['signature'];
    unset($query['signature']);

    expect($signature)->toBe((new UrlSigner('test-signing-key'))->signature($query));
});

it('publishes the configuration file', function () {
    $paths = ServiceProvider::pathsToPublish(SnapsbrewServiceProvider::class, 'snapsbrew-config');

    expect($paths)->toHaveCount(1)
        ->and(array_key_first($paths))->toEndWith('config/snapsbrew.php')
        ->and(reset($paths))->toBe(config_path('snapsbrew.php'));
});

it('explains a missing instance URL', function () {
    config(['snapsbrew.url' => null]);
    app()->forgetInstance(Snapsbrew::class);

    app(Snapsbrew::class);
})->throws(MissingCredentialsException::class, 'SNAPSBREW_URL');
