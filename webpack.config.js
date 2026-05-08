// https://github.com/microsoft/monaco-editor/blob/main/docs/integrate-esm.md
import MonacoWebpackPlugin from 'monaco-editor-webpack-plugin';
import url from 'url';
import path from 'path';

const __filename = url.fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);


export default {
    mode: process.env.NODE_ENV,
    entry: {
        'monaco-yaml-editor': [
            './src/public-path.js',  // Order is important.
            './src/monaco-yaml-editor.js',
        ],
    },
    // https://stackoverflow.com/a/75142079
    experiments: {
        outputModule: true,
    },
    output: {
        globalObject: 'self',
        clean: true,
        publicPath: '',
        path: path.resolve(__dirname, 'dist'),
        filename: '[name].js',
        libraryTarget: 'module',
    },
    module: {
        rules: [
            {
                test: /\.css$/,
                use: ['style-loader', 'css-loader'],
            },
            {
                test: /\.ttf$/,
                type: 'asset',
                parser: {
                    dataUrlCondition: {
                        maxSize: 4 * 1024,
                    }
                },
            },
        ],
    },
    plugins: [
        // https://github.com/microsoft/monaco-editor/tree/main/webpack-plugin
        new MonacoWebpackPlugin({
            languages: [
                'typescript',
                'javascript',
                'css',
                'yaml',
                'php',
            ],
            customLanguages: [
                // https://github.com/remcohaszing/monaco-yaml?tab=readme-ov-file#using-monaco-webpack-loader-plugin
                {
                    label: 'yaml',
                    entry: 'monaco-yaml',
                    worker: {
                        id: 'monaco-yaml/yamlWorker',
                        entry: 'monaco-yaml/yaml.worker'
                    },
                },
            ],
        }),
    ],
};
