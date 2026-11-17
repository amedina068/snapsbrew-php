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
            ->and($exception->getMessage())->toBe('The Snapsbrew instance answered with HTTP status 409.');

        return;
    }

    $this->fail('No conflict exception was thrown.');
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
