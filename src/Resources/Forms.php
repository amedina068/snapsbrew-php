<?php

declare(strict_types=1);

namespace Snapsbrew\Resources;

use InvalidArgumentException;
use Snapsbrew\Data\Payload;
use Snapsbrew\Data\Snap;
use Snapsbrew\Http\Multipart;
use Snapsbrew\Http\Transport;
use SplFileInfo;

/**
 * The embedded forms of the instance.
 *
 * A form renders on the account of its owner, so the SDK sends no token to
 * the submit URL. Copy the submit URL of the form from your instance. The URL
 * carries its own signature.
 */
final readonly class Forms
{
    public function __construct(private Transport $transport) {}

    /**
     * Send the layers of a form. A template form returns one snap. A
     * collection form returns a list of snaps, and its layer keys are
     * `{template uuid}__{layer key}`.
     *
     * A string is text. A resource or an `SplFileInfo`, for example an
     * uploaded file, is an image.
     *
     *     $snap = $snapsbrew->forms()->submit($submitUrl, ['headline' => 'Hello', 'photo' => $request->file('photo')]);
     *
     * @param  array<string, string|resource|SplFileInfo>  $layers
     * @return Snap|list<Snap>
     */
    public function submit(string $url, array $layers): Snap|array
    {
        if (! preg_match('#^https?://#i', $url)) {
            throw new InvalidArgumentException('Give the signed submit URL of the form, for example https://snaps.example.com/api/v1/embedded-forms/...?signature=...');
        }

        $fields = [];

        foreach ($layers as $key => $value) {
            $fields["layers[{$key}]"] = is_string($value) ? $value : Multipart::read($value);
        }

        $response = $this->transport->submit($url, Multipart::fields($fields));
        $body = $response->json();

        if (array_is_list($body)) {
            return array_map(
                static fn (array $snap): Snap => Snap::fromArray($snap, $response->requestUuid()),
                Payload::listOfObjects($body),
            );
        }

        return Snap::fromArray($body, $response->requestUuid());
    }
}
