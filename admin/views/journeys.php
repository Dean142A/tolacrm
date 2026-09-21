<?php
/**
 * Customer Journeys Timeline Tab View
 */

if (!defined('ABSPATH')) {
    exit;
}

$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$journeys = Woo_CRM_Orders::get_order_journeys(array(
    'status' => $status_filter,
    'limit'  => 25
));
?>

<div class="woo-crm-tab-content woo-crm-journeys">
    <div class="woo-crm-card">
        <div class="card-header">
            <h2><span class="dashicons dashicons-location-alt"></span> <?php esc_html_e('Customer Order Journey Timeline', 'woo-crm'); ?></h2>
            <div class="card-filters">
                <a href="<?php echo esc_url(admin_url('admin.php?page=woo-crm&tab=journeys')); ?>" class="button <?php echo empty($status_filter) ? 'button-primary' : 'button-secondary'; ?>"><?php esc_html_e('All Orders', 'woo-crm'); ?></a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=woo-crm&tab=journeys&status=processing')); ?>" class="button <?php echo $status_filter === 'processing' ? 'button-primary' : 'button-secondary'; ?>"><?php esc_html_e('Processing', 'woo-crm'); ?></a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=woo-crm&tab=journeys&status=completed')); ?>" class="button <?php echo $status_filter === 'completed' ? 'button-primary' : 'button-secondary'; ?>"><?php esc_html_e('Completed', 'woo-crm'); ?></a>
            </div>
        </div>

        <div class="card-body p-20">
            <?php if (!empty($journeys)) : ?>
                <div class="woo-crm-journeys-list">
                    <?php foreach ($journeys as $j) : ?>
                        <div class="journey-order-card">
                            <div class="journey-header">
                                <div class="journey-title">
                                    <span class="dashicons dashicons-cart"></span>
                                    <strong><?php sprintf(esc_html_e('Order #%d', 'woo-crm'), $j['order_id']); ?></strong> — <?php echo esc_html($j['customer_name'] ? $j['customer_name'] : $j['customer_email']); ?>
                                </div>
                                <div class="journey-meta">
                                    <span class="woo-crm-badge badge-<?php echo esc_attr($j['current_status']); ?>"><?php echo esc_html(strtoupper($j['current_status'])); ?></span>
                                    <strong class="journey-total"><?php echo wc_price($j['total']); ?></strong>
                                </div>
                            </div>

                            <!-- Horizontal Timeline Steps -->
                            <div class="journey-timeline-steps">
                                <?php foreach ($j['timeline'] as $idx => $step) : ?>
                                    <div class="timeline-step">
                                        <div class="step-icon step-active"><span class="dashicons dashicons-yes"></span></div>
                                        <div class="step-label"><?php echo esc_html($step['title']); ?></div>
                                        <div class="step-time"><?php echo esc_html($step['timestamp']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="text-muted"><?php esc_html_e('No order journeys found matching the selected filter.', 'woo-crm'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>
