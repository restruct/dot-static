# Building the static dot binary

The bundled `x64/dot_static` binary is built using Docker with Alpine Linux (musl libc) to produce a truly portable static binary. The build is fully reproducible via the included Dockerfile.

## Prerequisites

- Docker (or OrbStack on macOS)

## Build steps

```bash
cd /path/to/dot-static

# Build the Docker image (compiles Graphviz from source)
docker build --platform linux/amd64 -t dot-static-build build/

# Extract the binary from the image
docker create --platform linux/amd64 --name dot-extract dot-static-build /bin/true
docker cp dot-extract:/out/dot_static x64/dot_static
docker rm dot-extract
chmod +x x64/dot_static
```

## How the Dockerfile works

1. **Base image:** Alpine 3.21 (uses musl libc, which produces portable static binaries)
2. **Dependencies:** build-base, autoconf, automake, libtool, bison, flex, python3, expat-dev, expat-static, zlib-dev, zlib-static
3. **Source:** Downloads Graphviz source from GitLab (version set via `GRAPHVIZ_VERSION` ARG)
4. **Configure:** Disables all heavy image/font dependencies (cairo, pango, gd, freetype, fontconfig, libpng). Enables expat for HTML labels. Disables ltdl (dynamic plugin loading) so plugins are compiled as builtins.
5. **Build:** `make -j$(nproc)` builds all libraries and plugins
6. **Static link:** `make LDFLAGS="-all-static" dot_static` in `cmd/dot/` produces the truly static binary. The `-all-static` flag is critical — it tells libtool to link everything including libc statically. Without it, the binary would be dynamically linked against musl.
7. **Strip:** Debug symbols are stripped to reduce binary size (~2.6MB)
8. **Sanity tests:** SVG output and HTML labels are verified inside the container

## Key lessons learned

- **`LDFLAGS="-static"` is NOT enough** — libtool ignores `-static` during the final link. Use `-all-static` instead.
- **`make dot_static` won't work standalone** — you must first run `make` to build all libraries and plugins, then `make dot_static` links them into the static binary.
- **python3 is needed** — Graphviz uses Python scripts to generate color/entity header files at build time.
- **The `dot_static` Makefile target exists in `cmd/dot/`** — this is the built-in target for producing a static binary with all plugins compiled as builtins.

## Updating the Graphviz version

Edit `build/Dockerfile` and change the `GRAPHVIZ_VERSION` ARG:

```dockerfile
ARG GRAPHVIZ_VERSION=12.2.1
```

Then rebuild. Check [Graphviz releases](https://gitlab.com/graphviz/graphviz/-/releases) for available versions.

## Old build notes (Graphviz 2.40.1, ~2017)

The previous binary was built on Debian using the old approach. This produced a dynamically linked binary (despite the name "dot_static"), which only worked because the linked glibc version happened to be compatible with most servers.

Reference: [Life in plain text - Deploying Graphviz on AWS Lambda](https://lifeinplaintextblog.wordpress.com/deploying-graphviz-on-aws-lambda/)

```
$ wget http://www.graphviz.org/pub/graphviz/stable/SOURCES/graphviz-2.40.1.tar.gz
$ tar -xvf graphviz-2.40.1.tar.gz
$ // cd into the directory
$ ./configure --enable-static=yes --enable-shared=no
$ // install missing dependencies if there is any
$ make
$ cd cmd/dot
$ make dot_static
```

The original attempts to include PNG output (libpng) and HTML labels (libexpat) always resulted in dynamically linked libraries. The Alpine/musl Docker approach solved this — expat is now included and truly statically linked.
