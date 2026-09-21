<?php
/**
 * Abandoned Carts Monitor View
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$carts_table = $wpdb->prefix . 'crm_carts';

$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'active';
$stage_filter  = isset($_GET['stage']) ? sanitize_text_field($_GET['stage']) : 'all';

$where = array();
$params = array();

if ($status_filter === 'active') {
    $where[] = "converted_at IS NULL";
} elseif ($status_filter === 'converted') {
    $where[] = "converted_at IS NOT NULL";
}

if ($stage_filter !== 'all') {
    $where[] = "notification_stage = %d";
    $params[] = intval($stage_filter);
}

$where_sql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

$query = "SELECT * FROM {$carts_table} {$where_sql} ORDER BY updated_at DESC LIMIT 100";
if (!empty($params)) {
    $carts = $wpdb->get_results($wpdb->prepare($query, $params));
} else {
    $carts = $wpdb->get_results($query);
}

$stage_names = array(
    0 => __('0 - Tracked', 'woo-crm'),
    1 => __('1 - 1hr Reminder Sent', 'woo-crm'),
    2 => __('2 - 24hr Coupon Sent', 'woo-crm'),
    3 => __('3 - 3-Day Final Sent', 'woo-crm'),
    4 => __('4 - Stale Re-engagement', 'woo-crm'),
);

$export_url = admin_url('admin.php?action=woo_crm_export_carts');
?>

<div class="woo-crm-tab-content woo-crm-carts">
    <div class="woo-crm-card">
        <div class="card-header">
            <h2><span class="dashicons dashicons-cart"></span> <?php esc_html_e('Abandoned Cart Recovery Monitor', 'woo-crm'); ?></h2>
            <div class="card-actions">
                <a href="<?php echo esc_url($export_url); ?>" class="button button-secondary"><span class="dashicons dashicons-download"></span> <?php esc_html_e('Export CSV', 'woo-crm'); ?></a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=woo-crm&tab=carts&status=active')); ?>" class="button <?php echo $status_filter === 'active' ? 'button-primary' : 'button-secondary'; ?>"><?php esc_html_e('Active Unconverted', 'woo-crm'); ?></a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=woo-crm&tab=carts&status=converted')); ?>" class="button <?php echo $status_filter === 'converted' ? 'button-primary' : 'button-secondary'; ?>"><?php esc_html_e('Converted Carts', 'woo-crm'); ?></a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=woo-crm&tab=carts&status=all')); ?>" class="button <?php echo $status_filter === 'all' ? 'button-primary' : 'button-secondary'; ?>"><?php esc_html_e('All Carts', 'woo-crm'); ?></a>
            </div>
        </div>

        <div class="card-body p-0">
            <?php if (!empty($carts)) : ?>
                <table class="woo-crm-table woo-crm-cart-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Email / Customer', 'woo-crm'); ?></th>
                            <th><?php esc_html_e('Cart Contents Preview', 'woo-crm'); ?></th>
                            <th><?php esc_html_e('Cart Value', 'woo-crm'); ?></th>
                            <th><?php esc_html_e('Stage', 'woo-crm'); ?></th>
                            <th><?php esc_html_e('Last Notified', 'woo-crm'); ?></th>
                            <th><?php esc_html_e('Last Updated', 'woo-crm'); ?></th>
                            <th class="text-right"><?php esc_html_e('Actions', 'woo-crm'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($carts as $c) : ?>
                            <?php
                            $items = json_decode($c->cart_contents, true);
                            $item_names = array();
                            if (is_array($items)) {
                                foreach ($items as $item) {
                                    $item_names[] = esc_html($item['name']) . ' (x' . intval($item['quantity']) . ')';
                                }
                            }
                            $contents_str = implode(', ', $item_names);
                            ?>
                            <tr id="crm-cart-row-<?php echo esc_attr($c->id); ?>">
                                <td>
                                    <strong><?php echo esc_html($c->email); ?></strong>
                                    <?php if ($c->user_id) : ?>
                                        <br><span class="text-muted font-small"><?php echo esc_html(sprintf(__('User #%d', 'woo-crm'), $c->user_id)); ?></span>
                                    <?php else : ?>
                                        <br><span class="text-muted font-small"><?php esc_html_e('Guest', 'woo-crm'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="cart-items-preview" title="<?php echo esc_attr($contents_str); ?>">
                                        <?php echo esc_html(mb_strimwidth($contents_str, 0, 45, '...')); ?>
                                    </span>
                                </td>
                                <td><strong><?php echo wc_price($c->cart_total); ?></strong></td>
                                <td>
                                    <?php if ($c->converted_at) : ?>
                                        <span class="woo-crm-badge badge-converted"><?php esc_html_e('Converted', 'woo-crm'); ?></span>
                                    <?php else : ?>
                                        <span class="woo-crm-badge stage-badge-<?php echo intval($c->notification_stage); ?>">
                                            <?php echo esc_html(isset($stage_names[$c->notification_stage]) ? $stage_names[$c->notification_stage] : 'Stage ' . $c->notification_stage); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    echo $c->notified_at ? esc_html(human_time_diff(strtotime($c->notified_at), current_time('timestamp')) . ' ' . __('ago', 'woo-crm')) : '<em>' . esc_html__('Never', 'woo-crm') . '</em>';
                                    ?>
                                </td>
                                <td><?php echo esc_html(human_time_diff(strtotime($c->updated_at), current_time('timestamp')) . ' ' . __('ago', 'woo-crm')); ?></td>
                                <td class="text-right action-buttons">
                                    <?php if (!$c->converted_at) : ?>
                                        <button class="button button-small button-primary btn-send-now-cart" data-cart-id="<?php echo esc_attr($c->id); ?>">
                                            <span class="dashicons dashicons-email-alt"></span> <?php esc_html_e('Send Now', 'woo-crm'); ?>
                                        </button>
                                    <?php endif; ?>
                                    <button class="button button-small button-link-delete btn-clear-cart" data-cart-id="<?php echo esc_attr($c->id); ?>">
                                        <?php esc_html_e('Clear', 'woo-crm'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p class="p-20 text-muted"><?php esc_html_e('No carts match the selected filter criteria.', 'woo-crm'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>
