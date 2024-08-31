<?php
/**
 * Timber Context Manager
 *
 * @package Agnostic
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Class TimberContextManager
 */
class Timber_Context_Manager
{
    /**
     * Holds the context data.
     *
     * @var array
     */
    private static $context = null;

    /**
     * Generate the context.
     *
     * @param array  $atts    Shortcode attributes.
     * @param string $content Shortcode content.
     * @return array
     */
    public static function generate_context($atts, $content)
    {
        self::$context = Timber::context();
        self::$context['is_editor'] = is_admin() && current_user_can('edit_others_posts');
        self::$context['attributes'] = $atts;
        self::$context['singular'] = false;
        self::$context['archive'] = false;
        self::$context['tax'] = false;
        self::$context['editor'] = false;

        self::process_content_type($atts);

        return self::$context;
    }

    /**
     * Process the content type based on attributes.
     *
     * @param array $atts Shortcode attributes.
     */
    private static function process_content_type($atts)
    {
        if (isset($atts['content_type'])) {
            switch ($atts['content_type']) {
                case 'archive':
                    self::process_archive($atts);
                    break;
                case 'single':
                    self::process_single($atts);
                    break;
                case 'tax':
                    self::process_tax($atts);
                    break;
                case 'search':
                    self::process_search($atts);
                    break;
            }
        } else {
            self::process_default();
        }
    }

    /**
     * Process archive content type.
     *
     * @param array $atts Shortcode attributes.
     */
    private static function process_archive($atts)
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

    /**
     * Process single content type.
     *
     * @param array $atts Shortcode attributes.
     */
    private static function process_single($atts)
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

    /**
     * Process taxonomy content type.
     *
     * @param array $atts Shortcode attributes.
     */
    private static function process_tax($atts)
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

    /**
     * Process search content type.
     *
     * @param array $atts Shortcode attributes.
     */
    private static function process_search($atts)
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

    /**
     * Process default content type.
     */
    private static function process_default()
    {
        if (is_singular()) {
            self::process_default_single();
        } elseif (is_tax() || is_category() || is_tag()) {
            self::process_default_tax();
        } elseif (is_archive()) {
            self::process_default_archive();
        } else {
            self::process_default_editor();
        }
    }

    /**
     * Process default single post.
     */
    private static function process_default_single()
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

    /**
     * Process default taxonomy.
     */
    private static function process_default_tax()
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

    /**
     * Process default archive.
     */
    private static function process_default_archive()
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

    /**
     * Process default editor.
     */
    private static function process_default_editor()
    {
        global $post;
        self::$context['editor'] = true;
        if ($post) {
            self::$context['post'] = Timber::get_post($post->ID);
        }
    }

    /**
     * Get the current context.
     *
     * @return array
     */
    public static function get_context()
    {
        return self::$context;
    }

    /**
     * Add additional context.
     *
     * @param array $additional_context Additional context to add.
     */
    public static function add_additional_context($additional_context)
    {
        self::$context = array_merge(self::$context, $additional_context);
    }
}
