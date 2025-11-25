<?php
/**
 * Organisation Management AJAX Handlers
 */

// Save Organisation (Create/Update)
function iipm_save_organisation() {
    // Check permissions
    if (!current_user_can('manage_iipm_members') && !current_user_can('administrator')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'iipm_org_management_nonce')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    global $wpdb;
    
    $org_id = isset($_POST['org_id']) ? intval($_POST['org_id']) : 0;
    $name = sanitize_text_field($_POST['name']);
    $contact_email = sanitize_email($_POST['contact_email']);
    $contact_phone = sanitize_text_field($_POST['contact_phone']);
    $billing_contact = sanitize_text_field($_POST['billing_contact']);
    $address_line1 = sanitize_text_field($_POST['address_line1']);
    $address_line2 = sanitize_text_field($_POST['address_line2']);
    $city = sanitize_text_field($_POST['city']);
    $county = sanitize_text_field($_POST['county']);
    $eircode = sanitize_text_field($_POST['eircode']);
    $admin_name = isset($_POST['admin_name']) ? sanitize_text_field($_POST['admin_name']) : '';
    $admin_email = isset($_POST['admin_email']) ? sanitize_email($_POST['admin_email']) : '';
    $send_invitation = isset($_POST['send_invitation']);
    
    // Validate required fields
    if (empty($name) || empty($contact_email)) {
        wp_send_json_error('Organisation name and contact email are required');
        return;
    }
    
    if (!is_email($contact_email)) {
        wp_send_json_error('Invalid contact email address');
        return;
    }
    
    // Validate admin email if provided
    if ($admin_email && !is_email($admin_email)) {
        wp_send_json_error('Invalid admin email address');
        return;
    }
    
    // If admin email is provided, admin name is required
    if ($admin_email && empty($admin_name)) {
        wp_send_json_error('Administrator name is required when providing admin email');
        return;
    }
    
    // Check for duplicate organisation name (case-insensitive)
    $existing_org = $wpdb->get_row($wpdb->prepare(
        "SELECT id, name FROM {$wpdb->prefix}test_iipm_organisations 
         WHERE LOWER(name) = LOWER(%s) AND is_active = 1" . ($org_id ? " AND id != %d" : ""),
        $name,
        $org_id
    ));
    
    if ($existing_org) {
        wp_send_json_error('An organisation with this name already exists: "' . $existing_org->name . '". Please choose a different name.');
        return;
    }
    
    $data = array(
        'name' => $name,
        'contact_email' => $contact_email,
        'contact_phone' => $contact_phone,
        'billing_contact' => $billing_contact,
        'address_line1' => $address_line1,
        'address_line2' => $address_line2,
        'city' => $city,
        'county' => $county,
        'eircode' => $eircode,
        'updated_at' => current_time('mysql')
    );
    
    $formats = array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s');
    
    // Add admin fields if provided
    if ($admin_email && $admin_name) {
        $data['admin_name'] = $admin_name;
        $data['admin_email'] = $admin_email;
        $formats[] = '%s';
        $formats[] = '%s';
    }
    
    if ($org_id > 0) {
        // Update existing organisation
        $result = $wpdb->update(
            $wpdb->prefix . 'test_iipm_organisations',
            $data,
            array('id' => $org_id),
            $formats,
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error('Failed to update organisation: ' . $wpdb->last_error);
            return;
        }
        
        $action = 'updated';
    } else {
        // Create new organisation
        $data['created_at'] = current_time('mysql');
        $formats[] = '%s';
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'test_iipm_organisations',
            $data,
            $formats
        );
        
        if ($result === false) {
            wp_send_json_error('Failed to create organisation: ' . $wpdb->last_error);
            return;
        }
        
        $org_id = $wpdb->insert_id;
        $action = 'created';
    }
    
    // Check if admin email was changed
    $admin_email_changed = isset($_POST['admin_email_changed']);
    $old_admin_email = isset($_POST['old_admin_email']) ? sanitize_email($_POST['old_admin_email']) : '';
    
    // Send admin invitation if email and name provided
    if ($admin_email && $admin_name && ($action === 'created' || $send_invitation)) {
        $invitation_result = iipm_send_organisation_admin_invitation($admin_email, $org_id, $admin_name);
        if (!$invitation_result['success']) {
            // Log the error but don't fail the organisation creation
            error_log('IIPM: Failed to send admin invitation: ' . $invitation_result['error']);
        }
    }
    
    // Send notification if admin email was changed
    if ($admin_email_changed && $old_admin_email && $admin_email && $old_admin_email !== $admin_email) {
        // Notify old admin
        iipm_send_admin_removed_notification($old_admin_email, $name);
        
        // Send invitation to new admin
        if ($admin_name) {
            $invitation_result = iipm_send_organisation_admin_invitation($admin_email, $org_id, $admin_name);
            if (!$invitation_result['success']) {
                error_log('IIPM: Failed to send admin change invitation: ' . $invitation_result['error']);
            }
        }
    }
    
    // Log activity
    iipm_log_user_activity(
        get_current_user_id(),
        'organisation_' . $action,
        "Organisation '{$name}' {$action}"
    );
    
    wp_send_json_success(array(
        'org_id' => $org_id,
        'action' => $action,
        'invitation_sent' => $admin_email && $send_invitation
    ));
}
add_action('wp_ajax_iipm_save_organisation', 'iipm_save_organisation');

// Check Organisation Name for Duplicates
function iipm_check_organisation_name() {
    // Check permissions
    if (!current_user_can('manage_iipm_members') && !current_user_can('administrator')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'iipm_portal_nonce')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    global $wpdb;
    
    $name = sanitize_text_field($_POST['name']);
    $org_id = isset($_POST['org_id']) ? intval($_POST['org_id']) : 0;
    
    if (empty($name)) {
        wp_send_json_success(array('exists' => false));
        return;
    }
    
    // Check for existing organisation with the same name (case-insensitive)
    $existing_org = $wpdb->get_row($wpdb->prepare(
        "SELECT id, name FROM {$wpdb->prefix}test_iipm_organisations 
         WHERE LOWER(name) = LOWER(%s) AND is_active = 1" . ($org_id ? " AND id != %d" : ""),
        $name,
        $org_id
    ));
    
    if ($existing_org) {
        wp_send_json_success(array(
            'exists' => true,
            'existing_name' => $existing_org->name,
            'existing_id' => $existing_org->id
        ));
    } else {
        wp_send_json_success(array('exists' => false));
    }
}
add_action('wp_ajax_iipm_check_organisation_name', 'iipm_check_organisation_name');

// Get Organisation Data
function iipm_get_organisation() {
    if (!current_user_can('manage_iipm_members') && !current_user_can('administrator')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    if (!wp_verify_nonce($_POST['nonce'], 'iipm_portal_nonce')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    $org_id = intval($_POST['org_id']);
    
    if (!$org_id) {
        wp_send_json_error('Invalid organisation ID');
        return;
    }
    
    global $wpdb;
    $organisation = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}test_iipm_organisations WHERE id = %d",
        $org_id
    ));
    
    if (!$organisation) {
        wp_send_json_error('Organisation not found');
        return;
    }
    
    wp_send_json_success($organisation);
}
add_action('wp_ajax_iipm_get_organisation', 'iipm_get_organisation');

