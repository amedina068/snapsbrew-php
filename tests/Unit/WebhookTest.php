<?php

declare(strict_types=1);

use Snapsbrew\Data\Batch;
use Snapsbrew\Data\Snap;
use Snapsbrew\Enums\Status;
use Snapsbrew\Exceptions\MissingCredentialsException;
use Snapsbrew\Exceptions\WebhookVerificationException;
use Snapsbrew\Webhooks\VerificationFailure;
use Snapsbrew\Webhooks\Webhook;
use Snapsbrew\Webhooks\WebhookEvent;

/**
 * The shared vectors in `webhook-vectors.json`.
 *
 * @return array<string, mixed>
 */
function webhookVectors(): array
{
    return json_decode((string) file_get_contents(__DIR__.'/../Fixtures/webhook-vectors.json'), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * @return array<string, array{array<string, mixed>}>
 */
function webhookCases(): array
{
    $cases = [];

    foreach (webhookVectors()['cases'] as $case) {
        $cases[$case['name']] = [$case];
    }

    return $cases;
}

/**
 * Sign a body the same way as the instance.
 *
 * @return array<string, string>
 */
function signedHeaders(string $body, int $timestamp, string $secret = 'whsec_test'): array
{
    return [
        'X-Snapsbrew-Delivery' => '3f2b6c1e-8a4d-4e5f-9b0a-1c2d3e4f5a6b',
        'X-Snapsbrew-Timestamp' => (string) $timestamp,
        'X-Snapsbrew-Signature' => hash_hmac('sha256', "{$timestamp}.{$body}", $secret),
    ];
}

it('matches each shared test vector', function (array $case) {
    $vectors = webhookVectors();
    $webhook = new Webhook($vectors['secret'], tolerance: $vectors['tolerance_seconds']);

    if ($case['valid']) {
        $event = $webhook->verify($case['body'], $case['headers'], now: $vectors['now']);

        expect($event->type)->toBe($case['name'])
            ->and($event->payload)->toBe(json_decode($case['body'], true))
            ->and($event->timestamp)->toBe((int) $case['headers']['X-Snapsbrew-Timestamp'])
            ->and($event->deliveryId)->toBe($case['headers']['X-Snapsbrew-Delivery']);

        return;
    }

    expect(fn () => $webhook->verify($case['body'], $case['headers'], now: $vectors['now']))
        ->toThrow(WebhookVerificationException::class);
})->with(webhookCases());

it('signs the documented string of each valid vector', function (array $case) {
    if (! $case['valid']) {
        expect($case)->not->toHaveKey('signed_string');

        return;
    }

    $timestamp = $case['headers']['X-Snapsbrew-Timestamp'];

    expect("{$timestamp}.{$case['body']}")->toBe($case['signed_string'])
        ->and(hash_hmac('sha256', $case['signed_string'], webhookVectors()['secret']))->toBe($case['headers']['X-Snapsbrew-Signature']);
})->with(webhookCases());

it('reads the snap of a snap event', function () {
    $case = webhookCases()['snap.completed'][0];

    $event = (new Webhook(webhookVectors()['secret']))->verify($case['body'], $case['headers'], now: webhookVectors()['now']);

    expect($event->is(WebhookEvent::SNAP_COMPLETED))->toBeTrue()
        ->and($event->snap())->toBeInstanceOf(Snap::class)
        ->and($event->snap()?->isCompleted())->toBeTrue()
        ->and($event->snap()?->mediaUrl)->toBe('https://snapsbrew.example/storage/snaps/9f1c2d3e.png')
        ->and($event->snap()?->templateTitle)->toBe('¿Launch card?')
        ->and($event->batch())->toBeNull();
});

it('reads a failed snap with an empty media URL', function () {
    $case = webhookCases()['snap.failed'][0];

    $event = (new Webhook(webhookVectors()['secret']))->verify($case['body'], $case['headers'], now: webhookVectors()['now']);

    expect($event->is(WebhookEvent::SNAP_FAILED))->toBeTrue()
        ->and($event->snap()?->isFailed())->toBeTrue()
        ->and($event->snap()?->lastError)->toBe(Snap::ERROR_RENDER_FAILED)
        ->and($event->snap()?->mediaUrl)->toBeNull();
});

it('reads the batch of a batch event', function () {
    $case = webhookCases()['snap-import.completed'][0];

    $event = (new Webhook(webhookVectors()['secret']))->verify($case['body'], $case['headers'], now: webhookVectors()['now']);

    expect($event->is(WebhookEvent::BATCH_COMPLETED))->toBeTrue()
        ->and($event->batch())->toBeInstanceOf(Batch::class)
        ->and($event->batch()?->status)->toBe(Status::Completed)
        ->and($event->batch()?->completed)->toBe(2)
        ->and($event->template())->toBe(['uuid' => '141e8454-8aa1-420a-bd0b-18a85ba949a2', 'title' => 'Launch card'])
        ->and($event->snap())->toBeNull();
});

it('names each event type', function () {
    expect([
        WebhookEvent::SNAP_COMPLETED,
        WebhookEvent::SNAP_FAILED,
        WebhookEvent::BATCH_COMPLETED,
        WebhookEvent::BATCH_FAILED,
        WebhookEvent::BATCH_CANCELED,
    ])->toBe(['snap.completed', 'snap.failed', 'snap-import.completed', 'snap-import.failed', 'snap-import.canceled']);
});

it('refuses a timestamp outside the tolerance', function (int $offset, bool $valid) {
    $body = '{"event":"snap.completed"}';
    $now = 1767225600;
    $webhook = new Webhook('whsec_test');

    $verify = fn () => $webhook->verify($body, signedHeaders($body, $now + $offset), now: $now);

    if ($valid) {
        expect($verify()->type)->toBe('snap.completed');

        return;
    }

    try {
        $verify();
    } catch (WebhookVerificationException $exception) {
        expect($exception->reason)->toBe(VerificationFailure::Timestamp);

        return;
    }

    $this->fail('The old delivery was accepted.');
})->with([
    'now' => [0, true],
    '300 seconds before' => [-300, true],
    '300 seconds after' => [300, true],
    '301 seconds before' => [-301, false],
    '301 seconds after' => [301, false],
]);

it('accepts a custom tolerance', function () {
    $body = '{"event":"snap.completed"}';
    $now = 1767225600;

    expect((new Webhook('whsec_test', tolerance: 600))->verify($body, signedHeaders($body, $now - 599), now: $now)->type)
        ->toBe('snap.completed')
        ->and(fn () => (new Webhook('whsec_test', tolerance: 10))->verify($body, signedHeaders($body, $now - 11), now: $now))
        ->toThrow(WebhookVerificationException::class);
});

it('uses the clock when no time is given', function () {
    $body = '{"event":"snap.completed"}';

    expect((new Webhook('whsec_test'))->verify($body, signedHeaders($body, time()))->type)->toBe('snap.completed');
});

it('reads the headers in any case and as lists', function () {
    $body = '{"event":"snap.completed"}';
    $headers = [];

    foreach (signedHeaders($body, 1767225600) as $name => $value) {
        $headers[strtolower($name)] = [$value];
    }

    $event = (new Webhook('whsec_test'))->verify($body, $headers, now: 1767225600);

    expect($event->deliveryId)->toBe('3f2b6c1e-8a4d-4e5f-9b0a-1c2d3e4f5a6b');
});

it('refuses a delivery with a missing header', function (string $header) {
    $body = '{"event":"snap.completed"}';
    $headers = signedHeaders($body, 1767225600);
    unset($headers[$header]);

    try {
        (new Webhook('whsec_test'))->verify($body, $headers, now: 1767225600);
    } catch (WebhookVerificationException $exception) {
        expect($exception->reason)->toBe(VerificationFailure::Signature);

        return;
    }

    $this->fail('The delivery was accepted.');
})->with(['X-Snapsbrew-Signature', 'X-Snapsbrew-Timestamp']);

it('gives a null delivery ID when the header is missing', function () {
    $body = '{"event":"snap.completed"}';
    $headers = signedHeaders($body, 1767225600);
    unset($headers['X-Snapsbrew-Delivery']);

    expect((new Webhook('whsec_test'))->verify($body, $headers, now: 1767225600)->deliveryId)->toBeNull();
});

it('refuses a signed body that is not a JSON object', function () {
    $body = 'not json';

    try {
        (new Webhook('whsec_test'))->verify($body, signedHeaders($body, 1767225600), now: 1767225600);
    } catch (WebhookVerificationException $exception) {
        expect($exception->reason)->toBe(VerificationFailure::Payload);

        return;
    }

    $this->fail('The delivery was accepted.');
});

it('refuses an empty secret', function () {
    new Webhook('');
})->throws(MissingCredentialsException::class, 'SNAPSBREW_WEBHOOK_SECRET');

it('keeps the secret out of debug output', function () {
    expect(print_r(new Webhook('whsec_private'), true))->not->toContain('whsec_private');
});
