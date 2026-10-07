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
            'statsLink' => 'Статистика',
            'packagesLink' => 'Пакеты',
            'downloadsOne' => '%d скачивание',
            'downloadsFew' => '%d скачивания',
            'downloadsMany' => '%d скачиваний',
            'statsTitle' => 'Статистика скачиваний',
            'statsServer' => 'С этого сервера',
            'statsRecent' => 'За %d дней',
            'statsGithub' => 'На GitHub',
            'statsDaily' => 'Скачивания с сервера по дням',
            'statsByPackage' => 'По пакетам',
            'statsPackage' => 'Пакет',
            'statsTotal' => 'Всего',
            'statsFromPage' => 'Со страницы',
            'statsDirect' => 'Центр пакетов и прямые ссылки',
            'statsLast' => 'Последнее',
            'statsSince' => 'Подсчёт с %s',
            'statsNone' => 'Скачиваний пока не было. Каждое скачивание .spk с этого сервера появится здесь.',
            'statsGithubNone' => 'Чтобы видеть скачивания релизов, перечислите репозитории в SSPKS_GITHUB_REPOS.',
            'statsGithubError' => 'GitHub недоступен (%s). Показаны данные от %s.',
            'statsGithubChecked' => 'Обновлено %s, раз в час',
            'statsGithubEmpty' => 'В этом репозитории нет релизов.',
            'statsRelease' => 'Релиз',
            'statsPublished' => 'Опубликован',
            'statsFiles' => 'Файлы',
            'statsPrerelease' => 'пре-релиз',
            'statsShowAll' => 'Показать все релизы: %d',
            'statsDisabled' => 'Статистика отключена.',
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
            'statsLink' => 'Statistics',
            'packagesLink' => 'Packages',
            'downloadsOne' => '%d download',
            'downloadsFew' => '%d downloads',
            'downloadsMany' => '%d downloads',
            'statsTitle' => 'Download statistics',
            'statsServer' => 'From this server',
            'statsRecent' => 'Last %d days',
            'statsGithub' => 'On GitHub',
            'statsDaily' => 'Downloads from this server per day',
            'statsByPackage' => 'By package',
            'statsPackage' => 'Package',
            'statsTotal' => 'Total',
            'statsFromPage' => 'From the page',
            'statsDirect' => 'Package Center and direct links',
            'statsLast' => 'Last',
            'statsSince' => 'Counting since %s',
            'statsNone' => 'No downloads yet. Every .spk downloaded from this server will show up here.',
            'statsGithubNone' => 'List repositories in SSPKS_GITHUB_REPOS to see their release downloads.',
            'statsGithubError' => 'GitHub could not be reached (%s). Showing data from %s.',
            'statsGithubChecked' => 'Updated %s, once an hour',
            'statsGithubEmpty' => 'This repository has no releases.',
            'statsRelease' => 'Release',
            'statsPublished' => 'Published',
            'statsFiles' => 'Files',
            'statsPrerelease' => 'pre-release',
            'statsShowAll' => 'Show all %d releases',
            'statsDisabled' => 'Statistics are turned off.',
        ],
    ];

    public function __construct(
        private readonly Config $config,
        private readonly PackageRepository $repository,
        private readonly Urls $urls,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function strings(string $lang): array
    {
        return self::STRINGS[$lang] ?? self::STRINGS['en'];
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

    /**
     * @param array<string, array{total: int, recent: int}>|null $downloads Per package name; null when statistics are off.
     */
    public function render(string $lang, ?array $downloads = null): string
    {
        $t = self::strings($lang);
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
                'downloads' => $downloads[(string) $name]['total'] ?? 0,
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

        $view = self::frame($this->config, $this->urls, $lang, $items) + [
            'subtitle' => self::plural($lang, count($items), $t),
            'navLink' => $downloads !== null ? ['href' => $this->urls->base() . '?stats', 'label' => $t['statsLink']] : null,
            'sourceUrl' => $this->urls->base(),
            'json' => self::json($data),
        ];

        return self::template('page.php', $view);
    }

    /**
     * Values every page needs: language, strings, header with drive bays, footer.
     *
     * @param list<array{beta: bool}> $items Package groups, for the drive bays.
     * @return array<string, mixed>
     */
    public static function frame(Config $config, Urls $urls, string $lang, array $items): array
    {
        return [
            'lang' => $lang,
            't' => self::strings($lang),
            'siteName' => $config->siteName,
            'assetBase' => $urls->base() . 'assets/',
            'assetVersion' => substr(sha1(App::VERSION . $config->commit), 0, 8),
            'betaFlags' => array_map(static fn (array $item): bool => $item['beta'], $items),
            'version' => App::VERSION,
            'commit' => substr($config->commit, 0, 7),
        ];
    }

    /**
     * JSON that is safe inside <script type="application/json">.
     *
     * @param array<mixed> $data
     */
    public static function json(array $data): string
    {
        return json_encode(
            $data,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        );
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
    public static function template(string $name, array $view): string
    {
        $file = dirname(__DIR__) . '/templates/' . $name;
        $e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        ob_start();
        try {
            (static function (string $__file, array $view, callable $e): void {
                $__templates = dirname($__file);
                require $__file;
            })($file, $view, $e);
            return (string) ob_get_clean();
        } catch (\Throwable $error) {
            ob_end_clean();
            throw $error;
        }
    }
}
