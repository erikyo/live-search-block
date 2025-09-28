# Advanced Search for WordPress

A powerful WordPress plugin that enhances search functionality by searching through posts, custom post types, and taxonomies (categories, tags, and custom taxonomies including WooCommerce product attributes).

## Features

- **Enhanced Search Logic**: Searches in posts content and taxonomy terms
- **WooCommerce Support**: Includes product attributes, categories, and tags in search
- **Modular Design**: Separate functions for search logic and HTML generation
- **WordPress Integration**: Automatically enhances native WordPress search
- **Plugin Compatibility**: Safe integration with existing search plugins

## How It Works

The plugin uses a two-step approach:

1. **Direct Search**: First searches directly in post content using WordPress native search
2. **Taxonomy Search**: If no results found, searches in taxonomy terms (categories, tags, product attributes) and returns associated posts

### Supported Taxonomies

- **Posts**: Categories and tags
- **WooCommerce Products**: Product categories, tags, and custom attributes
- **Custom Post Types**: Any registered taxonomies

## Installation

1. Download or clone this repository
2. Copy the files to your WordPress theme's `functions.php` or create a custom plugin
3. The enhanced search will automatically integrate with WordPress native search

## Usage

### Basic Implementation

The plugin provides three main functions:

#### 1. Get Search Data (Raw Results)
```php
// Get structured search results without HTML
$search_data = advanced_search_data('search term', array('post', 'product'), 10);

// Returns array with:
// - type: 'posts', 'terms', or 'none'
// - results: array of posts or term results
// - query_string: original search term
```

#### 2. Generate HTML from Results
```php
// Generate HTML from search data
$html_output = advanced_search_html($search_data);
```

#### 3. Complete Search (Backward Compatible)
```php
// Original function - returns HTML directly
$html_results = advanced_search('search term', array('post', 'product'), 10);
```

### WordPress Native Search Integration

The plugin automatically enhances WordPress's built-in search (`/?s=search-term`) without requiring any template modifications.

For search templates, use:
```php
// In your search.php template, replace:
echo get_search_query();

// With:
echo get_enhanced_search_query();
```

### Custom Implementation Example

```php
// Custom search form handler
function handle_custom_search() {
    $search_term = sanitize_text_field($_GET['search']);
    $post_types = array('post', 'product', 'custom_post_type');
    
    // Get raw search data
    $search_data = advanced_search_data($search_term, $post_types, 20);
    
    if ($search_data['type'] === 'posts') {
        // Handle direct post results
        foreach ($search_data['results'] as $post) {
            // Process posts
        }
    } elseif ($search_data['type'] === 'terms') {
        // Handle taxonomy-based results
        foreach ($search_data['results'] as $term_result) {
            // Process term results
        }
    }
    
    // Generate HTML when needed
    $html = advanced_search_html($search_data);
}
```

## Plugin Compatibility

The plugin is designed to work alongside other search plugins:

- **Live Search Block**: Automatically detects and avoids conflicts
- **WooCommerce**: Native support for product searches
- **Custom Search Plugins**: Uses safe hooks to minimize conflicts

## Configuration

### Customize Post Types
```php
// Search in specific post types
$results = advanced_search_data('term', array('post', 'page', 'product'), 15);
```

### Modify HTML Output
```php
// Customize the HTML generation
function custom_search_html($search_data) {
    // Your custom HTML generation logic
    return advanced_search_html($search_data);
}
```

### Disable Native Search Integration
```php
// Remove the automatic WordPress search enhancement
remove_filter('posts_results', 'enhance_search_results_posts', 20, 2);
```

## Search Result Types

### Direct Post Results
When posts are found directly through content search:
```php
$search_data = array(
    'type' => 'posts',
    'results' => array(/* WP_Post objects */),
    'query_string' => 'search term'
);
```

### Taxonomy-Based Results
When results are found through taxonomy terms:
```php
$search_data = array(
    'type' => 'terms',
    'results' => array(
        array(
            'term' => $term_object,
            'taxonomy' => 'category',
            'posts' => array(/* WP_Post objects */)
        )
    ),
    'query_string' => 'search term'
);
```

## Requirements

- WordPress 4.0+
- PHP 7.0+
- WooCommerce (optional, for product search features)

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## License

This plugin is open source and available under the GPL v2 or later license.

## Support

For issues and questions:
1. Check existing GitHub issues
2. Create a new issue with detailed description
3. Include WordPress and PHP version information

## Changelog

### Version 1.0.0
- Initial release
- Basic search functionality for posts and taxonomies
- WordPress native search integration
- WooCommerce support
- Plugin compatibility features

