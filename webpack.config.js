var path = require('path');

var defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
	...defaultConfig,
	entry: {
		'search-block': path.resolve(process.cwd(), `src/frontend.tsx`),
	},
	module: {
		rules: [
			{
				test: /\.[tjmc]sx?$/,
				use: ['babel-loader'],
				exclude: /node_modules/,
			},
		],
		...defaultConfig.module,
	},
};
