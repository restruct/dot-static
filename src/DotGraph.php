<?php

namespace Restruct\Dot;

/**
 * Fluent builder for Graphviz DOT language.
 *
 * Provides a clean PHP API for constructing DOT graph definitions.
 * Handles attribute formatting, escaping, and indentation.
 *
 * Usage:
 *   $graph = DotGraph::digraph('hierarchy')
 *       ->set('rankdir', 'LR')
 *       ->set('bgcolor', 'transparent')
 *       ->nodeDefaults(['shape' => 'box', 'style' => 'filled'])
 *       ->edgeDefaults(['color' => '#666666'])
 *       ->node('a', ['label' => 'Node A', 'fillcolor' => '#ff0000'])
 *       ->node('b', ['label' => 'Node B'])
 *       ->edge('a', 'b', ['style' => 'dashed'])
 *       ->rankSame(['a', 'b']);
 *
 *   $dotSource = $graph->toDot();
 *   $svg = (new DotRenderer())->render($graph);
 *
 * @see DotRenderer Renders DOT source via the Graphviz binary
 * @see https://graphviz.org/doc/info/lang.html DOT language reference
 * @see https://graphviz.org/doc/info/attrs.html Attribute reference
 */
class DotGraph
{
    private bool $directed;
    private string $name;
    private bool $isSubgraph = false;

    /** @var array<string, string> Graph-level attributes (rankdir, bgcolor, etc.) */
    private array $graphAttrs = [];

    /** @var array<string, string> Attributes applied via graph [...] block */
    private array $graphBlockAttrs = [];

    /** @var array<string, string> Default node attributes */
    private array $nodeDefaults = [];

    /** @var array<string, string> Default edge attributes */
    private array $edgeDefaults = [];

    /** @var array<array{type: string, ...}> Ordered statements (nodes, edges, ranks, subgraphs, raw) */
    private array $statements = [];

    private function __construct(string $name, bool $directed)
    {
        $this->name = $name;
        $this->directed = $directed;
    }

    /**
     * Create a directed graph (digraph).
     */
    public static function digraph(string $name = 'G'): self
    {
        return new self($name, true);
    }

    /**
     * Create an undirected graph.
     */
    public static function graph(string $name = 'G'): self
    {
        return new self($name, false);
    }

    # ──────────────────────────────────────────────────────────────
    # Graph-level attributes
    # ──────────────────────────────────────────────────────────────

    /**
     * Set a graph-level attribute (rankdir, bgcolor, newrank, etc.).
     *
     * These render as top-level statements: `rankdir=LR;`
     */
    public function set(string $key, string $value): self
    {
        $this->graphAttrs[$key] = $value;
        return $this;
    }

    /**
     * Set attributes for the graph [...] block.
     *
     * These render as: `graph [fontname="Helvetica"];`
     * Useful for attributes that apply to the graph object itself.
     */
    public function graphAttrs(array $attrs): self
    {
        $this->graphBlockAttrs = array_merge($this->graphBlockAttrs, $attrs);
        return $this;
    }

    /**
     * Set default node attributes.
     *
     * Renders as: `node [shape=box style=filled ...];`
     */
    public function nodeDefaults(array $attrs): self
    {
        $this->nodeDefaults = array_merge($this->nodeDefaults, $attrs);
        return $this;
    }

    /**
     * Set default edge attributes.
     *
     * Renders as: `edge [color="#666666" arrowsize=0.7 ...];`
     */
    public function edgeDefaults(array $attrs): self
    {
        $this->edgeDefaults = array_merge($this->edgeDefaults, $attrs);
        return $this;
    }

    # ──────────────────────────────────────────────────────────────
    # Nodes, edges, and structural elements
    # ──────────────────────────────────────────────────────────────

    /**
     * Add a node.
     *
     * @param string $id Node identifier (e.g. 'item_42')
     * @param array<string, string> $attrs Node attributes (label, fillcolor, style, URL, tooltip, etc.)
     */
    public function node(string $id, array $attrs = []): self
    {
        $this->statements[] = ['type' => 'node', 'id' => $id, 'attrs' => $attrs];
        return $this;
    }

    /**
     * Add an edge between two nodes.
     *
     * @param string $from Source node ID
     * @param string $to Target node ID
     * @param array<string, string> $attrs Edge attributes (style, label, color, etc.)
     */
    public function edge(string $from, string $to, array $attrs = []): self
    {
        $this->statements[] = ['type' => 'edge', 'from' => $from, 'to' => $to, 'attrs' => $attrs];
        return $this;
    }

    /**
     * Add a rank constraint (same rank for a group of nodes).
     *
     * Renders as: `{ rank=same; id1; id2; id3; }`
     *
     * @param array<string> $nodeIds Node IDs to place on the same rank
     */
    public function rankSame(array $nodeIds): self
    {
        if (!empty($nodeIds)) {
            $this->statements[] = ['type' => 'rank', 'ids' => $nodeIds];
        }
        return $this;
    }

    /**
     * Add a subgraph (or cluster when name starts with "cluster_").
     *
     * The callable receives a DotGraph instance scoped to the subgraph.
     *
     * @param string $name Subgraph name (prefix with "cluster_" for visual clustering)
     * @param callable(DotGraph): void $builder Builds the subgraph contents
     */
    public function subgraph(string $name, callable $builder): self
    {
        $sub = new self($name, $this->directed);
        $sub->isSubgraph = true;
        $builder($sub);
        $this->statements[] = ['type' => 'subgraph', 'graph' => $sub];
        return $this;
    }

