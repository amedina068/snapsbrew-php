<?php

declare(strict_types=1);

namespace Snapsbrew;

use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use SensitiveParameter;
use Snapsbrew\Data\Account;
use Snapsbrew\Exceptions\MissingCredentialsException;
use Snapsbrew\Http\HttpClientFactory;
use Snapsbrew\Http\RetryPolicy;
use Snapsbrew\Http\Transport;
use Snapsbrew\Resources\Batches;
use Snapsbrew\Resources\Guides;
use Snapsbrew\Resources\Media;
use Snapsbrew\Resources\Screenshots;
use Snapsbrew\Resources\Snaps;
use Snapsbrew\Resources\Tags;
use Snapsbrew\Resources\Templates;
use Snapsbrew\Signing\PendingSignedUrl;
use Snapsbrew\Signing\UrlSigner;

/**
 * The client for one Snapsbrew instance.
 *
 *     $snapsbrew = new Snapsbrew(
 *         url: 'https://snapsbrew.example.com',
 *         token: $token,
 *         signingKey: $signingKey,
 *     );
 *
 *     $snap = $snapsbrew->snaps()->create($templateId, Variants::make()->text('title', 'Hello'));
 */
class Snapsbrew
{
    public const VERSION = '0.1.0';

    /**
     * The URL of the instance, with no trailing slash and no `/api` suffix.
     */
    public readonly string $url;

    protected readonly Transport $transport;

    private readonly ?UrlSigner $signer;

    /**
     * @param  string  $url  The URL of your instance. The SDK removes a trailing `/api`.
     * @param  string|null  $token  An API token with the `api_requests` ability.
     * @param  string|null  $signingKey  The key for signed image URLs.
     * @param  float  $timeout  The request timeout in seconds. The SDK uses it when it creates the HTTP client.
     * @param  int|RetryPolicy  $retries  The number of retries, or a full retry policy.
     * @param  ClientInterface|null  $httpClient  Any PSR-18 client. If you give one, configure its timeout yourself.
     */
    public function __construct(
        string $url,
        #[SensitiveParameter] ?string $token = null,
        #[SensitiveParameter] ?string $signingKey = null,
        float $timeout = 30.0,
        int|RetryPolicy $retries = 2,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ) {
        $this->url = self::normalizeUrl($url);
        $this->signer = $signingKey === null || $signingKey === '' ? null : new UrlSigner($signingKey);

        $this->transport = new Transport(
            apiUrl: $this->url.'/api',
            token: $token,
            client: $httpClient ?? HttpClientFactory::make($timeout),
            requests: $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory(),
            streams: $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory(),
            retry: $retries instanceof RetryPolicy ? $retries : new RetryPolicy(retries: max(0, $retries)),
        );
    }

    /**
     * The account that owns the token. Use it to make sure that a token works.
     */
    public function account(): Account
    {
        return Account::fromArray($this->transport->get('v1/auth')->data());
    }

    public function templates(): Templates
    {
        return new Templates($this->transport);
    }

    public function snaps(): Snaps
    {
        return new Snaps($this->transport);
    }

    public function batches(): Batches
    {
        return new Batches($this->transport);
    }

    public function screenshots(): Screenshots
    {
        return new Screenshots($this->transport);
    }

    public function media(): Media
    {
        return new Media($this->transport);
    }

    public function guides(): Guides
    {
        return new Guides($this->transport);
    }

    public function tags(): Tags
    {
        return new Tags($this->transport);
    }

    /**
     * Start a signed image URL for a template.
     *
     * Build signed URLs only on the server. The signing key is a secret.
     */
    public function signedUrl(string $templateId): PendingSignedUrl
    {
        if ($this->signer === null) {
            throw MissingCredentialsException::signingKey();
        }

        return new PendingSignedUrl($this->signer, $this->url.'/signed-url/image.jpg', $templateId);
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['url' => $this->url];
    }

    private static function normalizeUrl(string $url): string
    {
        $url = rtrim(trim($url), '/');

        if (str_ends_with($url, '/api')) {
            $url = substr($url, 0, -4);
        }

        if ($url === '') {
            throw MissingCredentialsException::url();
        }

        return $url;
    }
}
