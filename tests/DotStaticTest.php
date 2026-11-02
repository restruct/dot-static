<?php

namespace Restruct\Dot\Tests;

use PHPUnit\Framework\TestCase;

/**
 * DotStatic::getPath() / isAvailable() for each kind of resolved value.
 * Child processes, because each case predefines GRAPHVIZ_DOT_PATH differently.
 */
final class DotStaticTest extends TestCase
{
    use StubBinaries;

    private static function probe(string $definedPath): string
    {
        return ChildPhp::run(
            "define('GRAPHVIZ_DOT_PATH', " . var_export($definedPath, true) . ');'
            . 'echo json_encode([\Restruct\Dot\DotStatic::getPath(), \Restruct\Dot\DotStatic::isAvailable()]);'
        );
    }

    public function testExecutablePathIsAvailable(): void
    {
        $stub = $this->stub('exit 0');

        $this->assertSame(json_encode([$stub, true]), self::probe($stub));
    }

    public function testNonExecutablePathIsReturnedButNotAvailable(): void
    {
        # A binary uploaded over FTP without its x bit (README "Installation") lands here
        $file = $this->stub('exit 0', false);

        $this->assertSame(json_encode([$file, false]), self::probe($file));
    }

    public function testEmptyPathIsNotAvailable(): void
    {
        $this->assertSame(json_encode(['', false]), self::probe(''));
    }

    public function testWithoutConstantItResolvesThePlatformDefault(): void
    {
        # No constant, no env var: getPath() is the platform default, or '' when there is none
        $out = ChildPhp::run('echo json_encode([\Restruct\Dot\DotStatic::getPath(), \Restruct\Dot\DotStatic::isAvailable()]);');
        $default = ChildPhp::platformDefault();

        $this->assertSame(json_encode([$default ?? '', $default !== null]), $out);
    }
}
