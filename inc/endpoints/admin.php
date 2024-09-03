<?php

use Carbon_Fields\Container;
use Carbon_Fields\Field;

// Post Type Registration
function register_agnostic_views_post_type()
{
    $labels = array(
        'name' => 'Agnostic Views',
        'singular_name' => 'Agnostic View',
        'menu_name' => 'Views',
        'add_new' => 'Add New',
        'add_new_item' => 'Add New View',
        'edit_item' => 'Edit View',
        'new_item' => 'New View',
        'view_item' => 'View View',
        'search_items' => 'Search Views',
        'not_found' => 'No views found',
        'not_found_in_trash' => 'No views found in Trash',
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => false,
        'query_var' => true,
        'rewrite' => array('slug' => 'agnostic-view'),
        'capability_type' => 'post',
        'has_archive' => true,
        'hierarchical' => true,
        'menu_position' => null,
        'supports' => array('title', 'editor', 'revisions', 'thumbnail'),
        'show_in_rest' => false,
    );

    register_post_type('agnostic_view', $args);
}
add_action('init', 'register_agnostic_views_post_type');

// Taxonomy Registration
function register_agnostic_queries_taxonomy()
{
    $labels = array(
        'name' => _x('Agnostic Queries', 'taxonomy general name'),
        'singular_name' => _x('Agnostic Query', 'taxonomy singular name'),
        'search_items' => __('Search Agnostic Queries'),
        'all_items' => __('All Agnostic Queries'),
        'parent_item' => __('Parent Agnostic Query'),
        'parent_item_colon' => __('Parent Agnostic Query:'),
        'edit_item' => __('Edit Agnostic Query'),
        'update_item' => __('Update Agnostic Query'),
        'add_new_item' => __('Add New Agnostic Query'),
        'new_item_name' => __('New Agnostic Query Name'),
        'menu_name' => __('Agnostic Queries'),
    );

    $args = array(
        'hierarchical' => false,
        'labels' => $labels,
        'show_ui' => true,
        'show_in_menu' => false,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => array('slug' => 'agnostic-query'),
        'show_in_rest' => true, // Enable REST API support
        'rest_base' => 'agnostic_queries', // Base URL for REST API
        'rest_controller_class' => 'WP_REST_Terms_Controller', // REST API controller
    );

    register_taxonomy('agnostic_queries', array('agnostic_view'), $args);
}
add_action('init', 'register_agnostic_queries_taxonomy', 0);

// Admin Styles
function get_ag_admin_css()
{
    $css_file_path = get_template_directory() . '/assets/admin/styles.css';

    if (file_exists($css_file_path)) {
        $css_content = file_get_contents($css_file_path);

        if ($css_content !== false) {
            return sprintf('<style id="ag-admin-css">%s</style>', $css_content);
        } else {
            error_log('Failed to read the contents of the CSS file: ' . $css_file_path);
            return '<!-- Failed to load AG Admin CSS -->';
        }
    } else {
        error_log('AG Admin CSS file not found: ' . $css_file_path);
        return '<!-- AG Admin CSS file not found -->';
    }
}

// Menu Creation
function create_agnostic_menu()
{
    add_menu_page(
        'Agnostic',
        'Agnostic',
        'manage_options',
        'agnostic',
        'agnostic_main_page',
        'dashicons-admin-generic',
        3
    );

    add_submenu_page(
        'agnostic',
        'Agnostic Views',
        'Views',
        'manage_options',
        'edit.php?post_type=agnostic_view'
    );

    add_submenu_page(
        'agnostic',
        'Routing',
        'Routing',
        'manage_options',
        'agnostic_routing',
        'render_agnostic_routing_page'
    );

    // Add submenu for custom taxonomy
    add_submenu_page(
        'agnostic',
        'Agnostic Queries',
        'Queries',
        'manage_options',
        'edit-tags.php?taxonomy=agnostic_queries&post_type=agnostic_view'
    );
}
add_action('admin_menu', 'create_agnostic_menu');

// Page Rendering
function agnostic_main_page()
{
    error_log('agnostic_main_page function called');

    if (isset($_GET['media']) && $_GET['media'] == 'true') {
        error_log('Media parameter is true');
        $context = Timber::context();
        Timber::render('admin/_main.twig', $context);
    } else {
        error_log('Media parameter is not set or not true');
        $context = Timber::context();
        Timber::render('admin/_settings.twig', $context);
    }
}

