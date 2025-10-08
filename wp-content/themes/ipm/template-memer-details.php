<?php
/**
 * Template Name: User Details
 * 
 * User Details page for displaying individual user information with editing capabilities
 */

// Security check - only allow admins and org admins
if (!is_user_logged_in()) {
    wp_redirect(home_url('/login/'));
    exit;
}

$current_user = wp_get_current_user();
$is_site_admin = current_user_can('administrator');
$is_org_admin = in_array('iipm_corporate_admin', $current_user->roles) || current_user_can('manage_organisation_members');

if (!$is_site_admin && !$is_org_admin) {
    wp_redirect(home_url('/dashboard/'));
    exit;
}

// Get user ID from URL parameter
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$user_id) {
    wp_redirect(home_url('/member-management/'));
    exit;
}

get_header();

// Include notification system if not already loaded
if (!function_exists('add_success_notification')) {
    include_once get_template_directory() . '/includes/notification-system.php';
}
?>

<main class="user-details-page main-container">
    <div class="container" style="position: relative; z-index: 2;">
        
        <!-- Page Header -->
        <div class="page-header" style="text-align: center; margin-bottom: 40px;">
            <div>
                <h1 id="page-title" style="color: white; font-size: 2.5rem; margin-bottom: 10px;">Loading Member Details...</h1>
                <p style="color: rgba(255,255,255,0.9); font-size: 1.1rem;">
                    View and edit comprehensive user information
                </p>
            </div>
        </div>

        <!-- Back Button -->
        <div style="margin-bottom: 30px;">
            <a href="<?php echo home_url('/member-management/'); ?>" style="display: inline-flex; align-items: center; padding: 12px 24px; background: #6b4c93; color: white; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s ease;">
                <span style="margin-right: 8px;"><i class="fas fa-arrow-left"></i></span>
                Back to Members
            </a>
        </div>

        <!-- User Details Card -->
        <div class="user-details-card" style="background: white; border-radius: 12px; padding: 40px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
            <div id="loading-message" style="text-align: center; padding: 40px; color: #6b7280;">
                <div class="loading-spinner" style="display: inline-block; width: 24px; height: 24px; border: 3px solid #e5e7eb; border-top: 3px solid #667eea; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                <span style="margin-left: 10px;">Loading member details...</span>
            </div>
            
            <!-- Tab Navigation -->
            <div id="tab-navigation" style="display: none; margin-bottom: 30px;">
                <div class="tab-buttons" style="display: flex; border-bottom: 2px solid #e5e7eb;">
                    <button class="tab-btn active" data-tab="information" style="padding: 12px 24px; background: none; border: none; border-bottom: 2px solid #667eea; color: #667eea; font-weight: 500; cursor: pointer;">
                        <i class="fas fa-user"></i> Information
                    </button>
                    <button class="tab-btn" data-tab="cpd" style="padding: 12px 24px; background: none; border: none; border-bottom: 2px solid transparent; color: #6b7280; font-weight: 500; cursor: pointer;">
                        <i class="fas fa-certificate"></i> CPD Records
                    </button>
                    <button class="tab-btn" data-tab="qualifications" style="padding: 12px 24px; background: none; border: none; border-bottom: 2px solid transparent; color: #6b7280; font-weight: 500; cursor: pointer;">
                        <i class="fas fa-graduation-cap"></i> Qualifications
                    </button>
                </div>
            </div>
            
            <!-- Information Tab -->
            <div id="information-tab" class="tab-content">
            <form id="user-details-form" style="display: none;">
                <input type="hidden" name="user_id" id="user_id" value="<?php echo $user_id; ?>">
                
                <!-- Section 1: User Information -->
                <div class="info-section" data-section="user-info">
                    <div class="section-header">
                        <h3><i class="fas fa-user"></i> Member Information</h3>
                        <button type="button" class="edit-toggle-btn">Edit</button>
                    </div>
                    <div class="section-content">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Username:</label>
                                <input type="text" name="user_login" id="user_login_edit" disabled readonly>
                            </div>
                            <div class="form-group">
                                <label>Email:</label>
                                <input type="email" name="user_email" id="user_email_edit" disabled>
                            </div>
                            <div class="form-group">
                                <label>First Name:</label>
                                <input type="text" name="first_name" id="first_name_edit" disabled>
                            </div>
                            <div class="form-group">
                                <label>Surname:</label>
                                <input type="text" name="sur_name" id="sur_name_edit" disabled>
                            </div>
                            <div class="form-group">
                                <label>Member Type:</label>
                                <select name="member_type" id="member_type_edit" disabled>
                                    <option value="">Select Type</option>
                                    <option value="individual">Individual</option>
                                    <option value="organisation">Organisation</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>
                                    Membership Level:
                                    <span class="tooltip-trigger" data-tooltip="membership-info">
                                        <i class="fas fa-question-circle" style="color: #6b7280; font-size: 14px; margin-left: 5px; cursor: help;"></i>
                                    </span>
                                </label>
                                <select name="membership_level_id" id="membership_level_edit" disabled>
                                    <!-- Populated by JavaScript -->
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Status:</label>
                                <select name="membership_status" id="membership_status_edit" disabled>
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
                            <div class="form-group">
                                <label>Reset Password:</label>
                                <div class="password-reset-container">
                                    <input type="password" name="new_password" id="new_password_edit" disabled 
                                           placeholder="Leave empty to keep current password" 
                                           style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px;">
                                    <div class="password-strength-indicator" style="margin-top: 8px; display: none;">
                                        <div class="strength-bar" style="height: 4px; background: #e5e7eb; border-radius: 2px; overflow: hidden;">
                                            <div class="strength-fill" style="height: 100%; width: 0%; transition: all 0.3s ease;"></div>
                                        </div>
                                        <div class="strength-text" style="font-size: 12px; margin-top: 4px; color: #6b7280;"></div>
                                    </div>
                                    <div class="password-requirements" style="margin-top: 8px; font-size: 12px; color: #6b7280;">
                                        <div class="requirement" data-requirement="length">• At least 8 characters</div>
                                        <div class="requirement" data-requirement="uppercase">• One uppercase letter</div>
                                        <div class="requirement" data-requirement="lowercase">• One lowercase letter</div>
                                        <div class="requirement" data-requirement="number">• One number</div>
                                        <div class="requirement" data-requirement="special">• One special character</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Contact Information -->
                <div class="info-section" data-section="contact-info">
                    <div class="section-header">
                        <h3><i class="fas fa-address-book"></i> Contact Information</h3>
                        <button type="button" class="edit-toggle-btn">Edit</button>

                    </div>
                    <div class="section-content">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Email:</label>
                                <input type="email" name="email_address" id="email_address_edit" disabled>
                            </div>
                            <div class="form-group">
                                <label>Phone:</label>
                                <input type="text" name="user_phone" id="user_phone_edit" disabled>
                            </div>
                            <div class="form-group">
                                <label>Mobile:</label>
                                <input type="text" name="user_mobile" id="user_mobile_edit" disabled>
                            </div>
                            <div class="form-group">
                                <label>City/Town:</label>
                                <input type="text" name="city_or_town" id="city_or_town_edit" disabled>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Billing Information -->
                <div class="info-section" data-section="billing-info">
                    <div class="section-header">
                        <h3><i class="fas fa-credit-card"></i> Billing Information</h3>
                        <button type="button" class="edit-toggle-btn">Edit</button>
                    </div>
                    <div class="section-content">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Payment Method: <span style="color: red;">*</span></label>
                                <select name="user_payment_method" id="user_payment_method_edit" disabled required>
                                    <option value="">Select Payment Method</option>
                                    <option value="Employer Invoiced">Employer Invoiced</option>
                                    <option value="Direct Invoiced">Direct Invoiced</option>
                                    <option value="N/A">N/A</option>
                                </select>
                            </div>
                            <div class="form-group full-width">
                                <label>Billing Address Line 1:</label>
                                <input type="text" name="Address_1" id="Address_1_billing_edit" disabled>
                            </div>
                            <div class="form-group full-width">
                                <label>Billing Address Line 2:</label>
                                <input type="text" name="Address_2" id="Address_2_billing_edit" disabled>
                            </div>
                            <div class="form-group full-width">
                                <label>Billing Address Line 3:</label>
                                <input type="text" name="Address_3" id="Address_3_billing_edit" disabled>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 4: Personal Information (_pers) -->
                <div class="info-section" data-section="personal-info">
                    <div class="section-header">
                        <h3><i class="fas fa-user-secret"></i> Personal Information</h3>
                        <button type="button" class="edit-toggle-btn">Edit</button>
                    </div>
                    <div class="section-content">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Personal Email:</label>
                                <input type="email" name="email_address_pers" id="email_address_pers_edit" disabled>
                            </div>
                            <div class="form-group">
                                <label>Personal Phone:</label>
                                <input type="text" name="user_phone_pers" id="user_phone_pers_edit" disabled>
                            </div>
                            <div class="form-group">
                                <label>Personal Mobile:</label>
                                <input type="text" name="user_mobile_pers" id="user_mobile_pers_edit" disabled>
                            </div>
                            <div class="form-group">
                                <label>Correspondence Email:</label>
                                <input type="email" name="correspondence_email" id="correspondence_email_edit" disabled>
                            </div>
                            <div class="form-group full-width">
                                <label>Personal Address Line 1:</label>
                                <input type="text" name="Address_1_pers" id="Address_1_pers_edit" disabled>
                            </div>
                            <div class="form-group full-width">
                                <label>Personal Address Line 2:</label>
                                <input type="text" name="Address_2_pers" id="Address_2_pers_edit" disabled>
                            </div>
                            <div class="form-group full-width">
                                <label>Personal Address Line 3:</label>
                                <input type="text" name="Address_3_pers" id="Address_3_pers_edit" disabled>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 5: Employer Information -->
                <div class="info-section" data-section="employer-info">
                    <div class="section-header">
                        <h3><i class="fas fa-building"></i> Employer Information</h3>
                        <button type="button" class="edit-toggle-btn">Edit</button>
                    </div>
                    <div class="section-content">
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label>Organization:</label>
                                <select name="employer_id" id="employer_id" disabled>
                                    <option value="">Select Organization</option>
                                                <!-- Populated by JavaScript -->
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Save/Cancel Buttons -->
                <div style="text-align: center; padding-top: 30px; border-top: 1px solid #e5e7eb;">
                    <button type="submit" class="btn-primary save-btn" style="display: none;">
                        <i class="fas fa-save"></i>
                        Save Changes
                    </button>
                    <button type="button" class="btn-secondary cancel-btn" onclick="cancelEditAll()" style="display: none;">
                        <i class="fas fa-times"></i>
                        Cancel
                    </button>
                </div>
            </form>
            </div>
            
            <!-- CPD Tab -->
            <div id="cpd-tab" class="tab-content" style="display: none;">
                <div id="cpd-loading" style="text-align: center; padding: 40px; color: #6b7280;">
                    <div class="loading-spinner" style="display: inline-block; width: 24px; height: 24px; border: 3px solid #e5e7eb; border-top: 3px solid #667eea; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                    <span style="margin-left: 10px;">Loading CPD records...</span>
                </div>
                
                <!-- Year Selector -->
                <div id="cpd-year-selector" style="display: none; margin-bottom: 30px;">
                    <label for="cpd-year" style="font-weight: 600; margin-right: 15px;">Select Year:</label>
                    <select id="cpd-year" style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px;">
                        <!-- Populated by JavaScript -->
                    </select>
                </div>
                
                <!-- CPD Stats Container -->
                <div id="cpd-stats-container" style="display: none;">
                    <!-- CPD Overview Cards -->
                    <div class="cpd-overview" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
                        <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);">
                            <h4 style="margin: 0 0 15px 0; color: rgba(255,255,255,0.9); font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px;">Total CPD Hours</h4>
                            <div class="stat-value" id="total-cpd-hours" style="font-size: 2.5rem; font-weight: bold;">0</div>
                        </div>
                        <div class="stat-card" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);">
                            <h4 style="margin: 0 0 15px 0; color: rgba(255,255,255,0.9); font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px;">Target</h4>
                            <div class="stat-value" id="original-target" style="font-size: 2.5rem; font-weight: bold;">0</div>
                        </div>
                        <div class="stat-card" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);">
                            <h4 style="margin: 0 0 15px 0; color: rgba(255,255,255,0.9); font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px;">Completion</h4>
                            <div class="stat-value" id="completion-percentage" style="font-size: 2.5rem; font-weight: bold;">0%</div>
                        </div>
                    </div>
                    
                    <!-- Leave Requests Section -->
                    <div class="cpd-section" style="background: #f8fafc; border-radius: 12px; padding: 30px; margin-bottom: 30px;">
                        <h3 style="margin: 0 0 20px 0; color: #374151;"><i class="fas fa-calendar-times"></i> Leave Requests</h3>
                        <div id="leave-requests-list">
                            <!-- Populated by JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Courses Section -->
                    <div class="cpd-section" style="background: #f8fafc; border-radius: 12px; padding: 30px; margin-bottom: 30px;">
                        <h3 style="margin: 0 0 20px 0; color: #374151;"><i class="fas fa-graduation-cap"></i> Courses Completed</h3>
                        <div id="courses-list">
                            <!-- Populated by JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Certificate Section -->
                    <div class="cpd-section" style="background: #f8fafc; border-radius: 12px; padding: 30px;">
                        <h3 style="margin: 0 0 20px 0; color: #374151;"><i class="fas fa-certificate"></i> Certificate</h3>
                        <p style="color: #6b7280; font-size: 0.9rem; margin-bottom: 20px; font-style: italic;">
                            <i class="fas fa-info-circle"></i> As an admin, you can download and view certificates for any user.
                        </p>
                        <div id="certificate-section">
                            <div id="certificate-info" style="display: none;">
                                <div style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb; margin-bottom: 15px;">
                                    <div style="display: flex; gap: 20px;">
                                        <div style="flex: 1;">
                                            <div style="color: #6b7280; font-size: 0.9rem; margin-bottom: 10px;">
                                                <strong>Submission Status:</strong> <span id="submission-status"></span>
                                            </div>
                                            <div style="color: #6b7280; font-size: 0.9rem; margin-bottom: 10px;">
                                                <strong>Submission Date:</strong> <span id="submission-date"></span>
                                            </div>
                                            <div style="color: #6b7280; font-size: 0.9rem; margin-bottom: 10px;">
                                                <strong>Certificate:</strong> <span id="certificate-name"></span>
                                            </div>
                                            <div style="color: #6b7280; font-size: 0.9rem;">
                                                <strong>Year:</strong> <span id="certificate-year"></span>
                                            </div>
                                            <div id="certificate-description" style="color: #6b7280; font-size: 0.9rem; margin-top: 10px; display: none;">
                                                <strong>Description:</strong> <span id="certificate-desc-text"></span>
                                            </div>
                                        </div>
                                        <div style="width: 120px; height: 120px; border: 2px solid #e5e7eb; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: #f8fafc;">
                                            <img id="certificate-avatar" src="" alt="Certificate Avatar" style="max-width: 100%; max-height: 100%; border-radius: 6px; display: none;">
                                            <div id="certificate-avatar-placeholder" style="color: #9ca3af; font-size: 0.8rem; text-align: center;">
                                                <i class="fas fa-certificate" style="font-size: 2rem; margin-bottom: 5px; display: block;"></i>
                                                Certificate Avatar
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 15px; justify-content: center;">
                                    <button id="download-certificate" class="btn-primary" style="display: none; padding: 12px 24px; background: #667eea; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 500;">
                                        <i class="fas fa-download"></i> Download Certificate
                                    </button>
                                </div>
                            </div>
                            <div id="no-certificate" style="display: none;">
                                <div style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #e5e7eb;">
                                    <p style="color: #6b7280; font-style: italic; margin: 0;">No certificate available for this year</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="cpd-error" style="display: none; text-align: center; padding: 40px; color: #dc2626;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 15px;"></i>
                    <p>Error loading CPD records. Please try again.</p>
                </div>
            </div>
            
            <!-- Qualifications Tab -->
            <div id="qualifications-tab" class="tab-content" style="display: none;">
                <div id="qualifications-loading" style="text-align: center; padding: 40px; color: #6b7280;">
                    <div class="loading-spinner" style="display: inline-block; width: 24px; height: 24px; border: 3px solid #e5e7eb; border-top: 3px solid #667eea; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                    <span style="margin-left: 10px;">Loading qualifications...</span>
                </div>
                
                <div id="qualifications-content" style="display: none;">
                    <!-- Add Qualification Button -->
                    <div style="margin-bottom: 20px;">
                        <button id="add-qualification-btn" class="btn-primary" style="background: #667eea; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 500;">
                            <i class="fas fa-plus"></i> Add Qualification
                        </button>
                    </div>
                    
                    <!-- Qualifications Table -->
                    <div style="background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        <table id="qualifications-table" style="width: 100%; border-collapse: collapse;">
                            <thead style="background: #f8fafc;">
                                <tr>
                                    <th style="padding: 15px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Qualification</th>
                                    <th style="padding: 15px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Awarding Institute</th>
                                    <th style="padding: 15px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Date Attained</th>
                                    <th style="padding: 15px; text-align: center; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Current Designation</th>
                                    <th style="padding: 15px; text-align: center; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="qualifications-tbody">
                                <!-- Populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                    
                    <div id="no-qualifications" style="display: none; text-align: center; padding: 40px; color: #6b7280;">
                        <i class="fas fa-graduation-cap" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                        <p style="font-size: 1.1rem; margin: 0;">No qualifications found</p>
                        <p style="margin: 10px 0 0 0;">Click "Add Qualification" to get started</p>
                    </div>
                </div>
                
                <div id="qualifications-error" style="display: none; text-align: center; padding: 40px; color: #dc2626;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 15px;"></i>
                    <p>Error loading qualifications. Please try again.</p>
                </div>
            </div>
            
            <div id="error-message" style="display: none; text-align: center; padding: 40px; color: #dc2626;">
                <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 15px;"></i>
                <p>Error loading member details. Please try again.</p>
            </div>
        </div>
    </div>
