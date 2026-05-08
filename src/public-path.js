/**
 * Add this file to the entry point of your webpack configuration:
 *  entry: ['./public-path.js', './entry.js']
 * Else:
 *  entry: {
 *      'script-name': ['./public-path.js', './entry.js'],
 *  }
 *
 * @link https://github.com/webpack/webpack/issues/2776#issuecomment-233808146
 */
if (window.__webpack_public_path__) {
    // __webpack_public_path__ = '/wp-content/plugins/arnpo/wp-utils/dist/';
    __webpack_public_path__ = window.__webpack_public_path__;
    console.debug('Set __webpack_public_path__ to', __webpack_public_path__);
}
