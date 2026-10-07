<?php

declare(strict_types=1);

namespace SSpkS\Stats;

use SSpkS\AtomicFile;

/**
 * Counts .spk downloads served by Apache.
 *
 * Apache appends one tab-separated line per request for /packages/*.spk to
 * the download log (see docker/apache.conf):
 *
 *   <unix time> <status> <method> <path> <Range header> <Referer header>
 *
 * Each call reads only the lines added since the previous call and folds them
 * into a small JSON state file, so the log itself is read once. No IP
 * addresses or user agents are logged.
 *
 * What counts as a download: GET with status 200, or 206 for a range that
 * starts at byte 0 (the first request of a resumable download). Requests
 * with a Referer came from a web page (usually this one); requests without
 * one come from Package Center, scripts or direct links.
 */
final class DownloadStats
{
    private const SCHEMA = 1;
    private const KEEP_DAYS = 400;

    public function __construct(
        private readonly string $logFile,
        private readonly string $stateFile,
    ) {
    }

    /**
     * Reads new log lines and returns the updated state.
     *
     * @param callable(string): ?string $packageOf Package name for a file name, null if unknown.
     * @return array<string, mixed>
     */
    public function update(callable $packageOf, ?int $now = null): array
    {
        $state = $this->load();
        clearstatcache(true, $this->logFile);
        $size = @filesize($this->logFile);
        if ($size === false) {
            return $state;
        }
        if ($size < $state['offset']) {
            // The log was truncated or replaced: start over.
            $state = self::emptyState();
        }
        if ($size === $state['offset']) {
            return $state;
        }

        $fh = @fopen($this->logFile, 'rb');
        if ($fh === false) {
            return $state;
        }
        try {
            fseek($fh, $state['offset']);
            $chunk = stream_get_contents($fh, $size - $state['offset']);
        } finally {
            fclose($fh);
        }
        if (!is_string($chunk)) {
            return $state;
        }
        $end = strrpos($chunk, "\n");
        if ($end === false) {
            return $state; // only a partly written line so far
        }

        $complete = substr($chunk, 0, $end + 1);
        foreach (explode("\n", rtrim($complete, "\n")) as $line) {
            self::addLine($state, $line, $packageOf);
        }
        $state['offset'] += strlen($complete);
        self::forgetOldDays($state, $now ?? time());
        AtomicFile::writeJson($this->stateFile, $state);

        return $state;
    }

    /**
     * Numbers for the statistics page and the catalog.
     *
     * @param array<string, mixed> $state
     * @return array{
     *     total: int, recent: int, web: int, direct: int, since: ?int, days: int,
     *     daily: list<array{date: string, count: int}>,
     *     packages: list<array{name: string, total: int, recent: int, web: int, direct: int, last: int,
     *         files: list<array{file: string, total: int, recent: int, last: int}>}>
     * }
     */
    public static function summarize(array $state, int $now, int $days = 30): array
    {
        $dates = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $dates[date('Y-m-d', $now - $i * 86400)] = 0;
        }
        $firstDate = array_key_first($dates);

        $packages = [];
        $since = null;
        foreach ($state['files'] as $file => $f) {
            $recent = 0;
            foreach ($f['days'] as $date => $count) {
                if ($date >= $firstDate && isset($dates[$date])) {
                    $dates[$date] += $count;
                    $recent += $count;
                }
            }
            $name = (string) $f['package'];
            $p = $packages[$name] ?? ['name' => $name, 'total' => 0, 'recent' => 0, 'web' => 0, 'direct' => 0, 'last' => 0, 'files' => []];
            $p['total'] += $f['total'];
            $p['recent'] += $recent;
            $p['web'] += $f['web'];
            $p['direct'] += $f['direct'];
            $p['last'] = max($p['last'], $f['last']);
            $p['files'][] = ['file' => (string) $file, 'total' => $f['total'], 'recent' => $recent, 'last' => $f['last']];
            $packages[$name] = $p;
            $since = $since === null ? $f['first'] : min($since, $f['first']);
        }

