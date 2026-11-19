<?php

declare(strict_types=1);

namespace Snapsbrew\Data;

/**
 * The v2 editor document of a template, and its revision.
 *
 * Keep the revision. Each write sends it back, so the instance can refuse a
 * write that starts from an old document.
 */
final readonly class EditorDocument
{
    /**
     * @param  array<array-key, mixed>|null  $document  The document, with `version`, `layers` and `groups`. Null when the template has no draft.
     */
    public function __construct(
        public ?array $document,
        public int $revision,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $payload = Payload::of($data);

        return new self(
            document: $payload->nullableArray('document'),
            revision: $payload->int('revision'),
        );
    }

    public function exists(): bool
    {
        return $this->document !== null;
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    public function layers(): array
    {
        return Payload::of($this->document ?? [])->objects('layers');
    }
}
