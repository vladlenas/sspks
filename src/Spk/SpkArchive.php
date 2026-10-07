<?php

declare(strict_types=1);

namespace SSpkS\Spk;

/**
 * Reads entries straight out of an .spk (a tar archive, optionally gzipped).
 *
 * Only the entries the caller asks for are loaded into memory; everything
 * else (the big package.tgz) is skipped with fseek. Nothing is extracted to
 * disk, so concurrent requests can't step on each other.
 */
final class SpkArchive
{
    private const BLOCK = 512;

    /**
     * @param callable(string): bool $wanted Called with each entry name; return true to load its content.
     * @param int $maxEntrySize Entries larger than this are never loaded.
     * @return array{files: array<string, string>, names: list<string>}
     * @throws \RuntimeException if the file is not a readable tar archive.
     */
    public static function read(string $path, callable $wanted, int $maxEntrySize = 8_388_608): array
    {
        $probe = @fopen($path, 'rb');
        if ($probe === false) {
            throw new \RuntimeException('cannot open file');
        }
        $magic = (string) fread($probe, 2);
        fclose($probe);

        $gzipped = $magic === "\x1f\x8b";
        $fh = @fopen($gzipped ? 'compress.zlib://' . $path : $path, 'rb');
        if ($fh === false) {
            throw new \RuntimeException('cannot open file');
        }
        $fileSize = $gzipped ? null : (int) filesize($path);

        try {
            return self::readEntries($fh, $wanted, $maxEntrySize, $fileSize);
        } finally {
            fclose($fh);
        }
    }

    /**
     * @param resource $fh
     * @param callable(string): bool $wanted
     * @return array{files: array<string, string>, names: list<string>}
     */
    private static function readEntries($fh, callable $wanted, int $maxEntrySize, ?int $fileSize): array
    {
        $files = [];
        $names = [];
        $longName = null;
        $first = true;

        while (true) {
            $header = self::readBlock($fh, self::BLOCK, true);
            if ($header === null || trim($header, "\0") === '') {
                // End of file or the zero block that terminates a tar archive.
                break;
            }
            self::verifyChecksum($header, $first);
            $first = false;

            $name = rtrim(substr($header, 0, 100), "\0");
            $size = self::parseSize(substr($header, 124, 12));
            $type = $header[156];
            // POSIX ustar ("ustar\0") stores long paths in a prefix field.
            // Old GNU headers ("ustar  ") use those bytes for other data.
            if (substr($header, 257, 6) === "ustar\0") {
                $prefix = rtrim(substr($header, 345, 155), "\0");
                if ($prefix !== '') {
                    $name = $prefix . '/' . $name;
                }
            }
            $padded = (int) (ceil($size / self::BLOCK) * self::BLOCK);

            if ($type === 'L') {
                // GNU long name: the data block holds the name of the next entry.
                $longName = rtrim(substr((string) self::readBlock($fh, $padded, false), 0, $size), "\0");
                continue;
            }
            if ($type === 'x' || $type === 'g') {
                // PAX extended header; only the path record matters here.
                $pax = self::parsePax(substr((string) self::readBlock($fh, $padded, false), 0, $size));
                if ($type === 'x' && isset($pax['path'])) {
                    $longName = $pax['path'];
                }
                continue;
            }
            if ($longName !== null) {
                $name = $longName;
                $longName = null;
            }

            $name = self::normalizeName($name);
            if ($name !== '') {
                $names[] = $name;
            }

            $isRegularFile = $type === '0' || $type === "\0" || $type === '7';
            if ($isRegularFile && $name !== '' && $size <= $maxEntrySize && $wanted($name)) {
                $files[$name] = substr((string) self::readBlock($fh, $padded, false), 0, $size);
            } else {
                self::skip($fh, $padded, $fileSize);
            }
        }

        return ['files' => $files, 'names' => $names];
    }

    /**
     * @param resource $fh
     * @return string|null Null only when $allowEof is true and the stream is already at its end.
     */
    private static function readBlock($fh, int $length, bool $allowEof): ?string
    {
        if ($length === 0) {
            return '';
        }
        $buffer = '';
        while (strlen($buffer) < $length) {
            $chunk = fread($fh, $length - strlen($buffer));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $buffer .= $chunk;
        }
        if ($buffer === '' && $allowEof) {
            return null;
        }
        if (strlen($buffer) < $length) {
            throw new \RuntimeException('archive is truncated');
        }
        return $buffer;
    }

    /**
     * @param resource $fh
     */
    private static function skip($fh, int $length, ?int $fileSize): void
    {
        if ($length === 0) {
            return;
        }
        if ($fileSize !== null) {
            // Plain tar: seek, then make sure we did not run past the end.
            if (fseek($fh, $length, SEEK_CUR) !== 0 || ftell($fh) > $fileSize) {
                throw new \RuntimeException('archive is truncated');
            }
            return;
        }
        // Gzipped tar: the stream can only be read forward.
        $remaining = $length;
        while ($remaining > 0) {
            $chunk = fread($fh, min($remaining, 65536));
            if ($chunk === false || $chunk === '') {
                throw new \RuntimeException('archive is truncated');
            }
            $remaining -= strlen($chunk);
        }
    }

    private static function verifyChecksum(string $header, bool $first): void
    {
        $stored = trim(substr($header, 148, 8), " \0");
        $bytes = unpack('C*', substr($header, 0, 148) . '        ' . substr($header, 156));
        $calculated = array_sum($bytes === false ? [] : $bytes);
        if (!preg_match('/^[0-7]+$/', $stored) || (int) octdec($stored) !== $calculated) {
            throw new \RuntimeException($first ? 'not a tar archive' : 'corrupted tar header');
        }
    }

    private static function parseSize(string $field): int
    {
        if ($field !== '' && (ord($field[0]) & 0x80) !== 0) {
            // GNU base-256 encoding, used for entries over 8 GiB.
            $size = ord($field[0]) & 0x7f;
            for ($i = 1, $n = strlen($field); $i < $n; $i++) {
                $size = ($size << 8) | ord($field[$i]);
            }
            return $size;
        }
        $field = trim($field, " \0");
        if ($field === '') {
            return 0;
        }
        if (!preg_match('/^[0-7]+$/', $field)) {
            throw new \RuntimeException('corrupted tar header');
        }
        return (int) octdec($field);
    }

    /**
     * @return array<string, string>
     */
    private static function parsePax(string $data): array
    {
        $records = [];
        $offset = 0;
        $total = strlen($data);
        while ($offset < $total) {
            $space = strpos($data, ' ', $offset);
            if ($space === false) {
                break;
            }
            $length = (int) substr($data, $offset, $space - $offset);
            if ($length <= 0) {
                break;
            }
            $record = substr($data, $space + 1, $length - ($space - $offset) - 2);
            $eq = strpos($record, '=');
            if ($eq !== false) {
                $records[substr($record, 0, $eq)] = substr($record, $eq + 1);
            }
            $offset += $length;
        }
        return $records;
    }

    private static function normalizeName(string $name): string
    {
        $name = ltrim($name, '/');
        while (str_starts_with($name, './')) {
            $name = substr($name, 2);
        }
        $name = rtrim($name, '/');
        return $name === '.' ? '' : $name;
    }
}
