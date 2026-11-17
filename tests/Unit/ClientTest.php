<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use Snapsbrew\Data\Account;
use Snapsbrew\Exceptions\MissingCredentialsException;
use Snapsbrew\Snapsbrew;

it('reads the account of the token', function () {
    $history = [];
    $client = snapsbrew([json(['data' => ['email' => 'dev@example.com', 'name' => 'Ada Lovelace', 'company' => 'Analytical Engines']])], $history);

    $account = $client->account();

    expect($account)->toEqual(new Account('dev@example.com', 'Ada Lovelace', 'Analytical Engines'));

    $request = $history[0]['request'];

    expect($request->getMethod())->toBe('GET')
        ->and((string) $request->getUri())->toBe('https://snapsbrew.test/api/v1/auth')
        ->and($request->getHeaderLine('Authorization'))->toBe('Bearer test-token')
        ->and($request->getHeaderLine('Accept'))->toBe('application/json')
        ->and($request->getHeaderLine('User-Agent'))->toBe('snapsbrew-php/'.Snapsbrew::VERSION);
});

it('reads an account with no company', function () {
    $account = snapsbrew([json(['data' => ['email' => 'a@b.c', 'name' => 'A', 'company' => null]])])->account();

    expect($account->company)->toBeNull();
});

it('normalizes the instance URL', function (string $given) {
    expect((new Snapsbrew($given, 'token'))->url)->toBe('https://snapsbrew.test');
})->with([
    'plain' => 'https://snapsbrew.test',
    'trailing slash' => 'https://snapsbrew.test/',
    'api suffix' => 'https://snapsbrew.test/api',
    'api suffix and slash' => ' https://snapsbrew.test/api/ ',
]);

it('needs an instance URL', function () {
    new Snapsbrew('', 'token');
})->throws(MissingCredentialsException::class, 'SNAPSBREW_URL');

it('needs a token for API calls', function () {
    $client = new Snapsbrew('https://snapsbrew.test', signingKey: 'key');

    $client->account();
})->throws(MissingCredentialsException::class, 'SNAPSBREW_TOKEN');

it('keeps the token out of debug output', function () {
    $client = new Snapsbrew('https://snapsbrew.test', 'secret-token-value', 'secret-key-value');

    expect(print_r($client, true))
        ->not->toContain('secret-token-value')
        ->not->toContain('secret-key-value');
});

it('creates a Guzzle client with the timeout when no client is given', function () {
    $client = new Snapsbrew('https://snapsbrew.test', 'token', timeout: 12.5);

    $transport = (fn () => $this->transport)->call($client);
    $http = (fn () => $this->client)->call($transport);

    expect($http)->toBeInstanceOf(Client::class)
        ->and((fn () => $this->config['timeout'])->call($http))->toBe(12.5);
});
