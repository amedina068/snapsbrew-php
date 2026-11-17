<?php

declare(strict_types=1);

/*
 * These tests call a real Snapsbrew instance. They run only when
 * SNAPSBREW_TEST_URL and SNAPSBREW_TEST_TOKEN are set.
 *
 * The tests are read-only: they read the account, list the templates and
 * request one preview. A preview costs no credit and stores nothing.
 */

use Snapsbrew\Snapsbrew;

function liveClient(): Snapsbrew
{
    return new Snapsbrew(
        url: (string) getenv('SNAPSBREW_TEST_URL'),
        token: (string) getenv('SNAPSBREW_TEST_TOKEN'),
        retries: 1,
    );
}

beforeEach(function () {
    if (! getenv('SNAPSBREW_TEST_URL') || ! getenv('SNAPSBREW_TEST_TOKEN')) {
        $this->markTestSkipped('Set SNAPSBREW_TEST_URL and SNAPSBREW_TEST_TOKEN to run the integration tests.');
    }
});

it('reads the account of the token', function () {
    $account = liveClient()->account();

    expect($account->email)->toContain('@');
})->group('integration');

it('lists the templates', function () {
    $page = liveClient()->templates()->list();

    expect($page->items)->toBeArray();
})->group('integration');

it('previews the first template', function () {
    $template = liveClient()->templates()->list()->first();

    if ($template === null) {
        $this->markTestSkipped('The account has no template to preview.');
    }

    $preview = liveClient()->snaps()->preview($template->uuid);

    expect(strtolower($preview->html))->toContain('<html');
})->group('integration');
