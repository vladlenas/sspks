<?php

declare(strict_types=1);

namespace SSpkS\Tests;

use SSpkS\Spk\InfoParser;

final class InfoParserTest extends TestCase
{
    public function testParsesQuotedUnquotedAndEscapedValues(): void
    {
        $info = InfoParser::parse(
            "\xEF\xBB\xBFpackage=\"demo\"\r\n"
            . "# a comment\n"
            . "beta=yes\n"
            . "  displayname = 'Demo App'  \n"
            . "changelog=\"<a href=\\\"https://x.example\\\">notes</a> {!}\"\n"
            . "not a key value line\n"
        );

        $this->assertSame([
            'package' => 'demo',
            'beta' => 'yes',
            'displayname' => 'Demo App',
            'changelog' => '<a href="https://x.example">notes</a> {!}',
        ], $info);
    }

    public function testBooleans(): void
    {
        $this->assertTrue(InfoParser::toBool('yes'));
        $this->assertTrue(InfoParser::toBool('TRUE'));
        $this->assertTrue(InfoParser::toBool('1'));
        $this->assertFalse(InfoParser::toBool('no'));
        $this->assertFalse(InfoParser::toBool('0'));
        $this->assertNull(InfoParser::toBool(null));
        $this->assertNull(InfoParser::toBool(''));
    }
}
