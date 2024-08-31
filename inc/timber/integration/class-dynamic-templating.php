<?php
/**
 * Dynamic Templating
 *
 * @package Agnostic
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Class Dynamic_Templating
 */
class Dynamic_Templating
{
    public static function init()
    {
        add_action('wp_ajax_lc_process_dynamic_templating_twig', array(self::class, 'process_dynamic_templating_twig'));
    }

    public static function process_dynamic_templating_twig()
    {
        if (!current_user_can('edit_pages')) {
            wp_die('You don\'t have permission to perform this action.');
        }

        define('LC_DOING_DYNAMIC_TEMPLATE_TWIG_RENDERING', 1);

        try {
            $input = stripslashes($_POST['shortcode']);
            global $post;
            $post = get_post($_POST['post_id']);

            $settings = json_decode(stripslashes($_POST['settings']), true);
            $shortcode_attributes = self::build_shortcode_attributes($settings);

            $twig_shortcode = self::build_twig_shortcode($shortcode_attributes, $input);
            $output = do_shortcode($twig_shortcode);

            echo $output;
        } catch (Exception $e) {
            error_log('Error in process_dynamic_templating_twig: ' . $e->getMessage());
            echo $input;
        }

        wp_die();
    }

    private static function build_shortcode_attributes($settings)
    {
        $attributes = array();
        $keys = array('content_type', 'selection_type', 'single_id', 'search');

        foreach ($keys as $key) {
            if (isset($settings[$key]) && $settings[$key] !== null) {
                $attributes[$key] = $settings[$key];
            }
        }

        return $attributes;
    }

    private static function build_twig_shortcode($attributes, $content)
    {
        $shortcode = '[twig trigger="process"';
        foreach ($attributes as $key => $value) {
            $shortcode .= ' ' . $key . '="' . esc_attr($value) . '"';
        }
        $shortcode .= ']' . $content . '<script type="application/json" id="state-data">{{ function("json_encode", state, constant("JSON_PRETTY_PRINT")) | raw }}</script>[/twig]';

        return $shortcode;
    }
}
