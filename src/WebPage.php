<?php

declare(strict_types=1);

namespace SSpkS;

/**
 * The human-facing package list. PHP renders the frame and embeds the data
 * as JSON; public/assets/app.js draws the list and handles filtering.
 */
final class WebPage
{
    private const STRINGS = [
        'ru' => [
            'sourceLabel' => 'Адрес источника для Центра пакетов',
            'sourceHint' => 'Центр пакетов → Настройки → Источники пакетов → Добавить',
            'copy' => 'Копировать',
            'copied' => 'Скопировано',
            'search' => 'Поиск по названию или описанию',
            'platform' => 'Платформа',
            'allPlatforms' => 'Все платформы',
            'anyInFamily' => 'любая %s',
            'platformHelp' => 'Как узнать платформу своего NAS',
            'dsm' => 'Версия DSM',
            'dsmAny' => 'Любая',
            'dsm7' => 'DSM 7',
            'dsm6' => 'DSM 6 и ниже',
            'download' => 'Скачать',
            'builds' => 'Сборки',
            'showBuilds' => 'Сборки: %d',
            'hideDetails' => 'Свернуть',
            'details' => 'Подробнее',
            'version' => 'Версия',
            'platforms' => 'Платформы',
            'minDsm' => 'DSM от',
            'size' => 'Размер',
            'updated' => 'Обновлён',
            'beta' => 'бета',
            'changelog' => 'Что нового',
            'maintainer' => 'Автор',
            'support' => 'Поддержка',
            'screenshots' => 'Снимки экрана',
            'emptyFolder' => 'В папке пакетов пока нет ни одного файла .spk.',
            'emptyFolderHint' => 'Положите пакеты в папку, подключённую к контейнеру как /packages, и обновите страницу.',
            'emptyFilter' => 'Под выбранные фильтры не подходит ни один пакет.',
            'clearFilters' => 'Сбросить фильтры',
            'broken' => 'Не удалось прочитать',
            'packagesOne' => '%d пакет',
            'packagesFew' => '%d пакета',
            'packagesMany' => '%d пакетов',
            'noscript' => 'Для списка пакетов нужен JavaScript. Центр пакетов на NAS работает и без него.',
        ],
        'en' => [
            'sourceLabel' => 'Package Center source address',
            'sourceHint' => 'Package Center → Settings → Package Sources → Add',
            'copy' => 'Copy',
            'copied' => 'Copied',
            'search' => 'Search by name or description',
            'platform' => 'Platform',
            'allPlatforms' => 'All platforms',
            'anyInFamily' => 'any %s',
            'platformHelp' => 'Find your NAS platform',
            'dsm' => 'DSM version',
            'dsmAny' => 'Any',
            'dsm7' => 'DSM 7',
            'dsm6' => 'DSM 6 and older',
            'download' => 'Download',
            'builds' => 'Builds',
            'showBuilds' => 'Builds: %d',
            'hideDetails' => 'Collapse',
            'details' => 'Details',
            'version' => 'Version',
            'platforms' => 'Platforms',
            'minDsm' => 'Min. DSM',
            'size' => 'Size',
            'updated' => 'Updated',
            'beta' => 'beta',
            'changelog' => 'What’s new',
            'maintainer' => 'Maintainer',
            'support' => 'Support',
            'screenshots' => 'Screenshots',
            'emptyFolder' => 'The packages folder has no .spk files yet.',
            'emptyFolderHint' => 'Put packages into the folder mounted as /packages and reload this page.',
            'emptyFilter' => 'No packages match these filters.',
            'clearFilters' => 'Clear filters',
            'broken' => 'Could not read',
            'packagesOne' => '%d package',
            'packagesFew' => '%d packages',
            'packagesMany' => '%d packages',
            'noscript' => 'The package list needs JavaScript. Package Center on your NAS works without it.',
        ],
    ];

    public function __construct(
        private readonly Config $config,
        private readonly PackageRepository $repository,
        private readonly Urls $urls,
    ) {
    }

