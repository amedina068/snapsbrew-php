<?php

declare(strict_types=1);

namespace Snapsbrew\Signing;

use InvalidArgumentException;
use Snapsbrew\Variants;
use Stringable;

/**
 * A fluent builder for a signed image URL.
 *
 *     $snapsbrew->signedUrl($templateId)
 *         ->text('title', 'Hello')
 *         ->image('cover', $url)
 *         ->format('png')
 *         ->url();
 *
 * A signed URL renders an image from a plain `<img src>` or an `og:image`
 * tag, with no API call. The first request renders the image and uses one
 * credit. The instance caches the image for later requests with the same URL.
 */
final class PendingSignedUrl implements Stringable
{
    public const FORMATS = ['png', 'jpeg', 'webp'];

    private Variants $variants;

    private ?string $format = null;

    private bool $transparent = false;

    public function __construct(
        private readonly UrlSigner $signer,
        private readonly string $endpoint,
        private readonly string $templateId,
    ) {
        $this->variants = new Variants;
    }

    public function text(string $layer, string $text): self
    {
        $this->variants->text($layer, $text);

        return $this;
    }

    public function image(string $layer, string $url): self
    {
        $this->variants->image($layer, $url);

        return $this;
    }

    public function background(string $layer, string $color): self
    {
        $this->variants->background($layer, $color);

        return $this;
    }

    public function qr(string $layer, string $content): self
    {
        $this->variants->qr($layer, $content);

        return $this;
    }

    public function barcode(string $layer, string $content): self
    {
        $this->variants->barcode($layer, $content);

        return $this;
    }

    public function hide(string $layer, bool $hidden = true): self
    {
        $this->variants->hide($layer, $hidden);

        return $this;
    }

    /**
     * Add a list of variants.
     *
     * @param  Variants|iterable<array<string, mixed>>  $variants
     */
    public function variants(Variants|iterable $variants): self
    {
        $this->variants->merge($variants);

        return $this;
    }

    /**
     * Set the image format: `png`, `jpeg` or `webp`. The instance renders
     * `png` by default, and it renders `png` for any other value.
     */
    public function format(string $format): self
    {
        if (! in_array($format, self::FORMATS, true)) {
            throw new InvalidArgumentException("The format [{$format}] is not supported. Use png, jpeg or webp.");
        }

        $this->format = $format;

        return $this;
    }

    /**
     * Render on a transparent background. This is possible only for png and webp.
     */
    public function transparent(bool $transparent = true): self
    {
        $this->transparent = $transparent;

        return $this;
    }

    /**
     * The parameters before the SDK adds the signature.
     *
     * @return array<string, string>
     */
    public function parameters(): array
    {
        if ($this->transparent && $this->format === 'jpeg') {
            throw new InvalidArgumentException('The jpeg format does not support a transparent background.');
        }

        $parameters = ['template_id' => $this->templateId];

        if (! $this->variants->isEmpty()) {
            $parameters['variants'] = UrlSigner::encodeVariants($this->variants->toArray());
        }

        if ($this->format !== null) {
            $parameters['format'] = $this->format;
        }

        if ($this->transparent) {
            $parameters['transparent'] = '1';
        }

        return $parameters;
    }

    public function url(): string
    {
        return $this->signer->sign($this->endpoint, $this->parameters());
    }

    public function toString(): string
    {
        return $this->url();
    }

    public function __toString(): string
    {
        return $this->url();
    }
}
