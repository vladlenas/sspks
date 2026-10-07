<?php

declare(strict_types=1);

namespace SSpkS\Tests;

use SSpkS\CatalogJson;
use SSpkS\PackageRepository;
use SSpkS\Urls;

final class CatalogJsonTest extends TestCase
{
    /** @return array<string, mixed> */
    private function render(array $files, string $language, bool $dsm7, ?string $keyring = null): array
    {
        $repo = new PackageRepository($this->config());
        $packages = array_map(static fn (string $f) => $repo->find($f), $files);
        $json = CatalogJson::render($packages, Urls::fixed('https://nas.example/spk'), $this->config(), $language, $dsm7, $keyring);
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    public function testPackageEntry(): void
    {
        $doc = $this->render(['alpha_x64-1.0.spk'], 'rus', true);
        $pkg = $doc['packages'][0];

        $this->assertSame('alpha', $pkg['package']);
        $this->assertSame('1.0.0-1', $pkg['version']);
        $this->assertSame('Альфа', $pkg['dname']);
        $this->assertSame('Пакет альфа', $pkg['desc']);
        $this->assertSame('https://nas.example/spk/packages/alpha_x64-1.0.spk', $pkg['link']);
        $this->assertSame(filesize(self::fixture('alpha_x64-1.0.spk')), $pkg['size']);
        $this->assertSame(md5_file(self::fixture('alpha_x64-1.0.spk')), $pkg['md5']);
        $this->assertSame('https://nas.example/spk/?icon=alpha_x64-1.0.spk&s=72&v=1700000000', $pkg['thumbnail'][0]);
        $this->assertCount(2, $pkg['snapshot']);
        $this->assertSame('<a href="https://alpha.example/notes">Notes</a> & more', $pkg['changelog']);
        $this->assertSame('beta-tool>2.0', $pkg['deppkgs']);
        $this->assertSame('ssh', $pkg['depsers']);
        $this->assertSame('Alpha Team', $pkg['maintainer']);
        $this->assertFalse($pkg['qinst'], 'packages with an install wizard are not quick-installed');
        $this->assertTrue($pkg['silent_install']);
        $this->assertArrayNotHasKey('beta', $pkg, 'DSM 7 hides packages that have a beta key');
        $this->assertArrayNotHasKey('keyrings', $doc);
    }

    public function testDefaultsAndDsm6Fields(): void
    {
        $doc = $this->render(['legacy_dsm6.spk'], 'xx; drop', false, "-----BEGIN PGP-----\nkey\n");
        $pkg = $doc['packages'][0];

        $this->assertSame('legacy', $pkg['dname']);
        $this->assertSame('Default Maintainer', $pkg['maintainer']);
        $this->assertTrue($pkg['qinst']);
        $this->assertNull($pkg['deppkgs']);
        $this->assertFalse($pkg['beta']);
        $this->assertSame(["-----BEGIN PGP-----\nkey"], $doc['keyrings']);
    }

    public function testFileNamesAreUrlEncoded(): void
    {
        rename($this->packagesDir . '/longname.spk', $this->packagesDir . '/long name+1.spk');
        $doc = $this->render(['long name+1.spk'], 'enu', true);
        $this->assertSame('https://nas.example/spk/packages/long%20name%2B1.spk', $doc['packages'][0]['link']);
    }
}
