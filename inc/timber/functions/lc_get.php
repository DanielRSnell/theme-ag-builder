<?php

function getBlock($name, $data = [], $props = [])
{
    // Construct the shortcode with properly escaped attributes
    $shortcode = '[lc_get_post post_type="lc_block" slug="' . esc_attr($name) . '"]';
    $template = do_shortcode($shortcode);

    // Check if the shortcode returns a non-empty result
    if (!empty($template)) {
        // Prepare the context for Timber
        $context = [];
        $context['props'] = $data;

        // Compile the string with Timber, using the provided context
        $compiledString = Timber::compile_string($template, $context);
        echo $compiledString;
    } else {
        // If the shortcode doesn't return a valid template, output false for flagging
        echo false;
    }
}

function getLoop($name, $data, $type = 'lc_block')
{
    // Construct the shortcode with properly escaped attributes
    $shortcode = '[lc_get_post post_type="' . esc_attr($type) . '" slug="' . esc_attr($name) . '"]';
    $template = do_shortcode($shortcode);

    // Check if the shortcode returns a non-empty result
    if (!empty($template)) {
        // Prepare the context for Timber
        $context = [];
        $context['field'] = $data;

        // Compile the string with Timber, using the provided context
        $compiledString = Timber::compile_string($template, $context);
        echo $compiledString;
    } else {
        // If the shortcode doesn't return a valid template, output false for flagging
        echo false;
    }
}

function getPartial($name, $data = [], $type = 'lc_block')
{
    // Construct the shortcode with properly escaped attributes
    $shortcode = '[lc_get_post post_type="' . esc_attr($type) . '" slug="' . esc_attr($name) . '"]';
    $template = do_shortcode($shortcode);

    // Check if the shortcode returns a non-empty result
    if (!empty($template)) {
        // Prepare the context for Timber
        $context = $data;

        // Compile the string with Timber, using the provided context
        $compiledString = Timber::compile_string($template, $context);
        echo $compiledString;
    } else {
        // If the shortcode doesn't return a valid template, output false for flagging
        echo false;
    }
}

function getForm($name, $type = 'lc_block')
{
    // Construct the shortcode with properly escaped attributes
    $shortcode = '[lc_get_post post_type="' . esc_attr($type) . '" slug="' . esc_attr($name) . '"]';
    $template = do_shortcode($shortcode);

    $context = Timber::get_context();
    $context['post'] = get_page_by_path($name, OBJECT, $type);

    // Check if the shortcode returns a non-empty result
    if (!empty($template)) {

        // Compile the string with Timber, using the provided context
        $compiledString = Timber::compile_string($template, $context);
        echo $compiledString;
    } else {
        // If the shortcode doesn't return a valid template, output a placeholder message
        echo 'Component not found';
    }
}

function getMenu($name)
{
    $menu = Timber::get_menu($name);

    return $menu;
}

function registerTwigFunctions()
{
    add_filter('timber/twig/functions', function ($twigFunctions) {
        $twigFunctions['partial'] = [
            'callable' => 'getPartial',
        ];
        $twigFunctions['menu'] = [
            'callable' => 'getMenu',
        ];
        $twigFunctions['component'] = [
            'callable' => 'getBlock',
        ];
        $twigFunctions['form'] = [
            'callable' => 'getForm',
        ];
        $twigFunctions['loop'] = [
            'callable' => 'getLoop',
        ];
        return $twigFunctions;
    });
}

// Register Twig functions
registerTwigFunctions();
