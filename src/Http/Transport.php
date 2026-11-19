<?php

declare(strict_types=1);

namespace Snapsbrew\Http;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Snapsbrew\Exceptions\ApiException;
use Snapsbrew\Exceptions\ConflictException;
use Snapsbrew\Exceptions\ConnectionException;
use Snapsbrew\Exceptions\MissingCredentialsException;
use Snapsbrew\Snapsbrew;

/**
 * Sends requests to the API of one instance, with the token and the retry
 * policy.
 *
 * The SDK sends a request again after a 429, a 5xx or a connection error.
 * It sends a request that is not idempotent again only after a 429, or after
 * a connection error that occurred before the instance received the request.
 * A request with an idempotency key also gets a retry after a 409 with the
 * code `idempotency_key_in_progress`.
 *
 * @internal
 */
final readonly class Transport
{
    private const IDEMPOTENT_METHODS = ['GET', 'HEAD', 'PUT', 'DELETE', 'OPTIONS'];

    public function __construct(
        private string $apiUrl,
        #[\SensitiveParameter] private ?string $token,
        private ClientInterface $client,
        private RequestFactoryInterface $requests,
        private StreamFactoryInterface $streams,
        private RetryPolicy $retry,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     */
    public function get(string $path, array $query = []): Response
    {
        return $this->send('GET', $path, $query);
    }

    /**
     * A body with an `idempotency_key` makes the request idempotent.
     *
     * @param  array<string, mixed>  $data
     * @param  bool  $idempotent  True when the endpoint is safe to call two times.
     */
    public function post(string $path, array $data = [], bool $idempotent = false): Response
    {
        $keyed = isset($data['idempotency_key']);

        return $this->send('POST', $path, body: $this->encode($data), idempotent: $idempotent || $keyed, keyed: $keyed);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function patch(string $path, array $data = []): Response
    {
        return $this->send('PATCH', $path, body: $this->encode($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function put(string $path, array $data = []): Response
    {
        return $this->send('PUT', $path, body: $this->encode($data));
    }

    public function delete(string $path): Response
    {
        return $this->send('DELETE', $path);
    }

    public function upload(string $path, Multipart $multipart): Response
    {
        return $this->send('POST', $path, body: $multipart->body, contentType: $multipart->contentType());
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function send(
        string $method,
        string $path,
        array $query = [],
        ?string $body = null,
        string $contentType = 'application/json',
        bool $idempotent = false,
        bool $keyed = false,
    ): Response {
        $idempotent = $idempotent || in_array($method, self::IDEMPOTENT_METHODS, true);
        $attempts = 0;

        while (true) {
            $attempts++;

            try {
                $response = Response::fromPsr(
                    $this->client->sendRequest($this->request($method, $path, $query, $body, $contentType))
                );
            } catch (ClientExceptionInterface $exception) {
                $error = ConnectionException::from($exception);

                if ($this->retry->allows($attempts) && ($idempotent || ! $error->requestSent)) {
                    $this->retry->sleep($this->retry->delay($attempts));

                    continue;
                }

                throw $error;
            }

            if ($response->status < 400) {
                return $response;
            }

            if ($this->shouldRetry($response, $attempts, $idempotent, $keyed)) {
                $this->retry->sleep($this->retry->delay($attempts, $response->retryAfter()));

                continue;
            }

            throw ApiException::fromResponse($response);
        }
    }

    private function shouldRetry(Response $response, int $attempts, bool $idempotent, bool $keyed): bool
    {
        if (! $this->retry->allows($attempts) || ! $this->retry->accepts($response->retryAfter())) {
            return false;
        }

        return match (true) {
            $response->status === 429 => true,
            $response->status === 409 => $keyed && ($response->jsonOrEmpty()['code'] ?? null) === ConflictException::IDEMPOTENCY_KEY_IN_PROGRESS,
            $response->status >= 500 => $idempotent,
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function request(string $method, string $path, array $query, ?string $body, string $contentType): RequestInterface
    {
        if ($this->token === null || $this->token === '') {
            throw MissingCredentialsException::token();
        }

        $url = $this->apiUrl.'/'.ltrim($path, '/');

        if ($query !== []) {
            $url .= '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $request = $this->requests->createRequest($method, $url)
            ->withHeader('Authorization', 'Bearer '.$this->token)
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', 'snapsbrew-php/'.Snapsbrew::VERSION);

        if ($body === null) {
            return $request;
        }

        return $request
            ->withHeader('Content-Type', $contentType)
            ->withBody($this->streams->createStream($body));
    }

    /**
     * Keep the token out of `var_dump()` and `dd()` output.
     *
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['apiUrl' => $this->apiUrl, 'token' => '********'];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function encode(array $data): string
    {
        return $data === [] ? '{}' : json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
