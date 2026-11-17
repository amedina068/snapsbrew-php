<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Snapsbrew\Data\Media;
use Snapsbrew\Exceptions\UnexpectedResponseException;

describe('screenshots', function () {
    it('lists screenshots, which use the uuid key', function () {
        $page = snapsbrew([json(['data' => [[
            'uuid' => 'a1b2c3', 'url' => 'https://example.com', 'image_url' => 'https://x/a.png',
            'width' => 1330, 'height' => 780, 'created_at' => '2026-05-01T12:00:00Z',
        ]], 'meta' => ['previous_cursor' => null, 'next_cursor' => null]])])->screenshots()->list();

        expect($page->first()?->id)->toBe('a1b2c3')
            ->and($page->first()?->imageUrl)->toBe('https://x/a.png')
            ->and($page->hasMore())->toBeFalse();
    });

    it('reads one screenshot', function () {
        $history = [];
        $screenshot = snapsbrew([json(['data' => [
            'id' => 'a1b2c3', 'url' => 'https://example.com', 'image_url' => 'https://x/a.png',
            'width' => 1330, 'height' => 780, 'created_at' => '2026-05-01T12:00:00Z',
        ]])], $history)->screenshots()->find('a1b2c3');

        expect($screenshot->imageUrl)->toBe('https://x/a.png')
            ->and($history[0]['request']->getUri()->getPath())->toBe('/api/v1/screenshots/a1b2c3');
    });

    it('gives a null image URL when a screenshot has no image', function (string $method) {
        $item = ['url' => 'https://example.com', 'image_url' => null, 'width' => 1330, 'height' => 780, 'created_at' => '2026-05-01T12:00:00Z'];
        $client = snapsbrew([json(['data' => $method === 'list'
            ? [['uuid' => 'a1b2c3'] + $item]
            : ['id' => 'a1b2c3'] + $item,
        ] + ($method === 'list' ? ['meta' => ['previous_cursor' => null, 'next_cursor' => null]] : []))]);

        $screenshot = match ($method) {
            'list' => $client->screenshots()->list()->first(),
            'find' => $client->screenshots()->find('a1b2c3'),
            'create' => $client->screenshots()->create('https://example.com'),
        };

        expect($screenshot?->id)->toBe('a1b2c3')
            ->and($screenshot?->imageUrl)->toBeNull();
    })->with(['list', 'find', 'create']);

    it('captures a page', function () {
        $history = [];
        $screenshot = snapsbrew([json(['data' => [
            'id' => 'n', 'url' => 'https://example.com', 'image_url' => 'https://x/n.png',
            'width' => 800, 'height' => 600, 'created_at' => '2026-05-01T12:00:00Z',
        ]])], $history)->screenshots()->create('https://example.com', 800, 600);

        expect($screenshot->width)->toBe(800)
            ->and(body($history[0]['request']))->toBe(['url' => 'https://example.com', 'width' => 800, 'height' => 600]);
    });
});

describe('media', function () {
    it('lists media from the Laravel paginator body', function () {
        $history = [];
        $page = snapsbrew([json([
            'current_page' => 1,
            'data' => [mediaPayload()],
            'last_page' => 2,
            'per_page' => 12,
            'total' => 13,
        ])], $history)->media()->list();

        expect($page->first())->toBeInstanceOf(Media::class)
            ->and($page->first()?->uuid)->toBe('5b0c7a52-3f9e-4a8d-9c1e-2d7f6b4e8a10')
            ->and($page->first()?->originalUrl)->toBe('https://x/logo.png')
            ->and($page->total)->toBe(13)
            ->and($page->nextPage())->toBe(2)
            ->and($history[0]['request']->getUri()->getQuery())->toBe('page=1');
    });

    it('lists media from a body with a meta key', function () {
        $page = snapsbrew([json([
            'data' => [mediaPayload()],
            'meta' => ['current_page' => 1, 'per_page' => 12, 'total' => 1],
        ])])->media()->list();

        expect($page->hasMore())->toBeFalse()->and($page)->toHaveCount(1);
    });

    it('uploads a file as multipart form data', function () {
        $path = tempnam(sys_get_temp_dir(), 'snap');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

        $history = [];
        $media = snapsbrew([json(mediaPayload(['uuid' => 'm-1']))], $history)->media()->upload($path, 'logo.png');

        unlink($path);

        $request = $history[0]['request'];
        $body = (string) $request->getBody();

        expect($media->id)->toBe(12)
            ->and($media->uuid)->toBe('m-1')
            ->and($media->name)->toBe('logo')
            ->and($media->mimeType)->toBe('image/png')
            ->and($media->size)->toBe(68)
            ->and($media->originalUrl)->toBe('https://x/logo.png')
            ->and($media->createdAt?->format('Y-m-d'))->toBe('2026-05-01')
            ->and($request->getHeaderLine('Content-Type'))->toStartWith('multipart/form-data; boundary=')
            ->and($body)->toContain('name="file"; filename="logo.png"')
            ->and($body)->toContain('Content-Type: image/png');
    });

    it('uploads an open stream', function () {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'GIF89a');
        rewind($stream);

        $history = [];
        snapsbrew([json(mediaPayload(['id' => 1, 'name' => 'a', 'file_name' => 'a.gif', 'mime_type' => 'image/gif', 'src' => 'https://x/a.gif']))], $history)
            ->media()->upload($stream, 'a.gif');

        expect((string) $history[0]['request']->getBody())->toContain('filename="a.gif"');
    });

    it('refuses a missing file', function () {
        snapsbrew()->media()->upload('/does/not/exist.png');
    })->throws(InvalidArgumentException::class);
});

