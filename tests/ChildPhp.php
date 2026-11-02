<?php

namespace Restruct\Dot\Tests;

use Symfony\Component\Process\Process;

/**
 * Runs a PHP snippet in a fresh PHP process.
 *
 * bootstrap.php defines GRAPHVIZ_DOT_PATH once per process and a constant cannot be undefined,
 * so every resolution case (predefined constant, env var, platform default) needs its own
 * process. The child loads the Composer autoloader, so the snippet can use the package classes.
 */
final class ChildPhp
{
    public const REPO_ROOT = __DIR__ . '/..';

    /**
     * @param string $code PHP code without the opening tag; its stdout is returned
     * @param array<string, string|false> $env Environment overrides; false removes a variable
     */
    public static function run(string $code, array $env = []): string
    {
        # GRAPHVIZ_DOT_PATH is removed unless a case sets it, so a value in the developer's own
        # shell cannot leak into the expectation of a "no env var" case
        $env += ['GRAPHVIZ_DOT_PATH' => false];

        $prelude = 'require ' . var_export(self::REPO_ROOT . '/vendor/autoload.php', true) . ';';
        $process = new Process([PHP_BINARY, '-r', $prelude . $code], null, $env);
        $process->setTimeout(30);
        $process->mustRun();

        return $process->getOutput();
    }

    /**
     * What bootstrap.php resolves to on this machine with no constant and no env var,
     * mirroring its platform branch. null when nothing resolves.
     */
    public static function platformDefault(): ?string
    {
        $candidates = match (PHP_OS_FAMILY) {
            'Darwin' => ['/opt/homebrew/bin/dot', '/usr/local/bin/dot'],
            'Linux' => [self::bundledBinary()],
            default => [],
        };
        foreach ($candidates as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * The bundled binary as bootstrap.php spells it (__DIR__ of the repo root, not realpath'd).
     */
    public static function bundledBinary(): string
    {
        return dirname(__DIR__) . '/x64/dot_static';
    }
}
