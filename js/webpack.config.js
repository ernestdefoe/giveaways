const config = require('flarum-webpack-config');
const { extension } = require('./chunkIds.cjs');

module.exports = extension(config());
