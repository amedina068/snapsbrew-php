<?php

declare(strict_types=1);

namespace Snapsbrew\Resources;

use Snapsbrew\Data\Media as MediaItem;
use Snapsbrew\Data\Page;
use Snapsbrew\Http\Multipart;
use Snapsbrew\Http\Transport;

/**
 * The image library of the account. Use the URLs as image sources in snaps.
 */
final readonly class Media
{
    public function __construct(private Transport $transport) {}

    /**
     * One page of images, 12 for each page, newest first.
     *
     * @return Page<MediaItem>
     */
    public function list(int $page = 1): Page
    {
        $body = $this->transport->get('v1/media', ['page' => $page])->json();

        return Page::fromBody($body, MediaItem::fromArray(...));
    }

    /**
     * Upload an image from a file path, a file object or an open stream.
     *
     * The instance accepts jpg, jpeg, png, gif, webp and bmp files up to
     * 10 MB. It refuses SVG files. The SDK does not check the file, so a
     * refused file gives a `ValidationException`.
     *
     * @param  string|resource|\SplFileInfo  $file
     */
    public function upload(mixed $file, ?string $filename = null): MediaItem
    {
        $file = Multipart::read($file);
        $response = $this->transport->upload('v1/media', Multipart::file('file', $file['contents'], $filename ?? $file['filename']));

        return MediaItem::fromArray($response->resource());
    }
}
