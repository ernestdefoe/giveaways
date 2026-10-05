const config = require('flarum-webpack-config');

const NS = 'ernestdefoe-giveaways';

/*
 * Lazy chunks need ids no other extension can have.
 *
 * Flarum's registry finds a chunk's URL by its id alone, and every extension's
 * chunks report to one shared `webpackChunkmodule_exports` array. Webpack's
 * default ids are 3-digit hashes of the chunk's path, so two extensions can
 * share one (on the demo this extension's chunk 767 was flarum-audit's): the
 * wrong file is fetched, or the chunk counts as already loaded, and the page or
 * modal dies with "n[e] is not a function". So: ids prefixed with our extension
 * id, and our own chunk-loading global.
 */
class NamespacedChunkIds {
  apply(compiler) {
    compiler.hooks.compilation.tap('NamespacedChunkIds', (compilation) => {
      compilation.hooks.chunkIds.tap('NamespacedChunkIds', (chunks) => {
        for (const chunk of chunks) {
          if (chunk.id === null) {
            chunk.id = `${NS}:${chunk.name || chunk.debugId}`;
            chunk.ids = [chunk.id];
          }
        }
      });
    });
  }
}

module.exports = (...args) => {
  let base = config();
  if (typeof base === 'function') base = base(...args);

  base.optimization = { ...base.optimization, chunkIds: false };
  base.output = { ...base.output, chunkLoadingGlobal: 'webpackChunk_ernestdefoe_giveaways' };
  base.plugins = [...(base.plugins || []), new NamespacedChunkIds()];

  return base;
};
