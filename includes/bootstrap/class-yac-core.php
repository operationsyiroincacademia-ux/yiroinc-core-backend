<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_Core {

    private $loader;

    public function __construct() {

        $this->load_dependencies();

        if (class_exists('YAC_Database')) {
            YAC_Database::maybe_upgrade();
        }

        $this->loader = new YAC_Loader();

        add_action('rest_api_init', [$this, 'register_rest_routes']);

    }

    private function load_dependencies() {

        /*
         * Bootstrap
         */
        require_once YAC_PLUGIN_DIR . 'includes/bootstrap/class-yac-loader.php';

        /*
         * Database table classes
         */
        require_once YAC_PLUGIN_DIR . 'includes/db/class-yac-database.php';
        require_once YAC_PLUGIN_DIR . 'includes/db/class-yac-table-registry.php';

        /*
         * Base Services
         */
        require_once YAC_PLUGIN_DIR . 'includes/services/class-yac-base-service.php';

        /*
         * Services
         */
        require_once YAC_PLUGIN_DIR . 'includes/services/class-yac-status-service.php';
        require_once YAC_PLUGIN_DIR . 'includes/services/class-yac-file-service.php';
        require_once YAC_PLUGIN_DIR . 'includes/services/class-yac-notification-service.php';
        require_once YAC_PLUGIN_DIR . 'includes/services/class-yac-timeline-service.php';
        require_once YAC_PLUGIN_DIR . 'includes/services/class-yac-audit-service.php';
        require_once YAC_PLUGIN_DIR . 'includes/services/class-yac-order-service.php';
        require_once YAC_PLUGIN_DIR . 'includes/services/class-yac-payment-service.php';
        require_once YAC_PLUGIN_DIR . 'includes/services/class-yac-event-service.php';
        require_once YAC_PLUGIN_DIR . 'includes/auth/class-yac-jwt-service.php';
        require_once YAC_PLUGIN_DIR . 'includes/auth/class-yac-auth-service.php';
        require_once YAC_PLUGIN_DIR . 'includes/auth/class-yac-auth-helper.php';
        require_once YAC_PLUGIN_DIR . 'includes/services/class-yac-validation-service.php';
        require_once YAC_PLUGIN_DIR . 'includes/services/class-yac-profile-service.php';
        require_once YAC_PLUGIN_DIR . 'includes/services/class-yac-resource-service.php';
        require_once YAC_PLUGIN_DIR . 'includes/services/class-yac-admin-service.php';
        require_once YAC_PLUGIN_DIR . 'includes/services/class-yac-procurement-service.php';
        require_once YAC_PLUGIN_DIR . 'includes/services/class-yac-consulting-service.php';
        require_once YAC_PLUGIN_DIR . 'includes/services/class-yac-crm-service.php';
        

        /*
         * REST
         */
        require_once YAC_PLUGIN_DIR . 'includes/rest/class-yac-rest-controller.php';
        require_once YAC_PLUGIN_DIR . 'includes/rest/class-yac-orders-controller.php';
        require_once YAC_PLUGIN_DIR . 'includes/rest/class-yac-payments-controller.php';
        require_once YAC_PLUGIN_DIR . 'includes/rest/class-yac-profiles-controller.php';
        require_once YAC_PLUGIN_DIR . 'includes/rest/class-yac-files-controller.php';
        require_once YAC_PLUGIN_DIR . 'includes/rest/class-yac-tutor-requests-controller.php';
        require_once YAC_PLUGIN_DIR . 'includes/rest/class-yac-consulting-requests-controller.php';
        require_once YAC_PLUGIN_DIR . 'includes/rest/class-yac-procurements-controller.php';
        require_once YAC_PLUGIN_DIR . 'includes/rest/class-yac-admin-invitations-controller.php';
        require_once YAC_PLUGIN_DIR . 'includes/rest/class-yac-notifications-controller.php';
        require_once YAC_PLUGIN_DIR . 'includes/rest/class-yac-auth-controller.php';
        require_once YAC_PLUGIN_DIR . 'includes/rest/class-yac-resources-controller.php';
        require_once YAC_PLUGIN_DIR . 'includes/rest/class-yac-timeline-controller.php';
        require_once YAC_PLUGIN_DIR . 'includes/rest/class-yac-admin-controller.php';
        require_once YAC_PLUGIN_DIR . 'includes/rest/class-yac-audit-controller.php';
        require_once YAC_PLUGIN_DIR . 'includes/rest/class-yac-search-controller.php';
        require_once YAC_PLUGIN_DIR . 'includes/rest/class-yac-dashboard-controller.php';
        require_once YAC_PLUGIN_DIR . 'includes/rest/class-yac-products-controller.php';
        require_once YAC_PLUGIN_DIR . 'includes/rest/class-yac-settings-controller.php';
        
        

    }

    public function register_rest_routes() {

        (new YAC_Orders_Controller())->register_routes();
        (new YAC_Payments_Controller())->register_routes();
        (new YAC_Profiles_Controller())->register_routes();
        (new YAC_Files_Controller())->register_routes();
        (new YAC_Tutor_Requests_Controller())->register_routes();
        (new YAC_Consulting_Requests_Controller())->register_routes();
        (new YAC_Procurements_Controller())->register_routes();
        (new YAC_Admin_Invitations_Controller())->register_routes();
        (new YAC_Notifications_Controller())->register_routes();
        (new YAC_Auth_Controller())->register_routes();
        (new YAC_Resources_Controller())->register_routes();
        (new YAC_Timeline_Controller())->register_routes();
        (new YAC_Admin_Controller())->register_routes();
        (new YAC_Audit_Controller())->register_routes();
        (new YAC_Search_Controller())->register_routes();
        (new YAC_Dashboard_Controller())->register_routes();
        (new YAC_Products_Controller())->register_routes();
        (new YAC_Settings_Controller())->register_routes();

    }

    public function run() {

        $this->loader->run();

    }

}
