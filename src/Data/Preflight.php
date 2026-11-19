<?php

declare(strict_types=1);

namespace Snapsbrew\Data;

/**
 * The known problems of an editor document.
 *
 * A preflight with no issues passes the known checks. It does not prove that
 * the render looks right.
 */
final readonly class Preflight
{
    /**
     * @param  list<PreflightIssue>  $issues
     * @param  int  $revision  The revision that the instance inspected.
     */
    public function __construct(
        public array $issues,
        public int $revision,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $payload = Payload::of($data);

        return new self(
            issues: array_map(PreflightIssue::fromArray(...), $payload->objects('issues')),
            revision: $payload->int('revision'),
        );
    }

    public function passes(): bool
    {
        return $this->issues === [];
    }
}