function render_agnostic_routing_page()
{
    $context = Timber::context();
    Timber::render('admin/_settings.twig', $context);
}

// Menu Order Adjustment
function adjust_agnostic_menu_order($menu_order)
{
    $agnostic = 'agnostic';
    $dashboard_position = array_search('index.php', $menu_order);

    $agnostic_position = array_search($agnostic, $menu_order);
    if ($agnostic_position !== false) {
        unset($menu_order[$agnostic_position]);
    }

    array_splice($menu_order, $dashboard_position + 1, 0, $agnostic);

    return $menu_order;
}
add_filter('custom_menu_order', '__return_true');
add_filter('menu_order', 'adjust_agnostic_menu_order');

// Helper Functions for Options
function get_views_options()
{
    $views = get_posts(array(
        'post_type' => 'agnostic_view',
        'numberposts' => -1,
    ));
    $options = array(
        'theme' => 'Theme Default',
    );
    foreach ($views as $view) {
        $options[$view->ID] = $view->post_title;
    }
    return $options;
}

function get_post_types_options()
{
    $post_types = get_post_types(array('public' => true), 'objects');
    $options = array('theme' => 'Theme Default');
    foreach ($post_types as $post_type) {
        if (!in_array($post_type->name, ['agnostic_view', 'attachment'])) {
            $options[$post_type->name] = $post_type->label;
        }
    }
    return $options;
}

function get_archive_types_options()
{
    $post_types = get_post_types(array('public' => true, 'has_archive' => true), 'objects');
    $options = array(
        'theme' => 'Theme Default',
        'date' => 'Date Archives',
        'author' => 'Author Archives',
    );

    foreach ($post_types as $post_type) {
        if (!in_array($post_type->name, ['agnostic_view', 'attachment'])) {
            $options[$post_type->name] = $post_type->label . ' Archives';
        }
    }

    $taxonomies = get_taxonomies(array('public' => true), 'objects');
    foreach ($taxonomies as $taxonomy) {
        $options['taxonomy_' . $taxonomy->name] = $taxonomy->label . ' Archives';
    }

    return $options;
}

function get_taxonomies_options()
{
    $taxonomies = get_taxonomies(array('public' => true), 'objects');
    $options = array('theme' => 'Theme Default');
    foreach ($taxonomies as $taxonomy) {
        $options[$taxonomy->name] = $taxonomy->label;
    }
    return $options;
}

function get_terms_options()
{
    $taxonomies = get_taxonomies(array('public' => true), 'objects');
    $options = array();
    foreach ($taxonomies as $taxonomy) {
        $terms = get_terms(array('taxonomy' => $taxonomy->name, 'hide_empty' => false));
        if (!empty($terms) && !is_wp_error($terms)) {
            $options[$taxonomy->name] = array(
                'label' => $taxonomy->label,
                'terms' => array('theme' => 'Theme Default'),
            );
            foreach ($terms as $term) {
                $options[$taxonomy->name]['terms'][$term->term_id] = $term->name;
            }
        }
    }
    return $options;
}

function get_posts_options()
{
    $post_types = get_post_types(array('public' => true), 'names');
    $post_types = array_diff($post_types, ['agnostic_view', 'attachment']);

    $posts = get_posts(array(
        'post_type' => $post_types,
        'posts_per_page' => -1,
        'post_status' => 'publish',
    ));

    $options = array();
    foreach ($posts as $post) {
        $options[$post->ID] = $post->post_title . ' (' . $post->post_type . ')';
    }
    return $options;
}

// REST API Routes
add_action('rest_api_init', function () {
    register_rest_route('agnostic/v1', '/routing', array(
        'methods' => 'GET',
        'callback' => 'get_routing_data',
        // 'permission_callback' => 'agnostic_api_permissions_check',
    ));

    register_rest_route('agnostic/v1', '/routing', array(
        'methods' => 'POST',
        'callback' => 'update_routing_data',
        'permission_callback' => 'agnostic_api_permissions_check',
    ));

    register_rest_route('agnostic/v1', '/routing/form-data', array(
        'methods' => 'GET',
        'callback' => 'get_routing_form_data',
        'permission_callback' => 'agnostic_api_permissions_check',
    ));

    register_rest_route('agnostic/v1', '/routing/reset', array(
        'methods' => 'POST',
        'callback' => 'reset_routing_data',
        'permission_callback' => 'agnostic_api_permissions_check',
    ));
});

