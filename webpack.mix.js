const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel applications. By default, we are compiling the CSS
 | file for the application as well as bundling up all the JS files.
 |
 */

mix.js('resources/assets/js/app.js', 'public/js/app.js')
   .sass('resources/assets/sass/app.scss', 'public/css/app.css')
   .version();

var path = require('path');
var webpack = require('webpack');

module.exports = {
   // output: {
   //   filename: 'my-first-webpack.bundle.js'
   // },
   module: {
     rules: [
       { test: /\.vue$/, use: 'vue-loader' }
     ]
   }
 };

// module.exports = {
//   entry: './src/main.js',
//   output: {
//     path: path.resolve(__dirname, './dist'),
//     publicPath: '/dist/',
//     filename: 'build.js'
//   },
//   resolve: {
//     alias: {
//       'vue$': 'vue/dist/vue.esm.js'
//     },
//     extensions: ['*', '.js', '.vue', '.json']
//   }
// };

// module.exports = (env) => {
//    const isDevBuild = !(env && env.prod);
//    return [{
//       stats: { modules: false },
//       context: __dirname,
//       resolve: { extensions: [ '.js', '.ts' ] },
//       entry: { 'main': './src/main.ts' },
//       module: {
//          rules: [
//             { test: /\.vue$/, include: /src/, loader: 'vue-loader', options: { loaders: { js: 'awesome-typescript-loader?silent=true' } } },
//             { test: /\.ts$/, include: /src/, use: 'awesome-typescript-loader?silent=true' },
//             { test: /\.css$/, use: isDevBuild ? [ 'style-loader', 'css-loader' ] : ExtractTextPlugin.extract({ use: 'css-loader?minimize' }) },
//             { test: /\.(png|jpg|jpeg|gif|svg)$/, use: 'url-loader?limit=25000' }
//          ]
//       },
//       output: {
//          path: path.join(__dirname, bundleOutputDir),
//          filename: '[name].js',
//          publicPath: 'dist/'
//       },
//       plugins: [
//          new CheckerPlugin(),
//          new webpack.DefinePlugin({
//             'process.env': {
//                   NODE_ENV: JSON.stringify(isDevBuild ? 'development' : 'production')
//             }
//          }),
//          new webpack.DllReferencePlugin({
//             context: __dirname,
//             manifest: require('./wwwroot/dist/vendor-manifest.json')
//          })
//       ].concat(isDevBuild ? [
//          // Plugins that apply in development builds only
//          new webpack.SourceMapDevToolPlugin({
//             filename: '[file].map', // Remove this line if you prefer inline source maps
//             moduleFilenameTemplate: path.relative(bundleOutputDir, '[resourcePath]') // Point sourcemap entries to the original file locations on disk
//          })
//       ] : [
//          // Plugins that apply in production builds only
//          new webpack.optimize.UglifyJsPlugin(),
//          new ExtractTextPlugin('site.css')
//       ])
//    }];
// };


// if (process.env.NODE_ENV === 'production') {
//    module.exports.devtool = '#source-map'
//    // http://vue-loader.vuejs.org/en/workflow/production.html
//    module.exports.plugins = (module.exports.plugins || []).concat([
//      new webpack.DefinePlugin({
//        'process.env': {
//          NODE_ENV: '"production"'
//        }
//      }),
//      new webpack.optimize.UglifyJsPlugin({
//        sourceMap: true,
//        compress: {
//          warnings: false
//        }
//      }),
//      new webpack.LoaderOptionsPlugin({
//        minimize: true
//      })
//    ])
//  }