<?php
/**
 * Overview / Dashboard Tab View
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$stats_table = $wpdb->prefix . 'wc_order_stats';
$carts_table = $wpdb->prefix . 'crm_carts';

$first_day_month = date('Y-m-01 00:00:00');
$valid_statuses = array('completed', 'processing', 'wc-completed', 'wc-processing');

$month_snapshot = null;
if ($wpdb->get_var("SHOW TABLES LIKE '{$stats_table}'") === $stats_table) {
    $month_snapshot = $wpdb->get_row($wpdb->prepare(
        "SELECT SUM(COALESCE(net_total, total_sales, 0)) as total_sales, COUNT(order_id) as total_orders, AVG(COALESCE(net_total, total_sales, 0)) as avg_order_val 
         FROM {$stats_table} 
         WHERE status IN ('" . implode("','", $valid_statuses) . "') AND date_created >= %s",
        $first_day_month
    ));
}

if (!$month_snapshot || (floatval($month_snapshot->total_sales) == 0 && intval($month_snapshot->total_orders) == 0)) {
    $hpos_table = $wpdb->prefix . 'wc_orders';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$hpos_table}'") === $hpos_table) {
        $month_snapshot = $wpdb->get_row($wpdb->prepare(
            "SELECT SUM(total_amount) as total_sales, COUNT(id) as total_orders, AVG(total_amount) as avg_order_val 
             FROM {$hpos_table} 
             WHERE status IN ('" . implode("','", $valid_statuses) . "') AND type = 'shop_order' AND date_created_gmt >= %s",
            $first_day_month
        ));
    } else {
        $month_snapshot = $wpdb->get_row($wpdb->prepare(
            "SELECT SUM(pm.meta_value) as total_sales, COUNT(p.ID) as total_orders, AVG(pm.meta_value) as avg_order_val 
             FROM {$wpdb->posts} p 
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_order_total' 
             WHERE p.post_type = 'shop_order' AND p.post_status IN ('" . implode("','", $valid_statuses) . "') AND p.post_date >= %s",
            $first_day_month
        ));
    }
}

$monthly_sales  = $month_snapshot ? floatval($month_snapshot->total_sales) : 0.00;
$monthly_orders = $month_snapshot ? intval($month_snapshot->total_orders) : 0;
$monthly_aov    = $month_snapshot ? floatval($month_snapshot->avg_order_val) : 0.00;

// Active carts count & total potential value
$active_carts = $wpdb->get_row(
    "SELECT COUNT(*) as cart_count, SUM(cart_total) as potential_value FROM {$carts_table} WHERE converted_at IS NULL"
);
$active_cart_count = $active_carts ? intval($active_carts->cart_count) : 0;
$potential_cart_val = $active_carts ? floatval($active_carts->potential_value) : 0.00;

// Segment counts
$segment_counts = Woo_CRM_Customers::get_segment_counts();

// Recent Journey Activity Feed (latest 10 orders)
$recent_journeys = Woo_CRM_Orders::get_order_journeys(array('limit' => 6));
?>

<div class="woo-crm-tab-content woo-crm-dashboard">
    <!-- Stat Cards Grid -->
    <div class="woo-crm-metrics-grid">
        <div class="woo-crm-card woo-crm-metric-card">
            <div class="metric-icon metric-purple"><span class="dashicons dashicons-money-alt"></span></div>
            <div class="metric-info">
                <span class="metric-title"><?php esc_html_e('Monthly Revenue', 'woo-crm'); ?></span>
                <span class="metric-value"><?php echo wc_price($monthly_sales); ?></span>
                <span class="metric-sub"><?php echo esc_html(sprintf(__('%d orders this month', 'woo-crm'), $monthly_orders)); ?></span>
            </div>
        </div>

        <div class="woo-crm-card woo-crm-metric-card">
            <div class="metric-icon metric-amber"><span class="dashicons dashicons-cart"></span></div>
            <div class="metric-info">
                <span class="metric-title"><?php esc_html_e('Active Recoverable Carts', 'woo-crm'); ?></span>
                <span class="metric-value"><?php echo esc_html($active_cart_count); ?></span>
                <span class="metric-sub"><?php echo esc_html(sprintf(__('Potential Value: %s', 'woo-crm'), wc_price($potential_cart_val))); ?></span>
            </div>
        </div>

        <div class="woo-crm-card woo-crm-metric-card">
            <div class="metric-icon metric-emerald"><span class="dashicons dashicons-star-filled"></span></div>
            <div class="metric-info">
                <span class="metric-title"><?php esc_html_e('Active Customers', 'woo-crm'); ?></span>
                <span class="metric-value"><?php echo esc_html($segment_counts['active']); ?></span>
                <span class="metric-sub"><?php esc_html_e('Purchased in last 30 days', 'woo-crm'); ?></span>
            </div>
        </div>

        <div class="woo-crm-card woo-crm-metric-card">
            <div class="metric-icon metric-blue"><span class="dashicons dashicons-groups"></span></div>
            <div class="metric-info">
                <span class="metric-title"><?php esc_html_e('Returning / New Split', 'woo-crm'); ?></span>
                <span class="metric-value"><?php echo esc_html($segment_counts['returning']); ?> / <?php echo esc_html($segment_counts['new']); ?></span>
                <span class="metric-sub"><?php esc_html_e('Returning vs New customers', 'woo-crm'); ?></span>
            </div>
        </div>
    </div>

    <!-- Main Overview Row -->
    <div class="woo-crm-grid-2col">
        <!-- Quick Cart Recovery Snapshot -->
        <div class="woo-crm-card">
            <div class="card-header">
                <h2><span class="dashicons dashicons-cart"></span> <?php esc_html_e('Recent Abandoned Carts', 'woo-crm'); ?></h2>
                <a href="<?php echo esc_url(admin_url('admin.php?page=woo-crm&tab=carts')); ?>" class="button button-secondary"><?php esc_html_e('View All Carts', 'woo-crm'); ?> &rarr;</a>
            </div>
            <div class="card-body p-0">
                <?php
                $latest_carts = $wpdb->get_results("SELECT * FROM {$carts_table} WHERE converted_at IS NULL ORDER BY updated_at DESC LIMIT 5");
                if (!empty($latest_carts)) :
                ?>
                    <table class="woo-crm-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Customer / Email', 'woo-crm'); ?></th>
                                <th><?php esc_html_e('Cart Value', 'woo-crm'); ?></th>
                                <th><?php esc_html_e('Stage', 'woo-crm'); ?></th>
                                <th><?php esc_html_e('Last Updated', 'woo-crm'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($latest_carts as $c) : ?>
                                <tr>
                                    <td><strong><?php echo esc_html($c->email); ?></strong></td>
                                    <td><?php echo wc_price($c->cart_total); ?></td>
                                    <td>
                                        <span class="woo-crm-badge stage-badge-<?php echo intval($c->notification_stage); ?>">
                                            <?php
                                            $stages = array(0 => __('Tracked', 'woo-crm'), 1 => __('1hr Reminder', 'woo-crm'), 2 => __('24hr Discount', 'woo-crm'), 3 => __('3-Day Final', 'woo-crm'), 4 => __('Stale', 'woo-crm'));
                                            echo esc_html(isset($stages[$c->notification_stage]) ? $stages[$c->notification_stage] : 'Stage ' . $c->notification_stage);
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html(human_time_diff(strtotime($c->updated_at), current_time('timestamp')) . ' ' . __('ago', 'woo-crm')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p class="p-20 text-muted"><?php esc_html_e('No active abandoned carts currently tracked.', 'woo-crm'); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Customer Journey Timeline -->
        <div class="woo-crm-card">
            <div class="card-header">
                <h2><span class="dashicons dashicons-location-alt"></span> <?php esc_html_e('Recent Order Journeys', 'woo-crm'); ?></h2>
                <a href="<?php echo esc_url(admin_url('admin.php?page=woo-crm&tab=journeys')); ?>" class="button button-secondary"><?php esc_html_e('View Timeline', 'woo-crm'); ?> &rarr;</a>
            </div>
            <div class="card-body p-20">
                <?php if (!empty($recent_journeys)) : ?>
                    <div class="woo-crm-timeline-feed">
                        <?php foreach ($recent_journeys as $j) : ?>
                            <div class="feed-item">
                                <div class="feed-badge badge-<?php echo esc_attr($j['current_status']); ?>">
                                    #<?php echo esc_html($j['order_id']); ?>
                                </div>
                                <div class="feed-content">
                                    <div class="feed-title">
                                        <strong><?php echo esc_html($j['customer_name'] ? $j['customer_name'] : $j['customer_email']); ?></strong>
                                        <span class="feed-total"><?php echo wc_price($j['total']); ?></span>
                                    </div>
                                    <div class="feed-desc">
                                        <?php esc_html_e('Status:', 'woo-crm'); ?> <strong><?php echo esc_html(ucfirst($j['current_status'])); ?></strong> &bull; <?php echo esc_html($j['date_created']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p class="text-muted"><?php esc_html_e('No recent order journeys found.', 'woo-crm'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
