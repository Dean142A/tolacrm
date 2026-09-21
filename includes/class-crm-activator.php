<?php
/**
 * Woo_CRM_Activator
 *
 * Activation class: creates custom database tables, sets default options, and schedules Action Scheduler jobs.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_CRM_Activator {

    public static function activate() {
        self::create_tables();
        self::init_options();
        self::schedule_jobs();
    }

    /**
     * Create custom tables using dbDelta().
     */
    private static function create_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        // 1. wp_crm_carts table
        $carts_table = $wpdb->prefix . 'crm_carts';
        $sql_carts = "CREATE TABLE {$carts_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NULL,
            email VARCHAR(255) NOT NULL,
            cart_hash VARCHAR(32) DEFAULT '',
            cart_contents LONGTEXT DEFAULT NULL,
            cart_total DECIMAL(10,2) NOT NULL DEFAULT '0.00',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            notification_stage TINYINT NOT NULL DEFAULT 0,
            notified_at DATETIME NULL,
            converted_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY email (email),
            KEY user_id (user_id),
            KEY stage_updated (notification_stage, updated_at)
        ) {$charset_collate};";

        dbDelta($sql_carts);

        // 2. wp_crm_campaign_log table
        $campaigns_table = $wpdb->prefix . 'crm_campaign_log';
        $sql_campaigns = "CREATE TABLE {$campaigns_table} (
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

        dbDelta($sql_campaigns);
    }

    /**
     * Set default options array.
     */
    private static function init_options() {
        $default_settings = array(
            'stage_1_hours'                 => 1,
            'stage_2_hours'                 => 24,
            'stage_3_hours'                 => 72,
            'stale_stage_enabled'           => 0,
            'stage_4_days'                  => 30,
            'active_segment_days'           => 30,
            'weekly_digest_enabled'         => 1,
            'weekly_digest_recipients'      => get_option('admin_email'),
            'notify_owner_new_order'        => 1,
            'notify_owner_completed_order'  => 1,
            'notify_owner_cart_converted'   => 1,
            'notify_owner_segment_active'   => 1,
            'auto_campaign_segment_change'  => 1,
            'delete_data_on_uninstall'      => 0,
        );

        $existing = get_option('woo_crm_settings');
        if (false === $existing) {
            update_option('woo_crm_settings', $default_settings);
        } else {
            // Merge defaults for any missing keys
            $merged = wp_parse_args($existing, $default_settings);
            update_option('woo_crm_settings', $merged);
        }
    }

    /**
     * Schedule Action Scheduler jobs.
     */
    private static function schedule_jobs() {
        // 1. Hourly Cart Sweep
        if (function_exists('as_has_scheduled_action') && !as_has_scheduled_action('woo_crm_hourly_cart_sweep')) {
            as_schedule_recurring_action(time() + 300, HOUR_IN_SECONDS, 'woo_crm_hourly_cart_sweep');
        } elseif (!wp_next_scheduled('woo_crm_hourly_cart_sweep')) {
            wp_schedule_event(time() + 300, 'hourly', 'woo_crm_hourly_cart_sweep');
        }

        // 2. Weekly Digest
        if (function_exists('as_has_scheduled_action') && !as_has_scheduled_action('woo_crm_weekly_digest')) {
            as_schedule_recurring_action(time() + 3600, WEEK_IN_SECONDS, 'woo_crm_weekly_digest');
        } elseif (!wp_next_scheduled('woo_crm_weekly_digest')) {
            wp_schedule_event(time() + 3600, 'weekly', 'woo_crm_weekly_digest');
        }
    }
}
