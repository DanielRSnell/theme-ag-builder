<?php
// Register custom REST API endpoints for Agnostic Views
add_action('rest_api_init', function () {
    // Get all views
    register_rest_route('agnostic/v1', '/views', array(
        'methods' => 'GET',
        'callback' => 'get_agnostic_views',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        },
    ));

    // Create a new view
    register_rest_route('agnostic/v1', '/views', array(
        'methods' => 'POST',
        'callback' => 'create_agnostic_view',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        },
    ));

    // Update an existing view
    register_rest_route('agnostic/v1', '/views/(?P<id>\d+)', array(
        'methods' => 'PUT',
        'callback' => 'update_agnostic_view',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        },
    ));

    // Delete a view
    register_rest_route('agnostic/v1', '/views/(?P<id>\d+)', array(
        'methods' => 'DELETE',
        'callback' => 'delete_agnostic_view',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        },
    ));

    // Get view types
    register_rest_route('agnostic/v1', '/view-types', array(
        'methods' => 'GET',
        'callback' => 'get_view_type_options',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        },
    ));
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
    );

    $post_id = wp_insert_post($post_data);

    if (is_wp_error($post_id)) {
        return new WP_Error('failed-to-create', 'Failed to create the view', array('status' => 500));
    }

    // Update Carbon Fields
    carbon_set_post_meta($post_id, 'view_is', sanitize_text_field($params['view_is'] ?? ''));

    if ($params['view_is'] !== 'component') {
        carbon_set_post_meta($post_id, 'view_target', sanitize_text_field($params['view_target'] ?? ''));

        if (!empty($params['view_target'])) {
            carbon_set_post_meta($post_id, 'view_type', sanitize_text_field($params['view_type'] ?? ''));
        }

        if ($params['view_target'] === 'single') {
            carbon_set_post_meta($post_id, 'ag_if_single', absint($params['if_single'] ?? 0));
        }
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

    // Update Carbon Fields
    carbon_set_post_meta($post_id, 'view_is', sanitize_text_field($params['view_is'] ?? ''));

    if ($params['view_is'] !== 'component') {
        carbon_set_post_meta($post_id, 'view_target', sanitize_text_field($params['view_target'] ?? ''));

        if (!empty($params['view_target'])) {
            carbon_set_post_meta($post_id, 'view_type', sanitize_text_field($params['view_type'] ?? ''));
        } else {
            carbon_delete_post_meta($post_id, 'view_type');
        }

        if ($params['view_target'] === 'single') {
            carbon_set_post_meta($post_id, 'ag_if_single', absint($params['if_single'] ?? 0));
        } else {
            carbon_delete_post_meta($post_id, 'ag_if_single');
        }
    } else {
        carbon_delete_post_meta($post_id, 'view_target');
        carbon_delete_post_meta($post_id, 'view_type');
        carbon_delete_post_meta($post_id, 'ag_if_single');
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
