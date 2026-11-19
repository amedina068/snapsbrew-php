<?php

declare(strict_types=1);

namespace Snapsbrew\Data;

use DateTimeImmutable;
use Snapsbrew\Exceptions\UnexpectedResponseException;
use Throwable;

/**
 * Reads typed fields from one decoded JSON object.
 *
 * The value objects use this class so that a missing or a wrong field gives
 * one clear exception, not a PHP type error.
 *
 * @internal
 */
final readonly class Payload
{
    /**
     * @param  array<array-key, mixed>  $data
     */
    public function __construct(private array $data) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function of(array $data): self
    {
        return new self($data);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function string(string $key): string
    {
        $value = $this->data[$key] ?? null;

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        throw UnexpectedResponseException::field($key, 'string');
    }

    public function nullableString(string $key): ?string
    {
        return ($this->data[$key] ?? null) === null ? null : $this->string($key);
    }

    public function int(string $key): int
    {
        $value = $this->data[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        throw UnexpectedResponseException::field($key, 'integer');
    }

    public function nullableInt(string $key): ?int
    {
        return ($this->data[$key] ?? null) === null ? null : $this->int($key);
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->data[$key] ?? $default;

        return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function date(string $key): ?DateTimeImmutable
    {
        $value = $this->nullableString($key);

        if ($value === null) {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            throw UnexpectedResponseException::field($key, 'date');
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    public function array(string $key): array
    {
        $value = $this->data[$key] ?? [];

        if (! is_array($value)) {
            throw UnexpectedResponseException::field($key, 'array');
        }

        return $value;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public function nullableArray(string $key): ?array
    {
        return ($this->data[$key] ?? null) === null ? null : $this->array($key);
    }

    /**
     * The list of JSON objects under one key.
     *
     * @return list<array<array-key, mixed>>
     */
    public function objects(string $key): array
    {
        return self::listOfObjects($this->array($key), $key);
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    public static function listOfObjects(mixed $value, string $key = 'data'): array
    {
        if (! is_array($value)) {
            throw UnexpectedResponseException::field($key, 'array');
        }

        $objects = [];

        foreach ($value as $item) {
            if (! is_array($item)) {
                throw UnexpectedResponseException::field($key, 'list of objects');
            }

            $objects[] = $item;
        }

        return $objects;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
