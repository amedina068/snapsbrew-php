<?php

declare(strict_types=1);

namespace Snapsbrew\Resources;

use Generator;
use Snapsbrew\Data\CursorPage;
use Snapsbrew\Data\Payload;
use Snapsbrew\Data\Preview;
use Snapsbrew\Data\PublishedTemplate;
use Snapsbrew\Data\Template;
use Snapsbrew\Data\TemplateSize;
use Snapsbrew\Http\Transport;
use Snapsbrew\Variants;

/**
 * The templates of your company.
 */
final readonly class Templates
{
    public function __construct(private Transport $transport) {}

    /**
     * One page of templates. The list does not include the publish state.
     *
     * @return CursorPage<Template>
     */
    public function list(?string $cursor = null): CursorPage
    {
        $body = $this->transport->get('v1/templates', array_filter(['cursor' => $cursor]))->json();

        return CursorPage::fromBody($body, Template::fromArray(...));
    }

    /**
     * Every template. The SDK reads the next page when you reach the end of
     * the current page.
     *
     * @return Generator<int, Template>
     */
    public function all(): Generator
    {
        $cursor = null;

        do {
            $page = $this->list($cursor);

            yield from $page->items;

            $cursor = $page->nextCursor;
        } while ($cursor !== null);
    }

    /**
     * One template, with its layers and its publish state.
     */
    public function find(string $uuid): Template
    {
        return Template::fromArray($this->transport->get('v1/templates/'.rawurlencode($uuid))->data());
    }

    /**
     * The canvas size presets that you can give as `size`.
     *
     * @return list<TemplateSize>
     */
    public function sizes(): array
    {
        return array_map(
            TemplateSize::fromArray(...),
            Payload::listOfObjects($this->transport->get('v1/templates/sizes')->json()['data'] ?? null),
        );
    }

    /**
     * Create a template. Give a `size` preset, or a `width` and a `height`.
     *
     * @param  list<array<string, mixed>>|null  $layers  The layer definitions. The layers guide describes them.
     * @param  list<array<string, mixed>>|null  $groups  The layer groups. Each group has an `id`, and optional `name`, `visible`, `collapsed` and `order`.
     */
    public function create(
        string $title,
        ?string $size = null,
        ?int $width = null,
        ?int $height = null,
        ?array $layers = null,
        ?array $groups = null,
    ): Template {
        $response = $this->transport->post('v1/templates', self::filter([
            'title' => $title,
            'size' => $size,
            'width' => $width,
            'height' => $height,
            'layers' => $layers,
            'groups' => $groups,
        ]));

        return Template::fromArray($response->data());
    }

    /**
     * Update a template. The instance requires the title on each update. A
     * field that you do not give keeps its value.
     *
     * @param  string|int  $template  The UUID or the numeric ID of the template.
     * @param  list<array<string, mixed>>|null  $layers
     * @param  list<array<string, mixed>>|null  $groups  The new layer groups. An empty list removes every group.
     */
    public function update(
        string|int $template,
        string $title,
        ?string $size = null,
        ?int $width = null,
        ?int $height = null,
        ?array $layers = null,
        ?array $groups = null,
    ): Template {
        $response = $this->transport->patch('v1/templates/'.rawurlencode((string) $template), self::filter([
            'title' => $title,
            'size' => $size,
            'width' => $width,
            'height' => $height,
            'layers' => $layers,
            'groups' => $groups,
        ]));

        return Template::fromArray($response->data());
    }

    /**
     * Delete a template. The instance also deletes its snaps, images,
     * versions and forms.
     *
     * @param  string|int  $template  The UUID or the numeric ID of the template.
     */
    public function delete(string|int $template): void
    {
        $this->transport->delete('v1/templates/'.rawurlencode((string) $template));
    }

    /**
     * Publish the draft of a template. Snaps and previews render only the
     * published version. A second call on a draft with no new edits returns
     * the same version.
     */
    public function publish(string $uuid): PublishedTemplate
    {
        $response = $this->transport->post('v1/templates/'.rawurlencode($uuid).'/publish', idempotent: true);

        return PublishedTemplate::fromArray($response->data());
    }

    /**
     * A watermarked HTML preview of a template. The instance does not store it.
     *
     * @param  Variants|iterable<array<string, mixed>>  $variants
     */
    public function preview(string $uuid, Variants|iterable $variants = []): Preview
    {
        $variants = Variants::from($variants);

        $response = $this->transport->get(
            'v1/templates/'.rawurlencode($uuid).'/preview',
            $variants->isEmpty() ? [] : ['variants' => $variants->toArray()],
        );

        return new Preview($response->body);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function filter(array $data): array
    {
        return array_filter($data, static fn (mixed $value): bool => $value !== null);
    }
}
