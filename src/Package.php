<?php

declare(strict_types=1);

namespace SSpkS;

use SSpkS\Spk\InfoParser;

/**
 * One .spk file in the packages folder, built from its cache record.
 */
final class Package
{
    /**
     * @param array<string, string> $info Parsed INFO (without embedded icons).
     * @param list<string> $arch Lower-cased arch tokens from INFO.
     * @param array<int|string, bool> $icons Which icon sizes (72, 120) exist in the cache.
     */
    public function __construct(
        public readonly string $file,
        public readonly string $cacheKey,
        public readonly int $size,
        public readonly int $mtime,
        public readonly string $md5,
        public readonly array $info,
        public readonly array $arch,
        public readonly bool $hasWizard,
        public readonly array $icons,
        public readonly int $screenshots,
    ) {
    }

    /**
     * @param array<string, mixed> $record A record written by PackageRepository.
     */
    public static function fromRecord(array $record): self
    {
        /** @var array<string, string> $info */
        $info = array_map('strval', (array) ($record['info'] ?? []));
        $arch = preg_split('/\s+/', strtolower(trim($info['arch'] ?? 'noarch'))) ?: [];
        $arch = array_values(array_filter($arch, static fn (string $a): bool => $a !== ''));

        return new self(
            file: (string) $record['file'],
            cacheKey: (string) $record['key'],
            size: (int) $record['size'],
            mtime: (int) $record['mtime'],
            md5: (string) ($record['md5'] ?? ''),
            info: $info,
            arch: $arch === [] ? ['noarch'] : $arch,
            hasWizard: (bool) ($record['wizard'] ?? false),
            icons: array_map('boolval', (array) ($record['icons'] ?? [])),
            screenshots: (int) ($record['screenshots'] ?? 0),
        );
    }

    public function name(): string
    {
        return $this->info['package'] ?? '';
    }

    public function version(): string
    {
        return $this->info['version'] ?? '';
    }

    /**
     * Minimum DSM version (os_min_ver on DSM 7 packages, firmware on older ones).
     */
    public function minDsm(): string
    {
        foreach (['os_min_ver', 'firmware'] as $key) {
            if (($this->info[$key] ?? '') !== '') {
                return $this->info[$key];
            }
        }
        return '0';
    }

    public function isForDsm7(): bool
    {
        return version_compare($this->minDsm(), '7', '>=');
    }

    public function isBeta(): bool
    {
        return InfoParser::toBool($this->info['beta'] ?? null) ?? false;
    }

    /**
     * A boolean INFO flag, or $default when the package does not set it.
     */
    public function flag(string $key, bool $default): bool
    {
        return InfoParser::toBool($this->info[$key] ?? null) ?? $default;
    }

    /**
     * Localized INFO text: "<field>_<lang>" (Synology 3-letter code, e.g. rus),
     * then "<field>_enu", then "<field>".
     */
    public function text(string $field, string $lang = 'enu'): string
    {
        foreach (["{$field}_{$lang}", "{$field}_enu", $field] as $key) {
            if (($this->info[$key] ?? '') !== '') {
                return $this->info[$key];
            }
        }
        return '';
    }

    public function displayName(string $lang = 'enu'): string
    {
        $name = $this->text('displayname', $lang);
        return $name !== '' ? $name : $this->name();
    }

    public function hasIcon(int $size): bool
    {
        return $this->icons[$size] ?? false;
    }

    /**
     * Position of the best-matching token in $tokens (lower = more specific),
     * or null if the package does not run on any of them.
     *
     * @param list<string> $tokens From Architectures::compatibleWith().
     */
    public function archMatch(array $tokens): ?int
    {
        $best = null;
        foreach ($this->arch as $arch) {
            $position = array_search($arch, $tokens, true);
            if ($position !== false && ($best === null || $position < $best)) {
                $best = $position;
            }
        }
        return $best;
    }
}
