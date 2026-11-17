<?php

declare(strict_types=1);

namespace Snapsbrew\Resources;

use Snapsbrew\Data\Guide;
use Snapsbrew\Http\Transport;

/**
 * Read-only reference catalogs. The content is the same for every account,
 * and a guide call does not appear in the usage log.
 */
final readonly class Guides
{
    public function __construct(private Transport $transport) {}

    /**
     * Canvas presets, layer order, typography, color rules and recipes.
     */
    public function design(): Guide
    {
        return $this->guide('v1/guides/design');
    }

    /**
     * The layer object shape and every layer property.
     */
    public function layers(): Guide
    {
        return $this->guide('v1/guides/layers');
    }

    /**
     * Every property that a variant can set.
     */
    public function variantProperties(): Guide
    {
        return $this->guide('v1/guides/variant-properties');
    }

    /**
     * The contract of the v2 editor document.
     */
    public function editorDocument(): Guide
    {
        return $this->guide('v1/guides/editor-document');
    }

    /**
     * One line for each platform, with the size preset to start from.
     */
    public function platforms(): Guide
    {
        return $this->guide('v1/guides/platforms');
    }

    /**
     * The full rules of one platform, for example `instagram` or `x`.
     */
    public function platform(string $platform): Guide
    {
        return $this->guide('v1/guides/platforms/'.rawurlencode($platform));
    }

    /**
     * One line for each image purpose.
     */
    public function purposes(): Guide
    {
        return $this->guide('v1/guides/purposes');
    }

    /**
     * The full rules of one image purpose, for example `link_preview`.
     */
    public function purpose(string $purpose): Guide
    {
        return $this->guide('v1/guides/purposes/'.rawurlencode($purpose));
    }

    private function guide(string $path): Guide
    {
        return Guide::fromBody($this->transport->get($path)->json());
    }
}
