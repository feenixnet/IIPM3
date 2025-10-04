<?php
/**
 * Template Name: User Management
 * 
 * User Management page for Site Admins and Organization Admins
 */

 // Storm worked here...111

// Security check - only allow admins and org admins
if (!is_user_logged_in()) {
    wp_redirect(home_url('/login/'));
    exit;
}

$current_user = wp_get_current_user();
$is_site_admin = current_user_can('administrator');
$user_organisation = null;
$is_org_admin = in_array('iipm_corporate_admin', $current_user->roles) || current_user_can('manage_organisation_members');

if (!$is_site_admin && !$is_org_admin) {
    wp_redirect(home_url('/dashboard/'));
    exit;
}

if ($is_org_admin) {
    global $wpdb;
    $user_organisation = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}test_iipm_organisations WHERE admin_user_id = %d",
        $current_user->ID
    ));
}

// Get the active tab from URL, default to 'users'
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'users';

get_header();

// Include notification system if not already loaded
if (!function_exists('add_success_notification')) {
    include_once get_template_directory() . '/includes/notification-system.php';
}
?>

<main class="user-management-page main-container">
    <div class="container" style="position: relative; z-index: 2;">
        
        <!-- Page Header -->
        <div class="page-header" style="text-align: center; margin-bottom: 40px;">
            <div>
                <h1 style="color: white; font-size: 2.5rem; margin-bottom: 10px;">User Management</h1>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="tab-navigation" style="margin-bottom: 30px;">
            <div style="display: flex; justify-content: center; gap: 20px;">
                <a href="?tab=users" class="tab-button <?php echo $active_tab === 'users' ? 'active' : ''; ?>" 
                   style="padding: 12px 24px; background: <?php echo $active_tab === 'users' ? '#f8a135' : '#6b4c93'; ?>; color: white; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s ease;">
                    <span style="margin-right: 8px;"><i class="fas fa-users"></i></span>
                    Users
                </a>
                <a href="?tab=invitations" class="tab-button <?php echo $active_tab === 'invitations' ? 'active' : ''; ?>"
                   style="padding: 12px 24px; background: <?php echo $active_tab === 'invitations' ? '#f8a135' : '#6b4c93'; ?>; color: white; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s ease;">
                    <span style="margin-right: 8px;"><i class="fas fa-envelope"></i></span>
                    Add user
                </a>
                <a href="?tab=membership" class="tab-button <?php echo $active_tab === 'membership' ? 'active' : ''; ?>"
                   style="padding: 12px 24px; background: <?php echo $active_tab === 'membership' ? '#f8a135' : '#6b4c93'; ?>; color: white; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s ease;">
                    <span style="margin-right: 8px;"><i class="fas fa-id-card"></i></span>
                    Membership Levels
                </a>
            </div>
        </div>

        <!-- Tab Content -->
        <div class="tab-content main-content">
            <?php if ($active_tab === 'users'): ?>
                <!-- Users Management Content -->
                <div class="users-content">
                    <!-- Action Buttons -->
                    <div style="display: flex; justify-content: flex-end; gap: 20px; margin-bottom: 30px;">
                        <?php if ($is_site_admin): ?>
                        <!-- <button id="sync-login-data" style="display: inline-flex; align-items: center; padding: 12px 24px; background: #10B981; color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                            <span style="margin-right: 8px;"><i class="fas fa-sync-alt"></i></span>
                            Sync Login Data
                        </button> -->
                        <?php endif; ?>
                        <a href="<?php echo home_url('/bulk-import/'); ?>" style="display: inline-flex; align-items: center; padding: 12px 24px; background: #8B5CF6; color: white; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s ease; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                            <span style="margin-right: 8px;"><i class="fas fa-upload"></i></span>
                            Bulk User Upload
                        </a>
                    </div>

                    <!-- Search and Filter Section -->
                    <div class="search-filter-section" style="margin-bottom: 30px; display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                        <div class="search-box" style="flex: 1; min-width: 250px;">
                            <input type="text" id="user-search" placeholder="Search users by name or email..." 
                                   style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px;">
                        </div>
                        <div class="user-count-display" style="padding: 12px 16px; background: #f8fafc; border: 2px solid #e5e7eb; border-radius: 8px; font-weight: 500; color: #374151; min-width: 120px; text-align: center;">
                            <span id="user-count-text">Loading...</span>
                        </div>
                        
                        <div class="filter-controls" style="display: flex; gap: 15px; align-items: center;">
                            <select id="membership-filter" style="padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px;">
                                <option value="">All Membership Levels</option>
                                <!-- Membership levels will be loaded dynamically -->
                            </select>
                            
                            <select id="status-filter" style="padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px;">
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="pending">Pending</option>
                                <option value="suspended">Suspended</option>
                                <option value="lapsed">Lapsed</option>
                                <option value="revoked">Revoked</option>
                                <option value="resigned">Resigned</option>
                                <option value="retired">Retired</option>
                                <option value="leftsector">Left Sector</option>
                                <option value="paused">Paused</option>
                                <option value="deceased">Deceased</option>
                            </select>
                            
                            <button id="refresh-users" style="padding: 12px 20px; background: #667eea; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">
                                Refresh
                            </button>
                        </div>
                    </div>

                    <!-- Users Table -->
                    <div class="users-table-container" style="overflow-x: auto; border: 1px solid #e5e7eb; border-radius: 12px;">
                        <table id="users-table" class="users-table-sticky" style="width: 150%; border-collapse: collapse;">
                            <thead style="background: #f8fafc;">
                                <tr>
                                    <th class="sticky-col sticky-name" style="padding: 16px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Name</th>
                                    <th class="sticky-col sticky-email" style="padding: 16px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Email</th>
                                    <th style="padding: 16px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Membership Level</th>
                                    <th style="padding: 16px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Status</th>
                                    <th style="padding: 16px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Organization</th>
                                    <th style="padding: 16px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Last Login</th>
                                    <th style="padding: 16px; text-align: center; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">CPD Status</th>
                                    <th style="padding: 16px; text-align: center; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="users-table-body">
                                <!-- Users will be loaded here via AJAX -->
                                <tr>
                                    <td colspan="8" style="padding: 40px; text-align: center; color: #6b7280;">
                                        <div class="loading-spinner" style="display: inline-block; width: 20px; height: 20px; border: 2px solid #e5e7eb; border-top: 2px solid #667eea; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                                        <span style="margin-left: 10px;">Loading users...</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div id="pagination-container" style="margin-top: 20px; display: flex; justify-content: center; align-items: center; gap: 10px;">
                        <!-- Pagination will be loaded here -->
                    </div>
                </div>
            <?php elseif ($active_tab === 'invitations'): ?>
                <!-- Invitations Content -->
                <div class="invitations-content">
                    <div class="invitation-form-container">
                        <h2>Send Individual Invitation</h2>
                        <form id="send-invitation-form" class="invitation-form">
                            <?php wp_nonce_field('iipm_user_management_nonce', 'nonce'); ?>
                            
                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input type="email" name="email" id="email" required>
                            </div>
                            
                            <div class="form-group" style="display:none;">
                                <label for="type">Invitation Type *</label>
                                <select name="type" id="type" required>
                                    <option value="individual">Individual Member</option>
                                </select>
                            </div>
                            
                            <?php if ($is_org_admin && !$is_site_admin): ?>
                                <!-- Corporate admin - auto-set organization -->
                                <input type="hidden" name="organisation_id" value="<?php echo $user_organisation->id; ?>">
                                <div class="form-group">
                                    <label>Organization</label>
                                    <div class="readonly-field">
                                        <span class="organisation-badge">
                                            <i class="fas fa-building"></i> <?php echo esc_html($user_organisation->name); ?>
                                        </span>
                                        <small class="field-note">Invitations will be sent for your organization</small>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- Site admin - can select organization -->
                                <div class="form-group organisation-field" style="display:none;">
                                    <label for="organisation_id">Organization</label>
                                    <div class="custom-select-container">
                                        <div class="custom-select" id="organisation-select">
                                            <div class="select-trigger">
                                                <span class="select-placeholder">Select Organization</span>
                                                <i class="fas fa-chevron-down"></i>
                                            </div>
                                            <div class="select-dropdown">
                                                <div class="select-search">
                                                    <input type="text" id="organisation-search" placeholder="Search organizations...">
                                                </div>
                                                <div class="select-options" id="organisation-options">
                                                    <!-- Options will be loaded here -->
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <input type="hidden" name="organisation_id" id="organisation_id">
                                </div>
                            <?php endif; ?>
                            
                            <button type="submit" class="btn btn-primary">Send Invitation</button>
                        </form>
                        
                        <div id="invitation-result" style="display:none;"></div>
                    </div>
                    
                    <div class="recent-invitations">
                        <h2>Recent Invitations</h2>
                        <div class="table-container">
                            <table class="invitations-table">
                                <thead>
                                    <tr>
                                        <th>Email</th>
                                        <th>Type</th>
                                        <?php if ($is_site_admin): ?>
                                            <th>Organization</th>
                                        <?php endif; ?>
                                        <th>Status</th>
                                        <th>Sent Date</th>
                                        <th>Expires</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="invitations-table-body">
                                    <tr>
                                        <td colspan="<?php echo $is_site_admin ? '7' : '6'; ?>" style="text-align: center; padding: 20px;">
                                            <div class="loading-spinner"></div>
                                            Loading invitations...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php elseif ($active_tab === 'membership'): ?>
                <!-- Membership Management Content -->
                <div class="membership-content">
                    <div class="membership-overview">
                        <div style="text-align: center; margin-bottom: 40px;">
                            <h2 style="font-size: 2.5rem; font-weight: 700; margin: 0 0 15px 0; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">Membership Overview</h2>
                            <p style="margin: 0; font-size: 16px; font-weight: 400; max-width: 600px; margin: 0 auto;">
                                Manage membership levels, view comprehensive statistics, and configure membership settings with ease.
                            </p>
                        </div>
                        
                        <!-- Membership Statistics Cards -->
                        <div class="membership-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; margin-bottom: 40px;">
                            <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 25px; border-radius: 16px; box-shadow: 0 8px 32px rgba(102, 126, 234, 0.3); position: relative; overflow: hidden;">
                                <div style="position: absolute; top: -20px; right: -20px; width: 80px; height: 80px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
                                <div style="display: flex; align-items: center; justify-content: space-between; position: relative; z-index: 2;">
                                    <div>
                                        <h3 style="color: white; margin: 0; font-size: 2.5rem; font-weight: 800; text-shadow: 0 2px 4px rgba(0,0,0,0.2);" id="total-memberships">-</h3>
                                        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0 0; font-size: 14px; font-weight: 500;">Total Membership Levels</p>
                                    </div>
                                    <div style="background: rgba(255,255,255,0.2); padding: 18px; border-radius: 50%; backdrop-filter: blur(10px);">
                                        <i class="fas fa-id-card" style="color: white; font-size: 28px;"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); padding: 25px; border-radius: 16px; box-shadow: 0 8px 32px rgba(240, 147, 251, 0.3); position: relative; overflow: hidden;">
                                <div style="position: absolute; top: -20px; right: -20px; width: 80px; height: 80px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
                                <div style="display: flex; align-items: center; justify-content: space-between; position: relative; z-index: 2;">
                                    <div>
                                        <h3 style="color: white; margin: 0; font-size: 2.5rem; font-weight: 800; text-shadow: 0 2px 4px rgba(0,0,0,0.2);" id="active-members">-</h3>
                                        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0 0; font-size: 14px; font-weight: 500;">Active Members</p>
                                    </div>
                                    <div style="background: rgba(255,255,255,0.2); padding: 18px; border-radius: 50%; backdrop-filter: blur(10px);">
                                        <i class="fas fa-users" style="color: white; font-size: 28px;"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); padding: 25px; border-radius: 16px; box-shadow: 0 8px 32px rgba(79, 172, 254, 0.3); position: relative; overflow: hidden;">
                                <div style="position: absolute; top: -20px; right: -20px; width: 80px; height: 80px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
                                <div style="display: flex; align-items: center; justify-content: space-between; position: relative; z-index: 2;">
                                    <div>
                                        <h3 style="color: white; margin: 0; font-size: 2.5rem; font-weight: 800; text-shadow: 0 2px 4px rgba(0,0,0,0.2);" id="total-revenue">-</h3>
                                        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0 0; font-size: 14px; font-weight: 500;">Total Revenue</p>
                                    </div>
                                    <div style="background: rgba(255,255,255,0.2); padding: 18px; border-radius: 50%; backdrop-filter: blur(10px);">
                                        <i class="fas fa-pound-sign" style="color: white; font-size: 28px;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Membership Levels Table -->
                        <div class="membership-levels-section">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding: 20px; background: rgba(255,255,255,0.05); border-radius: 12px; backdrop-filter: blur(10px);">
                                <h3 style="margin: 0; color: white; font-size: 1.5rem; font-weight: 600;">Membership Levels</h3>
                                <button id="add-membership-btn" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3); transition: all 0.3s ease;">
                                    <i class="fas fa-plus" style="margin-right: 8px;"></i>
                                    Add Membership Level
                                </button>
                            </div>
                            <div class="table-container">
                                <table class="membership-table" style="width: 100%; border-collapse: collapse; background: rgba(255,255,255,0.98); border-radius: 16px; overflow: hidden; box-shadow: 0 8px 32px rgba(0,0,0,0.1);">
                                    <thead>
                                        <tr style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                            <th style="padding: 18px 20px; text-align: left; font-weight: 600; color: white; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">Name</th>
                                            <th style="padding: 18px 20px; text-align: left; font-weight: 600; color: white; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">Designation</th>
                                            <th style="padding: 18px 20px; text-align: left; font-weight: 600; color: white; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">Fee</th>
                                            <th style="padding: 18px 20px; text-align: left; font-weight: 600; color: white; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">CPD Requirements</th>
                                            <th style="padding: 18px 20px; text-align: left; font-weight: 600; color: white; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="membership-table-body">
                                        <tr>
                                            <td colspan="5" style="text-align: center; padding: 40px 20px;">
                                                <div class="loading-spinner" style="display: inline-block; width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid #667eea; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                                                <p style="margin: 15px 0 0 0; color: #6b7280; font-size: 14px;">Loading membership levels...</p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Edit User Modal -->
