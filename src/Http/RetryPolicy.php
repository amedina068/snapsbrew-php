<?php

declare(strict_types=1);

namespace Snapsbrew\Http;

use Closure;

/**
 * Decides how many times and how long the SDK waits before it sends a
 * request again.
 *
 * The delay grows exponentially with "equal jitter": half of the delay is
 * fixed, and the other half is random. A `Retry-After` header replaces the
 * computed delay.
 */
final readonly class RetryPolicy
{
    /**
     * @param  int  $retries  The number of retries after the first attempt.
     * @param  int  $baseDelayMs  The delay before the first retry.
     * @param  int  $maxDelayMs  The upper limit of a computed delay.
     * @param  int  $maxRetryAfter  If `Retry-After` is longer than this number of seconds, the SDK does not wait and throws the error.
     * @param  (Closure(int): void)|null  $sleep  Receives the delay in milliseconds. Tests replace it.
     */
    public function __construct(
        public int $retries = 2,
        public int $baseDelayMs = 500,
        public int $maxDelayMs = 8_000,
        public int $maxRetryAfter = 60,
        private ?Closure $sleep = null,
    ) {}

    public static function none(): self
    {
        return new self(retries: 0);
    }

    /**
     * True when the SDK can send the request again after `$attempts` attempts.
     */
    public function allows(int $attempts): bool
    {
        return $attempts <= $this->retries;
    }

    /**
     * The delay in milliseconds before the next attempt.
     */
    public function delay(int $attempts, ?int $retryAfter = null): int
    {
        if ($retryAfter !== null) {
            return $retryAfter * 1000;
        }

        $ceiling = (int) min($this->maxDelayMs, $this->baseDelayMs * (2 ** max(0, $attempts - 1)));
        $half = intdiv($ceiling, 2);

        return $half + random_int(0, $ceiling - $half);
    }

    /**
     * True when the wait that the instance asks for is acceptable.
     */
    public function accepts(?int $retryAfter): bool
    {
        return $retryAfter === null || $retryAfter <= $this->maxRetryAfter;
    }

    public function sleep(int $milliseconds): void
    {
        if ($this->sleep !== null) {
            ($this->sleep)($milliseconds);

            return;
        }

        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }
}
