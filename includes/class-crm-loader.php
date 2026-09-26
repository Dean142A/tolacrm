<?php
/**
 * Woo_CRM_Loader
 *
 * Central Hook Registrar and Core Orchestrator for WooCommerce CRM.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_CRM_Loader {

    protected $actions;
    protected $filters;

    public function __construct() {
        $this->actions = array();
        $this->filters = array();

        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    private function load_dependencies() {
        // Core functional classes are autoloaded by spl_autoload_register in woo-crm.php
    }

    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }

    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }

    private function add($hooks, $hook, $component, $callback, $priority, $accepted_args) {
        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args
        );

        return $hooks;
    }

    private function define_admin_hooks() {
        $plugin_admin = new Woo_CRM_Admin();

        $this->add_action('admin_menu', $plugin_admin, 'register_admin_menu');
        $this->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles_and_scripts');

        // Admin AJAX Actions
        $this->add_action('wp_ajax_woo_crm_send_manual_campaign', $plugin_admin, 'ajax_send_manual_campaign');
        $this->add_action('wp_ajax_woo_crm_preview_email', $plugin_admin, 'ajax_preview_email');
        $this->add_action('wp_ajax_woo_crm_send_now_cart', $plugin_admin, 'ajax_send_now_cart');
        $this->add_action('wp_ajax_woo_crm_clear_cart', $plugin_admin, 'ajax_clear_cart');
        $this->add_action('wp_ajax_woo_crm_save_settings', $plugin_admin, 'ajax_save_settings');
        $this->add_action('wp_ajax_woo_crm_get_customer_details', $plugin_admin, 'ajax_get_customer_details');
        $this->add_action('wp_ajax_woo_crm_toggle_contact_tag', $plugin_admin, 'ajax_toggle_contact_tag');

        // Coupon Management Admin AJAX
        $this->add_action('wp_ajax_woo_crm_create_coupon', $plugin_admin, 'ajax_create_coupon');
        $this->add_action('wp_ajax_woo_crm_delete_coupon', $plugin_admin, 'ajax_delete_coupon');

        // CSV Export Admin Actions
        $this->add_action('admin_action_woo_crm_export_carts', 'Woo_CRM_Exporter', 'export_carts_csv');
        $this->add_action('admin_action_woo_crm_export_customers', 'Woo_CRM_Exporter', 'export_customers_csv');
    }

    private function define_public_hooks() {
        $carts = new Woo_CRM_Carts();
        $orders = new Woo_CRM_Orders();
        $privacy = new Woo_CRM_Privacy();

        // Coupon URL Auto-Apply Trigger
        $this->add_action('wp', 'Woo_CRM_Campaigns', 'handle_auto_apply_coupon_url');

        // Cart tracking hooks
        $this->add_action('woocommerce_add_to_cart', $carts, 'on_cart_updated', 10, 0);
        $this->add_action('woocommerce_cart_updated', $carts, 'on_cart_updated', 10, 0);
        $this->add_action('woocommerce_thankyou', $carts, 'on_order_completed', 10, 1);

        // Checkout Email Capture Endpoint (AJAX)
        $this->add_action('wp_ajax_woo_crm_capture_cart_email', $carts, 'ajax_capture_cart_email');
        $this->add_action('wp_ajax_nopriv_woo_crm_capture_cart_email', $carts, 'ajax_capture_cart_email');

        // Order Journey & Status hooks
        $this->add_action('woocommerce_order_status_changed', $orders, 'on_order_status_changed', 10, 4);

        // Scheduled Sweeps & Actions
        $this->add_action('woo_crm_hourly_cart_sweep', $carts, 'process_abandoned_carts_sweep');
        
        $digest = new Woo_CRM_Digest();
        $this->add_action('woo_crm_weekly_digest', $digest, 'process_weekly_digest');

        // GDPR Hooks
        $this->add_filter('wp_privacy_personal_data_exporters', $privacy, 'register_exporters');
        $this->add_filter('wp_privacy_personal_data_erasers', $privacy, 'register_erasers');

        // Front-end JS enqueue for checkout email capture
        $this->add_action('wp_enqueue_scripts', $carts, 'enqueue_frontend_scripts');

        // REST API Routes
        $rest_controller = new Woo_CRM_REST_Controller();
        $this->add_action('rest_api_init', $rest_controller, 'register_routes');
    }

    public function run() {
        foreach ($this->filters as $hook) {
            add_filter($hook['hook'], array($hook['component'], $hook['callback']), $hook['priority'], $hook['accepted_args']);
        }

        foreach ($this->actions as $hook) {
            add_action($hook['hook'], array($hook['component'], $hook['callback']), $hook['priority'], $hook['accepted_args']);
        }
    }
}
