<?php

add_filter('timber/locations', function ($paths) {
    $theme_directory = get_template_directory();

    $paths['admin'] = [$theme_directory . '/views/admin'];

    $paths['builder'] = [$theme_directory . '/views/builder'];

    $paths['components'] = [$theme_directory . '/views/builder/components'];

    return $paths;
});
