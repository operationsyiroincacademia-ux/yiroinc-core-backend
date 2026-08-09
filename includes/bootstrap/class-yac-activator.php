<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Activator {

    /**
     * Runs when the plugin is activated.
     */
    public static function activate() {

        if (class_exists('YAC_Installer')) {
            YAC_Installer::install();
        }
    }
}