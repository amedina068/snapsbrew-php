<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Snapsbrew\Enums\Status;
use Snapsbrew\Variants;

it('creates a snap with variants', function () {
    $history = [];
    $client = snapsbrew([json(snapPayload(), 201, ['X-Request-Uuid' => 'req-1'])], $history);

    $snap = $client->snaps()->create('141e8454-8aa1-420a-bd0b-18a85ba949a2', [
        ['layer' => 'title', 'text' => 'Hello'],
    ]);

    expect($snap->id)->toBe('9f1c2d3e')
        ->and($snap->status)->toBe(Status::Completed)
        ->and($snap->isCompleted())->toBeTrue()
        ->and($snap->mediaUrl)->toBe('https://snapsbrew.test/storage/snaps/9f1c2d3e.png')
        ->and($snap->width)->toBe(1200)
        ->and($snap->templateTitle)->toBe('Launch card')
        ->and($snap->requestUuid)->toBe('req-1')
        ->and($snap->replayed)->toBeFalse()
        ->and(body($history[0]['request']))->toBe([
            'template_id' => '141e8454-8aa1-420a-bd0b-18a85ba949a2',
            'variants' => [['layer' => 'title', 'text' => 'Hello']],
        ]);
});

it('sends every option of a snap', function () {
    $history = [];
    snapsbrew([json(snapPayload(['status' => 'pending', 'media_url' => null, 'is_async' => true]), 202)], $history)
        ->snaps()->create(
            'tpl',
            Variants::make()->image('cover', 'https://example.com/c.jpg'),
            format: 'webp',
            type: 'image',
            transparent: true,
            async: true,
            webhookUrl: 'https://example.com/hook',
            idempotencyKey: 'order-42',
        );

    expect(body($history[0]['request']))->toBe([
        'template_id' => 'tpl',
        'variants' => [['layer' => 'cover', 'image_url' => 'https://example.com/c.jpg']],
        'format' => 'webp',
        'type' => 'image',
        'transparent' => true,
        'async' => true,
        'webhook_url' => 'https://example.com/hook',
        'idempotency_key' => 'order-42',
    ]);
});

it('reads a queued snap', function () {
    $snap = snapsbrew([json(snapPayload(['status' => 'pending', 'media_url' => null, 'completed_at' => null, 'is_async' => true]), 202)])
        ->snaps()->create('tpl', async: true, webhookUrl: 'https://example.com/hook');

    expect($snap->isPending())->toBeTrue()
        ->and($snap->isAsync)->toBeTrue()
        ->and($snap->mediaUrl)->toBeNull()
        ->and($snap->completedAt)->toBeNull();
});

it('marks a replayed snap', function () {
    $snap = snapsbrew([json(snapPayload(), 201, ['X-Idempotent-Replay' => 'true'])])
        ->snaps()->create('tpl', idempotencyKey: 'key');

    expect($snap->replayed)->toBeTrue();
});

it('reads a snap in a data envelope', function () {
    $snap = snapsbrew([json(['data' => snapPayload()], 201)])->snaps()->create('tpl');

    expect($snap->id)->toBe('9f1c2d3e');
});

it('creates one snap for each template of a collection', function () {
    $history = [];
    $snaps = snapsbrew([json([snapPayload(['id' => 'a']), snapPayload(['id' => 'b'])], 201)], $history)
        ->snaps()->createFromCollection('collection-1', [['layer' => 'title', 'text' => 'Hi']]);

    expect($snaps)->toHaveCount(2)
        ->and($snaps[1]->id)->toBe('b')
        ->and(body($history[0]['request']))->toBe([
            'collection_id' => 'collection-1',
            'variants' => [['layer' => 'title', 'text' => 'Hi']],
        ]);
});

it('previews a snap', function () {
    $history = [];
    $preview = snapsbrew([new Response(200, ['Content-Type' => 'text/html'], '<html>watermarked</html>')], $history)
        ->snaps()->preview('tpl', [['layer' => 'title', 'text' => 'Hi']]);

    parse_str($history[0]['request']->getUri()->getQuery(), $query);

    expect($preview->html)->toBe('<html>watermarked</html>')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/v1/snaps/preview')
        ->and($query)->toBe(['template_id' => 'tpl', 'variants' => [['layer' => 'title', 'text' => 'Hi']]]);
});
