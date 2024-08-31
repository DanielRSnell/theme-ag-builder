<?php
// Add this to your functions.php or create a custom plugin

// Register the API routes
add_action('rest_api_init', function () {
    register_rest_route('agnostic/v1', '/themes', array(
        'methods' => 'GET',
        'callback' => 'get_all_themes',
        // 'permission_callback' => function () {
        //     return current_user_can('edit_posts');
        // },
    ));

    register_rest_route('agnostic/v1', '/themes', array(
        'methods' => 'POST',
        'callback' => 'save_themes',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        },
    ));

    register_rest_route('agnostic/v1', '/tw-themes', array(
        'methods' => 'GET',
        'callback' => 'get_tw_themes',
        // 'permission_callback' => function () {
        //     return current_user_can('edit_posts');
        // },
    ));
});

// Callback function to get all themes
function get_all_themes()
{
    $themes = get_option('agnostic_themes', array());
    return new WP_REST_Response($themes, 200);
}

// Callback function to save themes
function save_themes($request)
{
    $themes = $request->get_json_params();

    if (empty($themes)) {
        return new WP_Error('empty_themes', 'No themes provided', array('status' => 400));
    }

    $update_result = update_option('agnostic_themes', $themes);

    if ($update_result) {
        return new WP_REST_Response(array('message' => 'Themes saved successfully'), 200);
    } else {
        return new WP_Error('save_failed', 'Failed to save themes', array('status' => 500));
    }
}

function get_tw_themes($request) {
    // Get the Daisy UI themes
    $daisy_themes_response = get_all_themes($request);
    
    // Check if we got a valid response
    if (is_wp_error($daisy_themes_response)) {
        return $daisy_themes_response;
    }

    // Extract the actual data from the response
    $daisy_themes = $daisy_themes_response->get_data();
    
    // Ensure we have an array to work with
    if (!is_array($daisy_themes)) {
        return new WP_Error('invalid_data', 'The themes data is not in the expected format.', array('status' => 500));
    }

    // Transform Daisy UI themes to Tailwind format
    $tailwind_themes = array_map(function($theme) {
        $theme_name = strtolower(str_replace(' ', '_', $theme['name']));
        return [
            $theme_name => array_merge(
                $theme['colors'],
                [
                    "--rounded-box" => $theme['config']['--rounded-box'],
                    "--rounded-btn" => $theme['config']['--rounded-btn'],
                    "--rounded-badge" => $theme['config']['--rounded-badge'],
                    "--animation-btn" => $theme['config']['--animation-btn'],
                    "--animation-input" => $theme['config']['--animation-input'],
                    "--btn-focus-scale" => $theme['config']['--btn-focus-scale'],
                    "--border-btn" => $theme['config']['--border-btn'],
                    "--tab-border" => $theme['config']['--tab-border'],
                    "--tab-radius" => $theme['config']['--tab-radius'],
                ]
            )
        ];
    }, $daisy_themes);
    
    return new WP_REST_Response($tailwind_themes, 200);
}

function ag_get_themes() {
    // Get the Daisy UI themes
    $daisy_themes = get_all_themes();
    
    // Check if we got a valid response
    if (is_wp_error($daisy_themes)) {
        error_log("Error getting themes: " . $daisy_themes->get_error_message());
        return array(); // Return an empty array in case of error
    }

    // If $daisy_themes is a WP_REST_Response, extract the data
    if ($daisy_themes instanceof WP_REST_Response) {
        $daisy_themes = $daisy_themes->get_data();
    }
    
    // Ensure we have an array to work with
    if (!is_array($daisy_themes)) {
        error_log("Invalid theme data format");
        return array(); // Return an empty array if data is not in expected format
    }

    // Transform Daisy UI themes to Tailwind format
    $tailwind_themes = array_map(function($theme) {
        $theme_name = strtolower(str_replace(' ', '_', $theme['name']));
        return [
            $theme_name => array_merge(
                $theme['colors'],
                [
                    "--rounded-box" => $theme['config']['--rounded-box'],
                    "--rounded-btn" => $theme['config']['--rounded-btn'],
                    "--rounded-badge" => $theme['config']['--rounded-badge'],
                    "--animation-btn" => $theme['config']['--animation-btn'],
                    "--animation-input" => $theme['config']['--animation-input'],
                    "--btn-focus-scale" => $theme['config']['--btn-focus-scale'],
                    "--border-btn" => $theme['config']['--border-btn'],
                    "--tab-border" => $theme['config']['--tab-border'],
                    "--tab-radius" => $theme['config']['--tab-radius'],
                ]
            )
        ];
    }, $daisy_themes);
    
    return $tailwind_themes;
}