<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use Snapsbrew\Enums\Status;
use Snapsbrew\Exceptions\NotFoundException;
use Snapsbrew\Exceptions\ValidationException;
use Snapsbrew\Laravel\Facades\Snapsbrew;
use Snapsbrew\Laravel\Testing\RecordedRequest;
use Snapsbrew\Laravel\Testing\SnapsbrewFake;
use Snapsbrew\Snapsbrew as Client;
use Snapsbrew\Variants;

it('swaps the client for a fake', function () {
    $fake = Snapsbrew::fake();

    expect($fake)->toBeInstanceOf(SnapsbrewFake::class)
        ->and(app(Client::class))->toBe($fake);
});

it('answers each endpoint with sample data', function () {
    Snapsbrew::fake();

    $snap = Snapsbrew::snaps()->create('tpl', Variants::make()->text('title', 'Hello'));
    $batch = Snapsbrew::batches()->create('tpl', ['a' => Variants::make()->text('title', 'A')]);
    $found = Snapsbrew::batches()->find($batch->uuid);

    expect($snap->isCompleted())->toBeTrue()
        ->and($snap->mediaUrl)->toStartWith('https://snapsbrew.test/storage/snaps/')
        ->and($snap->templateId)->toBe('tpl')
        ->and($snap->variants)->toBe([['layer' => 'title', 'text' => 'Hello']])
        ->and($batch->status)->toBe(Status::Pending)
        ->and($batch->totalRows)->toBe(1)
        ->and($found->isFinished())->toBeTrue()
        ->and(Snapsbrew::account()->email)->toBe('test@example.com')
        ->and(Snapsbrew::templates()->list()->first()?->layer('title'))->not->toBeNull()
        ->and(Snapsbrew::templates()->find('abc')->uuid)->toBe('abc')
        ->and(Snapsbrew::templates()->sizes())->toHaveCount(1)
        ->and(Snapsbrew::templates()->publish('abc')->publishedVersion)->toBe(1)
        ->and(Snapsbrew::screenshots()->create('https://example.com')->url)->toBe('https://example.com')
        ->and(Snapsbrew::screenshots()->list()->first()?->imageUrl)->toStartWith('https://snapsbrew.test/storage/screenshots/')
        ->and(Snapsbrew::media()->upload(__FILE__)->mimeType)->toBe('image/png')
        ->and(Snapsbrew::snaps()->preview('tpl')->html)->toContain('preview')
        ->and(Snapsbrew::snaps()->create('tpl', async: true, webhookUrl: 'https://example.com/hook')->isPending())->toBeTrue()
        ->and(Snapsbrew::snaps()->createFromCollection('col'))->toHaveCount(1)
        ->and(Snapsbrew::templates()->setTags('tpl', [1, 2]))->toHaveCount(2)
        ->and(Snapsbrew::media()->list()->total)->toBe(0)
        ->and(Snapsbrew::guides()->design()->data)->toBe([]);
});

it('asserts that a snap was created', function () {
    Snapsbrew::fake();

    Snapsbrew::snaps()->create('tpl-1', [['layer' => 'title', 'text' => 'Hello']]);

    Snapsbrew::assertSnapCreated();
    Snapsbrew::assertSnapCreated('tpl-1');
    Snapsbrew::assertSnapCreated(fn (RecordedRequest $request) => $request['variants'][0]['text'] === 'Hello');
    Snapsbrew::assertSnapNotCreated('tpl-2');
    Snapsbrew::assertSnapCreatedTimes(1, 'tpl-1');
    Snapsbrew::assertSentCount(1);
});

it('fails when the expected snap was not created', function () {
    Snapsbrew::fake();

    Snapsbrew::snaps()->create('tpl-1');

    Snapsbrew::assertSnapCreated('tpl-2');
})->throws(AssertionFailedError::class, 'The expected snap was not created.');

it('fails when a snap was created unexpectedly', function () {
    Snapsbrew::fake();

    Snapsbrew::snaps()->create('tpl-1');

    Snapsbrew::assertSnapNotCreated();
})->throws(AssertionFailedError::class);

