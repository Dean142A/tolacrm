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

            // Force immediate sync with WooCommerce Analytics DataStore if available
            if (class_exists('\Automattic\WooCommerce\Admin\API\Reports\Orders\DataStore')) {
                try {
                    \Automattic\WooCommerce\Admin\API\Reports\Orders\DataStore::sync_order($order_id);
                } catch (\Exception $e) {
                    // Silently ignore sync exception if any
                }
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
     * Retrieve monthly revenue and order count for past $months with zero-filled timeline.
     * Checks wc_order_stats, HPOS (wc_orders), and wp_posts with fallbacks.
     */
    public static function get_monthly_analytics($months = 12) {
        global $wpdb;

        $monthly_data = array();
        for ($i = $months - 1; $i >= 0; $i--) {
            $ym = date('Y-m', strtotime("-$i months", current_time('timestamp')));
            $monthly_data[$ym] = array(
                'label'   => date('M Y', strtotime($ym . '-01')),
                'revenue' => 0.0,
                'orders'  => 0,
            );
        }

        $valid_statuses = array('completed', 'processing', 'wc-completed', 'wc-processing');
        $has_data = false;

        // 1. Try wc_order_stats
        $stats_table = $wpdb->prefix . 'wc_order_stats';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$stats_table}'") === $stats_table) {
            $sql = "SELECT 
                        DATE_FORMAT(date_created, '%Y-%m') as y_m, 
                        SUM(COALESCE(net_total, total_sales, 0)) as total_revenue, 
                        COUNT(order_id) as total_orders 
                    FROM {$stats_table} 
                    WHERE status IN ('" . implode("','", $valid_statuses) . "') 
                      AND date_created >= DATE_SUB(NOW(), INTERVAL {$months} MONTH) 
                    GROUP BY y_m 
                    ORDER BY y_m ASC";
            $rows = $wpdb->get_results($sql);
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    if (isset($monthly_data[$row->y_m])) {
                        $monthly_data[$row->y_m]['revenue'] = floatval($row->total_revenue);
                        $monthly_data[$row->y_m]['orders']  = intval($row->total_orders);
                        if ($row->total_orders > 0) {
                            $has_data = true;
                        }
                    }
                }
            }
        }

        // 2. If wc_order_stats returned no rows, query HPOS or posts directly
        if (!$has_data) {
            $hpos_table = $wpdb->prefix . 'wc_orders';
            if ($wpdb->get_var("SHOW TABLES LIKE '{$hpos_table}'") === $hpos_table) {
                $sql_hpos = "SELECT 
                                DATE_FORMAT(date_created_gmt, '%Y-%m') as y_m, 
                                SUM(total_amount) as total_revenue, 
                                COUNT(id) as total_orders 
                            FROM {$hpos_table} 
                            WHERE status IN ('" . implode("','", $valid_statuses) . "') 
                              AND type = 'shop_order' 
                              AND date_created_gmt >= DATE_SUB(NOW(), INTERVAL {$months} MONTH) 
                            GROUP BY y_m 
                            ORDER BY y_m ASC";
                $hpos_rows = $wpdb->get_results($sql_hpos);
                if (!empty($hpos_rows)) {
                    foreach ($hpos_rows as $row) {
                        if (isset($monthly_data[$row->y_m])) {
                            $monthly_data[$row->y_m]['revenue'] = floatval($row->total_revenue);
                            $monthly_data[$row->y_m]['orders']  = intval($row->total_orders);
                        }
                    }
                }
            } else {
                // Fallback to wp_posts
                $sql_posts = "SELECT 
                                DATE_FORMAT(p.post_date, '%Y-%m') as y_m, 
                                SUM(pm.meta_value) as total_revenue, 
                                COUNT(p.ID) as total_orders 
                            FROM {$wpdb->posts} p 
                            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_order_total' 
                            WHERE p.post_type = 'shop_order' 
                              AND p.post_status IN ('" . implode("','", $valid_statuses) . "') 
                              AND p.post_date >= DATE_SUB(NOW(), INTERVAL {$months} MONTH) 
                            GROUP BY y_m 
                            ORDER BY y_m ASC";
                $post_rows = $wpdb->get_results($sql_posts);
                if (!empty($post_rows)) {
                    foreach ($post_rows as $row) {
                        if (isset($monthly_data[$row->y_m])) {
                            $monthly_data[$row->y_m]['revenue'] = floatval($row->total_revenue);
                            $monthly_data[$row->y_m]['orders']  = intval($row->total_orders);
                        }
                    }
                }
            }
        }

        return $monthly_data;
    }

    /**
     * Retrieve top revenue products with fallbacks across wc_order_product_lookup and woocommerce_order_items.
     */
    public static function get_top_products_analytics($limit = 10) {
        global $wpdb;

        $lookup_table = $wpdb->prefix . 'wc_order_product_lookup';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$lookup_table}'") === $lookup_table) {
            $sql = "SELECT product_id, SUM(product_qty) as total_qty, SUM(product_net_revenue) as total_revenue 
                    FROM {$lookup_table} 
                    GROUP BY product_id 
                    ORDER BY total_revenue DESC 
                    LIMIT {$limit}";
            $results = $wpdb->get_results($sql);
            if (!empty($results)) {
                return $results;
            }
        }

        // Fallback using woocommerce_order_items & woocommerce_order_itemmeta
        $items_table = $wpdb->prefix . 'woocommerce_order_items';
        $meta_table  = $wpdb->prefix . 'woocommerce_order_itemmeta';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$items_table}'") === $items_table) {
            $sql_fallback = "SELECT 
                                im_prod.meta_value as product_id, 
                                SUM(im_qty.meta_value) as total_qty, 
                                SUM(im_total.meta_value) as total_revenue 
                             FROM {$items_table} oi 
                             INNER JOIN {$meta_table} im_prod ON oi.order_item_id = im_prod.order_item_id AND im_prod.meta_key IN ('_product_id', '_variation_id') 
                             INNER JOIN {$meta_table} im_qty ON oi.order_item_id = im_qty.order_item_id AND im_qty.meta_key = '_qty' 
                             INNER JOIN {$meta_table} im_total ON oi.order_item_id = im_total.order_item_id AND im_total.meta_key = '_line_total' 
                             WHERE oi.order_item_type = 'line_item' AND im_prod.meta_value > 0 
                             GROUP BY im_prod.meta_value 
                             ORDER BY total_revenue DESC 
                             LIMIT {$limit}";
            return $wpdb->get_results($sql_fallback);
        }

        return array();
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
