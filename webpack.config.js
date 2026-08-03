const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		'admin-settings': './src/admin-settings/index.js',
		frontend: './src/frontend/index.js',
	},
};
