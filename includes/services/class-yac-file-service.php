<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_File_Service {

    /**
     * Upload a file to the WordPress uploads directory.
     *
     * @param array $file
     * @return array|WP_Error
     */
    public static function upload($file) {

        if (empty($file)) {
            return new WP_Error(
                'yac_no_file',
                'No file uploaded.'
            );
        }

        /**
         * Maximum upload size: 5 MB.
         */
        $max_size = 5 * MB_IN_BYTES;

        if (!empty($file['size']) && $file['size'] > $max_size) {
            return new WP_Error(
                'yac_file_too_large',
                'File size must not exceed 5 MB.'
            );
        }

        /**
         * Only allow receipt/document formats.
         */
        $allowed_mimes = [
            'jpg|jpeg' => 'image/jpeg',
            'png'      => 'image/png',
            'webp'     => 'image/webp',
            'pdf'      => 'application/pdf',
        ];

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $overrides = [
            'test_form' => false,
            'mimes'     => $allowed_mimes,
        ];

        return wp_handle_upload($file, $overrides);

    }

}