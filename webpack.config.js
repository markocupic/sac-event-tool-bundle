const Encore = require('@symfony/webpack-encore');

Encore
    .setOutputPath('public/')
    .setPublicPath('/bundles/markocupicsaceventtool')
    .setManifestKeyPrefix('')

    .addEntry('backend', './assets/backend.js') // Register Stimulus controllers

    .copyFiles({
        from: './assets/css',
        to: 'css/[path][name].[hash:8].[ext]',
    })
    .copyFiles({
        from: './assets/sounds',
        to: 'sounds/[path][name].[hash:8].[ext]',
    })
    .copyFiles({
        from: './assets/eventfilter',
        to: 'eventfilter/[path][name].[hash:8].[ext]',
    })
    .copyFiles({
        from: './assets/eventlist',
        to: 'eventlist/[path][name].[hash:8].[ext]',
    })
    .copyFiles({
        from: './assets/js',
        to: 'js/[path][name].[hash:8].[ext]',
    })
    .copyFiles({
        from: './assets/icons',
        to: 'icons/[path][name].[ext]',
    })
    .copyFiles({
        from: './assets/images',
        to: 'images/[path][name].[ext]',
    })
    .copyFiles({
        from: './node_modules/choices.js/public/assets',
        to: 'choices.js/[path][name].[hash:8].[ext]',
        pattern: /(choices\.min\.js|choices\.min\.css)$/,
    })
    .copyFiles({
        from: './node_modules/dexie/dist',
        to: 'dexie/dist/[path][name].[hash:8].[ext]',
        pattern: /(dexie\.js)$/,
    })
    .copyFiles({
        from: './node_modules/vue/dist',
        to: 'vue/dist/[path][name].[hash:8].[ext]',
        pattern: /(vue\.global\.prod\.js)$/,
    })

    // Typescripts
    .addEntry('js/avatar_uploader', './assets/ts/avatar_uploader.ts')
    .enableTypeScriptLoader()

    .disableSingleRuntimeChunk()
    .cleanupOutputBeforeBuild()
    .enableSourceMaps()
    .enableVersioning()


    // enables @babel/preset-env polyfills
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = 3;
    })

    .enablePostCssLoader()
;

module.exports = Encore.getWebpackConfig();
