<?php

declare(strict_types=1);

namespace Snapsbrew\Data;

use Stringable;

/**
 * A watermarked HTML preview of a render. The instance does not store it.
 */
final readonly class Preview implements Stringable
{
    public function __construct(public string $html) {}

    public function __toString(): string
    {
        return $this->html;
    }
}