function agnostic_api_permissions_check($request)
{
    return current_user_can('manage_options');
}

function ag_get_routing_data()
{
    $routing_data = get_option('agnostic_routing_data', null);
    if ($routing_data === null) {
        $routing_data = get_default_routing_data();
        update_option('agnostic_routing_data', $routing_data);
    } else {
        $default_data = get_default_routing_data();
        foreach ($default_data as $key => $value) {
            if (!isset($routing_data[$key])) {
                $routing_data[$key] = $value;
            }
        }
        if (!isset($routing_data['singles']) || !is_array($routing_data['singles'])) {
            $routing_data['singles'] = array();
        }
    }

    return $routing_data;
}

function get_routing_data($request)
{
    $routing_data = get_option('agnostic_routing_data', null);
    if ($routing_data === null) {
        $routing_data = get_default_routing_data();
        update_option('agnostic_routing_data', $routing_data);
    } else {
        $default_data = get_default_routing_data();
        foreach ($default_data as $key => $value) {
            if (!isset($routing_data[$key])) {
                $routing_data[$key] = $value;
            }
        }
        if (!isset($routing_data['singles']) || !is_array($routing_data['singles'])) {
            $routing_data['singles'] = array();
        }
    }
    return new WP_REST_Response($routing_data, 200);
}

function update_routing_data($request)
{
    $routing_data = $request->get_json_params();
    update_option('agnostic_routing_data', $routing_data);
    return new WP_REST_Response(array('message' => 'Routing data updated successfully'), 200);
}

function get_routing_form_data($request)
{
    $form_data = array(
        'assignment_types' => array(
            'front_page' => 'Front Page',
            'blog_index' => 'Blog Page',
            'global_partial' => 'Global Partial',
            'post_type' => 'Post Type',
            'archive' => 'Archive',
            'search_results' => 'Search Results',
            'error_page' => 'Error Page',
            'term' => 'Term',
            'single' => 'Single Post',
            'admin' => 'Admin Page',
        ),
        'views' => get_views_options(),
        'post_types' => get_post_types_options(),
        'archive_types' => get_archive_types_options(),
        'taxonomies' => get_taxonomies_options(),
        'terms' => get_terms_options(),
        'posts' => get_posts_options(),
    );
    return new WP_REST_Response($form_data, 200);
}

function get_default_routing_data()
{
    $post_types = get_post_types_options();
    $archive_types = get_archive_types_options();
    $taxonomies = get_taxonomies_options();

    $default_data = array(
        'front_page' => 'theme',
        'blog_index' => 'theme',
        'search_results' => 'theme',
        'error_page' => 'theme',
        'global_partials' => array(
            array('type' => 'header', 'view' => 'theme'),
            array('type' => 'footer', 'view' => 'theme'),
        ),
        'post_types' => array(),
        'archives' => array(),
        'terms' => array(),
        'singles' => array(),
    );

    foreach ($post_types as $post_type => $label) {
        if ($post_type !== 'theme') {
            $default_data['post_types'][] = array('post_type' => $post_type, 'view' => 'theme');
        }
    }

    foreach ($archive_types as $archive_type => $label) {
        if ($archive_type !== 'theme') {
            $default_data['archives'][] = array('archive_type' => $archive_type, 'view' => 'theme');
        }
    }

    foreach ($taxonomies as $taxonomy => $label) {
        if ($taxonomy !== 'theme') {
            $default_data['terms'][] = array('taxonomy' => $taxonomy, 'view' => 'theme');
        }
    }

    return $default_data;
}

function reset_routing_data($request)
{
    $default_data = get_default_routing_data();
    update_option('agnostic_routing_data', $default_data);
    return new WP_REST_Response(array(
        'message' => 'Routing data has been reset to default',
        'data' => $default_data,
    ), 200);
}

// Activation Hook
function agnostic_views_activation()
{
    register_agnostic_views_post_type();
    register_agnostic_queries_taxonomy();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'agnostic_views_activation');

// Admin Styles
function agnostic_view_admin_styles()
{
    global $post_type;
    if ('agnostic_view' === $post_type) {
        echo '<style type="text/css">
            #postdivrich {
                display: none !important;
            }
        </style>';
    }
}
add_action('admin_head', 'agnostic_view_admin_styles');