// Setup Organisation Administrator
function iipm_setup_organisation_admin() {
    if (!current_user_can('manage_iipm_members') && !current_user_can('administrator')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    if (!wp_verify_nonce($_POST['nonce'], 'iipm_setup_admin_nonce')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    $org_id = intval($_POST['org_id']);
    $admin_email = sanitize_email($_POST['admin_email']);
    $admin_name = sanitize_text_field($_POST['admin_name']);
    $send_invitation = isset($_POST['send_invitation']);
    
    if (!$org_id || !is_email($admin_email) || empty($admin_name)) {
        wp_send_json_error('Organisation ID, valid admin email, and admin name are required');
        return;
    }
    
    global $wpdb;
    
    // Check if organisation exists
    $organisation = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}test_iipm_organisations WHERE id = %d",
        $org_id
    ));
    
    if (!$organisation) {
        wp_send_json_error('Organisation not found');
        return;
    }
    
    // Store admin_name and admin_email directly in the organisation table
    $result = $wpdb->update(
        $wpdb->prefix . 'test_iipm_organisations',
        array(
            'admin_name' => $admin_name,
            'admin_email' => $admin_email,
            'updated_at' => current_time('mysql')
        ),
        array('id' => $org_id),
        array('%s', '%s', '%s'),
        array('%d')
    );
    
    if ($result === false) {
        wp_send_json_error('Failed to update organisation: ' . $wpdb->last_error);
        return;
    }
    
    // Send invitation email if requested
    if ($send_invitation) {
        $invitation_result = iipm_send_organisation_admin_invitation($admin_email, $org_id, $admin_name);
        
        if (!$invitation_result['success']) {
            error_log('IIPM: Failed to send admin invitation: ' . $invitation_result['error']);
            // Don't fail the whole operation if email fails
        }
    }
    
    // Log activity
    iipm_log_user_activity(
        get_current_user_id(),
        'admin_setup_completed',
        "Admin '{$admin_name}' ({$admin_email}) set for organisation '{$organisation->name}'"
    );
    
    wp_send_json_success(array(
        'message' => 'Administrator setup successfully',
        'admin_name' => $admin_name,
        'admin_email' => $admin_email,
        'invitation_sent' => $send_invitation
    ));
}
add_action('wp_ajax_iipm_setup_organisation_admin', 'iipm_setup_organisation_admin');

// Get Organisation Members
function iipm_get_organisation_members() {
    if (!current_user_can('manage_iipm_members') && 
        !current_user_can('administrator') && 
        !current_user_can('manage_organisation_members')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    // Support both nonces for backward compatibility
    $nonce_valid = wp_verify_nonce($_POST['nonce'], 'iipm_portal_nonce') || 
                   wp_verify_nonce($_POST['nonce'], 'iipm_user_management_nonce');
    
    if (!$nonce_valid) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    // Support both org_id and employer_id parameters
    $org_id = intval($_POST['org_id'] ?? $_POST['employer_id'] ?? 0);
    
    if (!$org_id) {
        wp_send_json_error('Invalid organisation ID');
        return;
    }
    
    // Check if user is org admin and restrict to their organisation
    $current_user_id = get_current_user_id();
    $is_org_admin = iipm_is_organisation_admin($current_user_id);
    $is_site_admin = current_user_can('manage_iipm_members') || current_user_can('administrator');
    
    if ($is_org_admin && !$is_site_admin) {
        global $wpdb;
        $current_user = wp_get_current_user();
        $user_org = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}test_iipm_organisations WHERE admin_email = %s AND admin_name IS NOT NULL AND admin_email IS NOT NULL",
            $current_user->user_email
        ));
        
        if (!$user_org || $user_org->id != $org_id) {
            wp_send_json_error('You can only view members of your own organisation');
            return;
        }
    }
    
    global $wpdb;
    
    // Get pagination parameters
    $page = intval($_POST['page'] ?? 1);
    $per_page = 20;
    $offset = ($page - 1) * $per_page;
    $search = sanitize_text_field($_POST['search'] ?? '');
    $status_filter = sanitize_text_field($_POST['status_filter'] ?? '');
    
    // Build WHERE clause
    $where_conditions = array("mp.employer_id = %d");
    $where_params = array($org_id);
    
    // Search filter
    if (!empty($search)) {
        $where_conditions[] = "(u.display_name LIKE %s OR u.user_email LIKE %s)";
        $where_params[] = '%' . $search . '%';
        $where_params[] = '%' . $search . '%';
    }
    
    // Status filter
    if (!empty($status_filter)) {
        $where_conditions[] = "m.membership_status = %s";
        $where_params[] = $status_filter;
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Get total count
    $count_sql = "
        SELECT COUNT(DISTINCT u.ID)
        FROM {$wpdb->users} u
        LEFT JOIN {$wpdb->prefix}test_iipm_members m ON u.ID = m.user_id
        LEFT JOIN {$wpdb->prefix}test_iipm_member_profiles mp ON u.ID = mp.user_id
        {$where_clause}
    ";
    
    $total_members = $wpdb->get_var($wpdb->prepare($count_sql, $where_params));
    
    // Get members with pagination
    $members_sql = "
        SELECT u.ID, u.display_name, u.user_email, u.user_registered,
               m.membership_status, m.last_login, mp.employer_id, mp.theUsersStatus
        FROM {$wpdb->users} u
        LEFT JOIN {$wpdb->prefix}test_iipm_members m ON u.ID = m.user_id
        LEFT JOIN {$wpdb->prefix}test_iipm_member_profiles mp ON u.ID = mp.user_id
        {$where_clause}
        ORDER BY u.display_name ASC
        LIMIT %d OFFSET %d
    ";
    
    $members_params = array_merge($where_params, array($per_page, $offset));
    $members = $wpdb->get_results($wpdb->prepare($members_sql, $members_params));
    
    // Process members data
    $processed_members = array();
    foreach ($members as $member) {
        $processed_members[] = array(
            'ID' => $member->ID,
            'display_name' => $member->display_name,
            'user_email' => $member->user_email,
            'membership_status' => $member->membership_status ?: 'pending',
            'last_login' => $member->last_login ? date('M j, Y g:i A', strtotime($member->last_login)) : null,
            'theUsersStatus' => $member->theUsersStatus
        );
    }
    
    $total_pages = ceil($total_members / $per_page);
    
    // Return data in the format expected by the new page
    wp_send_json_success(array(
        'members' => $processed_members,
        'pagination' => array(
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_members' => $total_members,
            'per_page' => $per_page
        )
    ));
}
add_action('wp_ajax_iipm_get_organisation_members', 'iipm_get_organisation_members');

// Deactivate Organisation
function iipm_deactivate_organisation() {
    if (!current_user_can('manage_iipm_members') && !current_user_can('administrator')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    if (!wp_verify_nonce($_POST['nonce'], 'iipm_portal_nonce')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    $org_id = intval($_POST['org_id']);
    
    if (!$org_id) {
        wp_send_json_error('Invalid organisation ID');
        return;
    }
    
    global $wpdb;
    
    // Get organisation name for logging
    $org_name = $wpdb->get_var($wpdb->prepare(
        "SELECT name FROM {$wpdb->prefix}test_iipm_organisations WHERE id = %d",
        $org_id
    ));
    
    // Deactivate organisation
    $result = $wpdb->update(
        $wpdb->prefix . 'test_iipm_organisations',
        array('is_active' => 0, 'updated_at' => current_time('mysql')),
        array('id' => $org_id),
        array('%d', '%s'),
        array('%d')
    );
    
    if ($result === false) {
        wp_send_json_error('Failed to deactivate organisation');
        return;
    }
    
    // Update member statuses to inactive
    $wpdb->update(
        $wpdb->prefix . 'test_iipm_members',
        array('membership_status' => 'inactive'),
        array('organisation_id' => $org_id),
        array('%s'),
        array('%d')
    );
    
    // Log activity
    iipm_log_user_activity(
        get_current_user_id(),
        'organisation_deactivated',
        "Organisation '{$org_name}' deactivated"
    );
    
    wp_send_json_success();
}
add_action('wp_ajax_iipm_deactivate_organisation', 'iipm_deactivate_organisation');

// NEW: Get Organisation Delete Information
function iipm_get_organisation_delete_info() {
    if (!current_user_can('manage_iipm_members') && !current_user_can('administrator')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    if (!wp_verify_nonce($_POST['nonce'], 'iipm_portal_nonce')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    $org_id = intval($_POST['org_id']);
    
    if (!$org_id) {
        wp_send_json_error('Invalid organisation ID');
        return;
    }
    
    global $wpdb;
    
    // Get organisation details
    $organisation = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}test_iipm_organisations WHERE id = %d",
        $org_id
    ));
    
    if (!$organisation) {
        wp_send_json_error('Organisation not found');
        return;
    }
    
    // Get member count and details
    $members = $wpdb->get_results($wpdb->prepare(
        "SELECT u.ID as user_id, u.display_name, u.user_email, m.membership_status
         FROM {$wpdb->prefix}test_iipm_members m
         JOIN {$wpdb->users} u ON m.user_id = u.ID
         JOIN {$wpdb->prefix}test_iipm_member_profiles mp ON u.ID = mp.user_id
         WHERE mp.employer_id = %d
         ORDER BY u.display_name",
        $org_id
    ));
    
    // Get import count
    $import_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}test_iipm_bulk_imports WHERE organisation_id = %d",
        $org_id
    ));
    
    // Get invitation count
    $invitation_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}test_iipm_invitations WHERE organisation_id = %d",
        $org_id
    ));
    
    wp_send_json_success(array(
        'organisation' => $organisation,
        'members' => $members,
        'member_count' => count($members),
        'import_count' => $import_count,
        'invitation_count' => $invitation_count
    ));
}
add_action('wp_ajax_iipm_get_organisation_delete_info', 'iipm_get_organisation_delete_info');

