<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Resource_Audiences_Table extends YAC_Table {

    const TABLE_NAME = 'yiroinc_resource_audiences';

    public static function create() {

        $table_name = self::table_name();
        $charset_collate = self::charset_collate();

        $sql = "CREATE TABLE {$table_name} (

            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,

            resource_id BIGINT(20) UNSIGNED NOT NULL,

            audience VARCHAR(50) NOT NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY (id),

            UNIQUE KEY resource_audience (resource_id, audience),

            KEY resource_id (resource_id),

            KEY audience (audience)

        ) {$charset_collate};";

        self::run_dbdelta($sql);

        self::migrate_legacy_profile_types();

    }

    private static function migrate_legacy_profile_types() {

        global $wpdb;

        $audiences_table = self::table_name();
        $resources_table = YAC_Resources_Table::table_name();

        $mappings = [
            'academic_user'    => 'academic',
            'exam_candidate'   => 'exam_candidate',
            'cfa_candidate'    => 'exam_candidate',
            'frm_candidate'    => 'exam_candidate',
            'corporate_client' => 'corporate',
        ];

        foreach ($mappings as $profile_type => $audience) {
            $wpdb->query(
                $wpdb->prepare(
                    "INSERT IGNORE INTO {$audiences_table} (resource_id, audience)
                     SELECT id, %s
                     FROM {$resources_table}
                     WHERE profile_type = %s",
                    $audience,
                    $profile_type
                )
            );
        }

        foreach (['academic', 'exam_candidate', 'corporate'] as $audience) {
            $wpdb->query(
                $wpdb->prepare(
                    "INSERT IGNORE INTO {$audiences_table} (resource_id, audience)
                     SELECT id, %s
                     FROM {$resources_table}
                     WHERE is_public = 1
                     AND (
                        profile_type IS NULL
                        OR profile_type = ''
                     )",
                    $audience
                )
            );
        }

    }

}
