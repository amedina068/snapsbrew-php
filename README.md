# Snapsbrew PHP SDK

A PHP client for a self-hosted Snapsbrew instance. The package also has a Laravel integration.

Snapsbrew renders images ("snaps") from templates. Each user runs an own instance, so the client needs the URL of your instance.

- Typed, read-only value objects for each response.
- A fluent builder for signed image URLs.
- A typed exception for each error.
- Retries with exponential backoff and jitter.
- Any PSR-18 HTTP client. Guzzle is the default.
- Laravel: a service provider, a facade, `Snapsbrew::fake()` and a Blade component.
- No telemetry.

## Supported versions

| Runtime | Versions |
|---|---|
| PHP | 8.2, 8.3, 8.4 |
| Laravel (optional) | 11, 12, 13 |

Laravel 13 needs PHP 8.3 or later.

## Installation

The package is not on Packagist yet. Add the GitHub repository to the `composer.json` file of your project:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/amedina068/snapsbrew-php" }
    ]
}
```

Then install the package:

```bash
composer require snapsbrew/snapsbrew-php
```

The SDK needs a PSR-18 HTTP client and PSR-17 factories. If your project has no HTTP client, install Guzzle:

```bash
composer require guzzlehttp/guzzle
```

## Quick start

```php
use Snapsbrew\Snapsbrew;
use Snapsbrew\Variants;

$snapsbrew = new Snapsbrew(
    url: 'https://snapsbrew.example.com',
    token: getenv('SNAPSBREW_TOKEN'),
);

$snap = $snapsbrew->snaps()->create($templateId, Variants::make()
    ->text('title', 'Hello')
    ->image('cover', 'https://example.com/cover.jpg'));

echo $snap->mediaUrl;
```

A render uses one credit. A preview costs no credit. Preview the variants first, then create the snap:

```php
$preview = $snapsbrew->snaps()->preview($templateId, $variants);

file_put_contents('preview.html', $preview->html);
```

## Configuration

| Argument | Default | Description |
|---|---|---|
| `url` | (required) | The URL of your instance. The SDK removes a trailing `/api`. |
| `token` | `null` | An API token with the `api_requests` ability. The SDK sends it as `Authorization: Bearer`. |
| `signingKey` | `null` | The key for signed image URLs. |
| `timeout` | `30.0` | The request timeout in seconds. |
| `retries` | `2` | The number of retries, or a `RetryPolicy`. |
| `httpClient` | Guzzle, or discovery | Any PSR-18 client. |
| `requestFactory`, `streamFactory` | discovery | PSR-17 factories. |

The SDK applies `timeout` only when it creates the Guzzle client. If you give your own `httpClient`, set the timeout on that client.

## Resources

```php
$snapsbrew->account();                                  // Account

$snapsbrew->templates()->list($cursor = null);          // CursorPage<Template>
$snapsbrew->templates()->all();                         // Generator<Template>, reads every page
$snapsbrew->templates()->find($uuid);                   // Template, with the publish state
$snapsbrew->templates()->sizes();                       // list<TemplateSize>
$snapsbrew->templates()->create('Launch card', size: 'open_graph');
$snapsbrew->templates()->update($uuid, 'New title');
$snapsbrew->templates()->publish($uuid);                // PublishedTemplate
$snapsbrew->templates()->delete($uuid);
$snapsbrew->templates()->preview($uuid, $variants);     // Preview (watermarked HTML)

$snapsbrew->snaps()->create($templateId, $variants, format: 'webp');
$snapsbrew->snaps()->create($templateId, $variants, async: true, webhookUrl: $url);
$snapsbrew->snaps()->createFromCollection($collectionId, $variants);  // list<Snap>
$snapsbrew->snaps()->preview($templateId, $variants);   // Preview

$snapsbrew->batches()->create($templateId, ['ada' => $adaVariants, 'grace' => $graceVariants]);
$snapsbrew->batches()->find($batchUuid);                // Batch, with the items
$snapsbrew->batches()->cancel($batchUuid);

$snapsbrew->screenshots()->create('https://example.com', width: 1200, height: 630);
$snapsbrew->screenshots()->list();
$snapsbrew->screenshots()->find($id);

$snapsbrew->media()->upload('/path/to/logo.png');       // Media
$snapsbrew->media()->list($page = 1);                   // Page<Media>

