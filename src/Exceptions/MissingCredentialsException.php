<?php

declare(strict_types=1);

namespace Snapsbrew\Exceptions;

/**
 * A call needs a credential that the client does not have.
 */
final class MissingCredentialsException extends SnapsbrewException
{
    public static function token(): self
    {
        return new self('Set an API token to call the Snapsbrew API. In Laravel, set SNAPSBREW_TOKEN.');
    }

    public static function signingKey(): self
    {
        return new self('Set a signing key to build signed URLs. In Laravel, set SNAPSBREW_SIGNING_KEY.');
    }

    public static function webhookSecret(): self
    {
        return new self('Set the webhook signing secret to verify webhooks. In Laravel, set SNAPSBREW_WEBHOOK_SECRET.');
    }

    public static function url(): self
    {
        return new self('Set the URL of your Snapsbrew instance. In Laravel, set SNAPSBREW_URL.');
    }
}
