<?php
/**
 * Plugin Name:       JE Request Block
 * Plugin URI:        https://jeplugins.github.io/request-block
 * Description:       Collect and manage feature requests with voting system.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            JE Plugins
 * Author URI:        https://jeplugins.github.io
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       je-request-block
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'JE_REQUEST_BLOCK_VERSION', '1.0.0' );
define( 'JE_REQUEST_BLOCK_PATH', plugin_dir_path( __FILE__ ) );
define( 'JE_REQUEST_BLOCK_URL', plugin_dir_url( __FILE__ ) );

// Include required files
require_once JE_REQUEST_BLOCK_PATH . 'includes/class-request-cpt.php';
require_once JE_REQUEST_BLOCK_PATH . 'includes/class-rest-api.php';

/**
 * Initialize the plugin
 */
function je_request_block_init() {
    // Register Custom Post Type
    JE_Request_CPT::register_post_type();
    JE_Request_CPT::register_meta();
    
    // Register block
    register_block_type( JE_REQUEST_BLOCK_PATH . 'build/request-board' );
}
add_action( 'init', 'je_request_block_init' );

/**
 * Register REST API routes
 */
function je_request_block_rest_init() {
    JE_Request_REST_API::register_routes();
}
add_action( 'rest_api_init', 'je_request_block_rest_init' );

/**
 * Enqueue frontend scripts
 */
function je_request_block_frontend_scripts() {
    if ( has_block( 'je-request/board' ) ) {
        wp_enqueue_script(
            'je-request-frontend',
            JE_REQUEST_BLOCK_URL . 'build/frontend.js',
            array(),
            JE_REQUEST_BLOCK_VERSION,
            true
        );
        
        wp_localize_script( 'je-request-frontend', 'jeRequestData', array(
            'restUrl' => rest_url( 'je-request/v1/' ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
        ) );
    }
}
add_action( 'wp_enqueue_scripts', 'je_request_block_frontend_scripts' );

/**
 * Plugin activation
 */
function je_request_block_activate() {
    JE_Request_CPT::register_post_type();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'je_request_block_activate' );

/**
 * Plugin deactivation
 */
function je_request_block_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'je_request_block_deactivate' );
