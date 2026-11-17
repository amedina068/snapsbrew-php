<?php

declare(strict_types=1);

namespace Snapsbrew\Data;

/**
 * A canvas size preset, for example `instagram_story`.
 */
final readonly class TemplateSize
{
    public function __construct(
        public string $size,
        public string $name,
        public string $platform,
        public int $width,
        public int $height,
        public string $aspectRatio,
        public int $safeMarginPx,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $payload = Payload::of($data);

        return new self(
            size: $payload->string('size'),
            name: $payload->string('name'),
            platform: $payload->string('platform'),
            width: $payload->int('width'),
            height: $payload->int('height'),
            aspectRatio: $payload->string('aspect_ratio'),
            safeMarginPx: $payload->int('safe_margin_px'),
        );
    }
}
