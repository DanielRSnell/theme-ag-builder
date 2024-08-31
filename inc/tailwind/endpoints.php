<?php

// AJAX ENDPOINTS
// Get Tailwind Config
add_action('wp_ajax_get_tailwind_config', 'get_tailwind_config');
// Remove this line if you don't need non-logged in users to access this
// add_action('wp_ajax_nopriv_get_tailwind_config', 'get_tailwind_config');

function get_tailwind_config()
{
    // Uncomment and use nonce verification if you're using nonces
    // if (!check_ajax_referer('tailwind_nonce', 'nonce', false)) {
    //     wp_send_json_error('Nonce verification failed', 403);
    //     wp_die();
    // }

    $upload_dir = wp_upload_dir();
    $config_file = $upload_dir['basedir'] . '/agnostic/tailwind.config.js';

    if (file_exists($config_file) && is_readable($config_file)) {
        $content = file_get_contents($config_file);
        if ($content !== false) {
            wp_send_json_success($content);
        } else {
            wp_send_json_error('Failed to read the Tailwind config file.', 500);
        }
    } else {
        wp_send_json_error('Tailwind config file not found or not readable.', 404);
    }

    wp_die(); // This is required to terminate immediately and return a proper response
}

// Update Tailwind Config
add_action('wp_ajax_update_tailwind_config', 'update_tailwind_config');

function update_tailwind_config()
{
    // check_ajax_referer('tailwind_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to perform this action.');
    }

    $upload_dir = wp_upload_dir();
    $config_file = $upload_dir['basedir'] . '/agnostic/tailwind.config.js';
    $content = isset($_POST['content']) ? stripslashes($_POST['content']) : '';

    // Additional content validation could be added here

    if (file_put_contents($config_file, $content) !== false) {
        wp_send_json_success('Tailwind config updated successfully.');
    } else {
        wp_send_json_error('Failed to update Tailwind config. Check file permissions.');
    }
}

// Get Tailwind CSS
add_action('wp_ajax_get_tailwind_css', 'get_tailwind_css');
// Remove this line if you don't need non-logged in users to access this
// add_action('wp_ajax_nopriv_get_tailwind_css', 'get_tailwind_css');

function get_tailwind_css()
{
    // check_ajax_referer('tailwind_nonce', 'nonce');

    $upload_dir = wp_upload_dir();
    $css_file = $upload_dir['basedir'] . '/agnostic/app.css';

    if (file_exists($css_file) && is_readable($css_file)) {
        wp_send_json_success(file_get_contents($css_file));
    } else {
        wp_send_json_error('Tailwind CSS file not found or not readable.');
    }
}

// Update Tailwind CSS
add_action('wp_ajax_update_tailwind_css', 'update_tailwind_css');

function update_tailwind_css()
{
    // check_ajax_referer('tailwind_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to perform this action.');
    }

    $upload_dir = wp_upload_dir();
    $css_file = $upload_dir['basedir'] . '/agnostic/app.css';
    $content = isset($_POST['content']) ? stripslashes($_POST['content']) : '';

    // Additional content validation could be added here

    if (file_put_contents($css_file, $content) !== false) {
        wp_send_json_success('Tailwind CSS updated successfully.');
    } else {
        wp_send_json_error('Failed to update Tailwind CSS. Check file permissions.');
    }
}

// REST API Endpoints

// Register REST API routes
add_action('rest_api_init', function () {
    // Get Tailwind Config
    register_rest_route('agnostic/v1', '/tailwind-config', array(
        'methods' => 'GET',
        'callback' => 'get_tailwind_config_rest',
        'permission_callback' => function () {
            return current_user_can('manage_options');
        },
    ));

    // Update Tailwind Config
    register_rest_route('agnostic/v1', '/tailwind-config', array(
        'methods' => 'POST',
        'callback' => 'update_tailwind_config_rest',
        'permission_callback' => function () {
            return current_user_can('manage_options');
        },
    ));

    // Get Tailwind CSS
    register_rest_route('agnostic/v1', '/tailwind-css', array(
        'methods' => 'GET',
        'callback' => 'get_tailwind_css_rest',
        'permission_callback' => function () {
            return current_user_can('manage_options');
        },
    ));

    // Update Tailwind CSS
    register_rest_route('agnostic/v1', '/tailwind-css', array(
        'methods' => 'POST',
        'callback' => 'update_tailwind_css_rest',
        'permission_callback' => function () {
            return current_user_can('manage_options');
        },
    ));

    register_rest_route('agnostic/v1', '/tw-objects', array(
        'methods' => 'GET',
        'callback' => 'get_tw_objects_endpoint',

    ));
});

