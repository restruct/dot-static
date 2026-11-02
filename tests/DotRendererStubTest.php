<?php

namespace Restruct\Dot\Tests;

use PHPUnit\Framework\TestCase;

/**
 * DotRenderer's process handling and error paths, against stub binaries.
 * The real binary is exercised in BundledBinaryTest.
 */
final class DotRendererStubTest extends TestCase
{
    use StubBinaries;

    /**
     * Render $dot with GRAPHVIZ_DOT_PATH predefined as $binary; returns [output, lastError].
     *
     * @return array{0: ?string, 1: ?string}
     */
    private static function renderWith(string $binary, string $dot = 'digraph { a -> b }', string $format = 'svg'): array
    {
        $out = ChildPhp::run(
            "define('GRAPHVIZ_DOT_PATH', " . var_export($binary, true) . ');'
            . '$r = new \Restruct\Dot\DotRenderer();'
            . '$o = $r->render(' . var_export($dot, true) . ', ' . var_export($format, true) . ');'
            . 'echo json_encode([$o, $r->getLastError()]);'
        );

        return json_decode($out, true, flags: JSON_THROW_ON_ERROR);
    }

    public function testNoResolvedBinaryReturnsNullWithoutError(): void
    {
        $this->assertSame([null, null], self::renderWith(''));
    }

    public function testSourceIsPipedOnStdinWithTheFormatFlag(): void
    {
        # The stub echoes its arguments, then stdin: proves "-T<format>" and the stdin pipe
        $stub = $this->stub('echo "args:$*"; cat');
        [$output, $error] = self::renderWith($stub, "digraph { x -> y }\n", 'json');

        $this->assertSame("args:-Tjson\ndigraph { x -> y }\n", $output);
        $this->assertNull($error);
    }

    public function testDotGraphIsCompiledBeforePiping(): void
    {
        $stub = $this->stub('cat');
        $out = ChildPhp::run(
            "define('GRAPHVIZ_DOT_PATH', " . var_export($stub, true) . ');'
            . '$g = \Restruct\Dot\DotGraph::digraph()->edge("a", "b");'
            . 'echo (new \Restruct\Dot\DotRenderer())->render($g) === $g->toDot() ? "same" : "different";'
        );

        $this->assertSame('same', $out);
    }

    public function testNonZeroExitReturnsNullAndKeepsStderr(): void
    {
        $stub = $this->stub('echo partial; echo "Error: boom" >&2; exit 1');

        $this->assertSame([null, "Error: boom\n"], self::renderWith($stub));
    }

    public function testEmptyOutputReturnsNull(): void
    {
        $stub = $this->stub('exit 0');

        $this->assertSame([null, null], self::renderWith($stub));
    }

    public function testStderrOnSuccessIsReportedAlongsideOutput(): void
    {
        # dot prints warnings on stderr and still exits 0; the output is kept
        $stub = $this->stub('echo "Warning: something" >&2; echo ok');

        $this->assertSame(["ok\n", "Warning: something\n"], self::renderWith($stub));
    }

    public function testTimeoutReturnsNullAndReportsIt(): void
    {
        # A dot run longer than setTimeout() must return null like any other failure (issue #3),
        # not throw ProcessTimedOutException out of render(). The stub prints to stderr first, so
        # the case also proves stderr written before the kill is kept in getLastError(). `exec` makes
        # the timeout kill hit sleep itself, so no orphaned child holds the pipes open afterwards.
        $stub = $this->stub('echo "Warning: slow" >&2; exec sleep 5');
        $out = ChildPhp::run(
            "define('GRAPHVIZ_DOT_PATH', " . var_export($stub, true) . ');'
            . '$r = (new \Restruct\Dot\DotRenderer())->setTimeout(1);'
            . '$o = $r->render("digraph { a -> b }");'
            . 'echo json_encode([$o, $r->getLastError()]);'
        );
        [$output, $error] = json_decode($out, true, flags: JSON_THROW_ON_ERROR);

        $this->assertNull($output);
        $this->assertIsString($error);
        $this->assertStringContainsString('timed out', $error);
        $this->assertStringContainsString('Warning: slow', $error);
    }
}
