# Snapsbrew PHP SDK

A PHP client for a self-hosted Snapsbrew instance. The package also has a Laravel integration.

Snapsbrew renders images ("snaps") from templates. Each user runs an own instance, so the client needs the URL of your instance.

- Typed, read-only value objects for each response.
- A fluent builder for signed image URLs.
- A verifier for signed webhooks.
- A typed exception for each error.
- Retries with exponential backoff and jitter.
- Any PSR-18 HTTP client. Guzzle is the default.
- Laravel: a service provider, a facade, `Snapsbrew::fake()` and a Blade component.
- No telemetry.

Read the SDK guides at [snapsbrew.com/docs/sdks/overview](https://snapsbrew.com/docs/sdks/overview).

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

Then install the package from the `main` branch:

```bash
composer require snapsbrew/snapsbrew-php:dev-main
```

The repository has no release tag yet. Thus `composer require snapsbrew/snapsbrew-php` without `:dev-main` fails. The `:dev-main` suffix lets Composer install the branch, also when `minimum-stability` is `stable`.

The repository is private. If Composer cannot read it, give Composer a GitHub token with read access to the repository:

```bash
composer config --global github-oauth.github.com <your-token>
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

A preview creates no snap. Preview the variants first, then create the snap:

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
$snapsbrew->templates()->create('Launch card', size: 'open_graph', groups: [['id' => 'header']]);
$snapsbrew->templates()->update($uuid, 'New title', groups: []);   // An empty list removes the groups.
$snapsbrew->templates()->publish($uuid);                // PublishedTemplate
$snapsbrew->templates()->delete($uuid);
$snapsbrew->templates()->preview($uuid, $variants);     // Preview (watermarked HTML)

$snapsbrew->documents()->find($uuid);                   // EditorDocument
$snapsbrew->documents()->replace($uuid, $document, revision: 4);
$snapsbrew->documents()->apply($uuid, $operations, revision: 4);
$snapsbrew->documents()->preflight($uuid);              // Preflight

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

Some calls have rules that the instance checks. The SDK does not check them before it sends the request, so a refused value gives a `ValidationException`:

- `media()->upload()` accepts jpg, jpeg, png, gif, webp and bmp files up to 10 MB. It refuses SVG files.
- `screenshots()->create()` accepts a `width` and a `height` from 1 to 4000 pixels. The default viewport is 1330x780.
- `templates()->list()` and `screenshots()->list()` refuse a `cursor` that is not valid.

`templates()->delete()` also deletes the snaps, the images, the versions and the forms of the template.

### Failed snaps

A failed snap has the status `failed` and a null `mediaUrl`. `$snap->lastError` holds a fixed code, never a raw error message:

| Code | Cause |
|---|---|
| `render_failed` (`Snap::ERROR_RENDER_FAILED`) | The render failed. |
| `webhook_redirect_refused` (`Snap::ERROR_WEBHOOK_REDIRECT_REFUSED`) | The webhook receiver answered with a redirect. The instance does not follow it. |
| `webhook_unreachable` (`Snap::ERROR_WEBHOOK_UNREACHABLE`) | The instance did not get a connection to the webhook receiver. |
| `webhook_http_<status>` | The webhook receiver answered with this error status, for example `webhook_http_500`. |

A batch item has `lastError` too. Its only code is `render_failed`.

### Editor documents

The editor document is the v2 draft of a template: its layers, its groups and their properties. The next render draws from this draft.

Each write is a compare-and-swap. Read the document, and send its `revision` with the write. If the stored revision moved, the instance saves nothing and throws a `ConflictException`.

```php
use Snapsbrew\Exceptions\ConflictException;

$document = $snapsbrew->documents()->find($uuid);

try {
    $document = $snapsbrew->documents()->apply($uuid, [
        ['operation' => 'set_layer_property', 'layer_key' => 'headline', 'property' => 'fontWeight', 'value' => 700],
    ], revision: $document->revision);
} catch (ConflictException $exception) {
    $exception->currentRevision();   // The stored revision.
    $exception->currentDocument();   // The stored document. Apply your change to it, then write again.
}

if (! $snapsbrew->documents()->preflight($uuid)->passes()) {
    // Correct the issues before you publish the template.
}
```

- `apply()` takes up to 100 operations: `set_layer_property` and `set_layer_type`. The instance applies all of them, or none of them.
- `replace()` sends the whole document. A layer or a property that you leave out is removed.
- A rejected operation gives a `ValidationException` with no field errors. `errorCode()` gives the code, for example `editor_operation_layer_not_found`.
- A document over a size limit gives a `ValidationException` with the code under `errors()['document']`.
- `GET v1/guides/editor-document` (`guides()->editorDocument()`) describes the document.

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

A key belongs to one snap or to one batch. If you send a snap key to `batches()->create()`, or a batch key to `snaps()->create()`, the instance answers with a `ConflictException`. A repeat with the same key does not succeed. Send a new key.

## Signed URLs

A signed URL renders an image from a plain `<img src>` or an `og:image` tag, with no API call. The first request renders the image. The instance caches the image for later requests with the same URL.

The instance accepts a signature from any active signing key of the company that owns the template.

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

The signed URL route is on the site root, not under `/api`. A good URL redirects to the image. If the signature or the template is not valid, the instance sends a placeholder image with status 200, not an error status.

The instance answers 400 to a URL with the reserved parameter `identifier` or `api_log_uuid`. The builder never adds these parameters. Over the rate limit, the route answers 429.

## Webhooks

An async snap and a batch post a webhook to their `webhookUrl`. The instance signs each delivery with the webhook signing secret of your company. **Account > Developers** shows the secret and rotates it. Verify each delivery before you use it.

WARNING: The webhook signing secret is a secret. Verify webhooks only on the server.

```php
use Snapsbrew\Exceptions\WebhookVerificationException;
use Snapsbrew\Webhooks\Webhook;
use Snapsbrew\Webhooks\WebhookEvent;

$webhook = new Webhook(getenv('SNAPSBREW_WEBHOOK_SECRET'));

try {
    $event = $webhook->verify(file_get_contents('php://input'), getallheaders());
} catch (WebhookVerificationException $exception) {
    http_response_code(400);
    exit;
}

if ($event->is(WebhookEvent::SNAP_COMPLETED)) {
    $imageUrl = $event->snap()->mediaUrl;
}
```

- Give the raw request body. Do not decode the JSON and encode it again. The instance escapes `/` and the characters that are not ASCII, so a new encoding gives other bytes.
- The signature is the HMAC-SHA256 hex digest of `{X-Snapsbrew-Timestamp}.{raw body}`. The SDK compares it in constant time.
- The SDK refuses a delivery that the instance signed more than 300 seconds before or after the current time. To change this tolerance, give `tolerance` to the constructor.
- `$exception->reason` tells why the SDK refused the delivery: `VerificationFailure::Signature`, `VerificationFailure::Timestamp` or `VerificationFailure::Payload`.
- `$event->deliveryId` is the `X-Snapsbrew-Delivery` header. All attempts of one delivery have the same value. Use it to ignore a duplicate.

| Event | Constant | Payload |
|---|---|---|
| `snap.completed` | `WebhookEvent::SNAP_COMPLETED` | `$event->snap()` |
| `snap.failed` | `WebhookEvent::SNAP_FAILED` | `$event->snap()`, with `lastError` `render_failed` and a null `mediaUrl` |
| `snap-import.completed` | `WebhookEvent::BATCH_COMPLETED` | `$event->batch()` and `$event->template()` |
| `snap-import.failed` | `WebhookEvent::BATCH_FAILED` | `$event->batch()` and `$event->template()` |
| `snap-import.canceled` | `WebhookEvent::BATCH_CANCELED` | `$event->batch()` and `$event->template()` |

The instance can add event types. The SDK accepts each event type. Ignore the types that you do not use. `$event->payload` holds the decoded body.

## Errors

Each exception extends `Snapsbrew\Exceptions\SnapsbrewException`.

| Exception | Cause |
|---|---|
| `AuthenticationException` | HTTP 401. The token is missing or not valid. |
| `AuthorizationException` | HTTP 403. The token does not have the `api_requests` ability (`Invalid ability provided.`), or a policy refused the action. Each bearer endpoint can send it. |
| `NotFoundException` | HTTP 404. The resource does not exist in your company. For a guide, `available()` gives the valid keys. |
| `ConflictException` | HTTP 409. The instance refused the write. `errorCode()` gives the reason. |
| `ValidationException` | HTTP 422. `errors()` gives the messages for each field. A rejected editor operation has no field errors, and `errorCode()` gives its code. |
| `RateLimitException` | HTTP 429. `retryAfter()` gives the seconds to wait. `limit()` and `remaining()` give the rate limit headers. |
| `ServerException` | HTTP 5xx. A 500 from `snaps()->create()` or `batches()->create()` has the code `internal_error`. |
| `ApiException` | Another 4xx status. It is the parent of the HTTP exceptions above. |
| `ConnectionException` | The SDK did not get a response. |
| `UnexpectedResponseException` | The body does not have the expected shape. |
| `MissingCredentialsException` | The client has no URL, no token or no signing key for the call, or the webhook secret is empty. |
| `WebhookVerificationException` | A webhook delivery is not valid. |

Each HTTP exception has `errorCode()`. It gives the `code` field of the body, or null. These are the codes of a `ConflictException`:

| Code | Cause | Retry |
|---|---|---|
| `idempotency_key_in_progress` | Another request with the same key is still running. | Yes, the SDK retries it. |
| `idempotency_key_belongs_to_snap` | A single snap owns the key, so a batch cannot use it. | No. Send a new key. |
| `idempotency_key_belongs_to_batch` | A batch owns the key, so a single snap cannot use it. | No. Send a new key. |
| `legacy_edit_rejected_for_v2` | The template uses the v2 editor document, so `templates()->update()` cannot change it. | No. Use `documents()`. |
| `editor_document_conflict` | The revision of the editor document moved. `currentRevision()` and `currentDocument()` give the stored values. | No. Apply your change to the current document, then write again. |

The constants on `ConflictException` hold these codes. `inProgress()` is true for the first code.

The message of a 404 comes from the instance, for example `No query results for model [App\Models\Template].`. Do not match on the text of a message. Use the exception class and `errorCode()`.

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
- HTTP 409 with the code `idempotency_key_in_progress`, if the request has an idempotency key
- a connection error

The SDK does not send a request again if the request is not idempotent and the instance can have received it. Thus a `POST` gets a retry only in these conditions:

- The response is a 429.
- The connection failed before the request left the client. For example, DNS failed or the instance refused the connection.
- The request has an idempotency key, or the endpoint is safe to call two times (publish, cancel a batch, sync tags).

The SDK does not retry a 409 with another code, because a repeat with the same key does not succeed.

The delay grows exponentially, with jitter: 0.5 s, 1 s, 2 s, and a maximum of 8 s. If the response has a `Retry-After` header, the SDK waits for that time. If `Retry-After` is more than 60 seconds, the SDK does not wait and throws the `RateLimitException`.

The instance allows 60 requests per minute for one user. The two preview endpoints allow 10 requests per minute. The editor document endpoints allow 30 requests per minute for one user and 180 for one company. Over a limit, the instance sends a 429 with `Retry-After`.

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
SNAPSBREW_WEBHOOK_SECRET=whsec_your-webhook-secret
```

`SNAPSBREW_TIMEOUT`, `SNAPSBREW_RETRIES` and `SNAPSBREW_WEBHOOK_TOLERANCE` are optional. To publish the configuration file, run this command:

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

### Webhooks

The container gives a `Snapsbrew\Webhooks\Webhook` with `SNAPSBREW_WEBHOOK_SECRET` and `SNAPSBREW_WEBHOOK_TOLERANCE`. Exclude the webhook route from CSRF protection.

```php
use Illuminate\Http\Request;
use Snapsbrew\Exceptions\WebhookVerificationException;
use Snapsbrew\Webhooks\Webhook;
use Snapsbrew\Webhooks\WebhookEvent;

Route::post('/webhooks/snapsbrew', function (Request $request, Webhook $webhook) {
    try {
        $event = $webhook->verify($request->getContent(), $request->headers->all());
    } catch (WebhookVerificationException) {
        abort(400);
    }

    if ($event->is(WebhookEvent::SNAP_FAILED)) {
        report("Snap {$event->snap()->id} failed: {$event->snap()->lastError}");
    }

    return response()->noContent();
});
```

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

The integration tests call a real instance. They run only when `SNAPSBREW_TEST_URL` and `SNAPSBREW_TEST_TOKEN` are set. The tests read the account, list the templates and request one preview. A preview stores nothing.

```bash
SNAPSBREW_TEST_URL=https://snapsbrew.example.com SNAPSBREW_TEST_TOKEN=... composer test:integration
```

## Security

To report a security problem, read [SECURITY.md](SECURITY.md).

## License

The owner has not chosen a license yet.
