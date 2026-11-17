<?php

declare(strict_types=1);

namespace Snapsbrew\Laravel\Testing;

use Closure;
use PHPUnit\Framework\Assert as PHPUnit;
use SensitiveParameter;
use Snapsbrew\Http\RetryPolicy;
use Snapsbrew\Snapsbrew;

/**
 * A Snapsbrew client that records each request and never opens a connection.
 *
 * The fake runs the real SDK code: the requests, the value objects and the
 * exceptions are the same as in production. Only the HTTP client is fake.
 */
class SnapsbrewFake extends Snapsbrew
{
    private FakeHttpClient $client;

    /**
     * @param  array<string, mixed>  $stubs
     */
    public function __construct(
        array $stubs = [],
        #[SensitiveParameter] ?string $signingKey = 'fake-signing-key',
        string $url = 'https://snapsbrew.test',
    ) {
        $this->client = new FakeHttpClient(rtrim($url, '/').'/api', $stubs);

        parent::__construct(
            url: $url,
            token: 'fake-token',
            signingKey: $signingKey,
            retries: RetryPolicy::none(),
            httpClient: $this->client,
        );
    }

    /**
     * Stub the response for one endpoint, for example `POST v1/snaps`.
     */
    public function stub(string $pattern, mixed $response): self
    {
        $this->client->stub($pattern, $response);

        return $this;
    }

    /**
     * The recorded requests, filtered by an optional callback.
     *
     * @param  (callable(RecordedRequest): bool)|null  $callback
     * @return list<RecordedRequest>
     */
    public function recorded(?callable $callback = null): array
    {
        $recorded = $this->client->recorded();

        return $callback === null ? $recorded : array_values(array_filter($recorded, $callback));
    }

    /**
     * @param  callable(RecordedRequest): bool  $callback
     */
    public function assertSent(callable $callback): void
    {
        PHPUnit::assertNotEmpty($this->recorded($callback), 'An expected Snapsbrew request was not sent.');
    }

    /**
     * @param  callable(RecordedRequest): bool  $callback
     */
    public function assertNotSent(callable $callback): void
    {
        PHPUnit::assertEmpty($this->recorded($callback), 'An unexpected Snapsbrew request was sent.');
    }

    public function assertNothingSent(): void
    {
        PHPUnit::assertEmpty($this->recorded(), 'Snapsbrew requests were sent unexpectedly.');
    }

    public function assertSentCount(int $count): void
    {
        PHPUnit::assertCount($count, $this->recorded(), "Expected {$count} Snapsbrew requests.");
    }

    /**
     * Assert that a snap was created. Give a template ID, or a callback that
     * receives the recorded request.
     *
     * @param  string|(callable(RecordedRequest): bool)|null  $template
     */
    public function assertSnapCreated(string|callable|null $template = null): void
    {
        PHPUnit::assertNotEmpty(
            $this->matching('POST', 'v1/snaps', 'template_id', $template),
            'The expected snap was not created.',
        );
    }

    /**
     * @param  string|(callable(RecordedRequest): bool)|null  $template
     */
    public function assertSnapNotCreated(string|callable|null $template = null): void
    {
        PHPUnit::assertEmpty(
            $this->matching('POST', 'v1/snaps', 'template_id', $template),
            'An unexpected snap was created.',
        );
    }

    /**
     * @param  string|(callable(RecordedRequest): bool)|null  $template
     */
    public function assertSnapCreatedTimes(int $times, string|callable|null $template = null): void
    {
        $count = count($this->matching('POST', 'v1/snaps', 'template_id', $template));

        PHPUnit::assertSame($times, $count, "The snap was created {$count} times instead of {$times} times.");
    }

    /**
     * @param  string|(callable(RecordedRequest): bool)|null  $template
     */
    public function assertBatchCreated(string|callable|null $template = null): void
    {
        PHPUnit::assertNotEmpty(
            $this->matching('POST', 'v1/snaps/batches', 'template_id', $template),
            'The expected batch was not created.',
        );
    }

    /**
     * @param  string|(callable(RecordedRequest): bool)|null  $template
     */
    public function assertBatchNotCreated(string|callable|null $template = null): void
    {
        PHPUnit::assertEmpty(
            $this->matching('POST', 'v1/snaps/batches', 'template_id', $template),
            'An unexpected batch was created.',
        );
    }

    /**
     * @param  string|(callable(RecordedRequest): bool)|null  $url
     */
    public function assertScreenshotCreated(string|callable|null $url = null): void
    {
        PHPUnit::assertNotEmpty(
            $this->matching('POST', 'v1/screenshots', 'url', $url),
            'The expected screenshot was not created.',
        );
    }

    /**
     * Assert that a snap preview or a template preview was requested.
     *
     * @param  string|(callable(RecordedRequest): bool)|null  $template
     */
    public function assertPreviewed(string|callable|null $template = null): void
    {
        $previews = $this->recorded(static fn (RecordedRequest $request): bool => $request->is('GET', 'v1/snaps/preview')
            || $request->is('GET', 'v1/templates/*/preview'));

        $previews = array_filter($previews, static function (RecordedRequest $request) use ($template): bool {
            if ($template === null) {
                return true;
            }

            if (is_string($template)) {
                return $request['template_id'] === $template
                    || $request->path === 'v1/templates/'.rawurlencode($template).'/preview';
            }

            return (bool) $template($request);
        });

        PHPUnit::assertNotEmpty($previews, 'The expected preview was not requested.');
    }

    /**
     * @param  string|(callable(RecordedRequest): bool)|null  $expected
     * @return list<RecordedRequest>
     */
    private function matching(string $method, string $path, string $field, string|callable|null $expected): array
    {
        return $this->recorded(static function (RecordedRequest $request) use ($method, $path, $field, $expected): bool {
            if (! $request->is($method, $path)) {
                return false;
            }

            return match (true) {
                $expected === null => true,
                is_string($expected) => $request[$field] === $expected,
                default => (bool) Closure::fromCallable($expected)($request),
            };
        });
    }
}