</main>

<!-- Qualification Modal -->
<div id="qualification-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 8px; padding: 30px; width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 id="qualification-modal-title" style="margin: 0; color: #374151;">Add Qualification</h3>
            <button id="close-qualification-modal" style="background: none; border: none; font-size: 1.5rem; color: #6b7280; cursor: pointer;">&times;</button>
        </div>
        
        <form id="qualification-form">
            <input type="hidden" id="qualification-id" value="">
            
            <div style="margin-bottom: 20px;">
                <label for="qualification-designation" style="display: block; margin-bottom: 5px; font-weight: 500; color: #374151;">Qualification *</label>
                <input type="text" id="qualification-designation" required style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
            </div>
            
            <div style="margin-bottom: 20px;">
                <label for="qualification-institute" style="display: block; margin-bottom: 5px; font-weight: 500; color: #374151;">Awarding Institute *</label>
                <input type="text" id="qualification-institute" required style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
            </div>
            
            <div style="margin-bottom: 20px;">
                <label for="qualification-date" style="display: block; margin-bottom: 5px; font-weight: 500; color: #374151;">Date Attained *</label>
                <input type="date" id="qualification-date" required style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: flex; align-items: center; cursor: pointer;">
                    <input type="checkbox" id="qualification-current" style="margin-right: 8px;">
                    <span style="font-weight: 500; color: #374151;">Current Designation</span>
                </label>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" id="cancel-qualification" style="padding: 10px 20px; border: 1px solid #d1d5db; background: white; color: #374151; border-radius: 6px; cursor: pointer;">Cancel</button>
                <button type="submit" id="save-qualification" style="padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 6px; cursor: pointer;">Save</button>
            </div>
        </form>
    </div>
