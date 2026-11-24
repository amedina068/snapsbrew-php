<?php

declare(strict_types=1);

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Snapsbrew\Exceptions\ApiException;
use Snapsbrew\Exceptions\AuthenticationException;
use Snapsbrew\Exceptions\AuthorizationException;
use Snapsbrew\Exceptions\ConflictException;
use Snapsbrew\Exceptions\ConnectionException;
use Snapsbrew\Exceptions\NotFoundException;
use Snapsbrew\Exceptions\RateLimitException;
use Snapsbrew\Exceptions\ServerException;
use Snapsbrew\Exceptions\SnapsbrewException;
use Snapsbrew\Exceptions\ValidationException;
use Snapsbrew\Snapsbrew;

it('throws a typed exception for each status', function (int $status, string $class, string $message) {
    $client = snapsbrew([json(['message' => $message], $status, ['X-Request-Uuid' => 'req-1'])]);

    try {
        $client->account();
        $this->fail('No exception was thrown.');
    } catch (ApiException $exception) {
        expect($exception)->toBeInstanceOf($class)
            ->toBeInstanceOf(SnapsbrewException::class)
            ->and($exception->getMessage())->toBe($message)
            ->and($exception->status)->toBe($status)
            ->and($exception->getCode())->toBe($status)
            ->and($exception->requestUuid())->toBe('req-1');
    }
})->with([
    'authentication' => [401, AuthenticationException::class, 'Unauthenticated.'],
    'authorization' => [403, AuthorizationException::class, 'Invalid ability provided.'],
    'not found' => [404, NotFoundException::class, 'Not found.'],
    'conflict' => [409, ConflictException::class, 'A request with this idempotency_key is already in progress.'],
    'validation' => [422, ValidationException::class, 'The given data was invalid.'],
    'rate limit' => [429, RateLimitException::class, 'Too Many Attempts.'],
    'server' => [500, ServerException::class, 'Server Error'],
    'bad gateway' => [502, ServerException::class, 'Bad Gateway'],
    'other client error' => [418, ApiException::class, 'I am a teapot'],
]);

it('gives the field errors of a validation error', function () {
    try {
        snapsbrew([json([
            'message' => 'The title field is required. (and 1 more error)',
            'errors' => ['title' => ['The title field is required.'], 'width' => ['Send a size.', 'Too wide.']],
        ], 422)])->templates()->create('');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe([
            'title' => ['The title field is required.'],
            'width' => ['Send a size.', 'Too wide.'],
        ])->and($exception->first('width'))->toBe('Send a size.')
            ->and($exception->first('height'))->toBeNull();

        return;
    }

    $this->fail('No validation exception was thrown.');
});

it('gives an empty error list when the validation body has no errors', function () {
    try {
        snapsbrew([json(['message' => 'The file must be an image.'], 422)])->account();
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe([]);

        return;
    }

    $this->fail('No validation exception was thrown.');
});

it('gives the Retry-After value of a rate limit error', function () {
    try {
        snapsbrew([json(['message' => 'Too Many Attempts.'], 429, ['Retry-After' => '42'])])->account();
    } catch (RateLimitException $exception) {
        expect($exception->retryAfter())->toBe(42);

        return;
    }

    $this->fail('No rate limit exception was thrown.');
});

it('gives the code of a conflict', function () {
    try {
        snapsbrew([json(['code' => 'editor_document_conflict'], 409)])->account();
    } catch (ConflictException $exception) {
        expect($exception->errorCode())->toBe('editor_document_conflict')
            ->and($exception->getMessage())->toBe('The Snapsbrew instance answered with HTTP status 409 (editor_document_conflict).');

        return;
    }

    $this->fail('No conflict exception was thrown.');
});

it('maps a 403 from the snap endpoint to the authorization error', function () {
    try {
        snapsbrew([json(['message' => 'This action is unauthorized.'], 403)])->snaps()->create('tpl');
    } catch (AuthorizationException $exception) {
        expect($exception->getMessage())->toBe('This action is unauthorized.')
            ->and($exception->status)->toBe(403);

        return;
    }

    $this->fail('No authorization exception was thrown.');
});

