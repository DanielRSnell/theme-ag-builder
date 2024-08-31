<?php
/**
 * ACF Fields Endpoint
 *
 * @package Agnostic
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Class ACF_Fields_Endpoint
 */
class ACF_Fields_Endpoint
{
    public static function init()
    {
        add_action('rest_api_init', array(self::class, 'register_endpoint'));
    }

    public static function register_endpoint()
    {
        register_rest_route(
            'acf/v1',
            '/fields',
            array(
                'methods' => 'GET',
                'callback' => array(self::class, 'get_acf_fields'),
            )
        );
    }

    public static function get_acf_fields()
    {
        $fields = self::get_fields_with_types();
        return rest_ensure_response($fields);
    }

    private static function get_fields_with_types()
    {
        $field_groups = acf_get_field_groups();
        $fields = array();

        foreach ($field_groups as $field_group) {
            $group_fields = acf_get_fields($field_group['ID']);
            $fields = array_merge($fields, $group_fields);
        }

        return $fields;
    }
}
