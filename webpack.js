const path = require('path');
const webpackConfig = require('@nextcloud/webpack-vue-config');

webpackConfig.entry = {
    main: [path.join(__dirname, 'src', 'template.js'), path.join(__dirname, 'src', 'main.js')],
    desktop: path.join(__dirname, 'src', 'desktop.js'),
    directeditor: path.join(__dirname, 'src', 'directeditor.js'),
    editor: path.join(__dirname, 'src', 'editor.js'),
    listener: path.join(__dirname, 'src', 'listener.js'),
    settings: path.join(__dirname, 'src', 'settings.js'),
    share: path.join(__dirname, 'src', 'share.js'),
    template: path.join(__dirname, 'src', 'template.js'),
    viewer: path.join(__dirname, 'src', 'viewer.js')
};

module.exports = webpackConfig;