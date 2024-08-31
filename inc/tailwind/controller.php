<?php
// Add this to your theme's functions.php or a custom plugin file
require get_template_directory() . '/inc/tailwind/endpoints.php';
// add_action('template_redirect', 'tailwind_compile_template_redirect');

// function tailwind_compile_template_redirect()
// {
//     // Check if the current URL is /compile/tailwind
//     if ($_SERVER['REQUEST_URI'] == '/agnostic/compile') {
//         // Check if the user is logged in
//         if (!is_user_logged_in()) {
//             // If not logged in, redirect to the login page
//             auth_redirect();
//             exit;
//         }

//         // User is logged in, set the status code to 200
//         status_header(200);

//         // Load the base.php file
//         $base_file = get_template_directory() . '/inc/tailwind/base.php';

//         if (file_exists($base_file)) {
//             // Output buffering to capture any potential headers sent by base.php
//             ob_start();
//             include $base_file;
//             $output = ob_get_clean();

//             // Ensure we're sending HTML
//             header('Content-Type: text/html; charset=utf-8');

//             // Output the contents of base.php
//             echo $output;
//             exit;
//         } else {
//             // File doesn't exist, show an error
//             wp_die('Tailwind base file not found.', 'File Not Found', array('response' => 404));
//         }
//     }
// }

// add_action('template_redirect', 'tailwind_autocomplete_template_redirect');

// function tailwind_autocomplete_template_redirect()
// {
//     // Check if the current URL is /compile/tailwind
//     if ($_SERVER['REQUEST_URI'] == '/agnostic/autocomplete') {
//         // Check if the user is logged in
//         if (!is_user_logged_in()) {
//             // If not logged in, redirect to the login page
//             auth_redirect();
//             exit;
//         }

//         // User is logged in, set the status code to 200
//         status_header(200);

//         // Load the base.php file
//         $base_file = get_template_directory() . '/inc/tailwind/autocomplete.php';

//         if (file_exists($base_file)) {
//             // Output buffering to capture any potential headers sent by base.php
//             ob_start();
//             include $base_file;
//             $output = ob_get_clean();

//             // Ensure we're sending HTML
//             header('Content-Type: text/html; charset=utf-8');

//             // Output the contents of base.php
//             echo $output;
//             exit;
//         } else {
//             // File doesn't exist, show an error
//             wp_die('Tailwind base file not found.', 'File Not Found', array('response' => 404));
//         }
//     }
// }

function get_user_tailwind_config_for_autocomplete()
{
    $config = get_user_tailwind_config_string();

    // Define the safelist pattern to be added
    $safelistPattern = "  safelist: [{ pattern: /./ }],\n";

    // Find the position of 'theme' in the config string
    $position = strpos($config, 'theme');

    if ($position !== false) {
        // Insert the safelist pattern above 'theme'
        $config = substr_replace($config, $safelistPattern, $position, 0);
    } else {
        // If 'theme' is not found, add the safelist pattern at the beginning
        $config = $safelistPattern . $config;
    }

    return $config;
}

function get_user_tailwind_config_string()
{
    $tw_data = get_tw_objects();

    return $tw_data['config'];
}

function get_user_tailwind_css_string()
{
    $tw_data = get_tw_objects();

    return $tw_data['css'];
}

function ag_app_css()
{
    // Get the CSS from /wp-content/uploads/agnostic/app.css
    $upload_dir = wp_upload_dir();
    $agnostic_dir = $upload_dir['basedir'] . '/agnostic';
    $css_file = $agnostic_dir . '/app.css';

    // Check if the file exists
    if (file_exists($css_file)) {
        // Get the URL to the file
        $css_url = $upload_dir['baseurl'] . '/agnostic/app.css';

        // Get the contents of the file and output it
        $css_content = file_get_contents($css_file);
        echo $css_content;

    }
}

add_action('wp_ajax_save_tailwind_css', 'save_tailwind_css');
add_action('wp_ajax_nopriv_save_tailwind_css', 'save_tailwind_css');

function save_tailwind_css()
{
    // Check for nonce security
    if (!wp_verify_nonce($_POST['nonce'], 'save_tailwind_css_nonce')) {
        wp_send_json_error('Invalid nonce');
    }

    $upload_dir = wp_upload_dir();
    $agnostic_dir = $upload_dir['basedir'] . '/agnostic';
    $css_file = $agnostic_dir . '/tailwind.css';

    // Create the directory if it doesn't exist
    if (!file_exists($agnostic_dir)) {
        wp_mkdir_p($agnostic_dir);
    }

    // Get the CSS content from the AJAX request
    $css_content = $_POST['css'];

    // Unescape the CSS content
    $css_content = stripslashes($css_content);

    // Additional unescaping for class names
    // $css_content = preg_replace_callback('/\\\\([^A-Za-z0-9\s])/', function ($matches) {
    //     return $matches[1];
    // }, $css_content);

    // Save the CSS content to the file
    $result = file_put_contents($css_file, $css_content);

    if ($result !== false) {
        wp_send_json_success('CSS saved successfully');
    } else {
        wp_send_json_error('Failed to save CSS');
    }
}

function echo_tailwind_autocomplete_scripts()
{
    $directory = get_template_directory() . '/inc/tailwind/tailwindcss-autocomplete/dist';
    $directory_url = get_template_directory_uri() . '/inc/tailwind/tailwindcss-autocomplete/dist';

    // Check if the directory exists
    if (!is_dir($directory)) {
        echo "Directory not found: $directory";
        return;
    }

    // Open the directory
    if ($handle = opendir($directory)) {
        // Loop through the directory contents
        while (false !== ($entry = readdir($handle))) {
            // Only process JavaScript files
            if (pathinfo($entry, PATHINFO_EXTENSION) === 'js') {
                $fileUrl = $directory_url . '/' . $entry;
                echo '<script id="autocomplete" type="module" src="' . esc_url($fileUrl) . '"></script>' . "\n";
            }
        }
        // Close the directory handle
        closedir($handle);
    } else {
        echo "Unable to open directory: $directory";
    }
}

// function enqueue_agnostic_tailwind_css()
// {
//     // Get the upload directory information
//     $upload_dir = wp_upload_dir();

//     // Construct the path to the tailwind.css file
//     $tailwind_css_path = $upload_dir['basedir'] . '/agnostic/tailwind.css';

//     // Check if the file exists
//     if (file_exists($tailwind_css_path)) {
//         // Construct the URL to the file
//         $tailwind_css_url = $upload_dir['baseurl'] . '/agnostic/tailwind.css';

//         // Enqueue the stylesheet
//         wp_enqueue_style('agnostic-tailwind', $tailwind_css_url, array(), filemtime($tailwind_css_path));

//         // Optionally, you can add a comment in the HTML head for debugging
//         add_action('wp_head', function () {
//             echo "<!-- Agnostic Tailwind CSS loaded -->\n";
//         }, 999);
//     }
// }

// // Hook the function to wp_enqueue_scripts
// add_action('wp_enqueue_scripts', 'enqueue_agnostic_tailwind_css');
