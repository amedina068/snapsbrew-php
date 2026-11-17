<?php

declare(strict_types=1);

namespace Snapsbrew\Http;

use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Snapsbrew\Exceptions\UnexpectedResponseException;

/**
 * One HTTP response from the instance, with the body already read.
 */
final readonly class Response
{
    /**
     * @param  array<string, list<string>>  $headers  The header names are in lower case.
     */
    public function __construct(
        public int $status,
        public string $body,
        public array $headers = [],
    ) {}

    public static function fromPsr(ResponseInterface $response): self
    {
        $headers = [];

        foreach ($response->getHeaders() as $name => $values) {
            $headers[strtolower((string) $name)] = array_values($values);
        }

        return new self($response->getStatusCode(), (string) $response->getBody(), $headers);
    }

    /**
     * The decoded JSON body.
     *
     * @return array<array-key, mixed>
     */
    public function json(): array
    {
        $decoded = json_decode($this->body, true);

        if (! is_array($decoded)) {
            throw UnexpectedResponseException::invalidJson($this->status);
        }

        return $decoded;
    }

    /**
     * The decoded JSON body, or an empty array when the body is not JSON.
     *
     * @return array<array-key, mixed>
     */
    public function jsonOrEmpty(): array
    {
        $decoded = json_decode($this->body, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * The value under the `data` key of a JSON envelope.
     *
     * @return array<array-key, mixed>
     */
    public function data(): array
    {
        $data = $this->json()['data'] ?? null;

        if (! is_array($data)) {
            throw UnexpectedResponseException::field('data', 'object');
        }

        return $data;
    }

    /**
     * The resource of a body that can have a `data` envelope or not.
     *
     * Some endpoints (snaps, batches, media) send the resource with no
     * envelope. This method reads both forms, so a later envelope does not
     * break the SDK.
     *
     * @return array<array-key, mixed>
     */
    public function resource(): array
    {
        $body = $this->json();

        return isset($body['data']) && is_array($body['data']) ? $body['data'] : $body;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)][0] ?? null;
    }

    /**
     * A header that holds a whole number, or null when the header is missing
     * or holds another value.
     */
    public function intHeader(string $name): ?int
    {
        $value = trim($this->header($name) ?? '');

        return ctype_digit($value) ? (int) $value : null;
    }

    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    public function requestUuid(): ?string
    {
        return $this->header('X-Request-Uuid');
    }

    /**
     * True when the instance answered an idempotency key that it saw before.
     */
    public function replayed(): bool
    {
        return $this->header('X-Idempotent-Replay') === 'true';
    }

    /**
     * The `Retry-After` header in seconds. The header can hold seconds or an
     * HTTP date.
     */
    public function retryAfter(): ?int
    {
        $value = $this->header('Retry-After');

        if ($value === null || trim($value) === '') {
            return null;
        }

        if (ctype_digit(trim($value))) {
            return (int) trim($value);
        }

        $date = DateTimeImmutable::createFromFormat('D, d M Y H:i:s \G\M\T', trim($value), new \DateTimeZone('UTC'));

        if ($date === false) {
            return null;
        }

        return max(0, $date->getTimestamp() - time());
    }
}
