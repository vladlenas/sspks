<?php

declare(strict_types=1);

namespace SSpkS;

use SSpkS\Stats\DownloadStats;
use SSpkS\Stats\GitHubStats;

/**
 * Routes a request:
 *   DSM Package Center (unique=synology_*)  → catalog JSON
 *   ?icon=<file>&s=72|120                   → package icon
 *   ?screen=<file>&n=1                      → screenshot
 *   ?stats                                  → download statistics page
 *   ?health                                 → "ok" (container health check)
 *   anything else                           → web page
 */
final class App
{
    public const VERSION = '2.1.0';

    private ?PackageRepository $repository = null;

    /**
     * @param array<string, mixed> $server $_SERVER
     */
    public function __construct(
        private readonly Config $config,
        private readonly array $server,
        private readonly string $publicDir,
    ) {
    }

    /**
     * @param array<string, mixed> $query $_GET
     * @param array<string, mixed> $params $_GET + $_POST (DSM may use either)
     */
    public function handle(array $query, array $params): void
    {
        $param = static fn (array $from, string $key): string => is_string($from[$key] ?? null) ? trim($from[$key]) : '';

        if (array_key_exists('health', $query)) {
            $this->healthCheck();
            return;
        }
        if (str_starts_with($param($params, 'unique'), 'synology')) {
            $this->catalog($param($params, 'arch'), $params, $param);
            return;
        }
        if ($param($query, 'icon') !== '') {
            $size = (int) $param($query, 's') === 72 ? 72 : 120;
            $this->image($param($query, 'icon'), 'icon', $size);
            return;
        }
        if ($param($query, 'screen') !== '') {
            $this->image($param($query, 'screen'), 'screen', max(1, (int) $param($query, 'n')));
            return;
        }

        $method = (string) ($this->server['REQUEST_METHOD'] ?? 'GET');
        if ($method !== 'GET' && $method !== 'HEAD') {
            http_response_code(405);
            header('Allow: GET, HEAD');
            return;
        }
        if (array_key_exists('stats', $query)) {
            $this->statsPage();
            return;
        }
        if ($this->config->redirectIndex !== '') {
            header('Location: ' . $this->config->redirectIndex, true, 302);
            return;
        }
        $this->page();
    }

    private function repository(): PackageRepository
    {
        return $this->repository ??= new PackageRepository($this->config);
    }

    private function urls(): Urls
    {
        return Urls::detect($this->config, $this->server);
    }

    /**
     * @param array<string, mixed> $params
     * @param callable(array<string, mixed>, string): string $param
     */
    private function catalog(string $arch, array $params, callable $param): void
    {
        $major = $param($params, 'major');
        $parts = array_filter(
            [$major, $param($params, 'minor'), $param($params, 'build')],
            static fn (string $part): bool => $part !== ''
        );
        $firmware = $parts === [] ? '0' : implode('.', $parts);
        $dsm7 = (int) $major >= 7;

        $packages = PackageFilter::forDevice(
            $this->repository()->all(),
            $arch,
            $firmware,
            $param($params, 'package_update_channel'),
        );

        $keyFile = $this->config->packagesDir . '/gpgkey.asc';
        $keyring = is_file($keyFile) ? (string) file_get_contents($keyFile) : null;

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $downloads = $this->config->statsEnabled ? DownloadStats::perPackage($this->downloadSummary()) : [];
        echo CatalogJson::render($packages, $this->urls(), $this->config, $param($params, 'language'), $dsm7, $keyring, $downloads);
    }

    private function image(string $file, string $kind, int $number): void
    {
        $package = $this->repository()->find($file);
        $path = null;
        if ($package !== null) {
            $path = $kind === 'icon'
                ? $this->repository()->iconPath($package, $number)
                : $this->repository()->screenshotPath($package, $number);
        }
        if ($path === null && $kind === 'icon') {
            $path = $this->publicDir . "/assets/package-{$number}.png";
        }
        if ($path === null || !is_file($path)) {
            http_response_code(404);
            return;
        }

        $etag = '"' . substr(sha1($path . '|' . filemtime($path) . '|' . filesize($path)), 0, 16) . '"';
        header('Content-Type: image/png');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: public, max-age=604800');
        header('ETag: ' . $etag);
        if (trim((string) ($this->server['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
            http_response_code(304);
            return;
        }
        header('Content-Length: ' . filesize($path));
        readfile($path);
    }

    private function page(): void
    {
        $lang = WebPage::language($this->config, (string) ($this->server['HTTP_ACCEPT_LANGUAGE'] ?? ''));
        $downloads = $this->config->statsEnabled ? DownloadStats::perPackage($this->downloadSummary()) : null;
        $html = (new WebPage($this->config, $this->repository(), $this->urls()))->render($lang, $downloads);
        $this->sendHtml($html);
    }

    private function statsPage(): void
    {
        if (!$this->config->statsEnabled) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo "Statistics are turned off (SSPKS_STATS).\n";
            return;
        }
        $lang = WebPage::language($this->config, (string) ($this->server['HTTP_ACCEPT_LANGUAGE'] ?? ''));
        $github = (new GitHubStats(
            $this->config->githubRepos,
            $this->config->githubToken,
            $this->config->cacheDir . '/stats/github.json',
        ))->get();
        $this->sendHtml(StatsPage::render(
            $this->config,
            $this->urls(),
            $this->repository(),
            $lang,
            $this->downloadSummary(),
            $github,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function downloadSummary(): array
    {
        $names = [];
        foreach ($this->repository()->all() as $package) {
            $names[$package->file] = $package->name();
        }
        $state = (new DownloadStats(
            $this->config->cacheDir . '/stats/downloads.log',
            $this->config->cacheDir . '/stats/downloads.json',
        ))->update(static fn (string $file): ?string => $names[$file] ?? null);
        return DownloadStats::summarize($state, time());
    }

    private function sendHtml(string $html): void
    {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-cache');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; base-uri 'none'; frame-ancestors 'self'");
        echo $html;
    }

    private function healthCheck(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        if (!is_dir($this->config->packagesDir) || !is_readable($this->config->packagesDir)) {
            http_response_code(503);
            echo "packages folder {$this->config->packagesDir} is missing or not readable\n";
            return;
        }
        echo "ok\n";
    }
}
