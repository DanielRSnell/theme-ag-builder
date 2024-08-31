<?php
/**
 * Timber Integration
 *
 * @package Agnostic
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

require_once get_template_directory() . '/inc/timber/integration/class-timber-context-manager.php';
require_once get_template_directory() . '/inc/timber/integration/class-acf-fields-endpoint.php';
require_once get_template_directory() . '/inc/timber/integration/class-twig-snippets-endpoint.php';
require_once get_template_directory() . '/inc/timber/integration/class-dynamic-templating.php';
require_once get_template_directory() . '/inc/timber/integration/class-lc-get-posts.php';
require_once get_template_directory() . '/inc/timber/integration/twig-shortcode.php';

// Initialize classes
ACF_Fields_Endpoint::init();
Twig_Snippets_Endpoint::init();
Dynamic_Templating::init();
LC_Get_Posts::init();

// Global variable to hold additional context data
global $additional_context_data;
$additional_context_data = array();

/**
 * Add to Twig context
 *
 * @param string $key   Context key.
 * @param mixed  $value Context value.
 */
function agnostic_add_to_twig_context($key, $value)
{
    global $additional_context_data;
    $additional_context_data[$key] = $value;
}