// NEW: Delete Organisation
function iipm_delete_organisation() {
    if (!current_user_can('manage_iipm_members') && !current_user_can('administrator')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    if (!wp_verify_nonce($_POST['nonce'], 'iipm_portal_nonce')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    $org_id = intval($_POST['org_id']);
    
    if (!$org_id) {
        wp_send_json_error('Invalid organisation ID');
        return;
    }
    
    global $wpdb;
    
    // Get organisation name for logging
    $org_name = $wpdb->get_var($wpdb->prepare(
        "SELECT name FROM {$wpdb->prefix}test_iipm_organisations WHERE id = %d",
        $org_id
    ));
    
    if (!$org_name) {
        wp_send_json_error('Organisation not found');
        return;
    }
    
    // Start transaction
    $wpdb->query('START TRANSACTION');
    
    try {
        // Get all members of this organisation
        $member_user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT mp.user_id FROM {$wpdb->prefix}test_iipm_member_profiles mp WHERE mp.employer_id = %d",
            $org_id
        ));
        
        // Delete member profiles
        if (!empty($member_user_ids)) {
            $placeholders = implode(',', array_fill(0, count($member_user_ids), '%d'));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}test_iipm_member_profiles WHERE user_id IN ($placeholders)",
                ...$member_user_ids
            ));
        }
        
        // Delete members
        $wpdb->delete(
            $wpdb->prefix . 'test_iipm_members',
            array('organisation_id' => $org_id),
            array('%d')
        );
        
        // Delete WordPress users (optional - you might want to keep them)
        if (!empty($member_user_ids)) {
            foreach ($member_user_ids as $user_id) {
                // Only delete users that are not administrators
                $user = get_user_by('id', $user_id);
                if ($user && !user_can($user, 'administrator')) {
                    wp_delete_user($user_id);
                }
            }
        }
        
        // Delete bulk imports
        $wpdb->delete(
            $wpdb->prefix . 'test_iipm_bulk_imports',
            array('organisation_id' => $org_id),
            array('%d')
        );
        
        // Delete invitations
        $wpdb->delete(
            $wpdb->prefix . 'test_iipm_invitations',
            array('organisation_id' => $org_id),
            array('%d')
        );
        
        // Delete user activity logs for organisation members
        if (!empty($member_user_ids)) {
            $placeholders = implode(',', array_fill(0, count($member_user_ids), '%d'));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}test_iipm_user_activity WHERE user_id IN ($placeholders)",
                ...$member_user_ids
            ));
        }
        
        // Finally, delete the organisation
        $result = $wpdb->delete(
            $wpdb->prefix . 'test_iipm_organisations',
            array('id' => $org_id),
            array('%d')
        );
        
        if ($result === false) {
            throw new Exception('Failed to delete organisation');
        }
        
        // Commit transaction
        $wpdb->query('COMMIT');
        
        // Log activity
        iipm_log_user_activity(
            get_current_user_id(),
            'organisation_deleted',
            "Organisation '{$org_name}' permanently deleted with all associated data"
        );
        
        wp_send_json_success(array(
            'message' => "Organisation '{$org_name}' has been permanently deleted.",
            'deleted_members' => count($member_user_ids)
        ));
        
    } catch (Exception $e) {
        // Rollback transaction
        $wpdb->query('ROLLBACK');
        
        error_log('IIPM: Error deleting organisation: ' . $e->getMessage());
        wp_send_json_error('Failed to delete organisation: ' . $e->getMessage());
    }
}
add_action('wp_ajax_iipm_delete_organisation', 'iipm_delete_organisation');

// Export Organisations
function iipm_export_organisations() {
    if (!current_user_can('manage_iipm_members') && !current_user_can('administrator')) {
        wp_die('Insufficient permissions');
        return;
    }
    
    if (!wp_verify_nonce($_GET['nonce'], 'iipm_portal_nonce')) {
        wp_die('Security check failed');
        return;
    }
    
    global $wpdb;
    $organisations = $wpdb->get_results("
        SELECT o.*, 
               COUNT(mp.id) as member_count
        FROM {$wpdb->prefix}test_iipm_organisations o
        LEFT JOIN {$wpdb->prefix}test_iipm_member_profiles mp ON o.id = mp.employer_id
        WHERE o.is_active = 1
        GROUP BY o.id
        ORDER BY o.name
    ");
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="iipm_organisations_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, array(
        'ID', 'Name', 'Contact Email', 'Contact Phone', 'City', 'County',
        'Admin Name', 'Admin Email', 'Member Count', 'Created Date'
    ));
    
    // CSV data
    foreach ($organisations as $org) {
        fputcsv($output, array(
            $org->id,
            $org->name,
            $org->contact_email,
            $org->contact_phone,
            $org->city,
            $org->county,
            $org->admin_name,
            $org->admin_email,
            $org->member_count,
            $org->created_at
        ));
    }
    
    fclose($output);
    exit;
}
add_action('wp_ajax_iipm_export_organisations', 'iipm_export_organisations');

/**
 * NEW: Clear Organisation Invitations (for testing)
 */
function iipm_clear_organisation_invitations() {
    // Check permissions - Allow both site admins and org admins
    if (!current_user_can('manage_iipm_members') && 
        !current_user_can('administrator') && 
        !current_user_can('manage_organisation_members')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'iipm_portal_nonce')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    $org_id = intval($_POST['org_id']);
    
    if (!$org_id) {
        wp_send_json_error('Invalid organisation ID');
        return;
    }
    
    // Check if user is org admin and restrict to their organisation
    $current_user_id = get_current_user_id();
    $is_org_admin = iipm_is_organisation_admin($current_user_id);
    $is_site_admin = current_user_can('manage_iipm_members') || current_user_can('administrator');
    
    if ($is_org_admin && !$is_site_admin) {
        global $wpdb;
        $current_user = wp_get_current_user();
        $user_org = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}test_iipm_organisations WHERE admin_email = %s AND admin_name IS NOT NULL AND admin_email IS NOT NULL",
            $current_user->user_email
        ));
        
        if (!$user_org || $user_org->id != $org_id) {
            wp_send_json_error('You can only clear invitations for your own organisation');
            return;
        }
    }
    
    global $wpdb;
    
    // Get organisation name for logging
    $org_name = $wpdb->get_var($wpdb->prepare(
        "SELECT name FROM {$wpdb->prefix}test_iipm_organisations WHERE id = %d",
        $org_id
    ));
    
    if (!$org_name) {
        wp_send_json_error('Organisation not found');
        return;
    }
    
    // Get count of invitations to be deleted
    $invitation_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}test_iipm_invitations WHERE organisation_id = %d",
        $org_id
    ));
    
    // Delete all invitations for this organisation
    $result = $wpdb->delete(
        $wpdb->prefix . 'test_iipm_invitations',
        array('organisation_id' => $org_id),
        array('%d')
    );
    
    if ($result === false) {
        wp_send_json_error('Failed to clear invitations: ' . $wpdb->last_error);
        return;
    }
    
    // Log activity
    iipm_log_user_activity(
        get_current_user_id(),
        'invitations_cleared',
        "Cleared {$invitation_count} invitations for organisation '{$org_name}'"
    );
    
    wp_send_json_success(array(
        'message' => "Cleared {$invitation_count} invitations for {$org_name}",
        'cleared_count' => $invitation_count
    ));
}
add_action('wp_ajax_iipm_clear_organisation_invitations', 'iipm_clear_organisation_invitations');

