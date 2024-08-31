<?php
// Prevent direct access to the file
if (!defined('ABSPATH')) {
    exit;
}

// Register custom endpoints
add_action('rest_api_init', function () {
    register_rest_route('agnostic/v1', '/components', array(
        'methods' => 'GET',
        'callback' => 'get_all_components_data',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('agnostic/v1', '/components/(?P<category>[\w-]+)/(?P<type>[\w-]+)/(?P<section>[\w-]+)', array(
        'methods' => 'GET',
        'callback' => 'get_specific_components',
        'permission_callback' => '__return_true',
    ));

});

// Callback function for /agnostic/v1/components
function get_all_components_data()
{
    // In a real-world scenario, you would fetch this data from a database or file
    $components_data = array(
        'page-sections' => array(
            array('title' => 'Templates', 'href' => '/templates', 'imageSrc' => 'https://windstatic.com/images/compthumbnails/templates.svg', 'count' => 6, 'type' => 'Marketing'),
            array('title' => 'Hero Sections', 'href' => '/headers', 'imageSrc' => 'https://windstatic.com/images/compthumbnails/hero.svg', 'count' => 13, 'type' => 'Marketing'),
            array('title' => 'Feature Sections', 'href' => '/features', 'imageSrc' => 'https://windstatic.com/images/compthumbnails/features.svg', 'count' => 15, 'type' => 'Marketing'),
            array('title' => 'Grids Sections', 'href' => '/grids', 'imageSrc' => 'https://windstatic.com/images/compthumbnails/grids.svg', 'count' => 11, 'type' => 'Marketing'),
            array('title' => 'Footers', 'href' => '/footers', 'imageSrc' => 'https://windstatic.com/images/compthumbnails/footers.svg', 'count' => 7, 'type' => 'Marketing'),
            array('title' => 'CTA Sections', 'href' => '/cta', 'imageSrc' => 'https://windstatic.com/images/compthumbnails/cta.svg', 'count' => 8, 'type' => 'Marketing'),
            array('title' => 'Testimonials', 'href' => '/testimonials', 'imageSrc' => 'https://windstatic.com/images/compthumbnails/testimonials.svg', 'count' => 7, 'type' => 'Marketing'),
            array('title' => 'Logo Clouds', 'href' => '/logoclouds', 'imageSrc' => 'https://windstatic.com/images/compthumbnails/logoclouds.svg', 'count' => 7, 'type' => 'Marketing'),
            array('title' => 'Gallery', 'href' => '/gallery', 'imageSrc' => 'https://windstatic.com/images/compthumbnails/gallery.svg', 'count' => 9, 'type' => 'Marketing'),
            array('title' => 'Pricing Sections', 'href' => '/pricing', 'imageSrc' => 'https://windstatic.com/images/compthumbnails/pricing.svg', 'count' => 14, 'type' => 'Marketing'),
            array('title' => 'FAQ Sections', 'href' => '/faq', 'imageSrc' => 'https://windstatic.com/images/compthumbnails/faq.svg', 'count' => 5, 'type' => 'Marketing'),
            array('title' => 'Team Sections', 'href' => '/teams', 'imageSrc' => 'https://windstatic.com/images/compthumbnails/teams.svg', 'count' => 5, 'type' => 'Marketing'),
            array('title' => 'Blog Sections', 'href' => '/blog', 'imageSrc' => 'https://windstatic.com/images/compthumbnails/blog.svg', 'count' => 4, 'type' => 'Marketing'),
            array('title' => 'Newsletter Sections', 'href' => '/newsletters', 'imageSrc' => 'https://windstatic.com/images/compthumbnails/newsletters.svg', 'count' => 3, 'type' => 'Marketing'),
            array('title' => 'Error Sections', 'href' => '/errors', 'imageSrc' => 'https://windstatic.com/images/compthumbnails/errors.svg', 'count' => 3, 'type' => 'Marketing'),
        ),
        'navigation' => array(
            array('title' => 'Navbars', 'href' => '/navbars', 'imageSrc' => 'https://windstatic.com/images/compthumbnails/navbars.svg', 'count' => 6, 'type' => 'Marketing'),
            array('title' => 'Flyout Menus', 'href' => '/flyouts', 'imageSrc' => 'https://windstatic.com/images/compthumbnails/flyouts.svg', 'count' => 6, 'type' => 'Marketing'),
            array('title' => 'Sidebar Menus', 'href' => '/sidebars', 'imageSrc' => 'https://windstatic.com/images/compthumbnails/sidebars.svg', 'count' => 6, 'type' => 'Marketing'),
            array('title' => 'Breadcrumbs', 'href' => '/breadcrumbs', 'imageSrc' => 'https://windstatic.com/images/compthumbnails/breadcrumbs.svg', 'count' => 5, 'type' => 'Marketing'),
            array('title' => 'Tabs', 'href' => '/tabs', 'imageSrc' => 'https://windstatic.com/images/compthumbnails/tabs.svg', 'count' => 5, 'type' => 'Marketing'),
            array('title' => 'Pagination', 'href' => '/pagination', 'imageSrc' => 'https://windstatic.com/images/compthumbnails/pagination.svg', 'count' => 5, 'type' => 'Marketing'),
            array('title' => 'Vertical Navigation', 'href' => '/verticalnavigation', 'imageSrc' => 'https://windstatic.com/images/compthumbnails/verticalnavigation.svg', 'count' => 4, 'type' => 'Marketing'),
            array('title' => 'Steps', 'href' => '/steps', 'imageSrc' => 'https://windstatic.com/images/compthumbnails/steps.svg', 'count' => 5, 'type' => 'Marketing'),
            array('title' => 'Command Palette', 'href' => '/commandpalette', 'imageSrc' => 'https://windstatic.com/images/compthumbnails/commandpalette.svg', 'count' => 5, 'type' => 'Marketing'),
        ),
        'forms' => array(
            array('title' => 'Sign-in and Registration', 'href' => '/registration', 'imageSrc' => 'https://windstatic.com/images/compthumbnails/registration.svg', 'count' => 6, 'type' => 'Marketing'),
        ),
        'styled-components' => array(
            array('title' => 'Styled Heroes', 'href' => '/styled-heros', 'imageSrc' => 'https://windstatic.com/images/compthumbnails/styleHeros.svg', 'count' => 20, 'type' => 'Marketing'),
            array('title' => 'Styled Pricing', 'href' => '/styled-pricing', 'imageSrc' => 'https://windstatic.com/images/compthumbnails/stylePricing.svg', 'count' => 7, 'type' => 'Marketing'),
        ),
    );

    return new WP_REST_Response($components_data, 200);
}

// Callback function for /agnostic/v1/components/{category}/{type}/{section}
function get_specific_components($request)
{
    $category = $request['category'];
    $type = $request['type'];
    $section = $request['section'];

    $components = array_merge(
        get_filesystem_components($category, $type, $section),
        get_view_components($category, $type, $section),
        get_pro_api_components($category, $type, $section)
    );

    return new WP_REST_Response($components, 200);
}

function get_filesystem_components($category, $type, $section)
{
    $components = array();
    $directories = array(
        get_template_directory() . '/inc/components',
        // get_stylesheet_directory() . '/inc/components',
    );

    foreach ($directories as $base_dir) {
        $component_dir = $base_dir . '/' . $category . '/' . $type . '/' . $section;
        if (is_dir($component_dir)) {
            $files = glob($component_dir . '/*.html');
            foreach ($files as $file) {
                $name = basename($file, '.html');
                $components[] = array(
                    'name' => $name,
                    'category' => $category,
                    'type' => $type,
                    'section' => $section,
                    'code' => file_get_contents($file),
                );
            }
        }
    }

    return $components;
}

function get_view_components($category, $type, $section)
{
    // Placeholder for view components
    // In the future, implement logic to fetch components from views
    return array();
}

function get_pro_api_components($category, $type, $section)
{
    // Placeholder for pro API components
    // In the future, implement logic to fetch components from pro API
    return array();
}
