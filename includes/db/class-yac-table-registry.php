<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once YAC_PLUGIN_DIR . 'includes/db/class-yac-table.php';
require_once YAC_PLUGIN_DIR . 'includes/db/class-yac-profiles-table.php';
require_once YAC_PLUGIN_DIR . 'includes/db/class-yac-orders-table.php';
require_once YAC_PLUGIN_DIR . 'includes/db/class-yac-payments-table.php';
require_once YAC_PLUGIN_DIR . 'includes/db/class-yac-files-table.php';
require_once YAC_PLUGIN_DIR . 'includes/db/class-yac-notifications-table.php';
require_once YAC_PLUGIN_DIR . 'includes/db/class-yac-timeline-table.php';
require_once YAC_PLUGIN_DIR . 'includes/db/class-yac-audit-logs-table.php';
require_once YAC_PLUGIN_DIR . 'includes/db/class-yac-tutor-requests-table.php';
require_once YAC_PLUGIN_DIR . 'includes/db/class-yac-consulting-requests-table.php';
require_once YAC_PLUGIN_DIR . 'includes/db/class-yac-procurements-table.php';
require_once YAC_PLUGIN_DIR . 'includes/db/class-yac-admin-invitations-table.php';
require_once YAC_PLUGIN_DIR . 'includes/db/class-yac-resources-table.php';



class YAC_Table_Registry {

    /**
     * Registered database tables.
     *
     * @return array
     */
    public static function get_tables() {

        return [

            YAC_Profiles_Table::class,
            YAC_Orders_Table::class,
            YAC_Payments_Table::class,
            YAC_Files_Table::class,
            YAC_Notifications_Table::class,
            YAC_Timeline_Table::class,
            YAC_Audit_Logs_Table::class,
            YAC_Tutor_Requests_Table::class,
            YAC_Consulting_Requests_Table::class,
            YAC_Procurements_Table::class,
            YAC_Admin_Invitations_Table::class,
            YAC_Resources_Table::class,

        ];

    }

}