    public static function language(Config $config, string $acceptLanguage): string
    {
        if (isset(self::STRINGS[$config->siteLang])) {
            return $config->siteLang;
        }
        foreach (explode(',', strtolower($acceptLanguage)) as $part) {
            $code = substr(trim($part), 0, 2);
            if (isset(self::STRINGS[$code])) {
                return $code;
            }
        }
        return 'en';
    }

    public function render(string $lang): string
    {
        $t = self::STRINGS[$lang] ?? self::STRINGS['en'];
        $synoLang = $lang === 'ru' ? 'rus' : 'enu';

        $groups = [];
        foreach ($this->repository->all() as $package) {
            $groups[$package->name()][] = $package; // already newest first
        }

        $items = [];
        foreach ($groups as $name => $builds) {
            $latest = $builds[0];
            $info = $latest->info;
            $items[] = [
                'id' => (string) $name,
                'name' => $latest->displayName($synoLang),
                'description' => $latest->text('description', $synoLang),
                'icon' => $this->urls->icon($latest, 120),
                'version' => $latest->version(),
                'beta' => $latest->isBeta(),
                'updated' => max(array_map(static fn (Package $p): int => $p->mtime, $builds)),
                'maintainer' => ($info['maintainer'] ?? '') ?: ($this->config->packageDefaults['maintainer'] ?? ''),
                'maintainerUrl' => ($info['maintainer_url'] ?? '') ?: ($this->config->packageDefaults['maintainer_url'] ?? ''),
                'supportUrl' => ($info['support_url'] ?? '') ?: ($this->config->packageDefaults['support_url'] ?? ''),
                'changelog' => $info['changelog'] ?? '',
                'screenshots' => $this->urls->screenshots($latest),
                'builds' => array_map(fn (Package $p): array => [
                    'file' => $p->file,
                    'url' => $this->urls->download($p),
                    'version' => $p->version(),
                    'arch' => $p->arch,
                    'minDsm' => $p->minDsm(),
                    'dsm7' => $p->isForDsm7(),
                    'beta' => $p->isBeta(),
                    'size' => $p->size,
                    'updated' => $p->mtime,
                ], $builds),
            ];
        }

        $data = [
            'lang' => $lang,
            't' => $t,
            'items' => $items,
            'broken' => $this->repository->broken(),
            'families' => Architectures::grouped(),
            'archFamily' => Architectures::FAMILIES,
            'aliases' => Architectures::ALIASES,
        ];

        $view = [
            'lang' => $lang,
            't' => $t,
            'siteName' => $this->config->siteName,
            'sourceUrl' => $this->urls->base(),
            'assetBase' => $this->urls->base() . 'assets/',
            'assetVersion' => substr(sha1(App::VERSION . $this->config->commit), 0, 8),
            'count' => count($items),
            'betaFlags' => array_map(static fn (array $item): bool => $item['beta'], $items),
            'version' => App::VERSION,
            'commit' => substr($this->config->commit, 0, 7),
            'json' => json_encode(
                $data,
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                    | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
            ),
        ];

        return self::template(dirname(__DIR__) . '/templates/page.php', $view);
    }

    public static function plural(string $lang, int $n, array $t): string
    {
        if ($lang === 'ru') {
            $mod10 = $n % 10;
            $mod100 = $n % 100;
            $key = ($mod10 === 1 && $mod100 !== 11) ? 'packagesOne'
                : (($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) ? 'packagesFew' : 'packagesMany');
        } else {
            $key = $n === 1 ? 'packagesOne' : 'packagesMany';
        }
        return sprintf($t[$key], $n);
    }

    /**
     * @param array<string, mixed> $view
     */
    private static function template(string $file, array $view): string
    {
        $e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        ob_start();
        try {
            (static function (string $__file, array $view, callable $e): void {
                require $__file;
            })($file, $view, $e);
            return (string) ob_get_clean();
        } catch (\Throwable $error) {
            ob_end_clean();
            throw $error;
        }
    }
}
