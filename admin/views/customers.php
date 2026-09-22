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
$customers = Woo_CRM_Customers::get_all_customers($selected_segment, 100);
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
                            $email = $c['email'];
                            $name  = $c['name'];
                            $segment = $c['segment'];
                            $tags = $c['tags'];
                            $identifier = $c['customer_id'] > 0 ? $c['customer_id'] : $email;
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($name); ?></strong>
                                    <?php if (!empty($email)) : ?>
                                        <br><span class="text-muted font-small"><?php echo esc_html($email); ?></span>
                                    <?php endif; ?>
                                    <?php if ($c['is_guest']) : ?>
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
                                <td><strong><?php echo intval($c['total_orders']); ?></strong></td>
                                <td><strong class="text-success"><?php echo wc_price($c['ltv']); ?></strong></td>
                                <td><?php echo esc_html($c['last_order_date'] ? date('M j, Y', strtotime($c['last_order_date'])) : '—'); ?></td>
                                <td class="text-right">
                                    <button class="button button-small button-secondary btn-view-customer-profile" data-identifier="<?php echo esc_attr($identifier); ?>">
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
