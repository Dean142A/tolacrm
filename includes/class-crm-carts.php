<?php
/**
 * Woo_CRM_Carts
 *
 * Cart abandonment tracking, frontend email capture, conversion marker, and staged reminder sweeper.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_CRM_Carts {

    /**
     * Enqueue frontend JS on checkout for email capture.
     */
    public function enqueue_frontend_scripts() {
        if (is_checkout() && !is_order_received_page()) {
            wp_register_script('woo-crm-checkout', false);
            wp_enqueue_script('woo-crm-checkout');

            $params = array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('woo_crm_frontend_nonce')
            );

            $inline_js = "
            (function($) {
                $(document).ready(function() {
                    var captureTimeout;
                    $(document).on('change blur keyup', '#billing_email', function() {
                        var email = $(this).val();
                        if (!email || !email.includes('@')) return;
                        clearTimeout(captureTimeout);
                        captureTimeout = setTimeout(function() {
                            $.post('" . esc_url(admin_url('admin-ajax.php')) . "', {
                                action: 'woo_crm_capture_cart_email',
                                email: email,
                                nonce: '" . esc_js(wp_create_nonce('woo_crm_frontend_nonce')) . "'
                            });
                        }, 800);
                    });
                });
            })(jQuery);";

            wp_add_inline_script('woo-crm-checkout', $inline_js);
        }
    }

    /**
     * Hooked on cart updates (add to cart / quantity change).
     */
    public function on_cart_updated() {
        if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
            return;
        }

        $current_user = wp_get_current_user();
        if ($current_user && $current_user->exists() && !empty($current_user->user_email)) {
            $this->save_cart_state($current_user->user_email, $current_user->ID);
        }
    }

    /**
     * AJAX endpoint for capturing checkout email before order completion.
     */
    public function ajax_capture_cart_email() {
        if (!Woo_CRM_Security::check_rate_limit('capture_email', 30, 60)) {
            wp_send_json_error(array('message' => 'Rate limit exceeded'), 429);
        }

        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        if (!wp_verify_nonce($nonce, 'woo_crm_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Invalid nonce'), 403);
        }

        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        if (empty($email) || !is_email($email)) {
            wp_send_json_error(array('message' => 'Invalid email'), 400);
        }

        $user_id = get_current_user_id();
        $saved = $this->save_cart_state($email, $user_id ? $user_id : null);

        if ($saved) {
            wp_send_json_success(array('message' => 'Cart captured successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to capture cart'), 500);
        }
    }

    /**
     * Save/update cart state in wp_crm_carts.
     */
    public function save_cart_state($email, $user_id = null) {
        global $wpdb;

        if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
            return false;
        }

        $table_name = $wpdb->prefix . 'crm_carts';
        $cart = WC()->cart;
        $cart_contents = array();

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $cart_contents[] = array(
                'product_id'   => $cart_item['product_id'],
                'variation_id' => $cart_item['variation_id'],
                'quantity'     => $cart_item['quantity'],
                'name'         => $product ? $product->get_name() : '',
                'price'        => $product ? $product->get_price() : 0,
                'line_total'   => $cart_item['line_total']
            );
        }

        $cart_total = $cart->get_total('edit');
        $cart_hash = $cart->get_cart_hash();
        $now = current_time('mysql');

        // Check if active (unconverted) cart row exists for this email or user_id
        $existing = null;
        if (!empty($user_id)) {
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE (user_id = %d OR email = %s) AND converted_at IS NULL ORDER BY id DESC LIMIT 1",
                $user_id,
                $email
            ));
        } else {
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE email = %s AND converted_at IS NULL ORDER BY id DESC LIMIT 1",
                $email
            ));
        }

        if ($existing) {
            $wpdb->update(
                $table_name,
                array(
                    'user_id'       => $user_id ? $user_id : $existing->user_id,
                    'email'         => $email,
                    'cart_hash'     => $cart_hash,
                    'cart_contents' => wp_json_encode($cart_contents),
                    'cart_total'    => $cart_total,
                    'updated_at'    => $now
                ),
                array('id' => $existing->id),
                array('%d', '%s', '%s', '%s', '%f', '%s'),
                array('%d')
            );
        } else {
            $wpdb->insert(
                $table_name,
                array(
                    'user_id'            => $user_id,
                    'email'              => $email,
                    'cart_hash'          => $cart_hash,
                    'cart_contents'      => wp_json_encode($cart_contents),
                    'cart_total'         => $cart_total,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                    'notification_stage' => 0
                ),
                array('%d', '%s', '%s', '%s', '%f', '%s', '%s', '%d')
            );
        }

        return true;
    }

    /**
     * Mark cart converted on order completion (woocommerce_thankyou).
     */
    public function on_order_completed($order_id) {
        if (!$order_id) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'crm_carts';

        $email = $order->get_billing_email();
        $user_id = $order->get_user_id();
        $now = current_time('mysql');

        $query = "SELECT * FROM {$table_name} WHERE converted_at IS NULL AND (email = %s";
        $params = array($email);

        if ($user_id) {
            $query .= " OR user_id = %d";
            $params[] = $user_id;
        }
        $query .= ") ORDER BY id DESC LIMIT 1";

        $cart_row = $wpdb->get_row($wpdb->prepare($query, $params));

        if ($cart_row) {
            $wpdb->update(
                $table_name,
                array('converted_at' => $now),
                array('id' => $cart_row->id),
                array('%s'),
                array('%d')
            );

            // Notify store owner if enabled
            $settings = get_option('woo_crm_settings', array());
            if (!empty($settings['notify_owner_cart_converted'])) {
                Woo_CRM_Notifications::send_owner_alert('cart_converted', array(
                    'email'      => $email,
                    'order_id'   => $order_id,
                    'cart_total' => $cart_row->cart_total
                ));
            }
        }
    }

    /**
     * Hourly Action Scheduler cart sweep procedure.
     */
    public function process_abandoned_carts_sweep() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'crm_carts';
        $settings = get_option('woo_crm_settings', array());

        $stage_1_hours = isset($settings['stage_1_hours']) ? intval($settings['stage_1_hours']) : 1;
        $stage_2_hours = isset($settings['stage_2_hours']) ? intval($settings['stage_2_hours']) : 24;
        $stage_3_hours = isset($settings['stage_3_hours']) ? intval($settings['stage_3_hours']) : 72;
        $stale_enabled = !empty($settings['stale_stage_enabled']);
        $stage_4_days  = isset($settings['stage_4_days']) ? intval($settings['stage_4_days']) : 30;

        $unconverted_carts = $wpdb->get_results(
            "SELECT * FROM {$table_name} WHERE converted_at IS NULL AND notification_stage < 4 ORDER BY id ASC LIMIT 50"
        );

        if (empty($unconverted_carts)) {
            return;
        }

        $now_ts = current_time('timestamp');

        foreach ($unconverted_carts as $cart) {
            $updated_ts = strtotime($cart->updated_at);
            $elapsed_hours = ($now_ts - $updated_ts) / 3600;
            $stage = intval($cart->notification_stage);

            $new_stage = null;
            $coupon_code = null;

            if ($stage === 0 && $elapsed_hours >= $stage_1_hours) {
                $new_stage = 1;
            } elseif ($stage === 1 && $elapsed_hours >= $stage_2_hours) {
                $new_stage = 2;
                // Generate 5% recovery discount coupon
                $coupon_code = Woo_CRM_Campaigns::create_coupon('percent', 5, 7, 'RECOVERY5-');
            } elseif ($stage === 2 && $elapsed_hours >= $stage_3_hours) {
                $new_stage = 3;
                $coupon_code = Woo_CRM_Campaigns::create_coupon('percent', 10, 3, 'FINAL10-');
            } elseif ($stage === 3 && $stale_enabled && $elapsed_hours >= ($stage_4_days * 24)) {
                // Stock check before sending Stage 4
                if ($this->has_in_stock_items($cart->cart_contents)) {
                    $new_stage = 4;
                }
            }

            if (null !== $new_stage) {
                $sent = Woo_CRM_Notifications::send_cart_recovery($cart->email, $cart, $new_stage, $coupon_code);

                if ($sent) {
                    $wpdb->update(
                        $table_name,
                        array(
                            'notification_stage' => $new_stage,
                            'notified_at'        => current_time('mysql')
                        ),
                        array('id' => $cart->id),
                        array('%d', '%s'),
                        array('%d')
                    );
                }
            }
        }
    }

    /**
     * Check if any item in cart is currently in stock.
     */
    private function has_in_stock_items($json_contents) {
        $contents = json_decode($json_contents, true);
        if (empty($contents) || !is_array($contents)) {
            return true;
        }

        foreach ($contents as $item) {
            $product_id = !empty($item['variation_id']) ? $item['variation_id'] : $item['product_id'];
            $product = wc_get_product($product_id);
            if ($product && $product->is_in_stock()) {
                return true;
            }
        }

        return false;
    }
}
