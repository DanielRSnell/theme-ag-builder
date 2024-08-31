<?php

function agnostic_rest_token()
{
    return wp_create_nonce('wp_rest');
}

/**
 * Agnostic Twig Implementation
 *
 * This script provides both a REST API endpoint and an AJAX function
 * for rendering Twig templates with Timber in WordPress.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Register the REST API route
add_action('rest_api_init', function () {
    register_rest_route('agnostic/v1', '/render', array(
        'methods' => 'GET,POST',
        'callback' => 'handle_agnostic_twig_rest',
        'permission_callback' => function () {
            // no permissions required
            return true;
        },
        'args' => array(
            'post_id' => array(
                'required' => true,
                'validate_callback' => function ($param, $request, $key) {
                    return is_numeric($param);
                },
            ),
            'html' => array(
                'required' => false,
            ),
            'php' => array(
                'required' => false,
            ),
        ),
    ));

    // Route for getting post content
    register_rest_route('agnostic/v1', '/post-content', array(
        'methods' => 'POST',
        'callback' => 'agnostic_get_post_content_callback',
        'permission_callback' => function () {
            return true; // Adjust this based on your permission requirements
        },
        'args' => array(
            'post_id' => array(
                'required' => true,
                'validate_callback' => function ($param, $request, $key) {
                    return is_numeric($param);
                },
            ),
        ),
    ));

// Route for saving post content
    register_rest_route('agnostic/v1', '/save-post-content', array(
        'methods' => 'POST',
        'callback' => 'agnostic_save_post_content_callback',
        'permission_callback' => function () {
            return current_user_can('edit_posts'); // Ensure user has permission to edit posts
        },
        'args' => array(
            'post_id' => array(
                'required' => true,
                'validate_callback' => function ($param, $request, $key) {
                    return is_numeric($param);
                },
            ),
            'content' => array(
                'required' => true,
            ),
            'php' => array(
                'required' => false,
            ),
        ),
    ));

});

function handle_agnostic_twig_rest($request)
{
    // Ensure proper UTF-8 handling
    ini_set('default_charset', 'UTF-8');

    $params = $request->get_params();
    $post_id = intval($params['post_id']);

    $context = Timber::context();
    $context['populate'] = $params['populate'] ?? null;
    // Handle populate data if present
    if (isset($params['populate']) && is_array($params['populate'])) {
        $context = prepareBuilderContext($context, $params['populate']);
    } else {
        $context['post'] = Timber::get_post($post_id);
    }

    $queries = get_agnostic_queries_for_view($params['post_id']);
    foreach ($queries as $query) {
        $context_key = str_replace('-', '_', $query['slug']);
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
                    $context[$context_key] = null;
                    error_log("Unknown query type: " . $parsed_query['type']);
            }
        } catch (Exception $e) {
            error_log("Error executing query for {$context_key}: " . $e->getMessage());
            $context[$context_key] = null;
        }
    }

    $php_error = null;

    if (isset($params['php']) && !empty($params['php'])) {
        $php_code = $params['php'];

        ob_start();
        try {
            $temp_context = $context;
            eval($php_code);
            $context = array_merge($context, array_diff_key(get_defined_vars(), ['context' => null, 'temp_context' => null, 'php_code' => null, 'params' => null]));
        } catch (Throwable $e) {
            $php_error = $e->getMessage();
        }
        $php_output = ob_get_clean();

        if ($php_output) {
            $context['php_output'] = $php_output;
        }
    }

    if ($request->get_method() === 'GET') {
        $post = $context['post'];
        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found', array('status' => 404));
        }
        $html = $post->post_content;
    } else {
        if (!isset($params['html'])) {
            return new WP_Error('missing_html', 'Missing HTML parameter', array('status' => 400));
        }
        $html = $params['html'];
    }

    $context = populateCustomFieldsForInspector($context);

    $context['state'] = $context;

    $script = '<script id="state-manager" type="application/json">' . json_encode($context['state'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</script>';

    $html .= $script;

    $html = do_shortcode($html);

    try {
        $compiled_html = Timber::compile_string($html, $context);

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML(mb_convert_encoding($compiled_html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $state_script = $dom->getElementById('state-manager');
        $state_json = $state_script ? json_decode($state_script->textContent, true) : null;

        if ($state_script) {
            $state_script->parentNode->removeChild($state_script);
        }

        $updated_html = $dom->saveHTML();

        $response_data = [
            'html' => $updated_html,
            'state' => $state_json,
            'queries' => wp_get_object_terms($post_id, 'agnostic_queries'),
            'id' => $post_id,
        ];

        if ($php_error !== null) {
            $response_data['php_error'] = $php_error;
        }

        $response = new WP_REST_Response($response_data, 200);
        $response->set_headers(['Content-Type' => 'application/json; charset=utf-8']);
        return $response;

    } catch (Exception $e) {
        return new WP_Error('compilation_error', $e->getMessage(), array('status' => 500));
    }
}

function populateCustomFieldsForInspector($context)
{
    if (!function_exists('get_fields')) {
        return $context; // ACF is not active, return context unchanged
    }

    foreach ($context as $key => $value) {
        if (is_object($value)) {
            $context[$key] = addCustomFieldsToObject($value);
        }
    }

    return $context;
}

function addCustomFieldsToObject($object)
{
    if (isset($object->ID) || isset($object->id) || isset($object->term_id)) {
        $id = isset($object->ID) ? $object->ID : (isset($object->id) ? $object->id : $object->term_id);
        $customFields = get_fields($id);
        if ($customFields && is_array($customFields)) {
            foreach ($customFields as $fieldKey => $fieldValue) {
                $object->$fieldKey = $fieldValue;
            }
        }
    }

    return $object;
}

// Basic Logic
// if ($params['populate']['if_single']) {
//     $post_id = intval($params['populate']['if_single']);
// }
// $context['post'] = Timber::get_post($post_id);

// // if get_fields function exists add acf fields for the post to $context['post']

// Create a frontend context for the builder
function prepareBuilderContext($context, $populate)
{
    // Match population to a view type and target
    $view_is = $populate['view_is'];
    $view_target = $populate['view_target'];
    $view_type = $populate['view_type'];
    $if_single = $populate['if_single'];

    // Get a router type based on the view target and view type
    $router_type = getRouterType($view_target, $view_type);
    // $context['preflight'] = $router_type;

    // Prepare the context based on the router type
    $prepared_context = ag_prepare_context($context, null, $router_type, true, $if_single);

    // If it's a single view, we need to set the post
    if ($router_type === 'single' || $router_type === 'product') {
        $prepared_context['post'] = Timber::get_post($if_single);
    }

    return $prepared_context;
}

function getRouterType($view_target, $view_type)
{
    switch ($view_target) {
        case 'single':
            if ($view_type === 'product') {
                return 'product';
            }

            if (in_array($view_type, ['product', 'product_cat', 'product_tag'])) {
                return 'product_term';
            }

            return 'single';
        case 'archive':
            if (in_array($view_type, ['product', 'product_cat', 'product_tag'])) {
                return 'product_term';
            }
            return 'archive';
        case 'term':
            if (in_array($view_type, ['product_cat', 'product_tag'])) {
                return 'product_term';
            }
            return 'archive';
        case 'front_page':
            return 'front_page';
        case 'search':
            return 'search';
        case 'cart':
            return 'cart';
        case 'checkout':
            return 'checkout';
        case 'account':
            return 'account';
        case '404':
            return 'error';
        default:
            // Handle special cases
            switch ($view_type) {
                case 'shop':
                    return 'shop';
                case 'cart':
                    return 'cart';
                case 'checkout':
                    return 'checkout';
                case 'account':
                    return 'account';
                case 'order_confirmation':
                    return 'order_confirmation';
                case 'mini_cart':
                    return 'mini_cart';
                default:
                    return 'default';
            }
    }
}

function get_agnostic_queries_for_view($post_id)
{
    $query_terms = wp_get_object_terms($post_id, 'agnostic_queries');
    $queries = array();

    foreach ($query_terms as $term) {
        $query_json = carbon_get_term_meta($term->term_id, 'ag_query_json');
        $query_data = json_decode($query_json, true);

        if ($query_data === null && json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error for term {$term->term_id}: " . json_last_error_msg());
            continue; // Skip this term if JSON is invalid
        }

        $queries[] = array(
            'name' => $term->name,
            'slug' => $term->slug,
            'query_json' => $query_data,
        );
    }

    return $queries;
}

function ag_parse_query($query_json)
{
    if (!is_array($query_json)) {
        $query_json = json_decode($query_json, true);
    }

    if (!$query_json || !isset($query_json['type']) || !isset($query_json['options'])) {
        return null; // Invalid query structure
    }

    $type = $query_json['type'];
    $args = $query_json['options']['basic'];

    // Transform keys for WordPress compatibility
    $key_transforms = [
        'postType' => 'post_type',
        'postsPerPage' => 'posts_per_page',
        'orderBy' => 'orderby',
    ];

    foreach ($key_transforms as $old_key => $new_key) {
        if (isset($args[$old_key])) {
            $args[$new_key] = $args[$old_key];
            unset($args[$old_key]);
        }
    }

    // Handle excludeCurrent
    if (isset($args['excludeCurrent']) && $args['excludeCurrent']) {
        $args['post__not_in'] = [get_the_ID()];
    }
    unset($args['excludeCurrent']);

    // Handle filters (taxonomy and meta queries)
    if (!empty($query_json['options']['filters'])) {
        $tax_query = [];
        $meta_query = [];

        foreach ($query_json['options']['filters'] as $filter) {
            if ($filter['type'] === 'taxonomy') {
                $tax_query[] = [
                    'taxonomy' => $filter['field'],
                    'field' => 'term_id',
                    'terms' => $filter['value'],
                    'operator' => $filter['operator'],
                ];
            } elseif ($filter['type'] === 'meta') {
                $meta_query[] = [
                    'key' => $filter['field'],
                    'value' => $filter['value'],
                    'compare' => $filter['operator'],
                ];
            }
        }

        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }
        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }
    }

    // Handle custom fields
    if (!empty($query_json['options']['customFields'])) {
        foreach ($query_json['options']['customFields'] as $custom_field) {
            $args['meta_query'][] = [
                'key' => $custom_field['key'],
                'value' => $custom_field['value'],
                'compare' => '=',
            ];
        }
    }

    // Type-specific adjustments
    switch ($type) {
        case 'posts':
            // Additional post-specific adjustments if needed
            break;
        case 'terms':
            if (isset($args['taxonomy']) && empty($args['taxonomy'])) {
                $args['taxonomy'] = 'category'; // Default to category if empty
            }
            break;
        case 'users':
            if (isset($args['role']) && empty($args['role'])) {
                unset($args['role']); // Remove role if empty to get all users
            }
            break;
    }

    return [
        'type' => $type,
        'args' => $args,
    ];
}

function safe_encode_context($data, $depth = 0, $max_depth = 5)
{
    if ($depth > $max_depth) {
        return 'Max depth reached';
    }

    if (is_array($data) || is_object($data)) {
        $result = [];
        foreach ($data as $key => $value) {
            $result[$key] = safe_encode_context($value, $depth + 1, $max_depth);
        }
        return $result;
    } elseif (is_resource($data)) {
        return 'Resource';
    } elseif ($data instanceof \Closure) {
        return 'Closure';
    } elseif (is_callable($data)) {
        return 'Callable';
    } elseif ($data instanceof \WP_Post) {
        return [
            'ID' => $data->ID,
            'post_title' => $data->post_title,
            'post_type' => $data->post_type,
        ];
    } elseif (is_object($data) && method_exists($data, 'to_array')) {
        return safe_encode_context($data->to_array(), $depth + 1, $max_depth);
    } elseif (is_object($data)) {
        return 'Object of class: ' . get_class($data);
    } else {
        return $data;
    }
}

/**
 * Handle the legacy AJAX request for Agnostic Twig rendering
 */
