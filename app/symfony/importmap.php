<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    '@hotwired/turbo' => [
        'version' => '7.3.0',
    ],
    'bootstrap' => [
        'version' => '5.3.8',
    ],
    '@popperjs/core' => [
        'version' => '2.11.8',
    ],
    'bootstrap/dist/css/bootstrap.min.css' => [
        'version' => '5.3.8',
        'type' => 'css',
    ],
    'chart.js' => [
        'version' => '4.4.1',
    ],
    'chart.js/auto' => [
        'version' => '4.4.1',
    ],
    'gulp' => [
        'version' => '3.8.7',
    ],
    'gulp-kss' => [
        'version' => '0.0.2',
    ],
    'gulp-ruby-sass' => [
        'version' => '0.7.1',
    ],
    'gulp-watch' => [
        'version' => '0.6.9',
    ],
    'gulp-connect' => [
        'version' => '2.0.6',
    ],
    'gulp-rename' => [
        'version' => '1.2.0',
    ],
    'gulp-coffee' => [
        'version' => '2.1.2',
    ],
    'gulp-shell' => [
        'version' => '0.2.9',
    ],
    'gulp-minify-css' => [
        'version' => '0.3.7',
    ],
    'gulp-uglify' => [
        'version' => '0.3.2',
    ],
    'gulp-jade' => [
        'version' => '0.7.0',
    ],
    'gulp-insert' => [
        'version' => '0.4.0',
    ],
    'gulp-file-insert' => [
        'version' => '1.0.2',
    ],
    'gulp-sourcemaps' => [
        'version' => '1.1.3',
    ],
    'orchestrator' => [
        'version' => '0.3.7',
    ],
    'gulp-util' => [
        'version' => '2.2.20',
    ],
    'deprecated' => [
        'version' => '0.0.1',
    ],
    'vinyl-fs' => [
        'version' => '0.3.4',
    ],
    'gulp-batch' => [
        'version' => '0.4.1',
    ],
    'vinyl' => [
        'version' => '0.3.0',
    ],
    'gaze' => [
        'version' => '0.5.1',
    ],
    'minimatch' => [
        'version' => '0.4.0',
    ],
    'glob2base' => [
        'version' => '0.0.10',
    ],
    'glob' => [
        'version' => '4.0.4',
    ],
    'through2' => [
        'version' => '0.5.1',
    ],
    'coffee-script' => [
        'version' => '1.7.1',
    ],
    'vinyl-sourcemaps-apply' => [
        'version' => '0.1.1',
    ],
    'merge' => [
        'version' => '1.1.3',
    ],
    'clean-css' => [
        'version' => '2.2.8',
    ],
    'bufferstreams' => [
        'version' => '0.0.1',
    ],
    'memory-cache' => [
        'version' => '0.0.5',
    ],
    'uglify-js' => [
        'version' => '2.4.6',
    ],
    'deepmerge' => [
        'version' => '0.2.7',
    ],
    'gulp-util/lib/PluginError' => [
        'version' => '3.0.0',
    ],
    'jade' => [
        'version' => '1.5.0',
    ],
    'readable-stream' => [
        'version' => '1.1.12',
    ],
    'streamqueue' => [
        'version' => '0.0.6',
    ],
    'end-of-stream' => [
        'version' => '0.1.5',
    ],
    'stream-consume' => [
        'version' => '0.1.0',
    ],
    'sequencify' => [
        'version' => '0.0.7',
    ],
    'chalk' => [
        'version' => '0.5.1',
    ],
    'dateformat' => [
        'version' => '1.0.7-1.2.3',
    ],
    'lodash.template' => [
        'version' => '2.4.1',
    ],
    'lodash._reinterpolate' => [
        'version' => '2.4.1',
    ],
    'minimist' => [
        'version' => '0.2.0',
    ],
    'multipipe' => [
        'version' => '0.1.1',
    ],
    'lodash' => [
        'version' => '2.4.1',
    ],
    'glob-stream' => [
        'version' => '3.1.12',
    ],
    'graceful-fs' => [
        'version' => '3.0.2',
    ],
    'strip-bom' => [
        'version' => '0.3.1',
    ],
    'mkdirp' => [
        'version' => '0.5.0',
    ],
    'glob-watcher' => [
        'version' => '0.0.6',
    ],
    'event-stream' => [
        'version' => '3.1.0',
    ],
    'lodash.defaults' => [
        'version' => '2.4.1',
    ],
    'clone-stats' => [
        'version' => '0.0.1',
    ],
    'lodash.clonedeep' => [
        'version' => '2.4.1',
    ],
    'lodash.findindex' => [
        'version' => '2.4.1',
    ],
    'readable-stream/transform' => [
        'version' => '1.0.26',
    ],
    'xtend' => [
        'version' => '3.0.0',
    ],
    'source-map' => [
        'version' => '0.1.33',
    ],
    'isarray' => [
        'version' => '0.0.1',
    ],
    'core-util-is' => [
        'version' => '1.0.1',
    ],
    'inherits' => [
        'version' => '2.0.1',
    ],
    'string_decoder' => [
        'version' => '0.10.25',
    ],
    'once' => [
        'version' => '1.3.0',
    ],
    'escape-string-regexp' => [
        'version' => '1.0.0',
    ],
    'ansi-styles' => [
        'version' => '1.1.0',
    ],
    'strip-ansi' => [
        'version' => '0.3.0',
    ],
    'has-ansi' => [
        'version' => '0.1.0',
    ],
    'supports-color' => [
        'version' => '0.2.0',
    ],
    'lodash.escape' => [
        'version' => '2.4.1',
    ],
    'lodash._escapestringchar' => [
        'version' => '2.4.1',
    ],
    'lodash.keys' => [
        'version' => '2.4.1',
    ],
    'lodash.templatesettings' => [
        'version' => '2.4.1',
    ],
    'lodash.values' => [
        'version' => '2.4.1',
    ],
    'duplexer2' => [
        'version' => '0.0.2',
    ],
    'ordered-read-streams' => [
        'version' => '0.0.7',
    ],
    'unique-stream' => [
        'version' => '1.0.0',
    ],
    'is-utf8' => [
        'version' => '0.2.0',
    ],
    'first-chunk-stream' => [
        'version' => '0.1.0',
    ],
    'through' => [
        'version' => '2.3.4',
    ],
    'from' => [
        'version' => '0.1.3',
    ],
    'duplexer' => [
        'version' => '0.1.1',
    ],
    'map-stream' => [
        'version' => '0.1.0',
    ],
    'pause-stream' => [
        'version' => '0.0.11',
    ],
    'split' => [
        'version' => '0.2.10',
    ],
    'stream-combiner' => [
        'version' => '0.0.4',
    ],
    'lodash._objecttypes' => [
        'version' => '2.4.1',
    ],
    'lodash._baseclone' => [
        'version' => '2.4.1',
    ],
    'lodash._basecreatecallback' => [
        'version' => '2.4.1',
    ],
    'lodash.createcallback' => [
        'version' => '2.4.1',
    ],
    'amdefine' => [
        'version' => '0.1.0',
    ],
    'ansi-regex' => [
        'version' => '0.2.0',
    ],
    'lodash._escapehtmlchar' => [
        'version' => '2.4.1',
    ],
    'lodash._reunescapedhtml' => [
        'version' => '2.4.1',
    ],
    'lodash._isnative' => [
        'version' => '2.4.1',
    ],
    'lodash.isobject' => [
        'version' => '2.4.1',
    ],
    'lodash._shimkeys' => [
        'version' => '2.4.1',
    ],
    'lodash.assign' => [
        'version' => '2.4.1',
    ],
    'lodash.foreach' => [
        'version' => '2.4.1',
    ],
    'lodash.forown' => [
        'version' => '2.4.1',
    ],
    'lodash._getarray' => [
        'version' => '2.4.1',
    ],
    'lodash.isarray' => [
        'version' => '2.4.1',
    ],
    'lodash._releasearray' => [
        'version' => '2.4.1',
    ],
    'lodash._slice' => [
        'version' => '2.4.1',
    ],
    'lodash.bind' => [
        'version' => '2.4.1',
    ],
    'lodash.identity' => [
        'version' => '2.4.1',
    ],
    'lodash._setbinddata' => [
        'version' => '2.4.1',
    ],
    'lodash.support' => [
        'version' => '2.4.1',
    ],
    'lodash._baseisequal' => [
        'version' => '2.4.1',
    ],
    'lodash.property' => [
        'version' => '2.4.1',
    ],
    'lodash._htmlescapes' => [
        'version' => '2.4.1',
    ],
    'lodash._arraypool' => [
        'version' => '2.4.1',
    ],
    'lodash._maxpoolsize' => [
        'version' => '2.4.1',
    ],
    'lodash._createwrapper' => [
        'version' => '2.4.1',
    ],
    'lodash.noop' => [
        'version' => '2.4.1',
    ],
    'lodash.forin' => [
        'version' => '2.4.1',
    ],
    'lodash.isfunction' => [
        'version' => '2.4.1',
    ],
    'lodash._basebind' => [
        'version' => '2.4.1',
    ],
    'lodash._basecreatewrapper' => [
        'version' => '2.4.1',
    ],
    'lodash._basecreate' => [
        'version' => '2.4.1',
    ],
];
