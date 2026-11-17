<?php

declare(strict_types=1);

namespace Snapsbrew\Laravel\Testing;

use Closure;
use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A PSR-18 client that records each request and answers with a stub or
 * with sample data. It never opens a connection.
 *
 * @internal
 */
final class FakeHttpClient implements ClientInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $stubs = [];

    /**
     * @var list<RecordedRequest>
     */
    private array $recorded = [];

    /**
     * @param  array<string, mixed>  $stubs
     */
    public function __construct(private readonly string $apiUrl, array $stubs = [])
    {
        foreach ($stubs as $pattern => $response) {
            $this->stub($pattern, $response);
        }
    }

    public function stub(string $pattern, mixed $response): void
    {
        // A later stub for the same pattern wins, so put it first.
        $this->stubs = [$pattern => $response] + $this->stubs;
    }

    /**
     * @return list<RecordedRequest>
     */
    public function recorded(): array
    {
        return $this->recorded;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $recorded = RecordedRequest::fromPsr($request, $this->apiUrl);
        $this->recorded[] = $recorded;

        $fake = $this->stubFor($recorded) ?? Fixtures::respond($recorded);

        $response = Psr17FactoryDiscovery::findResponseFactory()->createResponse($fake->status);

        foreach ($fake->headers() as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response->withBody(Psr17FactoryDiscovery::findStreamFactory()->createStream($fake->content()));
    }

    private function stubFor(RecordedRequest $request): ?FakeResponse
    {
        foreach ($this->stubs as $pattern => $response) {
            if (! self::patternMatches($pattern, $request)) {
                continue;
            }

            if ($response instanceof Closure) {
                $response = $response($request);
            }

            return FakeResponse::from($response);
        }

        return null;
    }

    private static function patternMatches(string $pattern, RecordedRequest $request): bool
    {
        $pattern = trim($pattern);

        if (preg_match('/^(GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS)\s+(.+)$/i', $pattern, $matches) === 1) {
            return $request->is($matches[1], $matches[2]);
        }

        return RecordedRequest::matches($pattern, $request->path);
    }
}
