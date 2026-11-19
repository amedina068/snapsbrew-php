<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Snapsbrew\Exceptions\MissingCredentialsException;
use Snapsbrew\Laravel\Facades\Snapsbrew as SnapsbrewFacade;
use Snapsbrew\Laravel\SnapsbrewServiceProvider;
use Snapsbrew\Signing\UrlSigner;
use Snapsbrew\Snapsbrew;
use Snapsbrew\Webhooks\Webhook;

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

it('binds a webhook verifier with the configured secret', function () {
    config(['snapsbrew.webhook.secret' => 'whsec_test', 'snapsbrew.webhook.tolerance' => 600]);

    $webhook = app(Webhook::class);
    $body = '{"event":"snap.completed"}';
    $timestamp = time() - 500;

    $event = $webhook->verify($body, [
        'x-snapsbrew-timestamp' => [(string) $timestamp],
        'x-snapsbrew-signature' => [hash_hmac('sha256', "{$timestamp}.{$body}", 'whsec_test')],
        'x-snapsbrew-delivery' => ['3f2b6c1e-8a4d-4e5f-9b0a-1c2d3e4f5a6b'],
    ]);

    expect($webhook->tolerance)->toBe(600)
        ->and(app(Webhook::class))->toBe($webhook)
        ->and($event->deliveryId)->toBe('3f2b6c1e-8a4d-4e5f-9b0a-1c2d3e4f5a6b');
});

it('verifies the request that Laravel received', function () {
    config(['snapsbrew.webhook.secret' => 'whsec_test']);

    $body = '{"event":"snap.failed","snap":{"media_url":"https:\/\/x"}}';
    $timestamp = (string) time();
    $request = Request::create('/hooks/snapsbrew', 'POST', server: [
        'HTTP_X_SNAPSBREW_TIMESTAMP' => $timestamp,
        'HTTP_X_SNAPSBREW_SIGNATURE' => hash_hmac('sha256', "{$timestamp}.{$body}", 'whsec_test'),
        'CONTENT_TYPE' => 'application/json',
    ], content: $body);

    expect(app(Webhook::class)->verify($request->getContent(), $request->headers->all())->type)->toBe('snap.failed');
});

it('uses a tolerance of 300 seconds by default', function () {
    config(['snapsbrew.webhook.secret' => 'whsec_test']);

    expect(config('snapsbrew.webhook.tolerance'))->toBe(300)
        ->and(app(Webhook::class)->tolerance)->toBe(300);
});

it('explains a missing webhook secret', function () {
    app(Webhook::class);
})->throws(MissingCredentialsException::class, 'SNAPSBREW_WEBHOOK_SECRET');
