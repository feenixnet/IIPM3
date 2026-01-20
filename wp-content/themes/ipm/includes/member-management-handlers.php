<?php
/**
 * Member Management AJAX Handlers
 */

// Get users with filtering and pagination
function iipm_get_users() {
    // Check permissions
    if (!current_user_can('administrator') && 
        !current_user_can('manage_organisation_members') && 
        !in_array('iipm_corporate_admin', wp_get_current_user()->roles)) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    // Auto-sync missing last_login data for administrators
    if (current_user_can('administrator')) {
        iipm_sync_missing_last_login_data();
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
    $membership_filter = sanitize_text_field($_POST['membership_filter'] ?? '');
    $status_filter = sanitize_text_field($_POST['status_filter'] ?? '');
    
    // Build WHERE clause
    $where_conditions = array();
    $where_params = array();
    
    // Organization restriction for org admins
    if (!$is_site_admin && $is_org_admin) {
        // Get the organization ID for the current user
        $org_id = $wpdb->get_var($wpdb->prepare(
            "SELECT employer_id FROM {$wpdb->prefix}test_iipm_member_profiles WHERE user_id = %d",
            $current_user->ID
        ));
        
        if ($org_id) {
            $where_conditions[] = "mp.employer_id = %d";
            $where_params[] = $org_id;
        } else {
            // If org admin has no organization, show no users
            wp_send_json_success(array(
                'users' => array(),
                'pagination' => array(
                    'current_page' => 1,
                    'total_pages' => 0,
                    'total_users' => 0
                )
            ));
            return;
        }
    }
    
    // Search filter - case-sensitive using BINARY for exact capital letter matching
    if (!empty($search)) {
        $where_conditions[] = "(BINARY u.display_name LIKE %s OR BINARY u.user_email LIKE %s)";
        $where_params[] = '%' . $search . '%';
        $where_params[] = '%' . $search . '%';
    }
    
    // Status filter
    if (!empty($status_filter)) {
        $where_conditions[] = "m.membership_status = %s";
        $where_params[] = $status_filter;
    }
    
    // Membership filter (membership level filter)
    if (!empty($membership_filter)) {
        $where_conditions[] = "mem.id = %d";
        $where_params[] = intval($membership_filter);
    }
    
    // Exclude administrators from results
    $where_conditions[] = "NOT EXISTS (SELECT 1 FROM {$wpdb->usermeta} um WHERE um.user_id = u.ID AND um.meta_key = '{$wpdb->prefix}capabilities' AND um.meta_value LIKE '%administrator%')";
    
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
        LEFT JOIN {$wpdb->prefix}memberships mem ON m.membership_level = mem.id
        {$where_clause}
    ";
    
    $total_users = $wpdb->get_var($wpdb->prepare($count_sql, $where_params));
    
    // Get users with pagination
    $users_sql = "
        SELECT u.ID, u.display_name, u.user_email, u.user_registered,
               m.membership_status, m.last_login, mp.employer_id, mp.theUsersStatus,
               o.name as organisation_name, mem.name as membership_name, mem.id as membership_id
        FROM {$wpdb->users} u
        LEFT JOIN {$wpdb->prefix}test_iipm_members m ON u.ID = m.user_id
        LEFT JOIN {$wpdb->prefix}test_iipm_member_profiles mp ON u.ID = mp.user_id
        LEFT JOIN {$wpdb->prefix}test_iipm_organisations o ON mp.employer_id = o.id
        LEFT JOIN {$wpdb->prefix}memberships mem ON m.membership_level = mem.id
        {$where_clause}
        ORDER BY u.display_name ASC
        LIMIT %d OFFSET %d
    ";
    
    $users_params = array_merge($where_params, array($per_page, $offset));
    $users = $wpdb->get_results($wpdb->prepare($users_sql, $users_params));
    
    // Process users data
    $processed_users = array();
    foreach ($users as $user) {
        $wp_user = get_user_by('id', $user->ID);
        $user_roles = $wp_user->roles;
        
        // Get membership name from database, fallback to 'Member' if not found
        $membership_display = $user->membership_name ?: 'Member';
        
        // Check CPD submission status for current year
        $current_year = date('Y');
        $cpd_submitted = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}test_iipm_submissions WHERE user_id = %d AND year = %s",
            $user->ID,
            $current_year
        )) > 0;
        
        $processed_users[] = array(
            'ID' => $user->ID,
            'display_name' => $user->display_name,
            'user_email' => $user->user_email,
            'membership_status' => $user->membership_status ?: 'pending',
            'organisation_name' => $user->organisation_name,
            'last_login' => $user->last_login ? date('M j, Y g:i A', strtotime($user->last_login)) : null,
            'role_display' => $membership_display,
            'membership_id' => $user->membership_id,
            'membership_name' => $user->membership_name,
            'roles' => $user_roles,
            'cpd_submitted' => $cpd_submitted
        );
    }
    
    // Calculate pagination
    $total_pages = ceil($total_users / $per_page);
    
    wp_send_json_success(array(
        'users' => $processed_users,
        'pagination' => array(
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_users' => $total_users
        )
    ));
}
add_action('wp_ajax_iipm_get_users', 'iipm_get_users');

