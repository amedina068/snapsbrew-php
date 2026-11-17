<?php

declare(strict_types=1);

namespace Snapsbrew\Data;

/**
 * The account that owns the API token.
 */
final readonly class Account
{
    public function __construct(
        public string $email,
        public string $name,
        public ?string $company,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $payload = Payload::of($data);

        return new self(
            email: $payload->string('email'),
            name: $payload->string('name'),
            company: $payload->nullableString('company'),
        );
    }
}
