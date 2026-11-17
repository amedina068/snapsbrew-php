<?php

declare(strict_types=1);

namespace Snapsbrew\Laravel\Testing;

use ArrayAccess;
use LogicException;
use Psr\Http\Message\RequestInterface;

/**
 * One request that the fake received.
 *
 * Read a body or a query field with array access: `$request['template_id']`.
 *
 * @implements ArrayAccess<string, mixed>
 */
final readonly class RecordedRequest implements ArrayAccess
{
    /**
     * @param  array<array-key, mixed>  $query
     * @param  array<array-key, mixed>  $data
     * @param  array<string, list<string>>  $headers
     */
    public function __construct(
        public string $method,
        public string $url,
        public string $path,
        public array $query,
        public array $data,
        public string $body,
        public array $headers,
    ) {}

    public static function fromPsr(RequestInterface $request, string $apiUrl): self
    {
        $uri = $request->getUri();
        $url = (string) $uri;
        $path = ltrim(substr(strtok($url, '?') ?: $url, strlen($apiUrl)), '/');

        parse_str($uri->getQuery(), $query);

        $body = (string) $request->getBody();
        $data = json_decode($body, true);

        $headers = [];

        foreach ($request->getHeaders() as $name => $values) {
            $headers[strtolower((string) $name)] = array_values($values);
        }

        return new self(
            method: strtoupper($request->getMethod()),
            url: $url,
            path: $path,
            query: $query,
            data: is_array($data) ? $data : [],
            body: $body,
            headers: $headers,
        );
    }

    /**
     * True when the method and the path match, for example `POST v1/snaps`.
     * The path can use `*` wildcards.
     */
    public function is(string $method, string $pattern): bool
    {
        return $this->method === strtoupper($method) && self::matches($pattern, $this->path);
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)][0] ?? null;
    }

    public static function matches(string $pattern, string $path): bool
    {
        $regex = '#^'.str_replace('\*', '.*', preg_quote(trim($pattern, '/'), '#')).'$#u';

        return preg_match($regex, $path) === 1;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]) || isset($this->query[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? $this->query[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new LogicException('A recorded request is read-only.');
    }

    public function offsetUnset(mixed $offset): never
    {
        throw new LogicException('A recorded request is read-only.');
    }
}
