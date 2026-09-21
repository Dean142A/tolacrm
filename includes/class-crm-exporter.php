<?php
/**
 * Woo_CRM_Exporter
 *
 * CSV Data Exporter for Abandoned Carts and Customer Records.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_CRM_Exporter {

    /**
     * Generate CSV export for Tracked Carts.
     */
    public static function export_carts_csv() {
        Woo_CRM_Security::check_capability();

        global $wpdb;
        $table_name = $wpdb->prefix . 'crm_carts';
        $results = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY updated_at DESC");

        $filename = 'woo_crm_carts_' . date('Y-m-d_H-i') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');

        // CSV Headers
        fputcsv($output, array('ID', 'User ID', 'Email', 'Cart Total', 'Stage', 'Notified At', 'Converted At', 'Created At', 'Last Updated', 'Items Preview'));

        foreach ($results as $row) {
            $items = json_decode($row->cart_contents, true);
            $item_str = array();
            if (is_array($items)) {
                foreach ($items as $it) {
                    $item_str[] = $it['name'] . ' (x' . $it['quantity'] . ')';
                }
            }

            fputcsv($output, array(
                $row->id,
                $row->user_id ? $row->user_id : 'Guest',
                $row->email,
                $row->cart_total,
                $row->notification_stage,
                $row->notified_at ? $row->notified_at : 'Never',
                $row->converted_at ? $row->converted_at : 'No',
                $row->created_at,
                $row->updated_at,
                implode('; ', $item_str)
            ));
        }

        fclose($output);
        exit;
    }

    /**
     * Generate CSV export for Customer Records.
     */
    public static function export_customers_csv() {
        Woo_CRM_Security::check_capability();

        global $wpdb;
        $stats_table = $wpdb->prefix . 'wc_order_stats';

        $sql = "SELECT customer_id, 
                       (SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_billing_email' AND post_id = MAX(order_id) LIMIT 1) as billing_email,
                       COUNT(order_id) as total_orders, 
                       SUM(net_total) as ltv, 
                       MAX(date_created) as last_order_date 
                FROM {$stats_table} 
                WHERE status IN ('wc-completed', 'wc-processing') 
                GROUP BY customer_id 
                ORDER BY ltv DESC";

        $customers = $wpdb->get_results($sql);

        $filename = 'woo_crm_customers_' . date('Y-m-d_H-i') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');

        fputcsv($output, array('Customer ID', 'Display Name / Email', 'Email', 'Segment', 'Tags', 'Total Orders', 'Lifetime Value (LTV)', 'Last Purchase Date'));

        foreach ($customers as $c) {
            $user = get_userdata($c->customer_id);
            $email = $user ? $user->user_email : $c->billing_email;
            $name  = $user ? $user->display_name : 'Guest Customer';
            $segment = Woo_CRM_Customers::recalculate_customer_segment($c->customer_id ? $c->customer_id : $email);
            $tags = Woo_CRM_Tags::get_tags($c->customer_id ? $c->customer_id : $email);

            fputcsv($output, array(
                $c->customer_id ? $c->customer_id : 'Guest',
                $name,
                $email,
                ucfirst($segment),
                implode(', ', $tags),
                $c->total_orders,
                $c->ltv,
                $c->last_order_date
            ));
        }

        fclose($output);
        exit;
    }
}