it('asserts batches, screenshots and previews', function () {
    Snapsbrew::fake();

    Snapsbrew::batches()->create('tpl', [Variants::make()->text('title', 'A')]);
    Snapsbrew::screenshots()->create('https://example.com');
    Snapsbrew::templates()->preview('tpl-9');

    Snapsbrew::assertBatchCreated('tpl');
    Snapsbrew::assertBatchNotCreated('other');
    Snapsbrew::assertScreenshotCreated('https://example.com');
    Snapsbrew::assertPreviewed('tpl-9');
    Snapsbrew::assertSent(fn (RecordedRequest $request) => $request->is('POST', 'v1/screenshots'));
    Snapsbrew::assertNotSent(fn (RecordedRequest $request) => $request->is('DELETE', 'v1/templates/*'));
});

it('asserts that nothing was sent', function () {
    Snapsbrew::fake();

    Snapsbrew::signedUrl('tpl')->text('title', 'A')->url();

    Snapsbrew::assertNothingSent();
});

it('sends a stubbed error response', function () {
    Snapsbrew::fake([
        'v1/snaps' => Snapsbrew::response(['message' => 'Invalid.', 'errors' => ['template_id' => ['Unknown.']]], 422),
    ]);

    try {
        Snapsbrew::snaps()->create('missing');
    } catch (ValidationException $exception) {
        expect($exception->first('template_id'))->toBe('Unknown.');
        Snapsbrew::assertSnapCreated('missing');

        return;
    }

    $this->fail('No validation exception was thrown.');
});

it('accepts stubs with a method, a wildcard, a closure, an array or a status', function () {
    Snapsbrew::fake([
        'GET v1/templates/*' => fn (RecordedRequest $request) => ['data' => [
            'uuid' => substr($request->path, strlen('v1/templates/')), 'title' => 'Stubbed', 'width' => 10, 'height' => 20,
        ]],
        'v1/auth' => 404,
    ]);

    expect(Snapsbrew::templates()->find('xyz')->title)->toBe('Stubbed')
        ->and(fn () => Snapsbrew::account())->toThrow(NotFoundException::class);
});

it('adds stubs when fake() is called again', function () {
    $first = Snapsbrew::fake();
    $second = Snapsbrew::fake(['v1/auth' => ['data' => ['email' => 'x@y.z', 'name' => 'X', 'company' => null]]]);

    expect($second)->toBe($first)
        ->and(Snapsbrew::account()->email)->toBe('x@y.z');
});

it('records the request details', function () {
    $fake = Snapsbrew::fake();

    Snapsbrew::snaps()->create('tpl', format: 'webp');

    $request = $fake->recorded()[0];

    expect($request->method)->toBe('POST')
        ->and($request->path)->toBe('v1/snaps')
        ->and($request->url)->toBe('https://snapsbrew.test/api/v1/snaps')
        ->and($request['format'])->toBe('webp')
        ->and(isset($request['format']))->toBeTrue()
        ->and($request->header('Authorization'))->toBe('Bearer fake-token');
});

it('keeps signed URLs working with the configured key', function () {
    Snapsbrew::fake();

    expect(Snapsbrew::signedUrl('tpl')->url())->toStartWith('https://snapsbrew.test/signed-url/image.jpg?template_id=tpl&signature=');
});

it('answers the editor document endpoints with sample data', function () {
    Snapsbrew::fake();

    $document = Snapsbrew::documents()->find('tpl');
    $replaced = Snapsbrew::documents()->replace('tpl', revision: $document->revision, document: ['version' => 2, 'layers' => [], 'groups' => []]);
    $applied = Snapsbrew::documents()->apply('tpl', revision: $replaced->revision, operations: []);

    expect($document->revision)->toBe(1)
        ->and($replaced->revision)->toBe(2)
        ->and($replaced->document)->toBe(['version' => 2, 'layers' => [], 'groups' => []])
        ->and($applied->revision)->toBe(3)
        ->and(Snapsbrew::documents()->preflight('tpl')->passes())->toBeTrue();
});
