<?php

// Alpine.js attribute sanitization
function agnostic_do_not_sanitize_alpine_attr_in_content($content)
{
    $alpine_attributes = [
        'x-data', 'x-init', 'x-show', 'x-bind', 'x-on', 'x-text', 'x-html', 'x-model', 'x-modelable',
        'x-for', 'x-transition', 'x-effect', 'x-ignore', 'x-ref', 'x-cloak', 'x-teleport', 'x-if', 'x-id',
        '$el', '$refs', '$store', '$watch', '$dispatch', '$nextTick', '$root', '$data', '$id',
        'Alpine.data()', 'Alpine.store()', 'Alpine.bind()',
    ];

    foreach ($alpine_attributes as $attribute) {
        $content = preg_replace_callback(
            "/(\\s$attribute)(\\s*?=\\s*?(\"|')(.*?)(\\3))/",
            function ($matches) {
                return htmlspecialchars_decode($matches[0]);
            },
            $content
        );
    }

    return $content;
}
