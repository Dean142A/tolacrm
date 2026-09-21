<?php
/**
 * Woo_CRM_Tags
 *
 * Custom Contact Tagging and Taxonomy system for WooCommerce CRM.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_CRM_Tags {

    /**
     * Get default predefined system tags.
     */
    public static function get_system_tags() {
        return array(
            'VIP'             => array('label' => __('VIP Customer', 'woo-crm'), 'color' => '#8b5cf6'),
            'High-Spender'    => array('label' => __('High Spender ($500+)', 'woo-crm'), 'color' => '#10b981'),
            'At-Risk'         => array('label' => __('At Risk (>60d inactive)', 'woo-crm'), 'color' => '#ef4444'),
            'Recovered-Cart'  => array('label' => __('Recovered Cart', 'woo-crm'), 'color' => '#3b82f6'),
            'Frequent-Buyer'  => array('label' => __('Frequent Buyer (3+ orders)', 'woo-crm'), 'color' => '#f59e0b'),
        );
    }

    /**
     * Get tags for a customer (by user_id or email).
     */
    public static function get_tags($identifier) {
        $user_id = 0;
        $email = '';

        if (is_numeric($identifier) && $identifier > 0) {
            $user_id = intval($identifier);
        } elseif (is_email($identifier)) {
            $email = sanitize_email($identifier);
            $user = get_user_by('email', $email);
            if ($user) {
                $user_id = $user->ID;
            }
        }

        if ($user_id > 0) {
            $tags = get_user_meta($user_id, '_crm_contact_tags', true);
            return is_array($tags) ? array_unique($tags) : array();
        } elseif (!empty($email)) {
            $guest_tags = get_option('woo_crm_guest_tags', array());
            return isset($guest_tags[$email]) && is_array($guest_tags[$email]) ? $guest_tags[$email] : array();
        }

        return array();
    }

    /**
     * Add tag to customer.
     */
    public static function add_tag($identifier, $tag_name) {
        $tag_name = sanitize_text_field($tag_name);
        if (empty($tag_name)) {
            return false;
        }

        $existing = self::get_tags($identifier);
        if (!in_array($tag_name, $existing, true)) {
            $existing[] = $tag_name;
            self::save_tags($identifier, $existing);
        }
        return true;
    }

    /**
     * Remove tag from customer.
     */
    public static function remove_tag($identifier, $tag_name) {
        $existing = self::get_tags($identifier);
        $updated = array_values(array_diff($existing, array($tag_name)));
        self::save_tags($identifier, $updated);
        return true;
    }

    /**
     * Persist tags array.
     */
    private static function save_tags($identifier, $tags_array) {
        $tags_array = array_values(array_unique(array_map('sanitize_text_field', $tags_array)));

        $user_id = 0;
        $email = '';

        if (is_numeric($identifier) && $identifier > 0) {
            $user_id = intval($identifier);
        } elseif (is_email($identifier)) {
            $email = sanitize_email($identifier);
            $user = get_user_by('email', $email);
            if ($user) {
                $user_id = $user->ID;
            }
        }

        if ($user_id > 0) {
            update_user_meta($user_id, '_crm_contact_tags', $tags_array);
        } elseif (!empty($email)) {
            $guest_tags = get_option('woo_crm_guest_tags', array());
            $guest_tags[$email] = $tags_array;
            update_option('woo_crm_guest_tags', $guest_tags);
        }
    }
}
