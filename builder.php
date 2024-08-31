<?php
require_once 'wp-load.php';

function ag_debug_log($message, $level = 'info')
{
    error_log("[AG_ROUTER_DEBUG][$level] " . $message);
}

function ag_route_handler()
{
    ag_debug_log("Starting route handler", 'info');
    $routes = get_routing_data(null)->get_data();
    ag_debug_log("Retrieved routes: " . print_r($routes, true), 'debug');

    $current_type = ag_get_route_type();
    ag_debug_log("Determined route type: " . $current_type, 'info');

    $view = 'fallback';
    $route_found = false;
    $custom_view_found = false;
    $theme_view_found = false;
    $debug_info = [
        'attempted_paths' => [],
        'route_type' => $current_type,
        'custom_route_checked' => false,
        'theme_route_checked' => false,
        'wordpress_conditionals' => ag_get_wordpress_conditionals(),
    ];

    // Handle WooCommerce specific routes
    if (function_exists('is_woocommerce') && (is_woocommerce() || is_cart() || is_checkout() || is_account_page())) {
        $view_info = ag_handle_woocommerce($routes);
    } else {
        switch ($current_type) {
            case 'front_page':
                $view_info = ag_handle_front_page($routes['front_page']);
                break;
            case 'blog_index':
                $view_info = ag_handle_blog_index($routes['blog_index']);
                break;
            case 'archive':
                $view_info = ag_handle_archive($routes['archives'], $routes['post_types']);
                break;
            case 'single':
                $view_info = ag_handle_single($routes['singles'], $routes['post_types']);
                break;
            case 'search':
                $view_info = ag_handle_search($routes['search_results']);
                break;
            case 'error':
                $view_info = ag_handle_error($routes['error_page']);
                break;
            case 'term':
                $view_info = ag_handle_term($routes['terms']);
                break;
            default:
                $view_info = ['view' => 'fallback', 'type' => 'fallback'];
        }
    }

    $view = $view_info['view'];
    $debug_info['custom_route_checked'] = true;
    $debug_info['attempted_paths'] = ag_get_attempted_paths($routes, $current_type);

    $route_found = ($view !== 'theme' && $view !== 'fallback');
    $custom_view_found = $route_found;

    if ($view === 'theme' || $view === 'fallback') {
        ag_debug_log("No custom view found, checking theme view", 'info');
        $theme_view_info = ag_get_theme_view($current_type);
        $view = $theme_view_info['view'];
        $theme_view_found = $theme_view_info['found'];
        $debug_info['theme_route_checked'] = true;
        $debug_info['attempted_paths'] = array_merge($debug_info['attempted_paths'], $theme_view_info['attempted_paths']);
    }

    ag_debug_log("Final view: " . $view, 'info');

    return [
        'view_path' => $view,
        'route_type' => $current_type,
        'route_found' => $route_found,
        'custom_view_found' => $custom_view_found,
        'using_theme_view' => ($view === 'theme' || $view === 'fallback'),
        'theme_view_found' => $theme_view_found,
        'debug_info' => $debug_info,
        'view_info' => $view_info,
    ];
}

function ag_get_route_type()
{
    if (is_front_page() && !is_home()) {
        return 'front_page';
    }

    if (is_home()) {
        return 'blog_index';
    }

    // WooCommerce specific checks
    if (function_exists('is_woocommerce') && is_woocommerce()) {
        if (is_shop()) {
            return 'shop';
        }
        if (is_product_category() || is_product_tag()) {
            return 'product_term';
        }
        if (is_product()) {
            return 'product';
        }
    }

    if (is_archive()) {
        return 'archive';
    }

    if (is_single() || is_page()) {
        return 'single';
    }

    if (is_search()) {
        return 'search';
    }

    if (is_404()) {
        return 'error';
    }

    if (is_tax() || is_category() || is_tag()) {
        return 'term';
    }

    // Additional WooCommerce page checks
    if (function_exists('is_cart') && is_cart()) {
        return 'cart';
    }

    if (function_exists('is_checkout') && is_checkout()) {
        return 'checkout';
    }

    if (function_exists('is_account_page') && is_account_page()) {
        return 'account';
    }

    return 'fallback';
}

function ag_handle_front_page($front_page_view)
{
    ag_debug_log("Handling front page", 'info');
    return ['view' => $front_page_view, 'type' => 'front_page'];
}

function ag_handle_blog_index($blog_index_view)
{
    ag_debug_log("Handling blog index", 'info');
    return ['view' => $blog_index_view, 'type' => 'blog_index'];
}

