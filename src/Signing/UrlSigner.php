<?php

declare(strict_types=1);

namespace Snapsbrew\Signing;

use SensitiveParameter;

/**
 * Signs the query parameters of a signed image URL.
 *
 * The algorithm is the same as `SignedUrlMiddleware` on the instance:
 *
 * 1. Take every parameter except `signature`.
 * 2. Sort the parameters by key, in byte order.
 * 3. Join them as `key=value` with `&`. Use the raw values, not URL-encoded values.
 * 4. The signature is the HMAC-SHA256 hex digest of that string.
 *
 * The final URL encodes every value. Sign only on the server, because the
 * signing key is a secret.
 */
final readonly class UrlSigner
{
    public function __construct(
        #[SensitiveParameter] private string $key,
    ) {}

    /**
     * @param  array<string, string>  $parameters
     */
    public static function canonicalString(array $parameters): string
    {
        unset($parameters['signature']);
        ksort($parameters, SORT_STRING);

        $pairs = [];

        foreach ($parameters as $key => $value) {
            $pairs[] = "{$key}={$value}";
        }

        return implode('&', $pairs);
    }

    /**
     * @param  array<string, string>  $parameters
     */
    public function signature(array $parameters): string
    {
        return hash_hmac('sha256', self::canonicalString($parameters), $this->key);
    }

    /**
     * Add the signature to the parameters and build the URL. The parameters
     * keep their order, and `signature` is the last parameter.
     *
     * @param  array<string, string>  $parameters
     */
    public function sign(string $url, array $parameters): string
    {
        unset($parameters['signature']);
        $parameters['signature'] = $this->signature($parameters);

        return $url.'?'.http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Encode a list of variants for the `variants` parameter: standard base64
     * (with `+`, `/` and `=`) of the JSON array. The instance decodes it with
     * `base64_decode`, so the SDK does not use URL-safe base64.
     *
     * @param  list<array<string, string|bool>>  $variants
     */
    public static function encodeVariants(array $variants): string
    {
        return base64_encode(json_encode($variants, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['key' => '********'];
    }
}
