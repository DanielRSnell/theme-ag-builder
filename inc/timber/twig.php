<?php

add_action('rest_api_init', 'create_acf_fields_endpoint');

function create_acf_fields_endpoint()
{
    register_rest_route('acf/v1', '/fields', array(
        'methods' => 'GET',
        'callback' => 'get_acf_fields',
    ));
}

function get_acf_fields()
{
    $fields = get_fields_with_types();
    return rest_ensure_response($fields);
}

function get_fields_with_types()
{
    $field_groups = acf_get_field_groups();
    $fields = array();

    foreach ($field_groups as $field_group) {
        $group_fields = acf_get_fields($field_group['ID']);

        foreach ($group_fields as $field) {
            $fields[] = $field;
        }
    }

    return $fields;
}

add_action('rest_api_init', function () {
    register_rest_route('snippets/v1', '/twig', array(
        'methods' => 'GET',
        'callback' => 'get_twig_snippets',
    ));
});

function get_twig_snippets()
{
    // Directory containing the JSON snippet files
    $dir = get_template_directory() . '/inc/timber/snippets';
    $files = glob($dir . '/*.json');
    $snippets = array();

    foreach ($files as $file) {
        $json_data = file_get_contents($file);
        $data = json_decode($json_data, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            foreach ($data as $key => $value) {
                $snippets[] = $value;
            }
        }
    }

    return new WP_REST_Response($snippets, 200);
}

add_action('wp_ajax_lc_process_dynamic_templating_twig', 'lc_process_dynamic_templating_twig_func');

function lc_process_dynamic_templating_twig_func()
{
    if (!current_user_can("edit_pages")) {
        echo "You don't have permission to perform this action.";
        wp_die();
    }

    define("LC_DOING_DYNAMIC_TEMPLATE_TWIG_RENDERING", 1);

    try {
        $input = stripslashes($_POST['shortcode']);
        global $post;
        $post = get_post($_POST['post_id']);

        // Determine which kind of template we're in
        foreach (get_post_meta($_POST['post_id']) as $meta_key => $meta_value) {
            if (substr($meta_key, 0, 3) == 'is_' && $meta_value[0] == 1) {
                break;
            }
        }

        // Get the settings data from the POST request
        $settings = json_decode(stripslashes($_POST['settings']), true);

        // Initialize an array to store the shortcode attributes
        $shortcode_attributes = array();

        // Check each attribute value and add non-null values to the shortcode attributes array
        if (!empty($settings)) {
            // add settings object
            if (isset($settings['content_type']) && $settings['content_type'] !== null) {
                $shortcode_attributes['content_type'] = $settings['content_type'];
            }
            if (isset($settings['selection_type']) && $settings['selection_type'] !== null) {
                $shortcode_attributes['selection_type'] = $settings['selection_type'];
            }
            if (isset($settings['single_id']) && $settings['single_id'] !== null) {
                $shortcode_attributes['single_id'] = $settings['single_id'];
            }
            if (isset($settings['search']) && $settings['search'] !== null) {
                $shortcode_attributes['search'] = $settings['search'];
            }
        }

        // Build the [twig] shortcode with the populated attributes
        $twig_shortcode = '[twig trigger="process"';
        foreach ($shortcode_attributes as $attribute => $value) {
            $twig_shortcode .= ' ' . $attribute . '="' . $value . '"';
        }
        $twig_shortcode .= ']' . $input . '<script
          type="application/json"
          id="state-data"
        >
          {{ function("json_encode", state, constant("JSON_PRETTY_PRINT")) | raw }}
        </script>[/twig]';

        // Process the Twig shortcode
        $output = do_shortcode($twig_shortcode);

        echo $output;
    } catch (Exception $e) {
        error_log("Error in lc_process_dynamic_templating_twig_func: " . $e->getMessage());
        echo $input;
    }

    wp_die();
}

// Function to unregister the existing shortcode and register the new one
function replace_lc_get_posts_shortcode()
{
    // Unregister the existing shortcode
    remove_shortcode('lc_get_posts');

    // Register the new shortcode function
    add_shortcode('lc_get_posts', 'lc_get_posts_timber');
}

