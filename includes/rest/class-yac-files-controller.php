<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Files_Controller extends YAC_REST_Controller {

    /**
     * Register routes.
     */
    public function register_routes() {

        register_rest_route(
            $this->namespace,
            '/files/upload',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'upload_file'],
                    'permission_callback' => [
                        YAC_Auth_Helper::class,
                        'authorize',
                    ],
                ],
            ]
        );

        /**
         * Protected file download.
         *
         * GET /files/{id}/download
         */
        register_rest_route(
            $this->namespace,
            '/files/(?P<id>\d+)/download',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'download_file'],
                    'permission_callback' => [
                        YAC_Auth_Helper::class,
                        'authorize',
                    ],
                ],
            ]
        );

    }

    /**
     * Upload file.
     */
    public function upload_file(WP_REST_Request $request) {

        global $wpdb;

        $user_id = YAC_Auth_Helper::user_id();

        if (!$user_id) {
            return $this->error('Unauthorized.', 401);
        }

        $files = $request->get_file_params();

        if (empty($files['file'])) {
            return $this->error('No file uploaded.', 422);
        }

        $related_type = sanitize_key(
            $request->get_param('related_type')
        );

        $related_id = absint(
            $request->get_param('related_id')
        );

        $file_type = sanitize_key(
            $request->get_param('file_type')
        );

        if (empty($related_type) || empty($file_type)) {
            return $this->error(
                'related_type and file_type are required.',
                422
            );
        }

        if ($related_type === 'resource') {
            return $this->upload_resource_file(
                $files['file'],
                $user_id,
                $related_id,
                $file_type
            );
        }

        if ($related_type !== 'payment') {
            return $this->error('Invalid related type.', 422);
        }

        if (empty($related_id)) {
            return $this->error(
                'related_id is required.',
                422
            );
        }

        if ($file_type !== 'proof_of_payment') {
            return $this->error('Invalid file type.', 422);
        }

        /**
         * Confirm that the payment belongs to the logged-in user.
         */
        $payment = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, user_id, order_id
                 FROM " . YAC_Payments_Table::table_name() . "
                 WHERE id = %d
                 AND user_id = %d",
                $related_id,
                $user_id
            ),
            ARRAY_A
        );

        if (!$payment) {
            return $this->error('Payment not found.', 404);
        }

        /**
         * Upload the physical file.
         */
        $upload = YAC_File_Service::upload($files['file']);

        if (is_wp_error($upload)) {
            return $this->error(
                $upload->get_error_message(),
                422
            );
        }

        /**
         * Save the protected file record.
         */
        $inserted = $wpdb->insert(
            YAC_Files_Table::table_name(),
            [
                'user_id'       => $user_id,
                'related_type'  => $related_type,
                'related_id'    => $related_id,
                'file_type'     => $file_type,
                'file_name'     => basename($upload['file']),
                'original_name' => sanitize_file_name(
                    $files['file']['name']
                ),
                'file_path'     => $upload['file'],
                'mime_type'     => $upload['type'],
                'file_size'     => absint($files['file']['size']),
                'visibility'    => 'private',
                'uploaded_by'   => $user_id,
            ],
            [
                '%d',
                '%s',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
                '%d',
            ]
        );

        if ($inserted === false) {
            return $this->error(
                'Unable to save file record.',
                500
            );
        }

        $file_id = (int) $wpdb->insert_id;

        /**
         * Mark the payment as having proof of payment.
         *
         * payment_status remains pending until an admin verifies it.
         */
        $payment_updated = $wpdb->update(
            YAC_Payments_Table::table_name(),
            [
                'has_pop'        => 1,
                'payment_status' => 'pending',
                'submitted_at'   => current_time('mysql'),
            ],
            [
                'id'      => $related_id,
                'user_id' => $user_id,
            ],
            [
                '%d',
                '%s',
                '%s',
            ],
            [
                '%d',
                '%d',
            ]
        );

        if ($payment_updated === false) {
            return $this->error(
                'Proof uploaded, but payment status could not be updated.',
                500
            );
        }

        /**
         * Move the order to under review.
         */
        $order_updated = $wpdb->update(
            YAC_Orders_Table::table_name(),
            [
                'order_status'   => 'under_review',
                'payment_status' => 'pending',
            ],
            [
                'id'      => (int) $payment['order_id'],
                'user_id' => $user_id,
            ],
            [
                '%s',
                '%s',
            ],
            [
                '%d',
                '%d',
            ]
        );

        if ($order_updated === false) {
            return $this->error(
                'Proof uploaded, but order status could not be updated.',
                500
            );
        }

        return $this->success([
            'file_id'        => $file_id,
            'user_id'        => $user_id,
            'related_type'   => $related_type,
            'related_id'     => $related_id,
            'file_type'      => $file_type,
            'file_name'      => basename($upload['file']),
            'original_name'  => sanitize_file_name(
                $files['file']['name']
            ),
            'mime_type'      => $upload['type'],
            'file_size'      => absint($files['file']['size']),
            'payment_status' => 'pending',
            'order_status'   => 'under_review',
            'message'        => 'Proof of payment submitted and awaiting verification.',
        ]);

    }

    private function upload_resource_file($file, $user_id, $related_id, $file_type) {

        global $wpdb;

        if (!user_can($user_id, 'manage_options')) {
            return $this->error('Administrator access required.', 403);
        }

        if ($file_type !== 'resource_file') {
            return $this->error('Invalid file type.', 422);
        }

        $upload = YAC_File_Service::upload($file, 'resource');

        if (is_wp_error($upload)) {
            return $this->error(
                $upload->get_error_message(),
                422
            );
        }

        $inserted = $wpdb->insert(
            YAC_Files_Table::table_name(),
            [
                'user_id'       => $user_id,
                'related_type'  => 'resource',
                'related_id'    => $related_id ?: 0,
                'file_type'     => 'resource_file',
                'file_name'     => basename($upload['file']),
                'original_name' => sanitize_file_name($file['name']),
                'file_path'     => $upload['file'],
                'mime_type'     => $upload['type'],
                'file_size'     => absint($file['size']),
                'visibility'    => 'private',
                'uploaded_by'   => $user_id,
            ],
            [
                '%d',
                '%s',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
                '%d',
            ]
        );

        if ($inserted === false) {
            return $this->error(
                'Unable to save file record.',
                500
            );
        }

        return $this->success([
            'file_id'       => (int) $wpdb->insert_id,
            'user_id'       => $user_id,
            'related_type'  => 'resource',
            'related_id'    => $related_id ?: 0,
            'file_type'     => 'resource_file',
            'file_name'     => basename($upload['file']),
            'original_name' => sanitize_file_name($file['name']),
            'mime_type'     => $upload['type'],
            'file_size'     => absint($file['size']),
            'message'       => 'Resource file uploaded successfully.',
        ]);

    }

    /**
     * Download protected file.
     */
    public function download_file(WP_REST_Request $request) {

        global $wpdb;

        $user_id = YAC_Auth_Helper::user_id();
        $file_id = absint($request['id']);

        if (!$user_id) {
            return $this->error('Unauthorized.', 401);
        }

        if (!$file_id) {
            return $this->error('Invalid file ID.', 422);
        }

        $file = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM " . YAC_Files_Table::table_name() . "
                 WHERE id = %d",
                $file_id
            ),
            ARRAY_A
        );

        if (!$file) {
            return $this->error('File not found.', 404);
        }

        $is_admin = user_can($user_id, 'manage_options');

        $can_download_resource = $this->can_download_resource_file($file, $user_id);

        if (
            !$is_admin &&
            (int) $file['user_id'] !== (int) $user_id &&
            !$can_download_resource
        ) {
            return $this->error(
                'You do not have permission to access this file.',
                403
            );
        }

        if (
            empty($file['file_path']) ||
            !file_exists($file['file_path'])
        ) {
            return $this->error(
                'File is missing from the server.',
                404
            );
        }

        header('Content-Type: ' . $file['mime_type']);

        header(
            'Content-Disposition: attachment; filename="' .
            basename($file['original_name']) .
            '"'
        );

        header(
            'Content-Length: ' .
            filesize($file['file_path'])
        );

        header('X-Content-Type-Options: nosniff');

        readfile($file['file_path']);
        exit;

    }

    private function can_download_resource_file($file, $user_id) {

        if (
            $file['related_type'] !== 'resource' ||
            $file['file_type'] !== 'resource_file'
        ) {
            return false;
        }

        return (bool) YAC_Resource_Service::find_file_resource(
            $file['id'],
            $user_id
        );

    }

}
