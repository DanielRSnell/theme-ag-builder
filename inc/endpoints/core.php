<?php
add_action('rest_api_init', 'register_agnostic_rest_routes');

function register_agnostic_rest_routes()
{
    $routes = array(
        'view-is' => 'get_view_is_options',
        'view-targets' => 'get_view_target_options',
        'view-types' => 'get_view_types',
        'post-types' => 'get_agnostic_post_types',
        'taxonomies' => 'get_agnostic_taxonomies',
        'menus' => 'get_agnostic_menus',
        'if-single-options' => 'get_if_single_options',
    );

    foreach ($routes as $route => $callback) {
        register_rest_route('agnostic/v1/core', '/' . $route, array(
            'methods' => 'GET',
            'callback' => $callback,
            // 'permission_callback' => function () {
            //     return current_user_can('edit_posts');
            // },
        ));
    }
}

function get_view_is_options()
{
    $options = array(
        'component' => 'Component',
        'template' => 'Template',
        'controller' => 'Controller',
        'block' => 'Block',
        'action' => 'Action',
        'header' => 'Header',
        'footer' => 'Footer',
    );
    return new WP_REST_Response($options, 200);
}

function get_view_target_options()
{
    $core_types = [
        'single' => 'Single',
        'archive' => 'Archive',
        'search' => 'Search',
        '404' => '404',
        'login' => 'Login',
        'menu' => 'Menu',
    ];

    if (class_exists('WooCommerce')) {
        $woo_types = [
            'cart' => 'Cart',
            'checkout' => 'Checkout',
            'account' => 'Account',
            'order_confirmation' => 'Order Confirmation',
            'mini_cart' => 'Mini Cart',
        ];
        $core_types = array_merge($core_types, $woo_types);
    }

    return new WP_REST_Response($core_types, 200);
}

function get_view_types(WP_REST_Request $request)
{
    $view_target = $request->get_param('view_target');
    $types = ag_target_types($view_target);
    return new WP_REST_Response($types, 200);
}

function ag_target_types($view_target = '')
{
    $all_types = array(
        'post_types' => ag_get_post_types(),
        'taxonomies' => ag_get_taxonomies(),
    );

    if (empty($view_target)) {
        return $all_types;
    }

    switch ($view_target) {
        case 'single':
        case 'archive':
            return array_merge($all_types['post_types'], $all_types['taxonomies']);
        case 'menu':
            return ag_get_menus();
        case 'search':
            return array_merge($all_types['post_types'], $all_types['taxonomies']);
        case '404':
        case 'login':
            return array('default' => 'Default');
        default:
            return $all_types;
    }
}

function ag_get_post_types()
{
    $post_types = get_post_types(array('public' => true), 'objects');
    return array_reduce($post_types, function ($acc, $post_type) {
        if ($post_type->name !== 'attachment' && strpos($post_type->name, 'agnostic_') !== 0) {
            $acc[$post_type->name] = $post_type->label;
        }
        return $acc;
    }, []);
}

function get_agnostic_post_types()
{
    $types = ag_get_post_types();
    return new WP_REST_Response($types, 200);
}

function ag_get_taxonomies()
{
    $taxonomies = get_taxonomies(array('public' => true), 'objects');
    return array_reduce($taxonomies, function ($acc, $taxonomy) {
        if (strpos($taxonomy->name, 'agnostic_') !== 0) {
            $acc[$taxonomy->name] = $taxonomy->label;
        }
        return $acc;
    }, []);
}

function get_agnostic_taxonomies()
{
    $taxonomies = ag_get_taxonomies();
    return new WP_REST_Response($taxonomies, 200);
}

function ag_get_menus()
{
    $menus = wp_get_nav_menus();
    return array_reduce($menus, function ($acc, $menu) {
        $acc[$menu->term_id] = $menu->name;
        return $acc;
    }, []);
}

function get_agnostic_menus()
{
    $menus = ag_get_menus();
    return new WP_REST_Response($menus, 200);
}

function get_if_single_options(WP_REST_Request $request)
{
    $view_target = $request->get_param('view_target');
    $view_type = $request->get_param('view_type');

    $result = array(
        'view_target' => $view_target,
        'view_type' => $view_type,
        'data' => array(),
    );

    if ($view_target === 'menu') {
        $result['data'] = ag_get_menus();
    } elseif (in_array($view_target, ['single', 'archive'])) {
        if (post_type_exists($view_type)) {
            $result['data'] = get_posts_of_type($view_type);
        } elseif (taxonomy_exists($view_type)) {
            $result['data'] = get_terms_of_taxonomy($view_type);
        }
    }

    return new WP_REST_Response($result, 200);
}

function get_posts_of_type($post_type)
{
    $posts = get_posts(array(
        'post_type' => $post_type,
        'numberposts' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    ));

    return array_reduce($posts, function ($acc, $post) {
        $acc[$post->ID] = $post->post_title;
        return $acc;
    }, []);
}

function get_terms_of_taxonomy($taxonomy)
{
    $terms = get_terms(array(
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
    ));

    if (is_wp_error($terms)) {
        return array();
    }

    return array_reduce($terms, function ($acc, $term) {
        $acc[$term->term_id] = $term->name;
        return $acc;
    }, []);
}
