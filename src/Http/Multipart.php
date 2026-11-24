<?php

declare(strict_types=1);

namespace Snapsbrew\Http;

use InvalidArgumentException;
use SplFileInfo;

/**
 * A multipart/form-data body. A field is text or a file.
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
        return self::fields([$field => ['contents' => $contents, 'filename' => $filename]]);
    }

    /**
     * @param  array<string, string|array{contents: string, filename: string}>  $fields  A string is a text field.
     */
    public static function fields(array $fields): self
    {
        $boundary = 'snapsbrew-'.bin2hex(random_bytes(12));
        $body = '';

        foreach ($fields as $name => $value) {
            $name = self::clean((string) $name);
            $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$name}\"";

            $body .= is_string($value)
                ? "\r\n\r\n{$value}\r\n"
                : '; filename="'.self::clean(basename($value['filename']))."\"\r\n"
                    .'Content-Type: '.self::mimeType($value['contents'])."\r\n\r\n"
                    .$value['contents']."\r\n";
        }

        return new self($body."--{$boundary}--\r\n", $boundary);
    }

    /**
     * Read a file path, an `SplFileInfo` (for example an uploaded file) or an
     * open stream. The SDK reads the whole file before the first attempt, so
     * that a retry sends the same bytes.
     *
     * @return array{contents: string, filename: string}
     */
    public static function read(mixed $file): array
    {
        if ($file instanceof SplFileInfo) {
            // An uploaded file of Laravel or Symfony keeps the name from the browser.
            $original = method_exists($file, 'getClientOriginalName') ? $file->getClientOriginalName() : null;
            $name = is_string($original) && $original !== '' ? $original : $file->getFilename();

            return ['contents' => self::read($file->getPathname())['contents'], 'filename' => $name];
        }

        if (is_string($file)) {
            if (! is_file($file) || ! is_readable($file)) {
                throw new InvalidArgumentException("The file [{$file}] does not exist or is not readable.");
            }

            return ['contents' => (string) file_get_contents($file), 'filename' => basename($file)];
        }

        if (is_resource($file)) {
            $path = stream_get_meta_data($file)['uri'] ?? 'upload';
            $contents = stream_get_contents($file);

            if ($contents === false) {
                throw new InvalidArgumentException('The SDK cannot read the given stream.');
            }

            return ['contents' => $contents, 'filename' => basename((string) $path)];
        }

        throw new InvalidArgumentException('Give a file path, a file object or an open stream.');
    }

    public function contentType(): string
    {
        return "multipart/form-data; boundary={$this->boundary}";
    }

    private static function clean(string $value): string
    {
        return str_replace(['"', "\r", "\n"], '', $value);
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
