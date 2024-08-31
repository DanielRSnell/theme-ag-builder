<?php
/**
 * Twig Snippets Endpoint
 *
 * @package Agnostic
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Class Twig_Snippets_Endpoint
 */
class Twig_Snippets_Endpoint
{
    public static function init()
    {
        add_action('rest_api_init', array(self::class, 'register_endpoint'));
    }

    public static function register_endpoint()
    {
        register_rest_route(
            'snippets/v1',
            '/twig',
            array(
                'methods' => 'GET',
                'callback' => array(self::class, 'get_twig_snippets'),
            )
        );
    }

    public static function get_twig_snippets()
    {
        $dir = get_template_directory() . '/inc/timber/snippets';
        $files = glob($dir . '/*.json');
        $snippets = array();

        foreach ($files as $file) {
            $json_data = file_get_contents($file);
            $data = json_decode($json_data, true);

            if (JSON_ERROR_NONE === json_last_error()) {
                $snippets = array_merge($snippets, $data);
            }
        }

        return new WP_REST_Response($snippets, 200);
    }
}
