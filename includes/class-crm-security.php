<?php
/**
 * Woo_CRM_Security
 *
 * Shared capability checks, nonce validation, rate limiting, and sanitization helpers.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_CRM_Security {

    /**
     * Enforce WooCommerce manager capability.
     */
    public static function check_capability() {
        if (!current_user_can('manage_woocommerce')) {
            if (wp_doing_ajax()) {
                wp_send_json_error(array('message' => __('Permission denied.', 'woo-crm')), 403);
            } else {
                wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'woo-crm'), 403);
            }
        }
    }

    /**
     * Verify AJAX Nonce.
     */
    public static function check_nonce($nonce_value, $action = 'woo_crm_admin_nonce') {
        if (!wp_verify_nonce($nonce_value, $action)) {
            if (wp_doing_ajax()) {
                wp_send_json_error(array('message' => __('Invalid security token. Please reload the page.', 'woo-crm')), 403);
            } else {
                wp_die(esc_html__('Invalid security token.', 'woo-crm'), 403);
            }
        }
    }

    /**
     * Rate limiter based on IP address and transient cache.
     * Useful for unauthenticated public AJAX endpoints (e.g. checkout cart email capture).
     *
     * @param string $action Action key name
     * @param int $limit Maximum allowed hits per window
     * @param int $seconds Window duration in seconds
     * @return bool True if allowed, false if limit exceeded.
     */
    public static function check_rate_limit($action, $limit = 10, $seconds = 60) {
        $ip = self::get_client_ip();
        $transient_key = 'woo_crm_rl_' . md5($action . '_' . $ip);

        $hits = get_transient($transient_key);

        if ($hits === false) {
            set_transient($transient_key, 1, $seconds);
            return true;
        }

        if ($hits >= $limit) {
            return false;
        }

        set_transient($transient_key, $hits + 1, $seconds);
        return true;
    }

    /**
     * Safely resolve client IP address.
     */
    public static function get_client_ip() {
        $ip = '';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip_list = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim(sanitize_text_field($ip_list[0]));
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1';
    }
}
