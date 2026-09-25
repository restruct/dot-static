Statically compiled Graphviz dot (dot_static, x64)
======

*Maintained by [Restruct](https://github.com/restruct). If this package saves you time, you can
[support ongoing maintenance](https://github.com/sponsors/restruct).*

Truly statically compiled `dot` binary for Linux x86_64. Supports SVG output and HTML node labels (via expat). No runtime dependencies — works on any Linux x64 system (Debian, Ubuntu, Alpine, etc.).

**Graphviz version:** 12.2.1 (compiled Feb 2026)

## Features

- **SVG output** with plaintext and HTML labels
- **Truly static binary** — no dynamic linker, no shared libraries needed
- **2.6MB** stripped binary size
- **OS-aware bootstrap** (`bootstrap.php`) auto-detects macOS Homebrew or Linux bundled binary
- **Client-side fallback** (`client/dot-client.js`) renders graphs in the browser via [viz-js](https://github.com/mdaines/viz-js) (WASM)

## Graphviz

**Graph visualization** is a way of representing structural information as diagrams of abstract graphs and networks. It has important applications in networking, bioinformatics, software engineering, database and web design, machine learning, and in visual interfaces for other technical domains.

The [Graphviz layout programs](http://www.graphviz.org/) take descriptions of graphs in a simple text language, and make diagrams in useful formats, such as images and SVG for web pages; PDF or Postscript for inclusion in other documents; or display in an interactive graph browser.

**So;**

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

**...becomes:**

![Graph example](images/cluster.png)

### dot command

**dot** can be used to create ``hierarchical'' or layered drawings of directed graphs. This is the default tool to use if edges have directionality. dot aims edges in the same direction (top to bottom, or left to right) and then attempts to avoid edge crossings and reduce edge length.

This package contains statically compiled version(s) of dot (self contained versions, which dont have dependencies on additional system libraries). These can simply be uploaded to a webserver in order to use dot without root/installation privileges.

## Requirements

- PHP 8.2 or higher and `symfony/process` ^7 (`composer.json` is the source of truth).
- The bundled binary is Linux x86_64 only. On macOS the bootstrap uses Homebrew's `dot`
  (`brew install graphviz`); on other platforms install Graphviz and set `GRAPHVIZ_DOT_PATH`.

## Installation

```bash
composer require restruct/dot-static
```

Make sure the `vendor/restruct/dot-static/x64/dot_static` executable has executable permissions (`chmod +x` / 744). This permission is set on the file in the repo but doesn't always seem to get transferred properly when uploading via FTP.

For local development on macOS, simply install dot using Homebrew (`brew install graphviz`) and use that instead. The bootstrap auto-detects this.

### PHP usage

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

### Client-side usage (browser)

For rendering graphs in the browser without a server binary, include the client-side wrapper which uses [viz-js](https://github.com/mdaines/viz-js) (Graphviz compiled to WebAssembly):

```html
<!-- Auto-renders all elements with data-dot-graph attribute -->
<script type="module" src="vendor/restruct/dot-static/client/dot-client.js"></script>

<div data-dot-graph="digraph { a -> b }"></div>
```

Or programmatically:

```javascript
const svg = await DotClient.renderString('digraph { a -> b }');
document.body.appendChild(svg);
```

## Building the static binary

The binary is built reproducibly via Docker (Alpine/musl). See **[build/README.md](build/README.md)** for full build instructions, how it works, and lessons learned.

## License

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
