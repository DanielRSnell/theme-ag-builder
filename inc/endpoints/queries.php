<?php

// Register REST API routes
add_action('rest_api_init', function () {
    register_rest_route('agnostic/v1', '/queries/form', array(
        'methods' => 'GET',
        'callback' => 'get_query_form_data',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        },
    ));

    register_rest_route('agnostic/v1', '/taxonomy-terms/(?P<taxonomy>[a-zA-Z0-9_-]+)', array(
        'methods' => 'GET',
        'callback' => 'get_taxonomy_terms',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        },
    ));

    register_rest_route('agnostic/v1', '/queries', array(
        'methods' => 'GET',
        'callback' => 'get_agnostic_queries',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        },
    ));

    register_rest_route('agnostic/v1', '/queries', array(
        'methods' => 'POST',
        'callback' => 'create_agnostic_query',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        },
    ));

    register_rest_route('agnostic/v1', '/queries/(?P<id>\d+)', array(
        'methods' => 'PUT',
        'callback' => 'update_agnostic_query',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        },
    ));

    register_rest_route('agnostic/v1', '/queries/batch', array(
        'methods' => 'POST',
        'callback' => 'batch_create_update_agnostic_queries',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        },
    ));

    register_rest_route('agnostic/v1', '/queries/(?P<id>\d+)', array(
        'methods' => 'DELETE',
        'callback' => 'delete_agnostic_query',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        },
    ));

    register_rest_route('agnostic/v1', '/queries/toggle-active', array(
        'methods' => 'POST',
        'callback' => 'toggle_query_active_state',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        },
    ));
});

// Add this function to your PHP file
function get_taxonomy_terms($request)
{
    $taxonomy = $request['taxonomy'];

    if (!taxonomy_exists($taxonomy)) {
        return new WP_Error('invalid_taxonomy', 'The specified taxonomy does not exist.', array('status' => 404));
    }

    $terms = get_terms(array(
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
    ));

    if (is_wp_error($terms)) {
        return new WP_Error('terms_fetch_error', $terms->get_error_message(), array('status' => 500));
    }

    $formatted_terms = array_map(function ($term) {
        return array(
            'term_id' => $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
        );
    }, $terms);

    return new WP_REST_Response($formatted_terms, 200);
}

function get_query_form_data()
{
    $data = array(
        'query_types' => array(
            'posts' => 'Posts',
            'terms' => 'Terms',
            'users' => 'Users',
        ),
        'post_types' => get_post_types(array('public' => true), 'objects'),
        'taxonomies' => get_taxonomies(array('public' => true), 'objects'),
        'user_roles' => wp_roles()->get_names(),
        'order_by_options' => array(
            'date' => 'Date',
            'title' => 'Title',
            'modified' => 'Modified',
            'rand' => 'Random',
        ),
        'order_options' => array(
            'DESC' => 'Descending',
            'ASC' => 'Ascending',
        ),
        'meta_keys' => get_registered_meta_keys('post'),
        'custom_fields' => ag_get_custom_fields(),
    );

    return new WP_REST_Response($data, 200);
}

