<?php

declare(strict_types=1);

namespace Snapsbrew\Resources;

use InvalidArgumentException;
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
     * Upload an image from a file path or from an open stream.
     *
     * The instance accepts jpg, jpeg, png, gif, webp and bmp files up to
     * 10 MB. It refuses SVG files. The SDK does not check the file, so a
     * refused file gives a `ValidationException`.
     *
     * @param  string|resource  $file
     */
    public function upload(mixed $file, ?string $filename = null): MediaItem
    {
        [$contents, $name] = self::read($file);

        $response = $this->transport->upload('v1/media', Multipart::file('file', $contents, $filename ?? $name));

        return MediaItem::fromArray($response->resource());
    }

    /**
     * @return array{string, string}
     */
    private static function read(mixed $file): array
    {
        if (is_string($file)) {
            if (! is_file($file) || ! is_readable($file)) {
                throw new InvalidArgumentException("The file [{$file}] does not exist or is not readable.");
            }

            return [(string) file_get_contents($file), basename($file)];
        }

        if (is_resource($file)) {
            $path = stream_get_meta_data($file)['uri'] ?? 'upload';
            $contents = stream_get_contents($file);

            if ($contents === false) {
                throw new InvalidArgumentException('The SDK cannot read the given stream.');
            }

            return [$contents, basename((string) $path)];
        }

        throw new InvalidArgumentException('Give a file path or an open stream to upload.');
    }
}
