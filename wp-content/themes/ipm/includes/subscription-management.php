<?php
/**
 * IIPM Subscription Management
 * 
 * Handles subscription orders, status checking, and management
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create subscription orders table
 */
function iipm_create_subscription_table() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $table_subscriptions = $wpdb->prefix . 'test_iipm_subscription_orders';
    $sql_subscriptions = "CREATE TABLE $table_subscriptions (
        id int(11) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        start_date timestamp DEFAULT CURRENT_TIMESTAMP,
        end_date timestamp NULL,
        status tinyint(1) DEFAULT 0 COMMENT '1=paid, 0=unpaid',
        paid_date timestamp NULL,
        membership_id int(11) NOT NULL,
        amount decimal(10,2) NOT NULL,
        stripe_payment_intent_id varchar(255) NULL,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY membership_id (membership_id),
        KEY status (status),
        KEY start_date (start_date),
        KEY end_date (end_date)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_subscriptions);
}

/**
 * Create a new subscription order for a user
 */
function iipm_create_subscription_order($user_id, $membership_id, $amount, $stripe_payment_intent_id = null) {
    global $wpdb;
    
    // Get membership details to calculate end date (1 minute for testing)
    $membership = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}memberships WHERE id = %d",
        $membership_id
    ));
    
    if (!$membership) {
        return false;
    }
    
    $start_date = current_time('mysql');
    $end_date = date('Y-m-d H:i:s', strtotime($start_date . ' +1 minute')); // 1 minute for testing
    
    $result = $wpdb->insert(
        $wpdb->prefix . 'test_iipm_subscription_orders',
        array(
            'user_id' => $user_id,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'status' => 0, // Initially unpaid
            'paid_date' => null,
            'membership_id' => $membership_id,
            'amount' => $amount,
            'stripe_payment_intent_id' => $stripe_payment_intent_id
        ),
        array('%d', '%s', '%s', '%d', '%s', '%d', '%f', '%s')
    );
    
    if ($result) {
        $subscription_id = $wpdb->insert_id;
        
        // Log the subscription creation
        iipm_log_user_activity($user_id, 'subscription_created', "Subscription order created with ID: $subscription_id");
        
        return $subscription_id;
    }
    
    return false;
}

/**
 * Mark subscription as paid
 */
function iipm_mark_subscription_paid($subscription_id, $stripe_payment_intent_id = null) {
    global $wpdb;
    
    $result = $wpdb->update(
        $wpdb->prefix . 'test_iipm_subscription_orders',
        array(
            'status' => 1,
            'paid_date' => current_time('mysql'),
            'stripe_payment_intent_id' => $stripe_payment_intent_id
        ),
        array('id' => $subscription_id),
        array('%d', '%s', '%s'),
        array('%d')
    );
    
    if ($result) {
        // Get subscription details for logging
        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}test_iipm_subscription_orders WHERE id = %d",
            $subscription_id
        ));
        
        if ($subscription) {
            iipm_log_user_activity($subscription->user_id, 'subscription_paid', "Subscription order $subscription_id marked as paid");
        }
    }
    
    return $result !== false;
}

/**
 * Check and update membership status based on subscription
 */
function iipm_check_subscription_status($user_id) {
    global $wpdb;
    
    // Get the latest subscription for the user
    $subscription = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}test_iipm_subscription_orders 
         WHERE user_id = %d 
         ORDER BY created_at DESC 
         LIMIT 1",
        $user_id
    ));
    
    if (!$subscription) {
        // No subscription found, set status to inactive
        iipm_update_membership_status($user_id, 'inactive');
        return 'inactive';
    }
    
    $current_time = current_time('mysql');
    
    // Check if subscription is paid
    if ($subscription->status == 0) {
        // Unpaid subscription
        iipm_update_membership_status($user_id, 'pending');
        return 'pending';
    }
    
    // Check if subscription has expired
    if (strtotime($current_time) > strtotime($subscription->end_date)) {
        // Subscription expired
        iipm_update_membership_status($user_id, 'lapsed');
        return 'lapsed';
    }
    
    // Subscription is active
    iipm_update_membership_status($user_id, 'active');
    return 'active';
}

/**
 * Update membership status in the members table
 */
