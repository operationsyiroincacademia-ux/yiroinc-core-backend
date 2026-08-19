<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Database {

    const DB_VERSION = '1.0.8';

    public static function load() {

        require_once YAC_PLUGIN_DIR . 'includes/db/class-yac-table-registry.php';

    }

    public static function install() {

        self::load();

        $tables = YAC_Table_Registry::get_tables();

        foreach ($tables as $table) {
            if (class_exists($table)) {
                $table::create();
            }
        }

        update_option('yac_db_version', self::DB_VERSION);

    }

    public static function maybe_upgrade() {

        if (version_compare(self::get_version(), self::DB_VERSION, '<')) {
            self::install();
        }

    }

    public static function get_version() {

        return get_option('yac_db_version', '0.0.0');

    }

}
