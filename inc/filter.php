<?php

/**
 * Post types to search in
 *
 * @param WP_Query $query The WordPress query object
 *
 * @return mixed|string[] The post-types to search in
 */
function ls_get_post_types( WP_Query $query ) {
    $post_types = esc_html($query->get( 'post_type' ) );
    if ( empty( $post_types ) ) {
        $post_types = array( 'product', 'post' );
    } elseif ( is_string( $post_types ) ) {
        $post_types = array( $post_types );
    }

    return $post_types;
}

/**
 * Returns the number of posts per page
 *
 * @param WP_Query $query The WordPress query object
 *
 * @return false|mixed|null The number of posts per page
 */
function ls_get_posts_per_page( WP_Query $query ) {
    $posts_per_page = $query->get( 'posts_per_page' );
    if ( empty( $posts_per_page ) || $posts_per_page == - 1 ) {
        $posts_per_page = get_option( 'posts_per_page', 10 );
    }

    return $posts_per_page;
}

/**
 * Enhance WordPress native search with advanced search functionality
 * This integrates your advanced_search_data() function with WordPress's built-in search
 */

/**
 * Filter to enhance WordPress search results with term-based search
 *
 * @param WP_Query $query The WordPress query object
 */
function ls_enhance_wordpress_search( WP_Query $query ) {
    // Only modify main search queries on frontend
    if ( ! is_admin() && $query->is_main_query() && $query->is_search() ) {

        $search_term = $query->get( 's' );
        if ( empty( $search_term ) ) {
            return;
        }

        // Get current post-types from a query, default to 'post' if not set
        $post_types = ls_get_post_types( $query );

        // Get posts per page setting
        $posts_per_page = ls_get_posts_per_page( $query );

        // advanced search data function
        $advanced_results = ls_advanced_search_data( $search_term, $post_types, $posts_per_page * 2 ); // Get more results to account for duplicates

        // If we have results from advanced search, merge them with the current query
        if ( $advanced_results['type'] !== 'none' ) {
            $additional_post_ids = array();

            if ( $advanced_results['type'] === 'posts' ) {
                // Direct post results
                foreach ( $advanced_results['results'] as $post ) {
                    $additional_post_ids[] = $post->ID;
                }
            } elseif ( $advanced_results['type'] === 'terms' ) {
                // Term-based results
                foreach ( $advanced_results['results'] as $term_result ) {
                    foreach ( $term_result['posts'] as $post ) {
                        $additional_post_ids[] = $post->ID;
                    }
                }
            }

            // Get existing post-IDs from the current query to avoid duplicates
            $existing_post_ids = array();
            if ( $query->posts ) {
                foreach ( $query->posts as $post ) {
                    $existing_post_ids[] = $post->ID;
                }
            }

            // Merge post IDs (remove duplicates)
            $all_post_ids = array_unique( array_merge( $existing_post_ids, $additional_post_ids ) );

            // If we have additional results, modify the query
            if ( ! empty( $all_post_ids ) ) {
                // Modify the query to include all found posts
                $query->set( 'post__in', $all_post_ids );
                $query->set( 'orderby', 'post__in' ); // Maintain relevance order

                // Remove the 's' parameter to avoid conflicts since we're now using post__in
                $query->set( 's', '' );

                // Store original search term for use in templates
                $query->set( 'original_search_term', $search_term );
            }
        }
    }
}

add_action( 'pre_get_posts', 'ls_enhance_wordpress_search' );

/**
 * Alternative approach: Filter search results after query execution
 * Use this if the above approach causes issues
 *
 * @param array $posts Array of post objects
 * @param WP_Query $query The WordPress query object
 *
 * @return array Modified array of posts
 */
