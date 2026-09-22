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
     * Generate dynamic single-use coupon exclusively via WooCommerce WC_Coupon API.
     */
    public static function create_coupon($discount_type = 'percent', $amount = 10, $expiry_days = 7, $prefix = 'CRM-', $min_spend = 0, $free_shipping = false) {
        if (!class_exists('WC_Coupon')) {
            return '';
        }

        $random_suffix = strtoupper(wp_generate_password(6, false, false));
        $code = strtolower($prefix . $random_suffix);

        $coupon = new WC_Coupon();
        $coupon->set_code($code);
        $coupon->set_discount_type($discount_type);
        $coupon->set_amount($amount);
        $coupon->set_individual_use(true);
        $coupon->set_usage_limit(1);

        if ($min_spend > 0) {
            $coupon->set_minimum_amount($min_spend);
        }

        if ($free_shipping) {
            $coupon->set_free_shipping(true);
        }

        if ($expiry_days > 0) {
            $expiry_date = date('Y-m-d', current_time('timestamp') + ($expiry_days * DAY_IN_SECONDS));
            $coupon->set_date_expires($expiry_date);
        }

        $coupon->save();

        return $code;
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
