<?php

declare(strict_types=1);

namespace SSpkS;

/**
 * Builds the absolute URLs that go into the catalog and the web page.
 */
final class Urls
{
    private function __construct(private readonly string $base)
    {
    }

    /**
     * Uses SSPKS_BASE_URL when set, otherwise works the URL out from the
     * request, honouring X-Forwarded-Proto/Host/Prefix from a reverse proxy.
     *
     * @param array<string, mixed> $server $_SERVER
     */
    public static function detect(Config $config, array $server): self
    {
        if ($config->baseUrl !== '') {
            return new self(rtrim($config->baseUrl, '/') . '/');
        }

        $header = static function (string $key) use ($server): string {
            $value = $server[$key] ?? '';
            return is_string($value) ? trim(explode(',', $value)[0]) : '';
        };

        $proto = strtolower($header('HTTP_X_FORWARDED_PROTO'));
        $https = $proto === 'https'
            || ($proto === '' && strtolower((string) ($server['HTTPS'] ?? '')) !== '' && strtolower((string) $server['HTTPS']) !== 'off');

        $host = $header('HTTP_X_FORWARDED_HOST') ?: $header('HTTP_HOST');
        if (!preg_match('/^([A-Za-z0-9.-]+|\[[0-9A-Fa-f:.]+\])(:\d{1,5})?$/', $host)) {
            $host = (string) ($server['SERVER_NAME'] ?? 'localhost');
        }

        $prefix = rtrim($header('HTTP_X_FORWARDED_PREFIX'), '/');
        if ($prefix !== '' && !preg_match('#^/[A-Za-z0-9._~/-]*$#', $prefix)) {
            $prefix = '';
        }

        $dir = str_replace('\\', '/', dirname((string) ($server['SCRIPT_NAME'] ?? '/index.php')));
        $dir = rtrim($dir, '/') . '/';

        return new self(($https ? 'https' : 'http') . '://' . $host . $prefix . $dir);
    }

    public static function fixed(string $base): self
    {
        return new self(rtrim($base, '/') . '/');
    }

    public function base(): string
    {
        return $this->base;
    }

    public function download(Package $package): string
    {
        return $this->base . 'packages/' . rawurlencode($package->file);
    }

    public function icon(Package $package, int $size): string
    {
        return $this->base . '?' . http_build_query(['icon' => $package->file, 's' => $size, 'v' => $package->mtime], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return list<string>
     */
    public function screenshots(Package $package): array
    {
        $urls = [];
        for ($n = 1; $n <= $package->screenshots; $n++) {
            $urls[] = $this->base . '?' . http_build_query(['screen' => $package->file, 'n' => $n, 'v' => $package->mtime], '', '&', PHP_QUERY_RFC3986);
        }
        return $urls;
    }
}
