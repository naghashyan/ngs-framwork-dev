# Builders and File Streaming in NGS Framework

This document explains how static assets (JS, CSS, LESS, SASS, images, etc.) are detected, routed, built, and streamed to clients in the NGS framework. It describes the interplay between the Routes Resolver, Dispatcher, and the Builder classes, as well as development vs. production behaviors and directory conventions.


## High-level flow
- A request enters Dispatcher::dispatch().
- NgsModuleResolver determines the active module from the request URI.
- NgsRoutesResolver::resolveRoute(module, uri) attempts to match a configured route. If the URL looks like a static file (e.g., ends with .js or .css), it creates an NgsFileRoute instead.
- Dispatcher detects route type:
  - type = file → streamStaticFile(NgsFileRoute) → picks a Builder (or FileUtils) and streams the resource.
  - other types (load/action/api_*) are unrelated to the static files topic.


## NgsFileRoute: what it carries
Static file requests are represented by ngs\routes\NgsFileRoute, which extends NgsRoute and adds:
- fileUrl: relative path under the module's public directory.
- fileType: file extension or asset type (e.g., js, css, less, sass, jpg, png...).
- module: the resolved NgsModule instance.

Special case for CSS routes containing preprocessor folders:
- If fileType is initially "css" but the URL path includes a segment equal to less or sass, NgsFileRoute::processSpecialFileTypes() will update fileType to less or sass. This allows URLs like /css/less/app.css to compile LESS and return CSS.


## Dispatcher: how it streams a static file
Dispatcher::streamStaticFile(NgsFileRoute $route):
1) Resolve the absolute path by combining module public dir and route->getFileUrl().
2) If the file physically exists there, use FileUtils->sendFile() directly.
3) Otherwise, pick a Builder based on fileType:
   - js   → JsBuilderV2
   - css  → CssBuilder
   - less → LessBuilder
   - sass → SassBuilder
   - default (or unknown) → FileUtils
4) Call $builder->streamFile($filePath). Builders will build and/or stream as described below.

Note: There is a legacy quirk in Dispatcher which rewrites fileUrl containing js/ngs to js/admin/ngs. This is specific to some installations and can be revised in project-level customization.


## Builders: common behavior via AbstractBuilder
All builders extend ngs\util\AbstractBuilder and share core logic:

- Environment awareness
  - Production: build missing outputs and serve with caching enabled.
  - Development: serve source files directly (or compile on the fly for preprocessors) with no cache.

- streamFile(string $filePath) default behavior (except overrides):
  1. Extract the requested output file name (basename).
  2. Production:
     - If the request points under PUBLIC_OUTPUT_DIR and the file is missing, build() is invoked to generate it from builder.json.
     - Then the file is sent via FileUtils with appropriate Content-Type and cache headers.
  3. Development:
     - If the URL contains devout (e.g., /devout/css/file.css), AbstractBuilder will stream the underlying raw file content after customBufferUpdates().
     - If a physical file exists at the resolved path, it is sent directly (no cache).
     - If not, the builder reads builder.json to resolve the output_file entry and composes the dev output via doDevOutput().

- build($file):
  - Reads builder.json (see below) and resolves the output_file entry to a list of source files (possibly across modules).
  - Concatenates sources, applies customBufferUpdates(), optionally compresses, and writes into PUBLIC_DIR/PUBLIC_OUTPUT_DIR/<type-subdir>/<output_file>.
  - touch() is used to set the built file mtime to builder.json mtime (for cache busting via timestamps).

- getBuilderArr():
  - Parses the builder.json and recursively resolves nested builders and directory specifications.
  - Determines the target module for each source, defaulting to the current request's module unless explicitly set in the builder entry; if that module equals the core NGS module, it may be treated as null for certain path resolutions.

- resolveOutputSubDir(subDirName):
  - Ensures {PUBLIC_DIR}/{PUBLIC_OUTPUT_DIR}/{subDirName} exists (creating if needed) and returns its absolute path.


## builder.json structure (per asset type)
Typically located under the current module's asset folder, e.g.:
- JS:   <module>/{JS_DIR}/builder.json
- CSS:  <module>/{CSS_DIR}/builder.json
- LESS: <module>/{LESS_DIR}/builder.json
- SASS: <module>/{SASS_DIR}/builder.json

Common fields:
- output_file: The target file name (e.g., app.js, styles.css) requested by the client.
- files: A list of source files relative to the asset folder.
- module: (optional) override module namespace for the listed files.
- type: (optional) custom type hint.
- compress: (optional, boolean) whether to minify/compress in build.
- builders: (optional) nested builders; can compose complex outputs.
- dir: (optional) object to include an entire directory of files with fields:
  - path: relative directory path under the relevant asset folder
  - ext: file extension to include (e.g., js, css)
  - recursively: boolean

Example (JS):
[
  {
    "output_file": "app.js",
    "compress": true,
    "files": [
      "vendor/jquery.js",
      "main/app.js"
    ]
  }
]

