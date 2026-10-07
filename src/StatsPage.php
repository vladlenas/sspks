<?php

declare(strict_types=1);

namespace SSpkS;

/**
 * Download statistics: this server (from the download log) and GitHub
 * releases. Rendered like the package list: PHP embeds JSON, stats.js draws.
 */
final class StatsPage
{
    /**
     * @param array<string, mixed> $server From DownloadStats::summarize().
     * @param list<array<string, mixed>> $github From GitHubStats::get().
     */
    public static function render(
        Config $config,
        Urls $urls,
        PackageRepository $repository,
        string $lang,
        array $server,
        array $github,
    ): string {
        $t = WebPage::strings($lang);
        $synoLang = $lang === 'ru' ? 'rus' : 'enu';

        // Display names for package ids, and one entry per package for the drive bays.
        $names = [];
        $bays = [];
        foreach ($repository->all() as $package) {
            if (!isset($names[$package->name()])) {
                $names[$package->name()] = $package->displayName($synoLang);
                $bays[] = ['beta' => $package->isBeta()];
            }
        }
        foreach ($server['packages'] as &$p) {
            $p['displayName'] = $names[$p['name']] ?? $p['name'];
        }
        unset($p);

        $data = [
            'lang' => $lang,
            't' => $t,
            'server' => $server,
            'github' => $github,
            'githubConfigured' => $config->githubRepos !== [],
        ];

        $view = WebPage::frame($config, $urls, $lang, $bays) + [
            'title' => $t['statsTitle'] . ' – ' . $config->siteName,
            'subtitle' => $t['statsTitle'],
            'navLink' => ['href' => $urls->base(), 'label' => $t['packagesLink']],
            'json' => WebPage::json($data),
        ];

        return WebPage::template('stats.php', $view);
    }
}
