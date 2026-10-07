<?php

declare(strict_types=1);

namespace SSpkS;

/**
 * Writes a file so readers see either the old or the new content, never a
 * half-written one: write to a temporary file in the same folder, then rename.
 */
final class AtomicFile
{
    public static function write(string $target, string $data): bool
    {
        $dir = dirname($target);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }
        $tmp = is_writable($dir) ? @tempnam($dir, '.tmp-') : false;
        if ($tmp === false || realpath(dirname($tmp)) !== realpath($dir)) {
            if ($tmp !== false) {
                @unlink($tmp);
            }
            return false;
        }
        if (@file_put_contents($tmp, $data) === false || !@rename($tmp, $target)) {
            @unlink($tmp);
            return false;
        }
        @chmod($target, 0644);
        return true;
    }

    /**
     * @return array<mixed>|null
     */
    public static function readJson(string $path): ?array
    {
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    /**
     * @param array<mixed> $data
     */
    public static function writeJson(string $path, array $data): bool
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        return $json !== false && self::write($path, $json);
    }
}
