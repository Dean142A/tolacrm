<?php
/**
 * Woo_CRM_Digest
 *
 * Weekly top-product purchase summary email generator via Action Scheduler.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_CRM_Digest {

    /**
     * Action Scheduler callback for weekly digest dispatch.
     */
    public function process_weekly_digest() {
        $settings = get_option('woo_crm_settings', array());
        if (empty($settings['weekly_digest_enabled'])) {
            return;
        }

        global $wpdb;
        $lookup_table = $wpdb->prefix . 'wc_order_product_lookup';

        $seven_days_ago = date('Y-m-d H:i:s', current_time('timestamp') - (7 * DAY_IN_SECONDS));

        // Aggregate past 7 days product sales
        $sql = $wpdb->prepare(
            "SELECT product_id, SUM(product_qty) as total_qty, SUM(product_net_revenue) as total_net 
             FROM {$lookup_table} 
             WHERE date_created >= %s 
             GROUP BY product_id 
             ORDER BY total_qty DESC 
             LIMIT 15",
            $seven_days_ago
        );

        $results = $wpdb->get_results($sql);

        $recipients = !empty($settings['weekly_digest_recipients']) ? $settings['weekly_digest_recipients'] : get_option('admin_email');

        Woo_CRM_Notifications::send_weekly_digest($recipients, $results);
    }
}
