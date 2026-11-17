<?php

declare(strict_types=1);

namespace Snapsbrew\Laravel\View\Components;

use Illuminate\View\Component;
use Snapsbrew\Snapsbrew;
use Snapsbrew\Variants;

/**
 * An `<img>` tag with a signed Snapsbrew URL.
 *
 *     <x-snapsbrew::image
 *         :template="$templateId"
 *         :variants="[['layer' => 'title', 'text' => $post->title]]"
 *         format="png"
 *         alt="Cover image"
 *         class="rounded"
 *     />
 *
 * The component signs the URL on the server. The signing key never reaches
 * the browser.
 */
final class Image extends Component
{
    public string $src;

    /**
     * @param  Variants|array<int, array<string, mixed>>  $variants
     */
    public function __construct(
        Snapsbrew $snapsbrew,
        public string $template,
        Variants|array $variants = [],
        ?string $format = null,
        bool $transparent = false,
        public string $alt = '',
    ) {
        $url = $snapsbrew->signedUrl($template)->variants($variants)->transparent($transparent);

        if ($format !== null) {
            $url->format($format);
        }

        $this->src = $url->url();
    }

    public function render(): string
    {
        return <<<'blade'
            <img src="{{ $src }}" alt="{{ $alt }}" {{ $attributes }} />
            blade;
    }
}
