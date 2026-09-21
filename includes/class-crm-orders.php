<?php
/**
 * Woo_CRM_Orders
 *
 * Order status change hooks, order journey tracking, and store owner alert dispatches.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_CRM_Orders {

    /**
     * Listener for woocommerce_order_status_changed action.
     */
    public function on_order_status_changed($order_id, $old_status, $new_status, $order) {
        if (!$order_id || !$order) {
            return;
        }

        $email = $order->get_billing_email();
        $user_id = $order->get_user_id();
        $settings = get_option('woo_crm_settings', array());

        // 1. Recalculate customer segment on paid / completed status transitions
        if (in_array($new_status, array('processing', 'completed'), true)) {
            if ($user_id > 0) {
                Woo_CRM_Customers::recalculate_customer_segment($user_id);
            } elseif (!empty($email)) {
                Woo_CRM_Customers::recalculate_customer_segment($email);
            }
        }

        // 2. Journey Tracking: Record timeline note
        $note_text = sprintf(
            __('[CRM Journey] Order status transitioned from "%s" to "%s".', 'woo-crm'),
            wc_get_order_status_name($old_status),
            wc_get_order_status_name($new_status)
        );
        $order->add_order_note($note_text);

        // 3. Store owner notifications
        if ($new_status === 'pending' || $new_status === 'processing') {
            if (!empty($settings['notify_owner_new_order'])) {
                Woo_CRM_Notifications::send_owner_alert('new_order', array(
                    'order_id'   => $order_id,
                    'email'      => $email,
                    'total'      => $order->get_total(),
                    'status'     => $new_status,
                ));
            }
        }

        if ($new_status === 'completed') {
            if (!empty($settings['notify_owner_completed_order'])) {
                Woo_CRM_Notifications::send_owner_alert('completed_order', array(
                    'order_id'   => $order_id,
                    'email'      => $email,
                    'total'      => $order->get_total(),
                ));
            }
        }
    }

    /**
     * Retrieve order journeys for Journeys tab view.
     *
     * @param array $args Filter args (status, limit, paged)
     * @return array Orders list with journey timelines
     */
    public static function get_order_journeys($args = array()) {
        $status = isset($args['status']) ? sanitize_text_field($args['status']) : '';
        $limit  = isset($args['limit']) ? intval($args['limit']) : 20;

        $query_args = array(
            'limit'   => $limit,
            'orderby' => 'date',
            'order'   => 'DESC',
        );

        if (!empty($status)) {
            $query_args['status'] = $status;
        }

        $orders = wc_get_orders($query_args);
        $journeys = array();

        foreach ($orders as $order) {
            $notes = wc_get_order_notes(array(
                'order_id' => $order->get_id(),
                'type'     => 'internal'
            ));

            $timeline = array();

            // Initial cart / order creation step
            $timeline[] = array(
                'title'     => __('Order Placed (Pending)', 'woo-crm'),
                'timestamp' => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : '',
                'status'    => 'pending'
            );

            foreach ($notes as $note) {
                if (strpos($note->content, '[CRM Journey]') !== false) {
                    $timeline[] = array(
                        'title'     => wp_strip_all_tags($note->content),
                        'timestamp' => $note->date_created ? $note->date_created->date('Y-m-d H:i:s') : '',
                        'status'    => $order->get_status()
                    );
                }
            }

            // Current status step
            $timeline[] = array(
                'title'     => sprintf(__('Current Status: %s', 'woo-crm'), wc_get_order_status_name($order->get_status())),
                'timestamp' => $order->get_date_modified() ? $order->get_date_modified()->date('Y-m-d H:i:s') : '',
                'status'    => $order->get_status()
            );

            $journeys[] = array(
                'order_id'       => $order->get_id(),
                'customer_email' => $order->get_billing_email(),
                'customer_name'  => $order->get_formatted_billing_full_name(),
                'total'          => $order->get_total(),
                'current_status' => $order->get_status(),
                'date_created'   => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : '',
                'timeline'       => $timeline,
            );
        }

        return $journeys;
    }
}
