<?php

function custom_route_handler()
{
    $routes = get_all_routes_rest()->get_data();
    $current_type = get_current_route_type();
    $view = 'fallback';

    switch ($current_type) {
        case 'front_page':
            $view = handle_front_page($routes['front_page_view']);
            break;
        case 'archive':
            $view = handle_archive($routes['archive_views']);
            break;
        case 'single':
            $view = handle_single($routes['single_views'], $routes['post_type_views']);
            break;
        case 'search':
            $view = handle_search($routes['search_results_view']);
            break;
        case 'error':
            $view = handle_error($routes['error_page_view']);
            break;
        case 'term':
            $view = handle_term($routes['term_views']);
            break;
    }

    if ($view === 'theme' || $view === 'fallback') {
        $view = get_theme_view($current_type);
    }

    return $view;
}

function get_current_route_type()
{
    if (is_front_page()) {
        return 'front_page';
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

    return 'fallback';
}

function handle_front_page($front_page_view)
{
    return $front_page_view;
}

function handle_archive($archive_views)
{
    $post_type = get_post_type();
    foreach ($archive_views as $archive) {
        if ($archive['archive_type'] === $post_type) {
            return $archive['view'];
        }
    }
    return 'theme';
}

function handle_single($single_views, $post_type_views)
{
    $post_id = get_the_ID();
    $post_type = get_post_type();

    // Check single_views first
    foreach ($single_views as $single) {
        if ($single['post_id'] == $post_id) {
            return $single['view'];
        }
    }

    // If not found in single_views, check post_type_views
    foreach ($post_type_views as $pt_view) {
        if ($pt_view['post_type'] === $post_type) {
            return $pt_view['view'];
        }
    }

    return 'theme';
}

function handle_search($search_results_view)
{
    return $search_results_view;
}

function handle_error($error_page_view)
{
    return $error_page_view;
}

function handle_term($term_views)
{
    $term_id = get_queried_object_id();
    $taxonomy = get_queried_object()->taxonomy;

    foreach ($term_views as $term_view) {
        if ($term_view['taxonomy'] === $taxonomy && $term_view['term'] == $term_id) {
            return $term_view['view'];
        }
    }

    return 'theme';
}

function get_theme_view($type)
{
    $post_type = get_post_type();
    $potential_paths = [
        get_stylesheet_directory() . "/views/theme/{$type}/{$post_type}.twig",
        get_stylesheet_directory() . "/views/theme/{$type}.twig",
        get_stylesheet_directory() . "/views/theme/fallback.twig",
    ];

    foreach ($potential_paths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }

    return get_stylesheet_directory() . "/views/theme/fallback.twig";
}

// Process Header, Footer, and Hooks for Global Partials
function process_global_partials()
{
    // Check if WooCommerce is active before proceeding
    if (!class_exists('WooCommerce')) {
        return;
    }

    $routing_data = ag_get_routing_data();
    $global_partials = $routing_data['global_partials'] ?? [];

    foreach ($global_partials as $partial) {
        $type = $partial['type'] ?? '';
        $view_id = $partial['view'] ?? '';
        $hook_name = $partial['hook_name'] ?? '';

        if (!$view_id) {
            continue;
        }

        $content = get_post_field('post_content', $view_id);
        if (!$content) {
            continue;
        }

        // Defer the context creation and content compilation to a later hook
        $process_partial = function () use ($type, $view_id, $hook_name, $content) {
            $context = Timber::context();
            $context['post'] = Timber::get_post($view_id);
            $context['meta'] = array(
                'view_id' => $view_id,
                'type' => $type,
                'hook_name' => $hook_name,
            );

            // Apply any filters to the context
            $context = apply_filters('ag_global_partial_context', $context, $view_id);

            // Compile the content using Timber
            $compiled_content = Timber::compile_string($content, $context);

            return $compiled_content;
        };

        switch ($type) {
            case 'header':
                add_action('ag_header', function () use ($process_partial) {
                    echo $process_partial();
                }, 20);
                add_action('editor_header', function () use ($process_partial) {
                    echo $process_partial();
                }, 20);
                break;
            case 'footer':
                add_action('ag_footer', function () use ($process_partial) {
                    echo $process_partial();
                }, 20);
                add_action('editor_footer', function () use ($process_partial) {
                    echo $process_partial();
                }, 20);
                break;
            case 'hook':
                if ($hook_name) {
                    add_action($hook_name, function () use ($process_partial) {
                        echo $process_partial();
                    }, 20);
                }
                break;
        }
    }
}

// Call this function after WooCommerce has been loaded
add_action('woocommerce_init', 'process_global_partials');
