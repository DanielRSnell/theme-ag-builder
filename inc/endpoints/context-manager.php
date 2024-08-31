<?php

function ag_render($router)
{
    ag_debug_log("Starting ag_render function", 'info');
    ag_debug_log("Router information: " . print_r($router, true), 'debug');

    if (!is_array($router) || !isset($router['view_info']['view'])) {
        ag_debug_log("Invalid router information provided to ag_render", 'error');
        echo "Error: Invalid router information";
        return;
    }

    $view = $router['view_info']['view'];
    $route_type = $router['route_type'];
    $is_builder_request = isset($router['is_builder_request']) ? $router['is_builder_request'] : false;
    $if_single = isset($router['if_single']) ? $router['if_single'] : null;

    $context = Timber::context();
    $context = ag_prepare_context($context, $router, $route_type, $is_builder_request, $if_single);

    if (isset($_GET['agnostic']) && $_GET['agnostic'] === 'context') {
        header('Content-Type: application/json');
        echo json_encode($context, JSON_PRETTY_PRINT);
        exit;
    }

    if ($view === 'theme') {
        ag_render_theme_view($router, $context);
    } else {
        ag_render_custom_view($view, $context);
    }

    ag_debug_log("Finished ag_render function", 'info');
}

function ag_prepare_context($context, $router, $route_type, $is_builder_request = false, $if_single = null)
{
    switch ($route_type) {
        case 'single':
            return ag_prepare_single_context($context, $router, $is_builder_request, $if_single);
        case 'product':
            return ag_prepare_product_single_context($context, $router, $is_builder_request, $if_single);
        case 'archive':
            return ag_prepare_archive_context($context, $router, $is_builder_request);
        case 'shop':
        case 'product_term':
            return ag_prepare_product_archive_context($context, $router, $is_builder_request, $if_single);
        case 'front_page':
            return ag_prepare_front_page_context($context, $router, $is_builder_request);
        case 'search':
            return ag_prepare_search_context($context, $router, $is_builder_request);
        case 'cart':
            return ag_prepare_cart_context($context, $router, $is_builder_request);
        case 'checkout':
            return ag_prepare_checkout_context($context, $router, $is_builder_request);
        case 'account':
            return ag_prepare_account_context($context, $router, $is_builder_request);
        default:
            return ag_prepare_default_context($context, $router, $is_builder_request);
    }
}

function ag_prepare_single_context($context, $router, $is_builder_request, $if_single)
{
    $context['meta'] = $router;
    if ($is_builder_request && $if_single) {
        $context['post'] = Timber::get_post($if_single);
    } else {
        $context['post'] = Timber::get_post();
    }
    return apply_filters('ag_prepare_single_context', $context, $router, $is_builder_request, $if_single);
}

function ag_prepare_product_single_context($context, $router, $is_builder_request, $if_single)
{
    $context['meta'] = $router;

    if ($is_builder_request && $if_single) {
        $context['post'] = Timber::get_post($if_single);
        $product_id = $if_single;
    } else {
        $context['post'] = Timber::get_post();
        $product_id = $context['post']->ID;
    }

    if (function_exists('wc_get_product')) {
        $product = wc_get_product($product_id);
        if ($product) {
            $context['product'] = ag_get_product_data($product);
        } else {
            $context['product'] = $is_builder_request;
        }
    }

    return apply_filters('ag_prepare_product_single_context', $context, $router, $is_builder_request, $if_single);
}

function ag_get_product_attributes($product)
{
    $attributes = array();
    $product_attributes = $product->get_attributes();

    foreach ($product_attributes as $attribute_name => $attribute) {
        if (is_a($attribute, 'WC_Product_Attribute')) {
            $attribute_data = $attribute->get_data();
            $attribute_options = $attribute->get_options();
            $attribute_data['options'] = array_map('strval', $attribute_options);
            $attributes[$attribute_name] = $attribute_data;
        } else {
            $attributes[$attribute_name] = strval($attribute);
        }
    }

    return $attributes;
}

