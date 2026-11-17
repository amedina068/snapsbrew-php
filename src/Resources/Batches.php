<?php

declare(strict_types=1);

namespace Snapsbrew\Resources;

use InvalidArgumentException;
use Snapsbrew\Data\Batch;
use Snapsbrew\Http\Transport;
use Snapsbrew\Variants;

/**
 * Renders up to 100 snaps from one template in the background.
 */
final readonly class Batches
{
    public function __construct(private Transport $transport) {}

    /**
     * Start a batch. The instance always answers 202 and renders the items in
     * the background. Read the batch with `find()` until `isFinished()` is true.
     *
     * Each item can be a `Variants` list, a list of raw variants, or an array
     * with the keys `variants` and `identifier`. If the items array has
     * string keys, the SDK uses each key as the identifier of its item.
     *
     * @param  iterable<array-key, Variants|array<array-key, mixed>>  $items
     */
    public function create(
        string $templateId,
        iterable $items,
        ?string $title = null,
        ?string $webhookUrl = null,
        ?string $format = null,
        ?string $type = null,
        ?bool $transparent = null,
        ?string $idempotencyKey = null,
    ): Batch {
        $body = array_filter([
            'template_id' => $templateId,
            'items' => self::items($items),
            'title' => $title,
            'webhook_url' => $webhookUrl,
            'format' => $format,
            'type' => $type,
            'transparent' => $transparent,
            'idempotency_key' => $idempotencyKey,
        ], static fn (mixed $value): bool => $value !== null);

        $response = $this->transport->post('v1/snaps/batches', $body, idempotent: $idempotencyKey !== null);

        return Batch::fromArray($response->resource(), $response->requestUuid(), $response->replayed());
    }

    /**
     * The batch, its counts and its items.
     */
    public function find(string $uuid): Batch
    {
        $response = $this->transport->get('v1/snaps/batches/'.rawurlencode($uuid));

        return Batch::fromArray($response->resource(), $response->requestUuid());
    }

    /**
     * Stop the items that did not start. An item that already rendered keeps
     * its image. A second cancel is safe and changes nothing.
     */
    public function cancel(string $uuid): Batch
    {
        $response = $this->transport->post('v1/snaps/batches/'.rawurlencode($uuid).'/cancel', idempotent: true);

        return Batch::fromArray($response->resource(), $response->requestUuid());
    }

    /**
     * @param  iterable<array-key, Variants|array<array-key, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function items(iterable $items): array
    {
        $normalized = [];

        foreach ($items as $key => $item) {
            $entry = self::item($item);

            if (is_string($key) && ! isset($entry['identifier'])) {
                $entry['identifier'] = $key;
            }

            $normalized[] = $entry;
        }

        return $normalized;
    }

    /**
     * @param  Variants|array<array-key, mixed>  $item
     * @return array<string, mixed>
     */
    private static function item(Variants|array $item): array
    {
        if ($item instanceof Variants || array_is_list($item)) {
            /** @var Variants|list<array<string, mixed>> $item */
            return ['variants' => Variants::from($item)->toArray()];
        }

        $variants = $item['variants'] ?? [];

        if (! $variants instanceof Variants && ! is_array($variants)) {
            throw new InvalidArgumentException('The "variants" key of a batch item must be an array or a Variants list.');
        }

        /** @var Variants|list<array<string, mixed>> $variants */
        $entry = ['variants' => Variants::from($variants)->toArray()];

        if (isset($item['identifier'])) {
            if (! is_scalar($item['identifier'])) {
                throw new InvalidArgumentException('The "identifier" key of a batch item must be a string.');
            }

            $entry['identifier'] = (string) $item['identifier'];
        }

        return $entry;
    }
}
