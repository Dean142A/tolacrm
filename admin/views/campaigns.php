<?php
/**
 * Campaigns & Dispatch Log Tab View
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$campaigns_table = $wpdb->prefix . 'crm_campaign_log';
$logs = $wpdb->get_results("SELECT * FROM {$campaigns_table} ORDER BY sent_at DESC LIMIT 50");
$merge_tags = Woo_CRM_Merge_Tags::get_available_tags();
?>

<div class="woo-crm-tab-content woo-crm-campaigns">
    <div class="woo-crm-grid-2col">
        <!-- Manual Campaign Blast Dispatch Box -->
        <div class="woo-crm-card">
            <div class="card-header">
                <h2><span class="dashicons dashicons-megaphone"></span> <?php esc_html_e('Dispatch Segment Campaign Blast', 'woo-crm'); ?></h2>
            </div>
            <div class="card-body p-20">
                <form id="woo-crm-manual-campaign-form">
                    <div class="form-group mb-15">
                        <label for="campaign_segment"><strong><?php esc_html_e('Target Customer Segment', 'woo-crm'); ?>:</strong></label>
                        <select name="segment" id="campaign_segment" class="widefat">
                            <option value="all"><?php esc_html_e('All Customers', 'woo-crm'); ?></option>
                            <option value="active"><?php esc_html_e('Active Customers (Purchased last 30 days)', 'woo-crm'); ?></option>
                            <option value="returning"><?php esc_html_e('Returning Customers (1+ prior orders)', 'woo-crm'); ?></option>
                            <option value="new"><?php esc_html_e('New Customers (0 prior orders)', 'woo-crm'); ?></option>
                        </select>
                    </div>

                    <div class="form-group mb-15">
                        <label for="campaign_subject"><strong><?php esc_html_e('Email Subject Line', 'woo-crm'); ?>:</strong></label>
                        <input type="text" name="subject" id="campaign_subject" class="widefat" placeholder="<?php esc_attr_e('e.g. Exclusive VIP Offer for {{contact.first_name}}!', 'woo-crm'); ?>" required>
                    </div>

                    <!-- Merge Tags Insertion Toolbar -->
                    <div class="merge-tags-toolbar mb-10">
                        <span class="toolbar-label"><?php esc_html_e('Insert Merge Tag:', 'woo-crm'); ?></span>
                        <div class="tag-pills-list">
                            <?php foreach ($merge_tags as $t) : ?>
                                <button type="button" class="tag-pill-btn" data-tag="<?php echo esc_attr($t['tag']); ?>" title="<?php echo esc_attr($t['label']); ?>">
                                    <?php echo esc_html($t['tag']); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-group mb-15">
                        <label for="campaign_message"><strong><?php esc_html_e('Email Message Body', 'woo-crm'); ?>:</strong></label>
                        <textarea name="message" id="campaign_message" rows="6" class="widefat" placeholder="<?php esc_attr_e('Hello {{contact.first_name}}, write your campaign broadcast message here...', 'woo-crm'); ?>" required></textarea>
                    </div>

                    <div class="form-group mb-20">
                        <label for="campaign_discount"><strong><?php esc_html_e('Attach Dynamic Discount Coupon (% Off)', 'woo-crm'); ?>:</strong></label>
                        <input type="number" name="discount" id="campaign_discount" min="0" max="100" value="10" class="small-text"> %
                        <p class="description"><?php esc_html_e('Generates single-use WooCommerce coupons via WC_Coupon API.', 'woo-crm'); ?></p>
                    </div>

                    <div class="form-actions-row">
                        <button type="submit" class="button button-primary button-large btn-send-campaign">
                            <span class="dashicons dashicons-send"></span> <?php esc_html_e('Send Campaign Blast Now', 'woo-crm'); ?>
                        </button>

                        <button type="button" class="button button-secondary button-large btn-preview-campaign">
                            <span class="dashicons dashicons-visibility"></span> <?php esc_html_e('Preview Email Live', 'woo-crm'); ?>
                        </button>
                        <span class="spinner" id="campaign-spinner"></span>
                    </div>
                </form>
            </div>
        </div>

        <!-- Campaign Dispatch History Log -->
        <div class="woo-crm-card">
            <div class="card-header">
                <h2><span class="dashicons dashicons-list-view"></span> <?php esc_html_e('Campaign Dispatch History', 'woo-crm'); ?></h2>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($logs)) : ?>
                    <table class="woo-crm-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Recipient Email', 'woo-crm'); ?></th>
                                <th><?php esc_html_e('Segment', 'woo-crm'); ?></th>
                                <th><?php esc_html_e('Campaign Type', 'woo-crm'); ?></th>
                                <th><?php esc_html_e('Coupon', 'woo-crm'); ?></th>
                                <th><?php esc_html_e('Trigger', 'woo-crm'); ?></th>
                                <th><?php esc_html_e('Sent At', 'woo-crm'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log) : ?>
                                <tr>
                                    <td><strong><?php echo esc_html($log->email); ?></strong></td>
                                    <td><span class="woo-crm-badge segment-badge-<?php echo esc_attr($log->segment); ?>"><?php echo esc_html(ucfirst($log->segment)); ?></span></td>
                                    <td><?php echo esc_html($log->campaign_type); ?></td>
                                    <td>
                                        <?php if ($log->coupon_code) : ?>
                                            <code><?php echo esc_html($log->coupon_code); ?></code>
                                        <?php else : ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="woo-crm-badge badge-trigger-<?php echo esc_attr($log->trigger_type); ?>">
                                            <?php echo esc_html(ucfirst($log->trigger_type)); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html(human_time_diff(strtotime($log->sent_at), current_time('timestamp')) . ' ' . __('ago', 'woo-crm')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p class="p-20 text-muted"><?php esc_html_e('No campaign dispatches logged yet.', 'woo-crm'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
