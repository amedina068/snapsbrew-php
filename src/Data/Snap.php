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
     * The render failed. The snap has no image.
     */
    public const ERROR_RENDER_FAILED = 'render_failed';

    /**
     * The webhook receiver answered with a redirect. The instance does not follow it.
     */
    public const ERROR_WEBHOOK_REDIRECT_REFUSED = 'webhook_redirect_refused';

    /**
     * The instance did not get a connection to the webhook receiver.
     */
    public const ERROR_WEBHOOK_UNREACHABLE = 'webhook_unreachable';

    /**
     * @param  string|null  $mediaUrl  The image URL, as the instance sends it. The instance sends an empty string for a snap with no image, for example a failed snap.
     * @param  string|null  $lastError  A fixed error code: one of the `ERROR_` constants, or `webhook_http_<status>` when the webhook receiver answered with an error status.
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
