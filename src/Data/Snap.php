<?php

declare(strict_types=1);

namespace Snapsbrew\Data;

use DateTimeImmutable;
use Snapsbrew\Enums\Status;
use Snapsbrew\Exceptions\UnexpectedResponseException;

/**
 * One render of a template into an image or a PDF.
 */
final readonly class Snap
{
    /**
     * @param  array<array-key, mixed>  $variants  The variants that the render used.
     */
    public function __construct(
        public string $id,
        public Status $status,
        public string $type,
        public ?string $mediaUrl = null,
        public ?int $width = null,
        public ?int $height = null,
        public bool $isAsync = false,
        public ?string $webhookUrl = null,
        public ?DateTimeImmutable $completedAt = null,
        public ?string $lastError = null,
        public array $variants = [],
        public ?string $templateId = null,
        public ?string $templateTitle = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?string $requestUuid = null,
        public bool $replayed = false,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data, ?string $requestUuid = null, bool $replayed = false): self
    {
        $payload = Payload::of($data);

        return new self(
            id: $payload->string('id'),
            status: Status::tryFrom($payload->string('status')) ?? throw UnexpectedResponseException::field('status', 'status'),
            type: $payload->nullableString('type') ?? 'image',
            mediaUrl: $payload->nullableString('media_url'),
            width: $payload->nullableInt('width'),
            height: $payload->nullableInt('height'),
            isAsync: $payload->bool('is_async'),
            webhookUrl: $payload->nullableString('webhook_url'),
            completedAt: $payload->date('completed_at'),
            lastError: $payload->nullableString('last_error'),
            variants: $payload->array('variants'),
            templateId: $payload->nullableString('template_id'),
            templateTitle: $payload->nullableString('template_title'),
            createdAt: $payload->date('created_at'),
            requestUuid: $requestUuid,
            replayed: $replayed,
        );
    }

    public function isCompleted(): bool
    {
        return $this->status === Status::Completed;
    }

    public function isFailed(): bool
    {
        return $this->status === Status::Failed;
    }

    public function isPending(): bool
    {
        return ! $this->status->isFinished();
    }
}