it('gives the code of each idempotency conflict', function (string $code, bool $inProgress) {
    try {
        snapsbrew([json(['message' => 'The idempotency_key cannot be used.', 'code' => $code], 409)])
            ->snaps()->create('tpl', idempotencyKey: 'order-1');
    } catch (ConflictException $exception) {
        expect($exception->errorCode())->toBe($code)
            ->and($exception->inProgress())->toBe($inProgress)
            ->and($exception->getMessage())->toBe('The idempotency_key cannot be used.');

        return;
    }

    $this->fail('No conflict exception was thrown.');
})->with([
    'in progress' => [ConflictException::IDEMPOTENCY_KEY_IN_PROGRESS, true],
    'belongs to a snap' => [ConflictException::IDEMPOTENCY_KEY_BELONGS_TO_SNAP, false],
    'belongs to a batch' => [ConflictException::IDEMPOTENCY_KEY_BELONGS_TO_BATCH, false],
]);

it('reads the generic body of a 500 from the snap endpoint', function () {
    $message = 'The server could not create the snap. Try again later. If the error continues, contact support and give the X-Request-Uuid header of this response.';

    try {
        snapsbrew([json(['message' => $message, 'code' => 'internal_error'], 500, ['X-Request-Uuid' => 'req-9'])])
            ->snaps()->create('tpl');
    } catch (ServerException $exception) {
        expect($exception->getMessage())->toBe($message)
            ->and($exception->errorCode())->toBe('internal_error')
            ->and($exception->requestUuid())->toBe('req-9');

        return;
    }

    $this->fail('No server exception was thrown.');
});

it('reads the generic body of a 500 from the batch endpoint', function () {
    $message = 'The server could not create the batch. Try again later. If the error continues, contact support and give the X-Request-Uuid header of this response.';
    $history = [];

    try {
        snapsbrew([json(['message' => $message, 'code' => 'internal_error'], 500, ['X-Request-Uuid' => 'req-10'])], $history)
            ->batches()->create('tpl', [[['layer' => 'a', 'text' => 'A']]]);
    } catch (ServerException $exception) {
        expect($exception->getMessage())->toBe($message)
            ->and($exception->errorCode())->toBe('internal_error')
            ->and($exception->requestUuid())->toBe('req-10')
            ->and($history)->toHaveCount(1);

        return;
    }

    $this->fail('No server exception was thrown.');
});

it('gives the rate limit headers of a rate limit error', function () {
    try {
        snapsbrew([json(['message' => 'Too Many Attempts.'], 429, ['Retry-After' => '30', 'X-RateLimit-Limit' => '60', 'X-RateLimit-Remaining' => '0'])])->account();
    } catch (RateLimitException $exception) {
        expect($exception->limit())->toBe(60)
            ->and($exception->remaining())->toBe(0);

        return;
    }

    $this->fail('No rate limit exception was thrown.');
});

it('gives null rate limit values when the headers are missing', function () {
    try {
        snapsbrew([json(['message' => 'Too Many Attempts.'], 429)])->account();
    } catch (RateLimitException $exception) {
        expect($exception->limit())->toBeNull()
            ->and($exception->remaining())->toBeNull()
            ->and($exception->errorCode())->toBeNull();

        return;
    }

    $this->fail('No rate limit exception was thrown.');
});

it('uses a default message when the body is not JSON', function () {
    try {
        snapsbrew([new Response(503, [], '<html>down</html>')])->account();
    } catch (ServerException $exception) {
        expect($exception->getMessage())->toBe('The Snapsbrew instance answered with HTTP status 503.')
            ->and($exception->body())->toBe([]);

        return;
    }

    $this->fail('No server exception was thrown.');
});

it('throws a connection exception when the instance cannot be reached', function () {
    snapsbrew([new ConnectException('cURL error 7: Failed to connect to snapsbrew.test port 443', new Request('GET', 'https://snapsbrew.test'))])
        ->account();
})->throws(ConnectionException::class, 'Failed to connect');

it('knows when a connection error happened before the request was sent', function (string $message, bool $sent) {
    $exception = ConnectionException::from(new ConnectException($message, new Request('GET', 'https://snapsbrew.test')));

    expect($exception->requestSent)->toBe($sent);
})->with([
    'refused' => ['cURL error 7: Failed to connect to snapsbrew.test port 443: Connection refused', false],
    'dns' => ['cURL error 6: Could not resolve host: snapsbrew.test', false],
    'connect timeout' => ['cURL error 28: Connection timed out after 10001 milliseconds', false],
    'read timeout' => ['cURL error 28: Operation timed out after 30001 milliseconds with 0 bytes received', true],
    'reset' => ['cURL error 56: Recv failure: Connection reset by peer', true],
]);