function iipm_update_membership_status($user_id, $status) {
    global $wpdb;
    
    $result = $wpdb->update(
        $wpdb->prefix . 'test_iipm_members',
        array('membership_status' => $status),
        array('user_id' => $user_id),
        array('%s'),
        array('%d')
    );
    
    if ($result !== false) {
        iipm_log_user_activity($user_id, 'membership_status_updated', "Membership status updated to: $status");
    }
    
    return $result !== false;
}

/**
 * Get user's subscription details
 */
function iipm_get_user_subscription($user_id) {
    global $wpdb;
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT s.*, m.name as membership_name, m.fee, m.cpd_requirement 
         FROM {$wpdb->prefix}test_iipm_subscription_orders s
         LEFT JOIN {$wpdb->prefix}memberships m ON s.membership_id = m.id
         WHERE s.user_id = %d 
         ORDER BY s.created_at DESC 
         LIMIT 1",
        $user_id
    ));
}

/**
 * Get all subscriptions for admin management
 */
function iipm_get_all_subscriptions($page = 1, $per_page = 20, $status_filter = '', $search = '') {
    global $wpdb;
    
    $offset = ($page - 1) * $per_page;
    
    $where_conditions = array();
    $where_values = array();
    
    if (!empty($status_filter)) {
        $where_conditions[] = "s.status = %d";
        $where_values[] = $status_filter;
    }
    
    if (!empty($search)) {
        $where_conditions[] = "(u.first_name LIKE %s OR u.last_name LIKE %s OR u.user_email LIKE %s OR m.name LIKE %s)";
        $search_term = '%' . $wpdb->esc_like($search) . '%';
        $where_values[] = $search_term;
        $where_values[] = $search_term;
        $where_values[] = $search_term;
        $where_values[] = $search_term;
    }
    
    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }
    
    $query = "
        SELECT s.*, 
               u.first_name, u.last_name, u.user_email,
               m.name as membership_name, m.fee, m.cpd_requirement
        FROM {$wpdb->prefix}test_iipm_subscription_orders s
        LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
        LEFT JOIN {$wpdb->prefix}memberships m ON s.membership_id = m.id
        $where_clause
        ORDER BY s.created_at DESC
        LIMIT %d OFFSET %d
    ";
    
    $where_values[] = $per_page;
    $where_values[] = $offset;
    
    return $wpdb->get_results($wpdb->prepare($query, $where_values));
}

/**
 * Get subscription count for pagination
 */
function iipm_get_subscription_count($status_filter = '', $search = '') {
    global $wpdb;
    
    $where_conditions = array();
    $where_values = array();
    
    if (!empty($status_filter)) {
        $where_conditions[] = "s.status = %d";
        $where_values[] = $status_filter;
    }
    
    if (!empty($search)) {
        $where_conditions[] = "(u.first_name LIKE %s OR u.last_name LIKE %s OR u.user_email LIKE %s OR m.name LIKE %s)";
        $search_term = '%' . $wpdb->esc_like($search) . '%';
        $where_values[] = $search_term;
        $where_values[] = $search_term;
        $where_values[] = $search_term;
        $where_values[] = $search_term;
    }
    
    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }
    
    $query = "
        SELECT COUNT(*)
        FROM {$wpdb->prefix}test_iipm_subscription_orders s
        LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
        LEFT JOIN {$wpdb->prefix}memberships m ON s.membership_id = m.id
        $where_clause
    ";
    
    return $wpdb->get_var($wpdb->prepare($query, $where_values));
}

/**
 * Check if current user is admin based on user_is_admin field
 */
function iipm_is_user_admin($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    global $wpdb;
    $user_is_admin = $wpdb->get_var($wpdb->prepare(
        "SELECT user_is_admin FROM {$wpdb->prefix}test_iipm_member_profiles WHERE user_id = %d",
        $user_id
    ));
    
    return $user_is_admin == 1;
}

/**
 * AJAX handler to get subscription data for admin
 */
