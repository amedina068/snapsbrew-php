<?php

declare(strict_types=1);

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Snapsbrew\Data\Snap;
use Snapsbrew\Exceptions\NotFoundException;
use Snapsbrew\Exceptions\RateLimitException;
use Snapsbrew\Exceptions\ServerException;
use Snapsbrew\Snapsbrew;

const FORM_URL = 'https://snapsbrew.test/api/v1/embedded-forms/5c2a9e1f-0b44-4a1e-9f2b-5d3c8e1a7b60?signature=0f3a9c';

it('submits a template form and returns one snap', function () {
    $history = [];

    $snap = snapsbrew([json(snapPayload(), 201)], $history)->forms()->submit(FORM_URL, ['headline' => 'Hello']);

    $request = $history[0]['request'];

    expect($snap)->toBeInstanceOf(Snap::class)
        ->and($snap->id)->toBe('9f1c2d3e')
        ->and((string) $request->getUri())->toBe(FORM_URL)
        ->and($request->getHeaderLine('Content-Type'))->toStartWith('multipart/form-data; boundary=')
        ->and((string) $request->getBody())->toContain("Content-Disposition: form-data; name=\"layers[headline]\"\r\n\r\nHello\r\n");
});

it('returns a list of snaps for a collection form', function () {
    $snaps = snapsbrew([json([snapPayload(), snapPayload(['id' => '4b8a7c6d'])], 201)])
        ->forms()->submit(FORM_URL, ['141e8454__headline' => 'Hello']);

    expect($snaps)->toBeArray()
        ->and(array_map(fn (Snap $snap) => $snap->id, $snaps))->toBe(['9f1c2d3e', '4b8a7c6d']);
});

it('sends an image layer as a file', function () {
    $history = [];
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, 'PNG-BYTES');
    rewind($stream);

    snapsbrew([json(snapPayload(), 201)], $history)->forms()->submit(FORM_URL, ['photo' => $stream]);

    expect((string) $history[0]['request']->getBody())
        ->toContain('name="layers[photo]"; filename="memory"')
        ->toContain("\r\n\r\nPNG-BYTES\r\n");
});

it('sends no token to the form', function () {
    $history = [];

    snapsbrew([json(snapPayload(), 201)], $history)->forms()->submit(FORM_URL, ['headline' => 'Hello']);

    expect($history[0]['request']->hasHeader('Authorization'))->toBeFalse();
});

it('needs no token to submit a form', function () {
    $history = [];
    $stack = HandlerStack::create(new MockHandler([json(snapPayload(), 201)]));
    $stack->push(Middleware::history($history));
    $client = new Snapsbrew('https://snapsbrew.test', httpClient: new GuzzleClient(['handler' => $stack]));

    expect($client->forms()->submit(FORM_URL, ['headline' => 'Hello'])->id)->toBe('9f1c2d3e');
});

it('refuses a URL that is not absolute', function () {
    snapsbrew()->forms()->submit('/api/v1/embedded-forms/5c2a', ['headline' => 'Hello']);
})->throws(InvalidArgumentException::class, 'signed submit URL');

it('gives a not found error for a bad signature', function () {
    snapsbrew([json(['message' => ''], 404)])->forms()->submit(FORM_URL, ['headline' => 'Hello']);
})->throws(NotFoundException::class);

it('gives the rate limit code of too many submissions', function () {
    try {
        snapsbrew([json(['message' => 'Too many submissions.', 'code' => 'rate_limited'], 429, ['Retry-After' => '30'])])
            ->forms()->submit(FORM_URL, ['headline' => 'Hello']);
    } catch (RateLimitException $exception) {
        expect($exception->errorCode())->toBe('rate_limited')
            ->and($exception->retryAfter())->toBe(30);

        return;
    }

    $this->fail('The submit did not throw.');
});

it('retries a rate limit', function () {
    $history = [];
    $delays = [];
    $client = snapsbrew([json(['code' => 'rate_limited'], 429, ['Retry-After' => '1']), json(snapPayload(), 201)], $history, noSleep(2, $delays));

    expect($client->forms()->submit(FORM_URL, ['headline' => 'Hello'])->id)->toBe('9f1c2d3e')
        ->and($history)->toHaveCount(2)
        ->and($delays)->toBe([1000]);
});

it('never retries a server error', function () {
    $history = [];
    $client = snapsbrew([json(['message' => 'Server Error'], 500), json(snapPayload(), 201)], $history, noSleep(2));

    expect(fn () => $client->forms()->submit(FORM_URL, ['headline' => 'Hello']))->toThrow(ServerException::class)
        ->and($history)->toHaveCount(1);
});

it('sends a file object as a file', function () {
    $history = [];
    $path = tempnam(sys_get_temp_dir(), 'snap');
    file_put_contents($path, 'LOGO');

    snapsbrew([json(snapPayload(), 201)], $history)->forms()->submit(FORM_URL, ['logo' => new SplFileInfo($path)]);
    unlink($path);

    expect((string) $history[0]['request']->getBody())
        ->toContain('name="layers[logo]"; filename="'.basename($path).'"')
        ->toContain("\r\n\r\nLOGO\r\n");
});