function ag_handle_archive($archive_views, $post_type_views)
{
    $post_type = get_post_type();
    ag_debug_log("Handling archive for post type: " . $post_type, 'info');

    foreach ($archive_views as $archive) {
        if ($archive['archive_type'] === $post_type) {
            ag_debug_log("Found matching archive view: " . $archive['view'], 'info');
            return ['view' => $archive['view'], 'type' => 'archive', 'post_type' => $post_type];
        }
    }

    foreach ($post_type_views as $pt_view) {
        if ($pt_view['post_type'] === $post_type) {
            ag_debug_log("Found matching post type view: " . $pt_view['view'], 'info');
            return ['view' => $pt_view['view'], 'type' => 'post_type', 'post_type' => $post_type];
        }
    }

    ag_debug_log("No matching archive or post type view found, returning 'theme'", 'warning');
    return ['view' => 'theme', 'type' => 'theme_fallback'];
}

function ag_handle_single($single_views, $post_type_views)
{
    $post_id = get_the_ID();
    $post_type = get_post_type();
    ag_debug_log("Handling single for post ID: " . $post_id . ", post type: " . $post_type, 'info');

    foreach ($single_views as $single) {
        if ($single['post'] == $post_id) {
            ag_debug_log("Found matching single view: " . $single['view'], 'info');
            return ['view' => $single['view'], 'type' => 'single_view', 'post_id' => $post_id];
        }
    }

    foreach ($post_type_views as $pt_view) {
        if ($pt_view['post_type'] === $post_type) {
            ag_debug_log("Found matching post type view: " . $pt_view['view'], 'info');
            return ['view' => $pt_view['view'], 'type' => 'post_type_view', 'post_type' => $post_type];
        }
    }

    ag_debug_log("No matching single or post type view found, returning 'theme'", 'warning');
    return ['view' => 'theme', 'type' => 'theme_fallback'];
}

function ag_handle_search($search_results_view)
{
    ag_debug_log("Handling search", 'info');
    return ['view' => $search_results_view, 'type' => 'search'];
}

function ag_handle_error($error_page_view)
{
    ag_debug_log("Handling 404 error", 'info');
    return ['view' => $error_page_view, 'type' => 'error'];
}

function ag_handle_term($term_views)
{
    $term_id = get_queried_object_id();
    $taxonomy = get_queried_object()->taxonomy;
    ag_debug_log("Handling term for term ID: " . $term_id . ", taxonomy: " . $taxonomy, 'info');

    foreach ($term_views as $term_view) {
        if ($term_view['taxonomy'] === $taxonomy) {
            ag_debug_log("Found matching term view: " . $term_view['view'], 'info');
            return ['view' => $term_view['view'], 'type' => 'term', 'taxonomy' => $taxonomy];
        }
    }

    ag_debug_log("No matching term view found, returning 'theme'", 'warning');
    return ['view' => 'theme', 'type' => 'theme_fallback'];
}

function ag_handle_woocommerce($routes)
{
    $post_type = get_post_type();
    $route_type = ag_get_route_type();

    switch ($route_type) {
        case 'shop':
            return ag_handle_shop($routes['post_types']);
        case 'product_term':
            return ag_handle_product_term($routes['terms']);
        case 'product':
            return ag_handle_product($routes['singles'], $routes['post_types']);
        case 'cart':
            return ag_handle_cart($routes);
        case 'checkout':
            return ag_handle_checkout($routes);
        case 'account':
            return ag_handle_account($routes);
        default:
            return ['view' => 'theme', 'type' => 'theme_fallback'];
    }
}

function ag_handle_shop($post_type_views)
{
    foreach ($post_type_views as $pt_view) {
        if ($pt_view['post_type'] === 'product') {
            return ['view' => $pt_view['view'], 'type' => 'shop'];
        }
    }
    return ['view' => 'theme', 'type' => 'theme_fallback'];
}

function ag_handle_product_term($term_views)
{
    $taxonomy = get_queried_object()->taxonomy;
    foreach ($term_views as $term_view) {
        if ($term_view['taxonomy'] === $taxonomy) {
            return ['view' => $term_view['view'], 'type' => 'product_term', 'taxonomy' => $taxonomy];
        }
    }
    return ['view' => 'theme', 'type' => 'theme_fallback'];
}

