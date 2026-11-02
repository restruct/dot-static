<?php

namespace Restruct\Dot\Tests;

use PHPUnit\Framework\TestCase;
use Restruct\Dot\DotRenderer;

/**
 * The static SVG cleaner on its own, no binary involved.
 */
final class CleanSvgForInlineTest extends TestCase
{
    public function testStripsDeclarationsAndModernisesLinks(): void
    {
        $svg = <<<'SVG'
            <?xml version="1.0" encoding="UTF-8" standalone="no"?>
            <!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN"
             "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">
            <svg width="10pt" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
            <a xlink:href="https://example.com/" xlink:title="t"><text>x</text></a>
            </svg>
            SVG;

        $expected = <<<'SVG'
            <svg width="10pt" xmlns="http://www.w3.org/2000/svg">
            <a href="https://example.com/" xlink:title="t"><text>x</text></a>
            </svg>
            SVG;
        $this->assertSame($expected, DotRenderer::cleanSvgForInline($svg));
    }

    public function testLeavesDimensionsAlone(): void
    {
        $svg = '<svg width="116pt" height="138pt" viewBox="0.00 0.00 116.00 138.00"></svg>';

        $this->assertSame($svg, DotRenderer::cleanSvgForInline($svg));
    }
}
