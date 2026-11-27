<?php

namespace Condoedge\Utils\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PlainTextHelpersTest extends TestCase
{
    #[DataProvider('texts')]
    public function test_plain_text(?string $html, string $text): void
    {
        $this->assertSame($text, plainText($html));
    }

    public static function texts(): array
    {
        return [
            'null' => [null, ''],
            'empty' => ['', ''],
            'blank paragraph' => ['<p>&nbsp;</p>', ''],
            'apostrophes and ampersand' => ['L&apos;été &amp; l&#39;hiver', "L'été & l'hiver"],
            'decoded once' => ['&amp;lt;b&amp;gt;', '&lt;b&gt;'],
            'br spellings' => ['a<BR/>b<br />c<br>d<br class="x">e', "a\nb\nc\nd\ne"],
            'paragraphs' => ['<p>a</p><p>b</p>', "a\nb"],
            'uppercase blocks' => ['<P>Haut</P><DIV>Bas</DIV>', "Haut\nBas"],
            'one blank line at most' => ['<p>Bonjour,</p><p>&nbsp;</p><p>&nbsp;</p><p>Merci</p>', "Bonjour,\n\nMerci"],
            'nested tags' => ['<p><strong>Gras <em>et</em></strong> fin</p>', 'Gras et fin'],
            'lists and headings' => ['<h2>Titre</h2><ul><li>un</li><li>deux</li></ul>', "Titre\nun\ndeux"],
            'spaces' => ["  a  \t b \r\n c  ", "a b\nc"],
            'non-breaking spaces' => ['Prix&nbsp;:&nbsp;5&nbsp;$', 'Prix : 5 $'],
            'multibyte' => ['<p>Œufs à la crème ½ — 日本</p>', 'Œufs à la crème ½ — 日本'],
            'not a tag' => ['1 < 2 and 3 > 2', '1 < 2 and 3 > 2'],
            'zero' => ['<p>0</p>', '0'],
        ];
    }

    public function test_invalid_utf8_is_kept(): void
    {
        $this->assertSame("bad \xC3\x28 utf", plainText("bad \xC3\x28 utf"));
    }

    public function test_plain_text_line_joins_the_lines(): void
    {
        $this->assertSame('Bonjour Marie, Merci !', plainTextLine('<p>Bonjour Marie,</p><p>Merci&nbsp;!</p>'));
        $this->assertSame('a b', plainTextLine("<p>a</p>\n\n<p>b</p>"));
        $this->assertSame('', plainTextLine(null));
    }

    #[DataProvider('blanks')]
    public function test_is_blank_html(?string $html, bool $blank): void
    {
        $this->assertSame($blank, isBlankHtml($html));
    }

    public static function blanks(): array
    {
        return [
            'null' => [null, true],
            'empty' => ['', true],
            'br only' => ['<p><br></p>', true],
            'nbsp only' => ['<p>&nbsp;</p>', true],
            'image only' => ['<p><img src="x.png"></p>', true],
            'zero' => ['<p>0</p>', false],
            'text' => ['<p>Merci</p>', false],
        ];
    }
}
