import { createAppConfig } from '@nextcloud/vite-config'

export default createAppConfig({
    main: 'src/main.js',
    desktop: 'src/desktop.js',
    directeditor: 'src/directeditor.js',
    editor: 'src/editor.js',
    listener: 'src/listener.js',
    settings: 'src/settings.js',
    share: 'src/share.js',
    template: 'src/template.js',
    viewer: 'src/viewer.js',
})