Example (CSS):
[
  {
    "output_file": "styles.css",
    "compress": true,
    "dir": { "path": "components", "ext": "css", "recursively": true }
  }
]


## Per-builder details

- JsBuilder
  - Production: concatenates and minifies via ClosureCompiler.
  - Development: doDevOutput() emits document.write() tags for each file to include them separately from /js/<file>.
  - Output directory: PUBLIC_OUTPUT_DIR/JS_DIR.
  - Build mode uses JS_BUILD_MODE from configuration.

- JsBuilderV2 (default for js in Dispatcher)
  - Development override: streamDevFile() tries to serve the exact requested source file directly (no document.write()) by resolving it relative to the request path or the module’s JS_DIR; sends with no cache.
  - Production: falls back to AbstractBuilder::streamFile() (build + serve with cache), minifying via ClosureCompiler when compress is enabled in builder.json.
  - Output directory: PUBLIC_OUTPUT_DIR/JS_DIR.

- CssBuilder
  - Production: concatenates, optional compression (CssCompressor), writes to PUBLIC_OUTPUT_DIR/CSS_DIR.
  - Development: doDevOutput() echoes @import url(".../devout/css/...") lines so the browser loads original CSS files; customBufferUpdates() replaces tokens @NGS_PATH and @NGS_MODULE_PATH in file contents to absolute URLs based on the RequestContext and current module.

- LessBuilder
  - Development: compiles LESS on the fly and streams text/css immediately (no cache), injecting variables NGS_PATH and NGS_MODULE_PATH.
  - Production: compiles and writes CSS into PUBLIC_OUTPUT_DIR/LESS_DIR (with compress), then serves with cache.

- SassBuilder
  - Development: compiles SCSS on the fly and streams text/css, setting variables NGS_PATH and NGS_MODULE_PATH.
  - Production: compiles to PUBLIC_OUTPUT_DIR/SASS_DIR (Crunched formatter) and serves with cache.
  - Supports import path aliases like @ngs-cms, @<NGS_CMS_NS>, and @<NGS_DASHBOARDS_NS> mapping to other modules' SASS_DIR.


## Environment and configuration knobs
- Global environment via NgsEnvironmentContext; per-builder overrides for build modes:
  - JS:    JS_BUILD_MODE
  - LESS:  LESS_BUILD_MODE
  - SASS:  SASS_BUILD_MODE
- Directory constants (configured in NGS):
  - PUBLIC_DIR: module public base folder.
  - PUBLIC_OUTPUT_DIR: subfolder under public where built outputs are stored.
  - JS_DIR, CSS_DIR, LESS_DIR, SASS_DIR: per-type asset folders.
- RequestContext supplies:
  - getRequestUri(), getHttpHost(), getHttpHostByNs(), isAjaxRequest(), redirect(), etc.


## Static URL forms and preprocessor switching
- A URL ending in .css or .js is considered a static asset by the resolver.
- For CSS-type URLs, if the path contains a segment named less or sass, NgsFileRoute switches fileType to less or sass. This allows URLs like:
  - /css/less/app.css → compile LESS sources and return CSS
  - /css/sass/theme.css → compile SASS sources and return CSS


## What happens in development vs production
- Development
  - JS: JsBuilderV2 serves the real source JS file with no caching.
  - CSS: CssBuilder streams @import of /devout/css/ paths; raw files are served with no caching.
  - LESS/SASS: compiles on-the-fly and returns CSS; no caching.

- Production
  - First request causes a build if the target file under PUBLIC_OUTPUT_DIR is missing; subsequent requests serve the built file with caching.
  - Minification/compression is applied when enabled in builder.json (or build mode dictates).


## Error handling and edge cases
- If a requested static file physically exists under the module’s public directory, FileUtils serves it directly.
- If the requested output is not found and builder.json does not define it, a DebugException is thrown (e.g., "Please add file in builder under section <file>").
- When a builder needs to read a file from another module, builder.json can specify a "module" for that file entry; otherwise, current request’s module is used.


## Notable caveats and suggestions
- JsBuilderV2::getBuilderFile() path in code currently includes spaces around the slash (" / "), which may not resolve correctly. If you rely on builder.json for JS in production with V2, ensure the path is correct in your project fork or override via configuration.
- CssBuilder::doBuild() exists but AbstractBuilder::build() is the normal code path used by streamFile(); consider aligning your project usage accordingly.
- A legacy rewrite in Dispatcher replaces js/ngs with js/admin/ngs for certain URLs; prefer explicit builder or route configuration if you need module-specific admin bundles.


## TL;DR
- RoutesResolver detects static asset URLs and returns an NgsFileRoute with module, fileUrl, and fileType.
- Dispatcher maps fileType to the proper Builder and streams the file.
- In dev, builders typically stream sources or compile on-the-fly (no cache).
- In prod, builders generate outputs under PUBLIC_OUTPUT_DIR and serve them with caching.
