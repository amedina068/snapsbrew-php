<?php

declare(strict_types=1);

namespace Snapsbrew\Resources;

use Snapsbrew\Data\Payload;
use Snapsbrew\Data\Preview;
use Snapsbrew\Data\Snap;
use Snapsbrew\Http\Transport;
use Snapsbrew\Variants;

/**
 * Renders templates into images and PDFs.
 */
final readonly class Snaps
{
    public function __construct(private Transport $transport) {}

    /**
     * Render one template. This call uses one credit.
     *
     * The instance renders the published version of the template. With
     * `async: true`, the instance queues the render and posts the result to
     * `$webhookUrl`. Give an `$idempotencyKey` to make a retry safe: the
     * instance then returns the same snap and does not render it again.
     *
     * @param  Variants|iterable<array<string, mixed>>  $variants
     */
    public function create(
        string $templateId,
        Variants|iterable $variants = [],
        ?string $format = null,
        ?string $type = null,
        ?bool $transparent = null,
        bool $async = false,
        ?string $webhookUrl = null,
        ?string $idempotencyKey = null,
    ): Snap {
        $response = $this->transport->post('v1/snaps', self::body([
            'template_id' => $templateId,
            'variants' => Variants::from($variants)->toArray(),
            'format' => $format,
            'type' => $type,
            'transparent' => $transparent,
            'async' => $async ?: null,
            'webhook_url' => $webhookUrl,
            'idempotency_key' => $idempotencyKey,
        ]));

        return Snap::fromArray($response->resource(), $response->requestUuid(), $response->replayed());
    }

    /**
     * Render every template of a collection. This call uses one credit for
     * each template. The instance does not accept an idempotency key here.
     *
     * @param  Variants|iterable<array<string, mixed>>  $variants
     * @return list<Snap>
     */
    public function createFromCollection(
        string $collectionId,
        Variants|iterable $variants = [],
        ?string $format = null,
        ?string $type = null,
        ?bool $transparent = null,
        bool $async = false,
        ?string $webhookUrl = null,
    ): array {
        $response = $this->transport->post('v1/snaps', self::body([
            'collection_id' => $collectionId,
            'variants' => Variants::from($variants)->toArray(),
            'format' => $format,
            'type' => $type,
            'transparent' => $transparent,
            'async' => $async ?: null,
            'webhook_url' => $webhookUrl,
        ]));

        return array_map(
            static fn (array $snap): Snap => Snap::fromArray($snap, $response->requestUuid()),
            Payload::listOfObjects($response->resource()),
        );
    }

    /**
     * A watermarked HTML preview of the snap. A preview costs no credit.
     * Give the same variants to `create()` to render the final image.
     *
     * @param  Variants|iterable<array<string, mixed>>  $variants
     */
    public function preview(string $templateId, Variants|iterable $variants = []): Preview
    {
        $variants = Variants::from($variants);
        $query = ['template_id' => $templateId];

        if (! $variants->isEmpty()) {
            $query['variants'] = $variants->toArray();
        }

        return new Preview($this->transport->get('v1/snaps/preview', $query)->body);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function body(array $data): array
    {
        return array_filter($data, static fn (mixed $value): bool => $value !== null && $value !== []);
    }
}