/**
 * NEW: Get Organisation Invitations
 */
function iipm_get_organisation_invitations() {
    // Check permissions
    if (!current_user_can('manage_iipm_members') && 
        !current_user_can('administrator') && 
        !current_user_can('manage_organisation_members')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'iipm_portal_nonce')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    $org_id = intval($_POST['org_id']);
    
    if (!$org_id) {
        wp_send_json_error('Invalid organisation ID');
        return;
    }
    
    // Check if user is org admin and restrict to their organisation
    $current_user_id = get_current_user_id();
    $is_org_admin = iipm_is_organisation_admin($current_user_id);
    $is_site_admin = current_user_can('manage_iipm_members') || current_user_can('administrator');
    
    if ($is_org_admin && !$is_site_admin) {
        global $wpdb;
        $current_user = wp_get_current_user();
        $user_org = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}test_iipm_organisations WHERE admin_email = %s AND admin_name IS NOT NULL AND admin_email IS NOT NULL",
            $current_user->user_email
        ));
        
        if (!$user_org || $user_org->id != $org_id) {
            wp_send_json_error('You can only view invitations for your own organisation');
            return;
        }
    }
    
    global $wpdb;
    
    // Get all invitations for this organisation
    $invitations = $wpdb->get_results($wpdb->prepare(
        "SELECT i.*, u.display_name as invited_by_name
         FROM {$wpdb->prefix}test_iipm_invitations i
         LEFT JOIN {$wpdb->users} u ON i.invited_by = u.ID
         WHERE i.organisation_id = %d
         ORDER BY i.created_at DESC",
        $org_id
    ));
    
    // Separate by status
    $pending = array();
    $used = array();
    $expired = array();
    
    foreach ($invitations as $invitation) {
        if ($invitation->used_at) {
            $used[] = $invitation;
        } elseif (strtotime($invitation->expires_at) < time()) {
            $expired[] = $invitation;
        } else {
            $pending[] = $invitation;
        }
    }
    
    wp_send_json_success(array(
        'pending' => $pending,
        'used' => $used,
        'expired' => $expired,
        'total' => count($invitations),
        'pending_count' => count($pending),
        'used_count' => count($used),
        'expired_count' => count($expired)
    ));
}
add_action('wp_ajax_iipm_get_organisation_invitations', 'iipm_get_organisation_invitations');

/**
 * Enhanced Organisation Admin Invitation System
 */
