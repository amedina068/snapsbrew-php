<?php

declare(strict_types=1);

namespace Snapsbrew\Data;

use Snapsbrew\Enums\Status;
use Snapsbrew\Exceptions\UnexpectedResponseException;

/**
 * A group of up to 100 snaps from one template.
 *
 * At a finished status, `completed + failed + canceled` is equal to
 * `totalRows`. The status `completed` means that at least one item rendered,
 * so also read `failed`.
 */
final readonly class Batch
{
    /**
     * @param  list<BatchItem>  $items  The create endpoint does not return the items.
     */
    public function __construct(
        public string $uuid,
        public string $title,
        public Status $status,
        public int $totalRows,
        public int $processedRows,
        public int $completed,
        public int $pending,
        public int $failed,
        public int $canceled,
        public ?string $statusUrl = null,
        public array $items = [],
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
            uuid: $payload->string('uuid'),
            title: $payload->string('title'),
            status: Status::tryFrom($payload->string('status')) ?? throw UnexpectedResponseException::field('status', 'status'),
            totalRows: $payload->int('total_rows'),
            processedRows: $payload->int('processed_rows'),
            completed: $payload->int('completed'),
            pending: $payload->int('pending'),
            failed: $payload->int('failed'),
            canceled: $payload->int('canceled'),
            statusUrl: $payload->nullableString('status_url'),
            items: array_map(BatchItem::fromArray(...), $payload->objects('items')),
            requestUuid: $requestUuid,
            replayed: $replayed,
        );
    }

    public function isFinished(): bool
    {
        return $this->status->isFinished();
    }

    /**
     * The items that rendered an image.
     *
     * @return list<BatchItem>
     */
    public function completedItems(): array
    {
        return array_values(array_filter($this->items, static fn (BatchItem $item): bool => $item->isCompleted()));
    }

    /**
     * The item with the given identifier, or null.
     */
    public function item(string $identifier): ?BatchItem
    {
        foreach ($this->items as $item) {
            if ($item->identifier === $identifier) {
                return $item;
            }
        }

        return null;
    }
}
