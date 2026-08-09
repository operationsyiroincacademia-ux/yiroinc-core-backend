<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Order_Service extends YAC_Base_Service {

    /**
     * Create an order.
     *
     * @param array $data
     * @return int|false
     */
    public static function create($data) {

        return self::insert(
            YAC_Orders_Table::table_name(),
            [
                'order_number'          => $data['order_number'],
                'user_id'               => $data['user_id'],
                'woo_product_id'        => $data['woo_product_id'],
                'woo_variation_id'      => $data['woo_variation_id'] ?? null,
                'product_name_snapshot' => $data['product_name_snapshot'],
                'sku_snapshot'          => $data['sku_snapshot'] ?? null,
                'quantity'              => $data['quantity'] ?? 1,
                'unit_price'            => $data['unit_price'],
                'total_price'           => $data['total_price'],
                'customer_note'         => $data['customer_note'] ?? null,
                'admin_note'            => null,
            ]
        );

    }

}