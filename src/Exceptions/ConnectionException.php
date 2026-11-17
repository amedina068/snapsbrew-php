<?php

declare(strict_types=1);

namespace Snapsbrew\Exceptions;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\NetworkExceptionInterface;

/**
 * The SDK did not get a response from the instance.
 *
 * `$requestSent` is false only when the SDK is sure that the instance did not
 * receive the request, for example when DNS or the TCP connection failed.
 */
final class ConnectionException extends SnapsbrewException
{
    /**
     * Error texts that show that the request did not leave the client.
     * cURL error 5 and 6 are DNS failures. cURL error 7 is a refused connection.
     */
    private const NOT_SENT_PATTERN = '/cURL error (5|6|7)\b|could ?n[o\']t resolve|connection refused|failed to connect|connection timed out/i';

    public function __construct(string $message, public readonly bool $requestSent, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function from(ClientExceptionInterface $exception): self
    {
        $requestSent = ! ($exception instanceof NetworkExceptionInterface
            && preg_match(self::NOT_SENT_PATTERN, $exception->getMessage()) === 1);

        return new self(
            'Could not connect to the Snapsbrew instance: '.$exception->getMessage(),
            $requestSent,
            $exception,
        );
    }
}
