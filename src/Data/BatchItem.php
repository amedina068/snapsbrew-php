<?php

declare(strict_types=1);

namespace Snapsbrew\Data;

use Snapsbrew\Enums\Status;
use Snapsbrew\Exceptions\UnexpectedResponseException;

/**
 * One snap of a batch.
 */
final readonly class BatchItem
{
    public function __construct(
        public string $id,
        public string $identifier,
        public Status $status,
        public ?string $mediaUrl = null,
        public ?string $lastError = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $payload = Payload::of($data);

        return new self(
            id: $payload->string('id'),
            identifier: $payload->string('identifier'),
            status: Status::tryFrom($payload->string('status')) ?? throw UnexpectedResponseException::field('status', 'status'),
            mediaUrl: $payload->nullableString('media_url'),
            lastError: $payload->nullableString('last_error'),
        );
    }

    public function isCompleted(): bool
    {
        return $this->status === Status::Completed;
    }
}