<div id="edit-user-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="modal-content" style="background: white; border-radius: 12px; padding: 30px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0; color: #374151;">Edit User</h3>
            <button id="close-edit-modal" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #6b7280;">&times;</button>
        </div>
        
        <form id="edit-user-form">
            <input type="hidden" id="edit-user-id" name="user_id">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #374151;">First Name</label>
                    <input type="text" id="edit-first-name" name="first_name" required 
                           style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #374151;">Last Name</label>
                    <input type="text" id="edit-last-name" name="last_name" required 
                           style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;">
                </div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #374151;">Email</label>
                <input type="email" id="edit-email" name="email" required 
                       style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;">
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #374151;">Password</label>
                <input type="password" id="edit-password" name="password" 
                       style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;"
                       placeholder="Leave blank to keep current password">
                <small style="display: block; margin-top: 4px; color: #6b7280;">Only enter a new password if you want to change it</small>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #374151;">Membership Level</label>
                    <select id="edit-membership" name="membership" 
                            style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;">
                        <!-- Membership levels will be loaded dynamically -->
                    </select>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #374151;">Status</label>
                    <select id="edit-status" name="status" 
                            style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="pending">Pending</option>
                        <option value="suspended">Suspended</option>
                        <option value="lapsed">Lapsed</option>
                        <option value="revoked">Revoked</option>
                        <option value="resigned">Resigned</option>
                        <option value="retired">Retired</option>
                        <option value="leftsector">Left Sector</option>
                        <option value="paused">Paused</option>
                        <option value="deceased">Deceased</option>
                    </select>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #374151;">Employer/Organization</label>
                    <select id="edit-employer" name="employer_id" 
                            style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;">
                        <option value="">Select Employer...</option>
                        <!-- Options will be populated by JavaScript -->
                    </select>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #374151;">Last Login</label>
                    <div id="edit-last-login" style="padding: 10px; background: #f9fafb; border: 1px solid #d1d5db; border-radius: 6px; color: #6b7280;">
                        Loading...
                    </div>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" id="cancel-edit" 
                        style="padding: 10px 20px; background: #6b7280; color: white; border: none; border-radius: 6px; cursor: pointer;">
                    Cancel
                </button>
                <button type="submit" 
                        style="padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 6px; cursor: pointer;">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Membership Modal -->
