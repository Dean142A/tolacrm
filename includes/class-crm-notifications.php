<?php
/**
 * Woo_CRM_Notifications
 *
 * HTML Email builder and wp_mail delivery wrapper for cart recovery, alerts, blasts, and digests.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_CRM_Notifications {

    /**
     * Send Cart Abandonment Recovery Email.
     */
    public static function send_cart_recovery($email, $cart, $stage, $coupon_code = null) {
        $store_name = get_bloginfo('name');
        $checkout_url = wc_get_checkout_url();

        $subjects = array(
            1 => sprintf(__('Did you leave something behind at %s?', 'woo-crm'), $store_name),
            2 => sprintf(__('Save on the items in your cart at %s!', 'woo-crm'), $store_name),
            3 => sprintf(__('Final Notice: Your cart at %s is expiring soon!', 'woo-crm'), $store_name),
            4 => sprintf(__('Hurry! Items in your cart at %s are almost sold out!', 'woo-crm'), $store_name),
        );

        $subject = isset($subjects[$stage]) ? $subjects[$stage] : sprintf(__('Your cart at %s', 'woo-crm'), $store_name);

        $body = '<p>' . esc_html__('Hello,', 'woo-crm') . '</p>';
        $body .= '<p>' . esc_html__('We noticed you left some great items in your shopping cart. We have saved them for you!', 'woo-crm') . '</p>';

        // Render Cart Items Table
        $body .= self::render_cart_items_html($cart->cart_contents);

        if (!empty($coupon_code)) {
            $body .= '<div style="background:#eef2ff; border:2px dashed #4f46e5; border-radius:8px; padding:16px; margin:20px 0; text-align:center;">';
            $body .= '<p style="margin:0; font-size:14px; color:#4338ca; font-weight:600;">' . esc_html__('Special Discount Code:', 'woo-crm') . '</p>';
            $body .= '<h3 style="margin:8px 0; font-size:24px; color:#1e1b4b; letter-spacing:2px;">' . esc_html($coupon_code) . '</h3>';
            $body .= '<p style="margin:0; font-size:12px; color:#6366f1;">' . esc_html__('Apply this code at checkout to claim your offer.', 'woo-crm') . '</p>';
            $body .= '</div>';
        }

        $body .= '<div style="text-align:center; margin:30px 0;">';
        $body .= '<a href="' . esc_url($checkout_url) . '" style="background:#4f46e5; color:#ffffff; text-decoration:none; padding:14px 28px; border-radius:6px; font-weight:bold; display:inline-block;">' . esc_html__('Complete Your Purchase Now &rarr;', 'woo-crm') . '</a>';
        $body .= '</div>';

        return self::send_email($email, $subject, $body);
    }

    /**
     * Send Store Owner Alert Email.
     */
    public static function send_owner_alert($event_type, $details) {
        $settings = get_option('woo_crm_settings', array());
        $to = !empty($settings['weekly_digest_recipients']) ? $settings['weekly_digest_recipients'] : get_option('admin_email');

        $titles = array(
            'new_order'      => __('CRM Alert: New Order Placed', 'woo-crm'),
            'completed_order'=> __('CRM Alert: Order Completed', 'woo-crm'),
            'cart_converted' => __('CRM Alert: Abandoned Cart Converted!', 'woo-crm'),
            'segment_active' => __('CRM Alert: Customer Entered Active Segment', 'woo-crm'),
        );

        $subject = isset($titles[$event_type]) ? $titles[$event_type] : __('CRM Store Alert', 'woo-crm');

        $body = '<h3>' . esc_html($subject) . '</h3>';
        $body .= '<ul>';
        foreach ($details as $k => $v) {
            $body .= '<li><strong>' . esc_html(ucwords(str_replace('_', ' ', $k))) . ':</strong> ' . esc_html($v) . '</li>';
        }
        $body .= '</ul>';

        return self::send_email($to, $subject, $body);
    }

    /**
     * Send Segment Welcome Email.
     */
    public static function send_segment_welcome($email, $segment, $coupon_code) {
        $store_name = get_bloginfo('name');
        $subject = sprintf(__('Welcome to our %s VIP segment at %s!', 'woo-crm'), ucfirst($segment), $store_name);

        $body = '<p>' . sprintf(esc_html__('Thank you for being a valued customer at %s.', 'woo-crm'), $store_name) . '</p>';
        $body .= '<p>' . sprintf(esc_html__('You have unlocked your new status as a %s member!', 'woo-crm'), esc_html(ucfirst($segment))) . '</p>';

        if ($coupon_code) {
            $body .= '<div style="background:#ecfdf5; border:2px dashed #10b981; border-radius:8px; padding:16px; margin:20px 0; text-align:center;">';
            $body .= '<p style="margin:0; font-size:14px; color:#047857; font-weight:600;">' . esc_html__('Exclusive Reward Coupon:', 'woo-crm') . '</p>';
            $body .= '<h3 style="margin:8px 0; font-size:24px; color:#064e3b; letter-spacing:2px;">' . esc_html($coupon_code) . '</h3>';
            $body .= '</div>';
        }

        return self::send_email($email, $subject, $body);
    }

    /**
     * Send Manual Campaign Blast Email.
     */
    public static function send_manual_blast($email, $subject, $message_body, $coupon_code = null) {
        $body = '<p>' . wp_kses_post(nl2br($message_body)) . '</p>';

        if (!empty($coupon_code)) {
            $body .= '<div style="background:#fffbeb; border:2px dashed #f59e0b; border-radius:8px; padding:16px; margin:20px 0; text-align:center;">';
            $body .= '<p style="margin:0; font-size:14px; color:#b45309; font-weight:600;">' . esc_html__('Your Special Promo Code:', 'woo-crm') . '</p>';
            $body .= '<h3 style="margin:8px 0; font-size:24px; color:#78350f; letter-spacing:2px;">' . esc_html($coupon_code) . '</h3>';
            $body .= '</div>';
        }

        return self::send_email($email, $subject, $body);
    }

    /**
     * Send Weekly Purchase Summary Digest.
     */
    public static function send_weekly_digest($to_emails, $digest_data) {
        $subject = sprintf(__('Weekly Sales & Top Products Summary — %s', 'woo-crm'), date('M j, Y'));

        $body = '<h2>' . esc_html__('Weekly WooCommerce Performance Summary', 'woo-crm') . '</h2>';
        $body .= '<p>' . esc_html__('Here is your top product sales ranking for the past 7 days:', 'woo-crm') . '</p>';

        if (!empty($digest_data)) {
            $body .= '<table style="width:100%; border-collapse:collapse; margin:20px 0;">';
            $body .= '<tr style="background:#f3f4f6; text-align:left;"><th style="padding:10px; border:1px solid #e5e7eb;">' . esc_html__('Product', 'woo-crm') . '</th><th style="padding:10px; border:1px solid #e5e7eb;">' . esc_html__('Qty Sold', 'woo-crm') . '</th><th style="padding:10px; border:1px solid #e5e7eb;">' . esc_html__('Net Revenue', 'woo-crm') . '</th></tr>';

            foreach ($digest_data as $row) {
                $product_name = get_the_title($row->product_id);
                $body .= '<tr>';
                $body .= '<td style="padding:10px; border:1px solid #e5e7eb;">' . esc_html($product_name ? $product_name : '#' . $row->product_id) . '</td>';
                $body .= '<td style="padding:10px; border:1px solid #e5e7eb;">' . esc_html($row->total_qty) . '</td>';
                $body .= '<td style="padding:10px; border:1px solid #e5e7eb;">' . wc_price($row->total_net) . '</td>';
                $body .= '</tr>';
            }
            $body .= '</table>';
        } else {
            $body .= '<p><em>' . esc_html__('No product sales recorded in the past 7 days.', 'woo-crm') . '</em></p>';
        }

        return self::send_email($to_emails, $subject, $body);
    }

    /**
     * Render cart items as HTML table snippet.
     */
    private static function render_cart_items_html($json_contents) {
        $items = json_decode($json_contents, true);
        if (empty($items) || !is_array($items)) {
            return '';
        }

        $html = '<table style="width:100%; border-collapse:collapse; margin:20px 0; font-size:14px;">';
        $html .= '<tr style="background:#f9fafb; text-align:left;"><th style="padding:10px; border-bottom:2px solid #e5e7eb;">' . esc_html__('Item', 'woo-crm') . '</th><th style="padding:10px; border-bottom:2px solid #e5e7eb;">' . esc_html__('Qty', 'woo-crm') . '</th><th style="padding:10px; border-bottom:2px solid #e5e7eb;">' . esc_html__('Price', 'woo-crm') . '</th></tr>';

        foreach ($items as $item) {
            $html .= '<tr>';
            $html .= '<td style="padding:10px; border-bottom:1px solid #f3f4f6;">' . esc_html($item['name']) . '</td>';
            $html .= '<td style="padding:10px; border-bottom:1px solid #f3f4f6;">' . esc_html($item['quantity']) . '</td>';
            $html .= '<td style="padding:10px; border-bottom:1px solid #f3f4f6;">' . wc_price($item['price']) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';
        return $html;
    }

    /**
     * Core wp_mail dispatcher with clean HTML template wrap.
     */
    private static function send_email($to, $subject, $content) {
        if (empty($to)) {
            return false;
        }

        $headers = array('Content-Type: text/html; charset=UTF-8');
        $site_name = get_bloginfo('name');

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0; padding:20px; font-family:Helvetica, Arial, sans-serif; background:#f4f5f7; color:#333333;">';
        $html .= '<div style="max-width:600px; margin:0 auto; background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 4px 6px rgba(0,0,0,0.05);">';
        $html .= '<div style="background:#1e1b4b; padding:20px; text-align:center; color:#ffffff;"><h1 style="margin:0; font-size:20px;">' . esc_html($site_name) . '</h1></div>';
        $html .= '<div style="padding:30px;">' . $content . '</div>';
        $html .= '<div style="background:#f9fafb; padding:15px; text-align:center; font-size:12px; color:#9ca3af; border-top:1px solid #f3f4f6;">&copy; ' . date('Y') . ' ' . esc_html($site_name) . '. All rights reserved.</div>';
        $html .= '</div></body></html>';

        return wp_mail($to, $subject, $html, $headers);
    }
}
