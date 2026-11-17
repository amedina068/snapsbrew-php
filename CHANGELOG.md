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
