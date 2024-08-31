<?php

function get_mock_products($count = 3)
{
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => $count,
        'orderby' => 'rand',
    );

    $products = wc_get_products($args);

    // Ensure we always have the requested number of products
    while (count($products) < $count) {
        $dummy_product = new WC_Product_Simple();
        $dummy_product->set_name('Dummy Product ' . (count($products) + 1));
        $dummy_product->set_price(rand(10, 100));
        $products[] = $dummy_product;
    }

    return $products;
}

// Add this filter to further customize the cart context
add_filter('ag_prepare_cart_context', 'customize_builder_cart_context', 10, 3);

function customize_builder_cart_context($context, $router, $is_builder_request)
{
    if ($is_builder_request) {
        // Add more customization to the mock cart data
        $context['cart']['coupon_applied'] = true;
        $context['cart']['coupon_code'] = 'SAMPLE10';
        $subtotal = (float) str_replace(',', '', $context['cart']['subtotal']); // Remove comma and convert to float
        $context['cart']['discount_total'] = number_format($subtotal * 0.1, 2); // 10% discount

        // Add a sample cross-sell product
        $cross_sell_products = get_mock_products(1);
        if (!empty($cross_sell_products)) {
            $cross_sell = $cross_sell_products[0];
            $context['cart']['cross_sells'] = [
                [
                    'id' => $cross_sell->get_id(),
                    'name' => $cross_sell->get_name(),
                    'price' => number_format((float) $cross_sell->get_price(), 2),
                    'image' => wp_get_attachment_url($cross_sell->get_image_id()),
                ],
            ];
        }
    }
    return $context;
}

add_filter('ag_prepare_search_context', 'customize_search_context', 10, 3);

function customize_search_context($context, $router, $is_builder_request)
{
    $view_type = $context['view_type']['name'];

    // Add any additional customization based on the view type
    if ($view_type === 'product') {
        // $context['search']['orderby_options'] = wc_get_catalog_ordering_options();
        $context['search']['default_sorting'] = get_option('woocommerce_default_catalog_orderby', 'menu_order');
    } elseif ($context['view_type']['is_taxonomy']) {
        // Add taxonomy-specific information if needed
        $context['search']['taxonomy_info'] = [
            'name' => get_taxonomy($view_type)->labels->singular_name,
            'description' => get_taxonomy($view_type)->description,
        ];
    }

    return $context;
}
