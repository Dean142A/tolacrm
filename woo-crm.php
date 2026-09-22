<?php
/**
 * Plugin Name: WooCommerce CRM
 * Plugin URI: https://example.com/woo-crm
 * Description: All-in-one native WooCommerce CRM for cart abandonment recovery, customer segmentation, purchase history, order journey tracking, campaign blasts, and analytics.
 * Version: 1.0.1
 * Author: Tola
 * Author URI: https://example.com
 * Text Domain: woo-crm
 * Domain Path: /languages
 * WC requires at least: 5.0
 * WC tests up to: 8.5
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Plugin Constants
define('WOO_CRM_VERSION', '1.0.1');
define('WOO_CRM_FILE', __FILE__);
define('WOO_CRM_PATH', plugin_dir_path(__FILE__));
define('WOO_CRM_URL', plugin_dir_url(__FILE__));
define('WOO_CRM_BASENAME', plugin_basename(__FILE__));

/**
 * Autoload core class files.
 */
spl_autoload_register(function ($class) {
    $prefix = 'Woo_CRM_';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file_name = 'class-crm-' . strtolower(str_replace('_', '-', $relative_class)) . '.php';

    // 1. Check includes/ directory
    $file_includes = WOO_CRM_PATH . 'includes/' . $file_name;
    if (file_exists($file_includes)) {
        require_once $file_includes;
        return;
    }

    // 2. Check admin/ directory
    $file_admin = WOO_CRM_PATH . 'admin/' . $file_name;
    if (file_exists($file_admin)) {
        require_once $file_admin;
        return;
    }
});

/**
 * WooCommerce activation requirement check.
 */
function woo_crm_check_dependencies() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>' . esc_html__('WooCommerce CRM requires WooCommerce to be installed and active.', 'woo-crm') . '</p></div>';
        });
        return false;
    }
    return true;
}

/**
 * Activation callback.
 */
function activate_woo_crm() {
    require_once WOO_CRM_PATH . 'includes/class-crm-activator.php';
    Woo_CRM_Activator::activate();
}

/**
 * Deactivation callback.
 */
function deactivate_woo_crm() {
    require_once WOO_CRM_PATH . 'includes/class-crm-deactivator.php';
    Woo_CRM_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_woo_crm');
register_deactivation_hook(__FILE__, 'deactivate_woo_crm');

/**
 * Bootstrap loader.
 */
function run_woo_crm() {
    if (!woo_crm_check_dependencies()) {
        return;
    }

    require_once WOO_CRM_PATH . 'includes/class-crm-loader.php';
    $plugin = new Woo_CRM_Loader();
    $plugin->run();
}

// Priority 15 ensures WooCommerce (priority 10) is fully loaded first
add_action('plugins_loaded', 'run_woo_crm', 15);