function ag_get_product_data($product)
{
    if (!$product || !is_a($product, 'WC_Product')) {
        return null;
    }

    $data = array(
        'id' => $product->get_id(),
        'name' => $product->get_name(),
        'type' => $product->get_type(),
        'status' => $product->get_status(),
        'featured' => $product->get_featured(),
        'catalog_visibility' => $product->get_catalog_visibility(),
        'description' => $product->get_description(),
        'short_description' => $product->get_short_description(),
        'sku' => $product->get_sku(),
        'price' => $product->get_price(),
        'regular_price' => $product->get_regular_price(),
        'sale_price' => $product->get_sale_price(),
        'date_on_sale_from' => $product->get_date_on_sale_from(),
        'date_on_sale_to' => $product->get_date_on_sale_to(),
        'total_sales' => $product->get_total_sales(),
        'tax_status' => $product->get_tax_status(),
        'tax_class' => $product->get_tax_class(),
        'manage_stock' => $product->get_manage_stock(),
        'stock_quantity' => $product->get_stock_quantity(),
        'stock_status' => $product->get_stock_status(),
        'backorders' => $product->get_backorders(),
        'sold_individually' => $product->get_sold_individually(),
        'weight' => $product->get_weight(),
        'length' => $product->get_length(),
        'width' => $product->get_width(),
        'height' => $product->get_height(),
        'dimensions' => $product->get_dimensions(),
        'upsell_ids' => $product->get_upsell_ids(),
        'cross_sell_ids' => $product->get_cross_sell_ids(),
        'parent_id' => $product->get_parent_id(),
        'reviews_allowed' => $product->get_reviews_allowed(),
        'purchase_note' => $product->get_purchase_note(),
        'attributes' => ag_get_product_attributes($product),
        'default_attributes' => $product->get_default_attributes(),
        'menu_order' => $product->get_menu_order(),
        'virtual' => $product->get_virtual(),
        'downloadable' => $product->get_downloadable(),
        'category_ids' => $product->get_category_ids(),
        'tag_ids' => $product->get_tag_ids(),
        'image_id' => $product->get_image_id(),
        'gallery_image_ids' => $product->get_gallery_image_ids(),
    );

    if ($product->is_type('variable')) {
        $data['variations'] = array_map(function ($variation) {
            return ag_get_product_data($variation);
        }, $product->get_available_variations('objects'));
    }

    return $data;
}

function ag_prepare_archive_context($context, $router, $is_builder_request)
{
    $context['meta'] = $router;
    $context['posts'] = $is_builder_request ? Timber::get_posts(['post_type' => $router['post_type']]) : Timber::get_posts();
    $archive_details = ag_get_archive_details($context, $is_builder_request, $router);
    $context['archive'] = [
        'title' => $archive_details['title'],
        'description' => $archive_details['description'],
    ];
    return apply_filters('ag_prepare_archive_context', $context, $router, $is_builder_request);
}

function ag_prepare_product_archive_context($context, $router, $is_builder_request, $if_single = null)
{
    $context['meta'] = $router ?? 'product';

    if ($is_builder_request && $if_single) {
        $term = get_term($if_single);
        if ($term && !is_wp_error($term)) {
            $context['term'] = [
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'description' => wp_strip_all_tags($term->description),
                'parent' => $term->parent,
                'count' => $term->count,
            ];
            $context['posts'] = Timber::get_posts([
                'post_type' => 'product',
                'tax_query' => [
                    [
                        'taxonomy' => $term->taxonomy,
                        'field' => 'term_id',
                        'terms' => $term->term_id,
                    ],
                ],
            ]);
            $context['archive'] = [
                'title' => $term->name,
                'description' => wp_strip_all_tags($term->description),
            ];
        }
    } else {
        $context['posts'] = $is_builder_request ? Timber::get_posts(['post_type' => 'product']) : Timber::get_posts();
        $archive_details = ag_get_archive_details($context, $is_builder_request, $router);
        $context['archive'] = [
            'title' => $archive_details['title'],
            'description' => $archive_details['description'],
        ];
    }

    if (function_exists('is_shop') && (is_shop() || ($is_builder_request && !$if_single))) {
        $shop_page_id = $is_builder_request ? wc_get_page_id('shop') : get_option('woocommerce_shop_page_id');
        $context['shop_page'] = Timber::get_post($shop_page_id);
        $context['archive']['title'] = $context['shop_page']->post_title;
        $context['archive']['description'] = wp_strip_all_tags($context['shop_page']->post_content);
    }

    if (!$is_builder_request && (is_product_category() || is_product_tag())) {
        $term = get_queried_object();
        $context['term'] = [
            'id' => $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
            'description' => wp_strip_all_tags($term->description),
            'parent' => $term->parent,
            'count' => $term->count,
        ];
    }

    return apply_filters('ag_prepare_product_archive_context', $context, $router, $is_builder_request, $if_single);
}

