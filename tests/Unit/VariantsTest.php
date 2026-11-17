<?php

declare(strict_types=1);

use Snapsbrew\Variants;

it('builds one override for each call', function () {
    $variants = Variants::make()
        ->text('title', 'Hello')
        ->image('cover', 'https://example.com/a.jpg')
        ->background('bg', '#ffffff')
        ->qr('qr', 'https://example.com')
        ->barcode('code', '012345678905')
        ->hide('badge');

    expect($variants->toArray())->toBe([
        ['layer' => 'title', 'text' => 'Hello'],
        ['layer' => 'cover', 'image_url' => 'https://example.com/a.jpg'],
        ['layer' => 'bg', 'background_color' => '#ffffff'],
        ['layer' => 'qr', 'qr_content' => 'https://example.com'],
        ['layer' => 'code', 'barcode_content' => '012345678905'],
        ['layer' => 'badge', 'hidden' => true],
    ])->and($variants)->toHaveCount(6)
        ->and(json_encode($variants))->toBe(json_encode($variants->toArray()));
});

it('reads raw variants', function () {
    $variants = Variants::from([['layer' => 'title', 'text' => 'Hi', 'hidden' => false]]);

    expect($variants->toArray())->toBe([['layer' => 'title', 'text' => 'Hi', 'hidden' => false]]);
});

it('returns the same list from from()', function () {
    $variants = Variants::make();

    expect(Variants::from($variants))->toBe($variants);
});

it('merges two lists', function () {
    $variants = Variants::make()->text('a', '1')->merge([['layer' => 'b', 'text' => '2']]);

    expect($variants->toArray())->toHaveCount(2);
});

it('needs a layer name', function () {
    Variants::from([['text' => 'no layer']]);
})->throws(InvalidArgumentException::class, 'layer');

it('refuses a nested value', function () {
    Variants::from([['layer' => 'a', 'text' => ['nested']]]);
})->throws(InvalidArgumentException::class);
