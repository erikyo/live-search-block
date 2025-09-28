<?php

/**
 * Registers a live search endpoint for the plugin.
 */
function register_live_search_endpoint() {
	register_rest_route(
		'vsge/v2',
		'/live-search',
		array(
			'methods'             => 'POST',
			'callback'            => 's_live_search',
			'permission_callback' => '__return_true',
			'args'                => array(
				'query' => array(
					'required' => true,
					'type'     => 'string',
					'validate_callback' => function( $value ) {
						return is_string( $value );
					}
				),
				'length' => array(
					'required' => false,
					'type'     => 'number',
					'default'  => 4,
					'validate_callback' => function( $value ) {
						return is_numeric( $value );
					}
				),
                'post_type' => array(
                    'required' => false,
                    'type'     => 'string',
                    'default'  => 'product,post',
                    'validate_callback' => function( $value ) {
                        return is_string( $value );
                    }
                )
			)
		)
	);
}

/**
 * Generates search results for a live search query.
 * The function performs a search on posts, products, or custom post types based on the provided query.
 * if the search is successful, it returns the search results already rendered in HTML format.
 * if the search fails, it searches into attributes and returns the search results already rendered in HTML format.
 * if the search fails again, it returns an error message.
 *
 * @param WP_REST_Request $request The REST request object containing the search query.
 * @throws Exception If an error occurs during the search process.
 * @return WP_REST_Response The search results in JSON format.
 */
function s_live_search( WP_REST_Request $request ) {
    $query_string = sanitize_text_field( $request->get_param( 'query' ) );
    $result_count = intval( $request->get_param( 'count'  ) ) ?? S_RESULTS_COUNT;
    $post_type_raw = strval( $request->get_param( 'post_type'  ) ) ?? 'product,post';
    $post_type_raw = explode(',', $post_type_raw);

    // now filter all the post-types that are not in the allowed list
    $allowed_post_types = array('product', 'post');
    $post_type = array_intersect($post_type_raw, $allowed_post_types);

    // Generate a unique key for the transient based on the search parameters
    $cache_key = 'custom_search_' . md5( $query_string );

    // Try to retrieve the search results from the transient cache
    $results = get_transient( $cache_key );

    if ( false === $results ) {
        $search_data = ls_advanced_search_data( $query_string, $post_type, $result_count);
        $results = ls_advanced_search_html($search_data);
    }

    // Save the search results to the transient cache for 1 minute (60 seconds)
    set_transient( $cache_key, $results, 60 );

    // Return the results as JSON
    return rest_ensure_response($results );
}
