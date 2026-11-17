<?php

declare(strict_types=1);

namespace Snapsbrew\Exceptions;

/**
 * HTTP 422: the instance refused the input.
 */
final class ValidationException extends ApiException
{
    /**
     * The messages for each field, keyed by the field name.
     *
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        $errors = $this->body()['errors'] ?? [];

        if (! is_array($errors)) {
            return [];
        }

        $result = [];

        foreach ($errors as $field => $messages) {
            $messages = is_array($messages) ? $messages : [$messages];

            $result[(string) $field] = array_values(array_map(
                static fn (mixed $message): string => is_scalar($message) ? (string) $message : '',
                $messages,
            ));
        }

        return $result;
    }

    /**
     * The first message for one field, or null when the field has no error.
     */
    public function first(string $field): ?string
    {
        return $this->errors()[$field][0] ?? null;
    }
}
