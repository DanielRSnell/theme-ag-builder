<?php
/**
 * LC Get Posts
 *
 * @package Agnostic
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Class LC_Get_Posts
 */
class LC_Get_Posts
{
    /**
     * Initialize the class
     */
    public static function init()
    {
        add_action('init', array(self::class, 'replace_shortcode'));
    }

    /**
     * Replace the lc_get_posts shortcode
     */
    public static function replace_shortcode()
    {
        // Unregister the existing shortcode
        remove_shortcode('lc_get_posts');

        // Register the new shortcode function
        add_shortcode('lc_get_posts', array(self::class, 'shortcode'));
    }

    /**
     * LC Get Posts shortcode
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public static function shortcode($atts)
    {
        global $additional_context_data;

        $default_atts = array(
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
        );

        $atts = shortcode_atts($default_atts, $atts, 'lc_get_posts');

        $atts = self::handle_tax_query($atts);

        $the_posts = Timber::get_posts($atts);

        $context_key = $atts['output_dynamic_view_id'] ?: $atts['output_view'];
        self::add_to_twig_context($context_key, $the_posts);

        return '';
    }

    /**
     * Handle tax query
     *
     * @param array $atts Shortcode attributes.
     * @return array
     */
    private static function handle_tax_query($atts)
    {
        if (!empty($atts['tax_query'])) {
            $array_tax_query_par = explode('=', $atts['tax_query']);

            if ('related' === $array_tax_query_par[1]) {
                global $post;
                $terms = wp_get_post_terms($post->ID, $array_tax_query_par[0]);

                if (!empty($terms)) {
                    $the_main_term = $terms[0];
                    $array_tax_query_par[1] = $the_main_term->term_id;
                    $atts['exclude'] = $post->ID;
                }
            }

            $atts['tax_query'] = array(
                array(
                    'taxonomy' => $array_tax_query_par[0],
                    'field' => 'id',
                    'terms' => $array_tax_query_par[1],
                    'include_children' => false,
                ),
            );
        }

        return $atts;
    }

    /**
     * Add to Twig context
     *
     * @param string $key   Context key.
     * @param mixed  $value Context value.
     */
    private static function add_to_twig_context($key, $value)
    {
        global $additional_context_data;
        $additional_context_data[$key] = $value;
    }
}
