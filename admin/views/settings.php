<?php
/**
 * Settings Tab View
 */

if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('woo_crm_settings', array());
?>

<div class="woo-crm-tab-content woo-crm-settings">
    <div class="woo-crm-card">
        <div class="card-header">
            <h2><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e('WooCommerce CRM Settings', 'woo-crm'); ?></h2>
        </div>
        <div class="card-body p-20">
            <form id="woo-crm-settings-form">
                <!-- Section 1: Cart Abandonment Intervals -->
                <h3 class="settings-section-title"><span class="dashicons dashicons-cart"></span> <?php esc_html_e('Cart Abandonment Recovery Intervals', 'woo-crm'); ?></h3>
                <div class="form-grid mb-20">
                    <div class="form-group">
                        <label for="stage_1_hours"><strong><?php esc_html_e('Stage 1 Reminder Interval (Hours)', 'woo-crm'); ?>:</strong></label>
                        <input type="number" min="1" max="168" name="stage_1_hours" id="stage_1_hours" value="<?php echo esc_attr(isset($settings['stage_1_hours']) ? $settings['stage_1_hours'] : 1); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e('Hours after cart update to send initial gentle reminder.', 'woo-crm'); ?></p>
                    </div>

                    <div class="form-group">
                        <label for="stage_2_hours"><strong><?php esc_html_e('Stage 2 Coupon Offer Interval (Hours)', 'woo-crm'); ?>:</strong></label>
                        <input type="number" min="1" max="168" name="stage_2_hours" id="stage_2_hours" value="<?php echo esc_attr(isset($settings['stage_2_hours']) ? $settings['stage_2_hours'] : 24); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e('Hours after Stage 1 to send discount coupon offer.', 'woo-crm'); ?></p>
                    </div>

                    <div class="form-group">
                        <label for="stage_3_hours"><strong><?php esc_html_e('Stage 3 Final Warning Interval (Hours)', 'woo-crm'); ?>:</strong></label>
                        <input type="number" min="1" max="168" name="stage_3_hours" id="stage_3_hours" value="<?php echo esc_attr(isset($settings['stage_3_hours']) ? $settings['stage_3_hours'] : 72); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e('Hours after Stage 2 to send final cart expiration notice.', 'woo-crm'); ?></p>
                    </div>
                </div>

                <!-- Section 2: Stale Cart Re-engagement -->
                <div class="form-group mb-20">
                    <label>
                        <input type="checkbox" name="stale_stage_enabled" value="1" <?php checked(!empty($settings['stale_stage_enabled'])); ?>>
                        <strong><?php esc_html_e('Enable Stage 4 Stale Cart Re-engagement', 'woo-crm'); ?></strong>
                    </label>
                    <p class="description"><?php esc_html_e('Pulls live stock levels at send time via wc_get_product() for "before stock runs out" messaging.', 'woo-crm'); ?></p>
                    <div style="margin-top:10px;">
                        <label for="stage_4_days"><?php esc_html_e('Stale Interval (Days):', 'woo-crm'); ?></label>
                        <input type="number" min="1" max="365" name="stage_4_days" id="stage_4_days" value="<?php echo esc_attr(isset($settings['stage_4_days']) ? $settings['stage_4_days'] : 30); ?>" class="small-text"> <?php esc_html_e('days', 'woo-crm'); ?>
                    </div>
                </div>

                <hr class="settings-divider">

                <!-- Section 3: Customer Segmentation -->
                <h3 class="settings-section-title"><span class="dashicons dashicons-groups"></span> <?php esc_html_e('Customer Segmentation Rules', 'woo-crm'); ?></h3>
                <div class="form-group mb-20">
                    <label for="active_segment_days"><strong><?php esc_html_e('"Active" Segment Window (Days)', 'woo-crm'); ?>:</strong></label>
                    <input type="number" min="1" max="365" name="active_segment_days" id="active_segment_days" value="<?php echo esc_attr(isset($settings['active_segment_days']) ? $settings['active_segment_days'] : 30); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e('Customers with orders in the past N days are categorized as "Active".', 'woo-crm'); ?></p>
                </div>

                <div class="form-group mb-20">
                    <label>
                        <input type="checkbox" name="auto_campaign_segment_change" value="1" <?php checked(!empty($settings['auto_campaign_segment_change'])); ?>>
                        <strong><?php esc_html_e('Auto-trigger campaign welcome email when customer enters new segment', 'woo-crm'); ?></strong>
                    </label>
                </div>

                <hr class="settings-divider">

                <!-- Section 4: Owner Alerts & Weekly Digest -->
                <h3 class="settings-section-title"><span class="dashicons dashicons-email-alt"></span> <?php esc_html_e('Store Owner Notifications & Digests', 'woo-crm'); ?></h3>
                <div class="form-group mb-15">
                    <label>
                        <input type="checkbox" name="weekly_digest_enabled" value="1" <?php checked(!empty($settings['weekly_digest_enabled'])); ?>>
                        <strong><?php esc_html_e('Enable Weekly Purchase Summary Digest Email', 'woo-crm'); ?></strong>
                    </label>
                    <div style="margin-top:8px;">
                        <label for="weekly_digest_recipients"><?php esc_html_e('Recipient Email(s):', 'woo-crm'); ?></label>
                        <input type="text" name="weekly_digest_recipients" id="weekly_digest_recipients" value="<?php echo esc_attr(isset($settings['weekly_digest_recipients']) ? $settings['weekly_digest_recipients'] : get_option('admin_email')); ?>" class="regular-text">
                    </div>
                </div>

                <div class="form-group mb-20">
                    <p><strong><?php esc_html_e('Owner Notification Events:', 'woo-crm'); ?></strong></p>
                    <label style="display:block; margin-bottom:5px;">
                        <input type="checkbox" name="notify_owner_new_order" value="1" <?php checked(!empty($settings['notify_owner_new_order'])); ?>> <?php esc_html_e('New Order Placed', 'woo-crm'); ?>
                    </label>
                    <label style="display:block; margin-bottom:5px;">
                        <input type="checkbox" name="notify_owner_completed_order" value="1" <?php checked(!empty($settings['notify_owner_completed_order'])); ?>> <?php esc_html_e('Order Completed', 'woo-crm'); ?>
                    </label>
                    <label style="display:block; margin-bottom:5px;">
                        <input type="checkbox" name="notify_owner_cart_converted" value="1" <?php checked(!empty($settings['notify_owner_cart_converted'])); ?>> <?php esc_html_e('Abandoned Cart Converted after recovery email', 'woo-crm'); ?>
                    </label>
                    <label style="display:block; margin-bottom:5px;">
                        <input type="checkbox" name="notify_owner_segment_active" value="1" <?php checked(!empty($settings['notify_owner_segment_active'])); ?>> <?php esc_html_e('Customer entered "Active" VIP segment', 'woo-crm'); ?>
                    </label>
                </div>

                <hr class="settings-divider">

                <!-- Section 5: Data & Uninstall Cleanup -->
                <h3 class="settings-section-title text-danger"><span class="dashicons dashicons-trash"></span> <?php esc_html_e('Data & Uninstall Cleanup', 'woo-crm'); ?></h3>
                <div class="form-group mb-20">
                    <label class="text-danger">
                        <input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked(!empty($settings['delete_data_on_uninstall'])); ?>>
                        <strong><?php esc_html_e('Delete all CRM data on uninstall', 'woo-crm'); ?></strong>
                    </label>
                    <p class="description text-danger"><?php esc_html_e('WARNING: When enabled, deleting this plugin from WordPress admin will drop wp_crm_carts and wp_crm_campaign_log tables, clear settings, and erase user metadata.', 'woo-crm'); ?></p>
                </div>

                <button type="submit" class="button button-primary button-large btn-save-settings">
                    <span class="dashicons dashicons-saved"></span> <?php esc_html_e('Save CRM Settings', 'woo-crm'); ?>
                </button>
                <span class="spinner" id="settings-spinner"></span>
            </form>
        </div>
    </div>
</div>
