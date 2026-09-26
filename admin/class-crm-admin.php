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
            'ajax_url'    => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('woo_crm_admin_nonce'),
            'active_tab'  => $current_tab,
            'merge_tags'  => Woo_CRM_Merge_Tags::get_available_tags(),
            'labels'      => array(
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
            'coupons'   => __('Coupons', 'woo-crm'),
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

        // Email preview modal partial
        include WOO_CRM_PATH . 'admin/partials/modal-email-preview.php';

        echo '</div>';
    }

    /**
     * AJAX: Trigger manual segment blast.
     */
    public function ajax_send_manual_campaign() {
        Woo_CRM_Security::check_capability();
        Woo_CRM_Security::check_nonce(isset($_POST['nonce']) ? $_POST['nonce'] : '');

        $segment  = isset($_POST['segment']) ? sanitize_text_field($_POST['segment']) : 'all';
        $subject  = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '';
        $message  = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        $discount = isset($_POST['discount']) ? floatval($_POST['discount']) : 0;

        if (empty($subject) || empty($message)) {
            wp_send_json_error(array('message' => __('Subject and message content are required.', 'woo-crm')));
        }

        $count = Woo_CRM_Campaigns::trigger_manual_blast($segment, $subject, $message, $discount);

        if ($count > 0) {
            wp_send_json_success(array(
                'message' => sprintf(__('Campaign sent successfully to %d recipient(s).', 'woo-crm'), $count)
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('No recipients found matching the selected segment or mail server delivery failed.', 'woo-crm')
            ));
        }
    }

    /**
     * AJAX: Live Email Preview Renderer.
     */
    public function ajax_preview_email() {
        Woo_CRM_Security::check_capability();
        Woo_CRM_Security::check_nonce(isset($_POST['nonce']) ? $_POST['nonce'] : '');

        $subject  = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '';
        $message  = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        $discount = isset($_POST['discount']) ? floatval($_POST['discount']) : 0;

        $sample_coupon = $discount > 0 ? 'PREVIEW-OFFER10' : '';

        $context = array(
            'email'           => 'alex.smith@example.com',
            'first_name'      => 'Alex',
            'segment'         => 'VIP',
            'coupon_code'     => $sample_coupon,
            'discount_amount' => $discount ? $discount . '%' : '',
        );

        $parsed_subject = Woo_CRM_Merge_Tags::process($subject, $context);
        $parsed_message = Woo_CRM_Merge_Tags::process($message, $context);

        $settings    = get_option('woo_crm_settings', array());
        $brand_color = !empty($settings['brand_color']) ? sanitize_hex_color($settings['brand_color']) : '#4f46e5';
        $logo_url    = !empty($settings['brand_logo_url']) ? esc_url($settings['brand_logo_url']) : '';
        $footer_text = !empty($settings['brand_footer_text']) ? sanitize_text_field($settings['brand_footer_text']) : sprintf('&copy; %s %s. All rights reserved.', date('Y'), get_bloginfo('name'));

        $site_name = get_bloginfo('name');

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0; padding:20px; font-family:Helvetica, Arial, sans-serif; background:#f4f5f7; color:#333333;">';
        $html .= '<div style="max-width:600px; margin:0 auto; background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 4px 6px rgba(0,0,0,0.05);">';
        
        $html .= '<div style="background:' . esc_attr($brand_color) . '; padding:20px; text-align:center; color:#ffffff;">';
        if ($logo_url) {
            $html .= '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($site_name) . '" style="max-height:45px; border:0;">';
        } else {
            $html .= '<h1 style="margin:0; font-size:20px; color:#ffffff;">' . esc_html($site_name) . '</h1>';
        }
        $html .= '</div>';

        $html .= '<div style="padding:30px;"><p>' . wp_kses_post(nl2br($parsed_message)) . '</p>';

        if ($sample_coupon && (strpos($parsed_message, $sample_coupon) === false)) {
            $html .= '<div style="background:#fffbeb; border:2px dashed #f59e0b; border-radius:8px; padding:16px; margin:20px 0; text-align:center;">';
            $html .= '<p style="margin:0; font-size:14px; color:#b45309; font-weight:600;">' . esc_html__('Your Special Promo Code:', 'woo-crm') . '</p>';
            $html .= '<h3 style="margin:8px 0; font-size:24px; color:#78350f; letter-spacing:2px;">' . esc_html($sample_coupon) . '</h3>';
            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '<div style="background:#f9fafb; padding:15px; text-align:center; font-size:12px; color:#9ca3af; border-top:1px solid #f3f4f6;">' . $footer_text . '</div>';
        $html .= '</div></body></html>';

        wp_send_json_success(array(
            'subject' => $parsed_subject,
            'html'    => $html
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
            'brand_color'                  => isset($_POST['brand_color']) ? sanitize_hex_color($_POST['brand_color']) : '#4f46e5',
            'brand_logo_url'               => isset($_POST['brand_logo_url']) ? esc_url_raw($_POST['brand_logo_url']) : '',
            'brand_footer_text'            => isset($_POST['brand_footer_text']) ? sanitize_text_field($_POST['brand_footer_text']) : '',
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
        $profile['tags'] = Woo_CRM_Tags::get_tags($identifier);

        wp_send_json_success($profile);
    }

    /**
     * AJAX: Add/Remove Custom Tag for Customer.
     */
    public function ajax_toggle_contact_tag() {
        Woo_CRM_Security::check_capability();
        Woo_CRM_Security::check_nonce(isset($_POST['nonce']) ? $_POST['nonce'] : '');

        $identifier = isset($_POST['identifier']) ? sanitize_text_field($_POST['identifier']) : '';
        $tag        = isset($_POST['tag']) ? sanitize_text_field($_POST['tag']) : '';
        $op         = isset($_POST['op']) ? sanitize_text_field($_POST['op']) : 'add';

        if (empty($identifier) || empty($tag)) {
            wp_send_json_error(array('message' => __('Identifier and tag required.', 'woo-crm')));
        }

        if ($op === 'add') {
            Woo_CRM_Tags::add_tag($identifier, $tag);
        } else {
            Woo_CRM_Tags::remove_tag($identifier, $tag);
        }

        $current_tags = Woo_CRM_Tags::get_tags($identifier);
        wp_send_json_success(array('tags' => $current_tags));
    }

    /**
     * AJAX: Create coupon via CRM admin tab.
     */
    public function ajax_create_coupon() {
        Woo_CRM_Security::check_capability();
        Woo_CRM_Security::check_nonce(isset($_POST['nonce']) ? $_POST['nonce'] : '');

        $custom_code    = isset($_POST['custom_code']) ? sanitize_text_field($_POST['custom_code']) : '';
        $prefix         = isset($_POST['prefix']) ? sanitize_text_field($_POST['prefix']) : 'CRM-';
        $discount_type  = isset($_POST['discount_type']) ? sanitize_text_field($_POST['discount_type']) : 'percent';
        $amount         = isset($_POST['amount']) ? floatval($_POST['amount']) : 10;
        $expiry_days    = isset($_POST['expiry_days']) ? intval($_POST['expiry_days']) : 7;
        $usage_limit    = isset($_POST['usage_limit']) ? intval($_POST['usage_limit']) : 1;
        $min_spend      = isset($_POST['min_spend']) ? floatval($_POST['min_spend']) : 0;
        $free_shipping  = !empty($_POST['free_shipping']) ? true : false;

        if (empty($prefix)) {
            $prefix = 'CRM-';
        }

        if ($amount <= 0 && !$free_shipping) {
            wp_send_json_error(array('message' => __('Please specify a discount amount greater than 0 or enable free shipping.', 'woo-crm')));
        }

        $code = Woo_CRM_Campaigns::create_coupon(
            $discount_type,
            $amount,
            $expiry_days,
            $prefix,
            $min_spend,
            $free_shipping,
            $usage_limit,
            $custom_code
        );

        if ($code) {
            wp_send_json_success(array(
                'message'     => sprintf(__('Coupon %s created successfully!', 'woo-crm'), strtoupper($code)),
                'coupon_code' => strtoupper($code)
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to create coupon. Ensure WooCommerce is active.', 'woo-crm')));
        }
    }

    /**
     * AJAX: Delete coupon.
     */
    public function ajax_delete_coupon() {
        Woo_CRM_Security::check_capability();
        Woo_CRM_Security::check_nonce(isset($_POST['nonce']) ? $_POST['nonce'] : '');

        $coupon_id = isset($_POST['coupon_id']) ? intval($_POST['coupon_id']) : 0;

        if (!$coupon_id) {
            wp_send_json_error(array('message' => __('Invalid coupon ID.', 'woo-crm')));
        }

        $deleted = wp_delete_post($coupon_id, true);

        if ($deleted) {
            wp_send_json_success(array('message' => __('Coupon deleted successfully.', 'woo-crm')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete coupon.', 'woo-crm')));
        }
    }
}

