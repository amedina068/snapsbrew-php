<?php

declare(strict_types=1);

namespace Snapsbrew\Laravel\Testing;

/**
 * A response that the fake sends for a stubbed endpoint.
 */
final readonly class FakeResponse
{
    /**
     * @param  array<array-key, mixed>|string  $body  An array becomes a JSON body.
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public array|string $body = [],
        public int $status = 200,
        public array $headers = [],
    ) {}

    /**
     * Make a response from the short forms that a stub accepts: an array is
     * a JSON body, a string is a raw body and an integer is a status code.
     */
    public static function from(mixed $value): self
    {
        return match (true) {
            $value instanceof self => $value,
            is_array($value) => new self($value),
            is_string($value) => new self($value),
            is_int($value) => new self('', $value),
            $value === null => new self,
            default => throw new \InvalidArgumentException('A stub must be a FakeResponse, an array, a string, an integer or a closure.'),
        };
    }

    public function content(): string
    {
        if (is_string($this->body)) {
            return $this->body;
        }

        return json_encode($this->body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        if (is_array($this->body) && ! $this->hasHeader('Content-Type')) {
            return $this->headers + ['Content-Type' => 'application/json'];
        }

        return $this->headers;
    }

    private function hasHeader(string $name): bool
    {
        foreach (array_keys($this->headers) as $header) {
            if (strcasecmp($header, $name) === 0) {
                return true;
            }
        }

        return false;
    }
}
