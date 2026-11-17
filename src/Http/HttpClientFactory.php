<?php

declare(strict_types=1);

namespace Snapsbrew\Http;

use GuzzleHttp\Client as GuzzleClient;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;

/**
 * Finds a PSR-18 client when the caller does not give one.
 *
 * PSR-18 has no timeout setting. If Guzzle is installed, the SDK creates a
 * Guzzle client with the timeout. Otherwise it uses the client that
 * `php-http/discovery` finds, and the timeout of that client applies.
 *
 * @internal
 */
final class HttpClientFactory
{
    public static function make(float $timeout): ClientInterface
    {
        if (class_exists(GuzzleClient::class)) {
            return new GuzzleClient([
                'timeout' => $timeout,
                'connect_timeout' => min($timeout, 10.0),
            ]);
        }

        return Psr18ClientDiscovery::find();
    }
}
