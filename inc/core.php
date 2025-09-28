<?php
/**
 * Performs advanced search and returns structured data array.
 *
 * @param string $query_string The search query.
 * @param array $post_type The post-types to search into.
 * @param int $result_count The number of results to return.
 *
 * @return array Array containing search results with structure:
 *               [
 *                   'type' => 'posts'|'terms'|'none',
 *                   'results' => array of posts or term results,
 *                   'query_string' => original search query
 *               ]
 */
function ls_advanced_search_data( string $query_string, array $post_type, int $result_count): array {
    $search_result = array(
        'type' => 'none',
        'results' => array(),
        'query_string' => $query_string
    );

    // First, search directly in posts
    $args = array(
        'post_type'      => $post_type,
        's'              => $query_string,
        'posts_per_page' => $result_count,
    );
    $query = new WP_Query($args);

    if ($query->have_posts()) {
        $search_result['type'] = 'posts';
        $search_result['results'] = $query->posts;
        wp_reset_postdata();
        return $search_result;
    }

    // No posts found, search in terms (product attributes, categories, tags)
    $term_results = array();

    // Define taxonomies to search based on post-types
    $taxonomies_to_search = array();
    if (in_array('product', $post_type)) {
        // WooCommerce product attributes
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
                    'posts_per_page' => 0, // No limits
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

    if (!empty($term_results)) {
        $search_result['type'] = 'terms';
        $search_result['results'] = $term_results;
    }

    return $search_result;
}

/**
 * Generates HTML output from search results data.
 *
 * @param array $search_data The search results data from advanced_search_data().
 *
 * @return string The search results HTML.
 */
function ls_advanced_search_html( array $search_data): string {
    if ($search_data['type'] === 'none') {
        return '<div class="no-search-results">No results found for "' . esc_html($search_data['query_string']) . '"</div>';
    }

    ob_start();

    if ($search_data['type'] === 'posts') {
        // Display direct post results
        foreach ($search_data['results'] as $post) {
            setup_postdata($post);

            $image_src = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'thumbnail')[0] ?? '';

            printf(
                '<div class="search-result-post search-post-%s"><a href="%s"><img src="%s" alt="%s"/></a><a href="%s"><h4>%s</h4><p>%s</p></a></div>',
                esc_attr($post->ID),
                esc_url(get_permalink($post->ID)),
                esc_url($image_src),
                esc_attr(get_the_title($post->ID)),
                esc_url(get_permalink($post->ID)),
                esc_html(get_the_title($post->ID)),
                esc_html(substr(get_the_excerpt($post->ID) ?? '', 0, 100))
            );
        }
    } elseif ($search_data['type'] === 'terms') {
        // Display term-based results
        foreach ($search_data['results'] as $term_result) {
            $term = $term_result['term'];
            $taxonomy = $term_result['taxonomy'];
            $posts = $term_result['posts'];

            // Display term header
            printf('<div class="search-result-term-group">');

            // Display posts under this term
            foreach ($posts as $post) {
                setup_postdata($post);

                $post_id = $post->ID; // Use $post->ID directly for readability/standard WP functions
                $image_src = wp_get_attachment_image_src(get_post_thumbnail_id($post_id), 'thumbnail')[0] ?? '';

                printf(
                    '<div class="search-result-post search-post-%s term-result"><a href="%s"><img src="%s" alt="%s"/></a><a href="%s"><h4>%s</h4><p>%s</p></a></div>',
                    esc_attr($post_id),
                    esc_url(get_permalink($post_id)),
                    esc_url($image_src),
                    esc_attr(get_the_title($post_id)),
                    esc_url(get_permalink($post_id)),
                    esc_html(get_the_title($post_id)),
                    esc_html(substr(get_the_excerpt($post_id) ?? '', 0, 100))
                );
            }

            printf('</div>');
        }
    }

    $results = ob_get_clean();
    wp_reset_postdata();

    return $results;
}
