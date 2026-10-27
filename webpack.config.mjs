import Encore from '@symfony/webpack-encore';

Encore
    .setOutputPath('public/')
    .setPublicPath('/bundles/markocupicsaceventtool')
    .setManifestKeyPrefix('')

    .addEntry('stimulus_backend', './assets/stimulus_backend.js') // Register Stimulus controllers for the backend
    .addEntry('stimulus_frontend', './assets/stimulus_frontend.js') // Register Stimulus controllers for the frontend
    .addEntry('swisstopo_event_map', './assets/swisstopo_event_map.js') // Register the swisstopo map entry
    .addEntry('member_dashboard_my_event_registrations', './assets/member_dashboard_my_event_registrations.js') // Turbo (streams only) for the "load more" button in the member dashboard

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
      from: './assets/icons/event_states',
      to: 'icons/event_states/[path][name].[ext]',
    })
    .copyFiles({
      from: './assets/icons/fontawesome',
      to: 'icons/fontawesome/[path][name].[ext]',
    })
    .copyFiles({
      from: './assets/icons/maintenance',
      to: 'icons/maintenance/[path][name].[ext]',
    })
    .copyFiles({
      from: './assets/icons/my_events_dashboard',
      to: 'icons/my_events_dashboard/[path][name].[ext]',
    })
    .copyFiles({
      from: './assets/icons/subscription-states',
      to: 'icons/subscription-states/[path][name].[ext]',
    })
    .copyFiles({
      from: './assets/icons/tour_booklet',
      to: 'icons/tour_booklet/[path][name].[ext]',
    })
    .copyFiles({
      from: './assets/icons/swisstopo/tourtypes',
      to: 'icons/swisstopo/tourtypes/[path][name].[hash:8].[ext]',
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
      from: './node_modules/vue/dist',
      to: 'vue/dist/[path][name].[hash:8].[ext]',
      pattern: /(vue\.global\.prod\.js)$/,
    })
    .copyFiles({
      from: './assets/text_input_tokenizer',
      to: 'text_input_tokenizer/[path][name].[hash:8].[ext]',
    })

    // Typescripts
    .addEntry('js/avatar_uploader', './assets/ts/avatar_uploader.ts')
    .enableTypeScriptLoader()

    .disableSingleRuntimeChunk()
    .cleanupOutputBeforeBuild()
    .enableSourceMaps()
    .enableVersioning()

    // Polyfills: Babel 8 removed the options "useBuiltIns" and "corejs" from @babel/preset-env.
    // babel-plugin-polyfill-corejs3 with "usage-global" does the same as "useBuiltIns: 'usage', corejs: 3"
    // before. Version '3.0' keeps the previous set of polyfills (a newer version adds polyfills for newer
    // features and makes the bundles about 6-15% bigger).
    .configureBabel((babelConfig) => {
      babelConfig.plugins.push(['babel-plugin-polyfill-corejs3', {
        method: 'usage-global',
        version: '3.0',
      }]);
    })

    // Encore 7 no longer minifies CSS by default. cssnano is the closest to the previous default.
    .configureCssMinimizerPlugin((options, MinimizerPlugin) => {
      options.minify = MinimizerPlugin.cssnanoMinify;
    })

    .enablePostCssLoader()
    // Preprocessing SCSS to CSS
    .enableSassLoader()
    .enablePostCssLoader()
    .addStyleEntry('css/be_stylesheet', './assets/styles/backend/scss/backend_main.scss')
;

export default await Encore.getWebpackConfig();
