<?php
/**
 * Qualification Management Handler
 * 
 * @package IPM
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get all qualifications for a user
 */
function iipm_get_user_qualifications($user_id) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'test_iipm_qualifications';
    
    $qualifications = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_name} 
         WHERE user_id = %d 
         ORDER BY date_attained DESC, id DESC",
        $user_id
    ));
    
    return $qualifications ?: array();
}

/**
 * Get a single qualification by ID
 */
function iipm_get_qualification($qualification_id) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'test_iipm_qualifications';
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d",
        $qualification_id
    ));
}

/**
 * Add a new qualification
 */
function iipm_add_qualification($user_id, $designation, $institute, $date_attained_txt, $is_current_designation = 0) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'test_iipm_qualifications';
    
    // Validate date format (yyyy-mm-dd)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_attained_txt)) {
        return array('success' => false, 'message' => 'Invalid date format. Use YYYY-MM-DD format.');
    }
    
    // Convert date_attained_txt to date_attained (datetime)
    $date_attained = $date_attained_txt;
    
    $result = $wpdb->insert(
        $table_name,
        array(
            'user_id' => $user_id,
            'designation' => sanitize_text_field($designation),
            'date_attained' => $date_attained,
            'institute' => sanitize_text_field($institute),
            'isCurrentDesignation' => intval($is_current_designation),
            'date_attained_txt' => sanitize_text_field($date_attained_txt),
            'updateTimestamp' => current_time('mysql')
        ),
        array('%d', '%s', '%s', '%s', '%d', '%s', '%s')
    );
    
    if ($result !== false) {
        return array('success' => true, 'message' => 'Qualification added successfully', 'id' => $wpdb->insert_id);
    } else {
        return array('success' => false, 'message' => 'Failed to add qualification: ' . $wpdb->last_error);
    }
}

/**
 * Update an existing qualification
 */
function iipm_update_qualification($qualification_id, $designation, $institute, $date_attained_txt, $is_current_designation = 0) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'test_iipm_qualifications';
    
    // Validate date format (yyyy-mm-dd)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_attained_txt)) {
        return array('success' => false, 'message' => 'Invalid date format. Use YYYY-MM-DD format.');
    }
    
    // Convert date_attained_txt to date_attained (datetime)
    $date_attained = $date_attained_txt;
    
    $result = $wpdb->update(
        $table_name,
        array(
            'designation' => sanitize_text_field($designation),
            'date_attained' => $date_attained,
            'institute' => sanitize_text_field($institute),
            'isCurrentDesignation' => intval($is_current_designation),
            'date_attained_txt' => sanitize_text_field($date_attained_txt),
            'updateTimestamp' => current_time('mysql')
        ),
        array('id' => $qualification_id),
        array('%s', '%s', '%s', '%d', '%s', '%s'),
        array('%d')
    );
    
    if ($result !== false) {
        return array('success' => true, 'message' => 'Qualification updated successfully');
    } else {
        return array('success' => false, 'message' => 'Failed to update qualification: ' . $wpdb->last_error);
    }
}

/**
 * Delete a qualification
 */
function iipm_delete_qualification($qualification_id) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'test_iipm_qualifications';
    
    $result = $wpdb->delete(
        $table_name,
        array('id' => $qualification_id),
        array('%d')
    );
    
    if ($result !== false) {
        return array('success' => true, 'message' => 'Qualification deleted successfully');
    } else {
        return array('success' => false, 'message' => 'Failed to delete qualification: ' . $wpdb->last_error);
    }
}

/**
 * AJAX handler for getting user qualifications
 */
function iipm_ajax_get_user_qualifications() {
    if (!wp_verify_nonce($_POST['nonce'], 'iipm_portal_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    $user_id = intval($_POST['user_id'] ?? 0);
    if (!$user_id) {
        wp_send_json_error('User ID is required');
        return;
    }
    
    $qualifications = iipm_get_user_qualifications($user_id);
    
    wp_send_json_success(array(
        'qualifications' => $qualifications,
        'user_id' => $user_id
    ));
}
add_action('wp_ajax_iipm_get_user_qualifications', 'iipm_ajax_get_user_qualifications');

/**
 * AJAX handler for adding a qualification
 */
function iipm_ajax_add_qualification() {
    if (!wp_verify_nonce($_POST['nonce'], 'iipm_portal_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    $user_id = intval($_POST['user_id'] ?? 0);
    $designation = sanitize_text_field($_POST['designation'] ?? '');
    $institute = sanitize_text_field($_POST['institute'] ?? '');
    $date_attained_txt = sanitize_text_field($_POST['date_attained_txt'] ?? '');
    $is_current_designation = intval($_POST['is_current_designation'] ?? 0);
    
    if (empty($designation) || empty($institute) || empty($date_attained_txt)) {
        wp_send_json_error('All fields are required');
        return;
    }
    
    $result = iipm_add_qualification($user_id, $designation, $institute, $date_attained_txt, $is_current_designation);
    
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result['message']);
    }
}
add_action('wp_ajax_iipm_add_qualification', 'iipm_ajax_add_qualification');

/**
 * AJAX handler for updating a qualification
 */
function iipm_ajax_update_qualification() {
    if (!wp_verify_nonce($_POST['nonce'], 'iipm_portal_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    $qualification_id = intval($_POST['qualification_id'] ?? 0);
    $designation = sanitize_text_field($_POST['designation'] ?? '');
    $institute = sanitize_text_field($_POST['institute'] ?? '');
    $date_attained_txt = sanitize_text_field($_POST['date_attained_txt'] ?? '');
    $is_current_designation = intval($_POST['is_current_designation'] ?? 0);
    
    if (!$qualification_id || empty($designation) || empty($institute) || empty($date_attained_txt)) {
        wp_send_json_error('All fields are required');
        return;
    }
    
    $result = iipm_update_qualification($qualification_id, $designation, $institute, $date_attained_txt, $is_current_designation);
    
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result['message']);
    }
}
add_action('wp_ajax_iipm_update_qualification', 'iipm_ajax_update_qualification');

/**
 * AJAX handler for deleting a qualification
 */
function iipm_ajax_delete_qualification() {
    if (!wp_verify_nonce($_POST['nonce'], 'iipm_portal_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    $qualification_id = intval($_POST['qualification_id'] ?? 0);
    
    if (!$qualification_id) {
        wp_send_json_error('Qualification ID is required');
        return;
    }
    
    $result = iipm_delete_qualification($qualification_id);
    
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result['message']);
    }
}
add_action('wp_ajax_iipm_delete_qualification', 'iipm_ajax_delete_qualification');
?>
