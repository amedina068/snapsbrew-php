<?php

declare(strict_types=1);

namespace Snapsbrew\Webhooks;

/**
 * The reason why a webhook delivery is not valid. The JS, Python and Ruby
 * SDKs use the same three values: `signature`, `timestamp` and `payload`.
 */
enum VerificationFailure: string
{
    /**
     * The signature header is missing, or the signature does not match the
     * body.
     */
    case Signature = 'signature';

    /**
     * The timestamp header is missing, is not Unix seconds, or is outside the
     * tolerance.
     */
    case Timestamp = 'timestamp';

    /**
     * The signature is valid, but the body is not a JSON object.
     */
    case Payload = 'payload';
}
