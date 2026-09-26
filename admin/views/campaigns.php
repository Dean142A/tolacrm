<?php
/**
 * Campaigns & Dispatch Log Tab View
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$campaigns_table = $wpdb->prefix . 'crm_campaign_log';

$search_campaign = isset($_GET['s_campaign']) ? sanitize_text_field($_GET['s_campaign']) : '';
$where = array();
$params = array();

if (!empty($search_campaign)) {
    $where[] = "(email LIKE %s OR campaign_type LIKE %s OR coupon_code LIKE %s)";
    $params[] = '%' . $wpdb->esc_like($search_campaign) . '%';
    $params[] = '%' . $wpdb->esc_like($search_campaign) . '%';
    $params[] = '%' . $wpdb->esc_like($search_campaign) . '%';
}

$where_sql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
$query = "SELECT * FROM {$campaigns_table} {$where_sql} ORDER BY sent_at DESC LIMIT 50";

if (!empty($params)) {
    $logs = $wpdb->get_results($wpdb->prepare($query, $params));
} else {
    $logs = $wpdb->get_results($query);
}

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
            <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <h2><span class="dashicons dashicons-list-view"></span> <?php esc_html_e('Campaign Dispatch History', 'woo-crm'); ?></h2>
                <div>
                    <input type="search" id="crm-campaign-search-input" placeholder="<?php esc_attr_e('Search email/coupon...', 'woo-crm'); ?>" value="<?php echo esc_attr($search_campaign); ?>" style="height:32px; width:160px; font-size:12px;">
                </div>
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
                                <th><?php esc_html_e('Sent At', 'woo-crm'); ?></th>
                                <th class="text-right"><?php esc_html_e('Actions', 'woo-crm'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log) : ?>
                                <tr id="crm-campaign-log-row-<?php echo esc_attr($log->id); ?>">
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
                                    <td><?php echo esc_html(human_time_diff(strtotime($log->sent_at), current_time('timestamp')) . ' ' . __('ago', 'woo-crm')); ?></td>
                                    <td class="text-right">
                                        <div style="display:flex; justify-content:flex-end; gap:4px;">
                                            <button type="button" class="button button-small btn-copy-campaign-log" data-email="<?php echo esc_attr($log->email); ?>" data-coupon="<?php echo esc_attr($log->coupon_code); ?>" data-type="<?php echo esc_attr($log->campaign_type); ?>" title="<?php esc_attr_e('Copy Log Details', 'woo-crm'); ?>">
                                                <span class="dashicons dashicons-admin-page" style="font-size:14px; width:14px; height:14px; vertical-align:middle;"></span>
                                            </button>
                                            <button type="button" class="button button-small btn-share-campaign-log" data-email="<?php echo esc_attr($log->email); ?>" data-coupon="<?php echo esc_attr($log->coupon_code); ?>" data-type="<?php echo esc_attr($log->campaign_type); ?>" title="<?php esc_attr_e('Share Log Details', 'woo-crm'); ?>">
                                                <span class="dashicons dashicons-share" style="font-size:14px; width:14px; height:14px; vertical-align:middle;"></span>
                                            </button>
                                            <button type="button" class="button button-small button-link-delete btn-delete-campaign-log" data-id="<?php echo esc_attr($log->id); ?>" title="<?php esc_attr_e('Delete Log Entry', 'woo-crm'); ?>">
                                                <span class="dashicons dashicons-trash" style="font-size:14px; width:14px; height:14px; vertical-align:middle; color:#ef4444;"></span>
                                            </button>
                                        </div>
                                    </td>
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