<div id="membership-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%;">
        <div class="modal-content" style="background: white; border-radius: 12px; padding: 30px; width: 90%; max-width: 500px;">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 id="membership-modal-title" style="margin: 0; color: #374151;">Add Membership Level</h3>
            <button id="close-membership-modal" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #6b7280;">&times;</button>
        </div>
        
        <form id="membership-form">
            <input type="hidden" id="membership-id" name="membership_id">
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #374151;">Name *</label>
                <input type="text" id="membership-name" name="name" required 
                       style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;">
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #374151;">Designation *</label>
                <input type="text" id="membership-designation" name="designation" required 
                       style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;">
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #374151;">Fee (£) *</label>
                <input type="number" id="membership-fee" name="fee" step="0.01" min="0" required 
                       style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;">
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #374151;">CPD Requirements</label>
                <input type="text" id="membership-cpd-requirement" name="cpd_requirement" 
                       style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;">
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" id="cancel-membership" 
                        style="padding: 10px 20px; background: #6b7280; color: white; border: none; border-radius: 6px; cursor: pointer;">
                    Cancel
                </button>
                <button type="submit" 
                        style="padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 6px; cursor: pointer;">
                    Save Membership
                </button>
            </div>
        </form>
    </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="delete-user-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="modal-content" style="background: white; border-radius: 12px; padding: 30px; width: 90%; max-width: 400px;">
        <div class="modal-header" style="text-align: center; margin-bottom: 20px;">
            <h3 style="margin: 0; color: #dc2626;">Delete User</h3>
        </div>
        
        <p style="text-align: center; margin-bottom: 20px; color: #374151;">
            Are you sure you want to delete this user? This action cannot be undone.
        </p>
        
        <div style="display: flex; gap: 10px; justify-content: center;">
            <button id="cancel-delete" 
                    style="padding: 10px 20px; background: #6b7280; color: white; border: none; border-radius: 6px; cursor: pointer;">
                Cancel
            </button>
            <button id="confirm-delete" 
                    style="padding: 10px 20px; background: #dc2626; color: white; border: none; border-radius: 6px; cursor: pointer;">
                Delete User
            </button>
        </div>
    </div>
</div>

<style>
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.stat-card:hover {
    transform: translateY(-5px);
    transition: all 0.3s ease;
}

