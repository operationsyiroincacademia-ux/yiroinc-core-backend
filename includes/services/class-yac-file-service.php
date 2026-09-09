<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_File_Service {

    /**
     * Upload a private file outside the public WordPress uploads directory.
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

        $private_dir = self::private_upload_dir();
        $protected = self::protect_private_upload_dir($private_dir['basedir']);

        if (is_wp_error($protected)) {
            return $protected;
        }

        $rename_file = function ($uploaded_file) {
            if (!empty($uploaded_file['name'])) {
                $uploaded_file['name'] = self::randomized_file_name(
                    $uploaded_file['name']
                );
            }

            return $uploaded_file;
        };

        $upload_dir = function ($uploads) use ($private_dir) {
            return array_merge($uploads, $private_dir);
        };

        $overrides = [
            'test_form' => false,
            'mimes'     => $allowed_mimes,
        ];

        add_filter('wp_handle_upload_prefilter', $rename_file);
        add_filter('upload_dir', $upload_dir);

        try {
            $upload = wp_handle_upload($file, $overrides);
        } finally {
            remove_filter('upload_dir', $upload_dir);
            remove_filter('wp_handle_upload_prefilter', $rename_file);
        }

        if (!empty($upload['error'])) {
            return new WP_Error(
                'yac_upload_failed',
                $upload['error']
            );
        }

        return [
            'file' => $upload['file'],
            'type' => $upload['type'],
        ];

    }

    private static function private_upload_dir() {

        $subdir = '/' . current_time('Y') . '/' . current_time('m');
        $basedir = trailingslashit(WP_CONTENT_DIR) . 'yac-private-uploads';
        $baseurl = content_url('yac-private-uploads');

        return [
            'path'    => $basedir . $subdir,
            'url'     => $baseurl . $subdir,
            'subdir'  => $subdir,
            'basedir' => $basedir,
            'baseurl' => $baseurl,
            'error'   => false,
        ];

    }

    private static function protect_private_upload_dir($basedir) {

        if (!wp_mkdir_p($basedir)) {
            return new WP_Error(
                'yac_private_upload_dir_failed',
                'Unable to create private upload directory.'
            );
        }

        $htaccess_path = trailingslashit($basedir) . '.htaccess';
        $rules = "Require all denied\nDeny from all\n";

        if (!file_exists($htaccess_path)) {
            $written = file_put_contents($htaccess_path, $rules, LOCK_EX);

            if ($written === false) {
                return new WP_Error(
                    'yac_private_upload_protection_failed',
                    'Unable to protect private upload directory.'
                );
            }
        }

        return true;

    }

    private static function randomized_file_name($original_name) {

        $sanitized = sanitize_file_name($original_name);
        $extension = pathinfo($sanitized, PATHINFO_EXTENSION);

        try {
            $random = bin2hex(random_bytes(16));
        } catch (Exception $exception) {
            $random = str_replace('-', '', wp_generate_uuid4());
        }

        if (!empty($extension)) {
            return $random . '.' . strtolower($extension);
        }

        return $random;

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

        if ($context === 'support') {
            return [
                'jpg|jpeg' => 'image/jpeg',
                'png'      => 'image/png',
                'webp'     => 'image/webp',
                'pdf'      => 'application/pdf',
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
