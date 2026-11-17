<?php

declare(strict_types=1);

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Snapsbrew\Exceptions\ConflictException;
use Snapsbrew\Exceptions\ConnectionException;
use Snapsbrew\Exceptions\RateLimitException;
use Snapsbrew\Exceptions\ServerException;
use Snapsbrew\Http\Response;
use Snapsbrew\Http\RetryPolicy;

$account = ['data' => ['email' => 'a@b.c', 'name' => 'A', 'company' => null]];

function connectionRefused(): ConnectException
{
    return new ConnectException('cURL error 7: Failed to connect: Connection refused', new Request('GET', 'https://snapsbrew.test'));
}

function readTimeout(): ConnectException
{
    return new ConnectException('cURL error 28: Operation timed out after 30001 milliseconds with 0 bytes received', new Request('GET', 'https://snapsbrew.test'));
}

it('retries a GET after a server error', function () use ($account) {
    $history = [];
    $delays = [];
    $client = snapsbrew([json(['message' => 'Error'], 500), json(['message' => 'Error'], 503), json($account)], $history, noSleep(2, $delays));

    expect($client->account()->email)->toBe('a@b.c')
        ->and($history)->toHaveCount(3)
        ->and($delays)->toHaveCount(2);
});

it('throws the last error when the retries are used up', function () {
    $history = [];
    $client = snapsbrew([json([], 500), json([], 500), json([], 500)], $history, noSleep(2));

    expect(fn () => $client->account())->toThrow(ServerException::class)
        ->and($history)->toHaveCount(3);
});

it('does not retry when retries are off', function () {
    $history = [];
    $client = snapsbrew([json([], 500), json([], 200)], $history, 0);

    expect(fn () => $client->account())->toThrow(ServerException::class)
        ->and($history)->toHaveCount(1);
});

it('does not retry a POST after a server error', function () {
    $history = [];
    $client = snapsbrew([json([], 500), json(snapPayload(), 201)], $history, noSleep(2));

    expect(fn () => $client->snaps()->create('tpl'))->toThrow(ServerException::class)
        ->and($history)->toHaveCount(1);
});

it('retries a POST with an idempotency key after a server error', function () {
    $history = [];
    $client = snapsbrew([json([], 502), json(snapPayload(), 201)], $history, noSleep(2));

    expect($client->snaps()->create('tpl', idempotencyKey: 'order-1')->id)->toBe('9f1c2d3e')
        ->and($history)->toHaveCount(2)
        ->and(body($history[1]['request'])['idempotency_key'])->toBe('order-1');
});

it('retries a POST after a rate limit and waits for Retry-After', function () {
    $history = [];
    $delays = [];
    $client = snapsbrew([json(['message' => 'Too Many Attempts.'], 429, ['Retry-After' => '3']), json(snapPayload(), 201)], $history, noSleep(2, $delays));

    expect($client->snaps()->create('tpl')->id)->toBe('9f1c2d3e')
        ->and($history)->toHaveCount(2)
        ->and($delays)->toBe([3000]);
});

it('does not wait when Retry-After is longer than the limit', function () {
    $history = [];
    $delays = [];
    $client = snapsbrew([json([], 429, ['Retry-After' => '120']), json([], 200)], $history, noSleep(2, $delays, maxRetryAfter: 60));

    expect(fn () => $client->account())->toThrow(RateLimitException::class)
        ->and($history)->toHaveCount(1)
        ->and($delays)->toBe([]);
});

it('retries a POST after a connection error that happened before the request was sent', function () {
    $history = [];
    $client = snapsbrew([connectionRefused(), json(snapPayload(), 201)], $history, noSleep(2));

    expect($client->snaps()->create('tpl')->id)->toBe('9f1c2d3e')
        ->and($history)->toHaveCount(2);
});

it('does not retry a POST after a timeout, because the instance can have received it', function () {
    $history = [];
    $client = snapsbrew([readTimeout(), json(snapPayload(), 201)], $history, noSleep(2));

    expect(fn () => $client->snaps()->create('tpl'))->toThrow(ConnectionException::class)
        ->and($history)->toHaveCount(1);
});

it('retries a GET after a timeout', function () use ($account) {
    $history = [];
    $client = snapsbrew([readTimeout(), json($account)], $history, noSleep(2));

    expect($client->account()->name)->toBe('A')
        ->and($history)->toHaveCount(2);
});

it('retries the idempotent cancel of a batch after a server error', function () {
    $history = [];
    $client = snapsbrew([json([], 500), json(batchPayload(['status' => 'canceled', 'items' => []]))], $history, noSleep(1));

    expect($client->batches()->cancel('b')->isFinished())->toBeTrue()
        ->and($history)->toHaveCount(2);
});

