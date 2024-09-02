<?php
// Add this to your functions.php or create a custom plugin

// Register the API routes
add_action('rest_api_init', function () {
    register_rest_route('agnostic/v1', '/themes', array(
        'methods' => 'GET',
        'callback' => 'get_all_themes',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        },
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

    // If the themes array is empty, add the default theme
    if (empty($themes)) {
        $default_theme = array(
            "name" => "Brand",
            "config" => array(
                "--rounded-box" => "1rem",
                "--rounded-btn" => "0.5rem",
                "--rounded-badge" => "1.9rem",
                "--animation-btn" => "0.25s",
                "--animation-input" => "0.2s",
                "--btn-focus-scale" => "0.95",
                "--border-btn" => "1px",
                "--tab-border" => "1px",
                "--tab-radius" => "0.5rem",
            ),
            "colors" => array(
                "primary" => "#570DF8",
                "primary-focus" => "#4506CB",
                "primary-content" => "#ffffff",
                "secondary" => "#F000B8",
                "secondary-focus" => "#BD0091",
                "secondary-content" => "#ffffff",
                "accent" => "#37CDBE",
                "accent-focus" => "#2AA79B",
                "accent-content" => "#ffffff",
                "neutral" => "#3D4451",
                "neutral-focus" => "#2A2E37",
                "neutral-content" => "#ffffff",
                "base-100" => "#ffffff",
                "base-200" => "#F2F2F2",
                "base-300" => "#E5E6E6",
                "base-content" => "#1F2937",
                "info" => "#3ABFF8",
                "info-content" => "#002B3D",
                "success" => "#36D399",
                "success-content" => "#003320",
                "warning" => "#FBBD23",
                "warning-content" => "#382800",
                "error" => "#F87272",
                "error-content" => "#470000",
            ),
        );
        $themes = array($default_theme);
        // save the default theme
        update_option('agnostic_themes', $themes);

        // now get the themes again
        $themes = get_option('agnostic_themes', array());
    }

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

function get_tw_themes($request)
{
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
    $tailwind_themes = array_map(function ($theme) {
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
            ),
        ];
    }, $daisy_themes);

    return new WP_REST_Response($tailwind_themes, 200);
}

function ag_get_themes()
{
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
    $tailwind_themes = array_map(function ($theme) {
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
            ),
        ];
    }, $daisy_themes);

    return $tailwind_themes;
}