function iipm_get_subscriptions_admin() {
    // Check WordPress admin capabilities first
    if (!current_user_can('manage_iipm_members') && !current_user_can('administrator')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    // Also check IIPM admin status
    if (!iipm_is_user_admin()) {
        wp_send_json_error('Access denied. Admin privileges required.');
        return;
    }
    
    $nonce_valid = wp_verify_nonce($_POST['nonce'], 'iipm_portal_nonce');
    if (!$nonce_valid) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    $page = intval($_POST['page'] ?? 1);
    $per_page = intval($_POST['per_page'] ?? 20);
    $status_filter = sanitize_text_field($_POST['status_filter'] ?? '');
    $search = sanitize_text_field($_POST['search'] ?? '');
    
    $subscriptions = iipm_get_all_subscriptions($page, $per_page, $status_filter, $search);
    $total_count = iipm_get_subscription_count($status_filter, $search);
    
    wp_send_json_success(array(
        'subscriptions' => $subscriptions,
        'total_count' => $total_count,
        'current_page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total_count / $per_page)
    ));
}
add_action('wp_ajax_iipm_get_subscriptions_admin', 'iipm_get_subscriptions_admin');

/**
 * AJAX handler to update subscription status
 */
function iipm_update_subscription_status() {
    // Check WordPress admin capabilities first
    if (!current_user_can('manage_iipm_members') && !current_user_can('administrator')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    // Also check IIPM admin status
    if (!iipm_is_user_admin()) {
        wp_send_json_error('Access denied. Admin privileges required.');
        return;
    }
    
    $nonce_valid = wp_verify_nonce($_POST['nonce'], 'iipm_portal_nonce');
    if (!$nonce_valid) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    $subscription_id = intval($_POST['subscription_id']);
    $status = intval($_POST['status']);
    
    if (!in_array($status, [0, 1])) {
        wp_send_json_error('Invalid status');
        return;
    }
    
    global $wpdb;
    
    $update_data = array('status' => $status);
    if ($status == 1) {
        $update_data['paid_date'] = current_time('mysql');
    }
    
    $result = $wpdb->update(
        $wpdb->prefix . 'test_iipm_subscription_orders',
        $update_data,
        array('id' => $subscription_id),
        array('%d', '%s'),
        array('%d')
    );
    
    if ($result !== false) {
        // Get subscription details for logging
        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}test_iipm_subscription_orders WHERE id = %d",
            $subscription_id
        ));
        
        if ($subscription) {
            $status_text = $status ? 'paid' : 'unpaid';
            iipm_log_user_activity($subscription->user_id, 'subscription_status_updated', "Subscription $subscription_id marked as $status_text");
            
            // Check and update membership status
            iipm_check_subscription_status($subscription->user_id);
        }
        
        wp_send_json_success('Subscription status updated successfully');
    } else {
        wp_send_json_error('Failed to update subscription status');
    }
}
add_action('wp_ajax_iipm_update_subscription_status', 'iipm_update_subscription_status');

/**
 * Initialize subscription table on theme activation
 */
function iipm_init_subscription_table() {
    iipm_create_subscription_table();
}
add_action('after_switch_theme', 'iipm_init_subscription_table');

/**
 * Hook to check subscription status when user visits member portal
 */
function iipm_check_subscription_on_portal_access() {
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        
        // Check subscription status first
        iipm_check_subscription_status($user_id);
        
        // Auto-create subscription order if user has membership level but no active subscription
        iipm_auto_create_subscription_order($user_id);
    }
}
add_action('wp', 'iipm_check_subscription_on_portal_access');

/**
 * Auto-create subscription order for user if they have membership level but no active subscription
 */
function iipm_auto_create_subscription_order($user_id) {
    global $wpdb;
    
    // Get user's current membership level
    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}test_iipm_members WHERE user_id = %d",
        $user_id
    ));
    
    if (!$member || !$member->membership_level) {
        return false;
    }
    
    $membership_level = $member->membership_level;
    
    // Determine membership ID
    if (is_numeric($membership_level)) {
        $membership_id = intval($membership_level);
    } else {
        // Try to find membership by name
        $membership = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}memberships WHERE name = %s LIMIT 1",
            $membership_level
        ));
        
        if (!$membership) {
            return false;
        }
        
        $membership_id = $membership->id;
    }
    
    // Check if user already has any subscription orders (active or expired)
    $existing_orders = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}test_iipm_subscription_orders 
         WHERE user_id = %d AND membership_id = %d",
        $user_id,
        $membership_id
    ));
    
    // If no subscription orders exist, create one automatically
    if ($existing_orders == 0) {
        $membership_info = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}memberships WHERE id = %d",
            $membership_id
        ));
        
        if ($membership_info) {
            $subscription_id = iipm_create_subscription_order(
                $user_id,
                $membership_id,
                $membership_info->fee
            );
            
            if ($subscription_id) {
                iipm_log_user_activity($user_id, 'auto_subscription_created', "Auto-created subscription order $subscription_id based on membership level");
            }
            
            return $subscription_id;
        }
    }
    
    return false;
}

