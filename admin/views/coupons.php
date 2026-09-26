<?php
/**
 * Coupons Management & Audit View
 */

if (!defined('ABSPATH')) {
    exit;
}

$filter_source = isset($_GET['crm_source']) ? sanitize_text_field($_GET['crm_source']) : 'all';
$filter_status = isset($_GET['crm_status']) ? sanitize_text_field($_GET['crm_status']) : 'all';
$search_query  = isset($_GET['s_coupon']) ? sanitize_text_field($_GET['s_coupon']) : '';

$all_coupons = Woo_CRM_Campaigns::get_all_coupons($filter_source, $filter_status, $search_query);

// Summary metrics calculation
$total_coupons = count($all_coupons);
$crm_coupons_count = 0;
$active_coupons_count = 0;
$total_uses_count = 0;

foreach ($all_coupons as $c) {
    if ($c['is_crm']) {
        $crm_coupons_count++;
    }
    if ($c['status'] === 'Active') {
        $active_coupons_count++;
    }
    $total_uses_count += $c['usage_count'];
}
?>

<div class="woo-crm-tab-content woo-crm-coupons">
    <!-- Coupons Top Summary Metrics Grid -->
    <div class="woo-crm-metrics-grid">
        <div class="woo-crm-card woo-crm-metric-card">
            <div class="metric-icon" style="background:#e0e7ff; color:#4f46e5;">
                <span class="dashicons dashicons-tickets-alt"></span>
            </div>
            <div class="metric-data">
                <h3><?php echo esc_html($total_coupons); ?></h3>
                <p><?php esc_html_e('Total Coupons', 'woo-crm'); ?></p>
            </div>
        </div>

        <div class="woo-crm-card woo-crm-metric-card">
            <div class="metric-icon" style="background:#d1fae5; color:#059669;">
                <span class="dashicons dashicons-cloud"></span>
            </div>
            <div class="metric-data">
                <h3><?php echo esc_html($crm_coupons_count); ?></h3>
                <p><?php esc_html_e('CRM Generated', 'woo-crm'); ?></p>
            </div>
        </div>

        <div class="woo-crm-card woo-crm-metric-card">
            <div class="metric-icon" style="background:#fef3c7; color:#d97706;">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="metric-data">
                <h3><?php echo esc_html($active_coupons_count); ?></h3>
                <p><?php esc_html_e('Active & Valid', 'woo-crm'); ?></p>
            </div>
        </div>

        <div class="woo-crm-card woo-crm-metric-card">
            <div class="metric-icon" style="background:#fae8ff; color:#c026d3;">
                <span class="dashicons dashicons-cart"></span>
            </div>
            <div class="metric-data">
                <h3><?php echo esc_html($total_uses_count); ?></h3>
                <p><?php esc_html_e('Total Coupon Uses', 'woo-crm'); ?></p>
            </div>
        </div>
    </div>

    <div class="woo-crm-grid-2col">
        <!-- Coupon Generator Card -->
        <div class="woo-crm-card">
            <div class="card-header">
                <h2><span class="dashicons dashicons-plus-alt"></span> <?php esc_html_e('Create New Coupon', 'woo-crm'); ?></h2>
            </div>
            <div class="card-body p-20">
                <form id="woo-crm-create-coupon-form">
                    <div class="form-group mb-15">
                        <label for="coupon_custom_code"><strong><?php esc_html_e('Custom Coupon Code (Optional)', 'woo-crm'); ?>:</strong></label>
                        <input type="text" name="custom_code" id="coupon_custom_code" class="widefat" placeholder="<?php esc_attr_e('e.g. SUMMER20 (Leave blank for auto-generated code)', 'woo-crm'); ?>">
                    </div>

                    <div class="form-group mb-15">
                        <label for="coupon_prefix"><strong><?php esc_html_e('Code Prefix (if auto-generated)', 'woo-crm'); ?>:</strong></label>
                        <input type="text" name="prefix" id="coupon_prefix" class="widefat" value="CRM-" placeholder="CRM-">
                    </div>

                    <div class="woo-crm-form-row mb-15" style="display:flex; gap:15px;">
                        <div style="flex:1;">
                            <label for="coupon_discount_type"><strong><?php esc_html_e('Discount Type', 'woo-crm'); ?>:</strong></label>
                            <select name="discount_type" id="coupon_discount_type" class="widefat">
                                <option value="percent"><?php esc_html_e('Percentage Discount (%)', 'woo-crm'); ?></option>
                                <option value="fixed_cart"><?php esc_html_e('Fixed Cart Discount ($)', 'woo-crm'); ?></option>
                                <option value="fixed_product"><?php esc_html_e('Fixed Product Discount ($)', 'woo-crm'); ?></option>
                            </select>
                        </div>

                        <div style="flex:1;">
                            <label for="coupon_amount"><strong><?php esc_html_e('Discount Amount', 'woo-crm'); ?>:</strong></label>
                            <input type="number" name="amount" id="coupon_amount" min="0" step="0.01" value="10" class="widefat" required>
                        </div>
                    </div>

                    <div class="woo-crm-form-row mb-15" style="display:flex; gap:15px;">
                        <div style="flex:1;">
                            <label for="coupon_expiry_days"><strong><?php esc_html_e('Expiry (Days)', 'woo-crm'); ?>:</strong></label>
                            <input type="number" name="expiry_days" id="coupon_expiry_days" min="0" value="7" class="widefat">
                            <p class="description"><?php esc_html_e('0 for no expiration', 'woo-crm'); ?></p>
                        </div>

                        <div style="flex:1;">
                            <label for="coupon_usage_limit"><strong><?php esc_html_e('Usage Limit', 'woo-crm'); ?>:</strong></label>
                            <input type="number" name="usage_limit" id="coupon_usage_limit" min="0" value="1" class="widefat">
                            <p class="description"><?php esc_html_e('0 for unlimited uses', 'woo-crm'); ?></p>
                        </div>
                    </div>

                    <div class="form-group mb-15">
                        <label for="coupon_min_spend"><strong><?php esc_html_e('Minimum Spend ($)', 'woo-crm'); ?>:</strong></label>
                        <input type="number" name="min_spend" id="coupon_min_spend" min="0" step="0.01" value="0" class="widefat">
                    </div>

                    <div class="form-group mb-20">
                        <label>
                            <input type="checkbox" name="free_shipping" value="1">
                            <strong><?php esc_html_e('Grant Free Shipping', 'woo-crm'); ?></strong>
                        </label>
                    </div>

                    <div class="form-actions-row">
                        <button type="submit" class="button button-primary button-large btn-create-coupon">
                            <span class="dashicons dashicons-saved"></span> <?php esc_html_e('Generate & Publish Coupon', 'woo-crm'); ?>
                        </button>
                        <span class="spinner" id="create-coupon-spinner"></span>
                    </div>
                </form>
            </div>
        </div>

        <!-- Coupons Table Card -->
        <div class="woo-crm-card">
            <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <h2><span class="dashicons dashicons-list-view"></span> <?php esc_html_e('Coupons Inventory & Audit', 'woo-crm'); ?></h2>
                <div class="woo-crm-table-filters" style="display:flex; gap:10px; align-items:center;">
                    <select id="crm-coupon-filter-source" class="small-text" style="height:32px;">
                        <option value="all" <?php selected($filter_source, 'all'); ?>><?php esc_html_e('All Sources', 'woo-crm'); ?></option>
                        <option value="crm" <?php selected($filter_source, 'crm'); ?>><?php esc_html_e('CRM Generated', 'woo-crm'); ?></option>
                        <option value="wc" <?php selected($filter_source, 'wc'); ?>><?php esc_html_e('WooCommerce Manual', 'woo-crm'); ?></option>
                    </select>

                    <select id="crm-coupon-filter-status" class="small-text" style="height:32px;">
                        <option value="all" <?php selected($filter_status, 'all'); ?>><?php esc_html_e('All Statuses', 'woo-crm'); ?></option>
                        <option value="active" <?php selected($filter_status, 'active'); ?>><?php esc_html_e('Active Only', 'woo-crm'); ?></option>
                        <option value="expired" <?php selected($filter_status, 'expired'); ?>><?php esc_html_e('Expired Only', 'woo-crm'); ?></option>
                    </select>

                    <input type="search" id="crm-coupon-search-input" placeholder="<?php esc_attr_e('Search code...', 'woo-crm'); ?>" value="<?php echo esc_attr($search_query); ?>" style="height:32px; width:140px;">
                </div>
            </div>

            <div class="card-body p-0">
                <?php if (!empty($all_coupons)) : ?>
                    <table class="woo-crm-table" id="crm-coupons-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Coupon Code', 'woo-crm'); ?></th>
                                <th><?php esc_html_e('Discount', 'woo-crm'); ?></th>
                                <th><?php esc_html_e('Usage', 'woo-crm'); ?></th>
                                <th><?php esc_html_e('Status', 'woo-crm'); ?></th>
                                <th><?php esc_html_e('Expires', 'woo-crm'); ?></th>
                                <th><?php esc_html_e('Actions', 'woo-crm'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_coupons as $coupon) : ?>
                                <tr id="crm-coupon-row-<?php echo esc_attr($coupon['id']); ?>">
                                    <td>
                                        <div style="display:flex; align-items:center; gap:8px;">
                                            <code class="coupon-code-pill" style="font-size:13px; font-weight:700; background:#eef2ff; color:#3730a3; padding:4px 8px; border-radius:4px; border:1px solid #c7d2fe;">
                                                <?php echo esc_html($coupon['code']); ?>
                                            </code>
                                            <?php if ($coupon['is_crm']) : ?>
                                                <span class="woo-crm-badge" style="background:#059669; color:#fff;" title="<?php esc_attr_e('Generated by CRM', 'woo-crm'); ?>"><?php esc_html_e('CRM', 'woo-crm'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($coupon['recipient_email'])) : ?>
                                            <small class="text-muted" style="display:block; margin-top:2px;">
                                                <?php esc_html_e('Sent to:', 'woo-crm'); ?> <?php echo esc_html($coupon['recipient_email']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong>
                                            <?php
                                            if ($coupon['discount_type'] === 'percent') {
                                                echo esc_html($coupon['amount'] . '% OFF');
                                            } else {
                                                echo esc_html('$' . number_format($coupon['amount'], 2) . ' OFF');
                                            }
                                            ?>
                                        </strong>
                                        <?php if ($coupon['free_shipping']) : ?>
                                            <span class="woo-crm-badge" style="background:#3b82f6; color:#fff; font-size:10px; margin-left:4px;"><?php esc_html_e('+ Free Ship', 'woo-crm'); ?></span>
                                        <?php endif; ?>
                                        <?php if ($coupon['min_spend'] > 0) : ?>
                                            <small class="text-muted" style="display:block;"><?php echo sprintf(esc_html__('Min Spend: $%s', 'woo-crm'), number_format($coupon['min_spend'], 2)); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="woo-crm-badge" style="background:#f1f5f9; color:#475569; border:1px solid #cbd5e1;">
                                            <?php
                                            if ($coupon['usage_limit'] > 0) {
                                                echo esc_html($coupon['usage_count'] . ' / ' . $coupon['usage_limit']);
                                            } else {
                                                echo esc_html($coupon['usage_count'] . ' / Unlimited');
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $badge_bg = '#10b981';
                                        if ($coupon['status'] === 'Expired') {
                                            $badge_bg = '#ef4444';
                                        } elseif ($coupon['status'] === 'Exhausted') {
                                            $badge_bg = '#f59e0b';
                                        } elseif ($coupon['status'] !== 'Active') {
                                            $badge_bg = '#64748b';
                                        }
                                        ?>
                                        <span class="woo-crm-badge" style="background:<?php echo esc_attr($badge_bg); ?>; color:#ffffff;">
                                            <?php echo esc_html($coupon['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo !empty($coupon['expiry_date']) ? esc_html($coupon['expiry_date']) : '<span class="text-muted">' . esc_html__('Never', 'woo-crm') . '</span>'; ?>
                                    </td>
                                    <td>
                                        <div style="display:flex; gap:6px;">
                                            <button type="button" class="button button-small btn-copy-code" data-code="<?php echo esc_attr($coupon['code']); ?>" title="<?php esc_attr_e('Copy Code', 'woo-crm'); ?>">
                                                <span class="dashicons dashicons-admin-page" style="font-size:14px; width:14px; height:14px; vertical-align:middle;"></span>
                                            </button>
                                            <button type="button" class="button button-small btn-copy-url" data-url="<?php echo esc_url(site_url('/?apply_coupon=' . $coupon['raw_code'])); ?>" title="<?php esc_attr_e('Copy Auto-Apply Link', 'woo-crm'); ?>">
                                                <span class="dashicons dashicons-admin-links" style="font-size:14px; width:14px; height:14px; vertical-align:middle;"></span>
                                            </button>
                                            <button type="button" class="button button-small button-link-delete btn-delete-coupon" data-id="<?php echo esc_attr($coupon['id']); ?>" title="<?php esc_attr_e('Delete Coupon', 'woo-crm'); ?>">
                                                <span class="dashicons dashicons-trash" style="font-size:14px; width:14px; height:14px; vertical-align:middle; color:#ef4444;"></span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p class="p-20 text-muted"><?php esc_html_e('No coupons found matching criteria.', 'woo-crm'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