function get_tw_objects()
{
    $upload_dir = wp_upload_dir();
    $agnostic_dir = $upload_dir['basedir'] . '/agnostic';
    $css_file = $agnostic_dir . '/app.css';
    $config_file = $agnostic_dir . '/tailwind.config.js';

    $objects = array();

    // Get the CSS file content
    if (file_exists($css_file) && is_readable($css_file)) {
        $objects['css'] = file_get_contents($css_file);
    } else {
        return new WP_Error('file_not_found', 'Tailwind CSS file not found or not readable.', array('status' => 404));
    }

    // Get the Config file content
    if (file_exists($config_file) && is_readable($config_file)) {
        $objects['config'] = file_get_contents($config_file);
    } else {
        return new WP_Error('file_not_found', 'Tailwind config file not found or not readable.', array('status' => 404));
    }

    // Generate font CSS and config
    $designations = get_option('ag_font_designations', array());
    $font_css = generate_font_css($designations);
    $font_config = array();

    foreach ($designations as $designation => $fonts) {
        if (!empty($fonts)) {
            $font_config[$designation] = $fonts;
        }
    }

    // Convert font config to JSON string
    $fonts_string = json_encode($font_config);
    if ($fonts_string === false) {
        return new WP_Error('json_encode_failed', 'Failed to encode font config as JSON.', array('status' => 500));
    }

    // Get AgnosticThemes
    $objects['AgnosticThemes'] = ag_get_themes();

    // Convert AgnosticThemes to JSON string
    $themes_json = json_encode($objects['AgnosticThemes']);
    if ($themes_json === false) {
        return new WP_Error('json_encode_failed', 'Failed to encode AgnosticThemes as JSON.', array('status' => 500));
    }

    // Replace the placeholders in the config
    $objects['config'] = str_replace('AgnosticFonts', $fonts_string, $objects['config']);
    $objects['config'] = str_replace('[...AgnosticThemes]', $themes_json, $objects['config']);

    // Replace the placeholder in the CSS
    $objects['css'] = str_replace('@agnosticfonts', $font_css, $objects['css']);

    return $objects;
}

function get_tw_objects_endpoint($request)
{
    $objects = get_tw_objects();
    if (is_wp_error($objects)) {
        return $objects;
    }
    return new WP_REST_Response($objects, 200);
}

function get_tailwind_config_rest(WP_REST_Request $request)
{
    $upload_dir = wp_upload_dir();
    $config_file = $upload_dir['basedir'] . '/agnostic/tailwind.config.js';

    if (file_exists($config_file) && is_readable($config_file)) {
        $content = file_get_contents($config_file);
        if ($content !== false) {
            return new WP_REST_Response($content, 200);
        } else {
            return new WP_Error('read_error', 'Failed to read the Tailwind config file.', array('status' => 500));
        }
    } else {
        return new WP_Error('file_not_found', 'Tailwind config file not found or not readable.', array('status' => 404));
    }
}

function update_tailwind_config_rest(WP_REST_Request $request)
{
    $upload_dir = wp_upload_dir();
    $config_file = $upload_dir['basedir'] . '/agnostic/tailwind.config.js';
    $content = $request->get_body();

    if (file_put_contents($config_file, $content) !== false) {
        return new WP_REST_Response('Tailwind config updated successfully.', 200);
    } else {
        return new WP_Error('update_failed', 'Failed to update Tailwind config. Check file permissions.', array('status' => 500));
    }
}

function get_tailwind_css_rest(WP_REST_Request $request)
{
    $upload_dir = wp_upload_dir();
    $css_file = $upload_dir['basedir'] . '/agnostic/app.css';

    if (file_exists($css_file) && is_readable($css_file)) {
        $content = file_get_contents($css_file);
        if ($content !== false) {
            return new WP_REST_Response($content, 200);
        } else {
            return new WP_Error('read_error', 'Failed to read the Tailwind CSS file.', array('status' => 500));
        }
    } else {
        return new WP_Error('file_not_found', 'Tailwind CSS file not found or not readable.', array('status' => 404));
    }
}

function update_tailwind_css_rest(WP_REST_Request $request)
{
    $upload_dir = wp_upload_dir();
    $css_file = $upload_dir['basedir'] . '/agnostic/app.css';
    $content = $request->get_body();

    if (file_put_contents($css_file, $content) !== false) {
        return new WP_REST_Response('Tailwind CSS updated successfully.', 200);
    } else {
        return new WP_Error('update_failed', 'Failed to update Tailwind CSS. Check file permissions.', array('status' => 500));
    }
}
