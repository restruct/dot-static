<?php

namespace Restruct\Dot\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The resolution order bootstrap.php documents (README "Binary resolution"):
 * predefined constant, then env var, then the platform default.
 * Each case runs in a child process because the constant can be defined only once.
 */
final class BootstrapTest extends TestCase
{
    use StubBinaries;

    private const PRINT_RESOLVED = 'require ' . "'" . __DIR__ . "/../bootstrap.php';"
        . "echo defined('GRAPHVIZ_DOT_PATH') ? GRAPHVIZ_DOT_PATH : '<undefined>';";

    public function testPredefinedConstantWinsOverEnvVar(): void
    {
        $stub = $this->stub('exit 0');
        $out = ChildPhp::run("define('GRAPHVIZ_DOT_PATH', '/predefined/dot');" . self::PRINT_RESOLVED, [
            'GRAPHVIZ_DOT_PATH' => $stub,
        ]);

        $this->assertSame('/predefined/dot', $out);
    }

    public function testExecutableEnvVarWinsOverPlatformDefault(): void
    {
        $stub = $this->stub('exit 0');
        $out = ChildPhp::run(self::PRINT_RESOLVED, ['GRAPHVIZ_DOT_PATH' => $stub]);

        $this->assertSame($stub, $out);
    }

    public function testNonExecutableEnvVarFallsBackToPlatformDefault(): void
    {
        $notExecutable = $this->stub('exit 0', false);
        $out = ChildPhp::run(self::PRINT_RESOLVED, ['GRAPHVIZ_DOT_PATH' => $notExecutable]);

        $this->assertSame(ChildPhp::platformDefault() ?? '<undefined>', $out);
    }

    public function testEmptyEnvVarFallsBackToPlatformDefault(): void
    {
        $out = ChildPhp::run(self::PRINT_RESOLVED, ['GRAPHVIZ_DOT_PATH' => '']);

        $this->assertSame(ChildPhp::platformDefault() ?? '<undefined>', $out);
    }

    public function testLinuxResolvesTheBundledBinary(): void
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            $this->markTestSkipped('The bundled binary is the default on Linux only');
        }

        $this->assertSame(ChildPhp::bundledBinary(), ChildPhp::run(self::PRINT_RESOLVED));
    }

    public function testIncludingTwiceKeepsTheFirstResolution(): void
    {
        # "Safe to include multiple times": the second include must not redefine (that would warn)
        $stub = $this->stub('exit 0');
        $code = 'require ' . "'" . __DIR__ . "/../bootstrap.php';"
            . "putenv('GRAPHVIZ_DOT_PATH=/elsewhere');"
            . self::PRINT_RESOLVED;

        $this->assertSame($stub, ChildPhp::run($code, ['GRAPHVIZ_DOT_PATH' => $stub]));
    }
}
