<?php
/**
 * Database Migration Script
 * Migrates from admin_user_id to admin_name and admin_email
 * 
 * Run this script once to update your database schema and migrate existing data
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Migrate organisation admin fields
 * This function should be run once to migrate from admin_user_id to admin_name/admin_email
 */
function iipm_migrate_admin_user_id_to_name_email() {
    global $wpdb;
    
    error_log('IIPM: Starting migration from admin_user_id to admin_name/admin_email');
    
    $table_name = $wpdb->prefix . 'test_iipm_organisations';
    
    // Step 1: Add new columns if they don't exist
    $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}");
    $column_names = wp_list_pluck($columns, 'Field');
    
    if (!in_array('admin_name', $column_names)) {
        $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN admin_name varchar(255) NULL AFTER country");
        error_log('IIPM: Added admin_name column');
    }
    
    if (!in_array('admin_email', $column_names)) {
        $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN admin_email varchar(255) NULL AFTER admin_name");
        error_log('IIPM: Added admin_email column');
    }
    
    // Step 2: Migrate existing data
    $organisations = $wpdb->get_results("SELECT id, admin_user_id FROM {$table_name} WHERE admin_user_id IS NOT NULL");
    
    $migrated_count = 0;
    $failed_count = 0;
    
    foreach ($organisations as $org) {
        $user = get_user_by('id', $org->admin_user_id);
        
        if ($user) {
            $result = $wpdb->update(
                $table_name,
                array(
                    'admin_name' => $user->display_name,
                    'admin_email' => $user->user_email
                ),
                array('id' => $org->id),
                array('%s', '%s'),
                array('%d')
            );
            
            if ($result !== false) {
                $migrated_count++;
                error_log("IIPM: Migrated organisation ID {$org->id} - Admin: {$user->display_name} ({$user->user_email})");
            } else {
                $failed_count++;
                error_log("IIPM: Failed to migrate organisation ID {$org->id}");
            }
        } else {
            $failed_count++;
            error_log("IIPM: User not found for organisation ID {$org->id} (admin_user_id: {$org->admin_user_id})");
        }
    }
    
    error_log("IIPM: Migration completed - Migrated: {$migrated_count}, Failed: {$failed_count}");
    
    // Step 3: Drop old column (optional - comment out if you want to keep it for backup)
    if (in_array('admin_user_id', $column_names)) {
        // Uncomment the following lines after verifying the migration was successful
        // $wpdb->query("ALTER TABLE {$table_name} DROP COLUMN admin_user_id");
        // error_log('IIPM: Removed admin_user_id column');
    }
    
    // Step 4: Add index for admin_email
    $indexes = $wpdb->get_results("SHOW INDEX FROM {$table_name} WHERE Key_name = 'admin_email'");
    if (empty($indexes)) {
        $wpdb->query("ALTER TABLE {$table_name} ADD INDEX admin_email (admin_email)");
        error_log('IIPM: Added index for admin_email');
    }
    
    // Step 5: Remove old index for admin_user_id (if exists)
    $old_indexes = $wpdb->get_results("SHOW INDEX FROM {$table_name} WHERE Key_name = 'admin_user_id'");
    if (!empty($old_indexes)) {
        // Uncomment after verifying migration
        // $wpdb->query("ALTER TABLE {$table_name} DROP INDEX admin_user_id");
        // error_log('IIPM: Removed index for admin_user_id');
    }
    
    return array(
        'success' => true,
        'migrated' => $migrated_count,
        'failed' => $failed_count,
        'message' => "Migration completed. Migrated: {$migrated_count}, Failed: {$failed_count}"
    );
}

/**
 * Admin page to run migration
 */
function iipm_admin_migration_page() {
    if (!current_user_can('administrator')) {
        wp_die('Insufficient permissions');
    }
    
    $result = null;
    if (isset($_POST['run_migration']) && check_admin_referer('iipm_migration', 'iipm_migration_nonce')) {
        $result = iipm_migrate_admin_user_id_to_name_email();
    }
    
    ?>
    <div class="wrap">
        <h1>IIPM Database Migration</h1>
        <div class="card">
            <h2>Migrate Organisation Admin Fields</h2>
            <p>This migration will:</p>
            <ul>
                <li>Add <code>admin_name</code> and <code>admin_email</code> columns to organisations table</li>
                <li>Copy data from existing <code>admin_user_id</code> relationships to the new fields</li>
                <li>Add index for <code>admin_email</code></li>
                <li>(Optional) Remove old <code>admin_user_id</code> column after verification</li>
            </ul>
            
            <?php if ($result): ?>
                <div class="notice notice-<?php echo $result['success'] ? 'success' : 'error'; ?>">
                    <p><?php echo esc_html($result['message']); ?></p>
                </div>
            <?php endif; ?>
            
            <form method="post">
                <?php wp_nonce_field('iipm_migration', 'iipm_migration_nonce'); ?>
                <p>
                    <button type="submit" name="run_migration" class="button button-primary">
                        Run Migration
                    </button>
                </p>
            </form>
            
            <h3>Manual SQL (Alternative)</h3>
            <p>You can also run these SQL commands manually if preferred:</p>
            <pre style="background: #f5f5f5; padding: 15px; overflow-x: auto;">
-- Add new columns
ALTER TABLE `<?php echo $GLOBALS['wpdb']->prefix; ?>test_iipm_organisations` 
    ADD COLUMN `admin_name` varchar(255) NULL AFTER `country`,
    ADD COLUMN `admin_email` varchar(255) NULL AFTER `admin_name`;

-- Add index for admin_email
ALTER TABLE `<?php echo $GLOBALS['wpdb']->prefix; ?>test_iipm_organisations` 
    ADD INDEX `admin_email` (`admin_email`);

-- After verifying data migration, optionally remove old column:
-- ALTER TABLE `<?php echo $GLOBALS['wpdb']->prefix; ?>test_iipm_organisations` 
--     DROP COLUMN `admin_user_id`,
--     DROP INDEX `admin_user_id`;
            </pre>
        </div>
    </div>
    <?php
}

// Add admin menu item
add_action('admin_menu', function() {
    add_management_page(
        'IIPM Migration',
        'IIPM Migration',
        'manage_options',
        'iipm-migration',
        'iipm_admin_migration_page'
    );
});

