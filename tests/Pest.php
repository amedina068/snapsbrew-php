<?php

declare(strict_types=1);

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Psr\Http\Message\RequestInterface;
use Snapsbrew\Http\RetryPolicy;
use Snapsbrew\Snapsbrew;
use Snapsbrew\Tests\Laravel\TestCase;

uses(TestCase::class)->in('Laravel');

/**
 * A client that answers from a queue of responses and records each request.
 *
 * @param  list<PsrResponse|Throwable>  $queue
 * @param  array<int, array{request: RequestInterface}>  $history
 */
function snapsbrew(array $queue = [], array &$history = [], int|RetryPolicy $retries = 0, ?string $signingKey = 'test-signing-key'): Snapsbrew
{
    $stack = HandlerStack::create(new MockHandler($queue));
    $stack->push(Middleware::history($history));

    return new Snapsbrew(
        url: 'https://snapsbrew.test',
        token: 'test-token',
        signingKey: $signingKey,
        retries: $retries,
        httpClient: new GuzzleClient(['handler' => $stack]),
    );
}

/**
 * @param  array<array-key, mixed>  $body
 * @param  array<string, string>  $headers
 */
function json(array $body, int $status = 200, array $headers = []): PsrResponse
{
    return new PsrResponse($status, ['Content-Type' => 'application/json'] + $headers, json_encode($body, JSON_THROW_ON_ERROR));
}

/**
 * The decoded JSON body of a recorded request.
 *
 * @return array<array-key, mixed>
 */
function body(RequestInterface $request): array
{
    return json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * A retry policy that records each delay and does not sleep.
 *
 * @param  list<int>  $delays
 */
function noSleep(int $retries, array &$delays = [], int $maxRetryAfter = 60): RetryPolicy
{
    return new RetryPolicy(
        retries: $retries,
        maxRetryAfter: $maxRetryAfter,
        sleep: function (int $milliseconds) use (&$delays): void {
            $delays[] = $milliseconds;
        },
    );
}

/**
 * @return array<string, mixed>
 */
function snapPayload(array $overrides = []): array
{
    return $overrides + [
        'id' => '9f1c2d3e',
        'media_url' => 'https://snapsbrew.test/storage/snaps/9f1c2d3e.png',
        'width' => 1200,
        'height' => 630,
        'type' => 'image',
        'status' => 'completed',
        'is_async' => false,
        'webhook_url' => null,
        'completed_at' => '2026-05-01T12:00:03Z',
        'last_error' => null,
        'variants' => [['layer' => 'title', 'text' => 'Hello']],
        'template_id' => '141e8454-8aa1-420a-bd0b-18a85ba949a2',
        'template_title' => 'Launch card',
        'created_at' => '2026-05-01T12:00:00Z',
    ];
}

/**
 * The exact body of `POST /v1/media` and of each item of `GET /v1/media`.
 *
 * @return array<string, mixed>
 */
function mediaPayload(array $overrides = []): array
{
    return $overrides + [
        'id' => 12,
        'uuid' => '5b0c7a52-3f9e-4a8d-9c1e-2d7f6b4e8a10',
        'name' => 'logo',
        'file_name' => 'logo.png',
        'mime_type' => 'image/png',
        'size' => 68,
        'original_url' => 'https://x/logo.png',
        'src' => 'https://x/logo.jpg',
        'created_at' => '2026-05-01T12:00:00Z',
    ];
}

/**
 * @return array<string, mixed>
 */
function batchPayload(array $overrides = []): array
{
    return $overrides + [
        'uuid' => '7c1f2a9e-0b44-4a1e-9f2b-5d3c8e1a7b60',
        'title' => 'October launch',
        'status' => 'processing',
        'total_rows' => 2,
        'processed_rows' => 0,
        'completed' => 0,
        'pending' => 2,
        'failed' => 0,
        'canceled' => 0,
        'status_url' => 'https://snapsbrew.test/api/v1/snaps/batches/7c1f2a9e-0b44-4a1e-9f2b-5d3c8e1a7b60',
    ];
}