        foreach ($packages as &$p) {
            usort($p['files'], static fn (array $a, array $b): int => $b['last'] <=> $a['last']);
        }
        unset($p);
        usort($packages, static fn (array $a, array $b): int => [$b['total'], $a['name']] <=> [$a['total'], $b['name']]);

        $daily = [];
        foreach ($dates as $date => $count) {
            $daily[] = ['date' => $date, 'count' => $count];
        }

        return [
            'total' => array_sum(array_column($packages, 'total')),
            'recent' => array_sum(array_column($packages, 'recent')),
            'web' => array_sum(array_column($packages, 'web')),
            'direct' => array_sum(array_column($packages, 'direct')),
            'since' => $since,
            'days' => $days,
            'daily' => $daily,
            'packages' => array_values($packages),
        ];
    }

    /**
     * Total and recent downloads per package name, for the catalog.
     *
     * @param array<string, mixed> $summary From summarize().
     * @return array<string, array{total: int, recent: int}>
     */
    public static function perPackage(array $summary): array
    {
        $result = [];
        foreach ($summary['packages'] as $p) {
            $result[$p['name']] = ['total' => $p['total'], 'recent' => $p['recent']];
        }
        return $result;
    }

    /**
     * @param array<string, mixed> $state
     * @param callable(string): ?string $packageOf
     */
    private static function addLine(array &$state, string $line, callable $packageOf): void
    {
        $fields = explode("\t", $line);
        if (count($fields) < 6) {
            return;
        }
        [$time, $status, $method, $path, $range, $referer] = $fields;

        if ($method !== 'GET') {
            return;
        }
        if ($status === '206') {
            if (!str_starts_with($range, 'bytes=0-')) {
                return; // continuation of a download already counted
            }
        } elseif ($status !== '200') {
            return;
        }
        $timestamp = (int) $time;
        $file = rawurldecode(basename($path));
        if ($timestamp <= 0 || !str_ends_with(strtolower($file), '.spk')) {
            return;
        }

        $entry = $state['files'][$file] ?? [
            'package' => '',
            'total' => 0,
            'web' => 0,
            'direct' => 0,
            'first' => $timestamp,
            'last' => 0,
            'days' => [],
        ];
        $known = $packageOf($file);
        if ($known !== null && $known !== '') {
            $entry['package'] = $known;
        } elseif ($entry['package'] === '') {
            $entry['package'] = preg_replace('/\.spk$/i', '', $file) ?? $file;
        }

        $entry['total']++;
        $entry[$referer !== '' && $referer !== '-' ? 'web' : 'direct']++;
        $entry['first'] = min($entry['first'], $timestamp);
        $entry['last'] = max($entry['last'], $timestamp);
        $day = date('Y-m-d', $timestamp);
        $entry['days'][$day] = ($entry['days'][$day] ?? 0) + 1;

        $state['files'][$file] = $entry;
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function forgetOldDays(array &$state, int $now): void
    {
        $oldest = date('Y-m-d', $now - self::KEEP_DAYS * 86400);
        foreach ($state['files'] as &$entry) {
            foreach (array_keys($entry['days']) as $day) {
                if ((string) $day < $oldest) {
                    unset($entry['days'][$day]);
                }
            }
        }
        unset($entry);
    }

    /**
     * @return array<string, mixed>
     */
    private function load(): array
    {
        $state = AtomicFile::readJson($this->stateFile);
        if ($state === null || ($state['schema'] ?? null) !== self::SCHEMA || !is_int($state['offset'] ?? null)) {
            return self::emptyState();
        }
        $state['files'] = is_array($state['files'] ?? null) ? $state['files'] : [];
        return $state;
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyState(): array
    {
        return ['schema' => self::SCHEMA, 'offset' => 0, 'files' => []];
    }
}
