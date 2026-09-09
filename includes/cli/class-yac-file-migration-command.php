<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_CLI_Command')) {
    return;
}

class YAC_File_Migration_Command extends WP_CLI_Command {

    /**
     * Move legacy private files out of the public uploads directory.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Count and inspect rows without moving files.
     *
     * [--yes]
     * : Skip confirmation before moving files.
     *
     * [--limit=<number>]
     * : Maximum number of rows to process.
     *
     * ## EXAMPLES
     *
     *     wp yac migrate-private-files --dry-run
     *     wp yac migrate-private-files --yes
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function __invoke($args, $assoc_args) {

        global $wpdb;

        $dry_run = !empty($assoc_args['dry-run']);
        $limit = isset($assoc_args['limit']) ? absint($assoc_args['limit']) : 0;

        $rows = $this->legacy_private_files($limit);
        $total = count($rows);

        WP_CLI::log('Legacy private files still in public uploads: ' . $total);

        if ($dry_run) {
            $this->print_dry_run_summary($rows);
            return;
        }

        if ($total === 0) {
            WP_CLI::success('No files need migration.');
            return;
        }

        WP_CLI::confirm(
            'Move these private files to wp-content/yac-private-uploads and update file_path?',
            $assoc_args
        );

        $report = [
            'moved'          => 0,
            'skipped'        => 0,
            'failed'         => 0,
            'missing_source' => 0,
        ];

        foreach ($rows as $row) {
            $result = $this->migrate_row($row);
            $report[$result]++;
        }

        WP_CLI::log('Moved: ' . $report['moved']);
        WP_CLI::log('Skipped: ' . $report['skipped']);
        WP_CLI::log('Failed: ' . $report['failed']);
        WP_CLI::log('Missing source files: ' . $report['missing_source']);

        $remaining = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM " . YAC_Files_Table::table_name() . "
                 WHERE visibility = %s
                 AND file_path LIKE %s",
                'private',
                '%/wp-content/uploads/%'
            )
        );

        WP_CLI::log('Remaining private rows in public uploads: ' . $remaining);

        if ($report['failed'] > 0 || $report['missing_source'] > 0) {
            WP_CLI::warning('Migration finished with rows requiring manual review.');
            return;
        }

        WP_CLI::success('Private file migration complete.');

    }

    private function legacy_private_files($limit = 0) {

        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT id, file_path, related_type, related_id, file_type
             FROM " . YAC_Files_Table::table_name() . "
             WHERE visibility = %s
             AND file_path LIKE %s
             ORDER BY id ASC",
            'private',
            '%/wp-content/uploads/%'
        );

        if ($limit > 0) {
            $sql .= ' LIMIT ' . absint($limit);
        }

        return $wpdb->get_results($sql, ARRAY_A);

    }

    private function migrate_row(array $row) {

        global $wpdb;

        $source = wp_normalize_path($row['file_path']);

        if (strpos($source, '/wp-content/yac-private-uploads/') !== false) {
            return 'skipped';
        }

        if (strpos($source, '/wp-content/uploads/') === false) {
            return 'skipped';
        }

        if (!file_exists($source) || !is_file($source)) {
            WP_CLI::warning('Missing source for file ID ' . (int) $row['id'] . ': ' . $source);
            return 'missing_source';
        }

        $destination_dir = $this->destination_dir($source);

        if (!$this->ensure_private_dir($destination_dir)) {
            WP_CLI::warning('Unable to create private destination for file ID ' . (int) $row['id']);
            return 'failed';
        }

        $destination = $this->unique_destination(
            $destination_dir,
            basename($source)
        );

        if (!@rename($source, $destination)) {
            WP_CLI::warning('Unable to move file ID ' . (int) $row['id'] . ' to private storage.');
            return 'failed';
        }

        $updated = $wpdb->update(
            YAC_Files_Table::table_name(),
            [
                'file_path' => $destination,
            ],
            [
                'id' => (int) $row['id'],
            ],
            [
                '%s',
            ],
            [
                '%d',
            ]
        );

        if ($updated === false) {
            if (!@rename($destination, $source)) {
                WP_CLI::warning(
                    'Database update failed for file ID ' .
                    (int) $row['id'] .
                    ', and rollback move also failed.'
                );
            } else {
                WP_CLI::warning('Database update failed for file ID ' . (int) $row['id']);
            }

            return 'failed';
        }

        return 'moved';

    }

    private function destination_dir($source) {

        if (preg_match('#/wp-content/uploads/([0-9]{4})/([0-9]{2})/#', $source, $matches)) {
            $year = $matches[1];
            $month = $matches[2];
        } else {
            $timestamp = file_exists($source) ? filemtime($source) : time();
            $year = gmdate('Y', $timestamp);
            $month = gmdate('m', $timestamp);
        }

        return trailingslashit(WP_CONTENT_DIR) .
            'yac-private-uploads/' .
            $year .
            '/' .
            $month;

    }

    private function ensure_private_dir($destination_dir) {

        if (!wp_mkdir_p($destination_dir)) {
            return false;
        }

        $base_dir = trailingslashit(WP_CONTENT_DIR) . 'yac-private-uploads';
        $htaccess_path = trailingslashit($base_dir) . '.htaccess';
        $rules = "Require all denied\nDeny from all\n";

        if (!file_exists($htaccess_path)) {
            return file_put_contents($htaccess_path, $rules, LOCK_EX) !== false;
        }

        return true;

    }

    private function unique_destination($destination_dir, $file_name) {

        $safe_name = sanitize_file_name($file_name);
        $path = trailingslashit($destination_dir) . $safe_name;

        if (!file_exists($path)) {
            return $path;
        }

        $extension = pathinfo($safe_name, PATHINFO_EXTENSION);
        $name = pathinfo($safe_name, PATHINFO_FILENAME);
        $suffix = 1;

        do {
            $candidate = $name . '-' . $suffix;

            if (!empty($extension)) {
                $candidate .= '.' . $extension;
            }

            $path = trailingslashit($destination_dir) . $candidate;
            $suffix++;
        } while (file_exists($path));

        return $path;

    }

    private function print_dry_run_summary(array $rows) {

        $missing = 0;

        foreach ($rows as $row) {
            if (!file_exists($row['file_path']) || !is_file($row['file_path'])) {
                $missing++;
            }
        }

        WP_CLI::log('Existing source files: ' . (count($rows) - $missing));
        WP_CLI::log('Missing source files: ' . $missing);
        WP_CLI::success('Dry run complete. No files were moved.');

    }
}