function iipm_send_organisation_admin_invitation($email, $organisation_id, $admin_name = '') {
    global $wpdb;
    
    try {
        // Get organisation details
        $organisation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}test_iipm_organisations WHERE id = %d",
            $organisation_id
        ));
        
        if (!$organisation) {
            return array('success' => false, 'error' => 'Organisation not found');
        }
        
        // Generate unique token
        $token = wp_generate_password(32, false);
        $expires_at = date('Y-m-d H:i:s', strtotime('+14 days')); // 14 days for admin invitations
        
        // Insert invitation record
        $result = $wpdb->insert(
            $wpdb->prefix . 'test_iipm_invitations',
            array(
                'email' => $email,
                'token' => $token,
                'invitation_type' => 'organisation_admin',
                'invited_by' => get_current_user_id(),
                'organisation_id' => $organisation_id,
                'expires_at' => $expires_at
            ),
            array('%s', '%s', '%s', '%d', '%d', '%s')
        );
        
        if ($result === false) {
            error_log('IIPM: Failed to insert admin invitation record: ' . $wpdb->last_error);
            return array('success' => false, 'error' => 'Database error: ' . $wpdb->last_error);
        }
        
        // Send email
        $registration_url = home_url('/member-registration/?token=' . $token);
        $subject = 'IIPM Organisation Administrator Invitation - ' . $organisation->name;
        
        $greeting = $admin_name ? "Dear {$admin_name}," : "Dear Colleague,";
        
        $location = array_filter([$organisation->city, $organisation->county]);
        $location_str = !empty($location) ? implode(', ', $location) : 'Not specified';
        
        $message = "
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #8b5a96 0%, #6b4c93 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #ffffff; padding: 30px; border: 1px solid #e5e7eb; }
        .button { display: inline-block; background: #8b5a96; color: white; padding: 14px 28px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
        .info-box { background: #f8fafc; border-left: 4px solid #8b5a96; padding: 15px; margin: 20px 0; }
        .footer { background: #f8fafc; padding: 20px; text-align: center; color: #6b7280; border-radius: 0 0 8px 8px; }
        ul { padding-left: 20px; }
        ul li { margin-bottom: 8px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1 style='margin: 0; font-size: 24px;'>IIPM Organisation Administrator Invitation</h1>
        </div>
        
        <div class='content'>
            <p><strong>{$greeting}</strong></p>
            
            <p>You have been invited to become the administrator for <strong>{$organisation->name}</strong> on the Irish Institute of Pensions Management (IIPM) platform.</p>
            
            <p><strong>As an organisation administrator, you will be able to:</strong></p>
            <ul>
                <li>Manage your organisation's member accounts</li>
                <li>Bulk import employees as IIPM members</li>
                <li>View organisation-wide reports and statistics</li>
                <li>Process group payments and invoicing</li>
            </ul>
            
            <p>To accept this invitation and set up your administrator account, please click the button below:</p>
            
            <p style='text-align: center;'>
                <a href='{$registration_url}' class='button' style='color: white;'>Accept Invitation & Register</a>
            </p>
            
            <p style='font-size: 12px; color: #6b7280;'><em>Or copy this link: {$registration_url}</em></p>
            
            <p style='color: #ef4444; font-weight: 600;'>⏰ This invitation will expire in 14 days.</p>
            
            <div class='info-box'>
                <p style='margin: 0 0 10px 0;'><strong>📋 Organisation Details:</strong></p>
                <p style='margin: 5px 0;'><strong>Name:</strong> {$organisation->name}</p>
                <p style='margin: 5px 0;'><strong>Contact:</strong> {$organisation->contact_email}</p>
                <p style='margin: 5px 0;'><strong>Location:</strong> {$location_str}</p>
            </div>
            
            <p>If you have any questions about this invitation or the IIPM platform, please contact us:</p>
            <p>
                <strong>📧 Email:</strong> info@iipm.ie<br>
                <strong>📞 Phone:</strong> +353 (0)1 613 0874
            </p>
            
            <p>Best regards,<br>
            <strong>IIPM Administration Team</strong></p>
        </div>
        
        <div class='footer'>
            <p style='margin: 0; font-size: 14px;'><strong>Irish Institute of Pensions Management</strong></p>
            <p style='margin: 5px 0 0 0;'><a href='https://www.iipm.ie' style='color: #8b5a96;'>www.iipm.ie</a></p>
        </div>
    </div>
</body>
</html>
        ";
        
        // Add headers for better email delivery
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: IIPM Portal <' . (defined('SMTP_FROM') ? SMTP_FROM : get_option('admin_email')) . '>'
        );
        
        // Try to send email
        $email_sent = wp_mail($email, $subject, $message, $headers);
        
        if (!$email_sent) {
            error_log('IIPM: Failed to send admin invitation email to: ' . $email);
            
            // For debugging in local environment
            if (defined('WP_DEBUG') && WP_DEBUG) {
                return array('success' => true, 'token' => $token, 'note' => 'Email logged locally instead of sending');
            }
            
            return array('success' => false, 'error' => 'Failed to send email. Please check email configuration.');
        }
        
        return array('success' => true, 'token' => $token);
        
    } catch (Exception $e) {
        error_log('IIPM: Exception in iipm_send_organisation_admin_invitation: ' . $e->getMessage());
        return array('success' => false, 'error' => 'System error: ' . $e->getMessage());
    }
}

/**
 * Enhanced Registration Processing for Organisation Admins
 */
function iipm_process_organisation_admin_registration($data, $invitation) {
    global $wpdb;
    
    $email = sanitize_email($data['email']);
    $first_name = sanitize_text_field($data['first_name']);
    $last_name = sanitize_text_field($data['last_name']);
    $password = $data['password'];
    $organisation_id = $invitation->organisation_id;
    
    // Verify that the email matches the admin_email in the organisation
    $organisation = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}test_iipm_organisations WHERE id = %d",
        $organisation_id
    ));
    
    if (!$organisation || $organisation->admin_email !== $email) {
        return array('success' => false, 'error' => 'Email does not match the organisation administrator email');
    }
    
    // Create user account
    $user_id = wp_create_user($email, $password, $email);
    
    if (is_wp_error($user_id)) {
        return array('success' => false, 'error' => $user_id->get_error_message());
    }
    
    // Update user profile
    wp_update_user(array(
        'ID' => $user_id,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'display_name' => $first_name . ' ' . $last_name,
    ));
    
    // Set user role to corporate admin
    $user = new WP_User($user_id);
    $user->set_role('iipm_corporate_admin');
    
    // Create member record
    $wpdb->insert(
        $wpdb->prefix . 'test_iipm_members',
        array(
            'user_id' => $user_id,
            'member_type' => 'organisation',
            'membership_status' => 'active', // Admins are automatically active
            'membership_level' => 'member',
            'gdpr_consent' => 1,
            'marketing_consent' => isset($data['marketing_consent']) ? 1 : 0,
            'email_verified' => 1, // Auto-verify admin emails
            'profile_completed' => 0
        ),
        array('%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d')
    );
    
    // Create profile record
    $wpdb->insert(
        $wpdb->prefix . 'test_iipm_member_profiles',
        array(
            'user_id' => $user_id,
            'user_phone' => sanitize_text_field($data['user_phone'] ?? ''),
            'email_address' => $email,
            'user_mobile' => sanitize_text_field($data['user_mobile'] ?? ''),
            'postal_address' => sanitize_text_field($data['postal_address'] ?? ''),
            'city_or_town' => sanitize_text_field($data['city_or_town'] ?? ''),
            'Address_1' => sanitize_text_field($data['address_line_1'] ?? ''),
            'Address_2' => sanitize_text_field($data['address_line_2'] ?? ''),
            'Address_3' => sanitize_text_field($data['address_line_3'] ?? ''),
            'user_fullName' => $first_name." ".$last_name,
            'user_payment_method' => !empty($data['payment_method']) ? sanitize_text_field($data['payment_method']) : 'Direct Invoiced',
            'sur_name' => sanitize_text_field($last_name ?? ''),
            'first_name' => sanitize_text_field($first_name ?? ''),
            'user_is_admin' => 1,
            'user_designation' => sanitize_text_field($data['user_designation'] ?? ''),
            'user_name_login' => sanitize_text_field($data['login_name'] ?? ''),
            'email_address_pers' => sanitize_email($data['email_address_pers'] ?? ''),
            'user_phone_pers' => sanitize_text_field($data['user_phone_pers'] ?? ''),
            'user_mobile_pers' => sanitize_text_field($data['user_mobile_pers'] ?? ''),
            'Address_1_pers' => sanitize_text_field($data['Address_1_pers'] ?? ''),
            'Address_2_pers' => sanitize_text_field($data['Address_2_pers'] ?? ''),
            'Address_3_pers' => sanitize_text_field($data['Address_3_pers'] ?? ''),
            'eircode_p' => sanitize_text_field($data['eircode_p'] ?? ''),
            'eircode_w' => sanitize_text_field($data['eircode_w'] ?? ''),
            'correspondence_email' => sanitize_email($data['correspondence_email'] ?? ''),
            'user_notes' => sanitize_textarea_field($data['user_notes'] ?? ''),
            'dateOfUpdatePers' => current_time('mysql'),
            'dateOfUpdateGen' => current_time('mysql'),
            'employerDetailsUpdated' => current_time('mysql'),
            'theUsersStatus' => 'Systems Admin',
            'employer_id' => $organisation_id,
        ),
        array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
    );
    
    // Mark invitation as used
    $wpdb->update(
        $wpdb->prefix . 'test_iipm_invitations',
        array('used_at' => current_time('mysql')),
        array('id' => $invitation->id),
        array('%s'),
        array('%d')
    );
    
    // Log activity
    iipm_log_user_activity($user_id, 'admin_registration', 'Organisation administrator registered successfully');
    
    // Send welcome email
    iipm_send_admin_welcome_email($user_id, $email, $first_name, $organisation_id);
    
    return array('success' => true, 'user_id' => $user_id, 'role' => 'corporate_admin');
}

/**
 * Send Welcome Email to New Organisation Admin
 */
