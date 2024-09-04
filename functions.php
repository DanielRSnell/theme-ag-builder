<?php
use Faker\Factory;

// define('DISALLOW_UNFILTERED_HTML', true);
// define('DISALLOW_FILE_EDIT', true);
// define('DISALLOW_FILE_MODS', true);
ini_set('max_input_vars', 10000);

// autoload
require_once __DIR__ . '/vendor/autoload.php';

// Define crb_load function in the global scope
function crb_load()
{
    \Carbon_Fields\Carbon_Fields::boot();
}

if (!function_exists('ag_theme_setup')):
    function ag_theme_setup()
{
        // Initialize Carbon Fields
        add_action('after_setup_theme', 'crb_load');

        // Initialize Timber
        Timber\Timber::init();
        Timber::$dirname = ['views'];
        Timber::$autoescape = false;

        // Add any other theme setup code here
    }
endif;

// Ensure Carbon Fields is loaded early
add_action('after_setup_theme', 'crb_load', 5);

add_action('after_setup_theme', 'ag_theme_setup');

// Include required files
require get_template_directory() . '/inc/timber/controller.php';
// require get_template_directory() . '/inc/enqueue.php';
require get_template_directory() . '/inc/controller.php';

add_action('after_setup_theme', 'woocommerce_support');
function woocommerce_support()
{
    add_theme_support('woocommerce');
}

add_filter('timber/context', 'add_faker_to_context');

function add_faker_to_context($context)
{
    $faker = Factory::create();
    $context['faker'] = $faker;
    return $context;
}

// Theme setup
function my_theme_activation()
{
    // Get the upload directory
    $upload_dir = wp_upload_dir();

    // Define the path for the new 'agnostic' directory
    $agnostic_dir = $upload_dir['basedir'] . '/agnostic';

    // Create the 'agnostic' directory if it doesn't exist
    if (!file_exists($agnostic_dir)) {
        if (!wp_mkdir_p($agnostic_dir)) {
            error_log("Failed to create directory: " . $agnostic_dir);
            return;
        }
    }

    // Define the source directory (adjust this path as needed)
    $source_dir = get_template_directory() . '/views/builder/DEFAULTS';

    // Check if the source directory exists
    if (file_exists($source_dir)) {
        // Copy files from source to destination
        $files = scandir($source_dir);
        foreach ($files as $file) {
            if ($file != "." && $file != "..") {
                $src = $source_dir . '/' . $file;
                $dst = $agnostic_dir . '/' . $file;

                // Attempt to copy the file
                if (!copy($src, $dst)) {
                    error_log("Failed to copy $file from $src to $dst");
                }
            }
        }
    } else {
        error_log("Source directory not found: " . $source_dir);
    }
}

// Hook the function to theme activation
add_action('after_switch_theme', 'my_theme_activation');

//////////////////////////////////////////////////////////////////////////////////////
//////// REDIRECT MANAGER ///////////////////////////////////////////////////////////
function universal_template_redirect()
{
    if (!is_admin()) {
        $current_url = $_SERVER['REQUEST_URI'];

        // Check for the clone functionality
        if (strpos($current_url, '/agnostic/clone') === 0) {
            $site_url = isset($_GET['site']) ? $_GET['site'] : '';
            agnostic_clone($site_url);
            exit;
        }

        if (isset($_GET['agnostic'])) {
            // Clear any output that might have already been generated
            ob_clean();

            // Prevent WordPress from sending default headers
            status_header(200);

            $context = Timber::context();

            switch ($_GET['agnostic']) {
                case 'builder':
                    Timber::render('builder/base', $context);
                    break;

                case 'preview':
                    agnostic_process();
                    break;

                case 'components':
                    Timber::render('builder/components/command-palette/elements/components.html', $context);
                    break;

                case 'theme':
                    Timber::render('builder/components/command-palette/elements/theme.html', $context);
                    break;

                case 'stylebook':
                    Timber::render('builder/components/command-palette/elements/stylebook.html', $context);
                    break;

                case 'fonts':
                    Timber::render('builder/components/command-palette/elements/fonts.html', $context);
                    break;

                case 'autocomplete':
                    // Check if the user is logged in
                    if (!is_user_logged_in()) {
                        auth_redirect();
                        exit;
                    }

                    // User is logged in, load the autocomplete file
                    $autocomplete_file = get_template_directory() . '/inc/tailwind/autocomplete.php';

                    if (file_exists($autocomplete_file)) {
                        include $autocomplete_file;
                    } else {
                        wp_die('Tailwind autocomplete file not found.', 'File Not Found', array('response' => 404));
                    }
                    break;

                default:
                    // If 'agnostic' is set but not to a recognized value,
                    // fall back to the default behavior
                    include get_stylesheet_directory() . '/builder.php';
                    break;
            }

            exit();
        } else {
            // Original behavior when 'agnostic' is not set
            include get_stylesheet_directory() . '/builder.php';
            exit();
        }
    }
}

add_action('template_redirect', 'universal_template_redirect', 1);

function get_params_object()
{
    // Merge GET and POST parameters
    $requestParams = array_merge($_GET, $_POST);

    $attributes = [];
    foreach ($requestParams as $key => $value) {
        // If the parameter is not 'email', keep it as is
        $attributes[$key] = sanitize_text_field($value);
    }

    return $attributes;
}

add_filter('timber/context', function ($context) {
    $context['params'] = get_params_object();
    $context['current_path'] = wp_parse_url(get_permalink(), PHP_URL_PATH);
    $context['current_url'] = get_permalink();

    // Get all menus
    $all_menus = wp_get_nav_menus();

    if (!empty($all_menus)) {
        $context['menus'] = new stdClass();

        foreach ($all_menus as $menu) {
            $timber_menu = Timber::get_menu($menu->term_id);

            if ($timber_menu) {
                // Replace hyphens with underscores and make the slug lowercase
                $menu_key = strtolower(str_replace('-', '_', $menu->slug));

                // Ensure the key is unique
                $original_key = $menu_key;
                $counter = 1;
                while (property_exists($context['menus'], $menu_key)) {
                    $menu_key = $original_key . '_' . $counter;
                    $counter++;
                }

                $context['menus']->$menu_key = $timber_menu;
            }
        }
    }

    return $context;
});
