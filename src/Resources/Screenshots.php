<?php

declare(strict_types=1);

namespace Snapsbrew\Resources;

use Generator;
use Snapsbrew\Data\CursorPage;
use Snapsbrew\Data\Screenshot;
use Snapsbrew\Http\Transport;

/**
 * Images of web pages.
 */
final readonly class Screenshots
{
    public function __construct(private Transport $transport) {}

    /**
     * @return CursorPage<Screenshot>
     */
    public function list(?string $cursor = null): CursorPage
    {
        $body = $this->transport->get('v1/screenshots', array_filter(['cursor' => $cursor]))->json();

        return CursorPage::fromBody($body, Screenshot::fromArray(...));
    }

    /**
     * @return Generator<int, Screenshot>
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

    public function find(string $id): Screenshot
    {
        return Screenshot::fromArray($this->transport->get('v1/screenshots/'.rawurlencode($id))->resource());
    }

    /**
     * Capture a web page. The instance uses a 1330x780 viewport when you do
     * not give a size.
     */
    public function create(string $url, ?int $width = null, ?int $height = null): Screenshot
    {
        $response = $this->transport->post('v1/screenshots', array_filter([
            'url' => $url,
            'width' => $width,
            'height' => $height,
        ], static fn (mixed $value): bool => $value !== null));

        return Screenshot::fromArray($response->resource());
    }
}
