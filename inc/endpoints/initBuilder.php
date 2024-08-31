<?php

add_action('rest_api_init', function () {
    register_rest_route('agnostic/v1', '/redirects', array(
        'methods' => 'GET',
        'callback' => 'get_all_redirects',
        // 'permission_callback' => function () {
        //     return current_user_can('manage_options');
        // },
    ));
});

// Callback function to get all redirects
function get_all_redirects()
{
    // This function assumes you're using a plugin like "Redirection" to manage redirects
    // You may need to adjust this based on how redirects are stored in your WordPress setup
    global $wpdb;

    $table_name = $wpdb->prefix . 'redirection_items';

    $redirects = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);

    if (empty($redirects)) {
        return new WP_Error('no_redirects', 'No redirects found', array('status' => 404));
    }

    return new WP_REST_Response($redirects, 200);
}

function get_blocks_data()
{
    $data = [
        'typography' => 'Typography',
        'layouts' => 'Layouts',
        'forms' => 'Forms',
        'block' => 'Blocks',
        'query' => 'Query',
    ];

    // Apply any filters or modifications to $data here if needed
    // For example:
    // $data = apply_filters('blocks_data', $data);

    // Return as JSON
    return json_encode($data);
}

/**
 * Ajax functions for post content management.
 *
 * @package AgnosticTheme
 */

/**
 * Retrieve post content by ID via Ajax.
 *
 * @return void
 */
function agnostic_get_post_content()
{
    // Check for valid post ID.
    if (!isset($_POST['post_id']) || !is_numeric($_POST['post_id'])) {
        wp_send_json_error('Invalid post ID');
    }

    // Fetch the post
    $post_id = intval($_POST['post_id']);
    $post = get_post($post_id);

    if (!$post) {
        wp_send_json_error('Post not found');
    }

    $context = Timber::context();
    $context['template'] = $post->post_content;

    $compile = Timber::compile('builder/render/base', $context);

    echo $compile;
}

add_action('wp_ajax_agnostic_get_post_content', 'agnostic_get_post_content');
add_action('wp_ajax_nopriv_agnostic_get_post_content', 'agnostic_get_post_content');

/**
 * Save post content via Ajax.
 *
 * @return void
 */
function agnostic_save_post_content()
{
    // Check for valid input data.
    if (!isset($_POST['post_id']) || !is_numeric($_POST['post_id']) || !isset($_POST['content'])) {
        wp_send_json_error('Invalid data');
    }

    $post_id = intval($_POST['post_id']);
    $content = $_POST['content'];

    $updated_post = array(
        'ID' => $post_id,
        'post_content' => $content,
    );

    // Attempt to update the post.
    $result = wp_update_post($updated_post);

    if (is_wp_error($result)) {
        wp_send_json_error('Failed to update post');
    }

    // Check if PHP code is being sent
    if (isset($_POST['php'])) {
        $php_code = $_POST['php'];

        // Save PHP code to Carbon field
        carbon_set_post_meta($post_id, 'crb_php_view', $php_code);

        // Update the 'has PHP' indicator
        $has_php = !empty(trim($php_code)) ? 'yes' : '';
        carbon_set_post_meta($post_id, 'crb_has_php', $has_php);
    }

    wp_send_json_success(array('message' => 'Post and PHP code updated successfully'));
}
add_action('wp_ajax_agnostic_save_post_content', 'agnostic_save_post_content');

/**
 * Process HTML content with Twig templating.
 *
 * This function handles AJAX requests to process HTML content using Twig templating.
 * It can optionally render a full HTML page structure.
 */
