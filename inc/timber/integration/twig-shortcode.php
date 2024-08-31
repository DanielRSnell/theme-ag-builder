<?php
/**
 * Twig Shortcode
 *
 * @package Agnostic
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Twig shortcode
 *
 * @param array  $atts    Shortcode attributes.
 * @param string $content Shortcode content.
 * @return string
 */
function agnostic_twig_shortcode($atts, $content = null)
{
    global $post;
    global $additional_context_data;

    $content = do_shortcode($content);

    $context = Timber_Context_Manager::generate_context($atts, $content);
    Timber_Context_Manager::add_additional_context($additional_context_data);
    $context = Timber_Context_Manager::get_context();

    $context['state'] = $context;

    $compiled_content = Timber::compile_string($content, $context);

    $additional_context_data = array();

    $filtered_content = apply_filters('pre_twig_response_filter', $compiled_content);
    $unescaped_content = preg_replace('/&amp;&amp;/', '&&', $filtered_content);

    return $unescaped_content;
}
add_shortcode('twig', 'agnostic_twig_shortcode');