function handle_agnostic_twig()
{
    // Check nonce for security
    // Uncomment the next line after adding nonce to your AJAX request
    // check_ajax_referer('agnostic_nonce', '_ajax_nonce');

    // Get the data from the AJAX request
    if (!isset($_POST['html']) || !isset($_POST['post_id'])) {
        wp_send_json_error('Missing required parameters', 400);
        return;
    }

    $html = $_POST['html'];
    $post_id = intval($_POST['post_id']);

    // Set up the Timber context
    $context = Timber::context();
    $context['post'] = Timber::get_post($post_id);
    $html = do_shortcode($html);

    try {
        // $compiled_html = Timber::compile_string($html, $context);
        wp_send_json_success([
            'html' => $html,
            'context' => $context,
        ]);
    } catch (Exception $e) {
        wp_send_json_error('Template compilation error: ' . $e->getMessage(), 500);
    }
}

// Hook the AJAX function to WordPress
add_action('wp_ajax_agnostic_twig', 'handle_agnostic_twig');
add_action('wp_ajax_nopriv_agnostic_twig', 'handle_agnostic_twig');

/**
 * Callback function for the get post content REST API endpoint
 */
function agnostic_get_post_content_callback($request)
{
    $post_id = intval($request['post_id']);
    $post = get_post($post_id);

    if (!$post) {
        return new WP_Error('post_not_found', 'Post not found', array('status' => 404));
    }

    $context = Timber::context();
    $context['template'] = $post->post_content;

    $compile = Timber::compile('builder/render/base', $context);

    $view_is = get_post_meta($post_id, '_view_is', true);
    $view_target = get_post_meta($post_id, '_view_target', true);
    $view_type = get_post_meta($post_id, '_view_type', true);
    $if_single = get_post_meta($post_id, '_ag_if_single', true);

    $response_data = array(
        'id' => $post_id,
        'title' => $post->post_title,
        'content' => $compile,
        'php' => get_post_meta($post_id, '_crb_php_view', true) ?: '',
        'js' => get_post_meta($post_id, '_crb_js_view', true) ?: '',
        'view_is' => $view_is ?: '',
        'view_target' => $view_target ?: '',
    );

    if (!empty($view_target)) {
        $response_data['view_type'] = $view_type ?: '';
    }

    if ($view_target === 'single') {
        $response_data['if_single'] = $if_single ?: '';
    }

    return new WP_REST_Response($response_data, 200);
}

/**
 * Callback function for the save post content REST API endpoint
 */
function agnostic_save_post_content_callback($request)
{
    $post_id = intval($request['post_id']);
    $content = $request['content'];

    $updated_post = array(
        'ID' => $post_id,
        'post_content' => $content,
    );

    // Attempt to update the post.
    $result = wp_update_post($updated_post);

    if (is_wp_error($result)) {
        return new WP_Error('update_failed', 'Failed to update post', array('status' => 500));
    }

    // Check if PHP code is being sent
    if ($request->has_param('php')) {
        $php_code = $request['php'];

        // Save PHP code to Carbon field
        carbon_set_post_meta($post_id, 'crb_php_view', $php_code);

        // Update the 'has PHP' indicator
        $has_php = !empty(trim($php_code)) ? 'yes' : '';
        carbon_set_post_meta($post_id, 'crb_has_php', $has_php);
    }

    return new WP_REST_Response(array(
        'message' => 'Post and PHP code updated successfully',
    ), 200);
}
