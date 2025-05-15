<?php

/**
 * Loads the plugin text domain for translation.
 */
function s_i18n() {
	load_plugin_textdomain( 'live-search-block', false, S_PATH . 'languages' );
}
add_action( 'init', 's_i18n' );