function iipm_send_admin_welcome_email($user_id, $email, $first_name, $organisation_id) {
    global $wpdb;
    
    $organisation = $wpdb->get_row($wpdb->prepare(
        "SELECT name FROM {$wpdb->prefix}test_iipm_organisations WHERE id = %d",
        $organisation_id
    ));
    
    $portal_url = home_url('/member-portal/');
    $bulk_import_url = home_url('/bulk-import/');
    $subject = 'Welcome to IIPM - Organisation Administrator Access';
    
    $message = "
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #ffffff; padding: 30px; border: 1px solid #e5e7eb; }
        .button { display: inline-block; background: #8b5a96; color: white; padding: 14px 28px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
        .feature-box { background: #f0fdf4; border-left: 4px solid #10b981; padding: 15px; margin: 15px 0; }
        .steps { background: #f8fafc; padding: 15px; border-radius: 6px; margin: 20px 0; }
        .footer { background: #f8fafc; padding: 20px; text-align: center; color: #6b7280; border-radius: 0 0 8px 8px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1 style='margin: 0; font-size: 24px;'>🎉 Welcome to IIPM!</h1>
        </div>
        
        <div class='content'>
            <p><strong>Dear {$first_name},</strong></p>
            
            <p>Welcome to the Irish Institute of Pensions Management! Your administrator account for <strong>{$organisation->name}</strong> has been successfully created.</p>
            
            <div class='feature-box'>
                <p style='margin: 0 0 10px 0;'><strong>✨ As an organisation administrator, you now have access to:</strong></p>
                <ul style='margin: 10px 0; padding-left: 20px;'>
                    <li>🏢 Organisation Dashboard</li>
                    <li>📊 Bulk Member Import</li>
                    <li>👥 Member Management Tools</li>
                    <li>📈 Reporting and Analytics</li>
                    <li>💳 Payment and Billing Management</li>
                </ul>
            </div>
            
            <p style='text-align: center;'>
                <a href='{$portal_url}' class='button' style='color: white;'>Access Your Dashboard</a>
            </p>
            
            <div class='steps'>
                <p style='margin: 0 0 10px 0;'><strong>🚀 Getting Started:</strong></p>
                <ol style='margin: 10px 0; padding-left: 20px;'>
                    <li>Log in to your dashboard</li>
                    <li>Complete your profile information</li>
                    <li>Review your organisation settings</li>
                    <li>Start importing your team members</li>
                </ol>
            </div>
            
            <p><strong>Need help getting started?</strong><br>
            Our support team is here to assist:</p>
            <p>
                📧 <strong>Email:</strong> info@iipm.ie<br>
                📞 <strong>Phone:</strong> +353 (0)1 613 0874
            </p>
            
            <p>Best regards,<br>
            <strong>IIPM Administration Team</strong></p>
        </div>
        
        <div class='footer'>
            <p style='margin: 0; font-size: 14px;'><strong>Irish Institute of Pensions Management</strong></p>
            <p style='margin: 5px 0 0 0;'><a href='https://www.iipm.ie' style='color: #8b5a96;'>www.iipm.ie</a></p>
        </div>
    </div>
</body>
</html>
    ";
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: IIPM Portal <' . (defined('SMTP_FROM') ? SMTP_FROM : get_option('admin_email')) . '>'
    );
    
    wp_mail($email, $subject, $message, $headers);
}

/**
 * UPDATED: Enhanced bulk import handler with organisation restrictions
 */
function iipm_handle_bulk_import_enhanced() {
    // Check permissions - Updated to include corporate admins
    if (!current_user_can('manage_iipm_members') && 
        !current_user_can('administrator') && 
        !current_user_can('bulk_import_members') &&
        !current_user_can('manage_organisation_members')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'iipm_bulk_import_nonce')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    // Validate organisation ID
    $organisation_id = intval($_POST['organisation_id']);
    if (!$organisation_id) {
        wp_send_json_error('Organisation ID is required');
        return;
    }
    
    // Check if user is org admin and restrict to their organisation
    $current_user_id = get_current_user_id();
    $is_org_admin = iipm_is_organisation_admin($current_user_id);
    $is_site_admin = current_user_can('manage_iipm_members') || current_user_can('administrator');
    
    if ($is_org_admin && !$is_site_admin) {
        global $wpdb;
        $current_user = wp_get_current_user();
        $user_org = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}test_iipm_organisations WHERE admin_email = %s AND admin_name IS NOT NULL AND admin_email IS NOT NULL",
            $current_user->user_email
        ));
        
        if (!$user_org || $user_org->id != $organisation_id) {
            wp_send_json_error('You can only import members to your own organisation');
            return;
        }
    }
    
    // Check file upload
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error('File upload failed');
        return;
    }
    
    $file = $_FILES['csv_file'];
    
    // Validate file type
    if (!in_array($file['type'], array('text/csv', 'application/csv', 'text/plain'))) {
        wp_send_json_error('Invalid file type. Please upload a CSV file.');
        return;
    }
    
    // Validate file size (5MB max)
    if ($file['size'] > 5 * 1024 * 1024) {
        wp_send_json_error('File too large. Maximum size is 5MB.');
        return;
    }
    
    // Move uploaded file to temp location
    $upload_dir = wp_upload_dir();
    $temp_file = $upload_dir['path'] . '/' . uniqid('bulk_import_') . '.csv';
    
    if (!move_uploaded_file($file['tmp_name'], $temp_file)) {
        wp_send_json_error('Failed to process uploaded file');
        return;
    }
    
    // Process import options
    $options = array(
        'send_invitations' => isset($_POST['send_invitations']),
        'skip_existing' => isset($_POST['skip_existing'])
    );
    
    // Log import start
    global $wpdb;
    $import_id = $wpdb->insert(
        $wpdb->prefix . 'test_iipm_bulk_imports',
        array(
            'filename' => $file['name'],
            'total_records' => 0,
            'import_type' => 'members',
            'imported_by' => get_current_user_id(),
            'organisation_id' => $organisation_id,
            'status' => 'processing'
        ),
        array('%s', '%d', '%s', '%d', '%d', '%s')
    );
    
    if (!$import_id) {
        unlink($temp_file);
        wp_send_json_error('Failed to create import record');
        return;
    }
    
    // Process the import
    $result = iipm_process_bulk_import($temp_file, $organisation_id, $options);
    
    // Update import record
    if ($result['success']) {
        $data = $result['data'];
        $wpdb->update(
            $wpdb->prefix . 'test_iipm_bulk_imports',
            array(
                'total_records' => $data['total'],
                'successful_imports' => $data['successful'],
                'failed_imports' => $data['failed'],
                'status' => 'completed',
                'error_log' => !empty($data['errors']) ? json_encode($data['errors']) : null
            ),
            array('id' => $import_id),
            array('%d', '%d', '%d', '%s', '%s'),
            array('%d')
        );
        
        // Log activity
        iipm_log_user_activity(
            get_current_user_id(),
            'bulk_import',
            "Bulk import completed for organisation ID {$organisation_id}: {$data['successful']} successful, {$data['failed']} failed"
        );
        
        wp_send_json_success($data);
    } else {
        $wpdb->update(
            $wpdb->prefix . 'test_iipm_bulk_imports',
            array(
                'status' => 'failed',
                'error_log' => $result['error']
            ),
            array('id' => $import_id),
            array('%s', '%s'),
            array('%d')
        );
        
        wp_send_json_error($result['error']);
    }
    
    // Clean up temp file
    unlink($temp_file);
}

// Replace the existing handler
remove_action('wp_ajax_iipm_bulk_import', 'iipm_handle_bulk_import');
add_action('wp_ajax_iipm_bulk_import', 'iipm_handle_bulk_import_enhanced');

// Get member details for editing
function iipm_get_member_details() {
    // Check permissions
    if (!current_user_can('administrator') && 
        !current_user_can('manage_organisation_members') && 
        !in_array('iipm_corporate_admin', wp_get_current_user()->roles)) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'iipm_user_management_nonce')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    $member_id = intval($_POST['member_id']);
    if (!$member_id) {
        wp_send_json_error('Invalid member ID');
        return;
    }
    
    $user = get_user_by('id', $member_id);
    if (!$user) {
        wp_send_json_error('Member not found');
        return;
    }
    
    global $wpdb;
    $member_data = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}test_iipm_members WHERE user_id = %d",
        $member_id
    ));
    
    wp_send_json_success(array(
        'ID' => $user->ID,
        'display_name' => $user->display_name,
        'user_email' => $user->user_email,
        'membership_status' => $member_data ? $member_data->membership_status : 'pending'
    ));
}
add_action('wp_ajax_iipm_get_member_details', 'iipm_get_member_details');

// Update member status
function iipm_update_member_status() {
    // Check permissions
    if (!current_user_can('administrator') && 
        !current_user_can('manage_organisation_members') && 
        !in_array('iipm_corporate_admin', wp_get_current_user()->roles)) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'iipm_user_management_nonce')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    $member_id = intval($_POST['member_id']);
    $status = sanitize_text_field($_POST['status']);
    
    if (!$member_id || !$status) {
        wp_send_json_error('Missing required fields');
        return;
    }
    
    global $wpdb;
    
    // Update membership status
    $result = $wpdb->update(
        $wpdb->prefix . 'test_iipm_members',
        array('membership_status' => $status),
        array('user_id' => $member_id),
        array('%s'),
        array('%d')
    );
    
    if ($result === false) {
        wp_send_json_error('Database error');
        return;
    }
    
    // Log activity
    iipm_log_user_activity(
        get_current_user_id(),
        'member_status_updated',
        "Updated member status to: {$status}"
    );
    
    wp_send_json_success('Member status updated successfully');
}
add_action('wp_ajax_iipm_update_member_status', 'iipm_update_member_status');

