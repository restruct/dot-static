<?php

namespace Restruct\Dot\Tests;

use PHPUnit\Framework\TestCase;
use Restruct\Dot\DotGraph;

/**
 * DOT source generation: no binary involved.
 */
final class DotGraphTest extends TestCase
{
    public function testFullGraphCompilesInDeclarationOrder(): void
    {
        $dot = DotGraph::digraph('hierarchy')
            ->set('rankdir', 'LR')
            ->graphAttrs(['fontname' => 'Helvetica'])
            ->nodeDefaults(['shape' => 'box'])
            ->edgeDefaults(['color' => '#666666', 'arrowsize' => 0.7])
            ->node('a', ['label' => 'Node A'])
            ->node('b')
            ->edge('a', 'b', ['style' => 'dashed'])
            ->rankSame(['a', 'b'])
            ->toDot();

        $expected = <<<'DOT'
            digraph hierarchy {
                rankdir="LR";
                graph [fontname="Helvetica"];
                node [shape="box"];
                edge [color="#666666" arrowsize="0.7"];

                a [label="Node A"];
                b;
                a -> b [style="dashed"];
                { rank=same; a; b; }
            }

            DOT;
        $this->assertSame($expected, $dot);
    }

    public function testUndirectedGraphUsesGraphKeywordAndDoubleDash(): void
    {
        $this->assertSame("graph G {\n    a -- b;\n}\n", DotGraph::graph()->edge('a', 'b')->toDot());
    }

    public function testEmptyGraphHasNoBlankHeaderLine(): void
    {
        $this->assertSame("digraph G {\n}\n", DotGraph::digraph()->toDot());
    }

    public function testDefaultsMergeAcrossCalls(): void
    {
        $dot = DotGraph::digraph()
            ->nodeDefaults(['shape' => 'box', 'color' => 'red'])
            ->nodeDefaults(['color' => 'blue'])
            ->toDot();

        $this->assertStringContainsString('node [shape="box" color="blue"];', $dot);
    }

    public function testEmptyRankSameAddsNothing(): void
    {
        $this->assertSame(DotGraph::digraph()->toDot(), DotGraph::digraph()->rankSame([])->toDot());
    }

    public function testSubgraphIsIndentedAndInheritsDirection(): void
    {
        $dot = DotGraph::digraph()
            ->subgraph('cluster_0', function (DotGraph $sub) {
                $sub->set('label', 'A cluster')->edge('c', 'd');
            })
            ->toDot();

        $expected = <<<'DOT'
            digraph G {
                subgraph cluster_0 {
                    label="A cluster";

                    c -> d;
                }

            }

            DOT;
        $this->assertSame($expected, $dot);
    }

    public function testRawStatementGetsSemicolonOnlyWhenMissing(): void
    {
        $dot = DotGraph::digraph()
            ->raw('e -> f [constraint=false]')
            ->raw('g -> h;')
            ->raw('{ rank=min; e }')
            ->toDot();

        $this->assertStringContainsString("    e -> f [constraint=false];\n", $dot);
        $this->assertStringContainsString("    g -> h;\n", $dot);
        $this->assertStringContainsString("    { rank=min; e }\n", $dot);
    }

    public function testToStringIsToDot(): void
    {
        $graph = DotGraph::digraph()->edge('a', 'b');

        $this->assertSame($graph->toDot(), (string) $graph);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function escapeCases(): array
    {
        return [
            'double quote' => ['say "hi"', 'say \\"hi\\"'],
            'backslash' => ['C:\\path', 'C:\\\\path'],
            'real newline becomes \\n' => ["one\ntwo", 'one\\ntwo'],
            'DOT \\n kept' => ['one\\ntwo', 'one\\ntwo'],
            'DOT \\l and \\r kept' => ['left\\lright\\r', 'left\\lright\\r'],
            'plain text untouched' => ['Node A', 'Node A'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('escapeCases')]
    public function testEscape(string $raw, string $escaped): void
    {
        $this->assertSame($escaped, DotGraph::escape($raw));
    }

    public function testAttributeValuesAreEscaped(): void
    {
        $dot = DotGraph::digraph()->node('a', ['label' => "Say \"hi\"\nthere"])->toDot();

        $this->assertStringContainsString('a [label="Say \\"hi\\"\\nthere"];', $dot);
    }
}
