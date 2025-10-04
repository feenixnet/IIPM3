<?php
/**
 * Template Name: Member Portal
 * 
 * Member Portal page with CPD progress tracking and course management
 */

// Redirect if not logged in
if (!is_user_logged_in()) {
    wp_redirect(home_url('/login/'));
    exit;
}

// Include the CPD record API
require_once get_template_directory() . '/includes/cpd-record-api.php';

// Get current user ID and year
$current_user_id = get_current_user_id();
$current_year = date('Y');

// Check if user is admin based on user_is_admin field in member profiles
global $wpdb;
$user_is_admin = $wpdb->get_var($wpdb->prepare(
    "SELECT user_is_admin FROM {$wpdb->prefix}test_iipm_member_profiles WHERE user_id = %d",
    $current_user_id
));

// Redirect admins away from member portal
if ($user_is_admin != 0) {
    wp_redirect(home_url('/admin-dashboard/'));
    exit;
}

// Check if user has active membership status
global $wpdb;
$member_status = $wpdb->get_var($wpdb->prepare(
    "SELECT membership_status FROM {$wpdb->prefix}test_iipm_members WHERE user_id = %d",
    $current_user_id
));

// Check subscription status and update membership status if needed
if (function_exists('iipm_check_subscription_status')) {
    $updated_status = iipm_check_subscription_status($current_user_id);
    if ($updated_status !== $member_status) {
        $member_status = $updated_status;
    }
}

// Auto-assign to 2025 CPD if user has active membership and is not already assigned
if ($member_status === 'active') {
    $cpd_stats = iipm_get_cpd_stats($current_user_id, $current_year);
    $is_user_assigned = isset($cpd_stats['is_user_assigned']) ? $cpd_stats['is_user_assigned'] : false;
    
    // If not assigned, automatically assign to CPD
    if (!$is_user_assigned) {
        $assignment_result = iipm_assign_user_to_cpd($current_user_id);
        if ($assignment_result) {
            // Refresh CPD stats after assignment
            $cpd_stats = iipm_get_cpd_stats($current_user_id, $current_year);
            $is_user_assigned = isset($cpd_stats['is_user_assigned']) ? $cpd_stats['is_user_assigned'] : false;
        }
    }
} else {
    // For inactive members, still check assignment status but don't auto-assign
    $cpd_stats = iipm_get_cpd_stats($current_user_id, $current_year);
    $is_user_assigned = isset($cpd_stats['is_user_assigned']) ? $cpd_stats['is_user_assigned'] : false;
}

// Check if logging period is currently active
$is_logging_period_active = false;
if ($is_user_assigned && isset($cpd_stats['is_logging_period_available'])) {
    $is_logging_period_active = $cpd_stats['is_logging_period_available'];
}

global $wpdb;
$is_submitted = false;
$submitted_rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}test_iipm_submissions WHERE user_id = $current_user_id AND year = $current_year");
error_log("sssss: " . "SELECT * FROM {$wpdb->prefix}test_iipm_submissions WHERE user_id = $id AND year = $current_year");
if(count($submitted_rows) > 0) {
    $is_submitted = true;
}

get_header(); 
?>

<div class="member-portal-page main-container">
    <!-- Header -->
    <div class="container" style="position: relative; z-index: 2;">
        <div class="page-header" style="text-align: center; margin-bottom: 40px;">
            <div>
                <h1 style="color: white; font-size: 2.5rem; margin-bottom: 10px;">Member Portal</h1>
                <p style="color: rgba(255,255,255,0.9); font-size: 1.1rem;">
                    Your CPD overview.
                </p>
            </div>
        </div>
        <!-- Success Alert for Completed CPD -->
        <?php if ($is_user_assigned && !empty($cpd_stats['courses_summary'])): 
            $has_minimum_time = $cpd_stats['completion_percentage'] >= 100;
            $has_all_categories = true;
            foreach ($cpd_stats['courses_summary'] as $category) {
                if ($category['required'] > 0 && $category['total_hours'] < 1) {
                    $has_all_categories = false;
                    break;
                }
            }
            $is_fully_completed = ($has_minimum_time && $has_all_categories);
        ?>
            <?php if ($is_fully_completed): ?>
            <div class="cpd-success-alert" id="cpd-success-alert">
                <div class="alert-content">
                    <div class="alert-icon">🎉</div>
                    <div class="alert-message">
                        <h4>Congratulations!</h4>
                        <p>You have successfully completed all required CPD courses for <?php echo $current_year; ?>. Your professional development is up to date!</p>
                    </div>
                    <button class="alert-close" onclick="closeSuccessAlert()">×</button>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- Submission Status Section -->
        <?php if ($is_submitted): ?>
        <div class="submission-status-section">
            <div class="submission-status-card">
                <div class="status-header">
                    <div class="status-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="status-content">
                        <h2>CPD Submission Complete!</h2>
                        <p>Congratulations! You have successfully submitted your CPD training for <?php echo $current_year; ?>.</p>
                    </div>
                </div>
                
                <div class="submission-details" id="submission-details">
                    <div class="loading-spinner"></div>
                </div>
                
                <!-- Training Table Container for Submitted Users -->
                <div class="training-table-section" id="training-table-section">
                    <!-- Training table will be rendered here -->
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!$is_submitted): ?>
        <div class="portal-layout">
            <?php if ($is_user_assigned): ?>
            <!-- Left Side - My CPD Course (for assigned users) -->
            <div class="cpd-course-panel">
                <div class="cpd-course-card">
                    <h3>My CPD Course</h3>
                    
                    <button class="btn btn-primary" id="log-training-btn" <?php echo (!$is_logging_period_active || $is_submitted) ? 'disabled' : ''; ?>>
                        <?php 
                        if ($is_submitted) {
                            echo 'Training Logging Disabled';
                        } elseif ($is_logging_period_active) {
                            echo 'Log Training';
                        } else {
                            echo 'Logging Period Closed';
                        }
                        ?>
                    </button>
                    
                    <div class="progress-section">
                        <div class="progress-header">
                            <h4>Category Progress</h4>
                            <div class="target-info">
                                <span class="target-label">Target:</span>
                                <span class="target-value" id="target-minutes">330 minutes</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="progress-info">
                                <span class="category-name">Pensions</span>
                                <span class="progress-status" id="pensions-status">0/1</span>
                            </div>
                            <div class="strength-bar">
                                <div class="strength-fill" id="pensions-progress" style="width: 0%"></div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="progress-info">
                                <span class="category-name">Savings & Investments</span>
                                <span class="progress-status" id="savings-status">0/1</span>
                            </div>
                            <div class="strength-bar">
                                <div class="strength-fill" id="savings-progress" style="width: 0%"></div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="progress-info">
                                <span class="category-name">Ethics</span>
                                <span class="progress-status" id="ethics-status">0/1</span>
                            </div>
                            <div class="strength-bar">
                                <div class="strength-fill" id="ethics-progress" style="width: 0%"></div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="progress-info">
                                <span class="category-name">Life Assurance</span>
                                <span class="progress-status" id="life-status">0/1</span>
                            </div>
                            <div class="strength-bar">
                                <div class="strength-fill" id="life-progress" style="width: 0%"></div>
                            </div>
                        </div>
                        
                        <div class="total-progress">
                            <span class="total-label">Total:</span>
                            <span class="target-status" id="total-status">0/4</span>
                        </div>
                    </div>
                    
                    <div class="time-progress-section">
                        <div class="progress-header">
                            <h4>Time Progress</h4>
                            <div class="time-info">
                                <span class="time-label">Progress</span>
                                <span class="time-percentage" id="time-percentage">0%</span>
                            </div>
                        </div>
                        
                        <div class="time-progress-bar">
                            <div class="time-progress-fill" id="time-progress-fill" style="width: 0%"></div>
                        </div>
                        
                        <div class="time-details">
                            <div class="time-item">
                                <span class="time-label w-100">Completed</span>
                                <span class="time-value" id="completed-minutes">0 minutes</span>
                            </div>
                            <div class="time-item">
                                <span class="time-label">Remaining</span>
                                <span class="time-value" id="remaining-minutes">330 minutes</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- <button class="btn btn-outline" id="submit-return-btn">Submit my return</button> -->
                    
                    <!-- CPD Assignment and Submission Buttons -->
                    <?php if (!$is_submitted): ?>
                        <div class="cpd-action-buttons" id="cpd-action-buttons" style="display: none;">
                            <button class="btn btn-success" id="submit-cpd-btn">
                                <span class="btn-icon"><i class="fas fa-check"></i></span>
                                Submit <?php echo $current_year; ?> CPD return
                            </button>
                        </div>
                        
                        <!-- Validation Message Area -->
                        <div class="cpd-validation-message" id="cpd-validation-message" style="display: none;">
                            <!-- Validation messages will be shown here -->
                        </div>
                    <?php endif; ?>
                    
                    <div class="cpd-dates-section">
                        <h4>Important CPD Dates</h4>
                        <div class="dates-grid">
                            <div class="date-item">
                                <span class="date-label">Logging Period:</span>
                                <span class="date-value" id="logging-period">-</span>
                            </div>
                            <div class="date-item">
                                <span class="date-label">Submission Period:</span>
                                <span class="date-value" id="submission-period">-</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert-banner">
                        <span class="alert-icon"><i class="fas fa-exclamation-triangle"></i></span>
                        <span class="alert-text" id="deadline-warning">Submit your CPD before the deadline</span>
                    </div>
                </div>
            </div>

            <!-- Right Side (for assigned users) -->
            <div class="portal-right-panel">
                <!-- Quick Links -->
                <div class="quick-links">
                    <div class="quick-link-card" id="all-courses-card">
                        <div class="card-icon"><i class="fas fa-book"></i></div>
                        <h4>All Courses</h4>
                        <?php if ($is_submitted): ?>
                            <span class="card-link disabled" style="color: #9ca3af; cursor: not-allowed;">Disabled <i class="fas fa-lock"></i></span>
                        <?php else: ?>
                            <a href="<?php echo home_url('/cpd-courses/?logging_available=' . ($is_logging_period_active ? '1' : '0')); ?>" class="card-link">Browse courses <i class="fas fa-arrow-right"></i></a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="quick-link-card">
                        <div class="card-icon"><i class="fas fa-calendar"></i></div>
                        <h4>Submit Leave Request</h4>
                        <?php if ($is_submitted): ?>
                            <span class="card-link disabled" style="color: #9ca3af; cursor: not-allowed;">Disabled <i class="fas fa-lock"></i></span>
                        <?php else: ?>
                            <a href="<?php echo home_url('/leave-request/') ?>" class="card-link">Submit <i class="fas fa-arrow-right"></i></a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recently Logged Training -->
                <div class="recently-logged-training">
                    <h3>Recently Logged Training</h3>
                    
                    <div class="training-content" id="training-content">
                        <div class="no-training-message">
                            <div class="no-training-icon">💻</div>
                            <h4>No training history yet</h4>
                            <p>Start your CPD journey by logging your first training session</p>
                            <button class="btn btn-primary" id="log-first-training-btn" <?php echo (!$is_logging_period_active || $is_submitted) ? 'disabled' : ''; ?>>
                                <?php 
                                if ($is_submitted) {
                                    echo 'Training Logging Disabled';
                                } elseif ($is_logging_period_active) {
                                    echo 'Log your first training';
                                } else {
                                    echo 'Logging Period Closed';
                                }
                                ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Inactive Member Section -->
            <div class="inactive-member-panel">
                <div class="cpd-course-card">
                    <div class="inactive-content">
                        <div class="inactive-icon"><i class="fas fa-user-slash"></i></div>
                        <h3>Account Inactive</h3>
                        <p class="inactive-description">
                            Your membership is currently inactive. Please contact support to reactivate your account and access CPD training.
                        </p>
                        <a href="mailto:info@iipm.ie" class="btn btn-primary">
                            <span class="btn-icon"><i class="fas fa-envelope"></i></span>
                            Contact Support
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Log Training Modal -->
<div class="modal-overlay" id="log-training-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Log CPD Training</h3>
            <button class="modal-close" id="modal-close">×</button>
        </div>
        
        <div class="modal-body">
            <p>Choose how you'd like to log your CPD training:</p>
            
            <div class="training-options">
                <div class="training-option" id="pre-approved-option">
                    <div class="option-icon"><i class="fas fa-book"></i></div>
                    <h4>Add from our course list</h4>
                    <p>Select from our library of approved courses. Automatically approved upon submission.</p>
                </div>
                
                <div class="training-option" id="external-training-option">
                    <div class="option-icon"><i class="fas fa-edit"></i></div>
                    <h4>Ask for a couse to be added to our list</h4>
                    <p>Submit training from external providers. Requires approval from admin.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Course Confirmation Modal -->
