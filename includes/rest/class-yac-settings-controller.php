<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Settings_Controller extends YAC_REST_Controller {

    public function register_routes() {

        register_rest_route(
            $this->namespace,
            '/admin/settings',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_admin_settings'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'update_admin_settings'],
                    'permission_callback' => [YAC_Auth_Helper::class, 'authorize_admin'],
                ],
            ]
        );
    
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

        $account = $this->bank_account_settings();

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

        $validation = $this->validate_bank_account_settings($data, true);

        if (is_wp_error($validation)) {
            return $validation;
        }

        if (!array_key_exists('currency', $data)) {
            $data['currency'] = 'NGN';
        }

        if (!array_key_exists('payment_instruction', $data)) {
            $data['payment_instruction'] = 'Use your order reference as the transfer narration.';
        }

        $this->save_bank_account_settings($data);

        return $this->success([
            'message'      => 'Bank account details updated successfully.',
            'bank_account' => $this->bank_account_settings(),
        ]);

    }

    /**
     * Get Admin settings.
     */
    public function get_admin_settings(WP_REST_Request $request) {

        return $this->success([
            'bank_account' => $this->bank_account_settings(),
        ]);

    }

    /**
     * Update Admin settings.
     */
    public function update_admin_settings(WP_REST_Request $request) {

        $data = $request->get_json_params();

        if (!is_array($data)) {
            return $this->error('Invalid settings payload.', 422);
        }

        $bank_account = isset($data['bank_account']) && is_array($data['bank_account'])
            ? $data['bank_account']
            : $data;

        $allowed_fields = [
            'account_name',
            'account_number',
            'bank_name',
            'currency',
            'payment_instruction',
        ];

        $updates = [];

        foreach ($allowed_fields as $field) {
            if (array_key_exists($field, $bank_account)) {
                $updates[$field] = $bank_account[$field];
            }
        }

        if (empty($updates)) {
            return $this->error('No supported settings provided.', 422);
        }

        $validation = $this->validate_bank_account_settings($updates, false);

        if (is_wp_error($validation)) {
            return $validation;
        }

        $this->save_bank_account_settings($updates);

        return $this->success([
            'message'      => 'Settings updated successfully.',
            'bank_account' => $this->bank_account_settings(),
        ]);

    }

    private function bank_account_settings() {

        return [
            'account_name'        => get_option('yac_bank_account_name', ''),
            'account_number'      => get_option('yac_bank_account_number', ''),
            'bank_name'           => get_option('yac_bank_name', ''),
            'currency'            => get_option('yac_bank_currency', 'NGN'),
            'payment_instruction' => get_option(
                'yac_payment_instruction',
                'Use your order reference as the transfer narration.'
            ),
        ];

    }

    private function validate_bank_account_settings(array $data, $require_all) {

        $max_lengths = [
            'account_name'        => 150,
            'account_number'      => 50,
            'bank_name'           => 150,
            'currency'            => 10,
            'payment_instruction' => 1000,
        ];

        foreach ($max_lengths as $field => $max_length) {
            if ($require_all && in_array($field, ['account_name', 'account_number', 'bank_name'], true)) {
                $validation = YAC_Validation_Service::required($data, $field);

                if (is_wp_error($validation)) {
                    return $validation;
                }
            }

            if (!array_key_exists($field, $data)) {
                continue;
            }

            if (!is_scalar($data[$field])) {
                return new WP_Error(
                    'yac_invalid_setting',
                    "{$field} must be text.",
                    ['status' => 422]
                );
            }

            $value = (string) $data[$field];

            if (in_array($field, ['account_name', 'account_number', 'bank_name'], true) && trim($value) === '') {
                return new WP_Error(
                    'yac_invalid_setting',
                    "{$field} cannot be empty.",
                    ['status' => 422]
                );
            }

            $validation = YAC_Validation_Service::max_length($value, $max_length);

            if (is_wp_error($validation)) {
                return $validation;
            }
        }

        return true;

    }

    private function save_bank_account_settings(array $data) {

        if (array_key_exists('account_name', $data)) {
            update_option(
                'yac_bank_account_name',
                sanitize_text_field($data['account_name'])
            );
        }

        if (array_key_exists('account_number', $data)) {
            update_option(
                'yac_bank_account_number',
                sanitize_text_field((string) $data['account_number'])
            );
        }

        if (array_key_exists('bank_name', $data)) {
            update_option(
                'yac_bank_name',
                sanitize_text_field($data['bank_name'])
            );
        }

        if (array_key_exists('currency', $data)) {
            update_option(
                'yac_bank_currency',
                strtoupper(sanitize_text_field($data['currency']))
            );
        }

        if (array_key_exists('payment_instruction', $data)) {
            update_option(
                'yac_payment_instruction',
                sanitize_textarea_field($data['payment_instruction'])
            );
        }

    }

}
