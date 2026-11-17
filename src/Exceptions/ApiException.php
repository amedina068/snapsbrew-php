<?php

declare(strict_types=1);

namespace Snapsbrew\Exceptions;

use Snapsbrew\Http\Response;

/**
 * The instance answered with an error status.
 *
 * The SDK throws a subclass for each known status. This class is used for the
 * other 4xx statuses.
 */
class ApiException extends SnapsbrewException
{
    final public function __construct(
        string $message,
        public readonly int $status,
        public readonly Response $response,
    ) {
        parent::__construct($message, $status);
    }

    public static function fromResponse(Response $response): ApiException
    {
        $class = match (true) {
            $response->status === 401 => AuthenticationException::class,
            $response->status === 403 => AuthorizationException::class,
            $response->status === 404 => NotFoundException::class,
            $response->status === 409 => ConflictException::class,
            $response->status === 422 => ValidationException::class,
            $response->status === 429 => RateLimitException::class,
            $response->status >= 500 => ServerException::class,
            default => ApiException::class,
        };

        return new $class(self::messageFrom($response), $response->status, $response);
    }

    /**
     * The decoded error body, or an empty array when the body is not JSON.
     *
     * @return array<array-key, mixed>
     */
    public function body(): array
    {
        return $this->response->jsonOrEmpty();
    }

    /**
     * The machine code of the error, for example `internal_error` or
     * `idempotency_key_in_progress`. The value is null when the body has no code.
     */
    public function errorCode(): ?string
    {
        $code = $this->body()['code'] ?? null;

        return is_string($code) ? $code : null;
    }

    /**
     * The `X-Request-Uuid` header. Give this value in a support request.
     */
    public function requestUuid(): ?string
    {
        return $this->response->header('X-Request-Uuid');
    }

    private static function messageFrom(Response $response): string
    {
        $message = $response->jsonOrEmpty()['message'] ?? null;

        return is_string($message) && $message !== ''
            ? $message
            : "The Snapsbrew instance answered with HTTP status {$response->status}.";
    }
}