// View UI Container
function add_view_ui_container()
{
    global $post_type;
    if ('agnostic_view' === $post_type) {
        global $post;
        $context = Timber::context();
        $context['post'] = Timber::get_post($post);
        $context['view_content'] = get_post_meta(get_the_ID(), '_agnostic_view_content', true);
        $context['update_post_nonce'] = wp_create_nonce('update_post_content_' . $post->ID);

        Timber::render('admin/_views.twig', $context);
    }
}
add_action('edit_form_after_title', 'add_view_ui_container');

// Save View Content
function save_agnostic_view_content($post_id)
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if ('agnostic_view' !== get_post_type($post_id)) {
        return;
    }

    if (isset($_POST['content'])) {
        update_post_meta($post_id, '_agnostic_view_content', wp_kses_post($_POST['content']));
    }
}
add_action('save_post', 'save_agnostic_view_content');

// Remove Meta Boxes
function remove_agnostic_view_meta_boxes()
{
    $meta_boxes = array('slugdiv', 'commentstatusdiv', 'commentsdiv', 'authordiv', 'postcustom', 'postexcerpt', 'revisionsdiv', 'tagsdiv-post_tag', 'categorydiv', 'postimagediv');
    foreach ($meta_boxes as $meta_box) {
        remove_meta_box($meta_box, 'agnostic_view', 'normal');
        remove_meta_box($meta_box, 'agnostic_view', 'side');
    }
}
add_action('add_meta_boxes', 'remove_agnostic_view_meta_boxes', 999);

// Carbon Fields Setup
add_action('carbon_fields_register_fields', 'crb_attach_agnostic_view_fields');
function crb_attach_agnostic_view_fields()
{
    Container::make('post_meta', __('Agnostic View Settings'))
        ->where('post_type', '=', 'agnostic_view')
        ->set_context('side')
        ->add_fields(array(
            Field::make('hidden', 'crb_php_view', __('PHP View'))
                ->set_visible_in_rest_api(false),
            Field::make('hidden', 'crb_js_view', __('JS View'))
                ->set_visible_in_rest_api(false),
            Field::make('hidden', 'crb_twig_view', __('Twig View'))
                ->set_visible_in_rest_api(false),
            Field::make('select', 'view_is', __('View Is'))
                ->set_options(array(
                    '' => '-- Select --',
                    'component' => 'Component',
                    'template' => 'Template',
                    'controller' => 'Controller',
                    'block' => 'Block',
                    'action' => 'Action',
                    'header' => 'Header',
                    'footer' => 'Footer',
                ))
                ->set_default_value(''),
            Field::make('select', 'view_target', __('View Target'))
                ->set_options(array(
                    '' => '-- Select --',
                    'single' => 'Single',
                    'archive' => 'Archive',
                    'search' => 'Search',
                    '404' => '404',
                    'login' => 'Login',
                    'menu' => 'Menu',
                ))
                ->set_default_value('')
                ->set_conditional_logic(array(
                    array(
                        'field' => 'view_is',
                        'value' => 'component',
                        'compare' => '!=',
                    ),
                )),
            Field::make('select', 'view_type', __('View Type'))
                ->set_options(
                    // post types and taxonomies
                    array(
                    )
                )
                ->set_default_value('')
                ->set_conditional_logic(array(
                    array(
                        'field' => 'view_target',
                        'value' => '',
                        'compare' => '!=',
                    ),
                )),
            Field::make('text', 'ag_if_single', __('If Single (Post ID)'))
                ->set_attribute('type', 'number')
                ->set_conditional_logic(array(
                    array(
                        'field' => 'view_target',
                        'value' => 'single',
                        'compare' => '=',
                    ),
                )),
        ));
}

// Carbon Fields for Agnostic Queries Taxonomy
add_action('carbon_fields_register_fields', 'crb_attach_agnostic_query_fields');
function crb_attach_agnostic_query_fields()
{
    Container::make('term_meta', __('Agnostic Query Fields'))
        ->where('term_taxonomy', '=', 'agnostic_queries')
        ->add_fields(array(
            Field::make('textarea', 'ag_query_json', __('Query JSON'))
                ->set_required(true)
                ->set_default_value('{}')
                ->set_help_text('Enter query JSON here'),
        ));
}

add_action('rest_api_init', function () {
    register_rest_route('agnostic/v1', '/view-types', array(
        'methods' => 'GET',
        'callback' => 'get_view_type_options',
        // 'permission_callback' => function() {
        //     return current_user_can('edit_posts');
        // }
    ));
});

