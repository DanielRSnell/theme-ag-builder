<?php
// Register custom REST API endpoints for Agnostic Views
add_action('rest_api_init', function () {
    $routes = array(
        array('route' => '/views', 'methods' => 'GET', 'callback' => 'get_agnostic_views'),
        array('route' => '/views', 'methods' => 'POST', 'callback' => 'create_agnostic_view'),
        array('route' => '/views/(?P<id>\d+)', 'methods' => 'PUT', 'callback' => 'update_agnostic_view'),
        array('route' => '/views/(?P<id>\d+)', 'methods' => 'DELETE', 'callback' => 'delete_agnostic_view'),
        array('route' => '/view-types', 'methods' => 'GET', 'callback' => 'get_view_type_options'),
        array('route' => '/views/grouped', 'methods' => 'GET', 'callback' => 'get_grouped_agnostic_views'),
        array('route' => '/views/grouped/(?P<category>[a-zA-Z0-9-]+)', 'methods' => 'GET', 'callback' => 'get_component_types_for_category'),
        array('route' => '/views/grouped/(?P<category>[a-zA-Z0-9-]+)/(?P<type>[a-zA-Z0-9-]+)', 'methods' => 'GET', 'callback' => 'get_components_for_category_and_type'),
    );

    foreach ($routes as $route) {
        register_rest_route('agnostic/v1', $route['route'], array(
            'methods' => $route['methods'],
            'callback' => $route['callback'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ));
    }
});

function get_agnostic_views()
{
    $args = array(
        'post_type' => 'agnostic_view',
        'posts_per_page' => -1,
    );

    $views = get_posts($args);
    $formatted_views = array();

    foreach ($views as $view) {
        $view_is = get_post_meta($view->ID, '_view_is', true);
        $view_target = get_post_meta($view->ID, '_view_target', true);
        $view_type = get_post_meta($view->ID, '_view_type', true);
        $if_single = get_post_meta($view->ID, '_ag_if_single', true);

        $formatted_view = array(
            'id' => $view->ID,
            'title' => $view->post_title,
            'view_is' => $view_is ?: '',
            'view_target' => $view_target ?: '',
        );

        if (!empty($view_target)) {
            $formatted_view['view_type'] = $view_type ?: '';
        }

        if ($view_target === 'single') {
            $formatted_view['if_single'] = $if_single ?: '';
        }

        $formatted_views[] = $formatted_view;
    }

    return new WP_REST_Response($formatted_views, 200);
}

function create_agnostic_view($request)
{
    $params = $request->get_params();

    $post_data = array(
        'post_title' => sanitize_text_field($params['title']),
        'post_type' => 'agnostic_view',
        'post_status' => 'publish',
        'post_content' => $params['content'] ?? '',
    );

    $post_id = wp_insert_post($post_data);

    if (is_wp_error($post_id)) {
        return new WP_Error('failed-to-create', 'Failed to create the view', array('status' => 500));
    }

    // Update post meta
    update_post_meta($post_id, '_view_is', sanitize_text_field($params['view_is'] ?? ''));

    if ($params['view_is'] !== 'component') {
        update_post_meta($post_id, '_view_target', sanitize_text_field($params['view_target'] ?? ''));

        if (!empty($params['view_target'])) {
            update_post_meta($post_id, '_view_type', sanitize_text_field($params['view_type'] ?? ''));
        }

        if ($params['view_target'] === 'single') {
            update_post_meta($post_id, '_ag_if_single', absint($params['if_single'] ?? 0));
        }
    }

    // Set component category and type
    if (!empty($params['component_category'])) {
        wp_set_object_terms($post_id, $params['component_category'], 'component_category');
    }
    if (!empty($params['component_type'])) {
        wp_set_object_terms($post_id, $params['component_type'], 'component_type');
    }

    $response_data = array(
        'id' => $post_id,
        'title' => $params['title'],
        'view_is' => $params['view_is'] ?? '',
    );

    if ($params['view_is'] !== 'component') {
        $response_data['view_target'] = $params['view_target'] ?? '';

        if (!empty($params['view_target'])) {
            $response_data['view_type'] = $params['view_type'] ?? '';
        }

        if ($params['view_target'] === 'single') {
            $response_data['if_single'] = $params['if_single'] ?? '';
        }
    }

    return new WP_REST_Response($response_data, 201);
}

function update_agnostic_view($request)
{
    $post_id = $request['id'];
    $params = $request->get_params();

    if (get_post_type($post_id) !== 'agnostic_view') {
        return new WP_Error('invalid-view', 'The specified view does not exist', array('status' => 404));
    }

    $post_data = array(
        'ID' => $post_id,
        'post_title' => sanitize_text_field($params['title']),
    );

    $updated = wp_update_post($post_data);

    if (is_wp_error($updated)) {
        return new WP_Error('failed-to-update', 'Failed to update the view', array('status' => 500));
    }

    // Update post meta
    update_post_meta($post_id, '_view_is', sanitize_text_field($params['view_is'] ?? ''));

    if ($params['view_is'] !== 'component') {
        update_post_meta($post_id, '_view_target', sanitize_text_field($params['view_target'] ?? ''));

        if (!empty($params['view_target'])) {
            update_post_meta($post_id, '_view_type', sanitize_text_field($params['view_type'] ?? ''));
        } else {
            delete_post_meta($post_id, '_view_type');
        }

        if ($params['view_target'] === 'single') {
            update_post_meta($post_id, '_ag_if_single', absint($params['if_single'] ?? 0));
        } else {
            delete_post_meta($post_id, '_ag_if_single');
        }
    } else {
        delete_post_meta($post_id, '_view_target');
        delete_post_meta($post_id, '_view_type');
        delete_post_meta($post_id, '_ag_if_single');
    }

    // Update component category and type
    if (isset($params['component_category'])) {
        wp_set_object_terms($post_id, $params['component_category'], 'component_category');
    }
    if (isset($params['component_type'])) {
        wp_set_object_terms($post_id, $params['component_type'], 'component_type');
    }

    $response_data = array(
        'id' => $post_id,
        'title' => $params['title'],
        'view_is' => $params['view_is'] ?? '',
    );

    if ($params['view_is'] !== 'component') {
        $response_data['view_target'] = $params['view_target'] ?? '';

        if (!empty($params['view_target'])) {
            $response_data['view_type'] = $params['view_type'] ?? '';
        }

        if ($params['view_target'] === 'single') {
            $response_data['if_single'] = $params['if_single'] ?? '';
        }
    }

    return new WP_REST_Response($response_data, 200);
}

function delete_agnostic_view($request)
{
    $post_id = $request['id'];

    if (get_post_type($post_id) !== 'agnostic_view') {
        return new WP_Error('invalid-view', 'The specified view does not exist', array('status' => 404));
    }

    $result = wp_delete_post($post_id, true);

    if (!$result) {
        return new WP_Error('failed-to-delete', 'Failed to delete the view', array('status' => 500));
    }

    return new WP_REST_Response(null, 204);
}

function get_view_type_options()
{
    $options = array(
        '404_page' => array(
            'label' => '404 Page',
            'svg_url' => get_template_directory_uri() . '/views/admin/svg/404 page.svg',
        ),
        'archive' => array(
            'label' => 'Archive',
            'svg_url' => get_template_directory_uri() . '/views/admin/svg/Archive.svg',
        ),
        'footer' => array(
            'label' => 'Footer',
            'svg_url' => get_template_directory_uri() . '/views/admin/svg/Footer.svg',
        ),
        'header' => array(
            'label' => 'Header',
            'svg_url' => get_template_directory_uri() . '/views/admin/svg/Header.svg',
        ),
        'product' => array(
            'label' => 'Product',
            'svg_url' => get_template_directory_uri() . '/views/admin/svg/Product.svg',
        ),
        'products_archive' => array(
            'label' => 'Products Archive',
            'svg_url' => get_template_directory_uri() . '/views/admin/svg/Products Archive.svg',
        ),
        'search_results' => array(
            'label' => 'Search Results',
            'svg_url' => get_template_directory_uri() . '/views/admin/svg/search results page.svg',
        ),
        'single_page' => array(
            'label' => 'Single Page',
            'svg_url' => get_template_directory_uri() . '/views/admin/svg/Single Page.svg',
        ),
        'single_post' => array(
            'label' => 'Single Post',
            'svg_url' => get_template_directory_uri() . '/views/admin/svg/Single Post.svg',
        ),
    );

    return new WP_REST_Response($options, 200);
}

function get_grouped_agnostic_views()
{
    $args = array(
        'post_type' => 'agnostic_view',
        'posts_per_page' => -1,
    );

    $views = get_posts($args);
    $grouped_views = array();

    foreach ($views as $view) {
        $categories = wp_get_post_terms($view->ID, 'component_category');
        $types = wp_get_post_terms($view->ID, 'component_type');

        if (!empty($categories) && !empty($types)) {
            $category = $categories[0]->slug; // Assuming we're using the first category

            if (!isset($grouped_views[$category])) {
                $grouped_views[$category] = array();
            }

            foreach ($types as $type) {
                if (!isset($grouped_views[$category][$type->slug])) {
                    $grouped_views[$category][$type->slug] = 0;
                }
                $grouped_views[$category][$type->slug]++;
            }
        }
    }

    // Reformat the data to match the desired structure
    $formatted_views = array();
    foreach ($grouped_views as $category => $types) {
        $formatted_views[$category] = array_map(function ($name, $count) {
            return array('name' => $name, 'count' => $count);
        }, array_keys($types), $types);
    }

    return new WP_REST_Response($formatted_views, 200);
}

function get_component_types_for_category($request)
{
    $category = $request['category'];

    $args = array(
        'post_type' => 'agnostic_view',
        'posts_per_page' => -1,
        'tax_query' => array(
            array(
                'taxonomy' => 'component_category',
                'field' => 'slug',
                'terms' => $category,
            ),
        ),
    );

    $views = get_posts($args);
    $types = array();

    foreach ($views as $view) {
        $view_types = wp_get_post_terms($view->ID, 'component_type');
        foreach ($view_types as $type) {
            if (!isset($types[$type->slug])) {
                $types[$type->slug] = 0;
            }
            $types[$type->slug]++;
        }
    }

    return new WP_REST_Response($types, 200);
}

function get_components_for_category_and_type($request)
{
    $category = $request['category'];
    $type = $request['type'];

    $args = array(
        'post_type' => 'agnostic_view',
        'posts_per_page' => -1,
        'tax_query' => array(
            'relation' => 'AND',
            array(
                'taxonomy' => 'component_category',
                'field' => 'slug',
                'terms' => $category,
            ),
            array(
                'taxonomy' => 'component_type',
                'field' => 'slug',
                'terms' => $type,
            ),
        ),
    );

    $views = get_posts($args);
    $components = array();

    foreach ($views as $view) {
        $components[] = array(
            'id' => $view->ID,
            'title' => $view->post_title,
        );
    }

    return new WP_REST_Response($components, 200);
}

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

// Function to add .twig as an editable file type in WordPress
function add_twig_mime_type($mime_types)
{
    $mime_types['twig'] = 'text/plain'; // Treat .twig files as plain text
    return $mime_types;
}
add_filter('upload_mimes', 'add_twig_mime_type');

// Function to allow .twig file uploads
function allow_twig_upload($data, $file, $filename, $mimes)
{
    $wp_filetype = wp_check_filetype($filename, $mimes);

    if ($wp_filetype['ext'] === false && preg_match('/\.twig$/i', $filename)) {
        $wp_filetype['ext'] = 'twig';
        $wp_filetype['type'] = 'text/plain';
    }

    return array(
        'ext' => $wp_filetype['ext'],
        'type' => $wp_filetype['type'],
        'proper_filename' => $filename,
    );
}
add_filter('wp_check_filetype_and_ext', 'allow_twig_upload', 10, 4);

// Function to display .twig files in the media library
function display_twig_in_media_library($result, $path)
{
    if (preg_match('/\.twig$/i', $path)) {
        $result = true;
    }
    return $result;
}
add_filter('file_is_displayable_image', 'display_twig_in_media_library', 10, 2);
