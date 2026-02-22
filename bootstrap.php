<?php

/**
 * Bootstrap for restruct/dot-static
 *
 * OS-aware path resolver for Graphviz dot binary.
 * Safe to include multiple times — guarded by defined() check.
 *
 * Priority:
 * 1. Already defined GRAPHVIZ_DOT_PATH constant (co-existence with docsys-tools)
 * 2. GRAPHVIZ_DOT_PATH environment variable
 * 3. macOS: Homebrew paths (ARM + Intel)
 * 4. Linux: bundled x64/dot_static binary
 */

if (!defined('GRAPHVIZ_DOT_PATH')) {
    $dotPath = null;

    # Check environment variable first
    $envPath = getenv('GRAPHVIZ_DOT_PATH');
    if ($envPath !== false && $envPath !== '' && is_executable($envPath)) {
        $dotPath = $envPath;
    }

    if ($dotPath === null) {
        switch (PHP_OS_FAMILY) {
            case 'Darwin':
                # macOS: check Homebrew paths (ARM first, then Intel)
                $candidates = [
                    '/opt/homebrew/bin/dot', # Apple Silicon (ARM)
                    '/usr/local/bin/dot',    # Intel
                ];
                foreach ($candidates as $candidate) {
                    if (is_executable($candidate)) {
                        $dotPath = $candidate;
                        break;
                    }
                }
                break;

            case 'Linux':
                # Linux: use the bundled statically compiled binary
                $bundled = __DIR__ . '/x64/dot_static';
                if (is_executable($bundled)) {
                    $dotPath = $bundled;
                }
                break;
        }
    }

    if ($dotPath !== null) {
        define('GRAPHVIZ_DOT_PATH', $dotPath);
    }
}
