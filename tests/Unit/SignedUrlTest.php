<?php

declare(strict_types=1);

use Snapsbrew\Exceptions\MissingCredentialsException;
use Snapsbrew\Signing\UrlSigner;
use Snapsbrew\Snapsbrew;
use Snapsbrew\Variants;

/**
 * @return array<string, mixed>
 */
function vectors(): array
{
    return json_decode((string) file_get_contents(__DIR__.'/../Fixtures/signed-url-vectors.json'), true, flags: JSON_THROW_ON_ERROR);
}

dataset('vectors', function () {
    foreach (vectors()['cases'] as $case) {
        yield $case['name'] => [$case];
    }
});

it('builds the canonical string of each vector', function (array $case) {
    expect(UrlSigner::canonicalString($case['params']))->toBe($case['canonical_string']);
})->with('vectors');

it('builds the signature of each vector', function (array $case) {
    $signer = new UrlSigner(vectors()['key']);

    expect($signer->signature($case['params']))->toBe($case['signature']);
})->with('vectors');

it('builds the URL of each vector', function (array $case) {
    $signer = new UrlSigner(vectors()['key']);

    expect($signer->sign(vectors()['base_url'], $case['params']))->toBe($case['url']);
})->with('vectors');

it('encodes the variants of each vector as standard base64 of the JSON', function (array $case) {
    if ($case['variants_json'] === null) {
        expect($case['params'])->not->toHaveKey('variants');

        return;
    }

    $variants = json_decode($case['variants_json'], true, flags: JSON_THROW_ON_ERROR);

    expect(UrlSigner::encodeVariants($variants))->toBe($case['params']['variants']);
})->with('vectors');

it('ignores a signature parameter when it signs', function () {
    $signer = new UrlSigner('key');

    expect($signer->signature(['template_id' => 'a', 'signature' => 'old']))
        ->toBe($signer->signature(['template_id' => 'a']));
});

it('builds the same signature as the vector with the fluent builder', function () {
    $vectors = vectors();
    $case = $vectors['cases'][1];
    $snapsbrew = new Snapsbrew('https://snapsbrew.example', signingKey: $vectors['key']);

    $url = $snapsbrew->signedUrl('044ec825-ee2f-4d06-bb77-98663e27f529')
        ->text('title', 'Dark Avengers')
        ->image('cover', 'https://example.com/a/b.jpg?x=1&y=2');

    expect($url->parameters())->toBe($case['params'])
        ->and($url->url())->toBe($case['url'])
        ->and((string) $url)->toBe($case['url']);
});

it('encodes unicode text and hidden layers like the vector', function () {
    $vectors = vectors();
    $case = $vectors['cases'][2];
    $snapsbrew = new Snapsbrew('https://snapsbrew.example', signingKey: $vectors['key']);

    $parameters = $snapsbrew->signedUrl('044ec825-ee2f-4d06-bb77-98663e27f529')
        ->text('headline', '¿Este sí es tu Hawkeye? 🏹')
        ->hide('badge')
        ->background('bg', '#f0573f')
        ->parameters();

    expect($parameters['variants'])->toBe($case['params']['variants']);
});

it('adds the format and the transparent flag to the signed parameters', function () {
    $snapsbrew = snapsbrew();

    $url = $snapsbrew->signedUrl('tpl')->text('title', 'Hi')->format('webp')->transparent();
    $parameters = $url->parameters();

    expect($parameters)->toMatchArray(['template_id' => 'tpl', 'format' => 'webp', 'transparent' => '1'])
        ->and($url->url())->toContain('signature='.(new UrlSigner('test-signing-key'))->signature($parameters));
});

it('accepts the three formats of the instance', function (string $format) {
    expect(snapsbrew()->signedUrl('tpl')->format($format)->parameters()['format'])->toBe($format);
})->with(['png', 'jpeg', 'webp']);

it('refuses a format that the instance does not render', function (string $format) {
    snapsbrew()->signedUrl('tpl')->format($format);
})->with(['jpg', 'JPEG', 'gif'])->throws(InvalidArgumentException::class);

it('refuses a transparent jpeg', function () {
    snapsbrew()->signedUrl('tpl')->format('jpeg')->transparent()->url();
})->throws(InvalidArgumentException::class);

it('uses the image path of the instance', function () {
    expect(snapsbrew()->signedUrl('tpl')->url())
        ->toStartWith('https://snapsbrew.test/signed-url/image.jpg?template_id=tpl&signature=');
});

it('URL-encodes every value, so base64 plus signs survive the query string', function () {
    $url = snapsbrew()->signedUrl('tpl')->text('title', '>>>?')->url();
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($url)->not->toContain('+')
        ->and(base64_decode($query['variants']))->toBe('[{"layer":"title","text":">>>?"}]');
});

it('accepts a Variants list and raw variants', function () {
    $fromList = snapsbrew()->signedUrl('tpl')->variants(Variants::make()->text('a', 'b'))->url();
    $fromArray = snapsbrew()->signedUrl('tpl')->variants([['layer' => 'a', 'text' => 'b']])->url();

    expect($fromList)->toBe($fromArray);
});

it('needs a signing key', function () {
    snapsbrew(signingKey: null)->signedUrl('tpl');
})->throws(MissingCredentialsException::class, 'SNAPSBREW_SIGNING_KEY');

it('keeps the signing key out of debug output', function () {
    expect(print_r(new UrlSigner('super-secret'), true))->not->toContain('super-secret');
});
