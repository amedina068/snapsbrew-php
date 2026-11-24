<?php

declare(strict_types=1);

namespace Snapsbrew\Resources;

use Snapsbrew\Data\EditorDocument;
use Snapsbrew\Data\Preflight;
use Snapsbrew\Http\Transport;

/**
 * The v2 editor document of a template: its layers, its groups and their
 * properties. The next render draws from this draft.
 *
 * Each write is a compare-and-swap. Read the document, change it, and send
 * the revision of the read. When the stored revision moved, the instance saves
 * nothing and throws a `ConflictException` with the current document.
 */
final readonly class Documents
{
    public function __construct(private Transport $transport) {}

    /**
     * The document and its revision. A template with no draft has a null
     * document and the revision 0.
     */
    public function find(string $templateUuid): EditorDocument
    {
        return EditorDocument::fromArray($this->transport->get($this->path($templateUuid))->json());
    }

    /**
     * Replace the whole document. A layer or a property that you leave out
     * is removed. To change some properties, use `apply()`.
     *
     * The SDK never retries this write after a 5xx or a timeout. A repeat
     * after a lost response gets a 409, because the first write changed the
     * revision.
     *
     * @param  array<array-key, mixed>  $document
     */
    public function replace(string $templateUuid, int $revision, array $document): EditorDocument
    {
        $response = $this->transport->put($this->path($templateUuid), [
            'revision' => $revision,
            'document' => $document,
        ], idempotent: false);

        return EditorDocument::fromArray($response->json());
    }

    /**
     * Apply up to 100 operations in one change. The instance applies all of
     * them, or none of them.
     *
     *     ['operation' => 'set_layer_property', 'layer_key' => 'headline', 'property' => 'fontWeight', 'value' => 700]
     *     ['operation' => 'set_layer_type', 'layer_key' => 'logo', 'type' => 'image']
     *
     * @param  list<array<string, mixed>>  $operations
     */
    public function apply(string $templateUuid, int $revision, array $operations): EditorDocument
    {
        $response = $this->transport->patch($this->path($templateUuid), [
            'revision' => $revision,
            'operations' => $operations,
        ]);

        return EditorDocument::fromArray($response->json());
    }

    /**
     * The known problems of the stored document. Run it after the last write
     * and before you publish the template.
     */
    public function preflight(string $templateUuid): Preflight
    {
        return Preflight::fromArray($this->transport->get($this->path($templateUuid).'/preflight')->json());
    }

    private function path(string $templateUuid): string
    {
        return 'v1/templates/'.rawurlencode($templateUuid).'/document';
    }
}