// Get membership levels for dropdowns
function iipm_get_membership_levels() {
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
    
    // Get all membership levels from the memberships table
    $memberships = $wpdb->get_results("
        SELECT id, name, designation, fee, cpd_requirement 
        FROM {$wpdb->prefix}memberships 
        ORDER BY name ASC
    ");
    
    wp_send_json_success(array(
        'memberships' => $memberships
    ));
}
add_action('wp_ajax_iipm_get_membership_levels', 'iipm_get_membership_levels');

// Get user details for editing
function iipm_get_user_details() {
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
    
    $user_id = intval($_POST['user_id']);
    if (!$user_id) {
        wp_send_json_error('Invalid user ID');
        return;
    }
    
    // Check if org admin can edit this user
    $current_user = wp_get_current_user();
    $is_site_admin = current_user_can('administrator');
    
    if (!$is_site_admin) {
        global $wpdb;
        $current_org_id = $wpdb->get_var($wpdb->prepare(
            "SELECT employer_id FROM {$wpdb->prefix}test_iipm_member_profiles WHERE user_id = %d",
            $current_user->ID
        ));
        
        $target_org_id = $wpdb->get_var($wpdb->prepare(
            "SELECT employer_id FROM {$wpdb->prefix}test_iipm_member_profiles WHERE user_id = %d",
            $user_id
        ));
        
        if ($current_org_id != $target_org_id) {
            wp_send_json_error('You can only edit users from your organisation');
            return;
        }
    }
    
    $user = get_user_by('id', $user_id);
    if (!$user) {
        wp_send_json_error('User not found');
        return;
    }
    
    global $wpdb;
    $member_data = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}test_iipm_members WHERE user_id = %d",
        $user_id
    ));

    $member_profile_data = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}test_iipm_member_profiles WHERE user_id = %d",
        $user_id
    ));

    error_log(print_r($member_profile_data, true));
    
    wp_send_json_success(array(
        'ID' => $user->ID,
        'first_name' => $member_profile_data->first_name,
        'last_name' => $member_profile_data->sur_name,
        'user_email' => $user->user_email,
        'roles' => array($member_profile_data->theUsersStatus),
        'membership_status' => $member_data ? $member_data->membership_status : 'pending',
        "membership_level" => $member_data ? $member_data->membership_level : 0,
        'employer_id' => $member_profile_data->employer_id,
        'last_login' => $member_data ? ($member_data->last_login ? date('M j, Y g:i A', strtotime($member_data->last_login)) : 'Never') : 'Never'
    ));
}
add_action('wp_ajax_iipm_get_user_details', 'iipm_get_user_details');

