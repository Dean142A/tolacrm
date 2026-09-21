<?php
/**
 * Woo_CRM_Merge_Tags
 *
 * Dynamic Merge Tags Processor for email templates, campaign blasts, and notifications.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_CRM_Merge_Tags {

    /**
     * Parse and replace merge tags in content string.
     *
     * @param string $content Text/HTML containing {{tag}} placeholders
     * @param array $context Data context (contact, cart, coupon, etc.)
     * @return string Parsed text with tags replaced
     */
    public static function process($content, $context = array()) {
        if (empty($content)) {
            return '';
        }

        $site_name = get_bloginfo('name');
        $site_url  = get_site_url();

        $tags = array(
            '{{site.title}}'          => $site_name,
            '{{site.url}}'            => $site_url,
            '{{contact.email}}'       => isset($context['email']) ? $context['email'] : '',
            '{{contact.first_name}}'  => isset($context['first_name']) ? $context['first_name'] : (isset($context['email']) ? strstr($context['email'], '@', true) : 'Valued Customer'),
            '{{contact.segment}}'     => isset($context['segment']) ? ucfirst($context['segment']) : '',
            '{{contact.tags}}'        => isset($context['tags']) && is_array($context['tags']) ? implode(', ', $context['tags']) : '',
            '{{cart.total}}'          => isset($context['cart_total']) ? wc_price($context['cart_total']) : '',
            '{{cart.checkout_url}}'   => function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : site_url('/checkout'),
            '{{cart.items_table}}'    => isset($context['cart_items_table']) ? $context['cart_items_table'] : '',
            '{{coupon.code}}'         => isset($context['coupon_code']) ? $context['coupon_code'] : '',
            '{{coupon.expiry_date}}'  => isset($context['expiry_date']) ? $context['expiry_date'] : '',
            '{{coupon.amount}}'       => isset($context['discount_amount']) ? $context['discount_amount'] : '',
        );

        return str_replace(array_keys($tags), array_values($tags), $content);
    }

    /**
     * Get list of all available merge tags with labels for admin UI insertion toolbar.
     *
     * @return array
     */
    public static function get_available_tags() {
        return array(
            array('tag' => '{{contact.first_name}}',  'label' => __('First Name', 'woo-crm')),
            array('tag' => '{{contact.email}}',       'label' => __('Email Address', 'woo-crm')),
            array('tag' => '{{contact.segment}}',     'label' => __('Segment Status', 'woo-crm')),
            array('tag' => '{{cart.total}}',          'label' => __('Cart Total Value', 'woo-crm')),
            array('tag' => '{{cart.checkout_url}}',   'label' => __('Checkout Page Link', 'woo-crm')),
            array('tag' => '{{cart.items_table}}',    'label' => __('Cart Items Table', 'woo-crm')),
            array('tag' => '{{coupon.code}}',         'label' => __('Dynamic Coupon Code', 'woo-crm')),
            array('tag' => '{{coupon.expiry_date}}',  'label' => __('Coupon Expiry Date', 'woo-crm')),
            array('tag' => '{{site.title}}',          'label' => __('Store Name', 'woo-crm')),
        );
    }
}