add_action('wp_ajax_update_post_content', 'handle_update_post_content');

function handle_update_post_content()
{
    if (!wp_verify_nonce($_POST['nonce'], 'update_post_content_' . $_POST['post_id'])) {
        wp_send_json_error(array('message' => 'Security check failed.'));
    }

    if (!current_user_can('edit_post', $_POST['post_id'])) {
        wp_send_json_error(array('message' => 'You do not have permission to edit this post.'));
    }

    $post_id = intval($_POST['post_id']);
    $content = wp_kses_post($_POST['content']);

    $updated_post = wp_update_post(array(
        'ID' => $post_id,
        'post_content' => $content,
    ), true);

    if (is_wp_error($updated_post)) {
        wp_send_json_error(array('message' => $updated_post->get_error_message()));
    } else {
        wp_send_json_success(array('message' => 'Post updated successfully.'));
    }
}

// Carbon Fields setup
add_action('carbon_fields_register_fields', 'crb_attach_agnostic_view_options');
function crb_attach_agnostic_view_options()
{
    // Container::make('term_meta', __('Category Properties'))
    //     ->where('term_taxonomy', '=', 'component_category')
    //     ->add_fields(array(
    //         Field::make('text', 'crb_category_description', __('Category Description')),
    //     ));

    // Container::make('term_meta', __('Type Properties'))
    //     ->where('term_taxonomy', '=', 'component_type')
    //     ->add_fields(array(
    //         Field::make('text', 'crb_type_description', __('Type Description')),
    //     ));

    // Container::make('post_meta', __('Component Details'))
    //     ->where('post_type', '=', 'agnostic_view')
    //     ->add_fields(array(
    //         Field::make('set', 'crb_component_category', __('Component Category'))
    //             ->add_options('get_component_category_options'),
    //         Field::make('text', 'crb_component_type', __('Component Type')),
    //     ));
}

// Function to get component category options
function get_component_category_options()
{
    $terms = get_terms([
        'taxonomy' => 'component_category',
        'hide_empty' => false,
    ]);
    $options = [];
    foreach ($terms as $term) {
        $options[$term->slug] = $term->name;
    }
    return $options;
}

// Register the taxonomies
function register_agnostic_view_taxonomies()
{
    register_taxonomy('component_category', 'agnostic_view', array(
        'label' => __('Component Categories'),
        'hierarchical' => true,
        'show_ui' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => array('slug' => 'component-category'),
    ));

    register_taxonomy('component_type', 'agnostic_view', array(
        'label' => __('Component Types'),
        'hierarchical' => false,
        'show_ui' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => array('slug' => 'component-type'),
    ));

}
add_action('init', 'register_agnostic_view_taxonomies');

// Add predefined terms for Component Category
function add_unique_component_category_terms()
{
    $terms = array(
        'Core',
        'Application',
        'Ecommerce',
        'Education',
        'Marketing',
        'Publisher',
    );

    foreach ($terms as $term) {
        $existing_term = term_exists($term, 'component_category');
        if (!$existing_term) {
            wp_insert_term($term, 'component_category');
        } else {
            // Check if the term exists with a different case
            $all_terms = get_terms([
                'taxonomy' => 'component_category',
                'hide_empty' => false,
            ]);
            $term_exists_case_insensitive = false;
            foreach ($all_terms as $existing_term) {
                if (strtolower($existing_term->name) === strtolower($term)) {
                    $term_exists_case_insensitive = true;
                    break;
                }
            }
            if (!$term_exists_case_insensitive) {
                wp_insert_term($term, 'component_category');
            }
        }
    }
}
add_action('init', 'add_unique_component_category_terms');

// Function to remove duplicate terms (run once if needed)
function remove_duplicate_component_category_terms()
{
    $terms = get_terms([
        'taxonomy' => 'component_category',
        'hide_empty' => false,
    ]);

    $unique_terms = [];
    foreach ($terms as $term) {
        $lower_name = strtolower($term->name);
        if (!isset($unique_terms[$lower_name])) {
            $unique_terms[$lower_name] = $term->term_id;
        } else {
            // If a duplicate is found, merge it with the existing term
            wp_delete_term($term->term_id, 'component_category', [
                'default' => $unique_terms[$lower_name],
                'force_default' => true,
            ]);
        }
    }
}