// Update user
function iipm_update_user() {
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
    
    $user_id = intval($_POST['user_id']);
    $first_name = sanitize_text_field($_POST['first_name']);
    $last_name = sanitize_text_field($_POST['last_name']);
    $email = sanitize_email($_POST['email']);
    $membership = sanitize_text_field($_POST['membership']);
    $status = sanitize_text_field($_POST['status']);
    $employer_id = intval($_POST['employer_id'] ?? 0);
    
    if (!$user_id || !$first_name || !$last_name || !$email) {
        wp_send_json_error('Missing required fields');
        return;
    }
    
    // Check if org admin can edit this user
    $current_user = wp_get_current_user();
    $is_site_admin = current_user_can('administrator');
    
    if (!$is_site_admin) {
        global $wpdb;
        $current_org_id = $wpdb->get_var($wpdb->prepare(
            "SELECT employer_id FROM {$wpdb->prefix}test_iipm_member_profiles WHERE user_id = %d",
            $current_user->ID
        ));
        
        $target_org_id = $wpdb->get_var($wpdb->prepare(
            "SELECT employer_id FROM {$wpdb->prefix}test_iipm_member_profiles WHERE user_id = %d",
            $user_id
        ));
        
        if ($current_org_id != $target_org_id) {
            wp_send_json_error('You can only edit users from your organisation');
            return;
        }
        
        // Org admins cannot assign administrator role
        if ($membership === 'Admin' || $membership === 'Systems Admin') {
            wp_send_json_error('You cannot assign administrator role');
            return;
        }
    }
    
    // Check if email already exists for another user
    $existing_user = get_user_by('email', $email);
    if ($existing_user && $existing_user->ID != $user_id) {
        wp_send_json_error('Email already exists for another user');
        return;
    }
    
    // Get current membership level to detect changes
    global $wpdb;
    $current_membership_data = $wpdb->get_row($wpdb->prepare(
        "SELECT m.membership_level, mem.designation 
         FROM {$wpdb->prefix}test_iipm_members m 
         LEFT JOIN {$wpdb->prefix}memberships mem ON m.membership_level = mem.id 
         WHERE m.user_id = %d",
        $user_id
    ));
    
    $current_membership_level = $current_membership_data ? $current_membership_data->membership_level : null;
    $current_designation = $current_membership_data ? $current_membership_data->designation : null;
    
    // Determine new membership level ID
    $new_membership_level = null;
    if ($membership !== 'Admin' && $membership !== 'Systems Admin' && is_numeric($membership)) {
        $new_membership_level = intval($membership);
    }
    
    // Check if membership level has changed
    $membership_changed = ($current_membership_level != $new_membership_level);
    
    // Get new designation if membership level changed
    $new_designation = null;
    if ($membership_changed && $new_membership_level) {
        $new_designation = $wpdb->get_var($wpdb->prepare(
            "SELECT designation FROM {$wpdb->prefix}memberships WHERE id = %d",
            $new_membership_level
        ));
    }
    
    // Check if employer is being changed and handle admin role cleanup
    $current_employer = $wpdb->get_var($wpdb->prepare(
        "SELECT employer_id FROM {$wpdb->prefix}test_iipm_member_profiles WHERE user_id = %d",
        $user_id
    ));
    
    $was_org_admin = false; // Initialize variable
    
    // If employer is being changed, check if user was admin of original organisation
    if ($current_employer != $employer_id) {
        // Check if user was admin of the original organisation
        $user = get_user_by('id', $user_id);
        if ($user) {
            $org_admin_email = $wpdb->get_var($wpdb->prepare(
                "SELECT admin_email FROM {$wpdb->prefix}test_iipm_organisations WHERE id = %d AND admin_name IS NOT NULL AND admin_email IS NOT NULL",
                $current_employer
            ));
            
            // If user was admin of original organisation, remove admin role
            if ($org_admin_email && $org_admin_email === $user->user_email) {
                $was_org_admin = true;
                $wpdb->update(
                    $wpdb->prefix . 'test_iipm_organisations',
                    array('admin_name' => null, 'admin_email' => null),
                    array('id' => $current_employer),
                    array('%s', '%s'),
                    array('%d')
                );
                
                // Log the admin role removal
                error_log("IIPM: Removed admin role from organisation ID {$current_employer} for user ID {$user_id} due to employer change");
            }
        }
    }
    
    // Update user data
    $user_data = array(
        'ID' => $user_id,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'display_name' => $first_name . ' ' . $last_name,
        'user_email' => $email
    );

    // Add password to user data if provided
    if (!empty($_POST['password'])) {
        $user_data['user_pass'] = $_POST['password'];
    }
    
    $result = wp_update_user($user_data);
    
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
        return;
    }
    
    // Update user role - set default subscriber role for all membership levels
    $user = new WP_User($user_id);
    $user->set_role('subscriber');
    
    // Get membership level ID - membership parameter contains the membership ID
    $membership_id = null;
    if (is_numeric($membership)) {
        $membership_id = intval($membership);
    }
    
    // Update membership status and level
    // Get membership name for theUsersStatus field
    $membership_name = $membership;
    if (is_numeric($membership)) {
        $membership_name = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}memberships WHERE id = %d",
            intval($membership)
        ));
    }
    
    // Prepare member profiles update data
    $profile_update_data = array(
        'theUsersStatus' => $membership_name, 
        'email_address' => $email, 
        'first_name' => $first_name, 
        'sur_name' => $last_name, 
        'user_fullName' => $first_name . ' ' . $last_name,
        'user_is_admin' => ($membership === 'Admin' || $membership === 'Systems Admin') ? 1 : 0,
        'employer_id' => $employer_id,
        'dateOfUpdateGen' => current_time('mysql')
    );
    
    // Add designation if membership level changed
    if ($membership_changed && $new_designation !== null) {
        $profile_update_data['user_designation'] = $new_designation;
    }
    
    // Prepare format array based on whether designation is included
    $format_array = array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s');
    if ($membership_changed && $new_designation !== null) {
        $format_array[] = '%s'; // Add format for designation
    }
    
    $wpdb->update(
        $wpdb->prefix . 'test_iipm_member_profiles',
        $profile_update_data,
        array('user_id' => $user_id),
        $format_array,
        array('%d')
    );

    $wpdb->update(
        $wpdb->prefix . 'test_iipm_members',
        array(
            'membership_status' => $status,
            'membership_level' => $membership_id
        ),
        array('user_id' => $user_id),
        array('%s', '%d'),
        array('%d')
    );
    
    // Log activity
    $log_message = "Updated user: {$first_name} {$last_name} ({$email})";
    
    // Add membership change info to log if applicable
    if ($membership_changed) {
        $old_membership_name = $current_membership_level ? 
            $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->prefix}memberships WHERE id = %d", $current_membership_level)) : 
            'None';
        $new_membership_name = $membership_name;
        $log_message .= " - Membership changed from '{$old_membership_name}' to '{$new_membership_name}'";
        if ($new_designation) {
            $log_message .= " (Designation: {$new_designation})";
        }
    }
    
    // Add admin role removal info to log if applicable
    if ($current_employer != $employer_id && $was_org_admin == $user_id) {
        $log_message .= " - Removed admin role from previous organisation";
    }
    
    iipm_log_user_activity(
        get_current_user_id(),
        'user_updated',
        $log_message
    );
    
    wp_send_json_success('User updated successfully');
}
add_action('wp_ajax_iipm_update_user', 'iipm_update_user');

