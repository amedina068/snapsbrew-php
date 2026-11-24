<?php

declare(strict_types=1);

namespace Snapsbrew\Laravel\Testing;

/**
 * The sample responses that the fake sends when no stub matches.
 *
 * Each response has the shape that the instance sends, so the SDK reads it
 * into the same value objects as in production.
 *
 * @internal
 */
final class Fixtures
{
    private const TEMPLATE_ID = '00000000-0000-4000-8000-000000000001';

    private const CREATED_AT = '2026-01-01T00:00:00.000000Z';

    public static function respond(RecordedRequest $request): FakeResponse
    {
        $segments = explode('/', $request->path);

        return match (true) {
            $request->is('GET', 'v1/auth') => self::data(['email' => 'test@example.com', 'name' => 'Test User', 'company' => 'Test Company']),
            $request->is('GET', 'v1/templates') => new FakeResponse(['data' => [self::template(self::TEMPLATE_ID)], 'meta' => ['previous_cursor' => null, 'next_cursor' => null]]),
            $request->is('GET', 'v1/templates/sizes') => self::data([['size' => 'open_graph', 'name' => 'Open Graph / Link Preview (1200x630)', 'platform' => 'Web & email', 'width' => 1200, 'height' => 630, 'aspect_ratio' => '1.9:1', 'safe_margin_px' => 47]]),
            $request->is('GET', 'v1/templates/*/preview'), $request->is('GET', 'v1/snaps/preview') => self::preview(),
            $request->is('GET', 'v1/templates/*/document/preflight') => new FakeResponse(['issues' => [], 'revision' => 1]),
            $request->is('GET', 'v1/templates/*/document') => new FakeResponse(['document' => self::document(), 'revision' => 1]),
            $request->is('PUT', 'v1/templates/*/document') => self::savedDocument($request, is_array($request['document']) ? $request['document'] : self::document()),
            $request->is('PATCH', 'v1/templates/*/document') => self::savedDocument($request, self::document()),
            $request->is('GET', 'v1/templates/*') => self::data(self::template($segments[2] ?? self::TEMPLATE_ID) + ['published_version' => 1, 'has_unpublished_edits' => false]),
            $request->is('POST', 'v1/templates') => self::data(self::summary(self::uuid(), $request), 'Template created successfully'),
            $request->is('PATCH', 'v1/templates/*') => self::data(self::summary($segments[2] ?? self::TEMPLATE_ID, $request), 'Template updated successfully'),
            $request->is('DELETE', 'v1/templates/*') => new FakeResponse(['message' => 'Template deleted successfully']),
            $request->is('POST', 'v1/templates/*/publish') => self::data(['id' => $segments[2] ?? '', 'uuid' => $segments[2] ?? '', 'published_version' => 1], 'Template published.'),
            $request->is('POST', 'v1/templates/*/tags') => new FakeResponse(['message' => 'Tags updated successfully.', 'tags' => self::tags($request)]),
            $request->is('POST', 'v1/snaps') => self::snaps($request),
            $request->is('POST', 'v1/snaps/batches') => new FakeResponse(self::batch(self::uuid(), $request, 'pending'), 202, self::requestHeaders()),
            $request->is('POST', 'v1/snaps/batches/*/cancel') => new FakeResponse(self::batch($segments[3] ?? '', $request, 'canceled', withItems: true)),
            $request->is('GET', 'v1/snaps/batches/*') => new FakeResponse(self::batch($segments[3] ?? '', $request, 'completed', withItems: true)),
            $request->is('GET', 'v1/screenshots') => new FakeResponse(['data' => [self::screenshotListItem(self::uuid(), 'https://example.com')], 'meta' => ['previous_cursor' => null, 'next_cursor' => null]]),
            $request->is('GET', 'v1/screenshots/*') => self::data(self::screenshot($segments[2] ?? self::uuid(), 'https://example.com')),
            $request->is('POST', 'v1/screenshots') => self::data(self::screenshot(self::uuid(), self::string($request['url']) ?? 'https://example.com')),
            $request->is('GET', 'v1/media') => new FakeResponse(['current_page' => 1, 'data' => [], 'last_page' => 1, 'per_page' => 12, 'total' => 0]),
            $request->is('POST', 'v1/media') => new FakeResponse(self::media()),
            $request->is('GET', 'v1/guides/*') => new FakeResponse(['data' => []]),
            $request->is('GET', 'v1/tags') => self::data([]),
            $request->is('POST', 'v1/tags') => new FakeResponse(['data' => ['id' => 1, 'name' => self::string($request['name']) ?? 'Tag', 'slug' => 'tag']], 201),
            default => new FakeResponse(['message' => 'Not found.'], 404),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function document(): array
    {
        return [
            'version' => 2,
            'layers' => [['key' => 'title', 'type' => 'text', 'properties' => [['name' => 'text', 'value' => 'Hello']]]],
            'groups' => [],
        ];
    }

    /**
     * A saved document gets the next revision.
     *
     * @param  array<array-key, mixed>  $document
     */
    private static function savedDocument(RecordedRequest $request, array $document): FakeResponse
    {
        $revision = is_numeric($request['revision']) ? (int) $request['revision'] : 0;

        return new FakeResponse(['document' => $document, 'revision' => $revision + 1]);
    }

    private static function snaps(RecordedRequest $request): FakeResponse
    {
        $async = (bool) $request['async'];
        $status = $async ? 202 : 201;

        if ($request['collection_id'] !== null) {
            return new FakeResponse([self::snap($request, $async)], $status, self::requestHeaders());
        }

        return new FakeResponse(self::snap($request, $async), $status, self::requestHeaders());
    }

    /**
     * @return array<string, mixed>
     */
    private static function snap(RecordedRequest $request, bool $async): array
    {
        $id = self::uuid();
        $format = self::string($request['format']) ?? 'png';

        return [
            'id' => $id,
            'media_url' => $async ? '' : "https://snapsbrew.test/storage/snaps/{$id}.{$format}",
            'width' => 1200,
            'height' => 630,
            'type' => self::string($request['type']) ?? 'image',
            'status' => $async ? 'pending' : 'completed',
            'is_async' => $async,
            'webhook_url' => self::string($request['webhook_url']),
            'completed_at' => $async ? null : self::CREATED_AT,
            'last_error' => null,
            'variants' => is_array($request['variants']) ? $request['variants'] : [],
            'template_id' => self::string($request['template_id']) ?? self::TEMPLATE_ID,
            'template_title' => 'Test Template',
            'created_at' => self::CREATED_AT,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function batch(string $uuid, RecordedRequest $request, string $status, bool $withItems = false): array
    {
        $items = is_array($request['items']) ? array_values($request['items']) : [[]];
        $total = count($items);

        $batch = [
            'uuid' => $uuid,
            'title' => self::string($request['title']) ?? 'API batch',
            'status' => $status,
            'total_rows' => $total,
            'processed_rows' => $status === 'pending' ? 0 : $total,
            'completed' => $status === 'completed' ? $total : 0,
            'pending' => $status === 'pending' ? $total : 0,
            'failed' => 0,
            'canceled' => $status === 'canceled' ? $total : 0,
            'status_url' => "https://snapsbrew.test/api/v1/snaps/batches/{$uuid}",
        ];

        if (! $withItems) {
            return $batch;
        }

        $batch['items'] = array_map(static function (mixed $item, int $index) use ($status): array {
            $id = self::uuid();
            $identifier = is_array($item) ? self::string($item['identifier'] ?? null) : null;

            return [
                'id' => $id,
                'identifier' => $identifier ?? 'item_'.($index + 1),
                'status' => $status,
                'media_url' => $status === 'completed' ? "https://snapsbrew.test/storage/snaps/{$id}.png" : null,
                'last_error' => null,
            ];
        }, $items, array_keys($items));

        return $batch;
    }

    /**
     * @return array<string, mixed>
     */
    private static function template(string $uuid): array
    {
        return [
            'uuid' => $uuid,
            'title' => 'Test Template',
            'size' => 'open_graph',
            'width' => 1200,
            'height' => 630,
            'layers' => [
                ['layer' => 'title', 'available_modifications' => ['text' => 'Hello']],
                ['layer' => 'cover', 'available_modifications' => ['image_url' => 'https://example.com/cover.jpg']],
            ],
            'created_at' => self::CREATED_AT,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function summary(string $uuid, RecordedRequest $request): array
    {
        $width = $request['width'];
        $height = $request['height'];

        return [
            'uuid' => $uuid,
            'title' => self::string($request['title']) ?? 'Test Template',
            'size' => self::string($request['size']) ?? 'custom',
            'width' => is_int($width) ? $width : 1200,
            'height' => is_int($height) ? $height : 630,
            'created_at' => self::CREATED_AT,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function screenshot(string $id, string $url): array
    {
        return [
            'id' => $id,
            'url' => $url,
            'image_url' => "https://snapsbrew.test/storage/screenshots/{$id}.png",
            'width' => 1330,
            'height' => 780,
            'created_at' => self::CREATED_AT,
        ];
    }

    /**
     * A list item names its id `uuid`, not `id`.
     *
     * @return array<string, mixed>
     */
    private static function screenshotListItem(string $uuid, string $url): array
    {
        $screenshot = self::screenshot($uuid, $url);
        unset($screenshot['id']);

        return ['uuid' => $uuid] + $screenshot;
    }

    /**
     * @return array<string, mixed>
     */
    private static function media(): array
    {
        return [
            'id' => 1,
            'uuid' => self::uuid(),
            'name' => 'image',
            'file_name' => 'image.png',
            'mime_type' => 'image/png',
            'size' => 1024,
            'original_url' => 'https://snapsbrew.test/storage/1/image.png',
            'src' => 'https://snapsbrew.test/storage/1/conversions/image-image.jpg',
            'created_at' => self::CREATED_AT,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function tags(RecordedRequest $request): array
    {
        $ids = is_array($request['tag_ids']) ? $request['tag_ids'] : [];

        return array_values(array_map(static fn (mixed $id): array => [
            'id' => is_numeric($id) ? (int) $id : 0,
            'name' => 'Tag '.(is_scalar($id) ? $id : ''),
            'slug' => 'tag-'.(is_scalar($id) ? $id : ''),
        ], $ids));
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function data(array $data, ?string $message = null): FakeResponse
    {
        return new FakeResponse($message === null ? ['data' => $data] : ['data' => $data, 'message' => $message]);
    }

    private static function preview(): FakeResponse
    {
        return new FakeResponse('<!DOCTYPE html><html><body>Snapsbrew preview</body></html>', 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * @return array<string, string>
     */
    private static function requestHeaders(): array
    {
        return ['X-Request-Uuid' => self::uuid()];
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