it('retries a snap with an idempotency key while the key is in progress', function () {
    $history = [];
    $delays = [];
    $client = snapsbrew([
        json(['message' => 'In progress.', 'code' => 'idempotency_key_in_progress'], 409),
        json(snapPayload(), 201, ['X-Idempotent-Replay' => 'true']),
    ], $history, noSleep(2, $delays));

    $snap = $client->snaps()->create('tpl', idempotencyKey: 'order-1');

    expect($snap->replayed)->toBeTrue()
        ->and($history)->toHaveCount(2)
        ->and($delays)->toHaveCount(1)
        ->and($delays[0])->toBeGreaterThanOrEqual(250)->toBeLessThanOrEqual(500)
        ->and(body($history[1]['request'])['idempotency_key'])->toBe('order-1');
});

it('retries a batch with an idempotency key while the key is in progress', function () {
    $history = [];
    $client = snapsbrew([
        json(['message' => 'In progress.', 'code' => 'idempotency_key_in_progress'], 409),
        json(batchPayload(), 202),
    ], $history, noSleep(2));

    expect($client->batches()->create('tpl', [[['layer' => 'a', 'text' => 'A']]], idempotencyKey: 'batch-1')->totalRows)->toBe(2)
        ->and($history)->toHaveCount(2);
});

it('throws the conflict when the key is still in progress after the last retry', function () {
    $history = [];
    $conflict = json(['message' => 'In progress.', 'code' => 'idempotency_key_in_progress'], 409);
    $client = snapsbrew([$conflict, $conflict, $conflict], $history, noSleep(2));

    expect(fn () => $client->snaps()->create('tpl', idempotencyKey: 'order-1'))->toThrow(ConflictException::class)
        ->and($history)->toHaveCount(3);
});

it('does not retry a conflict that a repeat cannot fix', function (string $code, string $resource) {
    $history = [];
    $client = snapsbrew([json(['message' => 'Use a new key.', 'code' => $code], 409), json(snapPayload(), 201)], $history, noSleep(2));

    $call = fn () => $resource === 'snaps'
        ? $client->snaps()->create('tpl', idempotencyKey: 'order-1')
        : $client->batches()->create('tpl', [[]], idempotencyKey: 'order-1');

    expect($call)->toThrow(ConflictException::class)
        ->and($history)->toHaveCount(1);
})->with([
    'key belongs to a batch' => ['idempotency_key_belongs_to_batch', 'snaps'],
    'key belongs to a snap' => ['idempotency_key_belongs_to_snap', 'batches'],
]);

it('does not retry an in-progress conflict for a request with no idempotency key', function () {
    $history = [];
    $client = snapsbrew([json(['code' => 'idempotency_key_in_progress'], 409), json([], 200)], $history, noSleep(2));

    expect(fn () => $client->tags()->sync('tpl', [1]))->toThrow(ConflictException::class)
        ->and($history)->toHaveCount(1);
});

it('does not retry a snap without an idempotency key after a 500 with the internal error code', function () {
    $history = [];
    $client = snapsbrew([json(['message' => 'The server could not create the snap.', 'code' => 'internal_error'], 500), json(snapPayload(), 201)], $history, noSleep(2));

    expect(fn () => $client->snaps()->create('tpl'))->toThrow(ServerException::class)
        ->and($history)->toHaveCount(1);
});

it('grows the delay exponentially with jitter', function () {
    $policy = new RetryPolicy(retries: 5, baseDelayMs: 100, maxDelayMs: 1000);

    foreach ([1 => 100, 2 => 200, 3 => 400, 4 => 800, 5 => 1000] as $attempt => $ceiling) {
        for ($i = 0; $i < 20; $i++) {
            expect($policy->delay($attempt))->toBeGreaterThanOrEqual(intdiv($ceiling, 2))->toBeLessThanOrEqual($ceiling);
        }
    }
});

it('uses Retry-After in place of the computed delay', function () {
    expect((new RetryPolicy)->delay(1, 7))->toBe(7000);
});

it('reads Retry-After as seconds or as an HTTP date', function () {
    $date = gmdate('D, d M Y H:i:s \G\M\T', time() + 30);

    expect((new Response(429, '', ['retry-after' => ['5']]))->retryAfter())->toBe(5)
        ->and((new Response(429, '', ['retry-after' => [$date]]))->retryAfter())->toBeGreaterThanOrEqual(28)->toBeLessThanOrEqual(30)
        ->and((new Response(429, '', ['retry-after' => ['soon']]))->retryAfter())->toBeNull()
        ->and((new Response(429, ''))->retryAfter())->toBeNull();
});

it('sleeps for real when no sleep callback is given', function () {
    $start = microtime(true);

    (new RetryPolicy)->sleep(20);

    expect(microtime(true) - $start)->toBeGreaterThanOrEqual(0.015);
});
