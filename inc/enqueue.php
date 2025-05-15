<?php
add_action(
	'init',
	function () {
		if ( is_admin() ) {
			return;
		}
		$asset = include S_PATH . 'build/search-block.asset.php';
		wp_enqueue_style( 'live-search-block-script', S_URL . 'build/style-search-block.css' );
		wp_enqueue_script( 'live-search-block-script', S_URL . 'build/search-block.js', $asset['dependencies'], $asset['version'], true );

		// add to the script some custom vars
		wp_localize_script( 'live-search-block-script', 'liveSearchBlock', array( "resultCount" => S_RESULTS_COUNT, "formRedirectUrl" => get_home_url() ) );

		// load the translation file for the block
		wp_set_script_translations( 'live-search-block-script', 'live-search-block', S_PATH . 'languages' );
	}
);