</div>

<style>
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.info-section {
    background: #f8fafc;
    border-radius: 12px;
    padding: 30px;
    margin-bottom: 30px;
    border: 1px solid #e5e7eb;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e5e7eb;
}

.section-header h3 {
    margin: 0;
    color: #374151;
    font-size: 1.3rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.edit-toggle-btn {
    padding: 8px 16px;
    background: #667eea;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.edit-toggle-btn:hover {
    background: #5a67d8;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-group label {
    font-weight: 600;
    color: #374151;
    font-size: 14px;
}

.display-value {
    color: #6b7280;
    font-weight: 400;
    padding: 8px 12px;
    background: white;
    border-radius: 6px;
    border: 1px solid #e5e7eb;
    min-height: 20px;
}

.form-group input,
.form-group select {
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.3s ease;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.btn-primary, .btn-secondary {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin: 0 10px;
}

.btn-primary {
    background: #667eea;
    color: white;
}

.btn-primary:hover {
    background: #5a67d8;
}

.btn-secondary {
    background: #6b7280;
    color: white;
}

.btn-secondary:hover {
    background: #4b5563;
}

/* Custom Select Styles */
.custom-select-container {
    position: relative;
}

.custom-select {
    position: relative;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: white;
}

.select-trigger {
    padding: 10px 12px;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.select-trigger.active {
    background: #f9fafb;
}

.select-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    z-index: 1000;
    display: none;
    max-height: 200px;
    overflow-y: auto;
}

.select-dropdown.show {
    display: block;
}

.select-search {
    padding: 8px;
    border-bottom: 1px solid #e5e7eb;
}

.select-search input {
    width: 100%;
    padding: 8px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
}

.select-options {
    max-height: 160px;
    overflow-y: auto;
}

.select-option {
    padding: 8px 12px;
    cursor: pointer;
    border-bottom: 1px solid #f3f4f6;
}

.select-option:hover {
    background: #f9fafb;
}

.select-option:last-child {
    border-bottom: none;
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    color: white;
    width: 100px;
    text-align: center;
    border: none !important;
}

.status-active { background: #10b981; }
.status-inactive { background: #f59e0b; }
.status-suspended { background: #ef4444; }
.status-pending { background: #3b82f6; }
.status-lapsed { background: #f59e0b; }
.status-revoked { background: #ef4444; }
.status-resigned { background: #6b7280; }
.status-retired { background: #6b7280; }
.status-leftsector { background: #6b7280; }
.status-paused { background: #3b82f6; }
.status-deceased { background: #6b7280; }

.form-group[style*="display: none"] {
    display: none !important;
}

/* Custom Tooltip Styles */
.tooltip-trigger {
    position: relative;
    display: inline-block;
}

.tooltip {
    position: absolute;
    background: #1f2937;
    color: white;
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 13px;
    line-height: 1.4;
    white-space: nowrap;
    z-index: 1000;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0.3s ease;
    pointer-events: none;
    width: 250px;
    white-space: normal;
}

.tooltip.show {
    opacity: 1;
    visibility: visible;
}

.tooltip::before {
    content: '';
    position: absolute;
    border: 6px solid transparent;
    border-bottom-color: #1f2937;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
}

.tooltip-content {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.tooltip-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 18px;
}

.tooltip-label {
    font-weight: 600;
    color: #d1d5db;
    font-size: 12px;
    min-width: 80px;
}

.tooltip-value {
    color: white;
    font-weight: 500;
    font-size: 12px;
    text-align: right;
    flex: 1;
}

.input-feedback, .error-message, .success-message {
    margin-top: 8px;
    padding: 12px;
    border-radius: 6px;
    font-size: 14px;
}
</style>

<script>
jQuery(document).ready(function($) {
    const userId = <?php echo json_encode($user_id); ?>;
    let userDetails = {};
    let organizations = [];
    let memberships = [];
    
    // Load user details
    loadUserDetails(userId);
    
    function loadUserDetails(userId) {
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'iipm_admin_get_user_details',
                nonce: '<?php echo wp_create_nonce('iipm_user_management_nonce'); ?>',
                user_id: userId
            },
            success: function(response) {
                if (response.success) {
                    userDetails = response.data;
                    organizations = response.data.options.organizations;
                    memberships = response.data.options.memberships;
                    displayUserDetails(response.data);
                } else {
                    showError(response.data);
                }
            },
            error: function() {
                showError('Failed to load user details. Please try again.');
            }
        });
    }
    
    function displayUserDetails(data) {
        // Update page title
        const displayName = (data.profile_info.first_name + ' ' + data.profile_info.sur_name).trim();
        $('#page-title').text(displayName || data.basic_info.user_login + ' - User Details');
        
        // Section 1: User Information
        $('#user_login_edit').val(data.basic_info.user_login);
        $('#user_email_edit').val(data.basic_info.user_email);
        $('#member_type_edit').val(data.member_info.member_type || '');
        $('#membership_status_edit').val(data.member_info.membership_status || '');
        
        // Populate memberships dropdown
        const membershipSelect = $('#membership_level_edit');
        membershipSelect.empty().append('<option value="">Select Membership Level</option>');
        memberships.forEach(function(membership) {
            membershipSelect.append(`<option value="${membership.id}">${membership.name}</option>`);
        });
        membershipSelect.val(data.member_info.membership_level || '');
        
        // Initialize tooltip functionality
        initializeMembershipTooltip();
        
        // Update First Name and Surname
        $('#first_name_edit').val(data.profile_info.first_name || '');
        $('#sur_name_edit').val(data.profile_info.sur_name || '');
        
        // Section 2: Contact Information
        $('#email_address_edit').val(data.profile_info.email_address || '');
        $('#user_phone_edit').val(data.profile_info.user_phone || '');
        $('#user_mobile_edit').val(data.profile_info.user_mobile || '');
        $('#city_or_town_edit').val(data.profile_info.city_or_town || '');
        
        // Section 3: Billing Information
        $('#user_payment_method_edit').val(data.profile_info.user_payment_method || '');
        
        // Initialize billing addresses
        setBillingAddresses(data.profile_info.user_payment_method, data.organization_info);
        
        // Section 4: Personal Information (_pers)
        $('#email_address_pers_edit').val(data.profile_info.email_address_pers || '');
        $('#user_phone_pers_edit').val(data.profile_info.user_phone_pers || '');
        $('#user_mobile_pers_edit').val(data.profile_info.user_mobile_pers || '');
        $('#correspondence_email_edit').val(data.profile_info.correspondence_email || '');
        $('#Address_1_pers_edit').val(data.profile_info.Address_1_pers || '');
        $('#Address_2_pers_edit').val(data.profile_info.Address_2_pers || '');
        $('#Address_3_pers_edit').val(data.profile_info.Address_3_pers || '');
        
        // Section 5: Employer Information
        const employerSelect = $('#employer_id');
        employerSelect.empty().append('<option value="">Select Organization</option>');
        organizations.forEach(function(org) {
            employerSelect.append(`<option value="${org.id}">${org.name}</option>`);
        });
        employerSelect.val(data.profile_info.employer_id || '');
        
        // Show/hide employer section based on member type
        if (data.member_info.member_type === 'organisation') {
            $('[data-section="employer-info"]').show();
        } else {
            $('[data-section="employer-info"]').hide();
        }
        
        // Hide loading, show content
        $('#loading-message').hide();
        $('#tab-navigation').show();
        $('#user-details-form').show();
        
        // Initialize edit buttons
        initializeEditButtons();
        
        // Setup payment method change handler
        setupPaymentMethodHandler();
        
        // Setup member type change handler  
        setupMemberTypeHandler();
        
        // Initialize tabs
        initializeTabs();
    }
    
    function showError(message) {
        $('#loading-message').hide();
        $('#error-message').show().find('p').text(message);
    }
    
    function toggleEditMode(section, enable) {
        const $section = section.closest('.info-section');
        const $inputs = $section.find('input:not([type="hidden"]), select');
        
        if (enable) {
            $inputs.prop('disabled', false);
            section.text('Cancel');
            $('.save-btn, .cancel-btn').show();
        } else {
            $inputs.prop('disabled', true);
            section.text('Edit');
            $('.save-btn, .cancel-btn').hide();
        }
    }
    
    
    function initializeEditButtons() {
        $('.edit-toggle-btn').off('click').on('click', function() {
            const section = $(this);
            const isEditing = section.text() === 'Cancel';
            
            if (isEditing) {
                toggleEditMode(section, false);
            } else {
                toggleEditMode(section, true);
            }
        });
    }
    
    
    function setBillingAddresses(paymentMethod, organizationData) {
        console.log("settingThem!", paymentMethod, organizationData);
        
        if (paymentMethod === 'Employer Invoiced' && organizationData) {
            // Use organization address for billing when employer invoiced
            $('#Address_1_billing_edit').val(organizationData.address_line1 || '');
            $('#Address_2_billing_edit').val(organizationData.address_line2 || '');
            $('#Address_3_billing_edit').val(organizationData.address_line3 || '');
        } else {
            // For Direct Invoiced or N/A - use user's own billing addresses
            $('#Address_1_billing_edit').val(userDetails.profile_info.Address_1 || '');
            $('#Address_2_billing_edit').val(userDetails.profile_info.Address_2 || '');
            $('#Address_3_billing_edit').val(userDetails.profile_info.Address_3 || '');
        }
    }
    
    function setupPaymentMethodHandler() {
        $('#user_payment_method_edit').off('change').on('change', function() {
            const paymentMethod = $(this).val();
            setBillingAddresses(paymentMethod, userDetails.organization_info);
        });
    }
    
    
    function setupMemberTypeHandler() {
        $('#member_type_edit').off('change').on('change', function() {
            const memberType = $(this).val();
            if (memberType === 'individual') {
                $('[data-section="employer-info"]').hide();
            } else if (memberType === 'organisation') {
                $('[data-section="employer-info"]').show();
            }
        });
    }
    
    function cancelEditAll() {
        $('.edit-toggle-btn').each(function() {
            toggleEditMode($(this), false);
        });
    }
    
    // Tab functionality
    function initializeTabs() {
        $('.tab-btn').on('click', function() {
            const tabName = $(this).data('tab');
            
            // Update tab buttons
            $('.tab-btn').removeClass('active').css({
                'border-bottom-color': 'transparent',
                'color': '#6b7280'
            });
            $(this).addClass('active').css({
                'border-bottom-color': '#667eea',
                'color': '#667eea'
            });
            
            // Show/hide tab content
            $('.tab-content').hide();
            $(`#${tabName}-tab`).show();
            
            // Load CPD data if CPD tab is selected
            if (tabName === 'cpd') {
                loadCPDData();
            }
            
            // Load qualifications data if qualifications tab is selected
            if (tabName === 'qualifications') {
                loadQualifications();
            }
        });
    }
    
    // Load CPD data using existing functions
    function loadCPDData() {
        $('#cpd-loading').show();
        $('#cpd-stats-container').hide();
        $('#cpd-error').hide();
        
        // Create year selector with current year as default
        populateYearSelector();
        
        // Load CPD data for current year
        const currentYear = new Date().getFullYear();
        $('#cpd-year').val(currentYear);
        loadCPDDataForYear(currentYear);
    }
    
    function populateYearSelector() {
        const $yearSelect = $('#cpd-year');
        $yearSelect.empty();
        
        // Generate years from current year + 1 down to 2020 (latest first)
        const currentYear = new Date().getFullYear();
        for (let year = currentYear; year >= 2020; year--) {
            $yearSelect.append(`<option value="${year}">${year}</option>`);
        }
        
        $('#cpd-year-selector').show();
        
        // Year change handler
        $yearSelect.off('change').on('change', function() {
            loadCPDDataForYear($(this).val());
        });
    }
    
    function loadCPDDataForYear(year) {
        $('#cpd-loading').show();
        
        // Use existing iipm_get_cpd_stats function from CPD record page
        // Pass user_id if available, otherwise let the function get current user ID
        const requestData = {
            action: 'iipm_get_cpd_stats',
            nonce: '<?php echo wp_create_nonce('iipm_portal_nonce'); ?>',
            year: year
        };
        
        // Only add user_id if we have a specific user (admin viewing another user)
        if (userId && userId !== '<?php echo get_current_user_id(); ?>') {
            requestData.user_id = userId;
        }
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: requestData,
            success: function(response) {
                if (response.success) {
                    displayCPDData(response.data);
        } else {
                    showCPDError('Failed to load CPD data for ' + year);
                }
            },
            error: function() {
                showCPDError('Failed to load CPD data for ' + year);
            },
            complete: function() {
                $('#cpd-loading').hide();
            }
        });
    }
    
    function displayCPDData(data) {
        // Update stats cards - using the actual data structure from iipm_get_cpd_stats
        $('#total-cpd-hours').text(data.total_hours || 0);
        
        // Handle target display - show adjusted target in hours, and original target as superscript if different
        const originalTargetMinutes = data.target_minutes || 0;
        const adjustedTargetMinutes = data.adjusted_target_minutes || originalTargetMinutes;
        
        // Convert minutes to hours (round to 0.5)
        const originalTargetHours = Math.round((originalTargetMinutes / 60) * 2) / 2;
        const adjustedTargetHours = Math.round((adjustedTargetMinutes / 60) * 2) / 2;
        
        if (originalTargetMinutes !== adjustedTargetMinutes) {
            $('#original-target').html(`${adjustedTargetHours}<sup style="font-size: 0.7em; color: #6b7280;">${originalTargetHours}</sup> hours`);
            $('#original-target').parent().find('h4').text('Adjusted Target');
        } else {
            $('#original-target').text(adjustedTargetHours + ' hours');
            $('#original-target').parent().find('h4').text('Target');
        }
        
        $('#completion-percentage').text((data.completion_percentage || 0) + '%');
        
        // Display courses from courses_summary
        displayCoursesFromSummary(data.courses_summary || []);
        
        // Load leave requests separately
        loadLeaveRequestsForYear($('#cpd-year').val());
        
        // Handle certificate data from submission_data
        if (data.submission_data) {
            handleCertificate(data.submission_data.certificate_available, data.submission_data);
        } else {
            handleCertificate(false);
        }
        
        $('#cpd-stats-container').show();
    }
    
    function displayCoursesFromSummary(coursesSummary) {
        const $container = $('#courses-list');
        $container.empty();
        
        if (coursesSummary.length === 0) {
            $container.html('<p style="color: #6b7280; font-style: italic; text-align: center; padding: 20px;">No courses completed this year</p>');
            return;
        }
        
        coursesSummary.forEach(function(category) {
            const statusClass = category.completed ? 'status-active' : 'status-inactive';
            
            // Create course details HTML
            let courseDetailsHtml = '';
            if (category.courses && category.courses.length > 0) {
                courseDetailsHtml = '<div style="margin-top: 15px; border-top: 1px solid #e5e7eb; padding-top: 15px;">';
                category.courses.forEach(function(course) {
                    courseDetailsHtml += `
                        <div style="background: #f8fafc; padding: 15px; border-radius: 6px; margin-bottom: 10px; border-left: 4px solid #667eea;">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; color: #374151; margin-bottom: 5px;">${course.courseName}</div>
                                    <div style="color: #6b7280; font-size: 0.9rem; margin-bottom: 3px;">
                                        <strong>Type:</strong> ${course.courseType || 'N/A'}
                                    </div>
                                    <div style="color: #6b7280; font-size: 0.9rem; margin-bottom: 3px;">
                                        <strong>Provider:</strong> ${course.crs_provider || 'N/A'}
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-weight: bold; color: #667eea; font-size: 1.1rem;">${course.hours} hours</div>
                                    <div style="color: #6b7280; font-size: 0.8rem;">${course.minutes} minutes</div>
                                    <div style="color: #6b7280; font-size: 0.8rem; margin-top: 3px;">
                                        ${formatCourseDateTime(course.dateOfReturn)}
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });
                courseDetailsHtml += '</div>';
            }
            
            const courseHtml = `
                <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #e5e7eb; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; margin-bottom: 8px;">
                                <strong style="font-size: 1.1rem;">${category.category}</strong>
                                <span class="status-badge ${statusClass}" style="margin-left: 12px;width: auto !important;">${category.status}</span>
                            </div>
                            <div style="color: #6b7280; font-size: 0.9rem;">
                                ${category.count} course${category.count !== 1 ? 's' : ''} completed
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-weight: bold; color: #667eea; font-size: 1.2rem;">${category.total_hours} hours</div>
                            <div style="color: #6b7280; font-size: 0.9rem;">${category.total_minutes} minutes</div>
                        </div>
                    </div>
                    ${courseDetailsHtml}
                </div>
            `;
            $container.append(courseHtml);
        });
    }
    
    function loadLeaveRequestsForYear(year) {
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'iipm_get_user_leave_requests_for_year',
                nonce: '<?php echo wp_create_nonce('iipm_portal_nonce'); ?>',
                user_id: userId,
                year: year
            },
            success: function(response) {
                if (response.success) {
                    displayLeaveRequests(response.data.leave_requests || []);
                } else {
                    displayLeaveRequests([]);
                }
            },
            error: function() {
                displayLeaveRequests([]);
            }
        });
    }
    
    function formatLeaveDateRange(startDateStr, endDateStr) {
        // Convert dd-mm-yyyy to "Oct 31, 2025" format
        const formatDateString = (dateStr) => {
            if (!dateStr) return '';
            
            const parts = dateStr.split('-');
            if (parts.length === 3) {
                const day = parseInt(parts[0], 10);
                const month = parseInt(parts[1], 10);
                const year = parts[2];
                
                const monthNames = [
                    'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                    'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
                ];
                
                return `${monthNames[month - 1]} ${day}, ${year}`;
            }
            return dateStr;
        };
        
        if (!startDateStr || !endDateStr) {
            return 'Invalid dates';
        }
        
        return `${formatDateString(startDateStr)} to ${formatDateString(endDateStr)}`;
    }
    
    function displayLeaveRequests(leaveRequests) {
        const $container = $('#leave-requests-list');
        $container.empty();
        
        if (leaveRequests.length === 0) {
            $container.html('<p style="color: #6b7280; font-style: italic; text-align: center; padding: 20px;">No leave requests for this year</p>');
            return;
        }
        
        leaveRequests.forEach(function(request) {
            const statusClass = request.status === 'approved' ? 'status-active' : 
                              request.status === 'pending' ? 'status-pending' : 
                              request.status === 'rejected' ? 'status-inactive' : 'status-inactive';
            const requestHtml = `
                <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #e5e7eb; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="flex: 1;">
                            <div style="display: flex; align-items: center; margin-bottom: 8px;">
                                <strong style="font-size: 1.1rem;">${request.title}</strong>
                                <span class="status-badge ${statusClass}" style="margin-left: 12px;">${request.status}</span>
                </div>
                            <div style="color: #6b7280; font-size: 0.9rem;">
                                ${request.reason ? 'Reason: ' + request.reason : ''}
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-weight: bold; color: #374151;">${request.duration_days} days</div>
                            <div style="color: #6b7280; font-size: 0.9rem;">
                                ${formatLeaveDateRange(request.leave_start_date, request.leave_end_date)}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $container.append(requestHtml);
        });
    }
    
    function handleCertificate(available, certificateData = null) {
        if (available && certificateData) {
            $('#certificate-info').show();
            $('#no-certificate').hide();
            
            // Display certificate details
            $('#submission-status').text(certificateData.submission_status || 'Unknown');
            
            // Format submission date to show only hours and minutes
            let formattedSubmissionDate = 'N/A';
            if (certificateData.submission_date) {
                const submissionDate = new Date(certificateData.submission_date);
                formattedSubmissionDate = submissionDate.toLocaleDateString() + ', ' + 
                    submissionDate.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            }
            $('#submission-date').text(formattedSubmissionDate);
            
            // Display certificate data if available
            if (certificateData.certificate_data) {
                $('#certificate-name').text(certificateData.certificate_data.name || 'N/A');
                $('#certificate-year').text(certificateData.certificate_data.year || 'N/A');
                
                // Show description if available
                if (certificateData.certificate_data.description) {
                    $('#certificate-desc-text').text(certificateData.certificate_data.description);
                    $('#certificate-description').show();
                } else {
                    $('#certificate-description').hide();
                }
                
                // Handle certificate avatar
                if (certificateData.certificate_data.avatar_url && certificateData.certificate_data.avatar_url !== '') {
                    $('#certificate-avatar').attr('src', certificateData.certificate_data.avatar_url).show();
                    $('#certificate-avatar-placeholder').hide();
                } else {
                    $('#certificate-avatar').hide();
                    $('#certificate-avatar-placeholder').show();
                }
            } else {
                $('#certificate-name').text('N/A');
                $('#certificate-year').text('N/A');
                $('#certificate-description').hide();
                $('#certificate-avatar').hide();
                $('#certificate-avatar-placeholder').show();
            }
            
            $('#download-certificate').show();
            
            // Certificate download handler - same as member portal
            $('#download-certificate').off('click').on('click', function() {
                const certificateId = certificateData.certificate_id;
                if (certificateId && certificateData.certificate_data) {
                    // Format user name properly (same as member portal)
                    const userName = (userDetails.profile_info.first_name || '') + ' ' + (userDetails.profile_info.sur_name || '');
                    const formattedUserName = userName.trim() || 'User';
                    
                    downloadCertificateDirect(
                        certificateId,
                        formattedUserName,
                        userDetails.profile_info.email_address || '',
                        userDetails.profile_info.Address_1 || '',
                        $('#cpd-year').val()
                    );
                }
            });
        } else {
            $('#certificate-info').hide();
            $('#no-certificate').show();
            $('#download-certificate').hide();
        }
    }
    
    function showCPDError(message) {
        $('#cpd-loading').hide();
        $('#cpd-stats-container').hide();
        $('#cpd-error').show().find('p').text(message);
    }
    
    /**
     * Format course date/time to show only hours and minutes
     */
    function formatCourseDateTime(dateTimeString) {
        if (!dateTimeString || dateTimeString === 'N/A') {
            return 'N/A';
        }
        
        try {
            const date = new Date(dateTimeString);
            if (isNaN(date.getTime())) {
                return 'N/A';
            }
            
            return date.toLocaleDateString() + ', ' + 
                date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        } catch (error) {
            return 'N/A';
        }
    }
    
    /**
     * Download certificate function - same as member portal
     */
    function downloadCertificateDirect(certificateId, userName, userEmail, contactAddress, submissionYear) {
        // Show loading state
        const downloadBtn = $('#download-certificate');
        const originalText = downloadBtn.html();
        downloadBtn.html('<i class="fas fa-spinner fa-spin"></i> Generating PDF...');
        downloadBtn.prop('disabled', true);
        
        // Create direct download URL (same as member portal)
        const params = new URLSearchParams({
            action: 'iipm_download_certificate_direct',
            certificate_id: certificateId,
            user_name: userName,
            user_email: userEmail,
            contact_address: contactAddress,
            submission_year: submissionYear
        });
        
        const downloadUrl = `<?php echo admin_url('admin-ajax.php'); ?>?${params.toString()}`;
        
        // Create a temporary link and trigger download (same as member portal)
        const link = document.createElement('a');
        link.href = downloadUrl;
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        // Reset button state
        setTimeout(() => {
            downloadBtn.html(originalText);
            downloadBtn.prop('disabled', false);
        }, 2000);
    }
    
    // ===== MEMBERSHIP TOOLTIP FUNCTIONS =====
    
    /**
     * Initialize membership tooltip functionality
     */
    function initializeMembershipTooltip() {
        const tooltipTrigger = $('.tooltip-trigger[data-tooltip="membership-info"]');
        
        if (tooltipTrigger.length === 0) return;
        
        // Create tooltip element
        const tooltip = $('<div class="tooltip" id="membership-tooltip"></div>');
        tooltipTrigger.append(tooltip);
        
        // Handle mouse enter
        tooltipTrigger.on('mouseenter', function() {
            const selectedMembershipId = $('#membership_level_edit').val();
            if (selectedMembershipId) {
                const membership = memberships.find(m => m.id == selectedMembershipId);
                if (membership) {
                    showMembershipTooltip(membership);
                }
            }
        });
        
        // Handle mouse leave
        tooltipTrigger.on('mouseleave', function() {
            hideMembershipTooltip();
        });
    }
    
    /**
     * Show membership tooltip with details
     */
    function showMembershipTooltip(membership) {
        const tooltip = $('#membership-tooltip');
        
        const tooltipContent = `
            <div class="tooltip-content">
                <div class="tooltip-row">
                    <span class="tooltip-label">Name:</span>
                    <span class="tooltip-value">${membership.name || 'N/A'}</span>
                </div>
                <div class="tooltip-row">
                    <span class="tooltip-label">Designation:</span>
                    <span class="tooltip-value">${membership.designation || 'N/A'}</span>
                </div>
                <div class="tooltip-row">
                    <span class="tooltip-label">Fee:</span>
                    <span class="tooltip-value">£${membership.fee || '0'}</span>
                </div>
                <div class="tooltip-row">
                    <span class="tooltip-label">CPD Requirements:</span>
                    <span class="tooltip-value">${membership.cpd_requirement || 'N/A'}</span>
                </div>
            </div>
        `;
        
        tooltip.html(tooltipContent);
        tooltip.addClass('show');
        
        // Position tooltip
        positionTooltip(tooltip);
    }
    
    /**
     * Hide membership tooltip
     */
    function hideMembershipTooltip() {
        $('#membership-tooltip').removeClass('show');
    }
    
    /**
     * Position tooltip relative to trigger
     */
    function positionTooltip(tooltip) {
        const trigger = $('.tooltip-trigger[data-tooltip="membership-info"]');
        const triggerOffset = trigger.offset();
        const triggerWidth = trigger.outerWidth();
        const triggerHeight = trigger.outerHeight();
        const tooltipWidth = 250; // Fixed width from CSS
        
        // Position tooltip below the trigger, centered horizontally
        const left = triggerOffset.left + (triggerWidth / 2) - (tooltipWidth / 2);
        const top = triggerOffset.top + triggerHeight + 8; // 8px gap below
        
        tooltip.css({
            position: 'absolute',
            left: '-114px',
            bottom: '-122px'
        });
    }
    
    // ===== QUALIFICATION MANAGEMENT FUNCTIONS =====
    
    /**
     * Load qualifications for the current user
     */
    function loadQualifications() {
        $('#qualifications-loading').show();
        $('#qualifications-content').hide();
        $('#qualifications-error').hide();

        console.log("userDetails.profile_info.user_id", userDetails);
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'iipm_get_user_qualifications',
                nonce: '<?php echo wp_create_nonce('iipm_portal_nonce'); ?>',
                user_id: userDetails.basic_info.user_id
            },
            success: function(response) {
                $('#qualifications-loading').hide();
                if (response.success) {
                    displayQualifications(response.data.qualifications);
                    $('#qualifications-content').show();
                } else {
                    showQualificationsError(response.data || 'Failed to load qualifications');
                }
            },
            error: function() {
                $('#qualifications-loading').hide();
                showQualificationsError('Network error occurred');
            }
        });
    }
    
    /**
     * Display qualifications in the table
     */
    function displayQualifications(qualifications) {
        const $tbody = $('#qualifications-tbody');
        $tbody.empty();
        
        // Store qualifications data for editing
        $tbody.data('qualifications', qualifications);
        
        if (qualifications.length === 0) {
            $('#no-qualifications').show();
            $('#qualifications-table').hide();
            return;
        }
        
        $('#no-qualifications').hide();
        $('#qualifications-table').show();
        
        qualifications.forEach(function(qual) {
            const row = `
                <tr style="border-bottom: 1px solid #e5e7eb;">
                    <td style="padding: 15px; color: #374151;">${qual.designation || 'N/A'}</td>
                    <td style="padding: 15px; color: #374151;">${qual.institute || 'N/A'}</td>
                    <td style="padding: 15px; color: #374151;">${formatQualificationDate(qual.date_attained_txt)}</td>
                    <td style="padding: 15px; text-align: center;">
                        ${qual.isCurrentDesignation == 1 ? 
                            '<i class="fas fa-check" style="color: #10b981; font-size: 1.2rem;"></i>' : 
                            '<i class="fas fa-times" style="color: #ef4444; font-size: 1.2rem;"></i>'
                        }
                    </td>
                    <td style="padding: 15px; text-align: center;">
                        <button onclick="editQualification(${qual.id})" style="background: #f3f4f6; border: 1px solid #d1d5db; padding: 5px 10px; border-radius: 4px; cursor: pointer; margin-right: 5px; color: #374151;">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="deleteQualification(${qual.id})" style="background: #fef2f2; border: 1px solid #fecaca; padding: 5px 10px; border-radius: 4px; cursor: pointer; color: #dc2626;">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            $tbody.append(row);
        });
    }
    
    /**
     * Format qualification date for display
     */
    function formatQualificationDate(dateString) {
        if (!dateString || dateString === 'N/A') {
            return 'N/A';
        }
        
        try {
            const date = new Date(dateString);
            return date.toLocaleDateString();
        } catch (error) {
            return dateString;
        }
    }
    
    /**
     * Show qualifications error
     */
    function showQualificationsError(message) {
        $('#qualifications-error').show().find('p').text(message);
    }
    
    /**
     * Open qualification modal for adding/editing
     */
    function openQualificationModal(qualificationId = null) {
        const modal = $('#qualification-modal');
        const title = $('#qualification-modal-title');
        const form = $('#qualification-form')[0];
        
        // Reset form
        form.reset();
        $('#qualification-id').val('');
        
        if (qualificationId) {
            title.text('Edit Qualification');
            loadQualificationForEdit(qualificationId);
        } else {
            title.text('Add Qualification');
        }
        
        modal.css('display', 'flex');
    }
    
    /**
     * Load qualification data for editing
     */
    function loadQualificationForEdit(qualificationId) {
        // Find qualification in current data
        const qualifications = $('#qualifications-tbody').data('qualifications') || [];
        const qualification = qualifications.find(q => q.id == qualificationId);
        
        if (qualification) {
            $('#qualification-id').val(qualification.id);
            $('#qualification-designation').val(qualification.designation);
            $('#qualification-institute').val(qualification.institute);
            $('#qualification-date').val(qualification.date_attained_txt);
            $('#qualification-current').prop('checked', qualification.isCurrentDesignation == 1);
        }
    }
    
    /**
     * Close qualification modal
     */
    function closeQualificationModal() {
        $('#qualification-modal').hide();
    }
    
    /**
     * Edit qualification - Global function
     */
    window.editQualification = function(qualificationId) {
        openQualificationModal(qualificationId);
    };
    
    /**
     * Delete qualification - Global function
     */
    window.deleteQualification = function(qualificationId) {
        if (!confirm('Are you sure you want to delete this qualification?')) {
            return;
        }
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'iipm_delete_qualification',
                nonce: '<?php echo wp_create_nonce('iipm_portal_nonce'); ?>',
                qualification_id: qualificationId
            },
            success: function(response) {
                if (response.success) {
                    loadQualifications(); // Reload the list
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Network error occurred');
            }
        });
    };
    
    // Qualification modal event handlers
    $('#add-qualification-btn').on('click', function() {
        openQualificationModal();
    });
    
    $('#close-qualification-modal, #cancel-qualification').on('click', function() {
        closeQualificationModal();
    });
    
    $('#qualification-form').on('submit', function(e) {
        e.preventDefault();
        
        const qualificationId = $('#qualification-id').val();
        const designation = $('#qualification-designation').val();
        const institute = $('#qualification-institute').val();
        const dateAttained = $('#qualification-date').val();
        const isCurrent = $('#qualification-current').is(':checked') ? 1 : 0;
        
        if (!designation || !institute || !dateAttained) {
            alert('Please fill in all required fields');
            return;
        }
        
        const action = qualificationId ? 'iipm_update_qualification' : 'iipm_add_qualification';
        const data = {
            action: action,
            nonce: '<?php echo wp_create_nonce('iipm_portal_nonce'); ?>',
            designation: designation,
            institute: institute,
            date_attained_txt: dateAttained,
            is_current_designation: isCurrent,
            user_id: userDetails.basic_info.user_id
        };

        console.log("data", data);
        
        if (qualificationId) {
            data.qualification_id = qualificationId;
        } else {
            data.user_id = userDetails.basic_info.user_id;
        }
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    closeQualificationModal();
                    loadQualifications(); // Reload the list
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Network error occurred');
            }
        });
    });
    
    // Password strength validation
    function validatePasswordStrength(password) {
        const requirements = {
            length: password.length >= 8,
            uppercase: /[A-Z]/.test(password),
            lowercase: /[a-z]/.test(password),
            number: /\d/.test(password),
            special: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)
        };
        
        const score = Object.values(requirements).filter(Boolean).length;
        const strength = score < 3 ? 'weak' : score < 4 ? 'medium' : 'strong';
        
        return { requirements, score, strength };
    }
    
    function updatePasswordStrengthIndicator(password) {
        const indicator = $('.password-strength-indicator');
        const fill = $('.strength-fill');
        const text = $('.strength-text');
        const requirements = $('.password-requirements .requirement');
        
        if (password.length === 0) {
            indicator.hide();
            requirements.removeClass('met');
            return;
        }
        
        indicator.show();
        const validation = validatePasswordStrength(password);
        
        // Update strength bar
        const percentage = (validation.score / 5) * 100;
        fill.css('width', percentage + '%');
        
        // Update strength bar color
        if (validation.strength === 'weak') {
            fill.css('background-color', '#ef4444');
            text.text('Weak password').css('color', '#ef4444');
        } else if (validation.strength === 'medium') {
            fill.css('background-color', '#f59e0b');
            text.text('Medium password').css('color', '#f59e0b');
        } else {
            fill.css('background-color', '#10b981');
            text.text('Strong password').css('color', '#10b981');
        }
        
        // Update requirements
        requirements.each(function() {
            const requirement = $(this).data('requirement');
            if (validation.requirements[requirement]) {
                $(this).addClass('met').css('color', '#10b981');
            } else {
                $(this).removeClass('met').css('color', '#6b7280');
            }
        });
        
        return validation.strength === 'strong';
    }
    
    // Password input event handler
    $('#new_password_edit').on('input', function() {
        const password = $(this).val();
        updatePasswordStrengthIndicator(password);
    });
    
    // Form submission
    $('#user-details-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            action: 'iipm_admin_update_user_details',
            nonce: '<?php echo wp_create_nonce('iipm_user_management_nonce'); ?>',
            user_id: userId
        };
        
        // Validate password if provided
        const passwordField = $('#new_password_edit');
        const password = passwordField.val();
        
        if (password && password.trim() !== '') {
            const isStrongPassword = updatePasswordStrengthIndicator(password);
            if (!isStrongPassword) {
                if (window.notifications) {
                    notifications.error('Password Validation Failed', 'Password must meet all strength requirements.');
                }
                return;
            }
        }
        
        // Collect all form data
        $(this).find('input, select').each(function() {
            const field = $(this);
            if (field.attr('name') && field.val() !== '') {
                formData[field.attr('name')] = field.val();
            }
        });
        
        // Show loading state
        $('.save-btn').html('<i class="fas fa-spinner fa-spin"></i> Saving...').prop('disabled', true);
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    // Show success notification
                    if (window.notifications) {
                        const passwordField = $('#new_password_edit');
                        const password = passwordField.val();
                        let message = 'Member details updated successfully.';
                        if (password && password.trim() !== '') {
                            message += ' Password has been reset.';
                            passwordField.val(''); // Clear password field
                            updatePasswordStrengthIndicator(''); // Hide strength indicator
                        }
                        notifications.success('Member Updated', message);
                    }
                    cancelEditAll();
                    // Reload user details
                    loadUserDetails(userId);
                } else {
                    // Show error notification
                    if (window.notifications) {
                        notifications.error('Update Failed', response.data);
                    }
                }
            },
            error: function() {
                // Show error notification
                if (window.notifications) {
                    notifications.error('Update Failed', 'Error updating user. Please try again.');
                }
            },
            complete: function() {
                $('.save-btn').html('<i class="fas fa-save"></i> Save Changes').prop('disabled', false);
            }
        });
    });
});
</script>

<style>
/* Password Strength Indicator Styles */
.password-reset-container {
    position: relative;
}

.password-strength-indicator {
    margin-top: 8px;
}

.strength-bar {
    height: 4px;
    background: #e5e7eb;
    border-radius: 2px;
    overflow: hidden;
    margin-bottom: 4px;
}

.strength-fill {
    height: 100%;
    width: 0%;
    transition: all 0.3s ease;
    border-radius: 2px;
}

.strength-text {
    font-size: 12px;
    font-weight: 500;
}

.password-requirements {
    margin-top: 8px;
    font-size: 12px;
}

.password-requirements .requirement {
    margin: 2px 0;
    transition: color 0.3s ease;
}

.password-requirements .requirement.met {
    color: #10b981 !important;
}

.password-requirements .requirement.met::before {
    content: "✓ ";
    color: #10b981;
    font-weight: bold;
}

/* Form field styling for password */
#new_password_edit:focus {
    border-color: #8b5a96;
    outline: none;
    box-shadow: 0 0 0 3px rgba(139, 90, 150, 0.1);
}

#new_password_edit:disabled {
    background-color: #f9fafb;
    color: #6b7280;
    cursor: not-allowed;
}
</style>

<?php get_footer(); ?>
