<?php

declare(strict_types=1);

namespace SSpkS\Tests;

use SSpkS\Package;
use SSpkS\PackageRepository;

final class PackageRepositoryTest extends TestCase
{
    public function testFindsReadablePackagesSortedByNameAndVersion(): void
    {
        $repo = new PackageRepository($this->config());

        $files = array_map(static fn (Package $p): string => $p->file, $repo->all());
        $this->assertSame([
            'alpha_armv8-1.1.spk',
            'alpha_x64-1.1.spk',
            'alpha_x64-1.0.spk',
            'beta-tool.spk',
            'legacy_dsm6.spk',
            'longname.spk',
        ], $files);

        $broken = $repo->broken();
        ksort($broken);
        $this->assertSame(['no-info.spk', 'not-a-tar.spk', 'truncated.spk'], array_keys($broken));
        $this->assertStringContainsString('INFO', $broken['no-info.spk']);
    }

    public function testExtractsMetadataIconsAndScreenshots(): void
    {
        $repo = new PackageRepository($this->config());
        $alpha = $repo->find('alpha_x64-1.0.spk');

        $this->assertNotNull($alpha);
        $this->assertSame('alpha', $alpha->name());
        $this->assertSame('1.0.0-1', $alpha->version());
        $this->assertSame(['x86_64', 'avoton'], $alpha->arch);
        $this->assertSame('Альфа', $alpha->displayName('rus'));
        $this->assertSame('Alpha', $alpha->displayName('ger'));
        $this->assertTrue($alpha->hasWizard);
        $this->assertTrue($alpha->isForDsm7());
        $this->assertFalse($alpha->isBeta());
        $this->assertSame(md5_file(self::fixture('alpha_x64-1.0.spk')), $alpha->md5);

        $icon = $repo->iconPath($alpha, 72);
        $this->assertNotNull($icon);
        $this->assertSame(file_get_contents(__DIR__ . '/fixtures/icon.png'), file_get_contents($icon));
        $this->assertNotNull($repo->iconPath($alpha, 120));

        $this->assertSame(2, $alpha->screenshots);
        $this->assertSame('first', file_get_contents((string) $repo->screenshotPath($alpha, 1)));
        $this->assertSame('second', file_get_contents((string) $repo->screenshotPath($alpha, 2)));
        $this->assertNull($repo->screenshotPath($alpha, 3));
    }

    public function testIconEmbeddedInInfo(): void
    {
        $repo = new PackageRepository($this->config());
        $beta = $repo->find('beta-tool.spk');

        $this->assertNotNull($beta);
        $this->assertTrue($beta->isBeta());
        $this->assertNotNull($repo->iconPath($beta, 72));
        $this->assertNull($repo->iconPath($beta, 120));
        $this->assertArrayNotHasKey('package_icon', $beta->info);
    }

    public function testUsesCacheUntilFileChanges(): void
    {
        (new PackageRepository($this->config()))->all();

        $key = PackageRepository::cacheKey('legacy_dsm6.spk');
        $jsonPath = $this->cacheDir . '/' . $key . '.json';
        $this->assertFileExists($jsonPath);

        // Tamper with the cached record: an unchanged file must be served from cache.
        $record = json_decode((string) file_get_contents($jsonPath), true);
        $record['info']['displayname'] = 'From cache';
        file_put_contents($jsonPath, json_encode($record));
        $this->assertSame('From cache', (new PackageRepository($this->config()))->find('legacy_dsm6.spk')?->displayName());

        // A new modification time means the file was replaced: re-read it.
        touch($this->packagesDir . '/legacy_dsm6.spk', time());
        $this->assertSame('legacy', (new PackageRepository($this->config()))->find('legacy_dsm6.spk')?->displayName());
    }

    public function testPrunesCacheOfDeletedPackagesAndOldFiles(): void
    {
        (new PackageRepository($this->config()))->all();
        $key = PackageRepository::cacheKey('alpha_x64-1.0.spk');
        $this->assertFileExists($this->cacheDir . "/{$key}.icon72.png");

        file_put_contents($this->cacheDir . '/old_package.nfo', 'x');
        file_put_contents($this->cacheDir . '/old_package_thumb_72.png', 'x');
        file_put_contents($this->cacheDir . '/.htaccess', 'keep me');
        unlink($this->packagesDir . '/alpha_x64-1.0.spk');

        (new PackageRepository($this->config()))->all();

        $this->assertFileDoesNotExist($this->cacheDir . "/{$key}.json");
        $this->assertFileDoesNotExist($this->cacheDir . "/{$key}.icon72.png");
        $this->assertFileDoesNotExist($this->cacheDir . "/{$key}.screen1.png");
        $this->assertFileDoesNotExist($this->cacheDir . '/old_package.nfo');
        $this->assertFileDoesNotExist($this->cacheDir . '/old_package_thumb_72.png');
        $this->assertFileExists($this->cacheDir . '/.htaccess');
        $this->assertFileExists($this->cacheDir . '/' . PackageRepository::cacheKey('alpha_x64-1.1.spk') . '.json');
    }

    public function testWorksWithoutWritableCache(): void
    {
        $repo = new PackageRepository(new \SSpkS\Config(
            packagesDir: $this->packagesDir,
            cacheDir: $this->tmp . '/does-not-exist',
        ));
        $this->assertCount(6, $repo->all());
        $this->assertStringContainsString('not writable', (string) file_get_contents($this->errorLog));
    }

    public function testMissingPackagesFolderIsAnError(): void
    {
        $this->expectException(\RuntimeException::class);
        (new PackageRepository(new \SSpkS\Config(packagesDir: $this->tmp . '/nope', cacheDir: $this->cacheDir)))->all();
    }
}
