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

    // now filter all the post types that are not in the allowed list
    $allowed_post_types = array('product', 'post');
    $post_type = array_intersect($post_type_raw, $allowed_post_types);

    // Generate a unique key for the transient based on the search parameters
    $cache_key = 'custom_search_' . md5( $query_string );

    // Try to retrieve the search results from the transient cache
    $results = get_transient( $cache_key );

    if ( false === $results ) {

        // Perform your search logic and fetch data
        $args  = array(
            'post_type'      => $post_type,
            's'              => $query_string,
            'posts_per_page' => $result_count,
        );
        $query = new WP_Query( $args );

        if ( $query->have_posts() ) {
            ob_start();
            while ( $query->have_posts() ) {
                $query->the_post();

                global $post;

                printf( '<div class="search-result-post search-post-%s"><a href="%s"><img src="%s"/></a><a href="%s"><h4>%s</h4><p>%s</p></a></span></div>',
                    $post->ID,
                    get_permalink(),
                    wp_get_attachment_image_src( get_post_thumbnail_id($post->ID), 'thumbnail' )[0],
                    get_permalink(),
                    get_the_title(),
                    substr( get_the_excerpt() ?? '' , 0, 100)
                );

            }
            $results = ob_get_clean();
            wp_reset_postdata();
        } else {
            // No posts found, search in terms (product attributes, categories, tags)
            $term_results = array();

            // Define taxonomies to search based on post types
            $taxonomies_to_search = array();
            if (in_array('product', $post_type)) {
                // WooCommerce product attributes (adjust these based on your attributes)
                $product_attributes = wc_get_attribute_taxonomies();
                foreach ($product_attributes as $attribute) {
                    $taxonomies_to_search[] = 'pa_' . $attribute->attribute_name;
                }
                // Add product categories and tags
                $taxonomies_to_search[] = 'product_cat';
                $taxonomies_to_search[] = 'product_tag';
            }
            if (in_array('post', $post_type)) {
                $taxonomies_to_search[] = 'category';
                $taxonomies_to_search[] = 'post_tag';
            }

            // Search through terms
            foreach ($taxonomies_to_search as $taxonomy) {
                $term_args = array(
                    'taxonomy'   => $taxonomy,
                    'hide_empty' => true,
                    'name__like' => $query_string,
                    'number'     => $result_count,
                );

                $terms = get_terms($term_args);

                if (!is_wp_error($terms) && !empty($terms)) {
                    foreach ($terms as $term) {
                        // Get posts associated with this term
                        $term_post_args = array(
                            'post_type'      => $post_type,
                            'posts_per_page' => 3, // Limit posts per term
                            'tax_query'      => array(
                                array(
                                    'taxonomy' => $taxonomy,
                                    'field'    => 'term_id',
                                    'terms'    => $term->term_id,
                                ),
                            ),
                        );

                        $term_query = new WP_Query($term_post_args);

                        if ($term_query->have_posts()) {
                            $term_results[] = array(
                                'term' => $term,
                                'taxonomy' => $taxonomy,
                                'posts' => $term_query->posts
                            );
                        }

                        wp_reset_postdata();
                    }
                }
            }

            // Generate HTML for term results
            if (!empty($term_results)) {
                ob_start();

                foreach ($term_results as $term_result) {
                    $term = $term_result['term'];
                    $taxonomy = $term_result['taxonomy'];
                    $posts = $term_result['posts'];

                    // Display term header
                    printf('<div class="search-result-term-group">');

                    // Display posts under this term
                    foreach ($posts as $post) {
                        setup_postdata($post);

                        printf( '<div class="search-result-post search-post-%s term-result"><a href="%s"><img src="%s"/></a><a href="%s"><h4>%s</h4><p>%s</p></a></div>',
                            $post->ID,
                            get_permalink($post->ID),
                            wp_get_attachment_image_src( get_post_thumbnail_id($post->ID), 'thumbnail' )[0] ?? '',
                            get_permalink($post->ID),
                            get_the_title($post->ID),
                            substr( get_the_excerpt($post->ID) ?? '' , 0, 100)
                        );
                    }

                    printf('</div>');
                }

                $results = ob_get_clean();
                wp_reset_postdata();
            } else {
                $results = '<div class="no-search-results">No results found for "' . esc_html($query_string) . '"</div>';
            }
        }
    }

    // Save the search results to the transient cache for 1 minute (60 seconds)
    set_transient( $cache_key, $results, 60 );

    // Return the results as JSON
    return rest_ensure_response($results );
}
