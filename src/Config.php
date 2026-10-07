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
        );
    }
}
