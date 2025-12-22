const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
    ...defaultConfig,
    entry: {
        ...defaultConfig.entry(),
        frontend: path.resolve( process.cwd(), 'src', 'frontend.js' ),
    },
};
