<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Deactivator {

    /**
     * Runs when the plugin is deactivated.
     */
    public static function deactivate() {

        // Future:
        // - Clear scheduled cron jobs
        // - Flush rewrite rules if needed
        // - Perform temporary cleanup

    }
}