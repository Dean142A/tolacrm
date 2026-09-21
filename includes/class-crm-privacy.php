<?php
/**
 * Woo_CRM_Privacy
 *
 * GDPR Personal Data Exporter & Eraser hooks for wp_crm_carts and wp_crm_campaign_log.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_CRM_Privacy {

    /**
     * Register personal data exporters.
     */
    public function register_exporters($exporters) {
        $exporters['woo_crm_data'] = array(
            'exporter_friendly_name' => __('WooCommerce CRM Records', 'woo-crm'),
            'callback'               => array($this, 'export_personal_data'),
        );
        return $exporters;
    }

    /**
     * Register personal data erasers.
     */
    public function register_erasers($erasers) {
        $erasers['woo_crm_data'] = array(
            'eraser_friendly_name' => __('WooCommerce CRM Records', 'woo-crm'),
            'callback'             => array($this, 'erase_personal_data'),
        );
        return $erasers;
    }

    /**
     * Export personal data for email address.
     */
    public function export_personal_data($email_address, $page = 1) {
        global $wpdb;

        $export_items = array();

        // 1. Cart records
        $carts_table = $wpdb->prefix . 'crm_carts';
        $carts = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$carts_table} WHERE email = %s", $email_address));

        foreach ($carts as $cart) {
            $data = array(
                array('name' => __('Cart Email', 'woo-crm'), 'value' => $cart->email),
                array('name' => __('Cart Value', 'woo-crm'), 'value' => $cart->cart_total),
                array('name' => __('Cart Contents', 'woo-crm'), 'value' => $cart->cart_contents),
                array('name' => __('Created At', 'woo-crm'), 'value' => $cart->created_at),
                array('name' => __('Last Updated', 'woo-crm'), 'value' => $cart->updated_at),
                array('name' => __('Converted At', 'woo-crm'), 'value' => $cart->converted_at ? $cart->converted_at : __('No', 'woo-crm')),
            );

            $export_items[] = array(
                'group_id'    => 'woo_crm_carts',
                'group_label' => __('CRM Tracked Carts', 'woo-crm'),
                'item_id'     => 'cart-' . $cart->id,
                'data'        => $data,
            );
        }

        // 2. Campaign log records
        $campaigns_table = $wpdb->prefix . 'crm_campaign_log';
        $campaigns = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$campaigns_table} WHERE email = %s", $email_address));

        foreach ($campaigns as $camp) {
            $data = array(
                array('name' => __('Recipient Email', 'woo-crm'), 'value' => $camp->email),
                array('name' => __('Segment', 'woo-crm'), 'value' => $camp->segment),
                array('name' => __('Campaign Type', 'woo-crm'), 'value' => $camp->campaign_type),
                array('name' => __('Coupon Code', 'woo-crm'), 'value' => $camp->coupon_code ? $camp->coupon_code : __('None', 'woo-crm')),
                array('name' => __('Sent At', 'woo-crm'), 'value' => $camp->sent_at),
            );

            $export_items[] = array(
                'group_id'    => 'woo_crm_campaigns',
                'group_label' => __('CRM Campaign Sends', 'woo-crm'),
                'item_id'     => 'campaign-' . $camp->id,
                'data'        => $data,
            );
        }

        return array(
            'data' => $export_items,
            'done' => true,
        );
    }

    /**
     * Erase personal data for email address.
     */
    public function erase_personal_data($email_address, $page = 1) {
        global $wpdb;

        $carts_table = $wpdb->prefix . 'crm_carts';
        $campaigns_table = $wpdb->prefix . 'crm_campaign_log';

        $items_removed = false;
        $items_retained = false;
        $messages = array();

        $carts_deleted = $wpdb->delete($carts_table, array('email' => $email_address), array('%s'));
        $campaigns_deleted = $wpdb->delete($campaigns_table, array('email' => $email_address), array('%s'));

        if ($carts_deleted || $campaigns_deleted) {
            $items_removed = true;
            $messages[] = sprintf(__('Removed CRM cart and campaign records for email %s', 'woo-crm'), $email_address);
        }

        return array(
            'items_removed'  => $items_removed,
            'items_retained' => $items_retained,
            'messages'       => $messages,
            'done'           => true,
        );
    }
}