.btn-edit-membership:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4) !important;
}

.btn-delete-membership:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4) !important;
}

#add-membership-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4) !important;
}

.user-row:hover {
    background-color: #f8fafc !important;
}

.action-btn {
    padding: 6px 12px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 500;
    margin: 0 2px;
    transition: all 0.2s ease;
}

.edit-btn {
    background: #3b82f6;
    color: white;
}

.edit-btn:hover {
    background: #2563eb;
}

.delete-btn {
    background: #dc2626;
    color: white;
}

.delete-btn:hover {
    background: #ef4444;
}

/* Invitation Form Styles */
.invitation-form {
    max-width: 600px;
    margin: 0 auto 40px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #374151;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 10px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
}

.organisation-badge {
    display: inline-block;
    padding: 8px 16px;
    background: #f3f4f6;
    border-radius: 6px;
    font-size: 14px;
    color: #374151;
}

.field-note {
    display: block;
    margin-top: 4px;
    color: #6b7280;
    font-size: 12px;
}

.btn-primary {
    display: inline-block;
    padding: 12px 24px;
    background: #6b4c93;
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background: #f8a135;
}

.invitations-table {
    width: 100%;
    border-collapse: collapse;
}

.invitations-table th,
.invitations-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

.invitations-table th {
    background: #f8fafc;
    font-weight: 600;
    color: #374151;
}

.status-pending {
    display: inline-block;
    padding: 4px 8px;
    background: #fef3c7;
    color: #92400e;
    border-radius: 4px;
    font-size: 12px;
}

.status-used {
    display: inline-block;
    padding: 4px 8px;
    background: #d1fae5;
    color: #065f46;
    border-radius: 4px;
    font-size: 12px;
}

.status-expired {
    display: inline-block;
    padding: 4px 8px;
    background: #fee2e2;
    color: #991b1b;
    border-radius: 4px;
    font-size: 12px;
}

.status-active {
    display: inline-block;
    padding: 4px 8px;
    background: #d1fae5;
    color: #065f46;
    border-radius: 4px;
    font-size: 12px;
}

.status-inactive {
    display: inline-block;
    padding: 4px 8px;
    background: #f3f4f6;
    color: #6b7280;
    border-radius: 4px;
    font-size: 12px;
}

.status-suspended {
    display: inline-block;
    padding: 4px 8px;
    background: #fee2e2;
    color: #991b1b;
    border-radius: 4px;
    font-size: 12px;
}

.status-lapsed {
    display: inline-block;
    padding: 4px 8px;
    background: #fef3c7;
    color: #92400e;
    border-radius: 4px;
    font-size: 12px;
}

.status-revoked {
    display: inline-block;
    padding: 4px 8px;
    background: #fee2e2;
    color: #991b1b;
    border-radius: 4px;
    font-size: 12px;
}

.status-resigned {
    display: inline-block;
    padding: 4px 8px;
    background: #e5e7eb;
    color: #374151;
    border-radius: 4px;
    font-size: 12px;
}

.status-retired {
    display: inline-block;
    padding: 4px 8px;
    background: #e5e7eb;
    color: #374151;
    border-radius: 4px;
    font-size: 12px;
}

.status-leftsector {
    display: inline-block;
    padding: 4px 8px;
    background: #e5e7eb;
    color: #374151;
    border-radius: 4px;
    font-size: 12px;
}

.status-paused {
    display: inline-block;
    padding: 4px 8px;
    background: #fef3c7;
    color: #92400e;
    border-radius: 4px;
    font-size: 12px;
}

.status-deceased {
    display: inline-block;
    padding: 4px 8px;
    background: #f3f4f6;
    color: #6b7280;
    border-radius: 4px;
    font-size: 12px;
}

.btn-small {
    padding: 4px 8px;
    background: #6b4c93;
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-small:hover {
    background: #f8a135;
}

#invitation-result {
    margin-top: 20px;
    padding: 12px;
    border-radius: 6px;
}

#invitation-result .error {
    background: #fee2e2;
    color: #991b1b;
}

#invitation-result .success {
    background: #d1fae5;
    color: #065f46;
}

.no-data {
    text-align: center;
    color: #6b7280;
    padding: 40px !important;
}

/* Sticky Table Styles */
.users-table-container {
    position: relative;
    max-width: 100%;
    overflow-x: auto;
}

.users-table-sticky {
    min-width: 1200px; /* Ensure table has minimum width for scrolling */
}

.sticky-col {
    position: sticky;
    background: #f8fafc;
    z-index: 10;
    border-right: 2px solid #e5e7eb;
}

.sticky-name {
    left: 0;
    min-width: 200px;
    max-width: 200px;
}

.sticky-email {
    left: 200px; /* Position after name column */
    min-width: 250px;
    max-width: 250px;
}

/* Body cells sticky styling */
tbody .sticky-col {
    background: white;
    border-right: 2px solid #e5e7eb;
}

/* Hover effects for sticky columns */
tbody tr:hover .sticky-col {
    background: #f8fafc;
}

/* Ensure proper z-index for headers */
thead .sticky-col {
    z-index: 11;
    background: #f8fafc;
}

