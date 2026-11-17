<?php

declare(strict_types=1);

namespace Snapsbrew\Exceptions;

/**
 * HTTP 409: another request with the same idempotency key is in progress, or
 * the stored state changed before the write.
 */
final class ConflictException extends ApiException
{
    /**
     * The machine code of the conflict, for example `editor_document_conflict`.
     */
    public function errorCode(): ?string
    {
        $code = $this->body()['code'] ?? null;

        return is_string($code) ? $code : null;
    }
}
