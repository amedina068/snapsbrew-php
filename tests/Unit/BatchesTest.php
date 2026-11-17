<?php

declare(strict_types=1);

use Snapsbrew\Enums\Status;
use Snapsbrew\Variants;

it('creates a batch from keyed items', function () {
    $history = [];
    $batch = snapsbrew([json(batchPayload(), 202, ['X-Request-Uuid' => 'req-9'])], $history)
        ->batches()->create('tpl', [
            'ada' => Variants::make()->text('name', 'Ada'),
            'grace' => [['layer' => 'name', 'text' => 'Grace']],
        ], title: 'October launch', webhookUrl: 'https://example.com/hook', format: 'png');

    expect($batch->uuid)->toBe('7c1f2a9e-0b44-4a1e-9f2b-5d3c8e1a7b60')
        ->and($batch->status)->toBe(Status::Processing)
        ->and($batch->isFinished())->toBeFalse()
        ->and($batch->items)->toBe([])
        ->and($batch->requestUuid)->toBe('req-9')
        ->and(body($history[0]['request']))->toBe([
            'template_id' => 'tpl',
            'items' => [
                ['variants' => [['layer' => 'name', 'text' => 'Ada']], 'identifier' => 'ada'],
                ['variants' => [['layer' => 'name', 'text' => 'Grace']], 'identifier' => 'grace'],
            ],
            'title' => 'October launch',
            'webhook_url' => 'https://example.com/hook',
            'format' => 'png',
        ]);
});

it('accepts items with explicit identifiers', function () {
    $history = [];
    snapsbrew([json(batchPayload(), 202)], $history)->batches()->create('tpl', [
        ['identifier' => 'row-1', 'variants' => [['layer' => 'name', 'text' => 'A']]],
        ['variants' => Variants::make()->text('name', 'B')],
    ], idempotencyKey: 'batch-1');

    expect(body($history[0]['request'])['items'])->toBe([
        ['variants' => [['layer' => 'name', 'text' => 'A']], 'identifier' => 'row-1'],
        ['variants' => [['layer' => 'name', 'text' => 'B']]],
    ])->and(body($history[0]['request'])['idempotency_key'])->toBe('batch-1');
});

it('reads a finished batch with its items', function () {
    $history = [];
    $batch = snapsbrew([json(batchPayload([
        'status' => 'completed', 'processed_rows' => 2, 'completed' => 1, 'pending' => 0, 'failed' => 1,
        'items' => [
            ['id' => '1', 'identifier' => 'row-1', 'status' => 'completed', 'media_url' => 'https://x/1.png', 'last_error' => null],
            ['id' => '2', 'identifier' => 'row-2', 'status' => 'failed', 'media_url' => null, 'last_error' => 'Render failed.'],
        ],
    ]))], $history)->batches()->find('7c1f2a9e-0b44-4a1e-9f2b-5d3c8e1a7b60');

    expect($batch->isFinished())->toBeTrue()
        ->and($batch->failed)->toBe(1)
        ->and($batch->items)->toHaveCount(2)
        ->and($batch->completedItems())->toHaveCount(1)
        ->and($batch->item('row-2')?->lastError)->toBe('Render failed.')
        ->and($batch->item('row-2')?->status)->toBe(Status::Failed)
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/v1/snaps/batches/7c1f2a9e-0b44-4a1e-9f2b-5d3c8e1a7b60');
});

it('cancels a batch', function () {
    $history = [];
    $batch = snapsbrew([json(batchPayload(['status' => 'canceled', 'pending' => 0, 'canceled' => 2, 'items' => []]))], $history)
        ->batches()->cancel('b-1');

    expect($batch->status)->toBe(Status::Canceled)
        ->and($history[0]['request']->getMethod())->toBe('POST')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/v1/snaps/batches/b-1/cancel');
});

it('refuses a batch item with bad variants', function () {
    snapsbrew()->batches()->create('tpl', [['variants' => 'nope']]);
})->throws(InvalidArgumentException::class);
