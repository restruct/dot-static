Statically compiled Graphviz dot (dot_static, x86)
======
This is a very basic version without any additional options, it supports SVG output (no PNG), and only plaintext node labels (no HTML).

## Graphviz

**Graph visualization** is a way of representing structural information as diagrams of abstract graphs and networks. It has important applications in networking, bioinformatics,  software engineering, database and web design, machine learning, and in visual interfaces for other technical domains.

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

## Installation
* Simply install using composer (```composer require restruct/dot-static```), upload to your server and make sure you call the correct dot_static for your architecture (hopefully x64/Linux 64bit, which is the only version currently included)
* Make sure the vendor/restruct/dot-static/x64/dot_static executable has executable permissions (```chmod +x``` / 744). This permission is set on the file in the repo but doesn't always seem to get transfered properly when uploading via FTP.
* For local development on OSX, simply install dot using Homebrew (```brew install graphviz```) and use that instead.

### Additional notes/credits
Add a deb-src source to /etc/apt/sources.list and run ```apt-get update```

Install dependencies using ```apt-get build-dep graphviz```

Then used [Life in plain text](https://lifeinplaintextblog.wordpress.com/deploying-graphviz-on-aws-lambda/) as a reference on how to compile dot_static, commands duplicated here for reference (ammended ```configure --enable-static=yes --enable-shared=no```):
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

### Compiling a more full-featured version

I didn't manage to compile a static version of dot with more options/library/plugins. My attempts always resulted in dynamically linked libraries and thus - crashes when run on the webserver. Most importantly, I'd like to compile a version which includes PNG output (libpng) and HTML labels (libexpat) support

Check library dependencies & getting static versions:
https://gist.github.com/stain/8335322
http://jurjenbokma.com/ApprenticesNotes/getting_statlinked_binaries_on_debian.xhtml

Additional references:
http://www.graphviz.org/Download_source.php
http://genomewiki.ucsc.edu/index.php/Graphviz_static_build

Using Debian pre-compiled versions in homedir with setting LD_LIBRARY_PATH: https://stackoverflow.com/questions/8835108/how-to-specify-non-default-shared-library-path-in-gcc-linux-getting-error-whil#answer-8835402

### Javascript version
Using Emscripten, this project transpiles dot into Javascript, allowing it to run in the browser, which would remove the need to install it on the server altogether: https://github.com/mdaines/viz.js

## License
* This 'Object code': MIT
* Source code: Eclipse Public License - v 1.0