// Hook into init to replace the shortcode after all shortcodes are registered
add_action('init', 'replace_lc_get_posts_shortcode');

// Global variable to hold additional context data
global $additional_context_data;
$additional_context_data = [];

function add_to_twig_context($key, $value)
{
    global $additional_context_data;
    $additional_context_data[$key] = $value;
}

function lc_get_posts_timber($atts)
{
    global $additional_context_data;

    // Extract values from shortcode call
    $get_posts_shortcode_atts = shortcode_atts(array(
        'posts_per_page' => 10,
        'offset' => 0,
        'category' => '',
        'category_name' => '',
        'orderby' => 'date',
        'order' => 'DESC',
        'include' => '',
        'exclude' => '',
        'meta_key' => '',
        'meta_value' => '',
        'post_type' => 'post',
        'post_mime_type' => '',
        'post_parent' => '',
        'author' => '',
        'post_status' => 'publish',
        'suppress_filters' => true,
        'tax_query' => '',
        'lang' => apply_filters('wpml_current_language', null),
        'output_view' => 'lc_get_posts_default_view',
        'output_dynamic_view_id' => '',
    ), $atts, 'lc_get_posts');

    // Handle custom tax query case
    if (!empty($get_posts_shortcode_atts['tax_query'])) {
        $array_tax_query_par = explode("=", $get_posts_shortcode_atts['tax_query']);

        // Handle related posts case
        if ($array_tax_query_par[1] === 'related') {
            global $post;

            $terms = wp_get_post_terms($post->ID, $array_tax_query_par[0]);

            if (!empty($terms)) {
                $the_main_term = $terms[0];
                $array_tax_query_par[1] = $the_main_term->term_id; // main category/term ID of the current post
                $get_posts_shortcode_atts = array_merge($get_posts_shortcode_atts, array('exclude' => $post->ID));
            }
        }

        // Add the tax query
        $get_posts_shortcode_atts['tax_query'] = array(
            array(
                'taxonomy' => $array_tax_query_par[0], // taxonomy name
                'field' => 'id',
                'terms' => $array_tax_query_par[1], // term_id
                'include_children' => false,
            ),
        );
    }

    // Get the posts
    $the_posts = Timber::get_posts($get_posts_shortcode_atts);

    // Add to Timber context
    $context_key = $atts['output_dynamic_view_id'] ?: $atts['output_view'];
    add_to_twig_context($context_key, $the_posts);

    // Return empty because we are not directly outputting anything
    return '';
}

add_shortcode('lc_get_posts', 'lc_get_posts_timber');

