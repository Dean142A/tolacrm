<?php
/**
 * Woo_CRM_Customers
 *
 * Customer segmentation calculator, caching, and per-customer profile aggregation.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_CRM_Customers {

    /**
     * Recalculate customer segment based on wc_order_stats.
     *
     * @param string|int $customer_identifier User ID or Email string
     * @return string Segment name ('new', 'returning', 'active')
     */
    public static function recalculate_customer_segment($customer_identifier) {
        global $wpdb;

        $user_id = 0;
        $email = '';

        if (is_numeric($customer_identifier) && $customer_identifier > 0) {
            $user_id = intval($customer_identifier);
            $user = get_userdata($user_id);
            if ($user) {
                $email = $user->user_email;
            }
        } elseif (is_email($customer_identifier)) {
            $email = sanitize_email($customer_identifier);
            $user = get_user_by('email', $email);
            if ($user) {
                $user_id = $user->ID;
            }
        }

        if (empty($email) && !$user_id) {
            return 'new';
        }

        $stats_table = $wpdb->prefix . 'wc_order_stats';
        
        // Build query using wc_order_stats
        $where_clause = "";
        $params = array();

        if ($user_id > 0) {
            $where_clause = "WHERE customer_id = %d AND status IN ('wc-completed', 'wc-processing')";
            $params[] = $user_id;
        } else {
            // Match guest by billing email in order stats or postmeta
            $where_clause = "WHERE status IN ('wc-completed', 'wc-processing') AND order_id IN (
                SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_billing_email' AND meta_value = %s
            )";
            $params[] = $email;
        }

        $sql = "SELECT COUNT(*) as total_orders, MAX(date_created) as last_order_date FROM {$stats_table} {$where_clause}";
        $row = $wpdb->get_row($wpdb->prepare($sql, $params));

        $total_orders = $row ? intval($row->total_orders) : 0;
        $last_order_date = $row && $row->last_order_date ? $row->last_order_date : null;

        $settings = get_option('woo_crm_settings', array());
        $active_days = isset($settings['active_segment_days']) ? intval($settings['active_segment_days']) : 30;

        $old_segment = 'new';
        if ($user_id > 0) {
            $old_segment = get_user_meta($user_id, '_crm_segment', true);
            if (!$old_segment) {
                $old_segment = 'new';
            }
        }

        $new_segment = 'new';

        if ($total_orders === 0) {
            $new_segment = 'new';
        } else {
            if ($last_order_date) {
                $last_order_ts = strtotime($last_order_date);
                $days_since = (current_time('timestamp') - $last_order_ts) / DAY_IN_SECONDS;

                if ($days_since <= $active_days) {
                    $new_segment = 'active';
                } else {
                    $new_segment = 'returning';
                }
            } else {
                $new_segment = 'returning';
            }
        }

        // Cache in user meta if registered user
        if ($user_id > 0) {
            update_user_meta($user_id, '_crm_segment', $new_segment);
        }

        // Trigger transition handler if segment changed
        if ($old_segment !== $new_segment) {
            Woo_CRM_Campaigns::on_segment_change($email ? $email : $user_id, $new_segment);

            if ($new_segment === 'active' && !empty($settings['notify_owner_segment_active'])) {
                Woo_CRM_Notifications::send_owner_alert('segment_active', array(
                    'email'   => $email,
                    'user_id' => $user_id
                ));
            }
        }

        return $new_segment;
    }

    /**
     * Aggregate detailed customer profile for Customers view modal/tab.
     */
    public static function get_customer_profile($identifier) {
        global $wpdb;

        $user_id = 0;
        $email = '';
        $is_guest_match = false;

        if (is_numeric($identifier) && $identifier > 0) {
            $user_id = intval($identifier);
            $user = get_userdata($user_id);
            if ($user) {
                $email = $user->user_email;
            }
        } elseif (is_email($identifier)) {
            $email = sanitize_email($identifier);
            $user = get_user_by('email', $email);
            if ($user) {
                $user_id = $user->ID;
            } else {
                $is_guest_match = true;
            }
        }

        $stats_table = $wpdb->prefix . 'wc_order_stats';
        $lookup_table = $wpdb->prefix . 'wc_order_product_lookup';

        // Orders summary & LTV
        $summary = array(
            'total_orders'  => 0,
            'ltv'           => 0.00,
            'avg_order'     => 0.00,
            'first_order'   => null,
            'last_order'    => null,
        );

        if ($user_id > 0) {
            $sql = $wpdb->prepare(
                "SELECT COUNT(*) as count, SUM(net_total) as ltv, AVG(net_total) as avg_val, MIN(date_created) as first_date, MAX(date_created) as last_date 
                 FROM {$stats_table} WHERE customer_id = %d AND status IN ('wc-completed', 'wc-processing')",
                $user_id
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT COUNT(*) as count, SUM(net_total) as ltv, AVG(net_total) as avg_val, MIN(date_created) as first_date, MAX(date_created) as last_date 
                 FROM {$stats_table} WHERE status IN ('wc-completed', 'wc-processing') AND order_id IN (
                    SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_billing_email' AND meta_value = %s
                 )",
                $email
            );
        }

        $res = $wpdb->get_row($sql);
        if ($res && $res->count > 0) {
            $summary['total_orders'] = intval($res->count);
            $summary['ltv']          = floatval($res->ltv);
            $summary['avg_order']    = floatval($res->avg_val);
            $summary['first_order']  = $res->first_date;
            $summary['last_order']   = $res->last_date;
        }

        // Recent Orders List
        $orders_list = array();
        if ($user_id > 0) {
            $wc_orders = wc_get_orders(array(
                'customer_id' => $user_id,
                'limit'       => 20,
                'orderby'     => 'date',
                'order'       => 'DESC'
            ));
        } else {
            $wc_orders = wc_get_orders(array(
                'billing_email' => $email,
                'limit'         => 20,
                'orderby'       => 'date',
                'order'         => 'DESC'
            ));
        }

        foreach ($wc_orders as $o) {
            $items = array();
            foreach ($o->get_items() as $item) {
                $items[] = array(
                    'name'     => $item->get_name(),
                    'qty'      => $item->get_quantity(),
                    'total'    => $item->get_total(),
                );
            }
            $orders_list[] = array(
                'id'         => $o->get_id(),
                'status'     => $o->get_status(),
                'total'      => $o->get_total(),
                'date'       => $o->get_date_created() ? $o->get_date_created()->date('Y-m-d H:i:s') : '',
                'items'      => $items,
            );
        }

        // Top products purchased
        $top_products = array();
        if ($user_id > 0) {
            $sql_prod = $wpdb->prepare(
                "SELECT product_id, SUM(product_qty) as total_qty, SUM(product_net_revenue) as total_rev 
                 FROM {$lookup_table} WHERE customer_id = %d GROUP BY product_id ORDER BY total_qty DESC LIMIT 5",
                $user_id
            );
            $top_products = $wpdb->get_results($sql_prod);
        }

        // Campaign history for customer
        $campaign_table = $wpdb->prefix . 'crm_campaign_log';
        $campaign_history = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$campaign_table} WHERE email = %s ORDER BY sent_at DESC LIMIT 20",
            $email
        ));

        // Segment calculation
        $current_segment = self::recalculate_customer_segment($email ? $email : $user_id);

        return array(
            'user_id'          => $user_id,
            'email'            => $email,
            'is_guest_match'   => $is_guest_match,
            'segment'          => $current_segment,
            'summary'          => $summary,
            'orders'           => $orders_list,
            'top_products'     => $top_products,
            'campaign_history' => $campaign_history,
        );
    }

    /**
     * Return counts for all segments (New, Returning, Active).
     */
    public static function get_segment_counts() {
        global $wpdb;
        $stats_table = $wpdb->prefix . 'wc_order_stats';
        $settings = get_option('woo_crm_settings', array());
        $active_days = isset($settings['active_segment_days']) ? intval($settings['active_segment_days']) : 30;

        $cutoff_date = date('Y-m-d H:i:s', current_time('timestamp') - ($active_days * DAY_IN_SECONDS));

        // Active customers: ordered within active_days
        $active_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT customer_id) FROM {$stats_table} WHERE status IN ('wc-completed', 'wc-processing') AND date_created >= %s",
            $cutoff_date
        ));

        // Total unique customers with completed orders
        $total_customers = $wpdb->get_var(
            "SELECT COUNT(DISTINCT customer_id) FROM {$stats_table} WHERE status IN ('wc-completed', 'wc-processing')"
        );

        // Returning customers: total unique with >1 order
        $returning_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM (
                SELECT customer_id FROM {$stats_table} WHERE status IN ('wc-completed', 'wc-processing') GROUP BY customer_id HAVING COUNT(order_id) >= 1
            ) as t"
        );

        // Registered users with 0 orders = New
        $user_count = count_users();
        $total_users = isset($user_count['total_users']) ? $user_count['total_users'] : 0;
        $new_count = max(0, $total_users - intval($total_customers));

        return array(
            'new'       => intval($new_count),
            'returning' => intval($returning_count),
            'active'    => intval($active_count),
        );
    }
}
