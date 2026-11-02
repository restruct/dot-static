<?php

namespace Restruct\Dot\Tests;

use PHPUnit\Framework\TestCase;
use Restruct\Dot\DotGraph;
use Restruct\Dot\DotRenderer;
use Restruct\Dot\DotStatic;

/**
 * Real renders through whatever dot this machine resolves: the bundled x64/dot_static on Linux
 * (which is what CI runs), Homebrew's dot on macOS. Skips when none resolves; CI runs with
 * --fail-on-skipped, so there a skip is a failure.
 */
final class BundledBinaryTest extends TestCase
{
    protected function setUp(): void
    {
        if (!DotStatic::isAvailable()) {
            $this->markTestSkipped('No dot binary resolved on this machine');
        }
    }

    private static function requireBundled(TestCase $test): void
    {
        if (DotStatic::getPath() !== ChildPhp::bundledBinary()) {
            $test->markTestSkipped('Checks the bundled binary, which only resolves on Linux');
        }
    }

    public function testRendersSvgFromDotSource(): void
    {
        $svg = (new DotRenderer())->render('digraph { a -> b }');

        $this->assertNotNull($svg);
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('<title>a</title>', $svg);
        $this->assertStringContainsString('<title>a&#45;&gt;b</title>', $svg);
    }

    public function testEscapedDotGraphLabelsSurviveTheRoundTrip(): void
    {
        $graph = DotGraph::digraph()->node('b', ['label' => "Say \"hi\"\nthere"]);
        $svg = (new DotRenderer())->render($graph);

        # The real newline became a DOT line break: two text lines, quotes intact
        $this->assertStringContainsString('>Say &quot;hi&quot;</text>', $svg);
        $this->assertStringContainsString('>there</text>', $svg);
    }

    public function testHtmlLabelsAreParsed(): void
    {
        # HTML labels need expat; a build without it would print the markup or fail
        $svg = (new DotRenderer())->render('digraph { a [label=<<b>bold</b> text>] }');

        $this->assertNotNull($svg);
        $this->assertStringContainsString('font-weight="bold"', $svg);
        $this->assertStringNotContainsString('&lt;b&gt;', $svg);
    }

    public function testRenderInlineSvgIsCleanedForHtml(): void
    {
        $renderer = new DotRenderer();
        $dot = 'digraph { a [URL="https://example.com/"] }';

        $raw = $renderer->render($dot);
        $this->assertStringContainsString('<?xml', $raw);
        $this->assertStringContainsString('xlink:href', $raw);

        $inline = $renderer->renderInlineSvg($dot);
        $this->assertStringNotContainsString('<?xml', $inline);
        $this->assertStringNotContainsString('<!DOCTYPE', $inline);
        $this->assertStringNotContainsString('xlink:href', $inline);
        $this->assertStringNotContainsString('xmlns:xlink', $inline);
        $this->assertStringContainsString('href="https://example.com/"', $inline);
    }

    public function testSyntaxErrorReturnsNullWithDotsMessage(): void
    {
        $renderer = new DotRenderer();

        $this->assertNull($renderer->render('digraph { a -> '));
        $this->assertStringContainsString('syntax error', (string) $renderer->getLastError());
    }

    public function testBundledBinaryIsTheDocumentedGraphvizVersion(): void
    {
        self::requireBundled($this);

        # dot -V prints to stderr
        $process = new \Symfony\Component\Process\Process([DotStatic::getPath(), '-V']);
        $process->mustRun();

        $this->assertStringContainsString('graphviz version 12.2.1', $process->getErrorOutput());
    }
}
