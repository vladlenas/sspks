<?php

declare(strict_types=1);

namespace SSpkS\Spk;

/**
 * Parses a Synology INFO file (shell-style key="value" lines).
 *
 * parse_ini_string() is not used on purpose: it rewrites yes/no/true into
 * "1"/"" and chokes on characters like ! { } " that are common in
 * descriptions and changelogs.
 */
final class InfoParser
{
    /**
     * @return array<string, string>
     */
    public static function parse(string $content): array
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }
        $result = [];
        foreach (preg_split('/\r\n|\r|\n/', $content) ?: [] as $line) {
            if (preg_match('/^\s*([A-Za-z0-9_.\-]+)\s*=\s*(.*?)\s*$/', $line, $m) === 1) {
                $result[$m[1]] = self::unquote($m[2]);
            }
        }
        return $result;
    }

    /**
     * Interprets yes/true/1/on as true. Returns null when the value is missing.
     */
    public static function toBool(?string $value): ?bool
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        return in_array(strtolower(trim($value)), ['yes', 'true', '1', 'on'], true);
    }

    private static function unquote(string $value): string
    {
        $length = strlen($value);
        if ($length >= 2 && $value[0] === '"' && $value[$length - 1] === '"') {
            return strtr(substr($value, 1, -1), ['\\"' => '"', '\\\\' => '\\']);
        }
        if ($length >= 2 && $value[0] === "'" && $value[$length - 1] === "'") {
            return substr($value, 1, -1);
        }
        return $value;
    }
}
