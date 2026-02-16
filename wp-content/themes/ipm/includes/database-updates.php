<?php
/**
 * Database Updates and Migrations
 */

/**
 * Update invitations table to add profile_data column
 */
function iipm_update_invitations_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'test_iipm_invitations';
    
    // Check if profile_data column exists
    $column_exists = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM `{$table_name}` LIKE %s",
        'profile_data'
    ));
    
    if (empty($column_exists)) {
        // Add the missing column
        $sql = "ALTER TABLE `{$table_name}` ADD COLUMN `profile_data` TEXT NULL AFTER `organisation_id`";
        
        $result = $wpdb->query($sql);
        
        if ($result === false) {
            error_log('IIPM: Failed to add profile_data column: ' . $wpdb->last_error);
            return false;
        } else {
            error_log('IIPM: Successfully added profile_data column to invitations table');
            return true;
        }
    } else {
        error_log('IIPM: profile_data column already exists');
        return true;
    }
}

/**
 * Add is_test_user column to member_profiles table for deploy/test mode
 */
function iipm_add_is_test_user_column() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'test_iipm_member_profiles';

    $column_exists = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM `{$table_name}` LIKE %s",
        'is_test_user'
    ));

    if (empty($column_exists)) {
        $sql = "ALTER TABLE `{$table_name}` ADD COLUMN `is_test_user` tinyint(1) DEFAULT 0 AFTER `user_id`";
        $result = $wpdb->query($sql);
        if ($result === false) {
            error_log('IIPM: Failed to add is_test_user column: ' . $wpdb->last_error);
            return false;
        }
        return true;
    }
    return true;
}

/**
 * Create forgo_requirements table
 */
function iipm_create_forgo_requirements_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'test_iipm_forgo_requirements';
    
    // Check if table exists
    $table_exists = $wpdb->get_var($wpdb->prepare(
        "SHOW TABLES LIKE %s",
        $table_name
    ));
    
    if ($table_exists) {
        error_log('IIPM: forgo_requirements table already exists');
        return true;
    }
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE `{$table_name}` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `category_ids` varchar(255) NULL DEFAULT NULL,
        `cpd_year` int(11) NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `user_year` (`user_id`, `cpd_year`),
        KEY `user_id` (`user_id`),
        KEY `cpd_year` (`cpd_year`)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    if ($wpdb->last_error) {
        error_log('IIPM: Failed to create forgo_requirements table: ' . $wpdb->last_error);
        return false;
    } else {
        error_log('IIPM: Successfully created forgo_requirements table');
        return true;
    }
}

/**
 * Run all database updates
 */
function iipm_run_database_updates() {
    // Update invitations table
    iipm_update_invitations_table();

    // Add is_test_user to member_profiles
    iipm_add_is_test_user_column();

    // Create forgo_requirements table
    iipm_create_forgo_requirements_table();
    
    // You can add more database updates here in the future
    
    // Update the database version
    update_option('iipm_db_version', '1.4');
}

/**
 * Check if database updates are needed
 */
function iipm_check_database_updates() {
    $current_version = get_option('iipm_db_version', '1.0');
    $required_version = '1.4';
    
    if (version_compare($current_version, $required_version, '<')) {
        iipm_run_database_updates();
    }
}

// Run database updates on admin init and init (for frontend admin pages like member-details)
add_action('admin_init', 'iipm_check_database_updates');
add_action('init', 'iipm_check_database_updates', 5);

/**
 * Manual database update function (for testing)
 */
function iipm_manual_database_update() {
    if (!current_user_can('administrator')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    if (!wp_verify_nonce($_POST['nonce'], 'iipm_portal_nonce')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    $result = iipm_update_invitations_table();
    
    if ($result) {
        wp_send_json_success('Database updated successfully');
    } else {
        wp_send_json_error('Failed to update database');
    }
}
add_action('wp_ajax_iipm_update_database', 'iipm_manual_database_update');
?>
