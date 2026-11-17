<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Snapsbrew\Data\Template;
use Snapsbrew\Variants;

function templatePayload(array $overrides = []): array
{
    return $overrides + [
        'uuid' => '141e8454-8aa1-420a-bd0b-18a85ba949a2',
        'title' => 'Template 1',
        'width' => 1200,
        'height' => 630,
        'layers' => [
            ['layer' => 'review_title', 'available_modifications' => ['text' => 'The worst movie']],
            ['layer' => 'review_poster', 'type' => 'image', 'available_modifications' => ['image_url' => 'https://example.com/a.jpg']],
            ['layer' => 'decoration', 'available_modifications' => []],
        ],
        'created_at' => '2026-05-01T12:00:00Z',
    ];
}

it('lists one page of templates', function () {
    $history = [];
    $client = snapsbrew([json(['data' => [templatePayload()], 'meta' => ['previous_cursor' => null, 'next_cursor' => 'eyJpZCI6MTB9']])], $history);

    $page = $client->templates()->list();

    expect($page)->toHaveCount(1)
        ->and($page->hasMore())->toBeTrue()
        ->and($page->nextCursor)->toBe('eyJpZCI6MTB9')
        ->and($page->first())->toBeInstanceOf(Template::class)
        ->and($page->first()?->layer('review_title')?->modifications)->toBe(['text' => 'The worst movie'])
        ->and($page->first()?->layer('review_poster')?->type)->toBe('image')
        ->and($page->first()?->layer('decoration')?->modifications)->toBe([])
        ->and((string) $history[0]['request']->getUri())->toBe('https://snapsbrew.test/api/v1/templates');
});

it('sends the cursor of the next page', function () {
    $history = [];
    snapsbrew([json(['data' => [], 'meta' => ['previous_cursor' => 'a', 'next_cursor' => null]])], $history)
        ->templates()->list('eyJpZCI6MTB9');

    expect($history[0]['request']->getUri()->getQuery())->toBe('cursor=eyJpZCI6MTB9');
});

it('reads every page with all()', function () {
    $history = [];
    $client = snapsbrew([
        json(['data' => [templatePayload(['uuid' => 'a'])], 'meta' => ['previous_cursor' => null, 'next_cursor' => 'next']]),
        json(['data' => [templatePayload(['uuid' => 'b'])], 'meta' => ['previous_cursor' => 'prev', 'next_cursor' => null]]),
    ], $history);

    $uuids = array_map(fn (Template $template) => $template->uuid, iterator_to_array($client->templates()->all(), false));

    expect($uuids)->toBe(['a', 'b'])
        ->and($history)->toHaveCount(2)
        ->and($history[1]['request']->getUri()->getQuery())->toBe('cursor=next');
});

it('reads one template with its publish state', function () {
    $history = [];
    $client = snapsbrew([json(['data' => templatePayload(['size' => 'open_graph', 'published_version' => 2, 'has_unpublished_edits' => true])])], $history);

    $template = $client->templates()->find('141e8454-8aa1-420a-bd0b-18a85ba949a2');

    expect($template->size)->toBe('open_graph')
        ->and($template->publishedVersion)->toBe(2)
        ->and($template->hasUnpublishedEdits)->toBeTrue()
        ->and($template->isPublished())->toBeTrue()
        ->and($template->createdAt?->format('Y-m-d'))->toBe('2026-05-01')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/v1/templates/141e8454-8aa1-420a-bd0b-18a85ba949a2');
});

it('lists the size presets', function () {
    $sizes = snapsbrew([json(['data' => [[
        'size' => 'instagram_story', 'name' => 'Instagram Story (1080x1920)', 'platform' => 'Instagram',
        'width' => 1080, 'height' => 1920, 'aspect_ratio' => '9:16', 'safe_margin_px' => 97,
    ]]])])->templates()->sizes();

    expect($sizes)->toHaveCount(1)
        ->and($sizes[0]->size)->toBe('instagram_story')
        ->and($sizes[0]->aspectRatio)->toBe('9:16')
        ->and($sizes[0]->safeMarginPx)->toBe(97);
});

it('creates a template from a size preset', function () {
    $history = [];
    $template = snapsbrew([json(['data' => ['uuid' => 'new', 'title' => 'Launch card', 'size' => 'open_graph', 'width' => 1200, 'height' => 630, 'created_at' => '2026-05-01T12:00:00Z'], 'message' => 'Template created successfully'])], $history)
        ->templates()->create('Launch card', size: 'open_graph');

    expect($template->uuid)->toBe('new')
        ->and($template->layers)->toBe([])
        ->and($history[0]['request']->getMethod())->toBe('POST')
        ->and(body($history[0]['request']))->toBe(['title' => 'Launch card', 'size' => 'open_graph']);
});

it('updates a template by its numeric ID', function () {
    $history = [];
    snapsbrew([json(['data' => ['uuid' => 'u', 'title' => 'New', 'size' => 'custom', 'width' => 800, 'height' => 600, 'created_at' => null]])], $history)
        ->templates()->update(42, 'New', width: 800, height: 600);

    expect($history[0]['request']->getMethod())->toBe('PATCH')
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/v1/templates/42')
        ->and(body($history[0]['request']))->toBe(['title' => 'New', 'width' => 800, 'height' => 600]);
});

it('deletes a template', function () {
    $history = [];
    snapsbrew([json(['message' => 'Template deleted successfully'])], $history)->templates()->delete('u');

    expect($history[0]['request']->getMethod())->toBe('DELETE');
});

it('publishes a template', function () {
    $history = [];
    $published = snapsbrew([json(['data' => ['id' => 'u', 'uuid' => 'u', 'published_version' => 3], 'message' => 'Template published.'])], $history)
        ->templates()->publish('u');

    expect($published->publishedVersion)->toBe(3)
        ->and($history[0]['request']->getUri()->getPath())->toBe('/api/v1/templates/u/publish');
});

it('previews a template with variants in the query string', function () {
    $history = [];
    $preview = snapsbrew([new Response(200, ['Content-Type' => 'text/html'], '<html>preview</html>')], $history)
        ->templates()->preview('u', Variants::make()->text('headline', 'Hello')->hide('badge'));

    parse_str($history[0]['request']->getUri()->getQuery(), $query);

    expect($preview->html)->toBe('<html>preview</html>')
        ->and((string) $preview)->toBe('<html>preview</html>')
        ->and($query['variants'])->toBe([['layer' => 'headline', 'text' => 'Hello'], ['layer' => 'badge', 'hidden' => '1']]);
});
