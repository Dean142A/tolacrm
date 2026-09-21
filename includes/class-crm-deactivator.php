<?php
/**
 * Woo_CRM_Deactivator
 *
 * Deactivation class: clears scheduled cron / Action Scheduler jobs without modifying plugin data.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_CRM_Deactivator {

    public static function deactivate() {
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions('woo_crm_hourly_cart_sweep');
            as_unschedule_all_actions('woo_crm_weekly_digest');
        }

        wp_clear_scheduled_hook('woo_crm_hourly_cart_sweep');
        wp_clear_scheduled_hook('woo_crm_weekly_digest');
    }
}
