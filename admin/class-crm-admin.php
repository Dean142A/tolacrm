<?php
/**
 * Woo_CRM_Admin
 *
 * Menu registration, tab router, script/style enqueuer, and admin AJAX handlers.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_CRM_Admin {

    public function register_admin_menu() {
        add_menu_page(
            __('WooCommerce CRM', 'woo-crm'),
            __('CRM', 'woo-crm'),
            'manage_woocommerce',
            'woo-crm',
            array($this, 'render_admin_page'),
            'dashicons-chart-line',
            56
        );
    }

    public function enqueue_styles_and_scripts($hook) {
        if ($hook !== 'toplevel_page_woo-crm') {
            return;
        }

        // Chart.js library
        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
            array(),
            '4.4.1',
            true
        );

        // Admin Styles & Scripts
        wp_enqueue_style(
            'woo-crm-admin-css',
            WOO_CRM_URL . 'assets/admin.css',
            array(),
            WOO_CRM_VERSION
        );

        wp_enqueue_script(
            'woo-crm-admin-js',
            WOO_CRM_URL . 'assets/admin.js',
            array('jquery', 'chart-js'),
            WOO_CRM_VERSION,
            true
        );

        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';

        wp_localize_script('woo-crm-admin-js', 'wooCrmData', array(
            'ajax_url'   => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('woo_crm_admin_nonce'),
            'active_tab' => $current_tab,
            'labels'     => array(
                'confirm_send'  => __('Are you sure you want to send this campaign blast now?', 'woo-crm'),
                'confirm_clear' => __('Are you sure you want to clear this cart?', 'woo-crm'),
                'sending'       => __('Sending...', 'woo-crm'),
                'saved'         => __('Settings saved successfully!', 'woo-crm'),
            )
        ));
    }

    public function render_admin_page() {
        Woo_CRM_Security::check_capability();

        $allowed_tabs = array(
            'overview'  => __('Overview', 'woo-crm'),
            'carts'     => __('Abandoned Carts', 'woo-crm'),
            'customers' => __('Customers', 'woo-crm'),
            'journeys'  => __('Customer Journeys', 'woo-crm'),
            'campaigns' => __('Campaigns', 'woo-crm'),
            'analytics' => __('Analytics', 'woo-crm'),
            'settings'  => __('Settings', 'woo-crm'),
        );

        $active_tab = isset($_GET['tab']) && array_key_exists($_GET['tab'], $allowed_tabs) ? sanitize_text_field($_GET['tab']) : 'overview';

        echo '<div class="wrap woo-crm-admin-wrap">';
        include WOO_CRM_PATH . 'admin/partials/nav.php';

        $view_file = WOO_CRM_PATH . 'admin/views/' . $active_tab . '.php';
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            include WOO_CRM_PATH . 'admin/views/dashboard.php';
        }

        echo '</div>';
    }

    /**
     * AJAX: Trigger manual segment blast.
     */
    public function ajax_send_manual_campaign() {
        Woo_CRM_Security::check_capability();
        Woo_CRM_Security::check_nonce(isset($_POST['nonce']) ? $_POST['nonce'] : '');

        $segment = isset($_POST['segment']) ? sanitize_text_field($_POST['segment']) : 'all';
        $subject = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        $discount = isset($_POST['discount']) ? floatval($_POST['discount']) : 0;

        if (empty($subject) || empty($message)) {
            wp_send_json_error(array('message' => __('Subject and message content are required.', 'woo-crm')));
        }

        $count = Woo_CRM_Campaigns::trigger_manual_blast($segment, $subject, $message, $discount);

        wp_send_json_success(array(
            'message' => sprintf(__('Campaign sent successfully to %d customer(s).', 'woo-crm'), $count)
        ));
    }

    /**
     * AJAX: Manually trigger cart recovery email send.
     */
    public function ajax_send_now_cart() {
        Woo_CRM_Security::check_capability();
        Woo_CRM_Security::check_nonce(isset($_POST['nonce']) ? $_POST['nonce'] : '');

        $cart_id = isset($_POST['cart_id']) ? intval($_POST['cart_id']) : 0;
        if (!$cart_id) {
            wp_send_json_error(array('message' => __('Invalid cart ID.', 'woo-crm')));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'crm_carts';
        $cart = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $cart_id));

        if (!$cart) {
            wp_send_json_error(array('message' => __('Cart not found.', 'woo-crm')));
        }

        $stage = max(1, intval($cart->notification_stage) + 1);
        $coupon_code = Woo_CRM_Campaigns::create_coupon('percent', 10, 3, 'MANUAL-');

        $sent = Woo_CRM_Notifications::send_cart_recovery($cart->email, $cart, $stage, $coupon_code);

        if ($sent) {
            $wpdb->update(
                $table_name,
                array(
                    'notification_stage' => $stage,
                    'notified_at'        => current_time('mysql')
                ),
                array('id' => $cart_id),
                array('%d', '%s'),
                array('%d')
            );
            wp_send_json_success(array('message' => __('Recovery email sent successfully.', 'woo-crm')));
        } else {
            wp_send_json_error(array('message' => __('Failed to send email. Check mail server logs.', 'woo-crm')));
        }
    }

    /**
     * AJAX: Clear abandoned cart row.
     */
    public function ajax_clear_cart() {
        Woo_CRM_Security::check_capability();
        Woo_CRM_Security::check_nonce(isset($_POST['nonce']) ? $_POST['nonce'] : '');

        $cart_id = isset($_POST['cart_id']) ? intval($_POST['cart_id']) : 0;
        if (!$cart_id) {
            wp_send_json_error(array('message' => __('Invalid cart ID.', 'woo-crm')));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'crm_carts';
        $deleted = $wpdb->delete($table_name, array('id' => $cart_id), array('%d'));

        if ($deleted) {
            wp_send_json_success(array('message' => __('Cart row cleared.', 'woo-crm')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete cart row.', 'woo-crm')));
        }
    }

    /**
     * AJAX: Save settings form.
     */
    public function ajax_save_settings() {
        Woo_CRM_Security::check_capability();
        Woo_CRM_Security::check_nonce(isset($_POST['nonce']) ? $_POST['nonce'] : '');

        $settings = array(
            'stage_1_hours'                => isset($_POST['stage_1_hours']) ? max(1, intval($_POST['stage_1_hours'])) : 1,
            'stage_2_hours'                => isset($_POST['stage_2_hours']) ? max(1, intval($_POST['stage_2_hours'])) : 24,
            'stage_3_hours'                => isset($_POST['stage_3_hours']) ? max(1, intval($_POST['stage_3_hours'])) : 72,
            'stale_stage_enabled'          => !empty($_POST['stale_stage_enabled']) ? 1 : 0,
            'stage_4_days'                 => isset($_POST['stage_4_days']) ? max(1, intval($_POST['stage_4_days'])) : 30,
            'active_segment_days'          => isset($_POST['active_segment_days']) ? max(1, intval($_POST['active_segment_days'])) : 30,
            'weekly_digest_enabled'        => !empty($_POST['weekly_digest_enabled']) ? 1 : 0,
            'weekly_digest_recipients'     => isset($_POST['weekly_digest_recipients']) ? sanitize_text_field($_POST['weekly_digest_recipients']) : get_option('admin_email'),
            'notify_owner_new_order'       => !empty($_POST['notify_owner_new_order']) ? 1 : 0,
            'notify_owner_completed_order' => !empty($_POST['notify_owner_completed_order']) ? 1 : 0,
            'notify_owner_cart_converted'  => !empty($_POST['notify_owner_cart_converted']) ? 1 : 0,
            'notify_owner_segment_active'  => !empty($_POST['notify_owner_segment_active']) ? 1 : 0,
            'auto_campaign_segment_change' => !empty($_POST['auto_campaign_segment_change']) ? 1 : 0,
            'delete_data_on_uninstall'     => !empty($_POST['delete_data_on_uninstall']) ? 1 : 0,
        );

        update_option('woo_crm_settings', $settings);

        wp_send_json_success(array('message' => __('Settings saved successfully.', 'woo-crm')));
    }

    /**
     * AJAX: Get customer profile details JSON.
     */
    public function ajax_get_customer_details() {
        Woo_CRM_Security::check_capability();
        Woo_CRM_Security::check_nonce(isset($_POST['nonce']) ? $_POST['nonce'] : '');

        $identifier = isset($_POST['identifier']) ? sanitize_text_field($_POST['identifier']) : '';
        if (empty($identifier)) {
            wp_send_json_error(array('message' => __('Customer identifier required.', 'woo-crm')));
        }

        $profile = Woo_CRM_Customers::get_customer_profile($identifier);
        wp_send_json_success($profile);
    }
}
