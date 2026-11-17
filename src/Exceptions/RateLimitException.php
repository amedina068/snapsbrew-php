<?php

declare(strict_types=1);

namespace Snapsbrew\Exceptions;

/**
 * HTTP 429: the account sent too many requests.
 */
final class RateLimitException extends ApiException
{
    /**
     * The number of seconds to wait, from the `Retry-After` header.
     */
    public function retryAfter(): ?int
    {
        return $this->response->retryAfter();
    }
}
