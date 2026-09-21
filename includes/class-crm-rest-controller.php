<?php
/**
 * Woo_CRM_REST_Controller
 *
 * REST API Endpoint Controller for WooCommerce CRM (/wp-json/woo-crm/v1).
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_CRM_REST_Controller extends WP_REST_Controller {

    protected $namespace = 'woo-crm/v1';

    public function register_routes() {
        // GET /woo-crm/v1/stats
        register_rest_route($this->namespace, '/stats', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_stats'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        // GET /woo-crm/v1/carts
        register_rest_route($this->namespace, '/carts', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_carts'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        // GET /woo-crm/v1/customers
        register_rest_route($this->namespace, '/customers', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_customers'),
            'permission_callback' => array($this, 'check_permissions'),
        ));

        // POST /woo-crm/v1/campaigns/send
        register_rest_route($this->namespace, '/campaigns/send', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array($this, 'send_campaign'),
            'permission_callback' => array($this, 'check_permissions'),
        ));
    }

    public function check_permissions() {
        return current_user_can('manage_woocommerce');
    }

    public function get_stats($request) {
        global $wpdb;
        $stats_table = $wpdb->prefix . 'wc_order_stats';
        $carts_table = $wpdb->prefix . 'crm_carts';

        $month_snapshot = $wpdb->get_row($wpdb->prepare(
            "SELECT SUM(net_total) as total_sales, COUNT(order_id) as total_orders 
             FROM {$stats_table} 
             WHERE status IN ('wc-completed', 'wc-processing') AND date_created >= %s",
            date('Y-m-01 00:00:00')
        ));

        $active_carts = $wpdb->get_var("SELECT COUNT(*) FROM {$carts_table} WHERE converted_at IS NULL");
        $segment_counts = Woo_CRM_Customers::get_segment_counts();

        return new WP_REST_Response(array(
            'monthly_revenue'  => $month_snapshot ? floatval($month_snapshot->total_sales) : 0,
            'monthly_orders'   => $month_snapshot ? intval($month_snapshot->total_orders) : 0,
            'active_carts'     => intval($active_carts),
            'segment_counts'   => $segment_counts,
        ), 200);
    }

    public function get_carts($request) {
        global $wpdb;
        $carts_table = $wpdb->prefix . 'crm_carts';
        $carts = $wpdb->get_results("SELECT * FROM {$carts_table} ORDER BY updated_at DESC LIMIT 50");
        return new WP_REST_Response($carts, 200);
    }

    public function get_customers($request) {
        global $wpdb;
        $stats_table = $wpdb->prefix . 'wc_order_stats';

        $customers = $wpdb->get_results("
            SELECT customer_id, 
                   COUNT(order_id) as total_orders, 
                   SUM(net_total) as ltv, 
                   MAX(date_created) as last_order_date 
            FROM {$stats_table} 
            WHERE status IN ('wc-completed', 'wc-processing') 
            GROUP BY customer_id HAVING total_orders > 0 
            ORDER BY ltv DESC LIMIT 50
        ");

        $data = array();
        foreach ($customers as $c) {
            $user = get_userdata($c->customer_id);
            $email = $user ? $user->user_email : 'Customer #' . $c->customer_id;
            $data[] = array(
                'customer_id'   => $c->customer_id,
                'name'          => $user ? $user->display_name : 'Guest',
                'email'         => $email,
                'segment'       => Woo_CRM_Customers::recalculate_customer_segment($c->customer_id ? $c->customer_id : $email),
                'tags'          => Woo_CRM_Tags::get_tags($c->customer_id ? $c->customer_id : $email),
                'total_orders'  => intval($c->total_orders),
                'ltv'           => floatval($c->ltv),
                'last_purchase' => $c->last_order_date,
            );
        }

        return new WP_REST_Response($data, 200);
    }

    public function send_campaign($request) {
        $params = $request->get_json_params();
        $segment  = isset($params['segment']) ? sanitize_text_field($params['segment']) : 'all';
        $subject  = isset($params['subject']) ? sanitize_text_field($params['subject']) : '';
        $message  = isset($params['message']) ? sanitize_textarea_field($params['message']) : '';
        $discount = isset($params['discount']) ? floatval($params['discount']) : 0;

        if (empty($subject) || empty($message)) {
            return new WP_Error('missing_fields', 'Subject and message are required.', array('status' => 400));
        }

        $count = Woo_CRM_Campaigns::trigger_manual_blast($segment, $subject, $message, $discount);

        return new WP_REST_Response(array('message' => sprintf('Campaign sent to %d recipients.', $count)), 200);
    }
}
