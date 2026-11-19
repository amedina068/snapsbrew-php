<?php

declare(strict_types=1);

namespace Snapsbrew\Webhooks;

use SensitiveParameter;
use Snapsbrew\Exceptions\MissingCredentialsException;
use Snapsbrew\Exceptions\WebhookVerificationException;

/**
 * Verifies the webhook deliveries of one company.
 *
 * Use this class only on the server. The webhook signing secret is a secret.
 *
 *     $event = (new Webhook($secret))->verify($rawBody, $headers);
 */
final readonly class Webhook
{
    public const SIGNATURE_HEADER = 'X-Snapsbrew-Signature';

    public const TIMESTAMP_HEADER = 'X-Snapsbrew-Timestamp';

    public const DELIVERY_HEADER = 'X-Snapsbrew-Delivery';

    /**
     * @param  string  $secret  The webhook signing secret of the company, with its `whsec_` prefix.
     * @param  int  $tolerance  The maximum difference in seconds between the signing time and now.
     */
    public function __construct(
        #[SensitiveParameter] private string $secret,
        public int $tolerance = 300,
    ) {
        if ($secret === '') {
            throw MissingCredentialsException::webhookSecret();
        }
    }

    /**
     * Verify a delivery and return its event.
     *
     * Give the raw request body, byte for byte. The instance escapes `/` and
     * the characters that are not ASCII. A new JSON encoding gives other
     * bytes, and the signature check fails.
     *
     * @param  string  $payload  The raw request body.
     * @param  array<string, string|list<string|null>|null>  $headers  The request headers. The names ignore case.
     * @param  int|null  $now  The current Unix time. Tests give it. The default is the clock.
     *
     * @throws WebhookVerificationException
     */
    public function verify(string $payload, array $headers, ?int $now = null): WebhookEvent
    {
        $headers = array_change_key_case($headers);
        $signature = self::header($headers, self::SIGNATURE_HEADER);
        $timestamp = self::header($headers, self::TIMESTAMP_HEADER);

        if ($signature === null || $timestamp === null || ! hash_equals($this->sign($timestamp, $payload), $signature)) {
            throw WebhookVerificationException::invalidSignature();
        }

        if (! ctype_digit($timestamp) || abs(($now ?? time()) - (int) $timestamp) > $this->tolerance) {
            throw WebhookVerificationException::timestampOutsideTolerance($this->tolerance);
        }

        $data = json_decode($payload, true);

        if (! is_array($data)) {
            throw WebhookVerificationException::invalidPayload();
        }

        return WebhookEvent::fromDelivery($data, (int) $timestamp, self::header($headers, self::DELIVERY_HEADER));
    }

    /**
     * The signature that the instance sends for this body.
     */
    private function sign(string $timestamp, string $payload): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$payload, $this->secret);
    }

    /**
     * @param  array<string, string|list<string|null>|null>  $headers
     */
    private static function header(array $headers, string $name): ?string
    {
        $value = $headers[strtolower($name)] ?? null;

        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        return is_string($value) ? trim($value) : null;
    }

    /**
     * Keep the secret out of `var_dump()` and `dd()` output.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['secret' => '********', 'tolerance' => $this->tolerance];
    }
}
