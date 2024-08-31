<?php

add_action('rest_api_init', 'register_font_api');

function register_font_api()
{
    register_rest_route('agnostic/v1', '/fonts', array(
        'methods' => 'GET',
        'callback' => 'get_fonts_list',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('agnostic/v1', '/fonts/download', array(
        'methods' => 'POST',
        'callback' => 'download_font',
        'permission_callback' => function () {
            return current_user_can('edit_theme_options');
        },
    ));

    register_rest_route('agnostic/v1', '/fonts/designations', array(
        'methods' => 'POST',
        'callback' => 'update_font_designations',
        'permission_callback' => function () {
            return current_user_can('edit_theme_options');
        },
    ));

    register_rest_route('agnostic/v1', '/fonts/tailwind', array(
        'methods' => 'GET',
        'callback' => 'ag_get_tailwind_config',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('agnostic/v1', '/fonts/css', array(
        'methods' => 'GET',
        'callback' => 'get_fonts_css',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('agnostic/v1', '/fonts/reset', array(
        'methods' => 'GET',
        'callback' => 'reset_font_designations',
        'permission_callback' => '__return_true',
    ));
}

function get_ag_fonts_dir()
{
    $upload_dir = wp_upload_dir();
    return $upload_dir['basedir'] . '/ag_fonts';
}

function get_ag_fonts_url()
{
    $upload_dir = wp_upload_dir();
    return $upload_dir['baseurl'] . '/ag_fonts';
}

function ensure_ag_fonts_dir()
{
    $ag_fonts_dir = get_ag_fonts_dir();
    if (!file_exists($ag_fonts_dir)) {
        wp_mkdir_p($ag_fonts_dir);
    }
}

function get_fonts_list()
{
    $json_file = get_template_directory() . '/fonts.json';

    if (file_exists($json_file)) {
        $fonts_json = file_get_contents($json_file);
        $fonts = json_decode($fonts_json, true);

        if (is_array($fonts) && isset($fonts['fonts'])) {
            $installed_fonts = get_option('ag_installed_fonts', array());
            $designations = get_option('ag_font_designations', array());

            $font_list = array_map(function ($font) use ($installed_fonts, $designations) {
                $designation = null;
                foreach ($designations as $key => $value) {
                    if (in_array($font, $value)) {
                        $designation = $key;
                        break;
                    }
                }
                return array(
                    'name' => $font,
                    'installed' => in_array($font, $installed_fonts),
                    'designation' => $designation,
                    'url' => "https://fonts.googleapis.com/css2?family=" . urlencode($font) . "&display=swap",
                );
            }, $fonts['fonts']);

            return new WP_REST_Response(array('fonts' => $font_list), 200);
        }
    }

    return new WP_Error('no_fonts', 'No fonts available', array('status' => 404));
}

function download_font($request)
{
    $font_name = $request->get_param('font_name');
    $font_url = $request->get_param('font_url');

    if (empty($font_name) || empty($font_url)) {
        return new WP_Error('invalid_font', 'Please provide a valid font name and URL', array('status' => 400));
    }

    ensure_ag_fonts_dir();
    $font_dir = get_ag_fonts_dir() . '/' . sanitize_file_name($font_name);

    if (!is_dir($font_dir)) {
        mkdir($font_dir, 0755, true);
    }

    // Fetch the CSS file
    $css_content = file_get_contents($font_url);
    if ($css_content === false) {
        return new WP_Error('download_failed', 'Failed to download font CSS', array('status' => 500));
    }

    // Save the CSS file
    file_put_contents($font_dir . '/font.css', $css_content);

    // Parse the CSS to find font file URLs
    preg_match_all('/url\((.*?)\)/', $css_content, $matches);
    $font_file_urls = $matches[1];

    // Download each font file
    foreach ($font_file_urls as $file_url) {
        $file_content = file_get_contents($file_url);
        if ($file_content !== false) {
            $file_name = basename($file_url);
            file_put_contents($font_dir . '/' . $file_name, $file_content);
        }
    }

    $installed_fonts = get_option('ag_installed_fonts', array());
    if (!in_array($font_name, $installed_fonts)) {
        $installed_fonts[] = $font_name;
        update_option('ag_installed_fonts', $installed_fonts);
    }

    return new WP_REST_Response(array('message' => "Font {$font_name} downloaded and saved successfully"), 200);
}

function update_font_designations($request)
{
    $params = $request->get_json_params();
    $fontName = $params['fontName'] ?? '';
    $newDesignation = $params['newDesignation'] ?? '';

    if (empty($fontName)) {
        return new WP_Error('invalid_font', 'Please provide a valid font name', array('status' => 400));
    }

    $designations = get_option('ag_font_designations', array());

    foreach ($designations as $designation => $fonts) {
        $designations[$designation] = array_values(array_diff($fonts, [$fontName]));
        if (empty($designations[$designation])) {
            unset($designations[$designation]);
        }
    }

    if (!empty($newDesignation)) {
        if (!isset($designations[$newDesignation])) {
            $designations[$newDesignation] = array();
        }
        if (!in_array($fontName, $designations[$newDesignation])) {
            $designations[$newDesignation][] = $fontName;
        }
    }

    update_option('ag_font_designations', $designations);

    return new WP_REST_Response(array(
        'message' => 'Font designations updated successfully',
        'designations' => $designations,
    ), 200);
}

function ag_get_tailwind_config()
{
    $designations = get_option('ag_font_designations', array());
    $css = generate_font_css();
    $config = array();

    foreach ($designations as $designation => $fonts) {
        if (!empty($fonts)) {
            $config[$designation] = $fonts;
        }
    }

    return new WP_REST_Response(array(
        'tailwind_config' => $config,
        'font_css' => $css,
    ), 200);
}

function generate_font_css()
{
    $fonts_url = get_ag_fonts_url();
    $css = "";

    $designations = get_option('ag_font_designations', array());
    $designated_fonts = array_unique(array_merge(...array_values($designations)));

    foreach ($designated_fonts as $font_name) {
        $font_dir = get_ag_fonts_dir() . '/' . sanitize_file_name($font_name);
        $font_files = glob($font_dir . '/*.{woff2,woff,ttf,otf}', GLOB_BRACE);

        if (!empty($font_files)) {
            $css .= "@font-face {\n";
            $css .= "  font-family: '" . esc_attr($font_name) . "';\n";
            $css .= "  src: ";

            $src_rules = array();
            foreach ($font_files as $file) {
                $file_url = $fonts_url . '/' . sanitize_file_name($font_name) . '/' . basename($file);
                $format = pathinfo($file, PATHINFO_EXTENSION);
                if ($format === 'ttf') {
                    $format = 'truetype';
                }

                $src_rules[] = "url('" . esc_url($file_url) . "') format('" . esc_attr($format) . "')";
            }

            $css .= implode(",\n       ", $src_rules) . ";\n";
            $css .= "  font-weight: normal;\n";
            $css .= "  font-style: normal;\n";
            $css .= "  font-display: swap;\n";
            $css .= "}\n\n";
        }
    }

    return $css;
}

function get_fonts_css()
{
    $css = generate_font_css();
    header('Content-Type: text/css');
    echo $css;
    exit;
}

function reset_font_designations()
{
    update_option('ag_font_designations', array());
    update_option('ag_installed_fonts', array());

    $fonts_dir = get_ag_fonts_dir();
    if (file_exists($fonts_dir)) {
        ag_recursive_rmdir($fonts_dir);
    }
    ensure_ag_fonts_dir();

    return new WP_REST_Response(array(
        'message' => 'All font designations, installed fonts, and font files have been reset successfully',
        'designations' => array(),
        'installed_fonts' => array(),
    ), 200);
}

function ag_recursive_rmdir($dir)
{
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir . DIRECTORY_SEPARATOR . $object) && !is_link($dir . "/" . $object)) {
                    ag_recursive_rmdir($dir . DIRECTORY_SEPARATOR . $object);
                } else {
                    unlink($dir . DIRECTORY_SEPARATOR . $object);
                }
            }
        }
        rmdir($dir);
    }
}
