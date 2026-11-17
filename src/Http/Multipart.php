<?php

declare(strict_types=1);

namespace Snapsbrew\Http;

/**
 * A multipart/form-data body with one file field.
 *
 * @internal
 */
final readonly class Multipart
{
    public function __construct(
        public string $body,
        public string $boundary,
    ) {}

    public static function file(string $field, string $contents, string $filename): self
    {
        $boundary = 'snapsbrew-'.bin2hex(random_bytes(12));
        $filename = str_replace(['"', "\r", "\n"], '', basename($filename));

        $body = "--{$boundary}\r\n"
            ."Content-Disposition: form-data; name=\"{$field}\"; filename=\"{$filename}\"\r\n"
            .'Content-Type: '.self::mimeType($contents)."\r\n\r\n"
            .$contents."\r\n"
            ."--{$boundary}--\r\n";

        return new self($body, $boundary);
    }

    public function contentType(): string
    {
        return "multipart/form-data; boundary={$this->boundary}";
    }

    private static function mimeType(string $contents): string
    {
        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;

        if ($finfo !== false) {
            $type = finfo_buffer($finfo, $contents);

            if (is_string($type) && $type !== '') {
                return $type;
            }
        }

        return 'application/octet-stream';
    }
}
