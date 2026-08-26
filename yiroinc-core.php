<?php
/**
 * Plugin Name: Yiroinc Core
 * Plugin URI: https://yiroincacademia.com
 * Description: Core plugin powering the Yiroinc Academia platform.
 * Version: 1.0.0
 * Author: Secern Digital
 * Author URI: https://secerndigital.com
 * License: GPL v2 or later
 * Text Domain: yiroinc-core
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin Constants
 */
define('YAC_VERSION', '1.0.0');
define('YAC_PLUGIN_FILE', __FILE__);
define('YAC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('YAC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('YAC_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Composer dependencies.
 */
$yac_autoload = YAC_PLUGIN_DIR . 'vendor/autoload.php';

if (file_exists($yac_autoload)) {
    require_once $yac_autoload;
}

/**
 * Bootstrap Classes
 */
require_once YAC_PLUGIN_DIR . 'includes/bootstrap/class-yac-loader.php';
require_once YAC_PLUGIN_DIR . 'includes/bootstrap/class-yac-installer.php';
require_once YAC_PLUGIN_DIR . 'includes/bootstrap/class-yac-activator.php';
require_once YAC_PLUGIN_DIR . 'includes/bootstrap/class-yac-deactivator.php';
require_once YAC_PLUGIN_DIR . 'includes/bootstrap/class-yac-core.php';

/**
 * Activation / Deactivation Hooks
 */
register_activation_hook(YAC_PLUGIN_FILE, ['YAC_Activator', 'activate']);
register_deactivation_hook(YAC_PLUGIN_FILE, ['YAC_Deactivator', 'deactivate']);

/**
 * Start Plugin
 */
function yac_run_plugin() {
    $plugin = new YAC_Core();
    $plugin->run();
}

yac_run_plugin();
