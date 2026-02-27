<?php

namespace Restruct\Dot;

use Symfony\Component\Process\Process;

/**
 * Renders DOT source to SVG (or other formats) via the Graphviz dot binary.
 *
 * Uses Symfony Process to pipe DOT source via stdin and read output from stdout.
 * Binary resolution is handled by DotStatic (OS-aware: macOS Homebrew / Linux bundled).
 *
 * @see DotStatic Binary path resolution
 * @see DotGraph Fluent DOT language builder
 */
class DotRenderer
{
    private int $timeout = 10;

    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * Render DOT source to the given output format.
     *
     * @param string|DotGraph $dot DOT source string or DotGraph builder instance
     * @param string $format Output format (svg, png, pdf, etc.)
     * @return string|null Rendered output or null on failure
     */
    public function render(string|DotGraph $dot, string $format = 'svg'): ?string
    {
        $dotPath = DotStatic::getPath();
        if (!$dotPath) {
            return null;
        }

        $dotSource = ($dot instanceof DotGraph) ? $dot->toDot() : $dot;

        $process = new Process([$dotPath, '-T' . $format]);
        $process->setInput($dotSource);
        $process->setTimeout($this->timeout);
        $process->run();

        # Capture stderr for debugging via getLastError()
        $this->lastError = $process->getErrorOutput() ?: null;

        if (!$process->isSuccessful() || $process->getOutput() === '') {
            return null;
        }

        return $process->getOutput();
    }

    /**
     * Render DOT source to SVG cleaned for inline HTML embedding.
     *
     * Convenience method: renders to SVG then strips XML/DOCTYPE declarations
     * and modernizes SVG attributes for direct embedding in HTML.
     *
     * @param string|DotGraph $dot DOT source string or DotGraph builder instance
     * @return string|null Cleaned SVG markup or null on failure
     */
    public function renderInlineSvg(string|DotGraph $dot): ?string
    {
        $svg = $this->render($dot, 'svg');
        if ($svg === null) {
            return null;
        }

        return static::cleanSvgForInline($svg);
    }

    /**
     * Clean raw SVG output for inline HTML embedding.
     *
     * Static helper — can be used independently on any SVG string.
     * Performs these transformations:
     * - Strip XML declaration (<?xml ...?>)
     * - Strip DOCTYPE declaration
     * - Replace deprecated xlink:href with href (modern SVG)
     * - Remove xmlns:xlink namespace (no longer needed)
     *
     * Does NOT modify dimensions or scaling — that's application-specific.
     *
     * @param string $svg Raw SVG string (e.g. from Graphviz dot)
     * @return string SVG ready for inline HTML embedding
     */
    public static function cleanSvgForInline(string $svg): string
    {
        # Strip XML declaration and DOCTYPE
        $svg = preg_replace('/<\?xml[^?]*\?>\s*/i', '', $svg);
        $svg = preg_replace('/<!DOCTYPE[^>]*>\s*/i', '', $svg);

        # Replace xlink:href → href (modern SVG)
        $svg = str_replace('xlink:href', 'href', $svg);

        # Remove xmlns:xlink if present (no longer needed)
        $svg = preg_replace('/\s*xmlns:xlink="[^"]*"/', '', $svg);

        return $svg;
    }

    /**
     * Get the stderr output from the last render call.
     *
     * Useful for debugging failed renders without adding a logger dependency.
     * The consuming application can log this as needed.
     *
     * @return string|null stderr output or null if no render has been performed
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    private ?string $lastError = null;
}