<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Snapsbrew\Laravel\Facades\Snapsbrew;
use Snapsbrew\Signing\UrlSigner;

/**
 * @return array<string, string>
 */
function srcQuery(string $html): array
{
    preg_match('/src="([^"]+)"/', $html, $matches);
    parse_str((string) parse_url(html_entity_decode($matches[1]), PHP_URL_QUERY), $query);

    return $query;
}

it('renders an img tag with a signed URL', function () {
    $html = Blade::render(
        '<x-snapsbrew::image :template="$id" :variants="$variants" format="png" alt="Cover" class="rounded" width="600" />',
        ['id' => 'tpl-1', 'variants' => [['layer' => 'title', 'text' => 'Hello & welcome']]],
    );

    $query = srcQuery($html);
    $signature = $query['signature'];
    unset($query['signature']);

    expect($html)->toContain('<img src="https://snapsbrew.test/signed-url/image.jpg?template_id=tpl-1')
        ->toContain('alt="Cover"')
        ->toContain('class="rounded"')
        ->toContain('width="600"')
        ->and($query['format'])->toBe('png')
        ->and(base64_decode($query['variants']))->toBe('[{"layer":"title","text":"Hello & welcome"}]')
        ->and($signature)->toBe((new UrlSigner('test-signing-key'))->signature($query));
});

it('renders with no variants', function () {
    $query = srcQuery(Blade::render('<x-snapsbrew::image template="tpl-2" />'));

    expect($query)->toHaveKeys(['template_id', 'signature'])
        ->and($query)->not->toHaveKey('variants');
});

it('accepts a transparent background', function () {
    $query = srcQuery(Blade::render('<x-snapsbrew::image template="tpl-3" format="webp" :transparent="true" />'));

    expect($query['transparent'])->toBe('1');
});

it('uses the fake when the client is faked', function () {
    Snapsbrew::fake();

    $html = Blade::render('<x-snapsbrew::image template="tpl-4" />');

    expect($html)->toContain('signature=');
    Snapsbrew::assertNothingSent();
});