function ag_get_custom_fields()
{
    global $wpdb;
    $custom_fields = $wpdb->get_col("
        SELECT DISTINCT meta_key
        FROM $wpdb->postmeta
        WHERE meta_key NOT LIKE '\_%'
        ORDER BY meta_key
    ");
    return array_combine($custom_fields, $custom_fields);
}

function get_agnostic_queries($request)
{
    $template_id = $request->get_param('template_id');
    if (empty($template_id)) {
        return new WP_Error('missing_template_id', 'Template ID is required', array('status' => 400));
    }

    $terms = get_terms(array(
        'taxonomy' => 'agnostic_queries',
        'hide_empty' => false,
    ));

    if (is_wp_error($terms)) {
        return new WP_Error('no_queries', 'No queries found', array('status' => 404));
    }

    $queries = array();
    foreach ($terms as $term) {
        $query_json = carbon_get_term_meta($term->term_id, 'ag_query_json');
        $is_active = has_term($term->term_id, 'agnostic_queries', $template_id);
        $query_data = json_decode($query_json, true);
        $queries[] = array(
            'id' => $term->term_id,
            'name' => $term->name,
            'type' => $query_data['type'] ?? '',
            'query_json' => $query_data,
            'isActive' => $is_active,
        );
    }

    return new WP_REST_Response($queries, 200);
}

function create_agnostic_query($request)
{
    $params = $request->get_params();

    if (empty($params['name']) || empty($params['type']) || empty($params['query_json'])) {
        return new WP_Error('missing_fields', 'Name, type, and query JSON are required', array('status' => 400));
    }

    $term = wp_insert_term($params['name'], 'agnostic_queries');

    if (is_wp_error($term)) {
        return new WP_Error('term_creation_failed', $term->get_error_message(), array('status' => 500));
    }

    $term_id = $term['term_id'];
    $query_json = $params['query_json'];
    $query_json['type'] = $params['type'];
    carbon_set_term_meta($term_id, 'ag_query_json', wp_json_encode($query_json));

    if (isset($params['template_id']) && $params['isActive']) {
        wp_set_object_terms($params['template_id'], $term_id, 'agnostic_queries', true);
    }

    return new WP_REST_Response(array(
        'id' => $term_id,
        'name' => $params['name'],
        'type' => $params['type'],
        'query_json' => $query_json,
        'isActive' => $params['isActive'] ?? false,
        'params' => $params,
    ), 201);
}

function update_agnostic_query($request)
{
    $params = $request->get_params();
    $term_id = $params['id'];

    if (empty($params['name']) || empty($params['type']) || empty($params['query_json'])) {
        return new WP_Error('missing_fields', 'Name, type, and query JSON are required', array('status' => 400));
    }

    $term = get_term($term_id, 'agnostic_queries');
    if (is_wp_error($term) || !$term) {
        return new WP_Error('term_not_found', 'Query not found', array('status' => 404));
    }

    wp_update_term($term_id, 'agnostic_queries', array('name' => $params['name']));
    $query_json = $params['query_json'];
    $query_json['type'] = $params['type'];
    carbon_set_term_meta($term_id, 'ag_query_json', wp_json_encode($query_json));

    if (isset($params['template_id'])) {
        if ($params['isActive']) {
            wp_set_object_terms($params['template_id'], $term_id, 'agnostic_queries', true);
        } else {
            wp_remove_object_terms($params['template_id'], $term_id, 'agnostic_queries');
        }
    }

    return new WP_REST_Response(array(
        'id' => $term_id,
        'name' => $params['name'],
        'type' => $params['type'],
        'query_json' => $query_json,
        'isActive' => $params['isActive'] ?? false,
    ), 200);
}

function batch_create_update_agnostic_queries($request)
{
    $params = $request->get_params();

    if (!is_array($params['queries'])) {
        return new WP_Error('invalid_input', 'Queries must be an array', array('status' => 400));
    }

    $results = array();

    foreach ($params['queries'] as $query) {
        if (isset($query['id'])) {
            $update_request = new WP_REST_Request('PUT', '/agnostic/v1/queries/' . $query['id']);
            $update_request->set_body_params($query);
            $result = update_agnostic_query($update_request);
        } else {
            $create_request = new WP_REST_Request('POST', '/agnostic/v1/queries');
            $create_request->set_body_params($query);
            $result = create_agnostic_query($create_request);
        }

        $results[] = $result;
    }

    return new WP_REST_Response($results, 200);
}

function delete_agnostic_query($request)
{
    $term_id = $request['id'];

    $result = wp_delete_term($term_id, 'agnostic_queries');

    if (is_wp_error($result)) {
        return new WP_Error('delete_failed', $result->get_error_message(), array('status' => 500));
    } elseif (!$result) {
        return new WP_ERROR('term_not_found', 'Query not found', array('status' => 404));
    }

    return new WP_REST_Response(null, 204);
}

function toggle_query_active_state($request)
{
    $params = $request->get_params();
    $query_id = $params['query_id'];
    $template_id = $params['template_id'];
    $active = $params['active'];

    if (empty($query_id) || empty($template_id)) {
        return new WP_Error('missing_fields', 'Query ID and Template ID are required', array('status' => 400));
    }

    $term = get_term($query_id, 'agnostic_queries');
    if (is_wp_error($term) || !$term) {
        return new WP_Error('term_not_found', 'Query not found', array('status' => 404));
    }

    if ($active) {
        $result = wp_set_object_terms($template_id, $term->term_id, 'agnostic_queries', true);
    } else {
        $result = wp_remove_object_terms($template_id, $term->term_id, 'agnostic_queries');
    }

    if (is_wp_error($result)) {
        return new WP_Error('update_failed', $result->get_error_message(), array('status' => 500));
    }

    return new WP_REST_Response(array(
        'success' => true,
        'message' => $active ? 'Query activated' : 'Query deactivated',
    ), 200);
}
