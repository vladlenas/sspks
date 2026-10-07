<?php

declare(strict_types=1);

namespace SSpkS\Tests;

use SSpkS\Config;
use SSpkS\Urls;

final class UrlsTest extends TestCase
{
    public function testPlainRequest(): void
    {
        $urls = Urls::detect(new Config(), ['HTTP_HOST' => 'nas.local:9999', 'SCRIPT_NAME' => '/index.php']);
        $this->assertSame('http://nas.local:9999/', $urls->base());
    }

    public function testReverseProxyHeaders(): void
    {
        $urls = Urls::detect(new Config(), [
            'HTTP_HOST' => 'sspks:8080',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'packages.example.lt',
            'HTTP_X_FORWARDED_PREFIX' => '/spk/',
            'SCRIPT_NAME' => '/index.php',
        ]);
        $this->assertSame('https://packages.example.lt/spk/', $urls->base());
    }

    public function testInvalidHostFallsBackToServerName(): void
    {
        $urls = Urls::detect(new Config(), [
            'HTTP_HOST' => 'evil.example/<script>',
            'SERVER_NAME' => 'nas.local',
            'HTTPS' => 'on',
            'SCRIPT_NAME' => '/index.php',
        ]);
        $this->assertSame('https://nas.local/', $urls->base());
    }

    public function testConfiguredBaseUrlWins(): void
    {
        $urls = Urls::detect(new Config(baseUrl: 'https://pkg.example.lt'), ['HTTP_HOST' => 'other']);
        $this->assertSame('https://pkg.example.lt/', $urls->base());
    }
}
