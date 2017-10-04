Statically compiled Graphviz dot (dot_static, x86)
======

##Graphviz

**Graph visualization** is a way of representing structural information as diagrams of abstract graphs and networks. It has important applications in networking, bioinformatics,  software engineering, database and web design, machine learning, and in visual interfaces for other technical domains.

The Graphviz layout programs take descriptions of graphs in a simple text language, and make diagrams in useful formats, such as images and SVG for web pages; PDF or Postscript for inclusion in other documents; or display in an interactive graph browser.

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

##dot command

**dot** can be used to create ``hierarchical'' or layered drawings of directed graphs. This is the default tool to use if edges have directionality. dot aims edges in the same direction (top to bottom, or left to right) and then attempts to avoid edge crossings and reduce edge length.

This package contains statically compiled version(s) of dot (self contained versions, which dont have dependencies on additional system libraries). These can simply be uploaded to a webserver in order to use dot without root/installation privileges.

### Installation
* Simply install using composer/upload to your server and make sure you call the correct dot_static for your architecture (hopefully x64/Linux 64bit, which is the only version currently included)
* For local development on OSX, simply install dot using Homebrew (```brew install graphviz```)

### Additional notes/credits
Not sure if necessary, but first simply installed a regular version and dependencies using ```apt-get install graphviz```

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

Check library dependencies: https://gist.github.com/stain/8335322

Additional reference: http://genomewiki.ucsc.edu/index.php/Graphviz_static_build

## License
* This 'Object code': MIT
* Source code: Eclipse Public License - v 1.0
