<?php

declare(strict_types=1);

namespace SSpkS\Tests;

use SSpkS\Spk\SpkArchive;

final class SpkArchiveTest extends TestCase
{
    private static function wantInfo(): \Closure
    {
        return static fn (string $name): bool => $name === 'INFO';
    }

    public function testReadsOnlyWantedEntriesAndListsAll(): void
    {
        $archive = SpkArchive::read(self::fixture('alpha_x64-1.0.spk'), self::wantInfo());

        $this->assertSame(['INFO'], array_keys($archive['files']));
        $this->assertStringContainsString('package="alpha"', $archive['files']['INFO']);
        $this->assertContains('package.tgz', $archive['names']);
        $this->assertContains('WIZARD_UIFILES', $archive['names']);
        $this->assertContains('WIZARD_UIFILES/install_uifile', $archive['names']);
    }

    public function testReadsGzippedArchiveWithDotSlashNames(): void
    {
        $archive = SpkArchive::read(self::fixture('beta-tool.spk'), self::wantInfo());
        $this->assertArrayHasKey('INFO', $archive['files']);
    }

    public function testFollowsGnuLongNames(): void
    {
        $archive = SpkArchive::read(self::fixture('longname.spk'), self::wantInfo());

        $this->assertArrayHasKey('INFO', $archive['files']);
        $this->assertContains('docs/' . str_repeat('x', 120) . '.txt', $archive['names']);
    }

    public function testRespectsSizeLimit(): void
    {
        $archive = SpkArchive::read(self::fixture('alpha_x64-1.0.spk'), static fn (): bool => true, 100);
        $this->assertArrayNotHasKey('package.tgz', $archive['files']);
    }

    public function testRejectsNonTarFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not a tar archive');
        SpkArchive::read(self::fixture('not-a-tar.spk'), self::wantInfo());
    }

    public function testDetectsTruncatedArchive(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('truncated');
        SpkArchive::read(self::fixture('truncated.spk'), self::wantInfo());
    }
}
