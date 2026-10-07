<?php

declare(strict_types=1);

namespace SSpkS\Tests;

use SSpkS\Stats\DownloadStats;

final class DownloadStatsTest extends TestCase
{
    private const NOW = 1_760_000_000; // 2025-10-09 08:53 UTC

    private string $log;
    private string $state;

    protected function setUp(): void
    {
        parent::setUp();
        mkdir($this->cacheDir . '/stats');
        $this->log = $this->cacheDir . '/stats/downloads.log';
        $this->state = $this->cacheDir . '/stats/downloads.json';
    }

    private static function line(int $time, string $status, string $method, string $file, string $range = '-', string $referer = '-'): string
    {
        return implode("\t", [$time, $status, $method, '/packages/' . $file, $range, $referer]) . "\n";
    }

    private function append(string ...$lines): void
    {
        file_put_contents($this->log, implode('', $lines), FILE_APPEND);
    }

    /** @return array<string, mixed> */
    private function update(?callable $packageOf = null, int $now = self::NOW): array
    {
        $packageOf ??= static fn (string $file): ?string => str_starts_with($file, 'alpha') ? 'alpha' : null;
        return (new DownloadStats($this->log, $this->state))->update($packageOf, $now);
    }

    public function testCountsOnlyRealDownloads(): void
    {
        $t = self::NOW - 60;
        $this->append(
            self::line($t, '200', 'GET', 'alpha_x64-1.1.spk'),
            self::line($t, '200', 'GET', 'alpha_x64-1.1.spk', '-', 'https://nas.example/'),
            self::line($t, '206', 'GET', 'alpha_x64-1.1.spk', 'bytes=0-'),
            self::line($t, '206', 'GET', 'alpha_x64-1.1.spk', 'bytes=1000-'),
            self::line($t, '200', 'HEAD', 'alpha_x64-1.1.spk'),
            self::line($t, '304', 'GET', 'alpha_x64-1.1.spk'),
            self::line($t, '404', 'GET', 'missing.spk'),
            self::line($t, '200', 'GET', 'long%20name.spk'),
            "garbage line\n",
        );

        $state = $this->update();
        $alpha = $state['files']['alpha_x64-1.1.spk'];
        $this->assertSame(['alpha', 3, 1, 2], [$alpha['package'], $alpha['total'], $alpha['web'], $alpha['direct']]);
        $this->assertSame('long name', $state['files']['long name.spk']['package']);
        $this->assertArrayNotHasKey('missing.spk', $state['files']);

        $summary = DownloadStats::summarize($state, self::NOW);
        $this->assertSame(4, $summary['total']);
        $this->assertSame(4, $summary['recent']);
        $this->assertSame(1, $summary['web']);
        $this->assertSame(3, $summary['direct']);
        $this->assertSame($t, $summary['since']);
        $this->assertCount(30, $summary['daily']);
        $this->assertSame(['date' => '2025-10-09', 'count' => 4], $summary['daily'][29]);
        $this->assertSame(['alpha', 'long name'], array_column($summary['packages'], 'name'));
        $this->assertSame(['alpha' => ['total' => 3, 'recent' => 3], 'long name' => ['total' => 1, 'recent' => 1]], DownloadStats::perPackage($summary));
    }

    public function testReadsOnlyNewCompleteLines(): void
    {
        $t = self::NOW - 60;
        $this->append(self::line($t, '200', 'GET', 'alpha_x64-1.1.spk'));
        $this->assertSame(1, $this->update()['files']['alpha_x64-1.1.spk']['total']);

        // A line Apache is still writing is left for the next run.
        $partial = self::line($t, '200', 'GET', 'alpha_x64-1.1.spk');
        $this->append(substr($partial, 0, 10));
        $this->assertSame(1, $this->update()['files']['alpha_x64-1.1.spk']['total']);

        $this->append(substr($partial, 10));
        $state = $this->update();
        $this->assertSame(2, $state['files']['alpha_x64-1.1.spk']['total']);
        $this->assertSame(filesize($this->log), $state['offset']);

        // Nothing new: the same numbers, read back from the state file.
        $this->assertSame(2, $this->update()['files']['alpha_x64-1.1.spk']['total']);
    }

    public function testStartsOverWhenLogIsTruncated(): void
    {
        $t = self::NOW - 60;
        $this->append(self::line($t, '200', 'GET', 'alpha_x64-1.1.spk'), self::line($t, '200', 'GET', 'alpha_x64-1.1.spk'));
        $this->update();

        file_put_contents($this->log, self::line($t, '200', 'GET', 'beta-tool.spk'));
        $state = $this->update();
        $this->assertSame(['beta-tool.spk'], array_keys($state['files']));
    }

    public function testKeepsNameOfDeletedPackagesAndForgetsOldDays(): void
    {
        $old = self::NOW - 500 * 86400;
        $this->append(self::line($old, '200', 'GET', 'alpha_x64-1.0.spk'));
        $this->update();

        // The file is gone now, so the lookup returns null; the name stays.
        $this->append(self::line(self::NOW - 60, '200', 'GET', 'alpha_x64-1.0.spk'));
        $state = $this->update(static fn (): ?string => null);
        $entry = $state['files']['alpha_x64-1.0.spk'];

        $this->assertSame('alpha', $entry['package']);
        $this->assertSame(2, $entry['total']);
        $this->assertSame(['2025-10-09'], array_keys($entry['days']), 'days older than 400 days are dropped');
        $this->assertSame(1, DownloadStats::summarize($state, self::NOW)['recent']);
    }

    public function testNoLogMeansNoDownloads(): void
    {
        $summary = DownloadStats::summarize($this->update(), self::NOW);
        $this->assertSame(0, $summary['total']);
        $this->assertNull($summary['since']);
        $this->assertSame([], $summary['packages']);
    }
}
