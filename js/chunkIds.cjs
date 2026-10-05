/**
 * Flarum 2 keeps every extension's async chunks in ONE registry keyed by chunk
 * id, and webpack's default ids are small numbers, so two extensions can both
 * own chunk "767" and the second one's page dies with "n[e] is not a function".
 * Naming each chunk after the extension makes the ids unique, and a JSONP
 * global of its own stops another extension's runtime from installing these
 * modules over its own same-numbered ones.
 */
const id = require('../composer.json').name.replace('/', '-');

exports.extension = (config) => {
  config.optimization = { ...config.optimization, chunkIds: false };
  config.output = { ...config.output, chunkLoadingGlobal: `webpackChunk_${id.replace(/\W/g, '_')}` };
  config.plugins.push({
    apply: (compiler) =>
      compiler.hooks.compilation.tap('ExtensionChunkIds', (compilation) =>
        compilation.hooks.chunkIds.tap('ExtensionChunkIds', (chunks) => {
          for (const chunk of chunks) {
            if (chunk.id !== null) continue;
            chunk.id = `${id}:${chunk.name}`;
            chunk.ids = [chunk.id];
          }
        })
      ),
  });
  return config;
};
