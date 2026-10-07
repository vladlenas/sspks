<?php

declare(strict_types=1);

namespace SSpkS\Tests;

use SSpkS\App;
use SSpkS\Config;
use SSpkS\WebPage;

final class AppTest extends TestCase
{
    private const SERVER = ['HTTP_HOST' => 'nas.local', 'SCRIPT_NAME' => '/index.php', 'REQUEST_METHOD' => 'GET'];

    private function request(array $query, array $params = [], array $server = self::SERVER): string
    {
        $app = new App($this->config(), $server, dirname(__DIR__) . '/public');
        ob_start();
        try {
            $app->handle($query, $params + $query);
        } finally {
            $output = (string) ob_get_clean();
        }
        return $output;
    }

    public function testCatalogForSynology(): void
    {
        $out = $this->request([], [
            'unique' => 'synology_geminilake_920+',
            'arch' => 'geminilake',
            'major' => '7',
            'minor' => '2',
            'build' => '64570',
            'package_update_channel' => 'stable',
            'language' => 'enu',
        ]);
        $doc = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['alpha', 'longname'], array_column($doc['packages'], 'package'));
        $this->assertSame('http://nas.local/packages/alpha_x64-1.1.spk', $doc['packages'][0]['link']);
    }

    public function testIconAndFallbackIcon(): void
    {
        $this->assertSame(file_get_contents(__DIR__ . '/fixtures/icon.png'), $this->request(['icon' => 'alpha_x64-1.0.spk', 's' => '72']));
        $this->assertSame(
            file_get_contents(dirname(__DIR__) . '/public/assets/package-120.png'),
            $this->request(['icon' => 'legacy_dsm6.spk', 's' => '120'])
        );
        $this->assertSame(
            file_get_contents(dirname(__DIR__) . '/public/assets/package-72.png'),
            $this->request(['icon' => '../../etc/passwd', 's' => '72']),
            'unknown files never touch the file system'
        );
        $this->assertSame('', $this->request(['screen' => 'alpha_x64-1.0.spk', 'n' => '9']));
        $this->assertSame('first', $this->request(['screen' => 'alpha_x64-1.0.spk', 'n' => '1']));
    }

    public function testHealth(): void
    {
        $this->assertSame("ok\n", $this->request(['health' => '']));
    }

    public function testWebPage(): void
    {
        $html = $this->request([], [], self::SERVER + ['HTTP_ACCEPT_LANGUAGE' => 'ru-RU,ru;q=0.9']);

        $this->assertStringContainsString('<html lang="ru">', $html);
        $this->assertStringContainsString('Test &lt;site&gt;', $html);
        $this->assertStringContainsString('<code id="source-url">http://nas.local/</code>', $html);
        $this->assertStringContainsString('4 пакета', $html);

        preg_match('#<script type="application/json" id="data">(.*?)</script>#s', $html, $m);
        $this->assertNotEmpty($m);
        $this->assertStringNotContainsString('<', $m[1], 'embedded JSON must not contain raw tags');
        $data = json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['alpha', 'beta-tool', 'legacy', 'longname'], array_column($data['items'], 'id'));
        $this->assertCount(3, $data['items'][0]['builds']);
        $this->assertCount(3, $data['broken']);
    }

    public function testLanguageDetection(): void
    {
        $this->assertSame('ru', WebPage::language(new Config(), 'ru-RU,ru;q=0.9,en;q=0.8'));
        $this->assertSame('en', WebPage::language(new Config(), 'lt-LT,lt;q=0.9,en;q=0.8'));
        $this->assertSame('en', WebPage::language(new Config(), ''));
        $this->assertSame('ru', WebPage::language(new Config(siteLang: 'ru'), 'en-US'));
    }

    public function testRedirectIndexAndMethods(): void
    {
        $app = new App(new Config(redirectIndex: 'https://example.com', packagesDir: $this->packagesDir, cacheDir: $this->cacheDir), self::SERVER, __DIR__);
        ob_start();
        $app->handle([], []);
        $this->assertSame('', ob_get_clean());

        $this->assertSame('', $this->request([], [], ['REQUEST_METHOD' => 'DELETE'] + self::SERVER));
    }
}
