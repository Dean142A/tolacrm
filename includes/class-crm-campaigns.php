<?php
/**
 * Woo_CRM_Campaigns
 *
 * Automated & manual segment campaign triggers, deduplication, dynamic merge tags, and WC_Coupon API generation.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_CRM_Campaigns {

    /**
     * Generate dynamic single-use or multi-use coupon exclusively via WooCommerce WC_Coupon API.
     */
    public static function create_coupon($discount_type = 'percent', $amount = 10, $expiry_days = 7, $prefix = 'CRM-', $min_spend = 0, $free_shipping = false, $usage_limit = 1, $custom_code = '') {
        if (!class_exists('WC_Coupon')) {
            return '';
        }

        // Validate discount type
        $allowed_types = array('percent', 'fixed_cart', 'fixed_product');
        if (!in_array($discount_type, $allowed_types, true)) {
            $discount_type = 'percent';
        }

        if (!empty($custom_code)) {
            $code = wc_format_coupon_code(trim($custom_code));
        } else {
            $random_suffix = strtoupper(wp_generate_password(6, false, false));
            $code = wc_format_coupon_code($prefix . $random_suffix);
        }

        // If coupon code already exists, delete or fallback
        $existing_id = wc_get_coupon_id_by_code($code);
        if ($existing_id) {
            if (empty($custom_code)) {
                $code = wc_format_coupon_code($prefix . strtoupper(wp_generate_password(8, false, false)));
            } else {
                // Update existing coupon
                $coupon = new WC_Coupon($existing_id);
            }
        }

        if (!isset($coupon)) {
            $coupon = new WC_Coupon();
        }

        $coupon->set_code($code);
        $coupon->set_status('publish'); // Ensure post is published and valid for WooCommerce checkout
        $coupon->set_discount_type($discount_type);
        $coupon->set_amount(floatval($amount));
        $coupon->set_individual_use(true);

        if ($usage_limit > 0) {
            $coupon->set_usage_limit(intval($usage_limit));
        } else {
            $coupon->set_usage_limit(null);
        }

        if ($min_spend > 0) {
            $coupon->set_minimum_amount(floatval($min_spend));
        } else {
            $coupon->set_minimum_amount(0);
        }

        $coupon->set_free_shipping((bool) $free_shipping);

        if ($expiry_days > 0) {
            $expiry_date = date('Y-m-d', current_time('timestamp') + ($expiry_days * DAY_IN_SECONDS));
            $coupon->set_date_expires($expiry_date);
        } else {
            $coupon->set_date_expires(null);
        }

        // Ensure coupon applies to all products by default
        $coupon->set_product_ids(array());
        $coupon->set_excluded_product_ids(array());

        $coupon_id = $coupon->save();

        if ($coupon_id) {
            update_post_meta($coupon_id, '_woo_crm_generated', '1');
            update_post_meta($coupon_id, '_woo_crm_source', sanitize_text_field($prefix));
        }

        return $code;
    }

    /**
     * Auto-apply coupon to WooCommerce cart when URL parameter ?apply_coupon=CODE or ?coupon=CODE is present.
     */
    /**
     * Auto-apply coupon to WooCommerce cart and optionally restore cart items when URL parameter ?apply_coupon=CODE or ?restore_cart=ID is present.
     */
    public static function handle_auto_apply_coupon_url() {
        if (is_admin()) {
            return;
        }

        if (!function_exists('WC') || !WC()->cart) {
            return;
        }

        // 1. Check for abandoned cart restoration request
        $restore_cart_id = !empty($_GET['restore_cart']) ? intval($_GET['restore_cart']) : (!empty($_GET['crm_cart_id']) ? intval($_GET['crm_cart_id']) : 0);
        if ($restore_cart_id > 0 && WC()->cart->is_empty()) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'crm_carts';
            $cart_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $restore_cart_id));
            if ($cart_row && !empty($cart_row->cart_contents)) {
                $items = json_decode($cart_row->cart_contents, true);
                if (is_array($items)) {
                    foreach ($items as $item) {
                        $p_id = !empty($item['product_id']) ? intval($item['product_id']) : 0;
                        $v_id = !empty($item['variation_id']) ? intval($item['variation_id']) : 0;
                        $qty  = !empty($item['quantity']) ? intval($item['quantity']) : 1;
                        if ($p_id > 0) {
                            WC()->cart->add_to_cart($p_id, $qty, $v_id);
                        }
                    }
                    if (!wc_has_notice(__('Your abandoned cart items have been restored!', 'woo-crm'), 'success')) {
                        wc_add_notice(__('Your abandoned cart items have been restored to your bag!', 'woo-crm'), 'success');
                    }
                }
            }
        }

        // 2. Check for coupon apply request
        $coupon_code = '';
        if (!empty($_GET['apply_coupon'])) {
            $coupon_code = sanitize_text_field($_GET['apply_coupon']);
        } elseif (!empty($_GET['coupon'])) {
            $coupon_code = sanitize_text_field($_GET['coupon']);
        } elseif (!empty($_GET['discount'])) {
            $coupon_code = sanitize_text_field($_GET['discount']);
        }

        if (empty($coupon_code)) {
            return;
        }

        $coupon_code = wc_format_coupon_code($coupon_code);

        if (WC()->cart->has_discount($coupon_code)) {
            return;
        }

        $coupon = new WC_Coupon($coupon_code);
        if ($coupon->get_id()) {
            // Ensure coupon is published and valid for products
            if ($coupon->get_status() !== 'publish') {
                $coupon->set_status('publish');
                $coupon->save();
            }

            if ($coupon->is_valid()) {
                WC()->cart->apply_coupon($coupon_code);
                if (!wc_has_notice(sprintf(__('Coupon "%s" applied successfully!', 'woo-crm'), strtoupper($coupon_code)), 'success')) {
                    wc_add_notice(sprintf(__('Promo code "%s" applied successfully!', 'woo-crm'), strtoupper($coupon_code)), 'success');
                }
            }
        }
    }

    /**
     * Get list of all coupons with CRM meta details and campaign usage trace.
     */
    public static function get_all_coupons($filter_source = 'all', $filter_status = 'all', $search = '') {
        if (!class_exists('WC_Coupon')) {
            return array();
        }

        $args = array(
            'posts_per_page' => 300,
            'post_type'      => 'shop_coupon',
            'post_status'    => array('publish', 'draft', 'private', 'pending'),
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        $posts = get_posts($args);
        $coupons = array();

        global $wpdb;
        $campaign_log_table = $wpdb->prefix . 'crm_campaign_log';
        $log_exists = ($wpdb->get_var("SHOW TABLES LIKE '{$campaign_log_table}'") === $campaign_log_table);

        $search_lower = strtolower(trim($search));

        foreach ($posts as $post) {
            $coupon = new WC_Coupon($post->ID);
            $code   = $coupon->get_code();
            $is_crm = (get_post_meta($post->ID, '_woo_crm_generated', true) === '1');
            $crm_source = get_post_meta($post->ID, '_woo_crm_source', true);

            if ($filter_source === 'crm' && !$is_crm) {
                continue;
            }
            if ($filter_source === 'wc' && $is_crm) {
                continue;
            }

            $date_expires = $coupon->get_date_expires();
            $expiry_formatted = $date_expires ? $date_expires->date('Y-m-d') : '';

            $usage_limit = $coupon->get_usage_limit();
            $usage_count = $coupon->get_usage_count();

            $is_expired = false;
            if ($date_expires && $date_expires->getTimestamp() < current_time('timestamp')) {
                $is_expired = true;
            }

            $is_exhausted = false;
            if ($usage_limit > 0 && $usage_count >= $usage_limit) {
                $is_exhausted = true;
            }

            $status_label = 'Active';
            if ($post->post_status !== 'publish') {
                $status_label = ucfirst($post->post_status);
            } elseif ($is_expired) {
                $status_label = 'Expired';
            } elseif ($is_exhausted) {
                $status_label = 'Exhausted';
            }

            if ($filter_status === 'active' && ($status_label !== 'Active')) {
                continue;
            }
            if ($filter_status === 'expired' && !$is_expired) {
                continue;
            }

            // Find matching campaign log entry if generated for email
            $recipient_email = '';
            $campaign_type   = '';
            if ($log_exists && !empty($code)) {
                $log_row = $wpdb->get_row($wpdb->prepare(
                    "SELECT email, campaign_type FROM {$campaign_log_table} WHERE coupon_code = %s ORDER BY id DESC LIMIT 1",
                    $code
                ));
                if ($log_row) {
                    $recipient_email = $log_row->email;
                    $campaign_type   = $log_row->campaign_type;
                }
            }

            // Substring search matching
            if (!empty($search_lower)) {
                $match_code  = strpos(strtolower($code), $search_lower) !== false;
                $match_email = strpos(strtolower($recipient_email), $search_lower) !== false;
                $match_src   = strpos(strtolower($crm_source), $search_lower) !== false;
                $match_type  = strpos(strtolower($coupon->get_discount_type()), $search_lower) !== false;
                if (!$match_code && !$match_email && !$match_src && !$match_type) {
                    continue;
                }
            }

            $coupons[] = array(
                'id'              => $post->ID,
                'code'            => strtoupper($code),
                'raw_code'        => $code,
                'discount_type'   => $coupon->get_discount_type(),
                'amount'          => floatval($coupon->get_amount()),
                'usage_count'     => intval($usage_count),
                'usage_limit'     => $usage_limit ? intval($usage_limit) : 0,
                'min_spend'       => floatval($coupon->get_minimum_amount()),
                'free_shipping'   => $coupon->get_free_shipping(),
                'expiry_date'     => $expiry_formatted,
                'is_crm'          => $is_crm,
                'crm_source'      => $crm_source ? $crm_source : ($is_crm ? 'CRM' : 'Manual'),
                'status'          => $status_label,
                'is_expired'      => $is_expired,
                'is_exhausted'    => $is_exhausted,
                'recipient_email' => $recipient_email,
                'campaign_type'   => $campaign_type,
                'created_at'      => get_the_date('Y-m-d H:i', $post->ID),
            );
        }

        return $coupons;
    }

    /**
     * Automatic segment-triggered campaign handler.
     */
    public static function on_segment_change($email_or_user_id, $new_segment) {
        $settings = get_option('woo_crm_settings', array());
        if (empty($settings['auto_campaign_segment_change'])) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'crm_campaign_log';

        $email = '';
        $user_id = null;

        if (is_numeric($email_or_user_id) && $email_or_user_id > 0) {
            $user_id = intval($email_or_user_id);
            $user = get_userdata($user_id);
            if ($user) {
                $email = $user->user_email;
            }
        } elseif (is_email($email_or_user_id)) {
            $email = sanitize_email($email_or_user_id);
            $user = get_user_by('email', $email);
            if ($user) {
                $user_id = $user->ID;
            }
        }

        if (empty($email)) {
            return;
        }

        $campaign_type = 'segment_welcome_' . $new_segment;

        // Deduplicate check
        $already_sent = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE email = %s AND campaign_type = %s",
            $email,
            $campaign_type
        ));

        if ($already_sent > 0) {
            return;
        }

        // Generate dynamic coupon
        $coupon_code = self::create_coupon('percent', 10, 7, 'WELCOME10-');

        // Dispatch notification
        $sent = Woo_CRM_Notifications::send_segment_welcome($email, $new_segment, $coupon_code);

        if ($sent) {
            $wpdb->insert(
                $table_name,
                array(
                    'user_id'       => $user_id,
                    'email'         => $email,
                    'segment'       => $new_segment,
                    'campaign_type' => $campaign_type,
                    'coupon_code'   => $coupon_code,
                    'trigger_type'  => 'auto',
                    'sent_at'       => current_time('mysql'),
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
            );
        }
    }

    /**
     * Dispatch manual campaign blast to segment.
     */
    public static function trigger_manual_blast($segment, $message_subject, $message_body, $discount_percent = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'crm_campaign_log';

        // Ensure campaign log table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE {$table_name} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NULL,
                email VARCHAR(255) NOT NULL,
                segment VARCHAR(20) NOT NULL,
                campaign_type VARCHAR(50) NOT NULL,
                coupon_code VARCHAR(50) NULL,
                trigger_type ENUM('auto','manual') NOT NULL DEFAULT 'auto',
                sent_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY email (email),
                KEY segment (segment),
                KEY sent_at (sent_at)
            ) {$charset_collate};";
            dbDelta($sql);
        }

        $recipients = self::get_emails_for_segment($segment);
        if (empty($recipients)) {
            return 0;
        }

        $sent_count = 0;

        foreach ($recipients as $recipient) {
            $email   = $recipient['email'];
            $user_id = $recipient['user_id'];
            $name    = !empty($recipient['name']) ? $recipient['name'] : strstr($email, '@', true);

            // Extract first name intelligently
            $first_name = $name;
            if ($user_id > 0) {
                $u = get_userdata($user_id);
                if ($u && !empty($u->first_name)) {
                    $first_name = $u->first_name;
                } elseif ($u && !empty($u->display_name)) {
                    $first_name = $u->display_name;
                }
            }
            if (empty($first_name) || $first_name === 'Guest Customer') {
                $first_name = strstr($email, '@', true);
            }

            $coupon_code = '';
            if ($discount_percent > 0) {
                $coupon_code = self::create_coupon('percent', $discount_percent, 7, 'BLAST-');
            }

            // Parse Merge Tags for personalized broadcast
            $context = array(
                'email'           => $email,
                'first_name'      => ucfirst($first_name),
                'segment'         => $recipient['segment'],
                'coupon_code'     => $coupon_code,
                'discount_amount' => $discount_percent ? $discount_percent . '%' : '',
            );

            $parsed_subject = Woo_CRM_Merge_Tags::process($message_subject, $context);
            $parsed_body    = Woo_CRM_Merge_Tags::process($message_body, $context);

            $sent = Woo_CRM_Notifications::send_manual_blast($email, $parsed_subject, $parsed_body, $coupon_code);

            if ($sent) {
                $wpdb->insert(
                    $table_name,
                    array(
                        'user_id'       => $user_id,
                        'email'         => $email,
                        'segment'       => $recipient['segment'],
                        'campaign_type' => 'manual_blast',
                        'coupon_code'   => $coupon_code,
                        'trigger_type'  => 'manual',
                        'sent_at'       => current_time('mysql'),
                    ),
                    array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
                );
                $sent_count++;
            }
        }

        return $sent_count;
    }

    /**
     * Helper to resolve recipients (registered users + guests) for a specific segment.
     */
    public static function get_emails_for_segment($segment) {
        $customers = Woo_CRM_Customers::get_all_customers($segment, 5000);
        $recipients = array();

        foreach ($customers as $c) {
            if (!empty($c['email']) && is_email($c['email'])) {
                $recipients[] = array(
                    'user_id' => $c['customer_id'] > 0 ? $c['customer_id'] : null,
                    'email'   => strtolower(trim($c['email'])),
                    'name'    => $c['name'],
                    'segment' => $c['segment'],
                );
            }
        }

        return $recipients;
    }
}
