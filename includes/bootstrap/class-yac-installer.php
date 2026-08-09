<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Installer {

    /**
     * Run plugin installation tasks.
     */
    public static function install() {

        self::install_database();

        // Future:
        // self::install_roles();
        // self::install_options();
        // self::run_migrations();
    }

    /**
     * Install database schema.
     */
    private static function install_database() {

        require_once YAC_PLUGIN_DIR . 'includes/db/class-yac-database.php';

        YAC_Database::install();
    }
}