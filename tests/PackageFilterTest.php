<?php

declare(strict_types=1);

namespace SSpkS\Tests;

use SSpkS\Architectures;
use SSpkS\Package;
use SSpkS\PackageFilter;
use SSpkS\PackageRepository;

final class PackageFilterTest extends TestCase
{
    /** @return list<string> */
    private function filesFor(string $arch, string $firmware, string $channel = 'stable'): array
    {
        $all = (new PackageRepository($this->config()))->all();
        $files = array_map(static fn (Package $p): string => $p->file, PackageFilter::forDevice($all, $arch, $firmware, $channel));
        sort($files);
        return $files;
    }

    public function testDsm7IntelStable(): void
    {
        $this->assertSame(['alpha_x64-1.1.spk', 'longname.spk'], $this->filesFor('avoton', '7.2.64570'));
    }

    public function testBetaChannelAddsBetaPackages(): void
    {
        $this->assertSame(['alpha_x64-1.1.spk', 'beta-tool.spk', 'longname.spk'], $this->filesFor('geminilake', '7.2.64570', 'beta'));
    }

    public function testArmGetsArmBuildThroughAlias(): void
    {
        $this->assertSame(['alpha_armv8-1.1.spk', 'longname.spk'], $this->filesFor('rtd1296', '7.1.42661'));
    }

    public function testDsm6OnlySeesDsm6Packages(): void
    {
        $this->assertSame(['legacy_dsm6.spk'], $this->filesFor('avoton', '6.2.25556'));
    }

    public function testMinimumDsmVersionIsRespected(): void
    {
        $this->assertSame([], $this->filesFor('avoton', '7.0.30000'));
        $this->assertSame([], $this->filesFor('avoton', '6.0.7321'));
    }

    public function testUnknownPlatformStillGetsNoarch(): void
    {
        $this->assertSame(['longname.spk'], $this->filesFor('someNewChip', '7.2.64570'));
    }

    public function testArchitectures(): void
    {
        $this->assertSame(['geminilake', 'x86_64', 'x64', 'noarch'], Architectures::compatibleWith('GeminiLake'));
        $this->assertSame(['armada38x', 'armv7', 'noarch'], Architectures::compatibleWith('armada38x'));
        $this->assertSame('armv8', Architectures::familyOf('rtd1619b'));
        $this->assertArrayHasKey('x86_64', Architectures::grouped());
    }
}
