<?php
/**
 * WooCommerce CRM Uninstall Script
 *
 * Runs ONLY when the plugin is deleted from the WordPress admin plugins screen.
 * Destructive cleanup occurs strictly if 'delete_data_on_uninstall' setting is enabled.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$settings = get_option('woo_crm_settings', array());
$delete_data = isset($settings['delete_data_on_uninstall']) && !empty($settings['delete_data_on_uninstall']);

if (!$delete_data) {
    return;
}

global $wpdb;

/**
 * Clean data for a single site context.
 */
function woo_crm_cleanup_site_data() {
    global $wpdb;

    // 1. Drop custom tables
    $carts_table = $wpdb->prefix . 'crm_carts';
    $campaigns_table = $wpdb->prefix . 'crm_campaign_log';

    $wpdb->query("DROP TABLE IF EXISTS {$carts_table}");
    $wpdb->query("DROP TABLE IF EXISTS {$campaigns_table}");

    // 2. Delete options
    delete_option('woo_crm_settings');

    // 3. Delete user meta across all users
    delete_metadata('user', 0, '_crm_segment', '', true);

    // 4. Unschedules Action Scheduler jobs if Action Scheduler exists
    if (function_exists('as_unschedule_all_actions')) {
        as_unschedule_all_actions('woo_crm_hourly_cart_sweep');
        as_unschedule_all_actions('woo_crm_weekly_digest');
    }
    wp_clear_scheduled_hook('woo_crm_hourly_cart_sweep');
    wp_clear_scheduled_hook('woo_crm_weekly_digest');

    // 5. Cleanup uploads directory if created
    $upload_dir = wp_upload_dir();
    $crm_upload_path = trailingslashit($upload_dir['basedir']) . 'woo-crm/';
    if (is_dir($crm_upload_path)) {
        $files = glob($crm_upload_path . '*', GLOB_MARK);
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($crm_upload_path);
    }
}

// Multisite vs Single Site check
if (is_multisite()) {
    $sites = get_sites();
    foreach ($sites as $site) {
        switch_to_blog($site->blog_id);
        woo_crm_cleanup_site_data();
        restore_current_blog();
    }
} else {
    woo_crm_cleanup_site_data();
}