    /**
     * Add a raw DOT statement (escape hatch for unsupported features).
     *
     * The statement is included as-is (with trailing semicolon if missing).
     */
    public function raw(string $statement): self
    {
        $this->statements[] = ['type' => 'raw', 'statement' => $statement];
        return $this;
    }

    # ──────────────────────────────────────────────────────────────
    # Compilation
    # ──────────────────────────────────────────────────────────────

    /**
     * Compile the graph to DOT source.
     */
    public function toDot(): string
    {
        return $this->compile(0);
    }

    public function __toString(): string
    {
        return $this->toDot();
    }

    /**
     * Internal: compile with indentation depth tracking.
     */
    private function compile(int $depth): string
    {
        $indent = str_repeat('    ', $depth);
        $innerIndent = $indent . '    ';
        $lines = [];

        # Graph/subgraph declaration
        if ($this->isSubgraph) {
            $lines[] = $indent . 'subgraph ' . $this->name . ' {';
        } else {
            $keyword = $this->directed ? 'digraph' : 'graph';
            $lines[] = $indent . $keyword . ' ' . $this->name . ' {';
        }

        # Graph-level attributes (top-level statements)
        foreach ($this->graphAttrs as $key => $value) {
            $lines[] = $innerIndent . $key . '=' . self::quoteValue($value) . ';';
        }

        # graph [...] block attributes
        if (!empty($this->graphBlockAttrs)) {
            $lines[] = $innerIndent . 'graph ' . self::formatAttrs($this->graphBlockAttrs) . ';';
        }

        # node/edge defaults
        if (!empty($this->nodeDefaults)) {
            $lines[] = $innerIndent . 'node ' . self::formatAttrs($this->nodeDefaults) . ';';
        }
        if (!empty($this->edgeDefaults)) {
            $lines[] = $innerIndent . 'edge ' . self::formatAttrs($this->edgeDefaults) . ';';
        }

        # Blank line between header and body
        if (!empty($this->graphAttrs) || !empty($this->graphBlockAttrs)
            || !empty($this->nodeDefaults) || !empty($this->edgeDefaults)) {
            $lines[] = '';
        }

        # Statements (nodes, edges, ranks, subgraphs, raw)
        $edgeOp = $this->directed ? ' -> ' : ' -- ';
        foreach ($this->statements as $stmt) {
            switch ($stmt['type']) {
                case 'node':
                    $attrStr = !empty($stmt['attrs']) ? ' ' . self::formatAttrs($stmt['attrs']) : '';
                    $lines[] = $innerIndent . $stmt['id'] . $attrStr . ';';
                    break;

                case 'edge':
                    $attrStr = !empty($stmt['attrs']) ? ' ' . self::formatAttrs($stmt['attrs']) : '';
                    $lines[] = $innerIndent . $stmt['from'] . $edgeOp . $stmt['to'] . $attrStr . ';';
                    break;

                case 'rank':
                    $lines[] = $innerIndent . '{ rank=same; ' . implode('; ', $stmt['ids']) . '; }';
                    break;

                case 'subgraph':
                    $lines[] = $stmt['graph']->compile($depth + 1);
                    break;

                case 'raw':
                    $raw = $stmt['statement'];
                    # Add semicolon if missing (skip for lines ending in } or already having ;)
                    if (!str_ends_with(rtrim($raw), ';') && !str_ends_with(rtrim($raw), '}')) {
                        $raw = rtrim($raw) . ';';
                    }
                    $lines[] = $innerIndent . $raw;
                    break;
            }
        }

        $lines[] = $indent . '}';

        return implode("\n", $lines) . "\n";
    }

    # ──────────────────────────────────────────────────────────────
    # Attribute formatting and escaping
    # ──────────────────────────────────────────────────────────────

    /**
     * Format an associative array as DOT attribute list: [key="val" key2="val2"]
     *
     * @param array<string, string|int|float|bool> $attrs
     */
    private static function formatAttrs(array $attrs): string
    {
        $parts = [];
        foreach ($attrs as $key => $value) {
            $parts[] = $key . '=' . self::quoteValue((string) $value);
        }
        return '[' . implode(' ', $parts) . ']';
    }

    /**
     * Quote a value for DOT. Always double-quotes for consistency.
     *
     * Values containing DOT line breaks (\n, \l, \r) are preserved as-is
     * inside the quotes — these are DOT formatting directives, not PHP escapes.
     */
    private static function quoteValue(string $value): string
    {
        return '"' . self::escape($value) . '"';
    }

    /**
     * Escape a string for use in DOT quoted contexts.
     *
     * - Converts real PHP newlines to DOT \n line breaks
     * - Escapes backslashes and double quotes
     * - Preserves existing DOT line-break sequences (\n, \l, \r)
     *
     * @param string $text Raw text
     * @return string Escaped text safe for DOT quoted strings
     */
    public static function escape(string $text): string
    {
        # Convert real PHP newlines to DOT \n line break directives
        # (do this first, before backslash escaping)
        $text = str_replace("\n", '\\n', $text);

        # Preserve existing DOT line-break sequences (\n, \l, \r)
        # Temporarily replace them with placeholders before general backslash escaping
        $text = str_replace(['\\n', '\\l', '\\r'], ["\x00n", "\x00l", "\x00r"], $text);

        # Escape remaining backslashes and quotes
        $text = str_replace(['\\', '"'], ['\\\\', '\\"'], $text);

        # Restore DOT line-break sequences
        $text = str_replace(["\x00n", "\x00l", "\x00r"], ['\\n', '\\l', '\\r'], $text);

        return $text;
    }
}