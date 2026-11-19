<?php

declare(strict_types=1);

namespace Snapsbrew\Data;

/**
 * One problem in an editor document.
 */
final readonly class PreflightIssue
{
    /**
     * @param  string  $code  A fixed code, for example `image_asset_missing`.
     * @param  string  $path  A JSON pointer to the problem, for example `/layers/0/source`.
     */
    public function __construct(
        public string $code,
        public string $path,
        public string $message,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $payload = Payload::of($data);

        return new self(
            code: $payload->string('code'),
            path: $payload->nullableString('path') ?? '',
            message: $payload->nullableString('message') ?? '',
        );
    }
}
