<?php

declare(strict_types=1);

namespace SSpkS\Stats;

use SSpkS\AtomicFile;

/**
 * Download counters of GitHub release assets.
 *
 * GitHub keeps a download_count per release file. These are fetched at most
 * once per $ttl seconds per repository and cached, so the page stays fast
 * and the unauthenticated API limit (60 requests/hour) is never an issue.
 * If GitHub can't be reached, the last good numbers are shown with the error.
 */
final class GitHubStats
{
    private const SCHEMA = 1;
    private const MAX_PAGES = 10;

    /** @var callable(string, list<string>): array{status: int, body: string} */
    private $http;

    /**
     * @param list<string> $repos "owner/repo"
     * @param (callable(string, list<string>): array{status: int, body: string})|null $http For tests.
     */
    public function __construct(
        private readonly array $repos,
        private readonly string $token,
        private readonly string $cacheFile,
        ?callable $http = null,
        private readonly int $ttl = 3600,
        private readonly int $retryAfterError = 600,
    ) {
        $this->http = $http ?? self::defaultHttp(...);
    }

    /**
     * @return list<array{repo: string, url: string, total: int, checked: int, fetched: int, error: ?string,
     *     releases: list<array{tag: string, name: string, url: string, published: ?int, prerelease: bool, total: int,
     *         assets: list<array{name: string, count: int}>}>}>
     */
    public function get(?int $now = null): array
    {
        $now ??= time();
        if ($this->repos === []) {
            return [];
        }

        $cache = AtomicFile::readJson($this->cacheFile);
        $repos = ($cache !== null && ($cache['schema'] ?? null) === self::SCHEMA && is_array($cache['repos'] ?? null))
            ? $cache['repos']
            : [];

        $stale = array_filter($this->repos, fn (string $repo): bool => $this->isStale($repos[$repo] ?? null, $now));
        if ($stale !== [] && ($lock = $this->lock()) !== null) {
            try {
                foreach ($stale as $repo) {
                    $repos[$repo] = $this->refresh($repo, $repos[$repo] ?? null, $now);
                }
                AtomicFile::writeJson($this->cacheFile, ['schema' => self::SCHEMA, 'repos' => $repos]);
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }

        $result = [];
        foreach ($this->repos as $repo) {
            if (isset($repos[$repo])) {
                $result[] = $repos[$repo];
            }
        }
        return $result;
    }

    /**
     * @param array<string, mixed>|null $entry
     */
    private function isStale(?array $entry, int $now): bool
    {
        if ($entry === null) {
            return true;
        }
        $wait = ($entry['error'] ?? null) !== null ? $this->retryAfterError : $this->ttl;
        return $now - (int) ($entry['checked'] ?? 0) >= $wait;
    }

    /**
     * @param array<string, mixed>|null $previous
     * @return array<string, mixed>
     */
    private function refresh(string $repo, ?array $previous, int $now): array
    {
        try {
            $releases = $this->fetchReleases($repo);
            return [
                'repo' => $repo,
                'url' => "https://github.com/{$repo}",
                'total' => array_sum(array_column($releases, 'total')),
                'releases' => $releases,
                'checked' => $now,
                'fetched' => $now,
                'error' => null,
            ];
        } catch (\RuntimeException $e) {
            error_log("sspks: GitHub statistics for {$repo}: {$e->getMessage()}");
            return [
                'repo' => $repo,
                'url' => "https://github.com/{$repo}",
                'total' => (int) ($previous['total'] ?? 0),
                'releases' => $previous['releases'] ?? [],
                'checked' => $now,
                'fetched' => (int) ($previous['fetched'] ?? 0),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchReleases(string $repo): array
    {
        $headers = [
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
            'User-Agent: SSpkS',
        ];
        if ($this->token !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        $releases = [];
        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $url = "https://api.github.com/repos/{$repo}/releases?per_page=100&page={$page}";
            $response = ($this->http)($url, $headers);
            if ($response['status'] !== 200) {
                throw new \RuntimeException(self::describeError($response['status'], $response['body']));
            }
            $list = json_decode($response['body'], true);
            if (!is_array($list)) {
                throw new \RuntimeException('unexpected answer from GitHub');
            }
            foreach ($list as $release) {
                if (!is_array($release) || !empty($release['draft'])) {
                    continue;
                }
                $assets = [];
                foreach ((array) ($release['assets'] ?? []) as $asset) {
                    if (is_array($asset) && isset($asset['name'])) {
                        $assets[] = ['name' => (string) $asset['name'], 'count' => (int) ($asset['download_count'] ?? 0)];
                    }
                }
                $published = strtotime((string) ($release['published_at'] ?? ''));
                $releases[] = [
                    'tag' => (string) ($release['tag_name'] ?? ''),
                    'name' => (string) ($release['name'] ?? ''),
                    'url' => (string) ($release['html_url'] ?? ''),
                    'published' => $published === false ? null : $published,
                    'prerelease' => !empty($release['prerelease']),
                    'total' => array_sum(array_column($assets, 'count')),
                    'assets' => $assets,
                ];
            }
            if (count($list) < 100) {
                break;
            }
        }
        return $releases;
    }

    private static function describeError(int $status, string $body): string
    {
        if ($status === 0) {
            return 'no connection to api.github.com';
        }
        $message = json_decode($body, true)['message'] ?? '';
        $hint = match ($status) {
            401 => 'the token is invalid',
            403, 429 => 'API rate limit reached, set SSPKS_GITHUB_TOKEN',
            404 => 'repository not found or private',
            default => '',
        };
        return trim("HTTP {$status}" . ($hint !== '' ? ": {$hint}" : '') . (is_string($message) && $message !== '' ? " ({$message})" : ''));
    }

    /**
     * Only one request at a time talks to GitHub; others show the cached data.
     *
     * @return resource|null
     */
    private function lock()
    {
        $dir = dirname($this->cacheFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $fh = @fopen($this->cacheFile . '.lock', 'c');
        if ($fh === false) {
            return null;
        }
        if (!flock($fh, LOCK_EX | LOCK_NB)) {
            fclose($fh);
            return null;
        }
        return $fh;
    }

    /**
     * @param list<string> $headers
     * @return array{status: int, body: string}
     */
    private static function defaultHttp(string $url, array $headers): array
    {
        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
            'timeout' => 8,
            'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($url, false, $context);
        $responseHeaders = function_exists('http_get_last_response_headers')
            ? (http_get_last_response_headers() ?? [])
            : ($http_response_header ?? []);
        $status = 0;
        foreach ($responseHeaders as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
                $status = (int) $m[1]; // the last status line wins (after redirects)
            }
        }
        return ['status' => $status, 'body' => $body === false ? '' : $body];
    }
}
