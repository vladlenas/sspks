<?php

declare(strict_types=1);

namespace SSpkS\Tests;

use SSpkS\Stats\GitHubStats;

final class GitHubStatsTest extends TestCase
{
    private const NOW = 1_760_000_000;

    /** @var list<array{url: string, headers: list<string>}> */
    private array $calls = [];
    /** @var list<array{status: int, body: string}> */
    private array $responses = [];

    private function stats(array $repos = ['me/pkg'], string $token = ''): GitHubStats
    {
        return new GitHubStats($repos, $token, $this->cacheDir . '/stats/github.json', function (string $url, array $headers): array {
            $this->calls[] = ['url' => $url, 'headers' => $headers];
            return array_shift($this->responses) ?? ['status' => 500, 'body' => ''];
        });
    }

    private static function release(string $tag, array $counts, array $extra = []): array
    {
        $assets = [];
        foreach ($counts as $name => $count) {
            $assets[] = ['name' => $name, 'download_count' => $count];
        }
        return $extra + [
            'tag_name' => $tag,
            'name' => $tag,
            'html_url' => "https://github.com/me/pkg/releases/tag/{$tag}",
            'published_at' => '2025-10-01T10:00:00Z',
            'prerelease' => false,
            'draft' => false,
            'assets' => $assets,
        ];
    }

    public function testFetchesAllPagesAndSkipsDrafts(): void
    {
        $page1 = [];
        for ($i = 0; $i < 100; $i++) {
            $page1[] = self::release("v1.{$i}", ['a.spk' => 1]);
        }
        $page2 = [
            self::release('v0.9', ['x64.spk' => 5, 'arm.spk' => 7], ['prerelease' => true]),
            self::release('v2.0-draft', ['x64.spk' => 99], ['draft' => true]),
        ];
        $this->responses = [
            ['status' => 200, 'body' => json_encode($page1)],
            ['status' => 200, 'body' => json_encode($page2)],
        ];

        $repos = $this->stats(token: 'secret')->get(self::NOW);

        $this->assertCount(2, $this->calls);
        $this->assertStringContainsString('/repos/me/pkg/releases?per_page=100&page=2', $this->calls[1]['url']);
        $this->assertContains('Authorization: Bearer secret', $this->calls[0]['headers']);
        $this->assertCount(1, $repos);
        $repo = $repos[0];
        $this->assertSame('me/pkg', $repo['repo']);
        $this->assertSame(112, $repo['total']);
        $this->assertCount(101, $repo['releases']);
        $last = $repo['releases'][100];
        $this->assertSame(['v0.9', true, 12], [$last['tag'], $last['prerelease'], $last['total']]);
        $this->assertSame([['name' => 'x64.spk', 'count' => 5], ['name' => 'arm.spk', 'count' => 7]], $last['assets']);
        $this->assertSame(strtotime('2025-10-01T10:00:00Z'), $last['published']);
        $this->assertNull($repo['error']);
    }

    public function testCachesForAnHourAndKeepsOldDataOnErrors(): void
    {
        $this->responses = [['status' => 200, 'body' => json_encode([self::release('v1', ['a.spk' => 3])])]];
        $this->stats()->get(self::NOW);
        $this->assertCount(1, $this->calls);

        $this->stats()->get(self::NOW + 1800);
        $this->assertCount(1, $this->calls, 'served from cache within the hour');

        $this->responses = [['status' => 403, 'body' => '{"message":"API rate limit exceeded"}']];
        $repo = $this->stats()->get(self::NOW + 3600)[0];
        $this->assertCount(2, $this->calls);
        $this->assertSame(3, $repo['total'], 'last good numbers are kept');
        $this->assertSame(self::NOW, $repo['fetched']);
        $this->assertStringContainsString('rate limit', (string) $repo['error']);
        $this->assertStringContainsString('rate limit', (string) file_get_contents($this->errorLog));

        $this->stats()->get(self::NOW + 3600 + 300);
        $this->assertCount(2, $this->calls, 'no retry right after an error');

        $this->responses = [['status' => 200, 'body' => json_encode([self::release('v2', ['a.spk' => 10])])]];
        $repo = $this->stats()->get(self::NOW + 3600 + 600)[0];
        $this->assertCount(3, $this->calls);
        $this->assertSame(10, $repo['total']);
        $this->assertNull($repo['error']);
    }

    public function testConnectionFailureWithoutCache(): void
    {
        $this->responses = [['status' => 0, 'body' => '']];
        $repo = $this->stats()->get(self::NOW)[0];
        $this->assertSame(0, $repo['total']);
        $this->assertSame([], $repo['releases']);
        $this->assertSame('no connection to api.github.com', $repo['error']);
    }

    public function testNoRepositoriesNoRequests(): void
    {
        $this->assertSame([], $this->stats([])->get(self::NOW));
        $this->assertSame([], $this->calls);
    }

    public function testParsesRepositoryList(): void
    {
        $this->assertSame(
            ['vladlenas/TorrServer-DSM', 'a/b.c'],
            \SSpkS\Config::parseRepos(" vladlenas/TorrServer-DSM, a/b.c\nbad repo/x/y vladlenas/TorrServer-DSM ")
        );
    }
}
