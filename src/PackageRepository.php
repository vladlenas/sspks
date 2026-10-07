<?php

declare(strict_types=1);

namespace SSpkS;

use SSpkS\Spk\InfoParser;
use SSpkS\Spk\SpkArchive;

/**
 * Finds .spk files and keeps one cache record per file.
 *
 * A record is rebuilt only when the file's size or modification time
 * changes, so replacing a package under the same name is picked up right
 * away, and an unchanged folder costs one stat() per file per request.
 *
 * Cache layout (all in the cache folder):
 *   <key>.json          metadata, md5, list of extracted images
 *   <key>.icon72.png    PACKAGE_ICON.PNG
 *   <key>.icon120.png   PACKAGE_ICON_256.PNG
 *   <key>.screenN.png   screen_N.png (optional SSpkS extension)
 */
final class PackageRepository
{
    private const SCHEMA = 2;
    private const ICON_SOURCES = [
        72 => ['file' => 'PACKAGE_ICON.PNG', 'info' => 'package_icon'],
        120 => ['file' => 'PACKAGE_ICON_256.PNG', 'info' => 'package_icon_256'],
    ];

    /** @var list<Package>|null */
    private ?array $packages = null;
    /** @var array<string, string> file name => reason */
    private array $broken = [];
    private bool $cacheWarningLogged = false;

    public function __construct(private readonly Config $config)
    {
    }

    /**
     * All readable packages, sorted by name, newest version first.
     *
     * @return list<Package>
     */
    public function all(): array
    {
        if ($this->packages !== null) {
            return $this->packages;
        }

        $dir = $this->config->packagesDir;
        if (!is_dir($dir)) {
            throw new \RuntimeException("Packages folder {$dir} does not exist");
        }

        $packages = [];
        $seenKeys = [];
        $changed = false;
        foreach (glob($dir . '/' . $this->config->fileMask, GLOB_NOSORT) ?: [] as $path) {
            if (!str_ends_with(strtolower($path), '.spk') || !is_file($path)) {
                continue;
            }
            $record = $this->record($path, $changed);
            if ($record === null) {
                continue;
            }
            $seenKeys[$record['key']] = true;
            if ($record['ok']) {
                $packages[] = Package::fromRecord($record);
            } else {
                $this->broken[$record['file']] = (string) $record['error'];
            }
        }

        // Something was rebuilt, or a package was deleted (more records than files).
        if ($changed || count(glob($this->cachePath('*.json')) ?: []) > count($seenKeys)) {
            $this->pruneCache($seenKeys);
        }

        usort($packages, static function (Package $a, Package $b): int {
            return strnatcasecmp($a->name(), $b->name())
                ?: version_compare($b->version(), $a->version())
                ?: strcmp($a->file, $b->file);
        });

        return $this->packages = $packages;
    }

    /**
     * Files that could not be read, with the reason.
     *
     * @return array<string, string>
     */
    public function broken(): array
    {
        $this->all();
        return $this->broken;
    }

    public function find(string $file): ?Package
    {
        foreach ($this->all() as $package) {
            if ($package->file === $file) {
                return $package;
            }
        }
        return null;
    }

    public function iconPath(Package $package, int $size): ?string
    {
        $path = $this->cachePath($package->cacheKey . ".icon{$size}.png");
        return $package->hasIcon($size) && is_file($path) ? $path : null;
    }

    public function screenshotPath(Package $package, int $number): ?string
    {
        if ($number < 1 || $number > $package->screenshots) {
            return null;
        }
        $path = $this->cachePath($package->cacheKey . ".screen{$number}.png");
        return is_file($path) ? $path : null;
    }