/**
 * Get user's subscription orders for profile display
 */
function iipm_get_user_subscription_orders($user_id, $limit = 10) {
    global $wpdb;
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT s.*, m.name as membership_name, m.fee, m.cpd_requirement 
         FROM {$wpdb->prefix}test_iipm_subscription_orders s
         LEFT JOIN {$wpdb->prefix}memberships m ON s.membership_id = m.id
         WHERE s.user_id = %d 
         ORDER BY s.created_at DESC 
         LIMIT %d",
        $user_id,
        $limit
    ));
}

/**
 * Create subscription order for user based on their current membership
 */
function iipm_create_user_subscription_order($user_id) {
    global $wpdb;
    
    // Get user's current membership level
    $member = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}test_iipm_members WHERE user_id = %d",
        $user_id
    ));
    
    if (!$member || !$member->membership_level) {
        return false;
    }
    
    $membership_level = $member->membership_level;
    
    // If membership level is numeric, it's a membership ID
    if (is_numeric($membership_level)) {
        $membership_id = intval($membership_level);
    } else {
        // If it's a string, try to find the membership by name
        $membership = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}memberships WHERE name = %s LIMIT 1",
            $membership_level
        ));
        
        if (!$membership) {
            return false;
        }
        
        $membership_id = $membership->id;
    }
    
    // Check if user already has an active subscription order
    $existing_order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}test_iipm_subscription_orders 
         WHERE user_id = %d AND membership_id = %d 
         AND status = 0 AND end_date > NOW()
         ORDER BY created_at DESC LIMIT 1",
        $user_id,
        $membership_id
    ));
    
    if ($existing_order) {
        return $existing_order->id; // Return existing order ID
    }
    
    // Get membership details
    $membership_info = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}memberships WHERE id = %d",
        $membership_id
    ));
    
    if (!$membership_info) {
        return false;
    }
    
    // Create new subscription order
    return iipm_create_subscription_order(
        $user_id,
        $membership_id,
        $membership_info->fee
    );
}

/**
 * AJAX handler to get user's subscription orders for profile
 */
function iipm_get_user_subscription_orders_ajax() {
    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in');
        return;
    }
    
    $nonce_valid = wp_verify_nonce($_POST['nonce'], 'iipm_portal_nonce');
    if (!$nonce_valid) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    $user_id = get_current_user_id();
    $subscription_orders = iipm_get_user_subscription_orders($user_id);
    
    wp_send_json_success($subscription_orders);
}
add_action('wp_ajax_iipm_get_user_subscription_orders', 'iipm_get_user_subscription_orders_ajax');

/**
 * AJAX handler to create new subscription order for user
 */
function iipm_create_user_subscription_order_ajax() {
    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in');
        return;
    }
    
    $nonce_valid = wp_verify_nonce($_POST['nonce'], 'iipm_portal_nonce');
    if (!$nonce_valid) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    $user_id = get_current_user_id();
    $subscription_id = iipm_create_user_subscription_order($user_id);
    
    if ($subscription_id) {
        wp_send_json_success(array(
            'subscription_id' => $subscription_id,
            'message' => 'Subscription order created successfully'
        ));
    } else {
        wp_send_json_error('Failed to create subscription order');
    }
}
add_action('wp_ajax_iipm_create_user_subscription_order', 'iipm_create_user_subscription_order_ajax');

/**
 * Include subscription management file in functions.php
 */
function iipm_include_subscription_management() {
    require_once get_template_directory() . '/includes/subscription-management.php';
}
add_action('init', 'iipm_include_subscription_management');
