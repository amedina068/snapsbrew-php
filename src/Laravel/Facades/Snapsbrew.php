<?php

declare(strict_types=1);

namespace Snapsbrew\Laravel\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;
use Snapsbrew\Laravel\Testing\FakeResponse;
use Snapsbrew\Laravel\Testing\SnapsbrewFake;
use Snapsbrew\Snapsbrew as Client;

/**
 * @method static \Snapsbrew\Data\Account account()
 * @method static \Snapsbrew\Resources\Templates templates()
 * @method static \Snapsbrew\Resources\Documents documents()
 * @method static \Snapsbrew\Resources\Snaps snaps()
 * @method static \Snapsbrew\Resources\Batches batches()
 * @method static \Snapsbrew\Resources\Screenshots screenshots()
 * @method static \Snapsbrew\Resources\Media media()
 * @method static \Snapsbrew\Resources\Guides guides()
 * @method static \Snapsbrew\Resources\Tags tags()
 * @method static \Snapsbrew\Signing\PendingSignedUrl signedUrl(string $templateId)
 * @method static SnapsbrewFake stub(string $pattern, mixed $response)
 * @method static list<\Snapsbrew\Laravel\Testing\RecordedRequest> recorded(callable|null $callback = null)
 * @method static void assertSent(callable $callback)
 * @method static void assertNotSent(callable $callback)
 * @method static void assertNothingSent()
 * @method static void assertSentCount(int $count)
 * @method static void assertSnapCreated(string|callable|null $template = null)
 * @method static void assertSnapNotCreated(string|callable|null $template = null)
 * @method static void assertSnapCreatedTimes(int $times, string|callable|null $template = null)
 * @method static void assertBatchCreated(string|callable|null $template = null)
 * @method static void assertBatchNotCreated(string|callable|null $template = null)
 * @method static void assertScreenshotCreated(string|callable|null $url = null)
 * @method static void assertPreviewed(string|callable|null $template = null)
 *
 * @see Client
 * @see SnapsbrewFake
 */
final class Snapsbrew extends Facade
{
    /**
     * Replace the client with a fake that records each request.
     *
     * The fake answers each endpoint with realistic sample data. Give stubs
     * to control a response: the key is an API path (with an optional HTTP
     * method and `*` wildcards), and the value is a response.
     *
     *     Snapsbrew::fake([
     *         'v1/snaps' => Snapsbrew::response(['message' => 'Invalid.'], 422),
     *     ]);
     *
     * @param  array<string, FakeResponse|array<array-key, mixed>|string|int|Closure>  $stubs
     */
    public static function fake(array $stubs = []): SnapsbrewFake
    {
        $root = self::getFacadeRoot();

        if ($root instanceof SnapsbrewFake) {
            foreach ($stubs as $pattern => $response) {
                $root->stub($pattern, $response);
            }

            return $root;
        }

        $config = self::$app?->make('config');
        $signingKey = $config?->get('snapsbrew.signing_key');
        $url = $config?->get('snapsbrew.url');

        self::swap($fake = new SnapsbrewFake(
            stubs: $stubs,
            signingKey: is_string($signingKey) && $signingKey !== '' ? $signingKey : 'fake-signing-key',
            url: is_string($url) && $url !== '' ? $url : 'https://snapsbrew.test',
        ));

        return $fake;
    }

    /**
     * Build a response for a stub.
     *
     * @param  array<array-key, mixed>|string  $body
     * @param  array<string, string>  $headers
     */
    public static function response(array|string $body = [], int $status = 200, array $headers = []): FakeResponse
    {
        return new FakeResponse($body, $status, $headers);
    }

    protected static function getFacadeAccessor(): string
    {
        return Client::class;
    }
}