describe('guides', function () {
    it('reads each guide', function (string $method, string $path, array $arguments = []) {
        $history = [];
        $guide = snapsbrew([json(['data' => ['typography' => ['scale' => 'big']]])], $history)
            ->guides()->{$method}(...$arguments);

        expect($guide->get('typography.scale'))->toBe('big')
            ->and($guide['typography'])->toBe(['scale' => 'big'])
            ->and($guide->get('missing.key', 'default'))->toBe('default')
            ->and($history[0]['request']->getUri()->getPath())->toBe('/api/'.$path);
    })->with([
        ['design', 'v1/guides/design'],
        ['layers', 'v1/guides/layers'],
        ['variantProperties', 'v1/guides/variant-properties'],
        ['editorDocument', 'v1/guides/editor-document'],
        ['platforms', 'v1/guides/platforms'],
        ['platform', 'v1/guides/platforms/instagram', ['instagram']],
        ['purposes', 'v1/guides/purposes'],
        ['purpose', 'v1/guides/purposes/link_preview', ['link_preview']],
    ]);

    it('keeps the reviewed month of the platforms guide', function () {
        $guide = snapsbrew([json(['data' => [], 'specs_reviewed' => '2026-09'])])->guides()->platforms();

        expect($guide->specsReviewed)->toBe('2026-09');
    });

    it('is read-only', function () {
        $guide = snapsbrew([json(['data' => []])])->guides()->design();

        $guide['x'] = 1;
    })->throws(LogicException::class);
});

describe('tags', function () {
    it('lists tags with a search term', function () {
        $history = [];
        $tags = snapsbrew([json(['data' => [['id' => 1, 'name' => 'Launch', 'slug' => 'launch']]])], $history)
            ->tags()->list('lau');

        expect($tags[0]->slug)->toBe('launch')
            ->and($history[0]['request']->getUri()->getQuery())->toBe('search=lau');
    });

    it('creates a tag', function () {
        $tag = snapsbrew([json(['data' => ['id' => 2, 'name' => 'Sale', 'slug' => 'sale']], 201)])->tags()->create('Sale');

        expect($tag->id)->toBe(2);
    });

    it('syncs the tags of a template', function () {
        $history = [];
        $tags = snapsbrew([json(['message' => 'Tags updated successfully.', 'tags' => [['id' => 1, 'name' => 'Launch', 'slug' => 'launch']]])], $history)
            ->tags()->sync('tpl', [1]);

        expect($tags)->toHaveCount(1)
            ->and(body($history[0]['request']))->toBe(['tag_ids' => [1]])
            ->and($history[0]['request']->getUri()->getPath())->toBe('/api/v1/templates/tpl/tags');
    });
});

it('throws a clear error for a body with a wrong shape', function () {
    snapsbrew([json(['data' => ['email' => 'a@b.c']])])->account();
})->throws(UnexpectedResponseException::class, '[name]');

it('throws a clear error for a body that is not JSON', function () {
    snapsbrew([new Response(200, [], 'not json')])->templates()->list();
})->throws(UnexpectedResponseException::class);
