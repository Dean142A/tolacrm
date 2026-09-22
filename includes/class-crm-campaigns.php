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

        $recipients = self::get_emails_for_segment($segment);
        if (empty($recipients)) {
            return 0;
        }

        $sent_count = 0;

        foreach ($recipients as $recipient) {
            $email = $recipient['email'];
            $user_id = $recipient['user_id'];

            $coupon_code = '';
            if ($discount_percent > 0) {
                $coupon_code = self::create_coupon('percent', $discount_percent, 7, 'BLAST-');
            }

            // Parse Merge Tags for personalized broadcast
            $context = array(
                'email'           => $email,
                'first_name'      => strstr($email, '@', true),
                'segment'         => $segment,
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
                        'segment'       => $segment,
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
     * Helper to resolve emails for a specific segment.
     */
    private static function get_emails_for_segment($segment) {
        global $wpdb;
        $recipients = array();

        if ($segment === 'all') {
            $users = get_users(array('fields' => array('ID', 'user_email')));
            foreach ($users as $u) {
                $recipients[] = array('user_id' => $u->ID, 'email' => $u->user_email);
            }
        } else {
            $users = get_users(array(
                'meta_key'   => '_crm_segment',
                'meta_value' => $segment,
                'fields'     => array('ID', 'user_email')
            ));

            foreach ($users as $u) {
                $recipients[] = array('user_id' => $u->ID, 'email' => $u->user_email);
            }

            // Also search wc_order_stats for non-user matches if segment matches
            $stats_table = $wpdb->prefix . 'wc_order_stats';
            $guest_emails = $wpdb->get_col("
                SELECT DISTINCT meta_value FROM {$wpdb->postmeta} 
                WHERE meta_key = '_billing_email' AND post_id IN (
                    SELECT order_id FROM {$stats_table} WHERE status IN ('completed', 'processing', 'wc-completed', 'wc-processing')
                )
            ");

            foreach ($guest_emails as $g_email) {
                if (is_email($g_email)) {
                    $exists = false;
                    foreach ($recipients as $r) {
                        if ($r['email'] === $g_email) {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) {
                        $recipients[] = array('user_id' => null, 'email' => $g_email);
                    }
                }
            }
        }

        return $recipients;
    }
}
