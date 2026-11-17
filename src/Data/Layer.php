<?php

declare(strict_types=1);

namespace Snapsbrew\Data;

/**
 * One layer of a template that a variant can change.
 *
 * The key is null when the stored layer has no key. A layer with no variant
 * property, for example an empty or an SVG layer, has no modifications.
 */
final readonly class Layer
{
    /**
     * @param  array<string, string|null>  $modifications  The variant properties that the layer accepts, with the current values.
     */
    public function __construct(
        public ?string $key,
        public array $modifications = [],
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $payload = Payload::of($data);
        $modifications = [];

        foreach ($payload->array('available_modifications') as $property => $value) {
            $modifications[(string) $property] = is_scalar($value) ? (string) $value : null;
        }

        return new self(
            key: $payload->nullableString('layer'),
            modifications: $modifications,
        );
    }

    /**
     * True when a variant can set the given property, for example `text`.
     */
    public function accepts(string $property): bool
    {
        return array_key_exists($property, $this->modifications);
    }
}