function ag_prepare_front_page_context($context, $router, $is_builder_request)
{
    $context['meta'] = $router;
    if ($is_builder_request) {
        $front_page_id = get_option('page_on_front');
        $context['post'] = Timber::get_post($front_page_id);
    }
    return apply_filters('ag_prepare_front_page_context', $context, $router, $is_builder_request);
}

function ag_prepare_search_context($context, $router, $is_builder_request)
{
    $context['meta'] = $router;

    $populate = $context['populate'] ?? [];
    $view_type = $populate['view_type'] ?? 'post';
    $search_query = $populate['search_query'] ?? '';
    $posts_per_page = get_option('posts_per_page', 10);
    $paged = get_query_var('paged') ? get_query_var('paged') : 1;

    // Determine if view_type is a post type or taxonomy
    $is_taxonomy = taxonomy_exists($view_type);
    $post_type = $is_taxonomy ? 'post' : $view_type;

    // Set up the arguments for the search query
    $args = array(
        'post_type' => $post_type,
        'post_status' => 'publish',
        'posts_per_page' => $posts_per_page,
        'paged' => $paged,
        's' => $search_query,
    );

    // If it's a taxonomy, add a tax query
    if ($is_taxonomy) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => $view_type,
                'operator' => 'EXISTS',
            ),
        );
    }

    // If it's a product search, add WooCommerce specific arguments
    if ($post_type === 'product') {
        $args['meta_query'] = WC()->query->get_meta_query();
        $args['tax_query'] = WC()->query->get_tax_query();
    }

    // Perform the search
    $search_results = new WP_Query($args);

    // Prepare the posts/products for the context
    $context['posts'] = array_map(function ($post) use ($post_type) {
        if ($post_type === 'product' && function_exists('wc_get_product')) {
            $product = wc_get_product($post);
            return [
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'permalink' => $product->get_permalink(),
                'price_html' => $product->get_price_html(),
                'description' => $product->get_short_description(),
                'image' => wp_get_attachment_url($product->get_image_id()),
                'average_rating' => $product->get_average_rating(),
                'review_count' => $product->get_review_count(),
                'categories' => wc_get_product_category_list($product->get_id()),
                'type' => $product->get_type(),
                'on_sale' => $product->is_on_sale(),
                'stock_status' => $product->get_stock_status(),
            ];
        } else {
            return [
                'id' => $post->ID,
                'title' => get_the_title($post),
                'permalink' => get_permalink($post),
                'excerpt' => wp_trim_words(get_the_excerpt($post), 20),
                'image' => get_the_post_thumbnail_url($post, 'medium'),
                'author' => get_the_author_meta('display_name', $post->post_author),
                'date' => get_the_date('', $post),
                'categories' => wp_get_post_categories($post->ID, ['fields' => 'names']),
            ];
        }
    }, $search_results->posts);

    $context['search'] = [
        'query' => $search_query,
        'total_results' => $search_results->found_posts,
        'current_page' => $paged,
        'total_pages' => $search_results->max_num_pages,
        'posts_per_page' => $posts_per_page,
    ];

    // Add pagination info
    $context['pagination'] = [
        'total' => $search_results->max_num_pages,
        'current' => $paged,
        'show_all' => false,
        'end_size' => 1,
        'mid_size' => 2,
        'prev_next' => true,
        'prev_text' => __('« Previous'),
        'next_text' => __('Next »'),
    ];

    // Add view type info
    $context['view_type'] = [
        'name' => $view_type,
        'is_taxonomy' => $is_taxonomy,
        'post_type' => $post_type,
    ];

    return apply_filters('ag_prepare_search_context', $context, $router, $is_builder_request);
}

