<?php

$endpoints = get_template_directory() . '/inc/endpoints/';

require get_template_directory() . '/inc/tailwind/controller.php';

// Require each file in the endpoints directory

if (is_dir($endpoints)) {
    foreach (new DirectoryIterator($endpoints) as $file) {
        if ($file->isFile() && $file->getExtension() == 'php') {
            require_once $file->getPathname();
        }
    }
}
