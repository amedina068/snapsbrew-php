<?php

declare(strict_types=1);

namespace Snapsbrew\Exceptions;

/**
 * HTTP 404: the resource does not exist, or it belongs to another company.
 *
 * The message comes from the instance and can change. Do not match on it.
 */
final class NotFoundException extends ApiException
{
    /**
     * The valid keys, when a platform or a purpose guide does not exist.
     * An empty list for the other endpoints.
     *
     * @return list<string>
     */
    public function available(): array
    {
        $available = $this->body()['available'] ?? [];

        return is_array($available) ? array_values(array_filter($available, is_string(...))) : [];
    }
}
