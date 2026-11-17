<?php

declare(strict_types=1);

namespace Snapsbrew;

use Countable;
use InvalidArgumentException;
use JsonSerializable;

/**
 * A fluent list of layer overrides for a render.
 *
 *     Variants::make()
 *         ->text('title', 'Hello')
 *         ->image('cover', 'https://example.com/cover.jpg')
 *         ->hide('badge');
 *
 * Each override sets one property of one layer. The template details tell
 * you the layer keys and the properties that each layer accepts.
 */
final class Variants implements Countable, JsonSerializable
{
    /**
     * @var list<array<string, string|bool>>
     */
    private array $variants = [];

    public static function make(): self
    {
        return new self;
    }

    /**
     * Create a list from raw variant arrays, or return the given list.
     *
     * @param  self|iterable<array<string, mixed>>  $variants
     */
    public static function from(self|iterable $variants): self
    {
        if ($variants instanceof self) {
            return $variants;
        }

        $list = new self;

        foreach ($variants as $variant) {
            $list->add($variant);
        }

        return $list;
    }

    /**
     * Replace the content of a text layer.
     */
    public function text(string $layer, string $text): self
    {
        return $this->set($layer, 'text', $text);
    }

    /**
     * Replace the image of an image layer. The URL must be public.
     */
    public function image(string $layer, string $url): self
    {
        return $this->set($layer, 'image_url', $url);
    }

    /**
     * Replace the background color of a layer, for example `#f0573f`.
     */
    public function background(string $layer, string $color): self
    {
        return $this->set($layer, 'background_color', $color);
    }

    /**
     * Set the content of a QR code layer.
     */
    public function qr(string $layer, string $content): self
    {
        return $this->set($layer, 'qr_content', $content);
    }

    /**
     * Set the content of a barcode layer.
     */
    public function barcode(string $layer, string $content): self
    {
        return $this->set($layer, 'barcode_content', $content);
    }

    /**
     * Remove a layer from the render.
     */
    public function hide(string $layer, bool $hidden = true): self
    {
        return $this->set($layer, 'hidden', $hidden);
    }

    /**
     * Add one raw variant, for example `['layer' => 'title', 'text' => 'Hello']`.
     *
     * @param  array<array-key, mixed>  $variant
     */
    public function add(array $variant): self
    {
        $layer = $variant['layer'] ?? null;

        if (! is_string($layer) || $layer === '') {
            throw new InvalidArgumentException('Each variant must have a "layer" key with a layer name.');
        }

        $clean = ['layer' => $layer];

        foreach ($variant as $property => $value) {
            if ($property === 'layer') {
                continue;
            }

            if (! is_string($value) && ! is_bool($value) && ! is_int($value) && ! is_float($value)) {
                throw new InvalidArgumentException("The variant property [{$property}] of layer [{$layer}] must be a string or a boolean.");
            }

            $clean[(string) $property] = is_bool($value) ? $value : (string) $value;
        }

        $this->variants[] = $clean;

        return $this;
    }

    /**
     * Add every variant of another list.
     *
     * @param  self|iterable<array<string, mixed>>  $variants
     */
    public function merge(self|iterable $variants): self
    {
        foreach (self::from($variants)->toArray() as $variant) {
            $this->variants[] = $variant;
        }

        return $this;
    }

    public function isEmpty(): bool
    {
        return $this->variants === [];
    }

    public function count(): int
    {
        return count($this->variants);
    }

    /**
     * @return list<array<string, string|bool>>
     */
    public function toArray(): array
    {
        return $this->variants;
    }

    /**
     * @return list<array<string, string|bool>>
     */
    public function jsonSerialize(): array
    {
        return $this->variants;
    }

    private function set(string $layer, string $property, string|bool $value): self
    {
        $this->variants[] = ['layer' => $layer, $property => $value];

        return $this;
    }
}