function twig_shortcode($atts, $content = null)
{
    global $post;
    global $additional_context_data;

    // Process the shortcodes within the content first
    $content = do_shortcode($content);

    // Get the base Timber context
    $context = Timber::context();
    $context['is_editor'] = is_admin() && current_user_can('edit_others_posts');
    $context['attributes'] = $atts;
    $context['singular'] = false;
    $context['archive'] = false;
    $context['tax'] = false;
    $context['editor'] = false;

    // Check if shortcode attributes are provided
    if (isset($atts['content_type'])) {
        switch ($atts['content_type']) {
            case 'archive':
                $context['archive'] = true;
                if (isset($atts['selection_type'])) {
                    $post_type = $atts['selection_type'];
                    $context['preview'] = true;
                    $context['archive'] = array(
                        'title' => do_shortcode('[lc_the_archive_title]'),
                        'description' => do_shortcode('[lc_the_archive_description]'),
                    );
                    $args = array(
                        'post_type' => $post_type,
                        'numberposts' => 6,
                    );
                    $context['posts'] = Timber::get_posts($args);
                }
                break;
            case 'single':
                $context['singular'] = true;
                if (isset($atts['selection_type']) && isset($atts['single_id'])) {
                    $post_id = intval($atts['single_id']);
                    $post_type = $atts['selection_type'];
                    $context['preview'] = true;
                    $context['post'] = Timber::get_post($post_id);
                    $related_posts = array(
                        'post_type' => $post_type,
                        'numberposts' => 3,
                        'post__not_in' => array($post_id),
                    );
                    $context['related'] = Timber::get_posts($related_posts);
                }
                break;
            case 'tax':
                $context['tax'] = true;
                if (isset($atts['selection_type']) && isset($atts['single_id'])) {
                    $term_id = intval($atts['single_id']);
                    $taxonomy = $atts['selection_type'];
                    $term = get_term($term_id, $taxonomy);
                    $context['preview'] = true;
                    $context['term'] = Timber::get_term($term);
                    // Get posts related to the term
                    $context['posts'] = Timber::get_posts(array(
                        'tax_query' => array(
                            array(
                                'taxonomy' => $taxonomy,
                                'field' => 'id',
                                'terms' => $term_id,
                            ),
                        ),
                    ));
                    $related_terms = array(
                        'taxonomy' => $taxonomy,
                        'number' => 5,
                        'exclude' => $term_id,
                    );
                    $context['related_terms'] = Timber::get_terms($related_terms);
                }
                break;
            case 'search':
                $context['search'] = true;
                if (isset($atts['search_query'])) {
                    $search_query = $atts['search_query'];
                    $context['preview'] = true;
                    $context['search_query'] = $search_query;
                    $args = array(
                        's' => $search_query,
                        'posts_per_page' => 6,
                    );
                    $context['posts'] = Timber::get_posts($args);
                }
                break;
        }
    } else {
        // If no shortcode attributes are provided, use the default behavior
        // Check if it's a single post
        if (is_singular()) {
            $context['singular'] = true;
            $context['post'] = Timber::get_post($post->ID);
            $related_posts = array(
                'post_type' => $post->post_type,
                'numberposts' => 3,
                'post__not_in' => array($post->ID),
            );
            $context['related'] = Timber::get_posts($related_posts);
        }

        // Check if it's a term archive
        if (is_tax() || is_category() || is_tag()) {
            $context['tax'] = true;
            $term = get_queried_object();
            $context['term'] = Timber::get_term($term);
            if (is_tag()) {
                $related_tags = get_tags(array(
                    'number' => 5,
                    'exclude' => $term->term_id,
                ));
                $context['related_tags'] = Timber::get_terms($related_tags);
            } else {
                $related_terms = array(
                    'taxonomy' => $term->taxonomy,
                    'number' => 5,
                    'exclude' => $term->term_id,
                );
                $context['related_terms'] = Timber::get_terms($related_terms);
            }
        }

        // Check if it's a general archive (e.g., date archive, author archive)
        if (is_archive()) {
            $context['archive'] = array(
                'title' => do_shortcode('[lc_the_archive_title]'),
                'description' => do_shortcode('[lc_the_archive_description]'),
            );
            $archive_type = get_query_var('post_type');
            if (is_date()) {
                $archive_type = 'date';
            } elseif (is_author()) {
                $archive_type = 'author';
            }
            $related_posts = array(
                'post_type' => $archive_type,
                'numberposts' => 3,
            );
            $context['related'] = Timber::get_posts($related_posts);
        }

        // If singular, tax, and archive = false, then default to single
        if (!$context['singular'] && !$context['tax'] && !$context['archive']) {
            $context['editor'] = true;
            $context['post'] = Timber::get_post($post->ID);
        }
    }

    // Add the additional context data
    $context = array_merge($context, $additional_context_data);

    $context['state'] = $context;

    // Compile the content as a Twig template
    $compiled_content = Timber::compile_string($content, $context);

    // Clear the additional context data to avoid data leakage
    $additional_context_data = [];

    // Apply a custom filter to allow modifications to the compiled content
    $filtered_content = apply_filters('pre_twig_response_filter', $compiled_content);

    // Replace &amp;&amp; with && using regular expression
    $unescaped_content = preg_replace('/&amp;&amp;/', '&&', $filtered_content);

    return $unescaped_content;

}

add_shortcode('twig', 'twig_shortcode');
