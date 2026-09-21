<?php
/**
 * Admin Navigation Header Partial
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$carts_table = $wpdb->prefix . 'crm_carts';
$active_carts_count = $wpdb->get_var("SELECT COUNT(*) FROM {$carts_table} WHERE converted_at IS NULL");

$tabs = array(
    'overview'  => array('label' => __('Overview', 'woo-crm'), 'icon' => 'dashicons-dashboard'),
    'carts'     => array('label' => __('Abandoned Carts', 'woo-crm'), 'icon' => 'dashicons-cart', 'badge' => $active_carts_count),
    'customers' => array('label' => __('Customers', 'woo-crm'), 'icon' => 'dashicons-groups'),
    'journeys'  => array('label' => __('Customer Journeys', 'woo-crm'), 'icon' => 'dashicons-location-alt'),
    'campaigns' => array('label' => __('Campaigns', 'woo-crm'), 'icon' => 'dashicons-megaphone'),
    'analytics' => array('label' => __('Analytics', 'woo-crm'), 'icon' => 'dashicons-chart-area'),
    'settings'  => array('label' => __('Settings', 'woo-crm'), 'icon' => 'dashicons-admin-generic'),
);

$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
?>

<div class="woo-crm-header">
    <div class="woo-crm-header-title">
        <span class="dashicons dashicons-chart-line woo-crm-logo-icon"></span>
        <div>
            <h1><?php esc_html_e('WooCommerce CRM', 'woo-crm'); ?> <span class="woo-crm-badge-ver">v<?php echo esc_html(WOO_CRM_VERSION); ?></span></h1>
            <p class="woo-crm-subtitle"><?php esc_html_e('Cart Recovery, Segmentation, Journey Tracking & Sales Analytics', 'woo-crm'); ?></p>
        </div>
    </div>
</div>

<nav class="woo-crm-nav-tabs">
    <?php foreach ($tabs as $key => $tab) : ?>
        <?php
        $active_class = ($current_tab === $key) ? 'nav-tab-active' : '';
        $url = admin_url('admin.php?page=woo-crm&tab=' . $key);
        ?>
        <a href="<?php echo esc_url($url); ?>" class="nav-tab <?php echo esc_attr($active_class); ?>">
            <span class="dashicons <?php echo esc_attr($tab['icon']); ?>"></span>
            <span class="tab-label"><?php echo esc_html($tab['label']); ?></span>
            <?php if (!empty($tab['badge']) && intval($tab['badge']) > 0) : ?>
                <span class="woo-crm-tab-badge"><?php echo esc_html($tab['badge']); ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>
