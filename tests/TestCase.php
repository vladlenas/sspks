<?php

declare(strict_types=1);

namespace SSpkS\Tests;

use SSpkS\Config;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected string $tmp;
    protected string $packagesDir;
    protected string $cacheDir;
    protected string $errorLog;
    private string|false $previousErrorLog = false;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/sspks-test-' . bin2hex(random_bytes(6));
        $this->packagesDir = $this->tmp . '/packages';
        $this->cacheDir = $this->tmp . '/cache';
        mkdir($this->packagesDir, 0777, true);
        mkdir($this->cacheDir, 0777, true);
        // Keep error_log() output of the code under test out of the PHPUnit report.
        $this->errorLog = $this->tmp . '/php-errors.log';
        $this->previousErrorLog = ini_set('error_log', $this->errorLog);
        foreach (glob(self::fixture('*.spk')) ?: [] as $file) {
            // Fixed mtime: git checkouts do not preserve it, URLs in tests depend on it.
            copy($file, $this->packagesDir . '/' . basename($file));
            touch($this->packagesDir . '/' . basename($file), 1_700_000_000);
        }
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->previousErrorLog === false ? '' : $this->previousErrorLog);
        self::removeTree($this->tmp);
    }

    protected function config(array $defaults = []): Config
    {
        return new Config(
            siteName: 'Test <site>',
            packagesDir: $this->packagesDir,
            cacheDir: $this->cacheDir,
            packageDefaults: $defaults + [
                'maintainer' => 'Default Maintainer',
                'maintainer_url' => '',
                'distributor' => '',
                'distributor_url' => '',
                'support_url' => '',
            ],
        );
    }

    protected static function fixture(string $name): string
    {
        return __DIR__ . '/fixtures/spk/' . $name;
    }

    private static function removeTree(string $path): void
    {
        if (is_dir($path) && !is_link($path)) {
            foreach (scandir($path) ?: [] as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    self::removeTree($path . '/' . $entry);
                }
            }
            rmdir($path);
        } elseif (file_exists($path) || is_link($path)) {
            unlink($path);
        }
    }
}