function ag_handle_product($single_views, $post_type_views)
{
    $post_id = get_the_ID();
    foreach ($single_views as $single) {
        if ($single['post'] == $post_id) {
            return ['view' => $single['view'], 'type' => 'product', 'post_id' => $post_id];
        }
    }
    foreach ($post_type_views as $pt_view) {
        if ($pt_view['post_type'] === 'product') {
            return ['view' => $pt_view['view'], 'type' => 'product'];
        }
    }
    return ['view' => 'theme', 'type' => 'theme_fallback'];
}

function ag_handle_cart($routes)
{
    if (isset($routes['cart']) && !empty($routes['cart'])) {
        return ['view' => $routes['cart'], 'type' => 'cart'];
    }
    return ['view' => 'theme', 'type' => 'cart'];
}

function ag_handle_checkout($routes)
{
    if (isset($routes['checkout']) && !empty($routes['checkout'])) {
        return ['view' => $routes['checkout'], 'type' => 'checkout'];
    }
    return ['view' => 'theme', 'type' => 'checkout'];
}

function ag_handle_account($routes)
{
    if (isset($routes['account']) && !empty($routes['account'])) {
        return ['view' => $routes['account'], 'type' => 'account'];
    }
    return ['view' => 'theme', 'type' => 'account'];
}

function ag_get_theme_view($type)
{
    $post_type = get_post_type();
    ag_debug_log("Getting theme view for type: " . $type . ", post type: " . $post_type, 'info');

    $potential_paths = [];

    switch ($type) {
        case 'front_page':
            $potential_paths[] = "front_page/single.twig";
            break;
        case 'blog_index':
            $potential_paths[] = "blog_index/single.twig";
            $potential_paths[] = "archive/single.twig";
            break;
        case 'archive':
            $potential_paths[] = "{$post_type}/archive.twig";
            $potential_paths[] = "archive/single.twig";
            break;
        case 'single':
            $potential_paths[] = "{$post_type}/single.twig";
            break;
        case 'search':
            $potential_paths[] = "search/single.twig";
            break;
        case 'error':
            $potential_paths[] = "error/single.twig";
            break;
        case 'term':
            $taxonomy = get_queried_object()->taxonomy;
            $potential_paths[] = "{$taxonomy}/single.twig";
            $potential_paths[] = "term/single.twig";
            break;
        case 'shop':
            $potential_paths[] = "woocommerce/shop.twig";
            break;
        case 'product_term':
            $taxonomy = get_queried_object()->taxonomy;
            $potential_paths[] = "woocommerce/{$taxonomy}.twig";
            $potential_paths[] = "woocommerce/archive-product.twig";
            break;
        case 'product':
            $potential_paths[] = "woocommerce/single-product.twig";
            break;
        case 'cart':
            $potential_paths[] = "woocommerce/cart.twig";
            break;
        case 'checkout':
            $potential_paths[] = "woocommerce/checkout.twig";
            break;
        case 'account':
            $potential_paths[] = "woocommerce/myaccount.twig";
            break;
        default:
            $potential_paths[] = "{$type}/single.twig";
            break;
    }

    $potential_paths[] = "debug.twig";

    $theme_dir = get_stylesheet_directory() . "/views/theme/";
    foreach ($potential_paths as $path) {
        $full_path = $theme_dir . $path;
        ag_debug_log("Checking path: " . $full_path, 'debug');
        if (file_exists($full_path)) {
            ag_debug_log("Found existing path: " . $full_path, 'info');
            return [
                'view' => $full_path,
                'found' => true,
                'attempted_paths' => $potential_paths,
            ];
        }
    }

    ag_debug_log("No theme view found, using debug template", 'warning');
    return [
        'view' => $theme_dir . "debug.twig",
        'found' => false,
        'attempted_paths' => $potential_paths,
    ];
}

function ag_get_attempted_paths($routes, $current_type)
{
    $attempted_paths = [];
    switch ($current_type) {
        case 'front_page':
            $attempted_paths[] = $routes['front_page'];
            break;
        case 'blog_index':
            $attempted_paths[] = $routes['blog_index'];
            break;
        case 'archive':
            $attempted_paths = array_merge(
                array_column($routes['archives'], 'view'),
                array_column($routes['post_types'], 'view')
            );
            break;
        case 'single':
        case 'product':
            $post_id = get_the_ID();
            $post_type = get_post_type();
            foreach ($routes['singles'] as $single) {
                if ($single['post'] == $post_id) {
                    $attempted_paths[] = $single['view'];
                    break;
                }
            }
            foreach ($routes['post_types'] as $pt_view) {
                if ($pt_view['post_type'] === $post_type) {
                    $attempted_paths[] = $pt_view['view'];
                    break;
                }
            }
            break;
        case 'search':
            $attempted_paths[] = $routes['search_results'];
            break;
        case 'error':
            $attempted_paths[] = $routes['error_page'];
            break;
        case 'term':
        case 'product_term':
            $attempted_paths = array_column($routes['terms'], 'view');
            break;
        case 'shop':
            foreach ($routes['post_types'] as $pt_view) {
                if ($pt_view['post_type'] === 'product') {
                    $attempted_paths[] = $pt_view['view'];
                    break;
                }
            }
            break;
        case 'cart':
            $attempted_paths[] = $routes['cart'] ?? '';
            break;
        case 'checkout':
            $attempted_paths[] = $routes['checkout'] ?? '';
            break;
        case 'account':
            $attempted_paths[] = $routes['account'] ?? '';
            break;
    }
    return array_values(array_unique(array_filter($attempted_paths)));
}