// Get users for bulk import (users not in current organization)
function iipm_get_import_users() {
    // Check permissions
    if (!current_user_can('administrator') && 
        !current_user_can('manage_organisation_members') && 
        !in_array('iipm_corporate_admin', wp_get_current_user()->roles)) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'iipm_user_management_nonce')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    global $wpdb;
    
    $current_user = wp_get_current_user();
    $is_site_admin = current_user_can('administrator');
    $is_org_admin = in_array('iipm_corporate_admin', $current_user->roles) || current_user_can('manage_organisation_members');
    
    // Get parameters
    $page = intval($_POST['page'] ?? 1);
    $per_page = 20;
    $offset = ($page - 1) * $per_page;
    $search = sanitize_text_field($_POST['search'] ?? '');
    $status_filter = sanitize_text_field($_POST['status_filter'] ?? '');
    $org_filter = intval($_POST['org_filter'] ?? 0);
    $current_employer_id = intval($_POST['current_employer_id'] ?? 0);
    
    if (!$current_employer_id) {
        wp_send_json_error('Invalid current employer ID');
        return;
    }
    
    // Build WHERE clause
    $where_conditions = array();
    $where_params = array();
    
    // Exclude users already in current organization
    $where_conditions[] = "(mp.employer_id IS NULL OR mp.employer_id != %d)";
    $where_params[] = $current_employer_id;
    
    // Search filter
    if (!empty($search)) {
        $where_conditions[] = "(u.display_name LIKE %s OR u.user_email LIKE %s)";
        $where_params[] = '%' . $search . '%';
        $where_params[] = '%' . $search . '%';
    }
    
    // Status filter
    if (!empty($status_filter)) {
        $where_conditions[] = "m.membership_status = %s";
        $where_params[] = $status_filter;
    }
    
    // Organization filter
    if (!empty($org_filter)) {
        $where_conditions[] = "mp.employer_id = %d";
        $where_params[] = $org_filter;
    }
    
    // Build WHERE clause
    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }
    
    // Get total count
    $count_sql = "
        SELECT COUNT(DISTINCT u.ID)
        FROM {$wpdb->users} u
        LEFT JOIN {$wpdb->prefix}test_iipm_members m ON u.ID = m.user_id
        LEFT JOIN {$wpdb->prefix}test_iipm_member_profiles mp ON u.ID = mp.user_id
        LEFT JOIN {$wpdb->prefix}test_iipm_organisations o ON mp.employer_id = o.id
        {$where_clause}
    ";
    
    $total_users = $wpdb->get_var($wpdb->prepare($count_sql, $where_params));
    
    // Get users with pagination
    $users_sql = "
        SELECT u.ID, u.display_name, u.user_email, u.user_registered,
               m.membership_status, m.last_login, mp.employer_id,
               o.name as organisation_name
        FROM {$wpdb->users} u
        LEFT JOIN {$wpdb->prefix}test_iipm_members m ON u.ID = m.user_id
        LEFT JOIN {$wpdb->prefix}test_iipm_member_profiles mp ON u.ID = mp.user_id
        LEFT JOIN {$wpdb->prefix}test_iipm_organisations o ON mp.employer_id = o.id
        {$where_clause}
        ORDER BY u.display_name ASC
        LIMIT %d OFFSET %d
    ";
    
    $users_params = array_merge($where_params, array($per_page, $offset));
    $users = $wpdb->get_results($wpdb->prepare($users_sql, $users_params));
    
    // Process users data
    $processed_users = array();
    foreach ($users as $user) {
        $processed_users[] = array(
            'ID' => $user->ID,
            'display_name' => $user->display_name,
            'user_email' => $user->user_email,
            'membership_status' => $user->membership_status ?: 'pending',
            'last_login' => $user->last_login ? date('M j, Y g:i A', strtotime($user->last_login)) : null,
            'organisation_name' => $user->organisation_name ?: 'No Organisation'
        );
    }
    
    $total_pages = ceil($total_users / $per_page);
    
    wp_send_json_success(array(
        'users' => $processed_users,
        'pagination' => array(
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_users' => $total_users,
            'per_page' => $per_page
        )
    ));
}
add_action('wp_ajax_iipm_get_import_users', 'iipm_get_import_users');

// Import users to organization
function iipm_import_users_to_organisation() {
    // Check permissions
    if (!current_user_can('administrator') && 
        !current_user_can('manage_organisation_members') && 
        !in_array('iipm_corporate_admin', wp_get_current_user()->roles)) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'iipm_user_management_nonce')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    $user_ids = $_POST['user_ids'] ?? array();
    $employer_id = intval($_POST['employer_id'] ?? 0);
    
    if (empty($user_ids) || !$employer_id) {
        wp_send_json_error('Missing required fields');
        return;
    }
    
    global $wpdb;
    
    $imported_count = 0;
    $errors = array();
    
    foreach ($user_ids as $user_id) {
        $user_id = intval($user_id);
        if (!$user_id) continue;
        
        // Update or insert member profile
        $existing_profile = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}test_iipm_member_profiles WHERE user_id = %d",
            $user_id
        ));
        
        if ($existing_profile) {
            // Update existing profile
            $result = $wpdb->update(
                $wpdb->prefix . 'test_iipm_member_profiles',
                array('employer_id' => $employer_id),
                array('user_id' => $user_id),
                array('%d'),
                array('%d')
            );
        } else {
            // Create new profile
            $user = get_user_by('id', $user_id);
            if ($user) {
                $result = $wpdb->insert(
                    $wpdb->prefix . 'test_iipm_member_profiles',
                    array(
                        'user_id' => $user_id,
                        'employer_id' => $employer_id,
                        'first_name' => $user->first_name,
                        'sur_name' => $user->last_name,
                        'user_fullName' => $user->display_name,
                        'email_address' => $user->user_email,
                        'theUsersStatus' => 'Full Member',
                        'dateOfUpdateGen' => current_time('mysql')
                    ),
                    array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
                );
            } else {
                $errors[] = "User ID {$user_id} not found";
                continue;
            }
        }
        
        if ($result !== false) {
            $imported_count++;
        } else {
            $errors[] = "Failed to import user ID {$user_id}";
        }
    }
    
    // Log activity
    iipm_log_user_activity(
        get_current_user_id(),
        'users_imported',
        "Imported {$imported_count} users to organization"
    );
    
    wp_send_json_success(array(
        'imported_count' => $imported_count,
        'errors' => $errors
    ));
}
add_action('wp_ajax_iipm_import_users_to_organisation', 'iipm_import_users_to_organisation');

