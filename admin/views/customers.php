<?php
/**
 * Customers Tab View
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$stats_table = $wpdb->prefix . 'wc_order_stats';

$selected_segment = isset($_GET['segment']) ? sanitize_text_field($_GET['segment']) : 'all';

$sql = "SELECT customer_id, 
               (SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_billing_email' AND post_id = MAX(order_id) LIMIT 1) as billing_email,
               COUNT(order_id) as total_orders, 
               SUM(net_total) as ltv, 
               MAX(date_created) as last_order_date 
        FROM {$stats_table} 
        WHERE status IN ('completed', 'processing', 'wc-completed', 'wc-processing') 
        GROUP BY customer_id HAVING total_orders > 0 
        ORDER BY ltv DESC LIMIT 100";

$customers = $wpdb->get_results($sql);
$export_url = admin_url('admin.php?action=woo_crm_export_customers');
?>

<div class="woo-crm-tab-content woo-crm-customers">
    <div class="woo-crm-card">
        <div class="card-header">
            <h2><span class="dashicons dashicons-groups"></span> <?php esc_html_e('Customer Profiles & Segmentation', 'woo-crm'); ?></h2>
            <div class="card-filters">
                <a href="<?php echo esc_url($export_url); ?>" class="button button-secondary"><span class="dashicons dashicons-download"></span> <?php esc_html_e('Export CSV', 'woo-crm'); ?></a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=woo-crm&tab=customers&segment=all')); ?>" class="button <?php echo $selected_segment === 'all' ? 'button-primary' : 'button-secondary'; ?>"><?php esc_html_e('All', 'woo-crm'); ?></a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=woo-crm&tab=customers&segment=active')); ?>" class="button <?php echo $selected_segment === 'active' ? 'button-primary' : 'button-secondary'; ?>"><?php esc_html_e('Active', 'woo-crm'); ?></a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=woo-crm&tab=customers&segment=returning')); ?>" class="button <?php echo $selected_segment === 'returning' ? 'button-primary' : 'button-secondary'; ?>"><?php esc_html_e('Returning', 'woo-crm'); ?></a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=woo-crm&tab=customers&segment=new')); ?>" class="button <?php echo $selected_segment === 'new' ? 'button-primary' : 'button-secondary'; ?>"><?php esc_html_e('New', 'woo-crm'); ?></a>
            </div>
        </div>

        <div class="card-body p-0">
            <?php if (!empty($customers)) : ?>
                <table class="woo-crm-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Customer / Identifier', 'woo-crm'); ?></th>
                            <th><?php esc_html_e('Segment Status', 'woo-crm'); ?></th>
                            <th><?php esc_html_e('Contact Tags', 'woo-crm'); ?></th>
                            <th><?php esc_html_e('Total Orders', 'woo-crm'); ?></th>
                            <th><?php esc_html_e('Lifetime Value (LTV)', 'woo-crm'); ?></th>
                            <th><?php esc_html_e('Last Purchase', 'woo-crm'); ?></th>
                            <th class="text-right"><?php esc_html_e('Action', 'woo-crm'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $c) : ?>
                            <?php
                            $user = get_userdata($c->customer_id);
                            $email = $user ? $user->user_email : ($c->billing_email ? $c->billing_email : 'Customer #' . $c->customer_id);
                            $name  = $user ? $user->display_name : __('Guest Customer', 'woo-crm');
                            $segment = Woo_CRM_Customers::recalculate_customer_segment($c->customer_id ? $c->customer_id : $email);
                            $tags = Woo_CRM_Tags::get_tags($c->customer_id ? $c->customer_id : $email);

                            if ($selected_segment !== 'all' && $segment !== $selected_segment) {
                                continue;
                            }
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($name); ?></strong>
                                    <br><span class="text-muted font-small"><?php echo esc_html($email); ?></span>
                                    <?php if (!$c->customer_id) : ?>
                                        <span class="woo-crm-badge badge-guest"><?php esc_html_e('Guest Match', 'woo-crm'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="woo-crm-badge segment-badge-<?php echo esc_attr($segment); ?>">
                                        <?php echo esc_html(ucfirst($segment)); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($tags)) : ?>
                                        <?php foreach ($tags as $t) : ?>
                                            <span class="contact-tag-pill"><?php echo esc_html($t); ?></span>
                                        <?php endforeach; ?>
                                    <?php else : ?>
                                        <span class="text-muted font-small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo intval($c->total_orders); ?></strong></td>
                                <td><strong class="text-success"><?php echo wc_price($c->ltv); ?></strong></td>
                                <td><?php echo esc_html(date('M j, Y', strtotime($c->last_order_date))); ?></td>
                                <td class="text-right">
                                    <button class="button button-small button-secondary btn-view-customer-profile" data-identifier="<?php echo esc_attr($c->customer_id ? $c->customer_id : $email); ?>">
                                        <span class="dashicons dashicons-id"></span> <?php esc_html_e('View Profile', 'woo-crm'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p class="p-20 text-muted"><?php esc_html_e('No customer records found matching criteria.', 'woo-crm'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Customer Profile Modal -->
<div id="woo-crm-customer-modal" class="woo-crm-modal">
    <div class="woo-crm-modal-content">
        <div class="woo-crm-modal-header">
            <h2 id="modal-customer-title"><?php esc_html_e('Customer Profile', 'woo-crm'); ?></h2>
            <button class="woo-crm-modal-close">&times;</button>
        </div>
        <div class="woo-crm-modal-body" id="modal-customer-body">
            <p><?php esc_html_e('Loading profile data...', 'woo-crm'); ?></p>
        </div>
    </div>
</div>