<div class="modal-overlay" id="course-confirmation-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Confirm Course Selection</h3>
            <button class="modal-close" id="course-modal-close">×</button>
        </div>
        
        <div class="modal-body">
            <div class="course-details" id="course-details">
                <!-- Course details will be populated here -->
            </div>
            
            <div class="confirmation-message">
                <p>Are you going to learn this course?</p>
            </div>
            
            <div class="modal-actions">
                <button class="btn-secondary" id="cancel-course">Cancel</button>
                <button class="btn-primary" id="confirm-course">Yes, I will learn this</button>
            </div>
        </div>
    </div>
</div>

<style>
    .member-portal-page {
        min-height: 100vh;
        padding-top: 0;
    }

    .portal-header {
        background: linear-gradient(135deg, #8b5a96 0%, #6b4c93 100%);
        color: white;
        padding: 40px 0;
        margin-bottom: 30px;
        padding-top: 120px;
    }

    .header-content {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .breadcrumb {
        font-size: 14px;
        opacity: 0.9;
    }

    .breadcrumb a {
        color: white;
        text-decoration: none;
    }

    .separator {
        margin: 0 8px;
    }

    .portal-header h1 {
        margin: 0;
        font-size: 2.5rem;
        font-weight: 600;
    }

    /* CPD Success Alert */
    .cpd-success-alert {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        border: 1px solid #10b981;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 30px;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        animation: slideDown 0.5s ease-out;
    }

    .cpd-success-alert .alert-content {
        display: flex;
        align-items: center;
        gap: 16px;
        color: white;
    }

    .cpd-success-alert .alert-icon {
        font-size: 32px;
        flex-shrink: 0;
    }

    .cpd-success-alert .alert-message {
        flex: 1;
    }

    .cpd-success-alert .alert-message h4 {
        margin: 0 0 8px 0;
        font-size: 20px;
        font-weight: 600;
        color: white;
    }

    .cpd-success-alert .alert-message p {
        margin: 0;
        font-size: 16px;
        color: rgba(255, 255, 255, 0.9);
        line-height: 1.5;
    }

    .cpd-success-alert .alert-close {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        color: white;
        font-size: 24px;
        cursor: pointer;
        padding: 0;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: background 0.2s;
        flex-shrink: 0;
    }

    .cpd-success-alert .alert-close:hover {
        background: rgba(255, 255, 255, 0.3);
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes slideUp {
        from {
            opacity: 1;
            transform: translateY(0);
        }
        to {
            opacity: 0;
            transform: translateY(-20px);
        }
    }

    .portal-layout {
        display: grid;
        grid-template-columns: 350px 1fr;
        gap: 30px;
        align-items: start;
    }
    
    .portal-layout.welcome-layout {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 60vh;
    }

    .cpd-course-panel {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    
    .cpd-welcome-panel {
        width: 100%;
        max-width: 700px;
        margin: 0 auto;
    }
    
    .cpd-welcome-panel .cpd-course-card {
        padding: 0;
        box-shadow: none;
        background: transparent;
    }
    
    .inactive-member-panel {
        width: 100%;
        max-width: 700px;
        margin: 0 auto;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 60vh;
    }
    
    .inactive-member-panel .cpd-course-card {
        padding: 0;
        box-shadow: none;
        background: transparent;
    }
    
    .inactive-content {
        text-align: center;
        padding: 40px;
    }
    
    .inactive-icon {
        font-size: 48px;
        color: #ef4444;
        margin-bottom: 20px;
    }
    
    .inactive-content h3 {
        color: #374151;
        margin-bottom: 16px;
        font-size: 24px;
    }
    
    .inactive-description {
        color: #6b7280;
        margin-bottom: 24px;
        font-size: 16px;
        line-height: 1.5;
    }

    .cpd-course-card {
        background: white;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .cpd-course-card h3 {
        margin: 0 0 20px 0;
        font-size: 1.3rem;
        font-weight: 600;
        color: #1f2937;
    }



    .progress-section {
        margin-bottom: 20px;
    }

    .progress-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 1px solid #e5e7eb;
    }

    .progress-header h4 {
        margin: 0;
        font-size: 16px;
        font-weight: 600;
        color: #1f2937;
    }

    .target-info {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
    }

    .target-label {
        color: #6b7280;
        font-weight: 500;
    }

    .target-value {
        color: #8b5a96;
        font-weight: 600;
    }

    /* Time Progress Section */
    .time-progress-section {
        margin-bottom: 20px;
        padding: 20px;
        background: #f8fafc;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }

    .time-progress-bar {
        width: 100%;
        height: 12px;
        background: #e5e7eb;
        border-radius: 6px;
        overflow: hidden;
        margin: 16px 0;
    }

    .time-progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #10b981 0%, #059669 100%);
        border-radius: 6px;
        transition: width 0.5s ease;
    }

    .time-details {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-top: 16px;
    }

    .time-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 12px;
        background: white;
        border-radius: 6px;
        border: 1px solid #e5e7eb;
    }

    .time-item .time-label {
        width: 100%;
        display: block;
        text-align: center;
        color: #6b7280;
        font-weight: 500;
        font-size: 13px;
    }

    .time-item .time-value {
        color: #1f2937;
        font-weight: 600;
        font-size: 13px;
        text-align: center;
    }

    .time-info .time-label {
        color: #6b7280;
        font-weight: 500;
    }

    .time-percentage {
        color: #10b981;
        font-weight: 600;
    }

    /* CPD Action Buttons */
    .cpd-action-buttons {
        margin-top: 20px;
        margin-bottom: 20px;
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }

    .cpd-action-buttons .btn {
        flex: 1;
        min-width: 150px;
    }

    /* CPD Alert */
    .cpd-alert {
        margin-top: 20px;
        background: #fef3c7;
        border: 1px solid #f59e0b;
        border-radius: 8px;
        padding: 16px;
    }

    .alert-content {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .alert-icon {
        font-size: 20px;
    }

    .alert-message {
        flex: 1;
        color: #92400e;
        font-weight: 500;
    }

    .alert-close {
        background: none;
        border: none;
        color: #92400e;
        font-size: 18px;
        cursor: pointer;
        padding: 0;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: background 0.2s;
    }

    .alert-close:hover {
        background: rgba(146, 64, 14, 0.1);
    }

    /* Unassigned User Section removed */

    .btn-icon {
        font-size: 20px;
    }

    .logging-period-info {
        background: white;
        border-radius: 12px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 16px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .period-icon {
        font-size: 32px;
        flex-shrink: 0;
    }

    .period-details h4 {
        color: #1e293b;
        font-size: 18px;
        font-weight: 600;
        margin: 0 0 8px 0;
    }

    .period-details p {
        color: #64748b;
        font-size: 14px;
        margin: 0;
    }

    .progress-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }

    .category-name {
        font-size: 14px;
        color: #374151;
        font-weight: 500;
    }

    .progress-status {
        font-size: 14px;
        color: #6b7280;
        font-weight: 600;
    }

    /* Custom styling for progress form groups */
    .progress-section .form-group {
        margin-bottom: 20px;
    }

    .progress-section .form-group:last-child {
        margin-bottom: 0;
    }

    /* Make buttons full width */
    .cpd-course-card .btn {
        width: 100%;
        margin-bottom: 20px;
    }

    .cpd-course-card .btn:last-child {
        margin-bottom: 0;
    }

    /* CPD Dates Section */
    .cpd-dates-section {
        margin-bottom: 20px;
        padding: 16px;
        background: #f8fafc;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }

    .cpd-dates-section h4 {
        margin: 0 0 12px 0;
        font-size: 14px;
        font-weight: 600;
        color: #374151;
    }

    .dates-grid {
        display: grid;
        gap: 8px;
    }

    .date-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 13px;
    }

    .date-label {
        color: #6b7280;
        font-weight: 500;
    }

    .date-value {
        color: #1f2937;
        font-weight: 600;
    }

    .total-progress {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 16px;
        border-top: 1px solid #e5e7eb;
        margin-top: 16px;
    }

    .total-label {
        font-size: 16px;
        color: #1f2937;
        font-weight: 600;
    }

    .total-status {
        font-size: 16px;
        color: #8b5a96;
        font-weight: 600;
    }



    .alert-banner {
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-radius: 6px;
        padding: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .alert-icon {
        font-size: 16px;
    }

    .alert-text {
        font-size: 14px;
        color: #dc2626;
        font-weight: 500;
    }

    .portal-right-panel {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .quick-links {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .quick-link-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        text-align: center;
        transition: transform 0.2s;
    }

    .quick-link-card:hover {
        transform: translateY(-2px);
    }

    .card-icon {
        font-size: 24px;
        margin-bottom: 12px;
    }

    .quick-link-card h4 {
        margin: 0 0 12px 0;
        font-size: 16px;
        font-weight: 600;
        color: #1f2937;
    }

    .card-link {
        color: #3b82f6;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
    }

    .card-link:hover {
        text-decoration: underline;
    }

    .card-link.disabled {
        color: #9ca3af !important;
        cursor: not-allowed !important;
        text-decoration: none !important;
    }

    .card-link.disabled:hover {
        text-decoration: none !important;
    }

    /* Submission Status Section */
    .submission-status-section {
        margin-bottom: 40px;
    }

    .submission-status-card {
        background: white;
        border-radius: 20px;
        padding: 40px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        text-align: center;
    }

    .status-header {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 20px;
        margin-bottom: 30px;
    }

    .status-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #10b981, #059669);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 2.5rem;
        box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
    }

    .status-content h2 {
        color: #1f2937;
        font-size: 2rem;
        font-weight: 700;
        margin: 0 0 10px 0;
    }

    .status-content p {
        color: #6b7280;
        font-size: 1.1rem;
        margin: 0;
    }

    .submission-details {
        background: #f8fafc;
        border-radius: 16px;
        padding: 30px;
        margin-top: 20px;
    }

    .training-table-section {
        margin-top: 20px;
    }

    .status-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .status-item {
        background: white;
        padding: 20px;
        border-radius: 12px;
        border-left: 4px solid #8b5a96;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .status-item h4 {
        color: #374151;
        font-size: 0.9rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin: 0 0 8px 0;
    }

    .status-item .value {
        color: #1f2937;
        font-size: 1.2rem;
        font-weight: 700;
        margin: 0;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 0.9rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-badge.pending {
        background: #fef3c7;
        color: #92400e;
    }

    .status-badge.approved {
        background: #d1fae5;
        color: #065f46;
    }

    .status-badge.rejected {
        background: #fee2e2;
        color: #991b1b;
    }

    .completed-courses-section {
        background: white;
        border-radius: 12px;
        padding: 24px;
        margin: 20px 0;
        border: 1px solid #e5e7eb;
    }

    .courses-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 1px solid #e5e7eb;
    }

    .courses-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #8b5a96 0%, #6b4c93 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 18px;
    }

    .courses-title {
        color: #1f2937;
        font-size: 1.25rem;
        font-weight: 600;
        margin: 0;
    }

    .courses-list {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .course-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px;
        background: #f8fafc;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }

    .course-info {
        flex: 1;
    }

    .course-name {
        color: #1f2937;
        font-size: 1rem;
        font-weight: 600;
        margin: 0 0 8px 0;
    }

    .course-details {
        display: flex;
        gap: 16px;
        flex-wrap: wrap;
    }

    .course-category,
    .course-duration,
    .course-provider {
        color: #6b7280;
        font-size: 0.875rem;
        background: white;
        padding: 4px 8px;
        border-radius: 4px;
        border: 1px solid #e5e7eb;
    }

    .course-status {
        color: #10b981;
        font-size: 20px;
    }

    .recent-training-section {
        background: white;
        border-radius: 12px;
        padding: 24px;
        margin: 20px 0;
        border: 1px solid #e5e7eb;
    }

    .training-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 1px solid #e5e7eb;
    }

    .training-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 18px;
    }

    .training-title-submitted {
        color: #1f2937;
        font-size: 20px;
        font-weight: 600;
        margin: 0 0 0 10px;
    }

    .training-summary {
        display: flex;
        gap: 24px;
        margin-bottom: 20px;
        padding: 16px;
        background: #f8fafc;
        border-radius: 8px;
    }

    .summary-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .summary-label {
        color: #6b7280;
        font-size: 0.875rem;
        font-weight: 500;
    }

    .summary-value {
        color: #1f2937;
        font-size: 1.125rem;
        font-weight: 700;
    }

    .training-table-container {
        overflow-x: auto;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
    }

    .training-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
    }

    .training-table th {
        background: #f8fafc;
        padding: 12px 16px;
        text-align: left;
        font-weight: 600;
        color: #374151;
        border-bottom: 1px solid #e5e7eb;
        font-size: 0.875rem;
    }

    .training-table td {
        padding: 12px 16px;
        border-bottom: 1px solid #f3f4f6;
        color: #6b7280;
        font-size: 0.875rem;
        text-align: left;
    }

    .training-table tbody tr:hover {
        background: #f8fafc;
    }

    .training-table tbody tr:last-child td {
        border-bottom: none;
    }

    .course-name-cell {
        font-weight: 600;
        color: #1f2937;
        max-width: 200px;
    }

    .category-cell {
        color: #6366f1;
        font-weight: 500;
    }

    .date-cell {
        color: #6b7280;
        white-space: nowrap;
    }

    .duration-cell {
        font-weight: 600;
        color: #059669;
    }

    .status-cell {
        text-align: center;
    }

    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-badge.completed {
        background: #d1fae5;
        color: #065f46;
    }

    .status-badge.pending {
        background: #fef3c7;
        color: #92400e;
    }

    .certificate-section {
        background: white;
        border-radius: 12px;
        padding: 25px;
        border: 2px solid #e5e7eb;
        margin-top: 20px;
    }

    .certificate-section.has-certificate {
        border-color: #10b981;
        background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
    }

    .certificate-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 15px;
    }

    .certificate-icon {
        width: 40px;
        height: 40px;
        background: #10b981;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.2rem;
    }

    .certificate-title {
        color: #1f2937;
        font-size: 1.3rem;
        font-weight: 700;
        margin: 0;
    }

    .certificate-info {
        color: #6b7280;
        font-size: 1rem;
        margin: 0 0 20px 0;
    }

    .download-btn {
        background: linear-gradient(135deg, #8b5a96, #6b4c93);
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 8px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }

    .download-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(139, 90, 150, 0.3);
        color: white;
        text-decoration: none;
    }

    .download-btn:disabled {
        background: #9ca3af;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    @media (max-width: 768px) {
        .submission-status-card {
            padding: 30px 20px;
        }

        .status-header {
            flex-direction: column;
            text-align: center;
        }

        .status-icon {
            width: 60px;
            height: 60px;
            font-size: 2rem;
        }

        .status-content h2 {
            font-size: 1.5rem;
        }

        .status-info {
            grid-template-columns: 1fr;
        }
    }

    .recently-logged-training {
        background: white;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .recently-logged-training h3 {
        margin: 0 0 20px 0;
        font-size: 1.2rem;
        font-weight: 600;
        color: #8b5a96;
    }

    .training-content {
        margin-bottom: 20px;
    }

    .no-training-message {
        text-align: center;
        padding: 40px 20px;
        border: 2px dashed #d1d5db;
        border-radius: 8px;
    }

    .no-training-icon {
        font-size: 48px;
        margin-bottom: 16px;
        opacity: 0.5;
    }

    .no-training-message h4 {
        margin: 0 0 8px 0;
        font-size: 18px;
        color: #374151;
    }

    .no-training-message p {
        margin: 0 0 20px 0;
        color: #6b7280;
        font-size: 14px;
    }



    .see-history-link {
        color: #3b82f6;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
    }

    .see-history-link:hover {
        text-decoration: underline;
    }

    /* Training Grid */
    .training-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 16px;
        margin-bottom: 20px;
    }

    .training-item {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 16px;
        transition: all 0.2s;
    }

    .training-item:hover {
        border-color: #8b5a96;
        box-shadow: 0 2px 8px rgba(139, 90, 150, 0.1);
    }

    .training-header-submitted {
        display: flex;
        justify-content: left !important;
        margin-bottom: 12px;
    }

    
    .training-title {
        font-size: 14px;
        font-weight: 600;
        color: #1f2937;
        line-height: 1.4;
        margin: 0 10px 0 0;
    }

    .training-actions {
        display: flex;
        gap: 8px;
    }

    .action-btn {
        padding: 4px 8px;
        border: none;
        border-radius: 4px;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .complete-btn {
        background:rgb(112, 112, 112);
        color: white;
    }

    .complete-btn:hover {
        background:rgb(0, 0, 0);
    }

    .completed-btn {
        background: #10b981;
        color: white;
        cursor: default;
    }

    .added-btn {
        background: #10b981;
        color: white;
        cursor: default;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 14px;
    }

    .added-btn i {
        font-size: 16px;
    }

    .delete-btn {
        color:rgb(255, 65, 65);
        border: none;
        background: transparent;
    }

    .delete-btn i {
        font-size: 16px;
    }

    .delete-btn:hover {
        color: rgb(223, 16, 16);
    }

    .training-meta {
        display: flex;
        flex-direction: column;
        gap: 4px;
        margin-bottom: 12px;
    }

    .meta-item {
        display: flex;
        justify-content: space-between;
        font-size: 12px;
    }

    .meta-label {
        color: #6b7280;
    }

    .meta-value {
        color: #374151;
        font-weight: 500;
    }

    .training-date {
        font-size: 12px;
        color: #6b7280;
        text-align: center;
        padding-top: 8px;
        border-top: 1px solid #e5e7eb;
    }

    /* Modal Styles */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
    }

    .modal-content {
        background: white;
        border-radius: 12px;
        max-width: 500px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
    }

    .modal-header {
        background: linear-gradient(135deg, #8b5a96 0%, #6b4c93 100%);
        color: white;
        padding: 20px 24px;
        border-radius: 12px 12px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header h3 {
        margin: 0;
        font-size: 1.2rem;
        font-weight: 600;
    }

    .modal-close {
        background: none;
        border: none;
        color: white;
        font-size: 24px;
        cursor: pointer;
        padding: 0;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: background 0.2s;
    }

    .modal-close:hover {
        background: rgba(255, 255, 255, 0.1);
    }

    .training-item:last-child {
        border-bottom: 1px solid #e5e7eb;
    }

    .modal-body {
        padding: 24px;
    }

    .modal-body p {
        margin: 0 0 20px 0;
        color: #374151;
        font-size: 16px;
    }

    .training-options {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    .training-option {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
    }

    .training-option:hover {
        border-color: #8b5a96;
        box-shadow: 0 2px 8px rgba(139, 90, 150, 0.1);
    }

    .training-option.selected {
        border-color: #ff6b35;
        background: #fff7ed;
    }

    .option-icon {
        font-size: 32px;
        margin-bottom: 12px;
    }

    .training-option h4 {
        margin: 0 0 8px 0;
        font-size: 16px;
        font-weight: 600;
        color: #1f2937;
    }

    .training-option p {
        margin: 0;
        font-size: 14px;
        color: #6b7280;
        line-height: 1.4;
    }

    .course-details {
        background: #f9fafb;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 20px;
    }

    .course-detail-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 8px;
    }

    .course-detail-item:last-child {
        margin-bottom: 0;
    }

    .detail-label {
        font-weight: 500;
        color: #374151;
    }

    .detail-value {
        color: #6b7280;
    }

    .confirmation-message {
        text-align: center;
        margin-bottom: 24px;
    }

    .confirmation-message p {
        font-size: 18px;
        font-weight: 600;
        color: #1f2937;
    }

    .period-details h4 {
        text-align: left !Important;
    }

    .modal-actions {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
    }

    .btn-secondary {
        padding: 10px 20px;
        background: #f3f4f6;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        color: #374151;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: background 0.2s;
    }

    .btn-secondary:hover {
        background: #e5e7eb;
    }

    .btn-primary {
        padding: 10px 20px;
        background: #8b5a96;
        border: none;
        border-radius: 6px;
        color: white;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: background 0.2s;
    }

    .btn-primary:hover {
        background: #6b4c93;
    }

    @media (max-width: 768px) {
        .portal-layout {
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        .quick-links {
            grid-template-columns: 1fr;
        }
        
        .training-grid {
            grid-template-columns: 1fr;
        }
        
        .portal-header h1 {
            font-size: 2rem;
        }
        
        .training-options {
            grid-template-columns: 1fr;
        }
        
        .training-summary {
            flex-direction: column;
            gap: 12px;
        }

        .training-table-container {
            font-size: 0.75rem;
        }

        .training-table th,
        .training-table td {
            padding: 8px 4px;
        }

        .course-name-cell {
            max-width: 120px;
            word-wrap: break-word;
        }
    }

    /* Validation Message Styles */
    .cpd-validation-message {
        margin-top: 15px;
    }

    .validation-alert {
        display: flex;
        align-items: flex-start;
        background: #fef3cd;
        border: 1px solid #fbbf24;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 20px;
    }

    .validation-icon {
        color: #d97706;
        font-size: 20px;
        margin-right: 12px;
        margin-top: 2px;
        flex-shrink: 0;
    }

    .validation-content {
        flex: 1;
    }

    .validation-content h4 {
        color: #92400e;
        font-size: 16px;
        font-weight: 600;
        margin: 0 0 8px 0;
    }

    .validation-content p {
        color: #92400e;
        font-size: 14px;
        margin: 0 0 8px 0;
    }

    .validation-content ul {
        color: #92400e;
        font-size: 14px;
        margin: 0;
        padding-left: 20px;
    }

    .validation-content li {
        margin-bottom: 4px;
    }

    @media (max-width: 768px) {
        .validation-alert {
            flex-direction: column;
            text-align: center;
        }
        
        .validation-icon {
            margin-right: 0;
            margin-bottom: 8px;
        }
    }
</style>

<script>
    // Define ajaxurl for AJAX calls
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    var isUserAssigned = <?php echo $is_user_assigned ? 'true' : 'false'; ?>;
    var isTrainingCompleted = <?php echo $cpd_stats['completion_percentage'] >= 100 ? 'true' : 'false'; ?> == 'true' ? true : false;
    var statData = null;
    
    document.addEventListener('DOMContentLoaded', function() {
        // Get DOM elements
        const logTrainingBtn = document.getElementById('log-training-btn');
        const logFirstTrainingBtn = document.getElementById('log-first-training-btn');
        const logTrainingModal = document.getElementById('log-training-modal');
        const modalClose = document.getElementById('modal-close');
        const preApprovedOption = document.getElementById('pre-approved-option');
        const externalTrainingOption = document.getElementById('external-training-option');
        const courseConfirmationModal = document.getElementById('course-confirmation-modal');
        const courseModalClose = document.getElementById('course-modal-close');
        const cancelCourseBtn = document.getElementById('cancel-course');
        const confirmCourseBtn = document.getElementById('confirm-course');
        const trainingContent = document.getElementById('training-content');
        const portalRightPanel = document.getElementById('portal-right-panel');
        const portalLeftPanel = document.getElementById('portal-left-panel');
        const coursePanel = document.getElementById('cpd-course-panel');
        const welcomePanel = document.getElementById('cpd-welcome-panel');
        
        // Initialize the page
        initializePage();
        
        // Load submission details if user has submitted
        <?php if ($is_submitted): ?>
        loadSubmissionDetails();
        <?php endif; ?>
        
        /**
         * Initialize the page
         */
        function initializePage() {
            // Load initial data
            loadCompletedCpdStats();
            
            // Always load training data for assigned users
            if (isUserAssigned) {
                loadRecentlyLoggedTraining();
            }
            
            // Set up event listeners
            setupEventListeners();
            
            // Set up CPD button event listeners
            setupCpdButtonListeners();
            
            // Debug: Log the current year being used
            console.log('Current year:', new Date().getFullYear());
        }
        
        /**
         * Set up CPD button event listeners
         */
        function setupCpdButtonListeners() {
            const assignBtn = document.getElementById('assign-to-cpd-btn');
            
            const submitBtn = document.getElementById('submit-cpd-btn');
            
            if (assignBtn) {
                assignBtn.addEventListener('click', assignToCpd);
            }
            
            
            
            if (submitBtn) {
                submitBtn.addEventListener('click', submitCpd);
            }
        }
        
        /**
         * Assign user to CPD
         */
        function assignToCpd() {
            const assignBtn = document.getElementById('assign-to-cpd-btn');
            const activeBtn = assignBtn;
            
            if (!activeBtn) return;
            
            // Show loading state
            const originalText = activeBtn.textContent;
            activeBtn.textContent = 'Assigning...';
            activeBtn.disabled = true;
            
            const formData = new FormData();
            formData.append('action', 'iipm_assign_to_cpd');
            
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        activeBtn.textContent = 'Assigned!';
                        activeBtn.style.background = '#10b981';
                        activeBtn.style.color = 'white';
                        
                        // Reload the page to show all sections for assigned user
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        activeBtn.textContent = 'Error!';
                        activeBtn.style.background = '#ef4444';
                        activeBtn.style.color = 'white';
                        
                        setTimeout(() => {
                            activeBtn.textContent = originalText;
                            activeBtn.style.background = '';
                            activeBtn.style.color = '';
                            activeBtn.disabled = false;
                        }, 3000);
                        
                        alert('Error: ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    activeBtn.textContent = 'Error!';
                    activeBtn.style.background = '#ef4444';
                    activeBtn.style.color = 'white';
                    
                    setTimeout(() => {
                        activeBtn.textContent = originalText;
                        activeBtn.style.background = '';
                        activeBtn.style.color = '';
                        activeBtn.disabled = false;
                    }, 3000);
                    
                    alert('Error assigning to CPD: ' + error);
                }
            });
        }
        
        /**
         * Submit CPD
         */
        function submitCpd() {
            const submitBtn = document.getElementById('submit-cpd-btn');
            if (!submitBtn) return;
            
            // Show loading state
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Submitting...';
            submitBtn.disabled = true;
            
            const formData = new FormData();
            formData.append('action', 'iipm_submission_save');
            formData.append('year', new Date().getFullYear());
            formData.append('details', JSON.stringify(statData));
            
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        submitBtn.textContent = 'Submitted!';
                        submitBtn.style.background = '#10b981';
                        submitBtn.style.color = 'white';
                        
                        // Show congratulations alert
                        showCongratulationsAlert();
                        
                        // Load submission details
                        loadSubmissionDetails();
                        
                        // Reload page after a delay to show the new status section
                        setTimeout(() => {
                            location.reload();
                        }, 3000);
                    } else {
                        submitBtn.textContent = 'Error!';
                        submitBtn.style.background = '#ef4444';
                        submitBtn.style.color = 'white';
                        
                        setTimeout(() => {
                            submitBtn.textContent = originalText;
                            submitBtn.style.background = '';
                            submitBtn.style.color = '';
                            submitBtn.disabled = false;
                        }, 3000);
                        
                        alert('Error: ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    submitBtn.textContent = 'Error!';
                    submitBtn.style.background = '#ef4444';
                    submitBtn.style.color = 'white';
                    
                    setTimeout(() => {
                        submitBtn.textContent = originalText;
                        submitBtn.style.background = '';
                        submitBtn.style.color = '';
                        submitBtn.disabled = false;
                    }, 3000);
                    
                    alert('Error submitting CPD: ' + error);
                }
            });
        }
        
        /**
         * Set up event listeners
         */
        function setupEventListeners() {
            // Log training buttons
            if (logTrainingBtn) logTrainingBtn.addEventListener('click', showLogTrainingModal);
            if (logFirstTrainingBtn) logFirstTrainingBtn.addEventListener('click', showLogTrainingModal);
            
            // Modal close buttons
            if (modalClose) modalClose.addEventListener('click', hideLogTrainingModal);
            if (courseModalClose) courseModalClose.addEventListener('click', hideCourseConfirmationModal);
            
            // Training options
            if (preApprovedOption) preApprovedOption.addEventListener('click', selectPreApprovedTraining);
            if (externalTrainingOption) externalTrainingOption.addEventListener('click', selectExternalTraining);
            
            // Course confirmation
            if (cancelCourseBtn) cancelCourseBtn.addEventListener('click', hideCourseConfirmationModal);
            if (confirmCourseBtn) confirmCourseBtn.addEventListener('click', confirmCourseSelection);
            
            // Close modal when clicking outside
            if (logTrainingModal) logTrainingModal.addEventListener('click', function(e) {
                if (e.target === logTrainingModal) hideLogTrainingModal();
            });
            
            if (courseConfirmationModal) courseConfirmationModal.addEventListener('click', function(e) {
                if (e.target === courseConfirmationModal) hideCourseConfirmationModal();
            });
        }
        
        /**
         * Load completed CPD stats
         */
        function loadCompletedCpdStats() {
            const currentYear = new Date().getFullYear();
            console.log('Loading CPD stats for year:', currentYear);
            
            const formData = new FormData();
            formData.append('action', 'iipm_get_cpd_stats');
            formData.append('year', currentYear);
            
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    console.log('API Response:', response);
                    if (response.success) {
                        if(response.data.is_user_assigned) {
                            isUserAssigned = true;
                        }
                        statData = response.data;
                        updateCpdProgress(response.data);
                    } else {
                        console.error('API returned error:', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error loading CPD stats:', error);
                    console.error('Status:', status);
                    console.error('Response:', xhr.responseText);
                }
            });
        }
        
        /**
         * Update CPD progress display
         */
        function updateCpdProgress(data) {
            console.log('CPD Data received:', data); // Debug log
            
            // Update category progress
            const categories = ['pensions', 'savings', 'ethics', 'life'];
            let totalCompleted = 0;
            
            categories.forEach(category => {
                const statusElement = document.getElementById(category + '-status');
                const progressElement = document.getElementById(category + '-progress');
                
                if (statusElement && progressElement) {
                    // Try to find category by exact match first, then by partial match
                    let categoryData = data.courses_summary.find(item => 
                        item.category.toLowerCase() === category.toLowerCase()
                    );
                    
                    // If no exact match, try partial match
                    if (!categoryData) {
                        categoryData = data.courses_summary.find(item => 
                            item.category.toLowerCase().includes(category.toLowerCase())
                        );
                    }
                    
                    // If still no match, try to match by common variations
                    if (!categoryData) {
                        if (category === 'savings') {
                            categoryData = data.courses_summary.find(item => 
                                item.category.toLowerCase().includes('savings') || 
                                item.category.toLowerCase().includes('investment')
                            );
                        } else if (category === 'life') {
                            categoryData = data.courses_summary.find(item => 
                                item.category.toLowerCase().includes('life') || 
                                item.category.toLowerCase().includes('assurance')
                            );
                        }
                    }
                    
                    if (categoryData) {
                        const completed = categoryData.count;
                        const required = categoryData.required || 1;
                        const percentage = completed >= required ? 100 : (completed / required) * 100;
                        
                        statusElement.textContent = `${completed}/${required}`;
                        progressElement.style.width = percentage + '%';
                        
                        if (completed >= required) {
                            totalCompleted++;
                        }
                        
                        console.log(`Updated ${category}: ${completed}/${required} (${percentage}%)`); // Debug log
                    } else {
                        statusElement.textContent = '0/1';
                        progressElement.style.width = '0%';
                        console.log(`No data found for ${category}`); // Debug log
                    }
                }
            });
            
            // Update total progress
            const totalStatus = document.getElementById('total-status');
            if (totalStatus) {
                totalStatus.textContent = `${totalCompleted}/4`;
            }
            
            // Update CPD dates if available
            if (data.cpd_dates) {
                updateCpdDates(data.cpd_dates);
            }
            
            // Update target minutes display
            if (data.target_minutes) {
                updateTargetMinutes(data.target_minutes);
            }
            
            // Update time progress
            if (data.total_cpd_minutes !== undefined && data.target_minutes) {
                updateTimeProgress(data.total_cpd_minutes, data.target_minutes);
            }
            
            // Update CPD action buttons based on period and assignment status
            updateCpdActionButtons(data);
            
            console.log(`Total completed: ${totalCompleted}/4`); // Debug log
        }
        
        /**
         * Update CPD dates display
         */
        function updateCpdDates(cpdDates) {
            console.log('Updating CPD dates:', cpdDates);
            
            // Update logging period
            const loggingPeriod = document.getElementById('logging-period');
            if (loggingPeriod && cpdDates.start_logging && cpdDates.end_logging) {
                const startDate = formatDate(cpdDates.start_logging);
                const endDate = formatDate(cpdDates.end_logging);
                loggingPeriod.textContent = `${startDate} - ${endDate}`;
            }
            
            // Update submission period
            const submissionPeriod = document.getElementById('submission-period');
            if (submissionPeriod && cpdDates.start_submission && cpdDates.end_submission) {
                const startDate = formatDate(cpdDates.start_submission);
                const endDate = formatDate(cpdDates.end_submission);
                submissionPeriod.textContent = `${startDate} - ${endDate}`;
            }
            
            // Update deadline warning
            const deadlineWarning = document.getElementById('deadline-warning');
            if (deadlineWarning && cpdDates.end_logging) {
                const endDate = formatDate(cpdDates.end_submission);
                deadlineWarning.textContent = `Submit your CPD before ${endDate}`;
            }
        }
        
        /**
         * Update target minutes display
         */
        function updateTargetMinutes(targetMinutes) {
            console.log('Target minutes:', targetMinutes);
            
            const targetElement = document.getElementById('target-minutes');
            if (targetElement) {
                targetElement.textContent = formatMinutesToHours(targetMinutes);
            }
        }
        
        /**
         * Update CPD action buttons based on period and assignment status
         */
        function updateCpdActionButtons(data) {
            const cpdActionButtons = document.getElementById('cpd-action-buttons');
            const submitBtn = document.getElementById('submit-cpd-btn');
            

            const isLoggingPeriod = data.is_logging_period_available;
            const isSubmissionPeriod = data.is_submission_period_available;
            const isUserAssigned = data.is_user_assigned;

            // If user is not assigned, update logging period info
            if (!isUserAssigned) {
                // Update logging period info
                updateLoggingPeriodInfo(data.cpd_dates);
                
                // Show alert if logging period expired
                if (!isLoggingPeriod) {
                    showCpdAlert('Logging period has already expired. You are not assigned to this CPD cycle.');
                }
                return;
            }

            console.log('CPDACTIONBUTTONS', cpdActionButtons);
            console.log('SUBMITBTN', submitBtn);
            
            
            if (!cpdActionButtons || !submitBtn) return;

            console.log('isUserAssigned', isUserAssigned);
            console.log('isSubmissionPeriod', isSubmissionPeriod);
            
            // Check if user meets all submission requirements
            const meetsSubmissionRequirements = checkSubmissionRequirements(data);
            
            // User is assigned, show submit button if in submission period AND meets all requirements
            if (isUserAssigned && isSubmissionPeriod && !isTrainingCompleted && meetsSubmissionRequirements) {
                cpdActionButtons.style.display = 'flex';
                submitBtn.style.display = 'block';
                hideValidationMessage();
            } else {
                // Hide submit button if requirements not met
                if (cpdActionButtons) {
                    cpdActionButtons.style.display = 'none';
                }
                
                // Show validation message if user is assigned and in submission period but doesn't meet requirements
                if (isUserAssigned && isSubmissionPeriod && !isTrainingCompleted) {
                    showValidationMessage(data);
                }
            }
            
            // Check if user has completed all CPD requirements and show success alert
            checkAndShowSuccessAlert(data);
        }
        
        /**
         * Check if user meets all submission requirements
         * Both time progress >= 100% AND completion in all required categories
         */
        function checkSubmissionRequirements(data) {
            // Check 1: Time progress must be >= 100%
            const timeProgress = parseFloat(data.completion_percentage || 0);
            const hasMinimumTimeProgress = timeProgress >= 100;
            
            // Check 2: Must have completed training in all required categories
            const hasCompletedAllCategories = checkAllCategoriesCompleted(data);
            
            console.log('Submission Requirements Check:');
            console.log('- Time Progress:', timeProgress + '%', hasMinimumTimeProgress ? '✓' : '✗');
            console.log('- All Categories Completed:', hasCompletedAllCategories ? '✓' : '✗');
            console.log('- Overall Requirements Met:', hasMinimumTimeProgress && hasCompletedAllCategories ? '✓' : '✗');
            
            return hasMinimumTimeProgress && hasCompletedAllCategories;
        }
        
        /**
         * Check if user has completed training in all required categories
         * Each category must have at least 1 hour of training
         */
        function checkAllCategoriesCompleted(data) {
            if (!data.courses_summary || data.courses_summary.length === 0) {
                console.log('No courses summary data available');
                return false;
            }
            
            // Check each category to ensure it has at least 1 hour of completed training
            for (let category of data.courses_summary) {
                if (category.required > 0 && category.total_hours < 1) {
                    console.log(`Category "${category.category}" has insufficient training hours (${category.total_hours} hours, required: at least 1 hour)`);
                    return false;
                }
            }
            
            console.log('All required categories have at least 1 hour of training');
            return true;
        }
        
        /**
         * Show validation message explaining why submit button is not available
         */
        function showValidationMessage(data) {
            const validationMessage = document.getElementById('cpd-validation-message');
            if (!validationMessage) return;
            
            const timeProgress = parseFloat(data.completion_percentage || 0);
            const hasMinimumTimeProgress = timeProgress >= 100;
            const hasCompletedAllCategories = checkAllCategoriesCompleted(data);
            
            let messages = [];
            
            if (!hasMinimumTimeProgress) {
                messages.push(`Complete at least 100% of required training time (currently ${timeProgress.toFixed(1)}%)`);
            }
            
            if (!hasCompletedAllCategories) {
                // Find categories with insufficient hours (less than 1 hour)
                const insufficientCategories = [];
                if (data.courses_summary) {
                    console.log(data.courses_summary);
                    data.courses_summary.forEach(category => {
                        if (category.required > 0 && category.total_hours < 1) {
                            insufficientCategories.push(`${category.category} (${category.total_hours} hours)`);
                        }
                    });
                }

                console.log(insufficientCategories);
                
                if (insufficientCategories.length > 0) {
                    messages.push(`Complete at least 1 hour of training in all required categories. Insufficient: <b>${insufficientCategories.join(', ')}</b>`);
                } else {
                    messages.push(`Complete at least 1 hour of training in all required categories`);
                }
            }
            
            if (messages.length > 0) {
                validationMessage.innerHTML = `
                    <div class="validation-alert">
                        <div class="validation-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="validation-content">
                            <h4>Requirements Not Met</h4>
                            <p>To submit your CPD return, you must:</p>
                            <ul>
                                ${messages.map(msg => `<li>${msg}</li>`).join('')}
                            </ul>
                        </div>
                    </div>
                `;
                validationMessage.style.display = 'block';
            }
        }
        
        /**
         * Hide validation message
         */
        function hideValidationMessage() {
            const validationMessage = document.getElementById('cpd-validation-message');
            if (validationMessage) {
                validationMessage.style.display = 'none';
            }
        }
        
        /**
         * Check if user has completed all CPD requirements and show success alert
         */
        function checkAndShowSuccessAlert(data) {
            if (!data.courses_summary || data.courses_summary.length === 0) return;
            
            let totalCompleted = 0;
            let totalRequired = 0;
            
            data.courses_summary.forEach(category => {
                totalCompleted += category.count;
                totalRequired += category.required;
            });
            
            const isFullyCompleted = (totalCompleted >= totalRequired && data.completion_percentage >= 100);
            
            // Show success alert if fully completed and not already shown
            if (isFullyCompleted && !document.getElementById('cpd-success-alert')) {
                showSuccessAlert();
            }
        }
        
        /**
         * Show success alert dynamically
         */
        function showSuccessAlert() {
            const currentYear = new Date().getFullYear();
            const alertHTML = `
                <div class="cpd-success-alert" id="cpd-success-alert">
                    <div class="alert-content">
                        <div class="alert-icon">🎉</div>
                        <div class="alert-message">
                            <h4>Congratulations!</h4>
                            <p>You have successfully completed all required CPD courses for ${currentYear}. Your professional development is up to date!</p>
                        </div>
                        <button class="alert-close" onclick="closeSuccessAlert()">×</button>
                    </div>
                </div>
            `;
            
            const container = document.querySelector('.container');
            const portalLayout = document.querySelector('.portal-layout');
            const alertDiv = document.createElement('div');
            alertDiv.innerHTML = alertHTML;
            container.insertBefore(alertDiv.firstElementChild, portalLayout);
        }
        
        /**
         * Update logging period information for unassigned users
         */
        function updateLoggingPeriodInfo(cpdDates) {
            console.log('Updating logging period information:', cpdDates);
            const loggingPeriodText = document.getElementById('logging-period-text');
            if (!loggingPeriodText || !cpdDates) return;
            
            if (cpdDates.start_logging && cpdDates.end_logging) {
                const startDate = formatDate(cpdDates.start_logging);
                const endDate = formatDate(cpdDates.end_logging);

                loggingPeriodText.textContent = `${startDate} - ${endDate}`;
            } else {
                loggingPeriodText.textContent = 'Dates not available';
            }
        }
        
        /**
         * Show CPD alert message
         */
        function showCpdAlert(message) {
            // Create alert element
            const alert = document.createElement('div');
            alert.className = 'cpd-alert';
            alert.innerHTML = `
                <div class="alert-content">
                    <span class="alert-icon"><i class="fas fa-exclamation-triangle"></i></span>
                    <span class="alert-message">${message}</span>
                    <button class="alert-close">×</button>
                </div>
            `;
            
            // Insert after the submit button
            const submitBtn = document.getElementById('submit-return-btn');
            if (submitBtn && submitBtn.parentNode) {
                submitBtn.parentNode.insertBefore(alert, submitBtn.nextSibling);
            }
            
            // Auto-hide after 10 seconds
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }, 10000);
            
            // Close button functionality
            const closeBtn = alert.querySelector('.alert-close');
            closeBtn.addEventListener('click', () => {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            });
        }
        
        /**
         * Update time progress display
         */
        function updateTimeProgress(completedMinutes, targetMinutes) {
            console.log('Updating time progress:', completedMinutes, targetMinutes);
            
            // Calculate percentage
            const percentage = targetMinutes > 0 ? Math.min(100, Math.round((completedMinutes / targetMinutes) * 100)) : 0;
            
            // Update progress bar
            const progressFill = document.getElementById('time-progress-fill');
            if (progressFill) {
                progressFill.style.width = percentage + '%';
            }
            
            // Update percentage text
            const percentageElement = document.getElementById('time-percentage');
            if (percentageElement) {
                percentageElement.textContent = percentage + '%';
            }
            
            // Update completed minutes
            const completedElement = document.getElementById('completed-minutes');
            if (completedElement) {
                completedElement.textContent = formatMinutesToHours(completedMinutes);
            }
            
            // Update remaining minutes
            const remainingElement = document.getElementById('remaining-minutes');
            if (remainingElement) {
                const remainingMinutes = Math.max(0, targetMinutes - completedMinutes);
                remainingElement.textContent = formatMinutesToHours(remainingMinutes);
            }
        }
        
        /**
         * Format date for display
         */
        function formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
        }
        
        /**
         * Format hours to display format with decimal equivalent
         * Example: 6.5 hours -> "6hr 30min (6.5)"
         */
        function formatDuration(hours) {
            if (!hours || hours === 0) return '0hr (0.0)';
            
            const totalHours = parseFloat(hours);
            const wholeHours = Math.floor(totalHours);
            const remainingMinutes = Math.round((totalHours - wholeHours) * 60);
            const decimalHours = totalHours.toFixed(1);
            
            if (wholeHours > 0) {
                if (remainingMinutes > 0) {
                    return `${wholeHours}hr ${remainingMinutes}min (${decimalHours})`;
                } else {
                    return `${wholeHours}hr (${decimalHours})`;
                }
            } else {
                return `${remainingMinutes}min (${decimalHours})`;
            }
        }
        
        /**
         * Format minutes to display format with decimal equivalent
         * Example: 390 minutes -> "6hr 30min (6.5)"
         */
        function formatMinutesToHours(minutes) {
            if (!minutes || minutes === 0) return '0hr (0.0)';
            
            const totalMinutes = parseInt(minutes);
            const hours = Math.floor(totalMinutes / 60);
            const mins = totalMinutes % 60;
            const decimalHours = (totalMinutes / 60).toFixed(1);
            
            if (hours > 0) {
                if (mins > 0) {
                    return `${hours}hr ${mins}min (${decimalHours})`;
                } else {
                    return `${hours}hr (${decimalHours})`;
                }
            } else {
                return `${mins}min (${decimalHours})`;
            }
        }
        
        /**
         * Load recently logged training
         */
        function loadRecentlyLoggedTraining() {
            const formData = new FormData();
            formData.append('action', 'iipm_get_recently_logged_training');
            formData.append('year', new Date().getFullYear());
            
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        updateTrainingDisplay(response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading training:', error);
                }
            });
        }
        
        /**
         * Update training display
         */
        function updateTrainingDisplay(training) {
            // Check if user has submitted - if so, render as table under certificate section
            <?php if ($is_submitted): ?>
                renderTrainingAsTable(training);
                return;
            <?php endif; ?>
            
            // For non-submitted users, render in portal
            renderTrainingInPortal(training);
        }
        
        /**
         * Render training data as table under certificate section (for submitted users)
         */
        function renderTrainingAsTable(training) {
            const trainingTableSection = document.getElementById('training-table-section');
            if (!trainingTableSection) {
                console.log('Training table section element not found');
                return;
            }
            
            if (!training || training.length === 0) {
                trainingTableSection.innerHTML = '<p style="text-align: center; color: #6b7280; padding: 20px;">No training data available.</p>';
                return;
            }
            
            // Calculate total duration (in hours)
            let totalDuration = 0;
            training.forEach(item => {
                // Extract duration from hrsAndCategory string (assuming it's in hours)
                const durationMatch = item.hrsAndCategory.match(/(\d+(?:\.\d+)?)/);
                if (durationMatch) {
                    totalDuration += parseFloat(durationMatch[1]);
                }
            });
            
            // Create training table HTML
            const trainingTableHtml = `
                <div class="recent-training-section">
                    <div class="training-header training-header-submitted">
                        <div class="training-icon">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <h3 class="training-title-submitted">Recently Logged Training</h3>
                    </div>
                    <div class="training-summary">
                        <div class="summary-item">
                            <span class="summary-label">Total Courses:</span>
                            <span class="summary-value">${training.length}</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Total Duration:</span>
                            <span class="summary-value">${formatDuration(totalDuration)}</span>
                        </div>
                    </div>
                    <div class="training-table-container">
                        <table class="training-table">
                            <thead>
                                <tr>
                                    <th>Course Name</th>
                                    <th>Category</th>
                                    <th>Completed Date</th>
                                    <th>Duration</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${training.map(item => {
                                    const isCompleted = item.dateOfReturn !== null;
                                    const category = item.hrsAndCategory.split(': ')[1] || 'N/A';
                                    const durationRaw = item.hrsAndCategory.split(': ')[0] || 'N/A';
                                    // Extract hours from duration string and format it
                                    const durationMatch = durationRaw.match(/(\d+(?:\.\d+)?)/);
                                    const durationFormatted = durationMatch ? formatDuration(parseFloat(durationMatch[1])) : durationRaw;
                                    
                                    return `
                                        <tr>
                                            <td class="course-name-cell">${item.courseName}</td>
                                            <td class="category-cell">${category}</td>
                                            <td class="date-cell">${formatDate(item.dateOfReturn || item.dateOfCourse)}</td>
                                            <td class="duration-cell">${durationFormatted}</td>
                                            <td class="status-cell">
                                                <span class="status-badge ${isCompleted ? 'completed' : 'pending'}">
                                                    ${isCompleted ? 'Completed' : 'Pending'}
                                                </span>
                                            </td>
                                        </tr>
                                    `;
                                }).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            
            // Render the training table in the dedicated container
            trainingTableSection.innerHTML = trainingTableHtml;
        }
        
        /**
         * Render training data in portal (for non-submitted users)
         */
        function renderTrainingInPortal(training) {
            // Check if trainingContent element exists (it might not exist if user has submitted)
            if (!trainingContent) {
                console.log('Training content element not found - user may have submitted CPD');
                return;
            }
            
            if (!training || training.length === 0) {
                trainingContent.innerHTML = `
                    <div class="no-training-message">
                        <div class="no-training-icon">💻</div>
                        <h4>No training history yet</h4>
                        <p>Start your CPD journey by logging your first training session</p>
                        <button class="btn btn-primary" id="log-first-training-btn">Log your first training</button>
                    </div>
                `;
                
                // Re-attach event listener
                const newLogFirstBtn = document.getElementById('log-first-training-btn');
                if (newLogFirstBtn) {
                    newLogFirstBtn.addEventListener('click', showLogTrainingModal);
                }
                return;
            }
            
            let html = '<div class="training-grid">';
            training.forEach(item => {
                // All logged courses are automatically completed
                const isCompleted = true; // Always true since logging auto-completes courses
                
                html += `
                    <div class="training-item">
                        <div class="training-header">
                            <h4 class="training-title">${item.courseName.charAt(0).toUpperCase() + item.courseName.slice(1)}</h4>
                            <div class="training-actions">
                                <button class="action-btn delete-btn" onclick="deleteCourse(${item.id})" title="Remove"><i class="fas fa-trash-alt"></i></button>
                            </div>
                        </div>
                        
                        <div class="training-meta">
                            <div class="meta-item">
                                <span class="meta-label">Category:</span>
                                <span class="meta-value">${item.hrsAndCategory.split(': ')[1] || 'N/A'}</span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Duration:</span>
                                <span class="meta-value">${(() => {
                                    const durationRaw = item.hrsAndCategory.split(': ')[0] || 'N/A';
                                    const durationMatch = durationRaw.match(/(\d+(?:\.\d+)?)/);
                                    return durationMatch ? formatDuration(parseFloat(durationMatch[1])) : durationRaw;
                                })()}</span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Provider:</span>
                                <span class="meta-value">${item.crs_provider || 'N/A'}</span>
                            </div>
                        </div>
                        
                        <div class="training-date">
                            ${new Date(item.dateOfReturn || item.dateOfCourse).toLocaleDateString()}
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            
            // Double check that trainingContent exists before setting innerHTML
            if (trainingContent) {
                trainingContent.innerHTML = html;
            }
        }
        
        /**
         * Show congratulations alert
         */
        function showCongratulationsAlert() {
            // Create a beautiful congratulations modal
            const alertHtml = `
                <div class="congratulations-overlay" style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.8);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 10000;
                    animation: fadeIn 0.3s ease;
                ">
                    <div class="congratulations-card" style="
                        background: white;
                        border-radius: 20px;
                        padding: 40px;
                        text-align: center;
                        max-width: 500px;
                        width: 90%;
                        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
                        animation: slideUp 0.5s ease;
                    ">
                        <div class="celebration-icon" style="
                            width: 100px;
                            height: 100px;
                            background: linear-gradient(135deg, #10b981, #059669);
                            border-radius: 50%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            margin: 0 auto 20px;
                            color: white;
                            font-size: 3rem;
                            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
                        ">
                            🎉
                        </div>
                        <h2 style="
                            color: #1f2937;
                            font-size: 2rem;
                            font-weight: 700;
                            margin: 0 0 15px 0;
                        ">Congratulations!</h2>
                        <p style="
                            color: #6b7280;
                            font-size: 1.1rem;
                            margin: 0 0 25px 0;
                            line-height: 1.6;
                        ">You have successfully submitted your CPD training for ${new Date().getFullYear()}!</p>
                        <button onclick="closeCongratulationsAlert()" style="
                            background: linear-gradient(135deg, #8b5a96, #6b4c93);
                            color: white;
                            border: none;
                            padding: 12px 30px;
                            border-radius: 8px;
                            font-size: 1rem;
                            font-weight: 600;
                            cursor: pointer;
                            transition: all 0.3s ease;
                        ">Continue</button>
                    </div>
                </div>
                <style>
                    @keyframes fadeIn {
                        from { opacity: 0; }
                        to { opacity: 1; }
                    }
                    @keyframes slideUp {
                        from { transform: translateY(50px); opacity: 0; }
                        to { transform: translateY(0); opacity: 1; }
                    }
                </style>
            `;
            
            document.body.insertAdjacentHTML('beforeend', alertHtml);
        }
        
        /**
         * Close congratulations alert
         */
        function closeCongratulationsAlert() {
            const overlay = document.querySelector('.congratulations-overlay');
            if (overlay) {
                overlay.remove();
            }
        }
        
        /**
         * Load submission details
         */
        function loadSubmissionDetails() {
            const submissionDetails = document.getElementById('submission-details');
            if (!submissionDetails) return;
            
            // Show loading state
            submissionDetails.innerHTML = '<div class="loading-spinner"></div>';
            
            // Fetch submission status
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'iipm_get_user_submission_status',
                    year: new Date().getFullYear()
                },
                success: function(response) {
                    if (response.success) {
                        renderSubmissionStatus(response.data);
                    } else {
                        submissionDetails.innerHTML = '<p>Error loading submission details: ' + response.data + '</p>';
                    }
                },
                error: function() {
                    submissionDetails.innerHTML = '<p>Failed to load submission details</p>';
                }
            });
        }
        
        /**
         * Render submission status
         */
        function renderSubmissionStatus(data) {
            const submissionDetails = document.getElementById('submission-details');
            if (!submissionDetails) return;
            
            const submission = data.submission;
            const user = data.user;
            const certificate = data.certificate;
            
            // Format dates
            const submittedDate = new Date(submission.created_at).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            const reviewedDate = submission.reviewed_at ? 
                new Date(submission.reviewed_at).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                }) : 'Not reviewed yet';
            
            // Status badge class
            const statusClass = submission.status;
            const statusText = submission.status.charAt(0).toUpperCase() + submission.status.slice(1);
            
            let html = `
                <div class="status-info">
                    <div class="status-item">
                        <h4>Submission Status</h4>
                        <span class="status-badge ${statusClass}">${statusText}</span>
                    </div>
                    <div class="status-item">
                        <h4>Submitted Date</h4>
                        <p class="value">${submittedDate}</p>
                    </div>
                    <div class="status-item">
                        <h4>Reviewed Date</h4>
                        <p class="value">${reviewedDate}</p>
                    </div>
                    <div class="status-item">
                        <h4>Submission Year</h4>
                        <p class="value">${submission.year}</p>
                    </div>
                </div>
            `;
            
            // Add completed courses section
            if (data.completed_courses && data.completed_courses.length > 0) {
                html += `
                    <div class="completed-courses-section">
                        <div class="courses-header">
                            <div class="courses-icon">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <h3 class="courses-title">Completed Courses</h3>
                        </div>
                        <div class="courses-list">
                `;
                
                data.completed_courses.forEach(course => {
                    html += `
                        <div class="course-item">
                            <div class="course-info">
                                <h4 class="course-name">${course.course_name}</h4>
                                <div class="course-details">
                                    <span class="course-category">${course.course_category}</span>
                                    <span class="course-duration">${formatDuration(course.course_cpd_mins)}</span>
                                    <span class="course-provider">${course.crs_provider}</span>
                                </div>
                            </div>
                            <div class="course-status">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    `;
                });
                
                html += `
                        </div>
                    </div>
                `;
            }
            
            // Add certificate section if certificate exists
            if (certificate) {
                html += `
                    <div class="certificate-section has-certificate">
                        <div class="certificate-header">
                            <div class="certificate-icon">
                                <i class="fas fa-certificate"></i>
                            </div>
                            <h3 class="certificate-title">Certificate Available</h3>
                        </div>
                        <p class="certificate-info">
                            <strong>${certificate.name}</strong> (${certificate.year})
                            ${certificate.description ? '<br>' + certificate.description : ''}
                        </p>
                        <button class="download-btn" onclick="downloadCertificate(${certificate.id}, '${user.name}', '${user.email}', '${user.contact_address}', '${submission.year}')">
                            <i class="fas fa-download"></i>
                            Download Certificate
                        </button>
                    </div>
                `;
            } else {
                html += `
                    <div class="certificate-section">
                        <div class="certificate-header">
                            <div class="certificate-icon" style="background: #9ca3af;">
                                <i class="fas fa-clock"></i>
                            </div>
                            <h3 class="certificate-title">Certificate Pending</h3>
                        </div>
                        <p class="certificate-info">
                            Your certificate will be available once your submission is approved by an administrator.
                        </p>
                    </div>
                `;
            }
            
            // Add recent training section after certificate
            if (data.recent_training && data.recent_training.length > 0) {
                let totalDuration = 0;
                data.recent_training.forEach(training => {
                    totalDuration += parseFloat(training.course_cpd_mins) || 0;
                });
                
                html += `
                    <div class="recent-training-section">
                        <div class="training-header">
                            <div class="training-icon">
                                <i class="fas fa-book-open"></i>
                            </div>
                            <h3 class="training-title">Recently Logged Training</h3>
                        </div>
                        <div class="training-summary">
                            <div class="summary-item">
                                <span class="summary-label">Total Courses:</span>
                                <span class="summary-value">${data.recent_training.length}</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Total Duration:</span>
                                <span class="summary-value">${formatDuration(totalDuration)}</span>
                            </div>
                        </div>
                        <div class="training-table-container">
                            <table class="training-table">
                                <thead>
                                    <tr>
                                        <th>Course Name</th>
                                        <th>Category</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Duration</th>
                                    </tr>
                                </thead>
                                <tbody>
                `;
                
                data.recent_training.forEach(training => {
                    const startDate = training.start_date ? new Date(training.start_date).toLocaleDateString() : 'N/A';
                    const endDate = training.end_date ? new Date(training.end_date).toLocaleDateString() : 'N/A';
                    
                    html += `
                        <tr>
                            <td class="course-name-cell">${training.course_name}</td>
                            <td class="category-cell">${training.course_category || 'N/A'}</td>
                            <td class="date-cell">${startDate}</td>
                            <td class="date-cell">${endDate}</td>
                            <td class="duration-cell">${formatDuration(training.course_cpd_mins)}</td>
                        </tr>
                    `;
                });
                
                html += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
            }
            
            // Add admin notes if available
            if (submission.admin_notes) {
                html += `
                    <div class="admin-notes" style="
                        background: #f3f4f6;
                        border-radius: 8px;
                        padding: 15px;
                        margin-top: 20px;
                        border-left: 4px solid #8b5a96;
                    ">
                        <h4 style="margin: 0 0 8px 0; color: #374151; font-size: 0.9rem;">Admin Notes:</h4>
                        <p style="margin: 0; color: #6b7280; font-size: 0.9rem;">${submission.admin_notes}</p>
                    </div>
                `;
            }
            
            submissionDetails.innerHTML = html;
        }
        
        /**
         * Download certificate PDF - Direct URL method (like CSV export)
         */
        window.downloadCertificate = function(certificateId, userName, userEmail, contactAddress, submissionYear) {
            // Show loading state
            const downloadBtn = event.target;
            const originalText = downloadBtn.innerHTML;
            downloadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating PDF...';
            downloadBtn.disabled = true;
            
            // Create direct download URL (like CSV export)
            const params = new URLSearchParams({
                action: 'iipm_download_certificate_direct',
                certificate_id: certificateId,
                user_name: userName,
                user_email: userEmail,
                contact_address: contactAddress,
                submission_year: submissionYear
            });
            
            const downloadUrl = `${ajaxurl}?${params.toString()}`;
            
            // Create a temporary link and trigger download
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // Reset button state
            setTimeout(() => {
                downloadBtn.innerHTML = originalText;
                downloadBtn.disabled = false;
            }, 2000);
        }

        /**
         * Show log training modal
         */
        function showLogTrainingModal() {
            if (logTrainingModal) {
                logTrainingModal.style.display = 'flex';
            }
        }
        
        /**
         * Hide log training modal
         */
        function hideLogTrainingModal() {
            if (logTrainingModal) {
                logTrainingModal.style.display = 'none';
            }
        }
        
        /**
         * Select pre-approved training
         */
        function selectPreApprovedTraining() {
            hideLogTrainingModal();
            // Redirect to CPD courses page with logging period status
            const loggingAvailable = <?php echo $is_logging_period_active ? '1' : '0'; ?>;
            window.location.href = '<?php echo home_url('/cpd-courses/'); ?>?logging_available=' + loggingAvailable;
        }
        
        /**
         * Select external training
         */
        function selectExternalTraining() {
            hideLogTrainingModal();
            // Redirect to external courses page
            window.location.href = '<?php echo home_url('/external-courses/'); ?>';
        }
        
        /**
         * Show course confirmation modal
         */
        function showCourseConfirmationModal(courseData) {
            const courseDetails = document.getElementById('course-details');
            if (courseDetails) {
                courseDetails.innerHTML = `
                    <div class="course-detail-item">
                        <span class="detail-label">Course Name:</span>
                        <span class="detail-value">${courseData.course_name}</span>
                    </div>
                    <div class="course-detail-item">
                        <span class="detail-label">Category:</span>
                        <span class="detail-value">${courseData.course_category}</span>
                    </div>
                    <div class="course-detail-item">
                        <span class="detail-label">Duration:</span>
                        <span class="detail-value">${formatDuration(courseData.course_cpd_mins)}</span>
                    </div>
                    <div class="course-detail-item">
                        <span class="detail-label">Provider:</span>
                        <span class="detail-value">${courseData.crs_provider}</span>
                    </div>
                `;
            }
            
            if (courseConfirmationModal) {
                courseConfirmationModal.style.display = 'flex';
            }
        }
        
        /**
         * Hide course confirmation modal
         */
        function hideCourseConfirmationModal() {
            if (courseConfirmationModal) {
                courseConfirmationModal.style.display = 'none';
            }
        }
        
        /**
         * Confirm course selection
         */
        function confirmCourseSelection() {
            // This will be called from the CPD courses page
            // The actual course data will be passed via URL parameters or localStorage
            hideCourseConfirmationModal();
            
            // For now, show a success message
            alert('Course added successfully!');
            
            // Always reload training data
            loadRecentlyLoggedTraining();
            loadCompletedCpdStats();
        }
    });
    
    // Global function to close success alert
    function closeSuccessAlert() {
        const alert = document.getElementById('cpd-success-alert');
        if (alert) {
            alert.style.animation = 'slideUp 0.3s ease-out';
            setTimeout(() => {
                alert.remove();
            }, 300);
        }
    }
    
    // Global functions for course actions
    // completeCourse function removed - courses are now automatically completed when logged
    // function completeCourse(courseId) { ... } - No longer needed
    
    function deleteCourse(courseId) {
        if (confirm('Are you sure you want to delete this course?')) {
            // Find the delete button and show loading state
            const deleteBtn = event.target;
            const originalText = deleteBtn.textContent;
            deleteBtn.textContent = 'Deleting...';
            deleteBtn.disabled = true;
            deleteBtn.style.background = '#6b7280';
            
            const formData = new FormData();
            formData.append('action', 'iipm_delete_cpd_confirmation');
            formData.append('confirmation_id', courseId);
            
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        // Show success state
                        deleteBtn.textContent = 'Deleted!';
                        deleteBtn.style.background = '#10b981';
                        deleteBtn.style.color = 'white';
                        
                        // Reload data after a short delay to show the success state
                        setTimeout(() => {
                        location.reload();
                        }, 1500);
                    } else {
                        // Show error state
                        deleteBtn.textContent = 'Error!';
                        deleteBtn.style.background = '#ef4444';
                        deleteBtn.style.color = 'white';
                        
                        // Revert after 3 seconds
                        setTimeout(() => {
                            deleteBtn.textContent = originalText;
                            deleteBtn.style.background = '';
                            deleteBtn.style.color = '';
                            deleteBtn.disabled = false;
                        }, 3000);
                        
                        alert('Error: ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    // Show error state
                    deleteBtn.textContent = 'Error!';
                    deleteBtn.style.background = '#ef4444';
                    deleteBtn.style.color = 'white';
                    
                    // Revert after 3 seconds
                    setTimeout(() => {
                        deleteBtn.textContent = originalText;
                        deleteBtn.style.background = '';
                        deleteBtn.style.color = '';
                        deleteBtn.disabled = false;
                    }, 3000);
                    
                    alert('Error deleting course: ' + error);
                }
            });
        }
    }
</script>

<?php get_footer(); ?>