it('maps a 403 to the authorization error on every bearer operation', function (Closure $call) {
    $client = snapsbrew([json(['message' => 'Invalid ability provided.'], 403)]);

    try {
        $call($client);
    } catch (AuthorizationException $exception) {
        expect($exception->getMessage())->toBe('Invalid ability provided.')
            ->and($exception->status)->toBe(403);

        return;
    }

    $this->fail('No authorization exception was thrown.');
})->with([
    'account' => [fn (Snapsbrew $client) => $client->account()],
    'templates list' => [fn (Snapsbrew $client) => $client->templates()->list()],
    'templates find' => [fn (Snapsbrew $client) => $client->templates()->find('tpl')],
    'templates sizes' => [fn (Snapsbrew $client) => $client->templates()->sizes()],
    'templates create' => [fn (Snapsbrew $client) => $client->templates()->create('Card', size: 'open_graph')],
    'templates update' => [fn (Snapsbrew $client) => $client->templates()->update('tpl', 'Card')],
    'templates delete' => [fn (Snapsbrew $client) => $client->templates()->delete('tpl')],
    'templates publish' => [fn (Snapsbrew $client) => $client->templates()->publish('tpl')],
    'templates preview' => [fn (Snapsbrew $client) => $client->templates()->preview('tpl')],
    'documents find' => [fn (Snapsbrew $client) => $client->documents()->find('tpl')],
    'documents replace' => [fn (Snapsbrew $client) => $client->documents()->replace('tpl', revision: 1, document: [])],
    'documents apply' => [fn (Snapsbrew $client) => $client->documents()->apply('tpl', revision: 1, operations: [])],
    'documents preflight' => [fn (Snapsbrew $client) => $client->documents()->preflight('tpl')],
    'snaps create' => [fn (Snapsbrew $client) => $client->snaps()->create('tpl')],
    'snaps collection' => [fn (Snapsbrew $client) => $client->snaps()->createFromCollection('col')],
    'snaps preview' => [fn (Snapsbrew $client) => $client->snaps()->preview('tpl')],
    'batches create' => [fn (Snapsbrew $client) => $client->batches()->create('tpl', [[]])],
    'batches find' => [fn (Snapsbrew $client) => $client->batches()->find('batch')],
    'batches cancel' => [fn (Snapsbrew $client) => $client->batches()->cancel('batch')],
    'screenshots list' => [fn (Snapsbrew $client) => $client->screenshots()->list()],
    'screenshots find' => [fn (Snapsbrew $client) => $client->screenshots()->find('shot')],
    'screenshots create' => [fn (Snapsbrew $client) => $client->screenshots()->create('https://example.com')],
    'media list' => [fn (Snapsbrew $client) => $client->media()->list()],
    'media upload' => [fn (Snapsbrew $client) => $client->media()->upload(__FILE__)],
    'guides design' => [fn (Snapsbrew $client) => $client->guides()->design()],
    'guides platform' => [fn (Snapsbrew $client) => $client->guides()->platform('instagram')],
    'tags list' => [fn (Snapsbrew $client) => $client->tags()->list()],
    'tags create' => [fn (Snapsbrew $client) => $client->tags()->create('Launch')],
    'templates set tags' => [fn (Snapsbrew $client) => $client->templates()->setTags('tpl', [1])],
]);

it('does not read the text of a 404 message', function () {
    try {
        snapsbrew([json(['message' => 'No query results for model [App\\Models\\Template].'], 404)])->templates()->find('tpl');
    } catch (NotFoundException $exception) {
        expect($exception->getMessage())->toBe('No query results for model [App\\Models\\Template].')
            ->and($exception->available())->toBe([]);

        return;
    }

    $this->fail('No not found exception was thrown.');
});

it('gives the valid keys of a guide that does not exist', function (string $method) {
    $available = ['instagram', 'linkedin', 'x'];

    try {
        snapsbrew([json(['message' => 'No platform guide matches `nope`.', 'available' => $available], 404)])->guides()->{$method}('nope');
    } catch (NotFoundException $exception) {
        expect($exception->available())->toBe($available);

        return;
    }

    $this->fail('No not found exception was thrown.');
})->with(['platform', 'purpose']);

it('throws a validation error for a bad cursor', function (Closure $call) {
    $client = snapsbrew([json(['message' => 'The cursor is not valid.', 'errors' => ['cursor' => ['The cursor is not valid.']]], 422)]);

    try {
        $call($client);
    } catch (ValidationException $exception) {
        expect($exception->first('cursor'))->toBe('The cursor is not valid.');

        return;
    }

    $this->fail('No validation exception was thrown.');
})->with([
    'templates' => [fn (Snapsbrew $client) => $client->templates()->list('bad')],
    'screenshots' => [fn (Snapsbrew $client) => $client->screenshots()->list('bad')],
]);
