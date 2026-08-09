<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Settings_Controller extends YAC_REST_Controller {

    public function register_routes() {
    
        register_rest_route(
            $this->namespace,
            '/settings/bank-account',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_bank_account'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize'],
                ],
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'update_bank_account'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );
    }
    
    /**
     * Get bank account details.
     */
    public function get_bank_account(WP_REST_Request $request) {

        $account = [
            'account_name'        => get_option('yac_bank_account_name', ''),
            'account_number'      => get_option('yac_bank_account_number', ''),
            'bank_name'           => get_option('yac_bank_name', ''),
            'currency'            => get_option('yac_bank_currency', 'NGN'),
            'payment_instruction' => get_option(
                'yac_payment_instruction',
                'Use your order reference as the transfer narration.'
            ),
        ];

        if (
            empty($account['account_name']) ||
            empty($account['account_number']) ||
            empty($account['bank_name'])
        ) {
            return $this->error('Bank account details have not been configured.', 404);
        }

        return $this->success([
            'bank_account' => $account,
        ]);

    }

    /**
     * Update bank account details.
     */
    public function update_bank_account(WP_REST_Request $request) {

        $data = $request->get_json_params();

        foreach (['account_name', 'account_number', 'bank_name'] as $field) {
            $validation = YAC_Validation_Service::required($data, $field);

            if (is_wp_error($validation)) {
                return $validation;
            }
        }

        update_option(
            'yac_bank_account_name',
            sanitize_text_field($data['account_name'])
        );

        update_option(
            'yac_bank_account_number',
            sanitize_text_field($data['account_number'])
        );

        update_option(
            'yac_bank_name',
            sanitize_text_field($data['bank_name'])
        );

        update_option(
            'yac_bank_currency',
            sanitize_text_field($data['currency'] ?? 'NGN')
        );

        update_option(
            'yac_payment_instruction',
            sanitize_textarea_field(
                $data['payment_instruction']
                ?? 'Use your order reference as the transfer narration.'
            )
        );

        return $this->success([
            'message' => 'Bank account details updated successfully.',
        ]);

    }

}