// Export organization members to CSV
function iipm_export_organisation_members() {
    // Check permissions
    if (!current_user_can('administrator') && 
        !current_user_can('manage_organisation_members') && 
        !in_array('iipm_corporate_admin', wp_get_current_user()->roles)) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'iipm_user_management_nonce')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    $employer_id = intval($_POST['employer_id'] ?? 0);
    $status_filter = sanitize_text_field($_POST['status_filter'] ?? '');
    $include_fields_raw = $_POST['include_fields'] ?? '';
    
    // Decode JSON string from JavaScript
    $include_fields = json_decode($include_fields_raw, true);
    
    // Fallback to default fields if decoding fails
    if (!is_array($include_fields)) {
        $include_fields = ['name', 'email', 'status', 'last_login', 'role'];
    }
    
    if (!$employer_id) {
        wp_send_json_error('Invalid employer ID');
        return;
    }
    
    global $wpdb;
    
    // Build WHERE clause
    $where_conditions = array("mp.employer_id = %d");
    $where_params = array($employer_id);
    
    if (!empty($status_filter)) {
        $where_conditions[] = "m.membership_status = %s";
        $where_params[] = $status_filter;
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Get members data
    $members = $wpdb->get_results($wpdb->prepare("
        SELECT u.ID, u.display_name, u.user_email, u.user_registered,
               m.membership_status, m.last_login, mp.theUsersStatus
        FROM {$wpdb->users} u
        LEFT JOIN {$wpdb->prefix}test_iipm_members m ON u.ID = m.user_id
        LEFT JOIN {$wpdb->prefix}test_iipm_member_profiles mp ON u.ID = mp.user_id
        {$where_clause}
        ORDER BY u.display_name ASC
    ", $where_params));
    
    if (empty($members)) {
        wp_send_json_error('No members found to export');
        return;
    }
    
    // Debug: Log members data to file
    error_log('CSV Export Debug - Members count: ' . count($members));
    error_log('CSV Export Debug - Status filter: ' . $status_filter);
    error_log('CSV Export Debug - Raw include_fields from POST: ' . print_r($_POST['include_fields'], true));
    error_log('CSV Export Debug - Processed include_fields: ' . print_r($include_fields, true));
    error_log('CSV Export Debug - Include fields type: ' . gettype($include_fields));
    error_log('CSV Export Debug - Include fields is_array: ' . (is_array($include_fields) ? 'true' : 'false'));
    error_log('CSV Export Debug - WHERE clause: ' . $where_clause);
    error_log('CSV Export Debug - WHERE params: ' . print_r($where_params, true));
    if (!empty($members)) {
        error_log('CSV Export Debug - First member: ' . print_r($members[0], true));
    }
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="organisation_members_' . date('Y-m-d') . '.csv"');
    
    // Create CSV output
    $output = fopen('php://output', 'w');
    
    // CSV headers
    $headers = array();
    if (in_array('name', $include_fields)) $headers[] = 'Name';
    if (in_array('email', $include_fields)) $headers[] = 'Email';
    if (in_array('status', $include_fields)) $headers[] = 'Status';
    if (in_array('last_login', $include_fields)) $headers[] = 'Last Login';
    if (in_array('role', $include_fields)) $headers[] = 'Role';
    
    fputcsv($output, $headers);
    
    // CSV data
    foreach ($members as $member) {
        $row = array();
        
        // Get user role for role display
        $user = get_user_by('id', $member->ID);
        $user_roles = $user ? $user->roles : array();
        $actual_role = $member->theUsersStatus ?: '';
        
        // Determine role display
        $role_display = 'Member';
        if ($actual_role === 'Systems Admin' || in_array('administrator', $user_roles)) {
            $role_display = 'Systems Admin';
        } elseif ($actual_role === 'EmployerContact') {
            $role_display = 'Employer Contact';
        } elseif ($actual_role === 'Full Member') {
            $role_display = 'Full Member';
        } elseif ($actual_role === 'Life Member') {
            $role_display = 'Life Member';
        } elseif ($actual_role === 'QPT Member') {
            $role_display = 'QPT Member';
        } else {
            $role_display = 'Member';
        }
        
        if (in_array('name', $include_fields)) $row[] = $member->display_name ?: 'N/A';
        if (in_array('email', $include_fields)) $row[] = $member->user_email ?: 'N/A';
        if (in_array('status', $include_fields)) $row[] = $member->membership_status ?: 'pending';
        if (in_array('last_login', $include_fields)) $row[] = $member->last_login ? date('Y-m-d H:i:s', strtotime($member->last_login)) : 'Never';
        if (in_array('role', $include_fields)) $row[] = $role_display;
        
        // Debug: Log first row only
        if ($member === $members[0]) {
            error_log('CSV Export Debug - First row data: ' . print_r($row, true));
        }
        
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}
add_action('wp_ajax_iipm_export_organisation_members', 'iipm_export_organisation_members');

// Get all organizations for import filter
function iipm_get_all_organisations_for_import() {
    // Check permissions
    if (!current_user_can('administrator') && 
        !current_user_can('manage_organisation_members') && 
        !in_array('iipm_corporate_admin', wp_get_current_user()->roles)) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'iipm_user_management_nonce')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    global $wpdb;
    
    $organizations = $wpdb->get_results(
        "SELECT id, name FROM {$wpdb->prefix}test_iipm_organisations WHERE is_active = 1 ORDER BY name"
    );
    
    if ($organizations === false) {
        wp_send_json_error('Database error');
        return;
    }
    
    wp_send_json_success($organizations);
}
add_action('wp_ajax_iipm_get_all_organisations_for_import', 'iipm_get_all_organisations_for_import');

// Remove Organisation Admin
function iipm_remove_organisation_admin() {
    if (!current_user_can('manage_iipm_members') && !current_user_can('administrator')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    if (!wp_verify_nonce($_POST['nonce'], 'iipm_portal_nonce')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    $org_id = intval($_POST['org_id']);
    
    if (!$org_id) {
        wp_send_json_error('Invalid organisation ID');
        return;
    }
    
    global $wpdb;
    
    // Get organisation details before removing admin
    $organisation = $wpdb->get_row($wpdb->prepare(
        "SELECT name, admin_name, admin_email FROM {$wpdb->prefix}test_iipm_organisations WHERE id = %d",
        $org_id
    ));
    
    if (!$organisation) {
        wp_send_json_error('Organisation not found');
        return;
    }
    
    $old_admin_email = $organisation->admin_email;
    $old_admin_name = $organisation->admin_name;
    
    // Remove admin by setting fields to null
    $result = $wpdb->update(
        $wpdb->prefix . 'test_iipm_organisations',
        array(
            'admin_name' => null,
            'admin_email' => null,
            'updated_at' => current_time('mysql')
        ),
        array('id' => $org_id),
        array('%s', '%s', '%s'),
        array('%d')
    );
    
    if ($result === false) {
        wp_send_json_error('Failed to remove admin: ' . $wpdb->last_error);
        return;
    }
    
    // Send notification to removed admin
    if ($old_admin_email) {
        iipm_send_admin_removed_notification($old_admin_email, $organisation->name, $old_admin_name);
    }
    
    // Log activity
    iipm_log_user_activity(
        get_current_user_id(),
        'admin_removed',
        "Admin '{$old_admin_name}' removed from organisation '{$organisation->name}'"
    );
    
    wp_send_json_success(array(
        'message' => 'Administrator removed successfully',
        'org_name' => $organisation->name
    ));
}
add_action('wp_ajax_iipm_remove_organisation_admin', 'iipm_remove_organisation_admin');

/**
 * Send notification when admin is removed from organisation
 */
function iipm_send_admin_removed_notification($email, $org_name, $admin_name = '') {
    $greeting = $admin_name ? "Dear {$admin_name}," : "Dear Colleague,";
    $subject = 'IIPM Organisation Administrator Status Update';
    
    $message = "
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background: #ffffff; padding: 30px; border: 1px solid #e5e7eb; }
        .info-box { background: #fffbeb; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; }
        .footer { background: #f8fafc; padding: 20px; text-align: center; color: #6b7280; border-radius: 0 0 8px 8px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1 style='margin: 0; font-size: 24px;'>Administrator Status Update</h1>
        </div>
        
        <div class='content'>
            <p><strong>{$greeting}</strong></p>
            
            <p>This is to inform you that your administrator status for <strong>{$org_name}</strong> has been updated in the IIPM system.</p>
            
            <div class='info-box'>
                <p style='margin: 0;'><strong>⚠️ Change Notice:</strong></p>
                <p style='margin: 10px 0 0 0;'>You are no longer listed as the administrator for this organisation.</p>
            </div>
            
            <p>If you have any questions about this change, please contact:</p>
            <p>
                📧 <strong>Email:</strong> info@iipm.ie<br>
                📞 <strong>Phone:</strong> +353 (0)1 613 0874
            </p>
            
            <p>Thank you,<br>
            <strong>IIPM Administration Team</strong></p>
        </div>
        
        <div class='footer'>
            <p style='margin: 0; font-size: 14px;'><strong>Irish Institute of Pensions Management</strong></p>
            <p style='margin: 5px 0 0 0;'><a href='https://www.iipm.ie' style='color: #8b5a96;'>www.iipm.ie</a></p>
        </div>
    </div>
</body>
</html>
    ";
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: IIPM Portal <' . (defined('SMTP_FROM') ? SMTP_FROM : get_option('admin_email')) . '>'
    );
    
    wp_mail($email, $subject, $message, $headers);
}

?>
