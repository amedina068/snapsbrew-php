<?php

declare(strict_types=1);

namespace Snapsbrew\Exceptions;

use Snapsbrew\Webhooks\VerificationFailure;

/**
 * A webhook delivery is not valid. `$reason` tells why.
 *
 * Answer the delivery with HTTP 400, and do not process it.
 */
final class WebhookVerificationException extends SnapsbrewException
{
    public function __construct(string $message, public readonly VerificationFailure $reason)
    {
        parent::__construct($message);
    }

    public static function invalidSignature(): self
    {
        return new self(
            'The webhook signature is missing or does not match the payload.',
            VerificationFailure::Signature,
        );
    }

    public static function invalidTimestamp(): self
    {
        return new self(
            'The webhook timestamp header is missing or is not Unix seconds.',
            VerificationFailure::Timestamp,
        );
    }

    public static function timestampOutsideTolerance(int $tolerance): self
    {
        return new self(
            "The webhook timestamp is more than {$tolerance} seconds before or after the current time.",
            VerificationFailure::Timestamp,
        );
    }

    public static function invalidPayload(): self
    {
        return new self('The webhook payload is not a JSON object.', VerificationFailure::Payload);
    }
}