// Delete user
function iipm_delete_user() {
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
    
    $user_id = intval($_POST['user_id']);
    if (!$user_id) {
        wp_send_json_error('Invalid user ID');
        return;
    }
    
    // Check if org admin can delete this user
    $current_user = wp_get_current_user();
    $is_site_admin = current_user_can('administrator');
    
    if (!$is_site_admin) {
        global $wpdb;
        $current_org_id = $wpdb->get_var($wpdb->prepare(
            "SELECT employer_id FROM {$wpdb->prefix}test_iipm_member_profiles WHERE user_id = %d",
            $current_user->ID
        ));
        
        $target_org_id = $wpdb->get_var($wpdb->prepare(
            "SELECT employer_id FROM {$wpdb->prefix}test_iipm_member_profiles WHERE user_id = %d",
            $user_id
        ));
        
        if ($current_org_id != $target_org_id) {
            wp_send_json_error('You can only delete users from your organisation');
            return;
        }
    }
    
    // Don't allow deleting administrators unless you're an admin
    $user_to_delete = get_user_by('id', $user_id);
    if ($user_to_delete && in_array('administrator', $user_to_delete->roles) && !$is_site_admin) {
        wp_send_json_error('You cannot delete administrators');
        return;
    }
    
    // Don't allow deleting yourself
    if ($user_id == get_current_user_id()) {
        wp_send_json_error('You cannot delete yourself');
        return;
    }
    
    // Get user info for logging
    $user_info = get_user_by('id', $user_id);
    $user_name = $user_info ? $user_info->display_name : 'Unknown';
    $user_email = $user_info ? $user_info->user_email : 'Unknown';
    
    // Delete related data first
    global $wpdb;
    
    // Delete member profile
    $wpdb->delete(
        $wpdb->prefix . 'test_iipm_member_profiles',
        array('user_id' => $user_id),
        array('%d')
    );
    
    // Delete member record
    $wpdb->delete(
        $wpdb->prefix . 'test_iipm_members',
        array('user_id' => $user_id),
        array('%d')
    );
    
    // Delete user activity logs
    $wpdb->delete(
        $wpdb->prefix . 'test_iipm_user_activity',
        array('user_id' => $user_id),
        array('%d')
    );
    
    // Delete the WordPress user
    $result = wp_delete_user($user_id);
    
    if (!$result) {
        wp_send_json_error('Failed to delete user');
        return;
    }
    
    // Log activity
    iipm_log_user_activity(
        get_current_user_id(),
        'user_deleted',
        "Deleted user: {$user_name} ({$user_email})"
    );
    
    wp_send_json_success('User deleted successfully');
}
add_action('wp_ajax_iipm_delete_user', 'iipm_delete_user');

// Sync missing last_login data
function iipm_sync_last_login_data() {
    // Check permissions
    if (!current_user_can('administrator')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'iipm_user_management_nonce')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    $updated_count = iipm_sync_missing_last_login_data();
    
    wp_send_json_success(array(
        'message' => "Successfully synced {$updated_count} user login timestamps",
        'updated_count' => $updated_count
    ));
}
add_action('wp_ajax_iipm_sync_last_login_data', 'iipm_sync_last_login_data');

// Get all organizations for select box
function iipm_get_all_organizations() {
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
add_action('wp_ajax_iipm_get_all_organizations', 'iipm_get_all_organizations');
?>