function ag_get_wordpress_conditionals()
{
    return [
        'is_front_page' => is_front_page(),
        'is_home' => is_home(),
        'is_archive' => is_archive(),
        'is_single' => is_single(),
        'is_page' => is_page(),
        'is_singular' => is_singular(),
        'is_search' => is_search(),
        'is_404' => is_404(),
        'is_tax' => is_tax(),
        'is_category' => is_category(),
        'is_tag' => is_tag(),
        'is_shop' => function_exists('is_shop') ? is_shop() : false,
        'is_product_category' => function_exists('is_product_category') ? is_product_category() : false,
        'is_product_tag' => function_exists('is_product_tag') ? is_product_tag() : false,
        'is_product' => function_exists('is_product') ? is_product() : false,
        'is_cart' => function_exists('is_cart') ? is_cart() : false,
        'is_checkout' => function_exists('is_checkout') ? is_checkout() : false,
        'is_account_page' => function_exists('is_account_page') ? is_account_page() : false,
    ];
}

// Main execution
ag_debug_log("Starting main execution", 'info');
$route_info = ag_route_handler();

ag_debug_log("Using Timber for rendering", 'info');
$context = Timber::context();
$context['post'] = Timber::get_post();

$context['ag_router'] = [
    'route_type' => $route_info['route_type'],
    'route_found' => $route_info['route_found'],
    'custom_view_found' => $route_info['custom_view_found'],
    'using_theme_view' => $route_info['using_theme_view'],
    'theme_view_found' => $route_info['theme_view_found'],
    'view_path' => $route_info['view_path'],
    'debug_info' => $route_info['debug_info'],
    'view_info' => $route_info['view_info'],
    'post_type' => get_post_type(),
    'queried_object' => get_queried_object(),
    'queried_object_id' => get_queried_object_id(),
];

$context['routes'] = get_routing_data(null)->get_data();

// Create a deep copy of $context for state
$state = json_decode(json_encode($context), true);

// Modify only the copy, leaving original $context unchanged
if (isset($state['post']) && is_array($state['post'])) {
    unset($state['post']['content']);
}

try {
    $context['state'] = json_encode($state, JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception(json_last_error_msg());
    }
} catch (Exception $e) {
    ag_debug_log("JSON encoding error: " . $e->getMessage(), 'error');
    $context['state'] = json_encode(['error' => 'Unable to encode full state']);
}

$view_to_render = $route_info['view_path'];

if (isset($_GET['agnostic']) && $_GET['agnostic'] === 'debug') {
    $debug_view = get_stylesheet_directory() . "/views/theme/debug.twig";
    if (file_exists($debug_view)) {
        $view_to_render = $debug_view;
        ag_debug_log("Debug mode activated. Rendering debug template.", 'info');
    } else {
        ag_debug_log("Debug template not found. Using original view path.", 'warning');
    }

    // Render the view using Timber for debug mode
    try {
        Timber::render($view_to_render, $context);
    } catch (Exception $e) {
        ag_debug_log("Error rendering view: " . $e->getMessage(), 'error');
        // Fallback to a basic error display if Timber rendering fails
        echo "An error occurred while rendering the page. Please check the error logs for more information.";
    }
} else {
    // Check if a view is detected
    if ($context['ag_router']['route_found'] || $context['ag_router']['theme_view_found']) {
        if ($context['ag_router']['using_theme_view']) {
            // For theme views, pass the full file path
            ag_render($context['ag_router']);
        } else {
            // For custom views, extract the view ID
            $view_id = basename($view_to_render, '.twig');
            ag_render($context['ag_router']);
        }
    } else {
        // If no view is found, render using Timber as before
        try {
            Timber::render($view_to_render, $context);
        } catch (Exception $e) {
            ag_debug_log("Error rendering view: " . $e->getMessage(), 'error');
            echo "An error occurred while rendering the page. Please check the error logs for more information.";
        }
    }
}

