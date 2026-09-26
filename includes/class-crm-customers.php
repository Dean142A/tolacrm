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
            $where_clause = "WHERE customer_id = %d AND status IN ('completed', 'processing', 'wc-completed', 'wc-processing')";
            $params[] = $user_id;
        } else {
            // Match guest by billing email in order stats or postmeta
            $where_clause = "WHERE status IN ('completed', 'processing', 'wc-completed', 'wc-processing') AND order_id IN (
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
     * Get aggregated customer records (registered users + guests) with segment filtering.
     */
    /**
     * Get aggregated customer records (registered users + guests) with segment, search, and tag filtering.
     */
    public static function get_all_customers($segment = 'all', $limit = 100, $search = '', $tag_filter = '') {
        global $wpdb;

        $customers_map = array();

        // 1. Fetch Registered Users with order history or segments
        $registered_users = get_users(array(
            'number'  => 200,
            'orderby' => 'registered',
            'order'   => 'DESC',
        ));

        foreach ($registered_users as $u) {
            $user_id = $u->ID;
            $email   = $u->user_email;
            $name    = $u->display_name ? $u->display_name : $email;

            $customers_map['user_' . $user_id] = array(
                'customer_id'     => $user_id,
                'email'           => $email,
                'name'            => $name,
                'is_guest'        => false,
                'total_orders'    => 0,
                'ltv'             => 0.00,
                'last_order_date' => null,
                'segment'         => 'new',
                'tags'            => Woo_CRM_Tags::get_tags($user_id),
            );
        }

        // 2. Fetch order metrics from WooCommerce orders
        $valid_statuses = array('completed', 'processing', 'wc-completed', 'wc-processing');
        $status_in = "'" . implode("','", $valid_statuses) . "'";

        $stats_table = $wpdb->prefix . 'wc_order_stats';
        $stats_available = ($wpdb->get_var("SHOW TABLES LIKE '{$stats_table}'") === $stats_table);

        if ($stats_available) {
            $user_stats = $wpdb->get_results(
                "SELECT customer_id, COUNT(order_id) as total_orders, SUM(COALESCE(net_total, total_sales, 0)) as ltv, MAX(date_created) as last_date 
                 FROM {$stats_table} 
                 WHERE status IN ({$status_in}) AND customer_id > 0 
                 GROUP BY customer_id"
            );

            foreach ($user_stats as $us) {
                $key = 'user_' . $us->customer_id;
                if (!isset($customers_map[$key])) {
                    $u = get_userdata($us->customer_id);
                    if ($u) {
                        $customers_map[$key] = array(
                            'customer_id'     => $us->customer_id,
                            'email'           => $u->user_email,
                            'name'            => $u->display_name,
                            'is_guest'        => false,
                            'total_orders'    => 0,
                            'ltv'             => 0.00,
                            'last_order_date' => null,
                            'segment'         => 'new',
                            'tags'            => Woo_CRM_Tags::get_tags($us->customer_id),
                        );
                    }
                }
                if (isset($customers_map[$key])) {
                    $customers_map[$key]['total_orders']    = intval($us->total_orders);
                    $customers_map[$key]['ltv']             = floatval($us->ltv);
                    $customers_map[$key]['last_order_date'] = $us->last_date;
                }
            }

            $guest_stats = $wpdb->get_results(
                "SELECT pm.meta_value as billing_email, COUNT(s.order_id) as total_orders, SUM(COALESCE(s.net_total, s.total_sales, 0)) as ltv, MAX(s.date_created) as last_date 
                 FROM {$stats_table} s 
                 INNER JOIN {$wpdb->postmeta} pm ON s.order_id = pm.post_id AND pm.meta_key = '_billing_email' 
                 WHERE s.status IN ({$status_in}) AND (s.customer_id IS NULL OR s.customer_id = 0) 
                 GROUP BY pm.meta_value"
            );

            foreach ($guest_stats as $gs) {
                if (is_email($gs->billing_email)) {
                    $g_email = strtolower(trim($gs->billing_email));
                    $key = 'guest_' . md5($g_email);
                    if (!isset($customers_map[$key])) {
                        $customers_map[$key] = array(
                            'customer_id'     => 0,
                            'email'           => $g_email,
                            'name'            => __('Guest Customer', 'woo-crm'),
                            'is_guest'        => true,
                            'total_orders'    => intval($gs->total_orders),
                            'ltv'             => floatval($gs->ltv),
                            'last_order_date' => $gs->last_date,
                            'segment'         => 'new',
                            'tags'            => Woo_CRM_Tags::get_tags($g_email),
                        );
                    }
                }
            }
        }

        // Fallback/Supplement using wc_get_orders
        $recent_orders = wc_get_orders(array(
            'limit'   => 150,
            'status'  => array('completed', 'processing'),
            'orderby' => 'date',
            'order'   => 'DESC',
        ));

        foreach ($recent_orders as $o) {
            $user_id = $o->get_user_id();
            $email   = strtolower(trim($o->get_billing_email()));
            $total   = floatval($o->get_total());
            $date    = $o->get_date_created() ? $o->get_date_created()->date('Y-m-d H:i:s') : '';

            if ($user_id > 0) {
                $key = 'user_' . $user_id;
                if (!isset($customers_map[$key])) {
                    $u = get_userdata($user_id);
                    $customers_map[$key] = array(
                        'customer_id'     => $user_id,
                        'email'           => $email ? $email : ($u ? $u->user_email : ''),
                        'name'            => $u ? $u->display_name : $o->get_formatted_billing_full_name(),
                        'is_guest'        => false,
                        'total_orders'    => 0,
                        'ltv'             => 0.00,
                        'last_order_date' => $date,
                        'segment'         => 'new',
                        'tags'            => Woo_CRM_Tags::get_tags($user_id),
                    );
                }
                if ($customers_map[$key]['total_orders'] == 0) {
                    $customers_map[$key]['total_orders']    = 1;
                    $customers_map[$key]['ltv']             = $total;
                    $customers_map[$key]['last_order_date'] = $date;
                }
            } elseif (is_email($email)) {
                $key = 'guest_' . md5($email);
                if (!isset($customers_map[$key])) {
                    $customers_map[$key] = array(
                        'customer_id'     => 0,
                        'email'           => $email,
                        'name'            => $o->get_formatted_billing_full_name() ? $o->get_formatted_billing_full_name() : __('Guest Customer', 'woo-crm'),
                        'is_guest'        => true,
                        'total_orders'    => 1,
                        'ltv'             => $total,
                        'last_order_date' => $date,
                        'segment'         => 'new',
                        'tags'            => Woo_CRM_Tags::get_tags($email),
                    );
                }
            }
        }

        // Calculate segment for each customer and filter
        $result = array();
        $search_lower = strtolower(trim($search));

        foreach ($customers_map as $c) {
            $identifier = $c['customer_id'] > 0 ? $c['customer_id'] : $c['email'];
            if (empty($identifier)) {
                continue;
            }

            $c['segment'] = self::recalculate_customer_segment($identifier);

            if ($segment !== 'all' && $c['segment'] !== $segment) {
                continue;
            }

            if (!empty($tag_filter) && !in_array($tag_filter, $c['tags'], true)) {
                continue;
            }

            if (!empty($search_lower)) {
                $match_email = strpos(strtolower($c['email']), $search_lower) !== false;
                $match_name  = strpos(strtolower($c['name']), $search_lower) !== false;
                $match_id    = (string) $c['customer_id'] === $search_lower;
                if (!$match_email && !$match_name && !$match_id) {
                    continue;
                }
            }

            $result[] = $c;
        }

        // Sort by LTV descending
        usort($result, function($a, $b) {
            if ($a['ltv'] == $b['ltv']) {
                return $b['total_orders'] - $a['total_orders'];
            }
            return ($a['ltv'] < $b['ltv']) ? 1 : -1;
        });

        return array_slice($result, 0, $limit);
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

        $summary = array(
            'total_orders'  => 0,
            'ltv'           => 0.00,
            'avg_order'     => 0.00,
            'first_order'   => null,
            'last_order'    => null,
        );

        $valid_statuses = array('completed', 'processing', 'wc-completed', 'wc-processing');
        $status_in = "'" . implode("','", $valid_statuses) . "'";

        if ($user_id > 0) {
            $sql = $wpdb->prepare(
                "SELECT COUNT(*) as count, SUM(COALESCE(net_total, total_sales, 0)) as ltv, AVG(COALESCE(net_total, total_sales, 0)) as avg_val, MIN(date_created) as first_date, MAX(date_created) as last_date 
                 FROM {$stats_table} WHERE customer_id = %d AND status IN ({$status_in})",
                $user_id
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT COUNT(*) as count, SUM(COALESCE(net_total, total_sales, 0)) as ltv, AVG(COALESCE(net_total, total_sales, 0)) as avg_val, MIN(date_created) as first_date, MAX(date_created) as last_date 
                 FROM {$stats_table} WHERE status IN ({$status_in}) AND order_id IN (
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
        } else {
            // Fallback via wc_get_orders
            $query_args = array(
                'limit'   => -1,
                'status'  => array('completed', 'processing'),
                'return'  => 'ids',
            );
            if ($user_id > 0) {
                $query_args['customer_id'] = $user_id;
            } elseif (!empty($email)) {
                $query_args['billing_email'] = $email;
            }
            $order_ids = wc_get_orders($query_args);
            if (!empty($order_ids)) {
                $tot_ltv = 0;
                foreach ($order_ids as $oid) {
                    $o = wc_get_order($oid);
                    if ($o) {
                        $tot_ltv += floatval($o->get_total());
                    }
                }
                $cnt = count($order_ids);
                $summary['total_orders'] = $cnt;
                $summary['ltv']          = $tot_ltv;
                $summary['avg_order']    = $cnt > 0 ? ($tot_ltv / $cnt) : 0;
            }
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
        $valid_statuses = array('completed', 'processing', 'wc-completed', 'wc-processing');
        $status_in = "'" . implode("','", $valid_statuses) . "'";

        // Active customers: ordered within active_days
        $active_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT customer_id) FROM {$stats_table} WHERE status IN ({$status_in}) AND date_created >= %s",
            $cutoff_date
        ));

        // Total unique customers with completed orders
        $total_customers = $wpdb->get_var(
            "SELECT COUNT(DISTINCT customer_id) FROM {$stats_table} WHERE status IN ({$status_in})"
        );

        // Returning customers: total unique with >1 order
        $returning_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM (
                SELECT customer_id FROM {$stats_table} WHERE status IN ({$status_in}) GROUP BY customer_id HAVING COUNT(order_id) >= 1
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
