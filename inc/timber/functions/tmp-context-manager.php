<?php

class TimberContextManager
{
    private static $context = null;

    public static function generateContext($atts, $content)
    {
        self::$context = Timber::context();
        self::$context['is_editor'] = is_admin() && current_user_can('edit_others_posts');
        self::$context['attributes'] = $atts;
        self::$context['singular'] = false;
        self::$context['archive'] = false;
        self::$context['tax'] = false;
        self::$context['editor'] = false;

        self::processContentType($atts);

        return self::$context;
    }

    private static function processContentType($atts)
    {
        if (isset($atts['content_type'])) {
            switch ($atts['content_type']) {
                case 'archive':
                    self::processArchive($atts);
                    break;
                case 'single':
                    self::processSingle($atts);
                    break;
                case 'tax':
                    self::processTax($atts);
                    break;
                case 'search':
                    self::processSearch($atts);
                    break;
            }
        } else {
            self::processDefault();
        }
    }

    private static function processArchive($atts)
    {
        self::$context['archive'] = true;
        if (isset($atts['selection_type'])) {
            $post_type = $atts['selection_type'];
            self::$context['preview'] = true;
            self::$context['archive'] = array(
                'title' => do_shortcode('[lc_the_archive_title]'),
                'description' => do_shortcode('[lc_the_archive_description]'),
            );
            $args = array(
                'post_type' => $post_type,
                'numberposts' => 6,
            );
            self::$context['posts'] = Timber::get_posts($args);
        }
    }

    private static function processSingle($atts)
    {
        self::$context['singular'] = true;
        if (isset($atts['selection_type']) && isset($atts['single_id'])) {
            $post_id = intval($atts['single_id']);
            $post_type = $atts['selection_type'];
            self::$context['preview'] = true;
            self::$context['post'] = Timber::get_post($post_id);
            $related_posts = array(
                'post_type' => $post_type,
                'numberposts' => 3,
                'post__not_in' => array($post_id),
            );
            self::$context['related'] = Timber::get_posts($related_posts);
        }
    }

    private static function processTax($atts)
    {
        self::$context['tax'] = true;
        if (isset($atts['selection_type']) && isset($atts['single_id'])) {
            $term_id = intval($atts['single_id']);
            $taxonomy = $atts['selection_type'];
            $term = get_term($term_id, $taxonomy);
            self::$context['preview'] = true;
            self::$context['term'] = Timber::get_term($term);
            self::$context['posts'] = Timber::get_posts(array(
                'tax_query' => array(
                    array(
                        'taxonomy' => $taxonomy,
                        'field' => 'id',
                        'terms' => $term_id,
                    ),
                ),
            ));
            $related_terms = array(
                'taxonomy' => $taxonomy,
                'number' => 5,
                'exclude' => $term_id,
            );
            self::$context['related_terms'] = Timber::get_terms($related_terms);
        }
    }

    private static function processSearch($atts)
    {
        self::$context['search'] = true;
        if (isset($atts['search_query'])) {
            $search_query = $atts['search_query'];
            self::$context['preview'] = true;
            self::$context['search_query'] = $search_query;
            $args = array(
                's' => $search_query,
                'posts_per_page' => 6,
            );
            self::$context['posts'] = Timber::get_posts($args);
        }
    }

    private static function processDefault()
    {
        global $post;
        if (is_singular()) {
            self::processDefaultSingle();
        } elseif (is_tax() || is_category() || is_tag()) {
            self::processDefaultTax();
        } elseif (is_archive()) {
            self::processDefaultArchive();
        } else {
            self::processDefaultEditor();
        }
    }

    private static function processDefaultSingle()
    {
        global $post;
        self::$context['singular'] = true;
        self::$context['post'] = Timber::get_post($post->ID);
        $related_posts = array(
            'post_type' => $post->post_type,
            'numberposts' => 3,
            'post__not_in' => array($post->ID),
        );
        self::$context['related'] = Timber::get_posts($related_posts);
    }

    private static function processDefaultTax()
    {
        self::$context['tax'] = true;
        $term = get_queried_object();
        self::$context['term'] = Timber::get_term($term);
        if (is_tag()) {
            $related_tags = get_tags(array(
                'number' => 5,
                'exclude' => $term->term_id,
            ));
            self::$context['related_tags'] = Timber::get_terms($related_tags);
        } else {
            $related_terms = array(
                'taxonomy' => $term->taxonomy,
                'number' => 5,
                'exclude' => $term->term_id,
            );
            self::$context['related_terms'] = Timber::get_terms($related_terms);
        }
    }

    private static function processDefaultArchive()
    {
        self::$context['archive'] = array(
            'title' => do_shortcode('[lc_the_archive_title]'),
            'description' => do_shortcode('[lc_the_archive_description]'),
        );
        $archive_type = get_query_var('post_type');
        if (is_date()) {
            $archive_type = 'date';
        } elseif (is_author()) {
            $archive_type = 'author';
        }
        $related_posts = array(
            'post_type' => $archive_type,
            'numberposts' => 3,
        );
        self::$context['related'] = Timber::get_posts($related_posts);
    }

    private static function processDefaultEditor()
    {
        global $post;
        self::$context['editor'] = true;
        self::$context['post'] = Timber::get_post($post->ID);
    }

    public static function getContext()
    {
        return self::$context;
    }

    public static function addAdditionalContext($additional_context)
    {
        self::$context = array_merge(self::$context, $additional_context);
    }
}
