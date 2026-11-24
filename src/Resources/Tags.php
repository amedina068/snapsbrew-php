<?php

declare(strict_types=1);

namespace Snapsbrew\Resources;

use Snapsbrew\Data\Payload;
use Snapsbrew\Data\Tag;
use Snapsbrew\Http\Transport;

/**
 * The tags of your company.
 */
final readonly class Tags
{
    public function __construct(private Transport $transport) {}

    /**
     * Up to 15 tags, ordered by name. The search matches part of the name.
     *
     * @return list<Tag>
     */
    public function list(?string $search = null): array
    {
        $body = $this->transport->get('v1/tags', array_filter(['search' => $search]))->json();

        return array_map(Tag::fromArray(...), Payload::of($body)->objects('data'));
    }

    public function create(string $name): Tag
    {
        return Tag::fromArray($this->transport->post('v1/tags', ['name' => $name])->resource());
    }
}
