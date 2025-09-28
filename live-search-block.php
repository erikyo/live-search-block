<?php
/**
 * Plugin Name: Live Search Block
 * Plugin URI: https://github.com/wp-blocks/live-search-blockl
 * Description: WordPress block search in typescript
 * Version: 1.1.0
 * Author: codekraft, johnhooks
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: live-search-block
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Defines the path to the main plugin file.
define( 'S_FILE', __FILE__ );

// Defines the path to be used for includes.
define( 'S_PATH', plugin_dir_path( S_FILE ) );

// Defines the URL to the plugin.
define( 'S_URL', plugin_dir_url( S_FILE ) );

if ( ! defined( 'S_RESULTS_COUNT' ) ) {
	define( 'S_RESULTS_COUNT', 6 );
}

/**
 * Load the plugin translations file
 */
include_once S_PATH . 'inc/i18n.php';

/**
 * The core search functions
 */
include_once S_PATH . 'inc/core.php';

/**
 * The search filters
 */
include_once S_PATH . 'inc/filter.php';

/**
 * The rest api that will handle the search request
 */
include_once S_PATH . 'inc/enqueue.php';

/**
* The block variation
 */
include_once S_PATH . 'inc/variation.php';
add_action( 'init', 'register_search_block_variation' );

/**
* The modal window
 */
include_once S_PATH . 'inc/modal.php';
add_action( 'wp_footer', 'live_search_modal_window' );

/**
* The rest api controller
 */
include_once S_PATH . 'inc/rest.php';
add_action( 'rest_api_init', 'register_live_search_endpoint' );