/* Shadow effect for sticky columns */
.sticky-col::after {
    content: '';
    position: absolute;
    top: 0;
    right: -2px;
    width: 2px;
    height: 100%;
    background: linear-gradient(to right, rgba(0,0,0,0.1), transparent);
    pointer-events: none;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .sticky-name {
        min-width: 150px;
        max-width: 150px;
    }
    
    .sticky-email {
        left: 150px;
        min-width: 200px;
        max-width: 200px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    let currentPage = 1;
    let currentSearch = '';
    let currentMembershipFilter = '';
    let currentStatusFilter = '';
    let userToDelete = null;

    // Load users and organizations on page load
    loadMembershipLevels();
    loadUsers();
    fetchOrganizations();
    
    // Initialize custom organization select
    initializeOrganizationSelect();

    // Load membership levels from database
    function loadMembershipLevels() {
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'iipm_get_membership_levels',
                nonce: '<?php echo wp_create_nonce('iipm_user_management_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    populateMembershipDropdowns(response.data.memberships);
                } else {
                    console.error('Error loading membership levels:', response.data);
                }
            },
            error: function() {
                console.error('Error loading membership levels');
            }
        });
    }

    // Populate membership dropdowns
    function populateMembershipDropdowns(memberships) {
        const membershipFilter = $('#membership-filter');
        const editMembership = $('#edit-membership');
        
        // Clear existing options except the first one
        membershipFilter.find('option:not(:first)').remove();
        editMembership.empty();
        
        // Add membership options to both dropdowns
        memberships.forEach(function(membership) {
            const option = `<option value="${membership.id}">${membership.name}</option>`;
            membershipFilter.append(option);
            editMembership.append(option);
        });
        
        // Add Admin option for site admins
        <?php if ($is_site_admin): ?>
        membershipFilter.append('<option value="Admin">Admin</option>');
        editMembership.append('<option value="Admin">Admin</option>');
        <?php endif; ?>
    }

    // Search functionality
    $('#user-search').on('input', function() {
        currentSearch = $(this).val();
        currentPage = 1;
        loadUsers();
    });

    // Filter functionality
    $('#membership-filter, #status-filter').on('change', function() {
        currentMembershipFilter = $('#membership-filter').val();
        currentStatusFilter = $('#status-filter').val();
        currentPage = 1;
        loadUsers();
    });

    // Refresh button
    $('#refresh-users').on('click', function() {
        loadUsers();
    });

    // Sync login data button
    $('#sync-login-data').on('click', function() {
        const button = $(this);
        const originalText = button.html();
        
        button.html('<span style="margin-right: 8px;"><i class="fas fa-spinner fa-spin"></i></span>Syncing...').prop('disabled', true);
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'iipm_sync_last_login_data',
                nonce: '<?php echo wp_create_nonce('iipm_user_management_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    loadUsers(); // Refresh the user list
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Error: Failed to sync login data. Please try again.');
            },
            complete: function() {
                button.html(originalText).prop('disabled', false);
            }
        });
    });

    // Load users function
    function loadUsers() {
        $('#users-table-body').html(`
            <tr>
                <td colspan="8" style="padding: 40px; text-align: center; color: #6b7280;">
                    <div class="loading-spinner" style="display: inline-block; width: 20px; height: 20px; border: 2px solid #e5e7eb; border-top: 2px solid #667eea; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                    <span style="margin-left: 10px;">Loading users...</span>
                </td>
            </tr>
        `);
        $('#user-count-text').text('Loading...');

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'iipm_get_users',
                nonce: '<?php echo wp_create_nonce('iipm_user_management_nonce'); ?>',
                page: currentPage,
                search: currentSearch,
                membership_filter: currentMembershipFilter,
                status_filter: currentStatusFilter
            },
            success: function(response) {
                if (response.success) {
                    displayUsers(response.data.users);
                    displayPagination(response.data.pagination);
                    updateUserCount(response.data.pagination);
                } else {
                    $('#users-table-body').html(`
                        <tr>
                            <td colspan="7" style="padding: 40px; text-align: center; color: #dc2626;">
                                Error: ${response.data}
                            </td>
                        </tr>
                    `);
                    $('#user-count-text').text('Error');
                }
            },
            error: function() {
                $('#users-table-body').html(`
                    <tr>
                        <td colspan="7" style="padding: 40px; text-align: center; color: #dc2626;">
                            Error loading users. Please try again.
                        </td>
                    </tr>
                `);
                $('#user-count-text').text('Error');
            }
        });
    }

    // Display users in table
    function displayUsers(users) {
        if (users.length === 0) {
            $('#users-table-body').html(`
                <tr>
                    <td colspan="8" style="padding: 40px; text-align: center; color: #6b7280;">
                        No users found.
                    </td>
                </tr>
            `);
            return;
        }

        let html = '';
        users.forEach(function(user) {
            // Determine CPD status icon
            const cpdStatusIcon = user.cpd_submitted ? 
                '<i class="fas fa-check" style="color: #10b981; font-size: 16px;" title="CPD Submitted"></i>' : 
                '<i class="fas fa-times" style="color: #ef4444; font-size: 16px;" title="CPD Not Submitted"></i>';
            
            html += `
                <tr class="user-row" style="border-bottom: 1px solid #e5e7eb;">
                    <td class="sticky-col sticky-name" style="padding: 16px;">
                        <div style="font-weight: 500; color: #374151;">${user.display_name}</div>
                    </td>
                    <td class="sticky-col sticky-email" style="padding: 16px; color: #6b7280;">${user.user_email}</td>
                    <td style="padding: 16px;">
                        <span class="role-badge">${user.role_display}</span>
                    </td>
                    <td style="padding: 16px;">
                        <span class="status-badge status-${user.membership_status}">${user.membership_status}</span>
                    </td>
                    <td style="padding: 16px; color: #6b7280;">${user.organisation_name || 'N/A'}</td>
                    <td style="padding: 16px; color: #6b7280;">${user.last_login || 'Never'}</td>
                    <td style="padding: 16px; text-align: center;">
                        ${cpdStatusIcon}
                    </td>
                    <td style="padding: 16px; text-align: center;width: 150px;">
                        <button class="action-btn edit-btn" onclick="editUser(${user.ID})">View</button>
                        <button class="action-btn delete-btn" onclick="deleteUser(${user.ID}, '${user.display_name}')">Delete</button>
                    </td>
                </tr>
            `;
        });
        $('#users-table-body').html(html);
    }

    // Update user count display
    function updateUserCount(pagination) {
        const totalUsers = pagination.total_users || 0;
        const currentPage = pagination.current_page || 1;
        const perPage = pagination.per_page || 20;
        const startUser = ((currentPage - 1) * perPage) + 1;
        const endUser = Math.min(currentPage * perPage, totalUsers);
        
        let countText;
        if (totalUsers === 0) {
            countText = 'No users found';
        } else if (totalUsers <= perPage) {
            countText = `${totalUsers} user${totalUsers === 1 ? '' : 's'}`;
        } else {
            countText = `${startUser}-${endUser} of ${totalUsers} users`;
        }
        
        $('#user-count-text').text(countText);
    }

    // Display pagination
    function displayPagination(pagination) {
        if (pagination.total_pages <= 1) {
            $('#pagination-container').html('');
            return;
        }

        let html = '';
        
        // Previous button
        if (pagination.current_page > 1) {
            html += `<button onclick="changePage(${pagination.current_page - 1})" style="padding: 8px 12px; border: 1px solid #d1d5db; background: white; border-radius: 4px; cursor: pointer;">Previous</button>`;
        }

        // Page numbers
        for (let i = Math.max(1, pagination.current_page - 2); i <= Math.min(pagination.total_pages, pagination.current_page + 2); i++) {
            const isActive = i === pagination.current_page;
            html += `<button onclick="changePage(${i})" style="padding: 8px 12px; border: 1px solid #d1d5db; background: ${isActive ? '#667eea' : 'white'}; color: ${isActive ? 'white' : '#374151'}; border-radius: 4px; cursor: pointer;">${i}</button>`;
        }

        // Next button
        if (pagination.current_page < pagination.total_pages) {
            html += `<button onclick="changePage(${pagination.current_page + 1})" style="padding: 8px 12px; border: 1px solid #d1d5db; background: white; border-radius: 4px; cursor: pointer;">Next</button>`;
        }

        $('#pagination-container').html(html);
    }

    // Change page function (global)
    window.changePage = function(page) {
        currentPage = page;
        loadUsers();
    };

    // Global variable to store organizations
    let organizations = [];

    // Fetch organizations on page load
    function fetchOrganizations() {
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'iipm_get_all_organizations',
                nonce: '<?php echo wp_create_nonce('iipm_user_management_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    organizations = response.data;
                    populateEmployerSelect();
                } else {
                    console.error('Error fetching organizations:', response.data);
                }
            },
            error: function() {
                console.error('Error fetching organizations');
            }
        });
    }

    // Populate employer select box
    function populateEmployerSelect() {
        const select = $('#edit-employer');
        select.empty();
        select.append('<option value="">Select Employer...</option>');
        
        organizations.forEach(function(org) {
            select.append(`<option value="${org.id}">${org.name}</option>`);
        });
    }

    // Initialize custom organization select for invitations
    function initializeOrganizationSelect() {
        // Custom select functionality
        $('.select-trigger').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).toggleClass('active');
            $('.select-dropdown').toggleClass('show');
            
            // Populate options when dropdown is opened
            if ($(this).hasClass('active') && organizations.length > 0) {
                populateOrganizationOptions(organizations);
            }
        });
        
        // Organization search functionality
        $('#organisation-search').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            const filteredOrgs = organizations.filter(org => 
                org.name.toLowerCase().includes(searchTerm)
            );
            populateOrganizationOptions(filteredOrgs);
        });
        
        // Organization selection
        $(document).on('click', '.select-option', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const orgId = $(this).data('org-id');
            const orgName = $(this).find('.option-name').text();
            
            // Update select display
            $('.select-trigger .select-placeholder').text(orgName);
            $('.select-trigger').addClass('selected');
            $('.select-dropdown').removeClass('show');
            $('.select-trigger').removeClass('active');
            
            // Update hidden input
            $('#organisation_id').val(orgId);
        });
        
        // Close dropdown when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.custom-select').length) {
                $('.select-dropdown').removeClass('show');
                $('.select-trigger').removeClass('active');
            }
        });
    }

    // Populate organization options in custom select
    function populateOrganizationOptions(orgs) {
        const $options = $('#organisation-options');
        $options.empty();
        
        if (!orgs || orgs.length === 0) {
            $options.html('<div class="select-option">No organizations found</div>');
            return;
        }
        
        orgs.forEach(function(org) {
            const option = $(`
                <div class="select-option" data-org-id="${org.id}">
                    <div class="option-name">${org.name}</div>
                </div>
            `);
            $options.append(option);
        });
    }

    // Edit user function (global)
    window.editUser = function(userId) {
        // Redirect to user details page
        window.location.href = '<?php echo home_url('/user-details'); ?>?id=' + userId;
    };

    // Delete user function (global)
    window.deleteUser = function(userId, userName) {
        userToDelete = userId;
        $('#delete-user-modal p').html(`Are you sure you want to delete <strong>${userName}</strong>? This action cannot be undone.`);
        $('#delete-user-modal').css('display', 'flex');
    };

    // Modal close handlers
    $('#close-edit-modal, #cancel-edit').on('click', function() {
        $('#edit-user-modal').hide();
    });

    $('#cancel-delete').on('click', function() {
        $('#delete-user-modal').hide();
        userToDelete = null;
    });

    // Edit user form submission
    $('#edit-user-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            action: 'iipm_update_user',
            nonce: '<?php echo wp_create_nonce('iipm_user_management_nonce'); ?>',
            user_id: $('#edit-user-id').val(),
            first_name: $('#edit-first-name').val(),
            last_name: $('#edit-last-name').val(),
            email: $('#edit-email').val(),
            membership: $('#edit-membership').val(),
            status: $('#edit-status').val(),
            employer_id: $('#edit-employer').val()
        };

        // Only include password if it's not empty
        const password = $('#edit-password').val();
        if (password) {
            formData.password = password;
        }
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    $('#edit-user-modal').hide();
                    $('#edit-password').val(''); // Clear password field
                    loadUsers();
                    // Show success notification
                    if (window.notifications) {
                        notifications.success('User Updated', 'User has been updated successfully.');
                    }
                } else {
                    // Show error notification
                    if (window.notifications) {
                        notifications.error('Update Failed', 'Error updating user: ' + response.data);
                    }
                }
            },
            error: function() {
                // Show error notification
                if (window.notifications) {
                    notifications.error('Update Failed', 'Error updating user. Please try again.');
                }
            }
        });
    });

    // Confirm delete
    $('#confirm-delete').on('click', function() {
        if (!userToDelete) return;

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'iipm_delete_user',
                nonce: '<?php echo wp_create_nonce('iipm_user_management_nonce'); ?>',
                user_id: userToDelete
            },
            success: function(response) {
                if (response.success) {
                    $('#delete-user-modal').hide();
                    loadUsers();
                    // Show success notification
                    if (window.notifications) {
                        notifications.success('User Deleted', 'User has been deleted successfully.');
                    }
                } else {
                    // Show error notification
                    if (window.notifications) {
                        notifications.error('Delete Failed', 'Error deleting user: ' + response.data);
                    }
                }
                userToDelete = null;
            },
            error: function() {
                // Show error notification
                if (window.notifications) {
                    notifications.error('Delete Failed', 'Error deleting user. Please try again.');
                }
                userToDelete = null;
            }
        });
    });

    // Close modals when clicking outside
    $(document).on('click', function(e) {
        if ($(e.target).is('#edit-user-modal')) {
            $('#edit-user-modal').hide();
        }
        if ($(e.target).is('#delete-user-modal')) {
            $('#delete-user-modal').hide();
            userToDelete = null;
        }
    });

    // Invitation form handling
    var isOrgAdmin = <?php echo json_encode($is_org_admin && !$is_site_admin); ?>;
    var userOrganisationId = <?php echo json_encode($user_organisation ? $user_organisation->id : null); ?>;
    
    // Show/hide organisation field for site admins only
    if (!isOrgAdmin) {
        $('#type').change(function() {
            if ($(this).val() === 'bulk') {
                $('.organisation-field').show();
            } else {
                $('.organisation-field').hide();
            }
        });
    }
    
    // Function to load invitations
    function loadInvitations() {
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'iipm_load_invitations',
                nonce: '<?php echo wp_create_nonce('iipm_user_management_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    var html = '';
                    if (response.data.total === 0) {
                        html = '<tr><td colspan="<?php echo $is_site_admin ? '7' : '6'; ?>" class="no-data">No invitations found</td></tr>';
                    } else {
                        response.data.pending.forEach(function(invitation) {
                            html += formatInvitationRow(invitation, 'Pending');
                        });
                        response.data.used.forEach(function(invitation) {
                            html += formatInvitationRow(invitation, 'Used');
                        });
                        response.data.expired.forEach(function(invitation) {
                            html += formatInvitationRow(invitation, 'Expired');
                        });
                    }
                    $('#invitations-table-body').html(html);
                } else {
                    $('#invitations-table-body').html('<tr><td colspan="<?php echo $is_site_admin ? '7' : '6'; ?>" class="error">Error loading invitations</td></tr>');
                }
            },
            error: function() {
                $('#invitations-table-body').html('<tr><td colspan="<?php echo $is_site_admin ? '7' : '6'; ?>" class="error">Failed to load invitations</td></tr>');
            }
        });
    }

    // Function to format invitation row
    function formatInvitationRow(invitation, status) {
        var row = '<tr>';
        row += '<td>' + invitation.email + '</td>';
        row += '<td>' + invitation.invitation_type + '</td>';
        <?php if ($is_site_admin): ?>
        row += '<td>' + (invitation.org_name || 'Individual') + '</td>';
        <?php endif; ?>
        row += '<td><span class="status-' + status.toLowerCase() + '">' + status + '</span></td>';
        row += '<td>' + formatDate(invitation.created_at) + '</td>';
        row += '<td>' + formatDate(invitation.expires_at) + '</td>';
        row += '<td>';
        if (status === 'Pending') {
            row += '<button class="btn-small resend-invitation" data-email="' + invitation.email + '">Resend</button>';
        }
        row += '</td>';
        row += '</tr>';
        return row;
    }

    // Helper function to format dates
    function formatDate(dateStr) {
        var date = new Date(dateStr);
        return date.toLocaleDateString('en-US', { 
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: 'numeric',
            hour12: true
        });
    }

    // ===== MEMBERSHIP OVERVIEW FUNCTIONS =====
    
    /**
     * Load membership overview data and statistics
     */
    function loadMembershipOverviewData() {
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'iipm_get_membership_overview_data',
                nonce: '<?php echo wp_create_nonce('iipm_user_management_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    updateMembershipOverviewStats(response.data.stats);
                    displayMembershipOverviewLevels(response.data.memberships);
                } else {
                    $('#membership-table-body').html('<tr><td colspan="5" class="error">Error loading membership data</td></tr>');
                }
            },
            error: function() {
                $('#membership-table-body').html('<tr><td colspan="5" class="error">Failed to load membership data</td></tr>');
            }
        });
    }
    
    /**
     * Update membership overview statistics cards
     */
    function updateMembershipOverviewStats(stats) {
        $('#total-memberships').text(stats.total_memberships || '0');
        $('#active-members').text(stats.active_members || '0');
        $('#total-revenue').text('£' + (stats.total_revenue || '0'));
    }
    
    /**
     * Display membership levels in overview table
     */
    function displayMembershipOverviewLevels(memberships) {
        let html = '';
        if (memberships && memberships.length > 0) {
            memberships.forEach(function(membership, index) {
                const rowClass = index % 2 === 0 ? 'style="background: #ffffff;"' : 'style="background: #f8fafc;"';
                html += `
                    <tr ${rowClass}>
                        <td style="padding: 18px 20px; border-bottom: 1px solid #e5e7eb; font-weight: 500; color: #374151;">${membership.name || 'N/A'}</td>
                        <td style="padding: 18px 20px; border-bottom: 1px solid #e5e7eb; color: #6b7280;">${membership.designation || 'N/A'}</td>
                        <td style="padding: 18px 20px; border-bottom: 1px solid #e5e7eb; color: #059669; font-weight: 600;">£${membership.fee || '0'}</td>
                        <td style="padding: 18px 20px; border-bottom: 1px solid #e5e7eb; color: #6b7280;">${membership.cpd_requirement || 'N/A'}</td>
                        <td style="padding: 18px 20px; border-bottom: 1px solid #e5e7eb;">
                            <button class="btn-edit-membership" data-id="${membership.id}" style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; margin-right: 8px; font-size: 12px; font-weight: 500; box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3); transition: all 0.3s ease;">
                                <i class="fas fa-edit" style="margin-right: 4px;"></i>
                                Edit
                            </button>
                            <button class="btn-delete-membership" data-id="${membership.id}" data-name="${membership.name}" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 500; box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3); transition: all 0.3s ease;">
                                <i class="fas fa-trash" style="margin-right: 4px;"></i>
                                Delete
                            </button>
                        </td>
                    </tr>
                `;
            });
        } else {
            html = '<tr><td colspan="5" style="text-align: center; padding: 40px 20px; color: #6b7280; font-size: 16px;">No membership levels found</td></tr>';
        }
        $('#membership-table-body').html(html);
    }
    
    // ===== MEMBERSHIP CRUD FUNCTIONS =====
    
    /**
     * Open membership modal for add/edit
     */
    function openMembershipModal(membershipId = null) {
        const modal = $('#membership-modal');
        const title = $('#membership-modal-title');
        const form = $('#membership-form')[0];
        
        if (membershipId) {
            title.text('Edit Membership Level');
            loadMembershipForEdit(membershipId);
        } else {
            title.text('Add Membership Level');
            form.reset();
            $('#membership-id').val('');
        }
        
        modal.css('display', 'flex');
    }
    
    /**
     * Load membership data for editing
     */
    function loadMembershipForEdit(membershipId) {
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'iipm_get_single_membership',
                nonce: '<?php echo wp_create_nonce('iipm_user_management_nonce'); ?>',
                membership_id: membershipId
            },
            success: function(response) {
                if (response.success) {
                    const membership = response.data;
                    console.log('Membership data:', membership); // Debug log
                    $('#membership-id').val(membership.id);
                    $('#membership-name').val(membership.name || '');
                    $('#membership-designation').val(membership.designation || '');
                    $('#membership-fee').val(membership.fee || '');
                    $('#membership-cpd-requirement').val(membership.cpd_requirement || '');
                } else {
                    alert('Error loading membership data: ' + response.data);
                }
            },
            error: function() {
                alert('Failed to load membership data');
            }
        });
    }
    
    /**
     * Close membership modal
     */
    function closeMembershipModal() {
        $('#membership-modal').css('display', 'none');
    }
    
    /**
     * Delete membership
     */
    function deleteMembership(membershipId, membershipName) {
        if (!confirm(`Are you sure you want to delete "${membershipName}"? This action cannot be undone.`)) {
            return;
        }
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'iipm_delete_membership',
                nonce: '<?php echo wp_create_nonce('iipm_user_management_nonce'); ?>',
                membership_id: membershipId
            },
            success: function(response) {
                if (response.success) {
                    alert('Membership deleted successfully');
                    loadMembershipOverviewData(); // Reload the data
                } else {
                    alert('Error deleting membership: ' + response.data);
                }
            },
            error: function() {
                alert('Failed to delete membership');
            }
        });
    }

    // Load invitations on page load if we're on the invitations tab
    if ($('.invitations-content').length) {
        loadInvitations();
    }
    
    // Load membership data on page load if we're on the membership tab
    if ($('.membership-content').length) {
        loadMembershipOverviewData();
    }
    
    // ===== MEMBERSHIP EVENT HANDLERS =====
    
    // Handle Add Membership button
    $(document).on('click', '#add-membership-btn', function() {
        openMembershipModal();
    });
    
    // Handle Edit Membership button
    $(document).on('click', '.btn-edit-membership', function() {
        const membershipId = $(this).data('id');
        openMembershipModal(membershipId);
    });
    
    // Handle Delete Membership button
    $(document).on('click', '.btn-delete-membership', function() {
        const membershipId = $(this).data('id');
        const membershipName = $(this).data('name');
        deleteMembership(membershipId, membershipName);
    });
    
    // Handle Close Membership Modal
    $(document).on('click', '#close-membership-modal, #cancel-membership', function() {
        closeMembershipModal();
    });
    
    // Handle Membership Form Submission
    $(document).on('submit', '#membership-form', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const membershipId = $('#membership-id').val();
        const action = membershipId ? 'iipm_update_membership' : 'iipm_create_membership';
        
        formData.append('action', action);
        formData.append('nonce', '<?php echo wp_create_nonce('iipm_user_management_nonce'); ?>');
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert(membershipId ? 'Membership updated successfully' : 'Membership created successfully');
                    closeMembershipModal();
                    loadMembershipOverviewData(); // Reload the data
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Failed to save membership');
            }
        });
    });

    // Handle invitation form submission
    $('#send-invitation-form').submit(function(e) {
        e.preventDefault();
        
        // Validate organization selection for bulk invitations
        if ($('#type').val() === 'bulk' && !isOrgAdmin) {
            var selectedOrg = $('#organisation_id').val();
            if (!selectedOrg) {
                $('#invitation-result').html('<div class="error">Error: Please select an organization for organization member invitations.</div>').show();
                return false;
            }
        }
        
        var formData = $(this).serialize() + '&action=iipm_send_invitation';
        
        // For org admins, ensure organisation_id is set
        if (isOrgAdmin && userOrganisationId) {
            formData += '&organisation_id=' + userOrganisationId;
        }
        
        // Show loading state
        var submitBtn = $(this).find('button[type="submit"]');
        var originalText = submitBtn.text();
        submitBtn.text('Sending...').prop('disabled', true);
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    // Handle both string and object responses
                    const message = typeof response.data === 'string' ? response.data : (response.data.message || 'Success');
                    $('#invitation-result').html('<div class="success">' + message + '</div>').show();
                    $('#send-invitation-form')[0].reset();
                    $('.organisation-field').hide();
                    
                    // Show success notification
                    if (window.notifications) {
                        notifications.success('Invitation Sent', message);
                    }
                    
                    // Reload invitations instead of page
                    loadInvitations();
                } else {
                    // Handle both string and object error messages
                    const errorMsg = typeof response.data === 'string' ? response.data : (response.data.message || 'Unknown error occurred');
                    $('#invitation-result').html('<div class="error">Error: ' + errorMsg + '</div>').show();
                    
                    // Show error notification
                    if (window.notifications) {
                        notifications.error('Send Failed', 'Error: ' + errorMsg);
                    }
                }
            },
            error: function() {
                $('#invitation-result').html('<div class="error">Error: Failed to send invitation. Please try again.</div>').show();
                
                // Show error notification
                if (window.notifications) {
                    notifications.error('Send Failed', 'Failed to send invitation. Please try again.');
                }
            },
            complete: function() {
                submitBtn.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Handle resend invitation using event delegation
    $(document).on('click', '.resend-invitation', function(e) {
        e.preventDefault();
        console.log('resend-invitation clicked');
        var button = $(this);
        var email = button.data('email');
        var originalText = button.text();
        
        button.text('Resending...').prop('disabled', true);
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'iipm_resend_invitation',
                email: email,
                nonce: '<?php echo wp_create_nonce('iipm_user_management_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Show success notification
                    if (window.notifications) {
                        notifications.success('Invitation Resent', 'Invitation resent successfully!');
                    }
                    // Reload invitations to refresh the list
                    loadInvitations();
                } else {
                    // Show error notification
                    if (window.notifications) {
                        notifications.error('Resend Failed', 'Error: ' + response.data.message);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', {xhr, status, error});
                // Show error notification
                if (window.notifications) {
                    notifications.error('Resend Failed', 'Failed to resend invitation. Please try again.');
                }
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    });
});
</script>

<?php get_footer(); ?>
