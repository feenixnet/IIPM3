<?php
/**
 * Template Name: Super Admin Management
 * 
 * This template is for managing super admins and is only accessible by super admins.
 */

// Include notification system if not already loaded
if (!function_exists('add_success_notification')) {
    include_once get_template_directory() . '/includes/notification-system.php';
}

// Global functions are now available from global-functions.php

// Redirect non-super admins to home page
if (!is_super_admin()) {
    wp_redirect(home_url());
    exit;
}

get_header();
?>

<style>
/* Reset WordPress defaults */
#wpcontent {
    padding: 0 !important;
}

#wpbody-content {
    padding: 0 !important;
}

.wrap {
    margin: 0 !important;
    padding: 0 !important;
}

/* Base Layout */
/* .iipm-dashboard-page {
    min-height: 100vh;
    background: transparent;
    margin: 0;
    padding: 0;
}

.main-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
} */

/* Hero Section */
.dashboard-hero {
    background: linear-gradient(135deg, #715091 0%, #715091 100%);
    padding: 40px 0;
    color: white;
    position: relative;
    margin: 0;
}

.hero-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 40px;
    margin: 0;
    padding: 0;
}

.welcome-section h1 {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0 0 16px 0;
    color: white;
}

.user-role {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 12px;
}

.role-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 500;
}

.role-badge.admin {
    background-color: #f8a135;
    color: white;
}

.user-email {
    color: rgba(255, 255, 255, 0.9);
    font-size: 14px;
}

.dashboard-avatar {
    flex-shrink: 0;
}

.avatar-circle {
    width: 80px;
    height: 80px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    font-weight: 600;
    color: white;
}

/* Message Container */
#iipm-message-container {
    max-width: 1200px;
    margin: 20px auto;
    padding: 0 20px;
}

/* Dashboard Content */
.dashboard-content {
    padding: 20px 0 40px;
    position: relative;
    background: #f0f0f1;
    margin: 0;
}

/* Cards */
.dashboard-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    margin-bottom: 30px;
}

.card-header {
    background: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #e5e5e5;
}

.card-header h2 {
    color: #715091;
    margin: 0;
    font-size: 18px;
    font-weight: 600;
}

.card-content {
    padding: 20px;
}

/* Form Fields */
.form-field {
    margin-bottom: 20px;
}

.form-field label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
}

.form-field input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.form-submit {
    margin-top: 30px;
}

