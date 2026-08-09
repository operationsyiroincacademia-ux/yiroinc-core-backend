<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Products_Controller extends YAC_REST_Controller {

    /**
     * Register routes.
     */
    public function register_routes() {

        /**
         * Product catalogue.
         *
         * GET /products
         */
        register_rest_route(
            $this->namespace,
            '/products',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_products'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
            ]
        );

        /**
         * Single product.
         *
         * GET /products/{id}
         */
        register_rest_route(
            $this->namespace,
            '/products/(?P<id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_product'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
            ]
        );

    }

    /**
     * Get WooCommerce products.
     */
    public function get_products(WP_REST_Request $request) {

        if (!function_exists('wc_get_products')) {
            return $this->error('WooCommerce is unavailable.', 503);
        }

        $page = max(1, (int) ($request->get_param('page') ?: 1));
        $per_page = max(1, (int) ($request->get_param('per_page') ?: 20));

        if ($per_page > 100) {
            $per_page = 100;
        }

        $products = wc_get_products([
            'status' => 'publish',
            'limit'  => $per_page,
            'page'   => $page,
            'return' => 'objects',
        ]);

        $total_products = wc_get_products([
            'status' => 'publish',
            'limit'  => -1,
            'return' => 'ids',
        ]);

        $items = array_map(
            [$this, 'format_product'],
            $products
        );

        $total = count($total_products);

        return $this->success([
            'products' => $items,
            'pagination' => [
                'page'        => $page,
                'per_page'    => $per_page,
                'total'       => $total,
                'total_pages' => (int) ceil($total / $per_page),
            ],
        ]);

    }

    /**
     * Get one WooCommerce product.
     */
    public function get_product(WP_REST_Request $request) {

        if (!function_exists('wc_get_product')) {
            return $this->error('WooCommerce is unavailable.', 503);
        }

        $product = wc_get_product((int) $request['id']);

        if (!$product || $product->get_status() !== 'publish') {
            return $this->error('Product not found.', 404);
        }

        return $this->success([
            'product' => $this->format_product($product),
        ]);

    }

    /**
     * Format product response.
     */
    private function format_product($product) {

        $image_id = $product->get_image_id();

        return [
            'id'                => $product->get_id(),
            'name'              => $product->get_name(),
            'slug'              => $product->get_slug(),
            'sku'               => $product->get_sku(),
            'type'              => $product->get_type(),
            'short_description' => wp_strip_all_tags($product->get_short_description()),
            'description'       => wp_kses_post($product->get_description()),
            'price'             => (float) $product->get_price(),
            'regular_price'     => (float) $product->get_regular_price(),
            'sale_price'        => $product->get_sale_price() !== ''
                ? (float) $product->get_sale_price()
                : null,
            'currency'          => get_woocommerce_currency(),
            'image'             => $image_id
                ? wp_get_attachment_image_url($image_id, 'large')
                : null,
        ];

    }

}