function ls_enhance_search_results_posts( array $posts, WP_Query $query ): array {
    // Only modify main search queries on frontend
    if ( ! is_admin() && $query->is_main_query() && $query->is_search() ) {

        $search_term = $query->get( 's' );
        if ( empty( $search_term ) ) {
            return $posts;
        }

        // Get current post types
        $post_types = ls_get_post_types( $query );

        // Get posts per page setting
        $posts_per_page = ls_get_posts_per_page( $query );

        // Advanced search function
        $advanced_results = ls_advanced_search_data( $search_term, $post_types, $posts_per_page );

        // If native search returned no results, try advanced search
        if ( empty( $posts ) && $advanced_results['type'] !== 'none' ) {
            $additional_posts = array();

            if ( $advanced_results['type'] === 'posts' ) {
                $additional_posts = $advanced_results['results'];
            } elseif ( $advanced_results['type'] === 'terms' ) {
                foreach ( $advanced_results['results'] as $term_result ) {
                    $additional_posts = array_merge( $additional_posts, $term_result['posts'] );
                }
            }

            // Remove duplicates based on post ID
            $seen_ids     = array();
            $unique_posts = array();
            foreach ( $additional_posts as $post ) {
                if ( ! in_array( $post->ID, $seen_ids ) ) {
                    $unique_posts[] = $post;
                    $seen_ids[]     = $post->ID;
                }
            }

            return $unique_posts;
        }

        // If native search has results, optionally merge with advanced results
        if ( ! empty( $posts ) && $advanced_results['type'] !== 'none' ) {
            $existing_ids     = array_map( function ( $post ) { return $post->ID; }, $posts );
            $additional_posts = array();

            if ( $advanced_results['type'] === 'posts' ) {
                foreach ( $advanced_results['results'] as $post ) {
                    if ( ! in_array( $post->ID, $existing_ids ) ) {
                        $additional_posts[] = $post;
                    }
                }
            } elseif ( $advanced_results['type'] === 'terms' ) {
                foreach ( $advanced_results['results'] as $term_result ) {
                    foreach ( $term_result['posts'] as $post ) {
                        if ( ! in_array( $post->ID, $existing_ids ) ) {
                            $additional_posts[] = $post;
                        }
                    }
                }
            }

            // Merge and limit to posts_per_page
            $merged_posts = array_merge( $posts, $additional_posts );

            return array_slice( $merged_posts, 0, $posts_per_page );
        }
    }

    return $posts;
}

// Uncomment the line below if you want to use the alternative approach
// add_filter('posts_results', 'enhance_search_results_posts', 10, 2);

/**
 * Helper function to get the original search term when using the enhanced search
 * Use this in your search templates instead of get_search_query()
 *
 * @return string The search term
 */
function ls_get_enhanced_search_query(): string {
    global $wp_query;

    $original_term = $wp_query->get( 'original_search_term' );
    if ( ! empty( $original_term ) ) {
        return $original_term;
    }

    return get_search_query();
}

/**
 * Fix search result count when using enhanced search
 *
 * @param string $title The search results title
 * @param string $sep The separator
 *
 * @return string Modified title
 */
function ls_fix_enhanced_search_title( string $title, string $sep ): string {
    if ( is_search() ) {
        global $wp_query;
        $search_term = ls_get_enhanced_search_query();

        if ( ! empty( $search_term ) ) {
            $title = 'Search Results for "' . $search_term . '" ' . $sep . ' ' . get_bloginfo( 'name' );
        }
    }

    return $title;
}

add_filter( 'wp_title', 'ls_fix_enhanced_search_title', 10, 2 );

/**
 * Add support for WooCommerce product search
 * Uncomment if you want to enhance WooCommerce product search as well
 */
function ls_enhance_woocommerce_product_search( $query ) {
    if ( ! is_admin() && $query->is_main_query() ) {
        // Check if this is a WooCommerce product search
        $search_term = isset( $_GET['s'] ) && sanitize_text_field( wp_unslash($_GET['s'] ));
        $search_type = isset( $_GET['post_type'] ) ? sanitize_text_field(wp_unslash($_GET['post_type'])) : 'null';
        if ( $search_term && $search_type === 'product' ) {
            $advanced_results = ls_advanced_search_data( $search_type, array( 'product' ), 20 );

            if ( $advanced_results['type'] !== 'none' ) {
                $product_ids = array();

                if ( $advanced_results['type'] === 'posts' ) {
                    foreach ( $advanced_results['results'] as $post ) {
                        $product_ids[] = $post->ID;
                    }
                } elseif ( $advanced_results['type'] === 'terms' ) {
                    foreach ( $advanced_results['results'] as $term_result ) {
                        foreach ( $term_result['posts'] as $post ) {
                            $product_ids[] = $post->ID;
                        }
                    }
                }

                if ( ! empty( $product_ids ) ) {
                    $query->set( 'post__in', array_unique( $product_ids ) );
                    $query->set( 's', '' ); // Remove search term to avoid conflicts
                    $query->set( 'original_search_term', $search_type );
                }
            }
        }

    }
}

add_action( 'pre_get_posts', 'ls_enhance_woocommerce_product_search' );
