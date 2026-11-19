<?php

declare(strict_types=1);

namespace Snapsbrew\Webhooks;

use Snapsbrew\Data\Batch;
use Snapsbrew\Data\Snap;

/**
 * A verified webhook delivery.
 *
 * Each payload has `event`. A snap event has `snap`. A batch event has
 * `import` and `template`. The instance can add event types, so do not refuse
 * a type that you do not know.
 */
final readonly class WebhookEvent
{
    public const SNAP_COMPLETED = 'snap.completed';

    public const SNAP_FAILED = 'snap.failed';

    public const BATCH_COMPLETED = 'snap-import.completed';

    public const BATCH_FAILED = 'snap-import.failed';

    public const BATCH_CANCELED = 'snap-import.canceled';

    /**
     * @param  string  $type  The event type, for example `snap.completed`.
     * @param  array<array-key, mixed>  $payload  The decoded JSON body.
     * @param  int  $timestamp  The Unix time when the instance signed the delivery.
     * @param  string|null  $deliveryId  The UUID of the delivery. Each attempt of one delivery has the same value. Use it to ignore a duplicate.
     */
    public function __construct(
        public string $type,
        public array $payload,
        public int $timestamp,
        public ?string $deliveryId = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public static function fromDelivery(array $payload, int $timestamp, ?string $deliveryId = null): self
    {
        $type = $payload['event'] ?? null;

        return new self(
            type: is_string($type) ? $type : '',
            payload: $payload,
            timestamp: $timestamp,
            deliveryId: $deliveryId === '' ? null : $deliveryId,
        );
    }

    public function is(string $type): bool
    {
        return $this->type === $type;
    }

    /**
     * The snap of a `snap.completed` or a `snap.failed` event. Null for the
     * other events.
     */
    public function snap(): ?Snap
    {
        return is_array($this->payload['snap'] ?? null) ? Snap::fromArray($this->payload['snap']) : null;
    }

    /**
     * The batch of a `snap-import.*` event, with its counts and no items.
     * Null for the other events.
     */
    public function batch(): ?Batch
    {
        return is_array($this->payload['import'] ?? null) ? Batch::fromArray($this->payload['import']) : null;
    }

    /**
     * The `uuid` and the `title` of the template of a batch event. Null for
     * the other events.
     *
     * @return array<array-key, mixed>|null
     */
    public function template(): ?array
    {
        return is_array($this->payload['template'] ?? null) ? $this->payload['template'] : null;
    }

    /**
     * The decoded JSON body.
     *
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return $this->payload;
    }
}
