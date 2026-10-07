<?php

declare(strict_types=1);

namespace SSpkS;

/**
 * Immutable configuration.
 *
 * Every setting comes from an environment variable. For installs without
 * Docker, the same variables can be put into config.local.php in the project
 * root (a PHP file returning ['SSPKS_SITE_NAME' => '...', ...]). Real
 * environment variables always win over that file.
 */
final class Config
{
    /**
     * @param array<string, string> $packageDefaults Fallback maintainer/distributor/support values.
     * @param list<string> $githubRepos "owner/repo" entries whose release downloads are shown.
     */
    public function __construct(
        public readonly string $siteName = 'Synology packages',
        public readonly string $siteLang = '',
        public readonly string $redirectIndex = '',
        public readonly string $packagesDir = '/packages',
        public readonly string $cacheDir = '/cache',
        public readonly string $fileMask = '*.spk',
        public readonly string $baseUrl = '',
        public readonly array $packageDefaults = [],
        public readonly string $commit = '',
        public readonly string $branch = '',
        public readonly bool $statsEnabled = true,
        public readonly array $githubRepos = [],
        public readonly string $githubToken = '',
    ) {
    }

    public static function fromEnvironment(?string $localFile = null): self
    {
        $local = [];
        if ($localFile !== null && is_file($localFile)) {
            $loaded = require $localFile;
            $local = is_array($loaded) ? $loaded : [];
        }

        $get = static function (string $name, string $default = '') use ($local): string {
            $value = getenv($name);
            if (is_string($value) && $value !== '') {
                return $value;
            }
            if (isset($local[$name]) && is_scalar($local[$name]) && (string) $local[$name] !== '') {
                return (string) $local[$name];
            }
            return $default;
        };

        return new self(
            siteName: $get('SSPKS_SITE_NAME', 'Synology packages'),
            siteLang: strtolower($get('SSPKS_SITE_LANG')),
            redirectIndex: $get('SSPKS_SITE_REDIRECTINDEX'),
            packagesDir: rtrim($get('SSPKS_PACKAGES_DIR', '/packages'), '/'),
            cacheDir: rtrim($get('SSPKS_CACHE_DIR', '/cache'), '/'),
            fileMask: $get('SSPKS_PACKAGES_FILE_MASK', '*.spk'),
            baseUrl: $get('SSPKS_BASE_URL'),
            packageDefaults: [
                'maintainer' => $get('SSPKS_PACKAGES_MAINTAINER'),
                'maintainer_url' => $get('SSPKS_PACKAGES_MAINTAINER_URL'),
                'distributor' => $get('SSPKS_PACKAGES_DISTRIBUTOR'),
                'distributor_url' => $get('SSPKS_PACKAGES_DISTRIBUTOR_URL'),
                'support_url' => $get('SSPKS_PACKAGES_SUPPORT_URL'),
            ],
            commit: $get('SSPKS_COMMIT'),
            branch: $get('SSPKS_BRANCH'),
            statsEnabled: !in_array(strtolower($get('SSPKS_STATS', 'on')), ['off', 'no', 'false', '0'], true),
            githubRepos: self::parseRepos($get('SSPKS_GITHUB_REPOS')),
            githubToken: $get('SSPKS_GITHUB_TOKEN'),
        );
    }

    /**
     * "owner/repo owner2/repo2" (spaces or commas) → validated list.
     *
     * @return list<string>
     */
    public static function parseRepos(string $value): array
    {
        $repos = [];
        foreach (preg_split('/[\s,]+/', trim($value)) ?: [] as $repo) {
            if (preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo) === 1) {
                $repos[] = $repo;
            }
        }
        return array_values(array_unique($repos));
    }
}