/* Message Styling */
.iipm-message {
    padding: 15px 20px;
    border-radius: 4px;
    margin-bottom: 20px;
    position: relative;
    display: flex;
    justify-content: space-between;
    align-items: center;
    animation: slideDown 0.3s ease-out;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

/* Action Buttons Styling */
.action-buttons {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}

.action-buttons .button {
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    background: #f0f0f1;
    color: #715091;
    min-width: 120px;
    text-align: center;
    margin: 0;
}

.action-buttons .button:hover {
    background: #e5e5e5;
    color: #715091;
}

.action-buttons .button-primary {
    background: #715091;
    color: white;
}

.action-buttons .button-primary:hover {
    background: #715091;
    color: white;
}

.action-buttons .button-link-delete {
    color: #dc3232;
    background: transparent;
    border: 1px solid #dc3232;
}

.action-buttons .button-link-delete:hover {
    background: #dc3232;
    color: white;
}

.action-buttons form {
    margin: 0;
    padding: 0;
}

.current-user-badge {
    background: #f8a135;
    color: white;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
}

/* Table Styling */
.wp-list-table {
    border: 1px solid #e5e5e5;
    border-radius: 4px;
    overflow: hidden;
}

.wp-list-table th {
    background: #f8f9fa;
    color: #715091;
    padding: 12px;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid #e5e5e5;
}

.wp-list-table td {
    padding: 12px;
    vertical-align: middle;
    border-bottom: 1px solid #e5e5e5;
}

.wp-list-table tr:last-child td {
    border-bottom: none;
}

.wp-list-table tr:hover {
    background-color: #f8f9fa;
}

/* Modal Dialog Styling */
.password-modal {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.password-modal h3 {
    color: #715091;
    margin-top: 0;
}

.password-modal .button-group {
    margin-top: 20px;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}
</style>

<?php
$current_user = wp_get_current_user();

// Handle password update
if (isset($_POST['update_password']) && isset($_POST['user_id']) && isset($_POST['new_password'])) {
    if (!wp_verify_nonce($_POST['update_password_nonce'], 'update_password_action')) {
        die('Security check failed');
    }

    $user_id = intval($_POST['user_id']);
    $new_password = $_POST['new_password'];
    
    // Update user password
    wp_set_password($new_password, $user_id);
    
    // Log the password change
    iipm_log_super_admin_change($user_id, 'password updated');
    
    // Show success notification immediately
    echo iipm_notification_script('success', 'Password Updated', 'Super admin password has been updated successfully.');
}

// Handle delete super admin
if (isset($_POST['delete_super_admin']) && isset($_POST['user_id'])) {
    if (!wp_verify_nonce($_POST['delete_super_admin_nonce'], 'delete_super_admin_action')) {
        die('Security check failed');
    }

    $user_id = intval($_POST['user_id']);
    $current_user_id = get_current_user_id();

    // Prevent deleting yourself
    if ($user_id === $current_user_id) {
        echo iipm_notification_script('error', 'Cannot Delete Account', 'You cannot delete your own account.');
    } else {
        // Remove super admin role first
        revoke_super_admin($user_id);
        
        // Get user info before deletion for the message
        $user = get_userdata($user_id);
        $user_name = $user ? $user->display_name : 'User';
        
        // Delete the user
        if (wp_delete_user($user_id)) {
            echo iipm_notification_script('success', 'Account Deleted', "Successfully deleted super admin account for {$user_name}.");
            iipm_log_super_admin_change($user_id, 'account deleted');
        } else {
            echo iipm_notification_script('error', 'Delete Failed', 'Failed to delete user account.');
        }
    }
}

// Handle remove super admin privileges
if (isset($_POST['remove_super_admin']) && isset($_POST['user_id'])) {
    if (!wp_verify_nonce($_POST['remove_super_admin_nonce'], 'remove_super_admin_action')) {
        die('Security check failed');
    }

    $user_id = intval($_POST['user_id']);
    $current_user_id = get_current_user_id();

    // Prevent removing yourself
    if ($user_id === $current_user_id) {
        echo iipm_notification_script('error', 'Cannot Remove Privileges', 'You cannot remove super admin privileges from your own account.');
    } else {
        // Get user info before removal
        $user = get_userdata($user_id);
        $user_name = $user ? $user->display_name : 'User';
        
        // Remove super admin privileges
        if (revoke_super_admin($user_id)) {
            echo iipm_notification_script('success', 'Privileges Removed', "Successfully removed super admin privileges from {$user_name}.");
            iipm_log_super_admin_change($user_id, 'super admin privileges removed');
        } else {
            echo iipm_notification_script('error', 'Remove Failed', 'Failed to remove super admin privileges.');
        }
    }
}

// Handle add new super admin
if (isset($_POST['add_super_admin']) && isset($_POST['super_admin_email'])) {
    if (!wp_verify_nonce($_POST['super_admin_nonce'], 'add_super_admin_action')) {
        die('Security check failed');
    }

    $email = sanitize_email($_POST['super_admin_email']);
    $first_name = sanitize_text_field($_POST['first_name']);
    $last_name = sanitize_text_field($_POST['last_name']);
    $password = $_POST['password'];

    // Check if user exists
    $existing_user = get_user_by('email', $email);

    if ($existing_user) {
        echo iipm_notification_script('error', 'User Exists', 'A user with this email already exists.');
    } else {
        // Create new user
        $userdata = array(
            'user_login'    => $email,
            'user_email'    => $email,
            'user_pass'     => $password,
            'first_name'    => $first_name,
            'last_name'     => $last_name,
            'display_name'  => $first_name . ' ' . $last_name,
            'role'          => 'administrator'
        );

        $user_id = wp_insert_user($userdata);

        if (!is_wp_error($user_id)) {
            // Grant super admin privileges
            grant_super_admin($user_id);
            
            // Update user meta to ensure super admin status
            update_user_meta($user_id, 'wp_user_level', 10);
            
            // Add user to site options
            $super_admins = get_option('wp_user_roles');
            if (is_array($super_admins)) {
                $super_admins['administrator']['capabilities']['manage_network'] = true;
                update_option('wp_user_roles', $super_admins);
            }
            
            // Force refresh of capabilities
            $user = new WP_User($user_id);
            $user->add_cap('manage_network');
            
            echo iipm_notification_script('success', 'Super Admin Added', "Successfully added {$first_name} {$last_name} as a super admin.");
            iipm_log_super_admin_change($user_id, 'added as super admin');
        } else {
            echo iipm_notification_script('error', 'Add Failed', $user_id->get_error_message());
        }
    }
}
?>

<div class="iipm-dashboard-page main-container">
    <!-- Error/Success Message Container -->
    <div id="iipm-message-container"></div>
    
    <!-- Hero Section -->
    <div class="container" style="position: relative; z-index: 2;">
        <div class="page-header" style="text-align: center; margin-bottom: 40px;">
            <div class="welcome-section">
                <h1 style="color: white; font-size: 2.5rem; margin-bottom: 10px;">Super Admin Management</h1>
                <div class="user-role">
                    <span class="role-badge admin">Super Admin</span>
                    <span class="user-email"><?php echo esc_html($current_user->user_email); ?></span>
                </div>
                <?php IIPM_Navigation_Manager::display_breadcrumbs(); ?>
            </div>
        </div>
        <div class="tab-content main-content">
            <!-- Add New Super Admin Section -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h2>Add New Super Admin</h2>
                </div>
                <div class="card-content">
                    <form method="post" action="" class="iipm-form">
                        <?php wp_nonce_field('add_super_admin_action', 'super_admin_nonce'); ?>
                        <div class="form-field">
                            <label for="first_name">First Name</label>
                            <input type="text" name="first_name" id="first_name" class="regular-text" required>
                        </div>
                        <div class="form-field">
                            <label for="last_name">Last Name</label>
                            <input type="text" name="last_name" id="last_name" class="regular-text" required>
                        </div>
                        <div class="form-field">
                            <label for="super_admin_email">Email Address</label>
                            <input type="email" name="super_admin_email" id="super_admin_email" class="regular-text" required>
                        </div>
                        <div class="form-field">
                            <label for="password">Password</label>
                            <input type="password" name="password" id="password" class="regular-text" required>
                            <p class="description">Password should be at least 8 characters long and include numbers and special characters.</p>
                        </div>
                        <div class="form-submit">
                            <button type="submit" name="add_super_admin" class="btn btn-primary btn-large">Add Super Admin</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Current Super Admins Section -->
            <div class="dashboard-card" style="margin-bottom: 0;">
                <div class="card-header">
                    <h2>Current Super Admins</h2>
                </div>
                <div class="card-content">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Email</th>
                                <th scope="col">Username</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Get all users with administrator role
                            $args = array(
                                'role' => 'administrator',
                                'orderby' => 'display_name',
                                'order' => 'ASC'
                            );
                            $admin_users = get_users($args);
                            $current_user_id = get_current_user_id();

                            foreach ($admin_users as $admin): 
                                // Check if user has super admin capabilities
                                if (is_super_admin($admin->ID)):
                            ?>
                                <tr>
                                    <td><?php echo esc_html($admin->display_name); ?></td>
                                    <td><?php echo esc_html($admin->user_email); ?></td>
                                    <td><?php echo esc_html($admin->user_login); ?></td>
                                    <td class="action-buttons">
                                        <?php if ($admin->ID !== $current_user_id): ?>
                                            <!-- Update Password Button -->
                                            <button type="button" class="button button-primary icon-button" 
                                                    onclick="showPasswordModal(<?php echo esc_attr($admin->ID); ?>, '<?php echo esc_attr($admin->display_name); ?>')"
                                                    title="Update Password">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            
                                            <!-- Delete Account Button -->
                                            <form method="post" action="">
                                                <?php wp_nonce_field('delete_super_admin_action', 'delete_super_admin_nonce'); ?>
                                                <input type="hidden" name="user_id" value="<?php echo esc_attr($admin->ID); ?>">
                                                <button type="submit" name="delete_super_admin" class="button button-link-delete icon-button" 
                                                        onclick="return confirm('Are you sure you want to delete this super admin account? This action cannot be undone.');"
                                                        title="Delete Account">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>

                                            <!-- Remove Super Admin Button -->
                                            <form method="post" action="">
                                                <?php wp_nonce_field('remove_super_admin_action', 'remove_super_admin_nonce'); ?>
                                                <input type="hidden" name="user_id" value="<?php echo esc_attr($admin->ID); ?>">
                                                <button type="submit" name="remove_super_admin" class="button" 
                                                        onclick="return confirm('Are you sure you want to remove super admin privileges from this user?');">
                                                    <i class="fas fa-user-minus"></i>
                                                    Remove Privileges
                                                </button>
                                            </form>

                                        <?php else: ?>
                                            <span class="current-user-badge">Current User</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Password Update Modal -->
<div id="password-modal" class="iipm-modal">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <h3>Update Password</h3>
        <p id="modal-user-name"></p>
        <form method="post" action="" class="iipm-form">
            <?php wp_nonce_field('update_password_action', 'update_password_nonce'); ?>
            <input type="hidden" name="user_id" id="modal-user-id">
            <div class="form-field">
                <label for="new_password">New Password</label>
                <input type="password" name="new_password" id="new_password" class="regular-text" required>
                <p class="description">Password should be at least 8 characters long and include numbers and special characters.</p>
            </div>
            <div class="form-submit">
                <button type="submit" name="update_password" class="btn btn-primary btn-large">Update Password</button>
            </div>
        </form>
    </div>
</div>

<style>
/* Hero Section Styling */
.dashboard-hero {
    position: relative;
    background: linear-gradient(135deg, #715091 0%, #715091 100%);
    padding: 40px 0;
    margin-bottom: 30px;
    z-index: 2;
}

.dashboard-content {
    position: relative;
    z-index: 1;
    margin-top: -20px;
    padding: 0 20px;
}

.dashboard-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    margin-bottom: 30px;
    overflow: hidden;
}

.card-header {
    background: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #e5e5e5;
}

.card-header h2 {
    color: #715091;
    margin: 0;
    font-size: 18px;
    font-weight: 600;
}

.card-content {
    padding: 20px;
}

/* Message Container */
#iipm-message-container {
    position: relative;
    z-index: 3;
    max-width: 1200px;
    margin: -20px auto 20px;
    padding: 0 20px;
}

/* Existing Message Styling */
.iipm-message {
    padding: 15px 20px;
    border-radius: 4px;
    margin-bottom: 20px;
    position: relative;
    display: flex;
    justify-content: space-between;
    align-items: center;
    animation: slideDown 0.3s ease-out;
    background: #fff;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

/* Form Styling */
.iipm-form .form-field {
    margin-bottom: 24px;
}

.iipm-form label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
}

.iipm-form .regular-text {
    width: 100%;
    max-width: 400px;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.iipm-form .description {
    margin-top: 8px;
    color: #666;
    font-style: italic;
}

.form-submit {
    margin-top: 24px;
}

/* Table Styling */
.wp-list-table {
    border: 1px solid #e5e5e5;
}

.wp-list-table th {
    font-weight: 600;
}

.wp-list-table td, 
.wp-list-table th {
    padding: 12px;
}

/* Current User Badge */
.current-user-badge {
    display: inline-block;
    padding: 4px 12px;
    background-color: #f8a135;
    color: white;
    border-radius: 20px;
    font-size: 13px;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.icon-button {
    width: 40px;
    height: 40px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    border: 1px solid #ddd;
    background: white;
    color: #333;
    text-decoration: none;
    transition: all 0.2s ease;
}

.icon-button:hover {
    background: #f5f5f5;
    border-color: #999;
}

.icon-button i {
    font-size: 16px;
    margin: 0;
}

.action-buttons .button {
    margin: 2px;
}

/* Modal Styles */
.iipm-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: #fff;
    margin: 10% auto;
    padding: 24px;
    border-radius: 8px;
    max-width: 500px;
    position: relative;
}

.close-modal {
    position: absolute;
    right: 24px;
    top: 24px;
    font-size: 24px;
    cursor: pointer;
    color: #666;
}

.close-modal:hover {
    color: #333;
}

#modal-user-name {
    margin-bottom: 20px;
    color: #666;
}

/* Responsive Adjustments */
@media screen and (max-width: 782px) {
    .hero-content {
        flex-direction: column;
        text-align: center;
        gap: 24px;
    }

    .user-role {
        justify-content: center;
        flex-wrap: wrap;
    }

    .welcome-section h1 {
        font-size: 2rem;
    }

    .card-content {
        padding: 16px;
    }

    .iipm-form .regular-text {
        max-width: 100%;
    }

    .action-buttons {
        flex-direction: column;
    }
    
    .action-buttons .button {
        width: 100%;
        text-align: center;
    }
    
    .modal-content {
        margin: 20px;
        width: auto;
    }
}
</style>

<script>
function showPasswordModal(userId, userName) {
    const modal = document.getElementById('password-modal');
    const userNameElement = document.getElementById('modal-user-name');
    const userIdInput = document.getElementById('modal-user-id');
    
    userNameElement.textContent = `Updating password for ${userName}`;
    userIdInput.value = userId;
    modal.style.display = 'block';
}

// Close modal when clicking the close button or outside the modal
document.querySelector('.close-modal').onclick = function() {
    document.getElementById('password-modal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('password-modal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}
</script>

<?php get_footer(); ?>
