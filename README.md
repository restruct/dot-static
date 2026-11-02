# Graphviz dot for PHP, statically compiled

Draw diagrams from a text description of a graph, on any Linux x86_64 server, without installing
Graphviz: this package bundles a truly static `dot` binary (Graphviz 12.2.1, SVG output with HTML
labels, no shared libraries), a fluent PHP builder for DOT source, a renderer that returns SVG ready
to inline in HTML, and a browser-side fallback that renders the same graphs with WebAssembly.

**Graphviz version:** 12.2.1 (compiled Feb 2026)

*Maintained by [Restruct](https://github.com/restruct). If this package saves you time, you can
[support ongoing maintenance](https://github.com/sponsors/restruct).*

## A diagram from a few lines of text, without a server install

[Graphviz](https://graphviz.org/) turns a description of a graph in a simple text language (DOT)
into a diagram. **Graph visualization** is a way of representing structural information as
diagrams of abstract graphs and networks, with applications in networking, bioinformatics, software
engineering, database and web design, machine learning, and visual interfaces for other technical
domains.

Normally that means installing the `graphviz` package, with its tree of image and font libraries,
on every server, which shared hosting and locked-down servers do not allow. The `dot` binary in
this package has no runtime dependencies at all: no dynamic linker, no shared libraries. It works
on any Linux x86_64 system (Debian, Ubuntu, Alpine, and so on) and can simply be uploaded with the
rest of the project, without root or installation privileges.

So this:

```
digraph G {

	subgraph cluster_0 {
		style=filled;
		color=lightgrey;
		node [style=filled,color=white];
		a0 -> a1 -> a2 -> a3;
		label = "process #1";
	}

	subgraph cluster_1 {
		node [style=filled];
		b0 -> b1 -> b2 -> b3;
		label = "process #2";
		color=blue
	}
	start -> a0;
	start -> b0;
	a1 -> b3;
	b2 -> a3;
	a3 -> a0;
	a3 -> end;
	b3 -> end;

	start [shape=Mdiamond];
	end [shape=Msquare];
}
```

becomes:

![A Graphviz diagram of two clustered processes: a grey "process #1" box with nodes a0 to a3 and a blue-outlined "process #2" box with b0 to b3, both starting from a diamond "start" node and ending in a square "end" node, with edges crossing between the clusters](images/cluster.png)

**dot** makes "hierarchical" or layered drawings of directed graphs. It is the default tool to use
if edges have directionality: dot aims edges in the same direction (top to bottom, or left to
right) and then tries to avoid edge crossings and reduce edge length.

## Build, render and embed graphs from PHP

- **Truly static binary**: no dynamic linker, no shared libraries needed, 2.6 MB stripped.
- **SVG output** with plaintext and HTML labels (via expat).
- **OS-aware bootstrap** (`bootstrap.php`) picks Homebrew's `dot` on macOS and the bundled binary on
  Linux.
- **Fluent DOT builder** (`DotGraph`) that handles quoting, escaping and indentation.
- **Renderer** (`DotRenderer`) that pipes DOT source to `dot` and returns SVG, optionally cleaned
  for inline HTML.
- **Client-side fallback** (`client/dot-client.js`) renders graphs in the browser via
  [viz-js](https://github.com/mdaines/viz-js) (WASM).

### Building a graph with `DotGraph`

```php
use Restruct\Dot\DotGraph;

$graph = DotGraph::digraph('hierarchy')        // or DotGraph::graph() for an undirected graph
    ->set('rankdir', 'LR')                     // graph-level attribute: rankdir="LR";
    ->set('bgcolor', 'transparent')
    ->graphAttrs(['fontname' => 'Helvetica']) // graph [fontname="Helvetica"];
    ->nodeDefaults(['shape' => 'box', 'style' => 'filled'])
    ->edgeDefaults(['color' => '#666666'])
    ->node('a', ['label' => 'Node A', 'fillcolor' => '#ff0000'])
    ->node('b', ['label' => 'Node B'])
    ->edge('a', 'b', ['style' => 'dashed'])
    ->rankSame(['a', 'b'])                     // { rank=same; a; b; }
    ->subgraph('cluster_0', function (DotGraph $sub) {
        $sub->set('label', 'A cluster')->node('c')->node('d')->edge('c', 'd');
    })
    ->raw('e -> f [constraint=false]');        // escape hatch, ';' is added when missing

$dotSource = $graph->toDot();                  // or (string) $graph
```

Every attribute value is double-quoted and escaped: backslashes and double quotes are escaped, a
real newline becomes DOT's `\n`, and the DOT line-break sequences `\n`, `\l` and `\r` are kept.
`DotGraph::escape()` does the same for a string of your own. Node IDs are written as given, so use
plain identifiers (`item_42`). A subgraph whose name starts with `cluster_` is drawn as a box.

### Rendering with `DotRenderer`

```php
use Restruct\Dot\DotRenderer;

$renderer = new DotRenderer();
$renderer->setTimeout(20);                     // seconds, default 10

$svg = $renderer->render($graph);              // a DotGraph or a DOT source string; SVG by default
$inline = $renderer->renderInlineSvg($graph);  // SVG ready to put inside HTML

if ($svg === null) {
    error_log($renderer->getLastError() ?? 'dot is not available');
}
```

`render()` returns `null` when no `dot` binary is resolved, when `dot` fails, when it prints
nothing, or when it runs longer than the timeout; `getLastError()` then holds dot's error output
(for a timeout: a "dot timed out" line, followed by anything dot wrote to stderr before it was
stopped). The second argument of `render()` is the
`-T` output format. The bundled binary is built without cairo, gd and libpng, so it has no PNG,
JPEG, GIF or PDF output (`render()` returns `null` and `getLastError()` says the format is not
recognized). It does have `svg` (the default) and `svgz`, PostScript (`ps`, `eps`) and Graphviz's
text formats such as `json`, `dot`, `xdot` and `plain`; `x64/dot_static -T?` lists them all. For
raster or PDF output, use a full Graphviz install (such as Homebrew's).

`renderInlineSvg()`, and `DotRenderer::cleanSvgForInline()` on any SVG string, strip the XML
declaration and DOCTYPE, replace `xlink:href` with `href` and drop the `xmlns:xlink` namespace.
They do not change the SVG's dimensions or scaling.

### The binary path, for your own command line

```php
// Auto-detect path (macOS Homebrew or Linux bundled binary)
require 'vendor/restruct/dot-static/bootstrap.php';
$dotPath = GRAPHVIZ_DOT_PATH;

// Or via the class wrapper
use Restruct\Dot\DotStatic;
$dotPath = DotStatic::getPath();
if (DotStatic::isAvailable()) {
    exec("$dotPath -Tsvg input.dot -o output.svg");
}
```

### Client-side rendering (browser)

For rendering graphs in the browser without a server binary, include the client-side wrapper, which
uses [viz-js](https://github.com/mdaines/viz-js) (Graphviz compiled to WebAssembly):

```html
<!-- Auto-renders all elements with data-dot-graph attribute -->
<script type="module" src="vendor/restruct/dot-static/client/dot-client.js"></script>

<div data-dot-graph="digraph { a -> b }"></div>

<!-- or with the DOT source as the element's content -->
<script data-dot-graph type="text/dot">digraph { a -> b }</script>
```

Or programmatically:

```javascript
const svg = await DotClient.renderString('digraph { a -> b }');
document.body.appendChild(svg);

await DotClient.render(element, { engine: 'neato' });  // one element, any Graphviz engine
await DotClient.renderAll();                            // every [data-dot-graph] element
await DotClient.isAvailable();                          // whether viz-js could be loaded
```

The script renders every `[data-dot-graph]` element once the page has loaded, and exposes
`DotClient` on `window` and as the module's default export. A `<div>` gets the SVG as its content,
a `<script>` gets it inserted after it. A graph that fails to render logs to the console and gets
the class `dot-render-error`.

viz-js is loaded at runtime from jsDelivr (`https://cdn.jsdelivr.net/npm/@viz-js/viz@3/+esm`), so
the browser needs to reach that CDN, and a Content-Security-Policy must allow it. The
`vendor/...` path above assumes the vendor directory is web-accessible. In a Silverstripe project,
this package's `composer.json` exposes the `client` directory, so `composer vendor-expose` publishes
the file to `_resources/vendor/restruct/dot-static/client/dot-client.js` (with the default
resources directory name).

## Technical

### Version compatibility

| Branch | Package version | Graphviz | PHP |
|--------|-----------------|----------|-----|
| `main` | `2.x` | 12.2.1, truly static | 8.2+ |
| - (tags only) | `1.5` | 12.2.1, truly static | any (no constraint declared) |
| - (tags only) | `1.0` - `1.4` | 2.40.1, SVG only, no HTML labels | any (no constraint declared) |

`composer.json` is the source of truth for exact constraints. `2.0` added `DotGraph` and
`DotRenderer` and with them the `symfony/process` requirement; `1.5` rebuilt the binary and added
the client-side fallback. This is a plain PHP package; it does not depend on any framework.

### Requirements

- PHP 8.2 or higher and `symfony/process` ^7.
- The bundled binary is Linux x86_64 only. On macOS the bootstrap uses Homebrew's `dot`
  (`brew install graphviz`); on other platforms install Graphviz and set `GRAPHVIZ_DOT_PATH`.

### Installation

```bash
composer require restruct/dot-static
```

Make sure the `vendor/restruct/dot-static/x64/dot_static` executable has executable permissions
(`chmod +x` / 744). This permission is set on the file in the repo but does not always get
transferred properly when uploading via FTP.

For local development on macOS, install dot using Homebrew (`brew install graphviz`) and use that
instead. The bootstrap auto-detects it.

### Binary resolution

`bootstrap.php` (included by `DotStatic` on first use, or by hand) defines `GRAPHVIZ_DOT_PATH`
from the first of these that is executable:

1. `GRAPHVIZ_DOT_PATH` constant (if already defined, for example by `restruct/docsys-tools`)
2. `GRAPHVIZ_DOT_PATH` environment variable
3. macOS: `/opt/homebrew/bin/dot` (Apple Silicon), then `/usr/local/bin/dot` (Intel)
4. Linux: the bundled `x64/dot_static`

When none resolves, the constant is not defined, `DotStatic::getPath()` returns `''` and
`DotRenderer::render()` returns `null`.

### Building the static binary

The binary is built reproducibly via Docker (Alpine/musl). See **[build/README.md](build/README.md)**
for full build instructions, how it works, and lessons learned. `build/` is not included in dist
installs.

### License

- Wrapper code (`bootstrap.php`, `src/`, `client/`, `build/`): MIT, see [LICENSE](LICENSE).
- Bundled binary (`x64/dot_static`): compiled by us, without modifications, from the Graphviz 12.2.1
  source, which is Copyright its authors and distributed under the Eclipse Public License 1.0
  (EPL-1.0; see `LICENSE` in that source). The full licence text ships as
  [licenses/Graphviz-EPL-1.0.txt](licenses/Graphviz-EPL-1.0.txt). The binary is not covered by
  our MIT licence. Its source code is available from
  https://gitlab.com/graphviz/graphviz/-/releases/12.2.1, and
  [build/Dockerfile](build/Dockerfile) reproduces the build. It also statically links these libraries
  from Alpine Linux 3.21, whose licence notices ship in [`licenses/`](licenses/):
  - expat 2.7.4 (MIT): [licenses/expat-COPYING](licenses/expat-COPYING)
  - zlib 1.3.1 (Zlib licence): [licenses/zlib-LICENSE](licenses/zlib-LICENSE)
  - musl libc 1.2.5 (MIT): [licenses/musl-COPYRIGHT](licenses/musl-COPYRIGHT)

  The expat and zlib versions are the ones embedded in the binary (`strings x64/dot_static`); a
  rebuild picks up whatever Alpine 3.21 ships at that time, so update these files with it.
- `client/dot-client.js` loads [viz-js](https://github.com/mdaines/viz-js) (MIT) from a CDN at
  runtime; it is not bundled.
