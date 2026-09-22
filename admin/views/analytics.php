<?php
/**
 * Monthly Sales Analytics Dashboard View
 */

if (!defined('ABSPATH')) {
    exit;
}

$monthly_data = Woo_CRM_Orders::get_monthly_analytics(12);

$labels       = array();
$revenue_data = array();
$orders_data  = array();

foreach ($monthly_data as $ym => $data) {
    $labels[]       = $data['label'];
    $revenue_data[] = round($data['revenue'], 2);
    $orders_data[]  = $data['orders'];
}

// 2. Month-over-Month calculation
$cur_month_rev  = !empty($revenue_data) ? end($revenue_data) : 0;
$prev_month_rev = (count($revenue_data) >= 2) ? $revenue_data[count($revenue_data) - 2] : 0;
$mom_rev_pct    = ($prev_month_rev > 0) ? (($cur_month_rev - $prev_month_rev) / $prev_month_rev) * 100 : 0;

$cur_month_ord  = !empty($orders_data) ? end($orders_data) : 0;
$prev_month_ord = (count($orders_data) >= 2) ? $orders_data[count($orders_data) - 2] : 0;
$mom_ord_pct    = ($prev_month_ord > 0) ? (($cur_month_ord - $prev_month_ord) / $prev_month_ord) * 100 : 0;

// 3. Top Products Ranking (by net revenue)
$top_products = Woo_CRM_Orders::get_top_products_analytics(10);
?>

<div class="woo-crm-tab-content woo-crm-analytics">
    <!-- MoM KPI Summary Cards -->
    <div class="woo-crm-metrics-grid mb-20">
        <div class="woo-crm-card woo-crm-metric-card">
            <div class="metric-icon metric-purple"><span class="dashicons dashicons-chart-line"></span></div>
            <div class="metric-info">
                <span class="metric-title"><?php esc_html_e('Current Month Revenue', 'woo-crm'); ?></span>
                <span class="metric-value"><?php echo wc_price($cur_month_rev); ?></span>
                <span class="metric-sub <?php echo $mom_rev_pct >= 0 ? 'text-success' : 'text-danger'; ?>">
                    <?php echo $mom_rev_pct >= 0 ? '▲ +' : '▼ '; ?><?php echo number_format(abs($mom_rev_pct), 1); ?>% <?php esc_html_e('vs prior month', 'woo-crm'); ?>
                </span>
            </div>
        </div>

        <div class="woo-crm-card woo-crm-metric-card">
            <div class="metric-icon metric-blue"><span class="dashicons dashicons-cart"></span></div>
            <div class="metric-info">
                <span class="metric-title"><?php esc_html_e('Current Month Orders', 'woo-crm'); ?></span>
                <span class="metric-value"><?php echo esc_html($cur_month_ord); ?></span>
                <span class="metric-sub <?php echo $mom_ord_pct >= 0 ? 'text-success' : 'text-danger'; ?>">
                    <?php echo $mom_ord_pct >= 0 ? '▲ +' : '▼ '; ?><?php echo number_format(abs($mom_ord_pct), 1); ?>% <?php esc_html_e('vs prior month', 'woo-crm'); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Chart Visualizations Row -->
    <div class="woo-crm-grid-2col mb-20">
        <div class="woo-crm-card">
            <div class="card-header">
                <h2><span class="dashicons dashicons-chart-area"></span> <?php esc_html_e('Monthly Revenue (Past 12 Months)', 'woo-crm'); ?></h2>
            </div>
            <div class="card-body p-20">
                <canvas id="crm-chart-monthly-revenue" height="240"></canvas>
            </div>
        </div>

        <div class="woo-crm-card">
            <div class="card-header">
                <h2><span class="dashicons dashicons-chart-bar"></span> <?php esc_html_e('Monthly Order Volume', 'woo-crm'); ?></h2>
            </div>
            <div class="card-body p-20">
                <canvas id="crm-chart-monthly-orders" height="240"></canvas>
            </div>
        </div>
    </div>

    <!-- Top Performing Products Table -->
    <div class="woo-crm-card">
        <div class="card-header">
            <h2><span class="dashicons dashicons-award"></span> <?php esc_html_e('Top Revenue Products', 'woo-crm'); ?></h2>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($top_products)) : ?>
                <table class="woo-crm-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><?php esc_html_e('Product Name', 'woo-crm'); ?></th>
                            <th><?php esc_html_e('Quantity Sold', 'woo-crm'); ?></th>
                            <th><?php esc_html_e('Total Net Revenue', 'woo-crm'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_products as $idx => $prod) : ?>
                            <?php $p_name = get_the_title($prod->product_id); ?>
                            <tr>
                                <td><strong><?php echo $idx + 1; ?></strong></td>
                                <td><strong><?php echo esc_html($p_name ? $p_name : 'Product #' . $prod->product_id); ?></strong></td>
                                <td><?php echo esc_html($prod->total_qty); ?></td>
                                <td><strong class="text-success"><?php echo wc_price($prod->total_revenue); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p class="p-20 text-muted"><?php esc_html_e('No product sales data available yet.', 'woo-crm'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Embedded Chart.js Data -->
<script type="text/javascript">
    window.wooCrmAnalyticsData = {
        labels: <?php echo json_encode($labels); ?>,
        revenue: <?php echo json_encode($revenue_data); ?>,
        orders: <?php echo json_encode($orders_data); ?>
    };
</script>