$snapsbrew->tags()->list('launch');
$snapsbrew->tags()->create('Launch');
$snapsbrew->tags()->sync($templateUuid, [1, 2]);

$snapsbrew->guides()->design();                         // Guide
$snapsbrew->guides()->platform('instagram');
$snapsbrew->guides()->purpose('link_preview');
```

### Variants

A variant changes one property of one layer. Read the layer keys from `templates()->find()`.

```php
$variants = Variants::make()
    ->text('title', 'Hello')
    ->image('cover', 'https://example.com/cover.jpg')
    ->background('bg', '#f0573f')
    ->qr('qr', 'https://example.com')
    ->barcode('code', '012345678905')
    ->hide('badge');
```

Each method that takes variants also accepts raw arrays: `[['layer' => 'title', 'text' => 'Hello']]`.

### Batches

A batch renders up to 100 snaps from one template in the background. If the items array has string keys, the SDK uses each key as the identifier of its item.

```php
$batch = $snapsbrew->batches()->create($templateId, [
    'ada' => Variants::make()->text('name', 'Ada'),
    'grace' => Variants::make()->text('name', 'Grace'),
]);

while (! $batch->isFinished()) {
    sleep(5);
    $batch = $snapsbrew->batches()->find($batch->uuid);
}

foreach ($batch->completedItems() as $item) {
    echo $item->identifier.': '.$item->mediaUrl.PHP_EOL;
}
```

The status `completed` means that at least one item rendered. Also read `$batch->failed`.

### Idempotent snaps

If you give an idempotency key, a retry of the same request returns the same snap. The instance does not render the snap again. The instance keeps a key for 24 hours.

```php
$snap = $snapsbrew->snaps()->create($templateId, $variants, idempotencyKey: "order-{$order->id}");

$snap->replayed; // true when the instance returned an earlier snap
```

## Signed URLs

A signed URL renders an image from a plain `<img src>` or an `og:image` tag, with no API call. The first request renders the image and uses one credit. The instance caches the image for later requests with the same URL.

```php
$snapsbrew = new Snapsbrew(url: $instanceUrl, signingKey: getenv('SNAPSBREW_SIGNING_KEY'));

$url = $snapsbrew->signedUrl($templateId)
    ->text('title', 'Hello')
    ->image('cover', $coverUrl)
    ->format('png')
    ->url();
```

The formats are `png`, `jpeg` and `webp`. Call `transparent()` for a transparent background. A transparent background is possible only with `png` and `webp`.

WARNING: The signing key is a secret. Build signed URLs only on the server. Do not put the signing key in browser code or in a mobile app.

The signature is the HMAC-SHA256 hex digest of the query parameters. The SDK sorts the parameters by key and joins them as `key=value` with `&`, with the raw values. The `variants` parameter is standard base64 of the JSON array.

## Errors

Each exception extends `Snapsbrew\Exceptions\SnapsbrewException`.

| Exception | Cause |
|---|---|
| `AuthenticationException` | HTTP 401. The token is missing or not valid. |
| `AuthorizationException` | HTTP 403. The token does not have the `api_requests` ability, or a policy refused the action. |
| `NotFoundException` | HTTP 404. The resource does not exist in your company. |
| `ConflictException` | HTTP 409. The idempotency key is in use, or the stored state changed. `errorCode()` gives the code. |
| `ValidationException` | HTTP 422. `errors()` gives the messages for each field. |
| `RateLimitException` | HTTP 429. `retryAfter()` gives the seconds to wait. |
| `ServerException` | HTTP 5xx. |
| `ApiException` | Another 4xx status. It is the parent of the HTTP exceptions above. |
| `ConnectionException` | The SDK did not get a response. |
| `UnexpectedResponseException` | The body does not have the expected shape. |
| `MissingCredentialsException` | The client has no URL, no token or no signing key for the call. |

```php
use Snapsbrew\Exceptions\ValidationException;