ag_debug_log("Final context: " . print_r($context['ag_router'], true), 'debug');
ag_debug_log("Finished main execution", 'info');

function ag_render_theme_view($router, $context)
{
    $full_path = $router['view_path'];
    ag_debug_log("Full theme view path: " . $full_path, 'info');

    if (preg_match('/(theme\/.*\.twig)/', $full_path, $matches)) {
        $relative_path = $matches[1];
        ag_debug_log("Rendering theme view: " . $relative_path, 'info');
        Timber::render($relative_path, $context);
    } else {
        ag_debug_log("Could not extract theme path, using full path", 'warning');
        Timber::render($full_path, $context);
    }
}

function ag_render_custom_view($view, $context)
{
    ag_debug_log("Rendering custom view with ID: " . $view, 'info');

    $context['view'] = Timber::get_post($view);
    $template = $context['view']->post_content;

    // Incorporate ag_view_context functionality
    $queries = get_agnostic_queries_for_view($view);
    foreach ($queries as $query) {
        $context_key = $query['slug'];
        // update context key so that - becomes _
        $context_key = str_replace('-', '_', $context_key);
        $parsed_query = ag_parse_query($query['query_json']);

        if (!$parsed_query) {
            continue; // Skip if parsing failed
        }

        try {
            switch ($parsed_query['type']) {
                case 'posts':
                    $context[$context_key] = Timber::get_posts($parsed_query['args']);
                    break;
                case 'terms':
                    $context[$context_key] = Timber::get_terms($parsed_query['args']);
                    break;
                case 'users':
                    $context[$context_key] = Timber::get_users($parsed_query['args']);
                    break;
                case 'menus':
                    // Menu Query Here
                    break;
                case 'products':
                    if (function_exists('wc_get_products')) {
                        $context[$context_key] = wc_get_products($parsed_query['args']);
                    }
                    break;
                case 'cart':
                    if (function_exists('WC')) {
                        $context[$context_key] = WC()->cart;
                    }
                    break;
                default:
                    // Handle unknown query type
                    $context[$context_key] = null;
                    error_log("Unknown query type: " . $parsed_query['type']);
            }
        } catch (Exception $e) {
            // Log the error and set the context key to null
            error_log("Error executing query for {$context_key}: " . $e->getMessage());
            $context[$context_key] = null;
        }
    }
// Get and execute the PHP from the view crb_php_view
    $php_code = get_post_meta($view, 'crb_php_view', true);

    if (!empty($php_code)) {
        ob_start();
        try {
            $temp_context = $context;
            eval($php_code);
            $context = array_merge($context, array_diff_key(get_defined_vars(), ['context' => null, 'temp_context' => null, 'php_code' => null, 'params' => null]));
        } catch (Throwable $e) {
            $context['php_error'] = $e->getMessage();
            ag_debug_log("PHP Error in custom view: " . $e->getMessage(), 'error');
        }
        ob_end_clean();
    }

    // Filter template before rendering
    $template = apply_filters('ag_modify_template_raw', $template);

    // Create a filter that makes the context available to the view
    $context = apply_filters('ag_modify_context', $context);

    $base_template = <<<EOT
        {% extends "theme/base.twig" %}
        {% block content %}
            <%REPLACE_WITH_CONTENT%>
        {% endblock %}
        EOT;

    // replace <%REPLACE_WITH_CONTENT%> with the actual content
    $base_template = str_replace('<%REPLACE_WITH_CONTENT%>', $template, $base_template);

    // Filter the base template
    $base_template = apply_filters('ag_modify_template', $base_template);

    $compile = Timber::compile_string($base_template, $context);

    echo $compile;
}

function ag_get_user_tailwind_css_string()
{
    $upload_dir = wp_upload_dir();
    $tailwind_css_path = $upload_dir['basedir'] . '/agnostic/tailwind.css';

    if (file_exists($tailwind_css_path)) {
        $version = filemtime($tailwind_css_path);
        $css = file_get_contents($tailwind_css_path);

        if ($css !== false) {
            return sprintf(
                "<style id='tw-inline' data-version='%s'>\n%s\n</style>",
                esc_attr($version),
                $css // Not escaping the CSS content
            );
        } else {
            error_log("Failed to read Tailwind CSS file: $tailwind_css_path");
        }
    } else {
        error_log("Tailwind CSS file not found: $tailwind_css_path");
    }

    return '';
}