function agnostic_process()
{
    // Check user capabilities
    if (!current_user_can('edit_pages')) {
        wp_die('You don\'t have permission to perform this action.');
    }

    // Define constant for dynamic template rendering
    define('LC_DOING_DYNAMIC_TEMPLATE_TWIG_RENDERING', 1);

    try {
        global $post;

        // Determine if we're processing AJAX or a preview
        $is_ajax = wp_doing_ajax();
        $is_preview = isset($_GET['agnostic']) && $_GET['agnostic'] === 'preview';

        if ($is_ajax) {
            // AJAX processing
            $input = isset($_POST['html']) ? wp_kses_post(stripslashes($_POST['html'])) : '';
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            $settings = isset($_POST['settings']) ? json_decode(stripslashes($_POST['settings']), true) : array();
        } elseif ($is_preview) {
            // Preview processing
            $template_id = isset($_GET['template_id']) ? intval($_GET['template_id']) : 0;
            $post = $template_id ? get_post($template_id) : $post;

            if (!$post) {
                wp_die('No valid post found');
            }

            $input = $post->post_content;
            $post_id = $post->ID;
            $settings = get_post_meta($post_id, 'agnostic_settings', true) ?: array();
        } else {
            wp_die('Invalid request');
        }

        // Set up global post data
        $post = get_post($post_id);

        // Determine template type
        $post_meta = get_post_meta($post_id);
        foreach ($post_meta as $meta_key => $meta_value) {
            if ('is_' === substr($meta_key, 0, 3) && 1 == $meta_value[0]) {
                break;
            }
        }

        // Build shortcode attributes
        $shortcode_attributes = array();
        $attribute_keys = array('content_type', 'selection_type', 'single_id', 'search');
        foreach ($attribute_keys as $key) {
            if (isset($settings[$key]) && null !== $settings[$key]) {
                $shortcode_attributes[$key] = sanitize_text_field($settings[$key]);
            }
        }

        // Construct Twig shortcode
        $twig_shortcode = '[twig trigger="process"';
        foreach ($shortcode_attributes as $attribute => $value) {
            $twig_shortcode .= ' ' . $attribute . '="' . esc_attr($value) . '"';
        }
        $twig_shortcode .= ']' . $input . '<script
          type="application/json"
          id="state-data"
        >
          {{ function("json_encode", state, constant("JSON_PRETTY_PRINT")) | raw }}
        </script>[/twig]';

        // Process the Twig shortcode
        $output = do_shortcode($twig_shortcode);

        $context = Timber::context();
        $context['template'] = $output;
        $context['post'] = $post;

        $compile = Timber::render('builder/render/base', $context);

        if ($is_ajax) {
            echo $compile;
            wp_die();
        } else {
            echo $compile;
            exit;
        }

    } catch (Exception $e) {
        error_log('Error in agnostic_process: ' . $e->getMessage());
        if ($is_ajax) {
            wp_send_json_error('Error processing dynamic template');
        } else {
            wp_die('Error processing dynamic template');
        }
    }
}

// Hook for AJAX requests
add_action('wp_ajax_agnostic_process', 'agnostic_process');
add_action('wp_ajax_nopriv_agnostic_process', 'agnostic_process');

function include_ace_editor_files()
{
    $parent_theme_uri = get_template_directory_uri();
    $ace_path = $parent_theme_uri . '/js/builder/ace/src-min-noconflict/';
    $files = [
        'ace.js',
        'ext-emmet.js',
        'ext-language_tools.js',
    ];

    $output = '';
    foreach ($files as $file) {
        $file_url = $ace_path . $file;
        $file_path = get_template_directory() . '/js/builder/ace/src-min-noconflict/' . $file;

        if (file_exists($file_path)) {
            $output .= sprintf('<script src="%s"></script>' . PHP_EOL, esc_url($file_url));
        } else {
            $output .= sprintf('<!-- File not found: %s -->' . PHP_EOL, esc_html($file_path));
        }
    }

    echo $output;
}

add_action('wp_ajax_agnostic_media_selector', 'agnostic_media_selector_page');
add_action('wp_ajax_nopriv_agnostic_media_selector', 'agnostic_media_selector_page');

function agnostic_media_selector_page()
{
    // Ensure user has permission
    if (!current_user_can('upload_files')) {
        wp_die(__('You do not have permission to upload files.'));
    }

    // Set up the media library scripts
    wp_enqueue_media();
    wp_enqueue_script('media-grid');
    wp_enqueue_script('media');

    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <?php wp_head();?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            window.addEventListener('message', function(event) {
                if (event.data === 'openMediaLibrary') {
                    var mediaUploader = wp.media({
                        title: 'Select or Upload Media',
                        button: {
                            text: 'Use this media'
                        },
                        multiple: false
                    });

                    mediaUploader.on('select', function() {
                        var attachment = mediaUploader.state().get('selection').first().toJSON();
                        window.parent.postMessage({
                            type: 'mediaSelected',
                            id: attachment.id,
                            url: attachment.url
                        }, '*');
                    });

                    mediaUploader.open();
                }
            }, false);
        });
        </script>
    </head>
    <body>
        <?php wp_footer();?>
    </body>
    </html>
    <?php
exit;
}

// Register Named Routes for Timber
add_filter('timber/locations', function ($paths) {
    $theme_directory = get_template_directory();

    $paths['admin'] = [$theme_directory . '/views/admin'];
    $paths['builder'] = [$theme_directory . '/views/builder'];
    $paths['components'] = [$theme_directory . '/views/builder/components'];

    return $paths;
});