function ag_prepare_cart_context($context, $router, $is_builder_request)
{
    $context['meta'] = $router;

    if ($is_builder_request) {
        // Get real products for mock cart
        $mock_products = get_mock_products(3); // Get 3 random products

        $total = 0;
        $items = [];
        $item_count = 0;

        foreach ($mock_products as $product) {
            $quantity = rand(1, 3); // Random quantity between 1 and 3
            $price = (float) $product->get_price(); // Ensure price is a float
            $line_total = $quantity * $price;
            $total += $line_total;
            $item_count += $quantity;

            $items[] = [
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'quantity' => $quantity,
                'price' => number_format($price, 2),
                'total' => number_format($line_total, 2),
            ];
        }

        $tax_rate = 0.1; // 10% tax rate for example
        $tax_total = $total * $tax_rate;
        $grand_total = $total + $tax_total;

        $context['cart'] = [
            'total' => number_format($grand_total, 2),
            'subtotal' => number_format($total, 2),
            'tax_total' => number_format($tax_total, 2),
            'item_count' => $item_count,
            'items' => $items,
        ];
    } elseif (function_exists('WC')) {
        $cart = WC()->cart;
        $context['cart'] = [
            'total' => $cart->get_total(),
            'subtotal' => $cart->get_subtotal(),
            'tax_total' => $cart->get_total_tax(),
            'item_count' => $cart->get_cart_contents_count(),
            'items' => array_map(function ($item) {
                $product = $item['data'];
                return [
                    'id' => $item['product_id'],
                    'name' => $product->get_name(),
                    'quantity' => $item['quantity'],
                    'price' => number_format((float) $product->get_price(), 2),
                    'total' => number_format((float) $item['line_total'], 2),
                ];
            }, $cart->get_cart()),
        ];
    }

    return apply_filters('ag_prepare_cart_context', $context, $router, $is_builder_request);
}

function ag_prepare_checkout_context($context, $router, $is_builder_request)
{
    $context['meta'] = $router;
    if (function_exists('WC')) {
        $checkout = WC()->checkout();
        $context['checkout'] = [
            'fields' => $checkout->get_checkout_fields(),
            'payment_methods' => WC()->payment_gateways->get_available_payment_gateways(),
        ];
    }
    return apply_filters('ag_prepare_checkout_context', $context, $router, $is_builder_request);
}

function ag_prepare_account_context($context, $router, $is_builder_request)
{
    $context['meta'] = $router;
    if (function_exists('wc_get_account_menu_items')) {
        $context['account_menu_items'] = wc_get_account_menu_items();
    }
    if (is_user_logged_in() || $is_builder_request) {
        $user = $is_builder_request ? wp_get_current_user() : get_user_by('id', 1);
        $context['user'] = [
            'id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
        ];
    }
    return apply_filters('ag_prepare_account_context', $context, $router, $is_builder_request);
}

function ag_prepare_default_context($context, $router, $is_builder_request)
{
    $context['meta'] = 'default';
    return apply_filters('ag_prepare_default_context', $context, $router, $is_builder_request);
}

function ag_get_archive_details($context, $is_builder_request, $router)
{
    $archive_details = [
        'title' => '',
        'description' => '',
    ];

    if ($is_builder_request) {
        $archive_details['title'] = $router['archive_title'] ?? 'Archive Title';
        $archive_details['description'] = $router['archive_description'] ?? 'Archive Description';
    } else {
        if (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            $archive_details['title'] = $term->name;
            $archive_details['description'] = wp_strip_all_tags(term_description($term->term_id, $term->taxonomy));
        } elseif (is_post_type_archive()) {
            $archive_details['title'] = post_type_archive_title('', false);
            $post_type = get_queried_object();
            if ($post_type) {
                $archive_details['description'] = wp_strip_all_tags(get_the_post_type_description($post_type->name));
            }
        } elseif (is_author()) {
            $archive_details['title'] = get_the_author();
            $archive_details['description'] = wp_strip_all_tags(get_the_author_meta('description'));
        } elseif (is_date()) {
            $archive_details['title'] = get_the_archive_title();
        }
    }

    return apply_filters('ag_get_archive_details', $archive_details, $context, $is_builder_request, $router);
}
