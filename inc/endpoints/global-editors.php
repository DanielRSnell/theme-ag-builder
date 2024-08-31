<?php
/**
 * WordPress REST API for JavaScript File Management and Splitting
 * Add this code to your theme's functions.php file or in a separate plugin file.
 */

// Register REST API routes
add_action('rest_api_init', function () {
    register_rest_route('agnostic/v1', '/javascript', array(
        array(
            'methods' => 'GET',
            'callback' => 'get_agnostic_javascript',
            // 'permission_callback' => 'agnostic_javascript_permissions_check',
        ),
        array(
            'methods' => 'POST',
            'callback' => 'update_agnostic_javascript',
            'permission_callback' => 'agnostic_javascript_permissions_check',
        ),
    ));
});

// Callback for GET request
function get_agnostic_javascript(WP_REST_Request $request)
{
    $file_path = WP_CONTENT_DIR . '/uploads/agnostic/scripts.js';

    if (!file_exists($file_path)) {
        return new WP_Error('file_not_found', 'JavaScript file not found', array('status' => 404));
    }

    $file_content = file_get_contents($file_path);

    if ($file_content === false) {
        return new WP_Error('file_read_error', 'Unable to read JavaScript file', array('status' => 500));
    }

    $sections = split_javascript_file($file_content);

    return new WP_REST_Response(array(
        'content' => $file_content,
        'sections' => $sections,
    ), 200);
}

// Callback for POST request
function update_agnostic_javascript(WP_REST_Request $request)
{
    $file_path = WP_CONTENT_DIR . '/uploads/agnostic/scripts.js';
    $new_content = $request->get_param('content');

    if (empty($new_content)) {
        return new WP_Error('empty_content', 'Content cannot be empty', array('status' => 400));
    }

    $dir_path = dirname($file_path);
    if (!file_exists($dir_path)) {
        if (!wp_mkdir_p($dir_path)) {
            return new WP_Error('directory_creation_failed', 'Failed to create directory', array('status' => 500));
        }
    }

    $result = file_put_contents($file_path, $new_content);

    if ($result === false) {
        return new WP_Error('file_write_error', 'Unable to write to JavaScript file', array('status' => 500));
    }

    return new WP_REST_Response(array(
        'message' => 'JavaScript file updated successfully',
    ), 200);
}

// Permission callback
function agnostic_javascript_permissions_check(WP_REST_Request $request)
{
    // Check if user can edit theme options
    return current_user_can('edit_theme_options');
}

// Function to split JavaScript file based on comment patterns
function split_javascript_file($content)
{
    $pattern = '/\/\*\s*([^*]+?)\s*\*\//';
    preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);

    $sections = array();
    $lastEnd = 0;

    foreach ($matches[0] as $index => $match) {
        $start = $match[1];
        $name = trim($matches[1][$index][0]);

        if ($start > $lastEnd) {
            $code = trim(substr($content, $lastEnd, $start - $lastEnd));
            if (!empty($code)) {
                $sections[] = array(
                    'name' => 'Uncategorized',
                    'code' => $code,
                );
            }
        }

        $nextStart = isset($matches[0][$index + 1]) ? $matches[0][$index + 1][1] : strlen($content);
        $code = trim(substr($content, $start + strlen($match[0]), $nextStart - ($start + strlen($match[0]))));

        $sections[] = array(
            'name' => $name,
            'code' => $code,
        );

        $lastEnd = $nextStart;
    }

    // Handle any remaining code after the last comment
    if ($lastEnd < strlen($content)) {
        $remainingCode = trim(substr($content, $lastEnd));
        if (!empty($remainingCode)) {
            $sections[] = array(
                'name' => 'Uncategorized',
                'code' => $remainingCode,
            );
        }
    }

    return $sections;
}
