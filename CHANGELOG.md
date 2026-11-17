# Changelog

All notable changes to this project are in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). The project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
