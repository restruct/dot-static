<?php

namespace Restruct\Dot;

/**
 * Wrapper class for the dot-static bootstrap.
 *
 * Provides a clean API for resolving and checking the Graphviz dot binary path.
 */
class DotStatic
{
    private static bool $initialized = false;

    /**
     * Include the bootstrap file (once).
     */
    public static function init(): void
    {
        if (!self::$initialized) {
            require_once __DIR__ . '/../bootstrap.php';
            self::$initialized = true;
        }
    }

    /**
     * Get the resolved path to the Graphviz dot binary.
     *
     * Auto-initializes if needed.
     *
     * @return string The path, or empty string if not resolved
     */
    public static function getPath(): string
    {
        self::init();

        return defined('GRAPHVIZ_DOT_PATH') ? GRAPHVIZ_DOT_PATH : '';
    }

    /**
     * Check whether a usable dot binary is available.
     *
     * Auto-initializes if needed.
     */
    public static function isAvailable(): bool
    {
        $path = self::getPath();

        return $path !== '' && is_executable($path);
    }
}
