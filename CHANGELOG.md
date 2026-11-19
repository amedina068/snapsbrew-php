# Changelog

All notable changes to this project are in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). The project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.0] - Unreleased

### Added

- `documents()`: read, replace and apply operations to the v2 editor document of a template, and run a preflight. The SDK returns `EditorDocument`, `Preflight` and `PreflightIssue` objects.
- `ConflictException::currentRevision()` and `ConflictException::currentDocument()`. They read the body of an `editor_document_conflict`.
- `ConflictException::EDITOR_DOCUMENT_CONFLICT`.
- `NotFoundException::available()`. It gives the valid keys when a platform or a purpose guide does not exist.
- The `groups` argument on `templates()->create()` and `templates()->update()`. An empty list removes every group.
- `Snap::ERROR_RENDER_FAILED`, `Snap::ERROR_WEBHOOK_REDIRECT_REFUSED` and `Snap::ERROR_WEBHOOK_UNREACHABLE`.
- A webhook verifier: `Snapsbrew\Webhooks\Webhook`. It checks `X-Snapsbrew-Signature` in constant time, and it refuses a timestamp outside the tolerance (300 seconds by default).
- `WebhookEvent`, with the event type, the decoded payload, the timestamp and the `X-Snapsbrew-Delivery` value in `deliveryId`. `snap()`, `batch()` and `template()` read the payload.
- Event constants for `snap.completed`, `snap.failed`, `snap-import.completed`, `snap-import.failed` and `snap-import.canceled`.
- `WebhookVerificationException`, with a `VerificationFailure` reason.
- Laravel: a shared `Webhook` in the container, and the `SNAPSBREW_WEBHOOK_SECRET` and `SNAPSBREW_WEBHOOK_TOLERANCE` settings.
- The fake answers the editor document endpoints.
- Tests for a 403 on each bearer operation, for the shared webhook vectors, and for a 422 from a bad cursor.

### Changed

- The message of an error body with a `code` and no `message` includes the code, for example `The Snapsbrew instance answered with HTTP status 422 (editor_operation_layer_not_found).`
- `Snap::$mediaUrl` and `BatchItem::$mediaUrl` are null when the instance sends an empty string. A failed snap has an empty `media_url`.
- The docs of `Snap::$lastError` and `BatchItem::$lastError` list the error codes.
- The docs of `media()->upload()` and `screenshots()->create()` give the rules of the instance. The SDK does not check these rules.
- The User-Agent header sends the version `0.2.0`.
- The README installs the package from the GitHub repository with `:dev-main`, because the repository has no tag.
- The README links to the SDK guides at `https://snapsbrew.com/docs/sdks/overview`.
- The README describes the editor documents, the failed snap codes, the webhooks, the signed URL key rules, the reserved signed URL parameters and the rate limits of the editor document endpoints.
- The README says that `templates()->delete()` also deletes the snaps, the images, the versions and the forms of the template.

### Removed

- The README and the docs no longer say that a render uses a credit. The instance keeps a usage log with no balance.

## [0.1.0] - Unreleased

### Added

- A client for one Snapsbrew instance, with an API token, a timeout and a retry policy.
- Resources: account, templates, snaps, snap batches, screenshots, media, tags and guides.
- Typed, read-only value objects for each response.
- A fluent `Variants` builder.
- A fluent builder for signed image URLs.
- A typed exception for each error: 401, 403, 404, 409, 422, 429, 5xx, connection and unexpected response.
- Retries after a 429, a 5xx or a connection error, with exponential backoff, jitter and `Retry-After`.
- PSR-18 and PSR-17 support with `php-http/discovery`.
- Laravel: a service provider with auto-discovery, a configuration file, a facade, `Snapsbrew::fake()` with assertions, and the `<x-snapsbrew::image>` Blade component.
- `errorCode()` on each HTTP exception. It gives the `code` field of the error body, for example `internal_error`.
- Constants on `ConflictException` for the codes `idempotency_key_in_progress`, `idempotency_key_belongs_to_snap`, `idempotency_key_belongs_to_batch` and `legacy_edit_rejected_for_v2`.
- `ConflictException::inProgress()`.
- `RateLimitException::limit()` and `RateLimitException::remaining()`. They read the `X-RateLimit-Limit` and `X-RateLimit-Remaining` headers.
- `Media` has the fields `uuid`, `name`, `mimeType`, `size`, `originalUrl` and `createdAt`.

### Changed

- The SDK retries a request with an idempotency key after a 409 with the code `idempotency_key_in_progress`. It does not retry the other 409 codes.
- The transport reads the idempotency key from the request body. A `POST` with an `idempotency_key` is idempotent.
- `Layer::$key` and `BatchItem::$identifier` can be null, as the API documents.
- `Layer` has no `type` field. The template endpoints do not send it.
- The fake sends a screenshot list item with an `image_url`, and a media upload with every field.
- The README describes the conflict codes, the rate limits and the placeholder image of a signed URL that is not valid.
- The `Media` docs list the nine fields that the upload and the list send. The server no longer sends `model_type`, `collection_name`, `preview_url`, `updated_at` or the other internal fields of the media record.
- `Screenshot::$imageUrl` is null when the screenshot has no image. The server sends `null`, not an empty string.
- The `ServerException` docs name `POST /v1/snaps/batches`. A failed `batches()->create()` gets a 500 with the code `internal_error`, the same as `snaps()->create()`.
