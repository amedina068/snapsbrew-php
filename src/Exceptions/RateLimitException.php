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

    /**
     * The number of requests that the limit allows in one minute, from the
     * `X-RateLimit-Limit` header.
     */
    public function limit(): ?int
    {
        return $this->response->intHeader('X-RateLimit-Limit');
    }

    /**
     * The number of requests left in the current minute, from the
     * `X-RateLimit-Remaining` header.
     */
    public function remaining(): ?int
    {
        return $this->response->intHeader('X-RateLimit-Remaining');
    }
}
