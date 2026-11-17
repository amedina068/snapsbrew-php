<?php

declare(strict_types=1);

namespace Snapsbrew\Exceptions;

/**
 * The instance sent a body that the SDK cannot read.
 */
final class UnexpectedResponseException extends SnapsbrewException
{
    public static function field(string $key, string $type): self
    {
        return new self("The response field [{$key}] is missing or is not a valid {$type}.");
    }

    public static function invalidJson(int $status): self
    {
        return new self("The instance sent a response with status {$status} that is not a JSON object.");
    }
}