try {
    $snapsbrew->snaps()->create($templateId, $variants);
} catch (ValidationException $exception) {
    $exception->errors();          // ['variants.0.image_url' => ['...']]
    $exception->requestUuid();     // Give this value in a support request.
}
```

## Retries

The SDK sends a request again after these errors:

- HTTP 429
- HTTP 5xx
- a connection error

The SDK does not send a request again if the request is not idempotent and the instance can have received it. Thus a `POST` gets a retry only in these conditions:

- The response is a 429.
- The connection failed before the request left the client. For example, DNS failed or the instance refused the connection.
- The request has an idempotency key, or the endpoint is safe to call two times (publish, cancel a batch, sync tags).

The delay grows exponentially, with jitter: 0.5 s, 1 s, 2 s, and a maximum of 8 s. If the response has a `Retry-After` header, the SDK waits for that time. If `Retry-After` is more than 60 seconds, the SDK does not wait and throws the `RateLimitException`.

```php
use Snapsbrew\Http\RetryPolicy;

new Snapsbrew(url: $url, token: $token, retries: 0);   // no retries

new Snapsbrew(url: $url, token: $token, retries: new RetryPolicy(
    retries: 4,
    baseDelayMs: 250,
    maxDelayMs: 5_000,
    maxRetryAfter: 30,
));
```

## Laravel

The service provider and the facade load automatically.

### Configuration

Add the settings to your `.env` file:

```dotenv
SNAPSBREW_URL=https://snapsbrew.example.com
SNAPSBREW_TOKEN=your-api-token
SNAPSBREW_SIGNING_KEY=your-signing-key
```

`SNAPSBREW_TIMEOUT` and `SNAPSBREW_RETRIES` are optional. To publish the configuration file, run this command:

```bash
php artisan vendor:publish --tag=snapsbrew-config
```

### Facade

```php
use Snapsbrew\Laravel\Facades\Snapsbrew;

$snap = Snapsbrew::snaps()->create($templateId, [['layer' => 'title', 'text' => $post->title]]);

$url = Snapsbrew::signedUrl($templateId)->text('title', $post->title)->url();
```

You can also inject `Snapsbrew\Snapsbrew`. The container gives one shared client.

### Blade component

```blade
<x-snapsbrew::image
    :template="$templateId"
    :variants="[['layer' => 'title', 'text' => $post->title]]"
    format="png"
    alt="Cover image"
    class="rounded-lg"
/>
```

The component signs the URL on the server. Other attributes, for example `class` and `width`, go to the `<img>` tag.

```blade
<meta property="og:image" content="{{ Snapsbrew::signedUrl($templateId)->text('title', $post->title) }}">
```

### Testing

`Snapsbrew::fake()` replaces the client with a fake. The fake records each request and never opens a connection. It answers each endpoint with sample data in the real response shape.

```php
use Snapsbrew\Laravel\Facades\Snapsbrew;
use Snapsbrew\Laravel\Testing\RecordedRequest;

Snapsbrew::fake();

$this->post('/posts/1/publish');

Snapsbrew::assertSnapCreated($templateId);
Snapsbrew::assertSnapCreated(fn (RecordedRequest $request) => $request['variants'][0]['text'] === 'My post');
Snapsbrew::assertSnapNotCreated('other-template');
Snapsbrew::assertBatchCreated($templateId);
Snapsbrew::assertScreenshotCreated('https://example.com');
Snapsbrew::assertPreviewed($templateId);
Snapsbrew::assertSentCount(1);
Snapsbrew::assertNothingSent();
```

To control a response, give stubs. The key is an API path, with an optional HTTP method and `*` wildcards:

```php
Snapsbrew::fake([
    'v1/snaps' => Snapsbrew::response(['message' => 'Invalid.', 'errors' => ['template_id' => ['Unknown.']]], 422),
    'GET v1/templates/*' => fn (RecordedRequest $request) => ['data' => [/* ... */]],
    'v1/auth' => 401,
]);
```

A stubbed error status throws the same exception as in production.

## Testing the SDK

```bash
composer test       # Pest: unit tests and Laravel tests
composer analyse    # PHPStan at level max, with Larastan
composer lint       # Laravel Pint
```

The integration tests call a real instance. They run only when `SNAPSBREW_TEST_URL` and `SNAPSBREW_TEST_TOKEN` are set. The tests read the account, list the templates and request one preview. A preview costs no credit.

```bash
SNAPSBREW_TEST_URL=https://snapsbrew.example.com SNAPSBREW_TEST_TOKEN=... composer test:integration
```

## Security

To report a security problem, read [SECURITY.md](SECURITY.md).

## License

The owner has not chosen a license yet.
