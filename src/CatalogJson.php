<?php

declare(strict_types=1);

namespace SSpkS;

/**
 * The JSON document DSM's Package Center expects from a package source.
 */
final class CatalogJson
{
    /**
     * @param list<Package> $packages
     * @param string $language Synology language code sent by DSM (enu, rus, ger, ...).
     * @param string|null $keyring ASCII-armored GPG public key to publish, if any.
     */
    public static function render(
        array $packages,
        Urls $urls,
        Config $config,
        string $language,
        bool $dsm7,
        ?string $keyring = null,
    ): string {
        $language = preg_match('/^[a-z]{3}$/', $language) === 1 ? $language : 'enu';

        $document = ['packages' => []];
        foreach ($packages as $package) {
            $document['packages'][] = self::package($package, $urls, $config, $language, $dsm7);
        }
        if ($keyring !== null && trim($keyring) !== '') {
            $document['keyrings'] = [trim($keyring)];
        }

        return json_encode(
            $document,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function package(Package $p, Urls $urls, Config $config, string $language, bool $dsm7): array
    {
        $info = $p->info;
        $optional = static fn (string $key): ?string => ($info[$key] ?? '') !== '' ? $info[$key] : null;
        $withDefault = static fn (string $key): string => ($info[$key] ?? '') !== ''
            ? $info[$key]
            : (string) ($config->packageDefaults[$key] ?? '');
        $quick = !$p->hasWizard;

        $json = [
            'package' => $p->name(),
            'version' => $p->version(),
            'dname' => $p->displayName($language),
            'desc' => $p->text('description', $language),
            'price' => 0,
            'download_count' => 0,
            'recent_download_count' => 0,
            'link' => $urls->download($p),
            'size' => $p->size,
            'md5' => $p->md5,
            'thumbnail' => [$urls->icon($p, 72), $urls->icon($p, 120)],
            'snapshot' => $urls->screenshots($p),
            'qinst' => $p->flag('qinst', $quick),
            'qstart' => $p->flag('qstart', $quick),
            'qupgrade' => $p->flag('qupgrade', $quick),
            'depsers' => $optional('start_dep_services'),
            'deppkgs' => $optional('install_dep_packages'),
            'conflictpkgs' => $optional('install_conflict_packages'),
            'start' => true,
            'maintainer' => $withDefault('maintainer'),
            'maintainer_url' => $withDefault('maintainer_url'),
            'distributor' => $withDefault('distributor'),
            'distributor_url' => $withDefault('distributor_url'),
            'support_url' => $withDefault('support_url'),
            'changelog' => $info['changelog'] ?? '',
            'thirdparty' => true,
            'category' => 0,
            'subcategory' => 0,
            'type' => 0,
            'silent_install' => $p->flag('silent_install', false),
            'silent_uninstall' => $p->flag('silent_uninstall', false),
            'silent_upgrade' => $p->flag('silent_upgrade', false),
            'auto_upgrade_from' => $optional('auto_upgrade_from'),
        ];

        // DSM 7 hides every package that carries a "beta" key, whatever its value.
        if (!$dsm7) {
            $json['beta'] = $p->isBeta();
        }

        return $json;
    }
}
