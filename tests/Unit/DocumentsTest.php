<?php

declare(strict_types=1);

use Snapsbrew\Data\EditorDocument;
use Snapsbrew\Data\PreflightIssue;
use Snapsbrew\Exceptions\ConflictException;
use Snapsbrew\Exceptions\NotFoundException;
use Snapsbrew\Exceptions\ValidationException;

/**
 * @return array<string, mixed>
 */
function editorDocument(int $fontWeight = 700): array
{
    return [
        'version' => 2,
        'layers' => [['key' => 'headline', 'type' => 'text', 'properties' => [['name' => 'fontWeight', 'value' => $fontWeight]]]],
        'groups' => [],
    ];
}

it('reads the editor document and its revision', function () {
    $history = [];

    $document = snapsbrew([json(['document' => editorDocument(), 'revision' => 4])], $history)
        ->documents()->find('141e8454-8aa1-420a-bd0b-18a85ba949a2');

    expect($document)->toBeInstanceOf(EditorDocument::class)
        ->and($document->revision)->toBe(4)
        ->and($document->document)->toBe(editorDocument())
        ->and($document->layers())->toHaveCount(1)
        ->and($document->exists())->toBeTrue()
        ->and($history[0]['request']->getMethod())->toBe('GET')
        ->and((string) $history[0]['request']->getUri())->toBe('https://snapsbrew.test/api/v1/templates/141e8454-8aa1-420a-bd0b-18a85ba949a2/document');
});

it('reads a template with no draft', function () {
    $document = snapsbrew([json(['document' => null, 'revision' => 0])])->documents()->find('tpl');

    expect($document->document)->toBeNull()
        ->and($document->revision)->toBe(0)
        ->and($document->exists())->toBeFalse()
        ->and($document->layers())->toBe([]);
});

it('replaces the whole document', function () {
    $history = [];

    $document = snapsbrew([json(['document' => editorDocument(400), 'revision' => 5])], $history)
        ->documents()->replace('tpl', editorDocument(400), revision: 4);

    expect($document->revision)->toBe(5)
        ->and($history[0]['request']->getMethod())->toBe('PUT')
        ->and((string) $history[0]['request']->getUri())->toBe('https://snapsbrew.test/api/v1/templates/tpl/document')
        ->and(body($history[0]['request']))->toBe(['revision' => 4, 'document' => editorDocument(400)]);
});

it('applies a list of operations', function () {
    $history = [];
    $operations = [
        ['operation' => 'set_layer_property', 'layer_key' => 'headline', 'property' => 'fontWeight', 'value' => 700],
        ['operation' => 'set_layer_type', 'layer_key' => 'logo', 'type' => 'image'],
    ];

    $document = snapsbrew([json(['document' => editorDocument(), 'revision' => 5])], $history)
        ->documents()->apply('tpl', $operations, revision: 4);

    expect($document->revision)->toBe(5)
        ->and($history[0]['request']->getMethod())->toBe('PATCH')
        ->and(body($history[0]['request']))->toBe(['revision' => 4, 'operations' => $operations]);
});

it('lists the preflight issues', function () {
    $history = [];

    $preflight = snapsbrew([json([
        'issues' => [['code' => 'image_asset_missing', 'path' => '/layers/0/source', 'message' => 'Image layers require a pinned asset id.']],
        'revision' => 4,
    ])], $history)->documents()->preflight('tpl');

    expect($preflight->revision)->toBe(4)
        ->and($preflight->passes())->toBeFalse()
        ->and($preflight->issues)->toHaveCount(1)
        ->and($preflight->issues[0])->toBeInstanceOf(PreflightIssue::class)
        ->and($preflight->issues[0]->code)->toBe('image_asset_missing')
        ->and($preflight->issues[0]->path)->toBe('/layers/0/source')
        ->and($preflight->issues[0]->message)->toBe('Image layers require a pinned asset id.')
        ->and((string) $history[0]['request']->getUri())->toBe('https://snapsbrew.test/api/v1/templates/tpl/document/preflight');
});

it('passes a preflight with no issues', function () {
    expect(snapsbrew([json(['issues' => [], 'revision' => 4])])->documents()->preflight('tpl')->passes())->toBeTrue();
});

it('gives the current revision and document of a write conflict', function (string $method) {
    $client = snapsbrew([json([
        'code' => 'editor_document_conflict',
        'current_revision' => 5,
        'current_document' => editorDocument(400),
    ], 409)]);

    try {
        $method === 'replace'
            ? $client->documents()->replace('tpl', editorDocument(), revision: 4)
            : $client->documents()->apply('tpl', [], revision: 4);
    } catch (ConflictException $exception) {
        expect($exception->errorCode())->toBe(ConflictException::EDITOR_DOCUMENT_CONFLICT)
            ->and($exception->currentRevision())->toBe(5)
            ->and($exception->currentDocument())->toBe(editorDocument(400))
            ->and($exception->inProgress())->toBeFalse();

        return;
    }

    $this->fail('No conflict exception was thrown.');
})->with(['replace', 'apply']);

it('gives null conflict fields for another conflict', function () {
    try {
        snapsbrew([json(['message' => 'In progress.', 'code' => 'idempotency_key_in_progress'], 409)])
            ->snaps()->create('tpl');
    } catch (ConflictException $exception) {
        expect($exception->currentRevision())->toBeNull()
            ->and($exception->currentDocument())->toBeNull();

        return;
    }

    $this->fail('No conflict exception was thrown.');
});

it('gives the code of a rejected operation', function () {
    try {
        snapsbrew([json(['code' => 'editor_operation_layer_not_found'], 422)])
            ->documents()->apply('tpl', [['operation' => 'set_layer_type', 'layer_key' => 'nope', 'type' => 'text']], revision: 4);
    } catch (ValidationException $exception) {
        expect($exception->errorCode())->toBe('editor_operation_layer_not_found')
            ->and($exception->errors())->toBe([])
            ->and($exception->getMessage())->toBe('The Snapsbrew instance answered with HTTP status 422 (editor_operation_layer_not_found).');

        return;
    }

    $this->fail('No validation exception was thrown.');
});

it('gives the field errors of a document over a limit', function () {
    try {
        snapsbrew([json(['message' => 'The given data was invalid.', 'errors' => ['document' => ['editor_document_too_large']]], 422)])
            ->documents()->replace('tpl', editorDocument(), revision: 4);
    } catch (ValidationException $exception) {
        expect($exception->first('document'))->toBe('editor_document_too_large')
            ->and($exception->errorCode())->toBeNull();

        return;
    }

    $this->fail('No validation exception was thrown.');
});

it('throws a not found error for a template of another company', function (string $method) {
    $client = snapsbrew([json(['message' => 'No query results for model [App\\Models\\Template].'], 404)]);

    match ($method) {
        'find' => $client->documents()->find('tpl'),
        'replace' => $client->documents()->replace('tpl', editorDocument(), revision: 4),
        'apply' => $client->documents()->apply('tpl', [], revision: 4),
        'preflight' => $client->documents()->preflight('tpl'),
    };
})->with(['find', 'replace', 'apply', 'preflight'])->throws(NotFoundException::class);