    public static function cacheKey(string $file): string
    {
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename($file, '.spk')) ?? 'package';
        return substr($base, 0, 80) . '-' . substr(sha1($file), 0, 10);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function record(string $path, bool &$changed): ?array
    {
        $stat = @stat($path);
        if ($stat === false) {
            return null; // removed while we were looking
        }
        $file = basename($path);
        $key = self::cacheKey($file);
        $jsonPath = $this->cachePath($key . '.json');

        $cached = $this->readJson($jsonPath);
        if ($cached !== null
            && ($cached['schema'] ?? null) === self::SCHEMA
            && ($cached['file'] ?? null) === $file
            && ($cached['size'] ?? null) === $stat['size']
            && ($cached['mtime'] ?? null) === $stat['mtime']
        ) {
            return $cached;
        }

        $changed = true;
        $record = $this->build($path, $file, $key, (int) $stat['size'], (int) $stat['mtime']);
        $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json !== false) {
            $this->writeAtomic($jsonPath, $json);
        }
        return $record;
    }

    /**
     * @return array<string, mixed>
     */
    private function build(string $path, string $file, string $key, int $size, int $mtime): array
    {
        $record = [
            'schema' => self::SCHEMA,
            'file' => $file,
            'key' => $key,
            'size' => $size,
            'mtime' => $mtime,
            'ok' => false,
            'error' => null,
        ];

        try {
            $archive = SpkArchive::read($path, static function (string $name): bool {
                return $name === 'INFO'
                    || $name === 'PACKAGE_ICON.PNG'
                    || $name === 'PACKAGE_ICON_256.PNG'
                    || preg_match('/^screen_\d+\.png$/', $name) === 1;
            });
            $files = $archive['files'];
            if (!isset($files['INFO'])) {
                throw new \RuntimeException('INFO file not found');
            }
            $info = InfoParser::parse($files['INFO']);
            if (($info['package'] ?? '') === '' || ($info['version'] ?? '') === '') {
                throw new \RuntimeException('INFO has no package name or version');
            }

            $icons = [];
            foreach (self::ICON_SOURCES as $iconSize => $source) {
                $png = $files[$source['file']] ?? self::decodeBase64($info[$source['info']] ?? '');
                $target = $this->cachePath("{$key}.icon{$iconSize}.png");
                $icons[$iconSize] = $png !== '' && $this->writeAtomic($target, $png);
                if (!$icons[$iconSize] && is_file($target)) {
                    @unlink($target);
                }
            }
            // Embedded icons are large base64 blobs; they are in the PNGs now.
            unset($info['package_icon'], $info['package_icon_256']);

            $screens = [];
            foreach ($files as $name => $content) {
                if (preg_match('/^screen_(\d+)\.png$/', $name, $m) === 1) {
                    $screens[(int) $m[1]] = $content;
                }
            }
            ksort($screens);
            $count = 0;
            foreach ($screens as $content) {
                if ($this->writeAtomic($this->cachePath($key . '.screen' . ($count + 1) . '.png'), $content)) {
                    $count++;
                }
            }
            foreach (glob($this->cachePath($key . '.screen*.png')) ?: [] as $stale) {
                if (preg_match('/\.screen(\d+)\.png$/', $stale, $m) === 1 && (int) $m[1] > $count) {
                    @unlink($stale);
                }
            }

            $hasWizard = false;
            foreach ($archive['names'] as $name) {
                if ($name === 'WIZARD_UIFILES' || str_starts_with($name, 'WIZARD_UIFILES/')) {
                    $hasWizard = true;
                    break;
                }
            }

            $record['ok'] = true;
            $record['md5'] = (string) md5_file($path);
            $record['info'] = $info;
            $record['wizard'] = $hasWizard;
            $record['icons'] = $icons;
            $record['screenshots'] = $count;
        } catch (\Throwable $e) {
            $record['error'] = $e->getMessage();
            error_log("sspks: skipping {$file}: {$e->getMessage()}");
        }

        return $record;
    }

    /**
     * Removes cache entries of packages that are gone, plus files left by
     * SSpkS 1.x (*.nfo, *.wiz, *_thumb_*.png, ...).
     *
     * @param array<string, true> $keep Cache keys still in use.
     */
    private function pruneCache(array $keep): void
    {
        foreach (@scandir($this->config->cacheDir) ?: [] as $entry) {
            $path = $this->cachePath($entry);
            if (!is_file($path)) {
                continue;
            }
            if (str_starts_with($entry, '.tmp-')) {
                if (filemtime($path) < time() - 3600) {
                    @unlink($path); // left behind by an interrupted write
                }
                continue;
            }
            if (preg_match('/^(.+-[0-9a-f]{10})\.(json|icon\d+\.png|screen\d+\.png)$/', $entry, $m) === 1) {
                if (!isset($keep[$m[1]])) {
                    @unlink($path);
                }
                continue;
            }
            if (preg_match('/(\.nfo|\.wiz|\.nowiz|_thumb_\d+\.png|_screen_\d+\.png)$/', $entry) === 1) {
                @unlink($path);
            }
        }
    }

    private function cachePath(string $name): string
    {
        return $this->config->cacheDir . '/' . $name;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readJson(string $path): ?array
    {
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    private function writeAtomic(string $target, string $data): bool
    {
        $dir = dirname($target);
        $tmp = is_dir($dir) && is_writable($dir) ? @tempnam($dir, '.tmp-') : false;
        if ($tmp === false || realpath(dirname($tmp)) !== realpath($dir)) {
            if ($tmp !== false) {
                @unlink($tmp);
            }
            if (!$this->cacheWarningLogged) {
                error_log("sspks: cache folder {$dir} is not writable, every request will re-read all packages");
                $this->cacheWarningLogged = true;
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

    private static function decodeBase64(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $decoded = base64_decode($value, true);
        return $decoded === false ? '' : $decoded;
    }
}
