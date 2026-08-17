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
    public static function upload($file, $context = 'payment_proof') {

        if (empty($file)) {
            return new WP_Error(
                'yac_no_file',
                'No file uploaded.'
            );
        }

        $max_size = $context === 'resource'
            ? 50 * MB_IN_BYTES
            : 5 * MB_IN_BYTES;

        if (!empty($file['size']) && $file['size'] > $max_size) {
            return new WP_Error(
                'yac_file_too_large',
                'File size must not exceed ' . size_format($max_size) . '.'
            );
        }

        $allowed_mimes = self::allowed_mimes($context);

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $overrides = [
            'test_form' => false,
            'mimes'     => $allowed_mimes,
        ];

        return wp_handle_upload($file, $overrides);

    }

    private static function allowed_mimes($context) {

        if ($context === 'resource') {
            return [
                'pdf'       => 'application/pdf',
                'doc'       => 'application/msword',
                'docx'      => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'ppt'       => 'application/vnd.ms-powerpoint',
                'pptx'      => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'xls'       => 'application/vnd.ms-excel',
                'xlsx'      => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'jpg|jpeg'  => 'image/jpeg',
                'png'       => 'image/png',
                'webp'      => 'image/webp',
                'mp4|m4v'   => 'video/mp4',
                'mov|qt'    => 'video/quicktime',
                'webm'      => 'video/webm',
            ];
        }

        return [
            'jpg|jpeg' => 'image/jpeg',
            'png'      => 'image/png',
            'webp'     => 'image/webp',
            'pdf'      => 'application/pdf',
        ];

    }

}
