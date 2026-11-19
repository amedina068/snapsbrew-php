<?php

declare(strict_types=1);

namespace Snapsbrew\Webhooks;

/**
 * The reason why a webhook delivery is not valid.
 */
enum VerificationFailure: string
{
    /**
     * The signature or the timestamp header is missing, or the signature does
     * not match the body.
     */
    case Signature = 'signature';

    /**
     * The instance signed the delivery outside the tolerance.
     */
    case Timestamp = 'timestamp';

    /**
     * The signature is valid, but the body is not a JSON object.
     */
    case Payload = 'payload';
}
