# Static build of Graphviz dot for Linux x86_64
# Uses Alpine/musl for truly portable static binary
#
# Targets: SVG output + HTML labels (expat)
# Excludes: PNG/cairo/pango/gd/fontconfig (too many deps for static linking)
#
# Usage:
#   docker build --platform linux/amd64 -t dot-static-build build/
#   docker run --rm dot-static-build cat /out/dot_static > x64/dot_static
#   chmod +x x64/dot_static

FROM --platform=linux/amd64 alpine:3.21 AS builder

# Build dependencies
RUN apk add --no-cache \
    build-base \
    autoconf \
    automake \
    libtool \
    bison \
    flex \
    python3 \
    expat-dev \
    expat-static \
    zlib-dev \
    zlib-static

# Download Graphviz source
ARG GRAPHVIZ_VERSION=12.2.1
RUN wget -q "https://gitlab.com/graphviz/graphviz/-/archive/${GRAPHVIZ_VERSION}/graphviz-${GRAPHVIZ_VERSION}.tar.gz" \
    && tar xzf "graphviz-${GRAPHVIZ_VERSION}.tar.gz" \
    && rm "graphviz-${GRAPHVIZ_VERSION}.tar.gz"

WORKDIR /graphviz-${GRAPHVIZ_VERSION}

# Generate configure script (GitLab source tarball doesn't include it)
RUN ./autogen.sh

# Configure: static only, no dynamic plugins, no image renderers
# --enable-static=yes --enable-shared=no: static libraries
# --disable-ltdl: no dynamic plugin loading (plugins compiled as builtins)
# --with-expat: HTML label support
# --without-*: skip all heavy image/font dependencies
RUN LDFLAGS="-static" ./configure \
    --enable-static=yes \
    --enable-shared=no \
    --disable-ltdl \
    --with-expat \
    --without-gdk \
    --without-gdk-pixbuf \
    --without-gtk \
    --without-pangocairo \
    --without-cairo \
    --without-pango \
    --without-gd \
    --without-freetype2 \
    --without-fontconfig \
    --without-libpng \
    --without-webp \
    --without-jpeg \
    --without-rsvg \
    --without-ghostscript \
    --without-lasi \
    --without-glut \
    --without-x \
    --without-swig \
    --without-tcl \
    --without-perl \
    --without-python \
    --without-php \
    --without-ruby \
    --without-java \
    --without-lua \
    --without-ocaml \
    --without-sharp \
    --without-go \
    --without-guile \
    --without-r

# Build everything (libraries, plugins, commands) in dependency order
RUN make -j$(nproc)

# Build the truly static binary (links all plugins as builtins)
# -all-static tells libtool to link everything statically including libc
# rm first because 'make' already built a dynamically-linked version
RUN cd cmd/dot && rm -f dot_static && make LDFLAGS="-all-static" dot_static

# Verify it's truly static and strip debug symbols
RUN file cmd/dot/dot_static
RUN strip cmd/dot/dot_static
RUN ls -lh cmd/dot/dot_static

# Copy to output
RUN mkdir -p /out && cp cmd/dot/dot_static /out/

# Quick sanity test
RUN echo 'digraph { a -> b [label="test"] }' | /out/dot_static -Tsvg > /dev/null \
    && echo "SVG output: OK"
RUN echo 'digraph { a [label=<<b>HTML</b>>] }' | /out/dot_static -Tsvg > /dev/null \
    && echo "HTML labels: OK"

# Final stage: just the binary
FROM scratch
COPY --from=builder /out/dot_static /out/dot_static
