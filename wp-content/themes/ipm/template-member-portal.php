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
// Member Portal: Use current date year directly (not CPD year logic)
// If today is 2026, use 2026
// Note: Payment Management page uses different logic (CPD year N = membership expiration Feb 1, N+1)
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

// Get membership status using global function (auto-updates based on payment and dates)
$member_status = iipm_get_membership_status($current_user_id, true);

// Auto-assign to CPD if user has active or expired membership (but not inactive)
// Note: Expired members can still do CPD training until Feb 5th when they become inactive
if ($member_status === 'active' || $member_status === 'expired') {
    $cpd_stats = iipm_get_cpd_stats($current_user_id, $current_year);
    
    // If not assigned, automatically assign to CPD
    if ($member_status === 'active') {
        $assignment_result = iipm_assign_user_to_cpd($current_user_id);
    }
} else {
    // For inactive members, still check assignment status but don't auto-assign
    $cpd_stats = iipm_get_cpd_stats($current_user_id, $current_year);
}

// Allow logging actions for active and expired members (but not inactive)
// Inactive members cannot log CPD training
$is_logging_period_active = ($member_status !== 'inactive');

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
                <?php IIPM_Navigation_Manager::display_breadcrumbs(); ?>
            </div>
        </div>
        
        <!-- Inactive Membership Alert -->
        <?php if ($member_status === 'inactive'): ?>
        <div class="membership-inactive-alert" style="background: #ffffff; border-left: 4px solid #ef4444; border-radius: 8px; padding: 24px; margin-bottom: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: flex-start; gap: 16px;">
                <div style="flex-shrink: 0; width: 40px; height: 40px; background: #fee2e2; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-exclamation-circle" style="color: #ef4444; font-size: 20px;"></i>
                </div>
                <div style="flex: 1;">
                    <h3 style="color: #1f2937; margin: 0 0 8px 0; font-size: 1.25rem; font-weight: 600;">Membership Inactive</h3>
                    <p style="color: #6b7280; margin: 0 0 16px 0; font-size: 0.95rem; line-height: 1.5;">
                        Your membership is currently inactive. Please renew your membership to access CPD training and member benefits.
                    </p>
                    <a href="<?php echo home_url('/profile/?tab=payment'); ?>" 
                       style="display: inline-block; background: #ef4444; color: white; padding: 10px 24px; border-radius: 6px; text-decoration: none; font-weight: 500; font-size: 0.9rem; transition: background 0.2s;">
                        Renew Membership
                    </a>
                </div>
            </div>
        </div>
        <?php elseif ($member_status === 'expired'): 
            $inactive_deadline = date('F j', mktime(0, 0, 0, IIPM_MEMBERSHIP_INACTIVE_MONTH, IIPM_MEMBERSHIP_INACTIVE_DAY));
        ?>
        <!-- Expired Membership Notice -->
        <div class="membership-expired-notice" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); border-radius: 16px; padding: 25px; margin-bottom: 30px; box-shadow: 0 10px 30px rgba(245, 158, 11, 0.3);">
            <div class="alert-content" style="display: flex; align-items: center; gap: 20px;">
                <div class="alert-icon" style="font-size: 40px; flex-shrink: 0;">📋</div>
                <div class="alert-message" style="flex: 1;">
                    <h4 style="color: white; margin: 0 0 8px 0; font-size: 1.3rem;">Membership Renewal Required</h4>
                    <p style="color: rgba(255,255,255,0.95); margin: 0 0 12px 0;">
                        Your membership has expired. Please renew by <strong style="color: white;"><?php echo $inactive_deadline; ?></strong> to avoid service interruption.
                    </p>
                    <p style="color: rgba(255,255,255,0.9); margin: 0 0 15px 0; font-size: 0.95rem;">
                        Note: You can still log CPD training during the grace period until <?php echo $inactive_deadline; ?>.
                    </p>
                    <a href="<?php echo home_url('/profile/?tab=payment'); ?>" 
                       style="display: inline-block; background: white; color: #d97706; padding: 10px 25px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: transform 0.2s;">
                        Renew Now
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Success Alert for Completed CPD - Hidden at top, will be shown in sidebar instead -->
        
        <?php if ($member_status === 'inactive'): ?>
            <!-- For inactive members, show only the alert above and hide all other content -->
            <div style="text-align: center; padding: 50px 20px; color: rgba(255,255,255,0.7);">
                <p style="font-size: 1.1rem;">Please renew your membership to access the member portal.</p>
            </div>
        <?php else: ?>
        <!-- Show all content for active and expired members -->
        
        <!-- Statistics Blocks Section -->
        <?php if (false && $is_submitted): // Hide statistics blocks when submitted ?>
        <div class="statistics-blocks-section">
            <?php
            // Get CPD statistics
            $cpd_stats = iipm_get_cpd_stats($current_user->ID, $current_year);
            $total_cpd_hours = $cpd_stats['total_cpd_minutes'] / 60; // Convert minutes to hours
            
            // Get course count from category summary
            $total_courses = 0;
            if (isset($cpd_stats['courses_summary']) && is_array($cpd_stats['courses_summary'])) {
                foreach ($cpd_stats['courses_summary'] as $category) {
                    $total_courses += $category['count'];
                }
            }
            
            // Get leave hours deducted using the predefined function
            $leave_duration_days = iipm_calculate_user_leave_duration($current_user->ID, $current_year);
            $membership_constant = iipm_get_membership_constant($current_user->ID);
            $leave_hours_deducted = iipm_round_to_nearest_half($membership_constant * ($leave_duration_days / 30));
            ?>
            
            <div class="stats-grid">
                <!-- CPD Records Block -->
                <div class="stat-block cpd-records">
                    <div class="stat-icon">
                        <i class="fas fa-certificate" style="margin-right: 0px; color: white;"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo number_format($total_cpd_hours, 1); ?></div>
                        <div class="stat-label">CPD Hours</div>
                        <div class="stat-description">Total professional development hours completed</div>
                        <?php if ($leave_hours_deducted > 0): ?>
                            <div class="stat-subtitle">(
                                <?php echo number_format(
                                    ($cpd_stats["target_minutes"] / 60) + $leave_hours_deducted,
                                     1); ?> 
                                    - <?php echo number_format($leave_hours_deducted, 1); ?> = <?php echo number_format(($cpd_stats["target_minutes"] / 60), 1); ?>)</div>
                        <?php endif; ?>
                    </div>
                    <div class="stat-progress">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo min(100, ($total_cpd_hours / ($cpd_stats["target_minutes"] / 60 )) * 100); ?>%"></div>
                        </div>
                        <div class="progress-text"><?php echo number_format(($total_cpd_hours / ($cpd_stats["target_minutes"] / 60 )) * 100, 1); ?>% of target</div>
                        <div class="progress-details">
                            <div class="detail-item">
                                <span class="detail-label">Leave Days:</span>
                                <span class="detail-value"><?php echo $leave_duration_days; ?> days</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Membership Factor:</span>
                                <span class="detail-value"><?php echo $membership_constant; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Courses Block -->
                <div class="stat-block courses">
                    <div class="stat-icon">
                        <i class="fas fa-graduation-cap" style="margin-right: 0px; color: white;"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $total_courses; ?></div>
                        <div class="stat-label">Courses Completed</div>
                        <div class="stat-description">Total courses studied across all categories</div>
                    </div>
                    <div class="stat-categories">
                        <?php if (isset($cpd_stats['courses_summary']) && is_array($cpd_stats['courses_summary'])): ?>
                            <?php foreach ($cpd_stats['courses_summary'] as $category): ?>
                                <div class="category-item">
                                    <span class="category-name"><?php echo esc_html($category['category']); ?></span>
                                    <span class="category-count"><?php echo $category['count']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Subscription Status Block -->
                <div class="stat-block subscription-status">
                    <div class="stat-icon">
                        <i class="fas fa-credit-card" style="margin-right: 0px; color: white;"></i>
                    </div>
                    <div class="stat-content">
                        <?php
                        $current_month = date('n'); // Current month number (1-12)
                        $month_names = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                        $current_month_name = $month_names[$current_month];
                        ?>
                        <div class="stat-number" style="font-size: 2.5rem;"><?php echo $member_status === 'active' ? 'Active' : 'Inactive'; ?></div>
                        <div class="stat-label">Subscription Status</div>
                        <div class="stat-description">Membership status for <?php echo $current_month_name . ' ' . date('Y'); ?></div>
                    </div>
                    <div class="stat-details" style="margin-top: 10px;">
                        <div class="detail-item">
                            <span class="detail-label">Status:</span>
                            <span class="detail-value <?php echo $member_status === 'active' ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo ucfirst($member_status); ?>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Last Updated:</span>
                            <span class="detail-value"><?php echo date('M d, Y'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Submission Status Section -->
        <?php if (false && $is_submitted): // Hide submission status section when submitted ?>
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
        
        <div class="portal-layout">
            <!-- Left Side - My CPD Course (for assigned users) -->
            <div class="cpd-course-panel" id="cpd-course-panel">
                <div class="cpd-course-card">
                    <div class="cpd-card-header" style="display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 16px;">
                        <h3 style="margin: 0;">My CPD Course</h3>
                        <div class="cpd-year-selector" style="display: flex; align-items: center; gap: 8px;">
                            <label for="cpd-year-select" style="font-size: 0.9rem; color: #4b5563;">Year:</label>
                            <select id="cpd-year-select" style="padding: 6px 10px; border-radius: 6px; border: 1px solid #d1d5db; font-size: 0.9rem;">
                                <?php
                                $current_year_for_select = $current_year;
                                $current_user_id_for_select = get_current_user_id();
                                // Get user's enrollment date
                                $user_registered_for_select = $wpdb->get_var($wpdb->prepare(
                                    "SELECT user_registered FROM {$wpdb->users} WHERE ID = %d",
                                    $current_user_id_for_select
                                ));
                                // Extract enrollment year
                                $enrollment_year_for_select = 2019; // Default fallback
                                if ($user_registered_for_select) {
                                    $enrollment_year_for_select = (int) date('Y', strtotime($user_registered_for_select));
                                }
                                // Generate years from current year down to enrollment year
                                for ($year = $current_year_for_select; $year >= $enrollment_year_for_select; $year--) {
                                    $selected = ($year == $current_year_for_select) ? 'selected' : '';
                                    echo "<option value='{$year}' {$selected}>{$year}</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Log Training Button or Success Alert (shown dynamically via JavaScript) -->
                    <div id="sidebar-action-container">
                        <button class="btn btn-primary" id="log-training-btn" <?php echo ($is_submitted || $member_status === 'inactive') ? 'disabled' : ''; ?>>
                            <?php 
                            if ($member_status === 'inactive') {
                                echo 'Membership Inactive';
                            } elseif ($is_submitted) {
                                echo 'Training Logging Disabled';
                            } else {
                                echo 'Log Training';
                            }
                            ?>
                        </button>
                    </div>
                    
                    <!-- CPD Stats Grid -->
                    <div class="cpd-stats-grid">
                        <div class="stat-item">
                            <div class="stat-label">CPD Requirement</div>
                            <div class="stat-value" id="cpd-requirement">0</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">CPD hours logged</div>
                            <div class="stat-value" id="cpd-hours-logged">0</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Start Date</div>
                            <div class="stat-value" id="cpd-start-date">-</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Completion Date</div>
                            <div class="stat-value" id="cpd-completion-date">-</div>
                        </div>
                    </div>
                        
                    <!-- Course Summary Table -->
                    <div class="course-summary-section">
                        <h4>Courses Summary</h4>
                        <div class="summary-content" id="course-summary-content">
                            <div class="loading-message">
                                <div class="loading-spinner">
                                    <div class="spinner"></div>
                                    <p>Loading summary...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- <button class="btn btn-outline" id="submit-return-btn">Submit my return</button> -->
                    
                    <!-- CPD Assignment and Submission Buttons -->
                    <!-- Submit Button Container (separate from download button) -->
                    <div class="cpd-action-buttons" id="cpd-action-buttons-submit" style="display: none;">
                        <button class="btn btn-success" id="submit-cpd-btn">
                            <span class="btn-icon"><i class="fas fa-check"></i></span>
                            <span id="submit-btn-text">Submit <?php echo $current_year; ?> CPD return</span>
                        </button>
                    </div>
                    
                    <!-- Download Certificate Button Container (separate from submit button) -->
                    <div class="cpd-action-buttons" id="cpd-action-buttons-download" style="display: none;">
                        <button class="btn btn-success" id="download-certificate-btn" onclick="directDownloadCertificate()" style="display: none;">
                            <span class="btn-icon"><i class="fas fa-download"></i></span>
                            Download Certificate
                        </button>
                    </div>
                    
                    <!-- Validation Message Area -->
                    <div class="cpd-validation-message" id="cpd-validation-message" style="display: none;">
                        <!-- Validation messages will be shown here -->
                    </div>

                    <!-- Submission Deadline Alert -->
                    <?php if (!$is_submitted): ?>
                    <div class="submission-alert" id="submission-alert" style="display: none;">
                        <div class="alert-content">
                            <i class="fas fa-calendar-check alert-icon"></i>
                            <div class="alert-text">
                                <strong>Submission Deadline</strong>
                                <p id="submission-deadline-text">-</p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Side (for assigned users) -->
            <div class="portal-right-panel" id="portal-right-panel">
                <!-- Quick Links -->
                <div class="quick-links">
                    <div class="quick-link-card" id="all-courses-card">
                        <div class="card-icon"><i class="fas fa-book"></i></div>
                        <h4>All Courses</h4>
                        <?php if ($is_submitted): ?>
                            <span class="card-link disabled" style="color: #9ca3af; cursor: not-allowed;">Disabled <i class="fas fa-lock"></i></span>
                        <?php else: ?>
                            <a href="<?php echo home_url('/cpd-courses/'); ?>" class="card-link" id="browse-courses-link" data-base-url="<?php echo home_url('/cpd-courses/'); ?>">Browse courses <i class="fas fa-arrow-right"></i></a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="quick-link-card">
                        <div class="card-icon"><i class="fas fa-calendar"></i></div>
                        <h4>Submit Leave Request</h4>
                        <a href="<?php echo home_url('/leave-request/') ?>" class="card-link" id="submit-leave-link">Submit <i class="fas fa-arrow-right"></i></a>
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
                            <button class="btn btn-primary" id="log-first-training-btn" <?php echo ($is_submitted || $member_status === 'inactive') ? 'disabled' : ''; ?>>
                                <?php 
                                if ($member_status === 'inactive') {
                                    echo 'Membership Inactive';
                                } elseif ($is_submitted) {
                                    echo 'Training Logging Disabled';
                                } else {
                                    echo 'Log your first training';
                                } 
                                ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; // End check for inactive membership status ?>
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
                    <h4>Ask for a course to be added to our list</h4>
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
    
    /* CPD Success Alert in Sidebar (compact version) */
    .cpd-success-alert-sidebar {
        margin-bottom: 10px;
        padding: 16px;
    }
    
    .cpd-success-alert-sidebar .alert-message h4 {
        font-size: 18px;
        margin-bottom: 6px;
    }
    
    .cpd-success-alert-sidebar .alert-message p {
        font-size: 14px;
    }
    
    .cpd-success-alert-sidebar .alert-icon {
        font-size: 28px;
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
        grid-template-columns: 420px 1fr;
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



    /* CPD Stats Grid */
    .cpd-stats-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }

    .cpd-stats-grid .stat-item {
        text-align: center;
        padding: 16px;
        background: #f9fafb;
        border-radius: 8px;
    }

    .cpd-stats-grid .stat-label {
        display: block;
        font-size: 12px;
        color: #6b7280;
        margin-bottom: 8px;
        text-transform: capitalize;
        letter-spacing: 0.5px;
    }

    .cpd-stats-grid .stat-value {
        font-size: 18px;
        font-weight: 600;
        color: #1f2937;
    }

    /* Course Summary Section */
    .course-summary-section {
        margin-bottom: 20px;
        padding: 20px;
        background: #f8fafc;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }

    .course-summary-section h4 {
        margin: 0 0 16px 0;
        font-size: 16px;
        font-weight: 600;
        color: #1f2937;
    }

    .summary-content {
        margin-bottom: 16px;
    }

    /* Submission Alert */
    .submission-alert {
        padding: 16px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 8px;
        color: white;
        box-shadow: 0 4px 6px rgba(102, 126, 234, 0.15);
    }

    .submission-alert .alert-content {
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }

    .submission-alert .alert-icon {
        font-size: 20px;
        margin-top: 2px;
        opacity: 0.95;
    }

    .submission-alert .alert-text {
        flex: 1;
    }

    .submission-alert .alert-text strong {
        display: block;
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 4px;
    }

    .submission-alert .alert-text p {
        margin: 0;
        font-size: 13px;
        opacity: 0.95;
    }

    .summary-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 16px;
    }

    .summary-table th,
    .summary-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #e5e7eb;
    }

    .summary-table th {
        background: #f9fafb;
        font-weight: 600;
        color: #374151;
        font-size: 14px;
        text-transform: capitalize;
        letter-spacing: 0.5px;
    }

    .summary-table td {
        color: #1f2937;
        font-size: 14px;
    }

    .summary-table tr:hover {
        background: #f9fafb;
    }

    .summary-category {
        font-weight: 500;
        color: #374151;
        align-items: center;
    }

    .summary-hours {
        font-weight: 600;
        color: #8b5a96;
    }

    .status-icon {
        margin-right: 6px;
        font-size: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .status-icon i {
        display: inline-block;
    }

    .status-completed {
        color: #10b981;
    }

    .status-incomplete {
        color: #ef4444;
    }

    .status-forgoable {
        color: #6b7280;
        font-style: italic;
    }

    .completion-status {
        font-weight: 600;
        min-width: 40px;
        text-align: center;
    }

    .summary-total {
        display: flex;
        justify-content: space-between;
        padding: 0px 14px;
        align-items: center;
    }

    .total-label {
        font-weight: 600;
        color: #1f2937;
        font-size: 16px;
    }

    .total-value {
        font-weight: 600;
        color: #8b5a96;
        font-size: 18px;
    }

    /* Loading Spinner */
    .loading-spinner {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 20px;
        padding: 40px 20px;
    }

    .spinner {
        width: 40px;
        height: 40px;
        border: 4px solid #e5e7eb;
        border-top: 4px solid #8b5a96;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .loading-spinner p {
        margin: 0;
        color: #6b7280;
        font-size: 14px;
    }

    .no-data-message {
        text-align: center;
        padding: 20px;
        color: #6b7280;
        font-size: 14px;
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


    /* Make buttons full width */
    .cpd-course-card .btn {
        width: 100%;
        margin-bottom: 20px;
    }

    .cpd-course-card .btn:last-child {
        margin-bottom: 0;
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

    /* Statistics Blocks Section */
    .statistics-blocks-section {
        margin-bottom: 40px;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 25px;
        margin-bottom: 30px;
    }

    .stat-block {
        background: white;
        border-radius: 20px;
        padding: 30px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        animation: fadeInUp 0.6s ease-out;
    }

    .stat-block::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #667eea, #764ba2);
    }

    .stat-block:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    }

    .stat-block:nth-child(1) {
        animation-delay: 0.1s;
    }

    .stat-block:nth-child(2) {
        animation-delay: 0.2s;
    }

    .stat-block:nth-child(3) {
        animation-delay: 0.3s;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .stat-block.cpd-records::before {
        background: linear-gradient(90deg, #10b981, #059669);
    }

    .stat-block.courses::before {
        background: linear-gradient(90deg, #3b82f6, #1d4ed8);
    }

    .stat-block.subscription-status::before {
        background: linear-gradient(90deg, #8b5a96, #6b4c93);
    }

    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 20px;
        font-size: 24px;
        color: white;
    }

    .stat-block.cpd-records .stat-icon {
        background: linear-gradient(135deg, #10b981, #059669);
    }

    .stat-block.courses .stat-icon {
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    }

    .stat-block.subscription-status .stat-icon {
        background: linear-gradient(135deg, #8b5a96, #6b4c93);
    }

    .stat-content {
        margin-bottom: 20px;
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 5px;
        line-height: 1;
    }

    .stat-label {
        font-size: 1.1rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 8px;
    }

    .stat-description {
        font-size: 0.9rem;
        color: #6b7280;
        line-height: 1.4;
    }

    /* CPD Records Progress Bar */
    .stat-progress {
        margin-top: 15px;
    }

    .progress-bar {
        width: 100%;
        height: 8px;
        background: #e5e7eb;
        border-radius: 4px;
        overflow: hidden;
        margin-bottom: 8px;
    }

    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #10b981, #059669);
        border-radius: 4px;
        transition: width 0.3s ease;
    }

    .progress-text {
        font-size: 0.85rem;
        color: #6b7280;
        text-align: center;
    }

    /* Courses Categories */
    .stat-categories {
        margin-top: 15px;
    }

    .category-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #f3f4f6;
    }

    .category-item:last-child {
        border-bottom: none;
    }

    .category-name {
        font-size: 0.9rem;
        color: #374151;
        font-weight: 500;
    }

    .category-count {
        font-size: 0.9rem;
        color: #3b82f6;
        font-weight: 600;
        background: #eff6ff;
        padding: 2px 8px;
        border-radius: 12px;
    }

    /* Leave Requests Details */
    .stat-details {
        margin-top: 15px;
    }

    .detail-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #f3f4f6;
    }

    .detail-item:last-child {
        border-bottom: none;
    }

    .detail-label {
        font-size: 0.9rem;
        color: #6b7280;
    }

    .detail-value {
        font-size: 0.9rem;
        color: #f59e0b;
        font-weight: 600;
        background: #fffbeb;
        padding: 2px 8px;
        border-radius: 12px;
    }

    /* CPD Hours Subtitle */
    .stat-subtitle {
        font-size: 0.85rem;
        color: #6b7280;
        margin-top: 8px;
    }

    /* Progress Details (for Leave Days and Membership Factor) */
    .progress-details {
        margin-top: 10px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    /* Status Active/Inactive Colors */
    .status-active {
        color: #10b981 !important;
        background: #d1fae5 !important;
    }

    .status-inactive {
        color: #ef4444 !important;
        background: #fee2e2 !important;
    }

    #sidebar-action-container {
        margin-bottom: 20px;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .stat-block {
            padding: 25px 20px;
        }

        .stat-number {
            font-size: 2rem;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            font-size: 20px;
        }
    }

    @media (max-width: 480px) {
        .statistics-blocks-section {
            margin-bottom: 30px;
        }

        .stat-block {
            padding: 20px 15px;
        }

        .stat-number {
            font-size: 1.8rem;
        }

        .stat-label {
            font-size: 1rem;
        }

        .stat-description {
            font-size: 0.85rem;
        }
    }
</style>

<script>
    /**
     * Get the current CPD year based on date logic
     * If current date is before January 31st, return previous year
     * Otherwise return current year
     * This matches the PHP function iipm_get_cpd_logging_year()
     * 
     * @return {number} The CPD year
     */
    function getCpdYear() {
        const now = new Date();
        const currentYear = now.getFullYear();
        
        // Member Portal: Always use current date year (not CPD year logic)
        // If today is 2026, returns 2026
        // Note: Payment Management page uses different logic (CPD year N = membership expiration Feb 1, N+1)
        return currentYear;
    }
    
    // Define ajaxurl for AJAX calls
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    var isUserAssigned = true;
    var isTrainingCompleted = <?php echo $cpd_stats['completion_percentage'] >= 100 ? 'true' : 'false'; ?> == 'true' ? true : false;
    var isSubmitted = <?php echo $is_submitted ? 'true' : 'false'; ?>;
    var statData = null;
    // CPD logging year - uses current date year (Member Portal uses current year, not CPD year logic)
    var cpdLoggingYear = getCpdYear();
    // Base CPD year (current year) - used to detect non-current views
    var baseCpdYear = cpdLoggingYear;
    
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
        // Check certificate availability on page load
        checkCertificateAvailability(null);
        <?php endif; ?>
        
        /**
         * Initialize the page
         */
        function initializePage() {
            // Sync selector with current CPD year if it exists
            const cpdYearSelect = document.getElementById('cpd-year-select');
            if (cpdYearSelect) {
                cpdYearSelect.value = cpdLoggingYear.toString();
            }
            
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
            
            // Apply initial year-based UI state
            updateYearDependentUI();
            
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
            formData.append('year', cpdLoggingYear);
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

            // Year selector change
            const cpdYearSelect = document.getElementById('cpd-year-select');
            if (cpdYearSelect) {
                cpdYearSelect.addEventListener('change', function() {
                    const selected = parseInt(this.value, 10);
                    if (!isNaN(selected)) {
                        cpdLoggingYear = selected;
                        console.log('Changed CPD year to:', cpdLoggingYear);
                        updateYearDependentUI();
                        loadCompletedCpdStats();
                        if (isUserAssigned) {
                            loadRecentlyLoggedTraining();
                        }
                        // Update submit button text when year changes
                        const submitBtnText = document.getElementById('submit-btn-text');
                        if (submitBtnText) {
                            submitBtnText.textContent = `Submit ${selected} CPD return`;
                        }
                        // Note: updateCpdActionButtons and checkCertificateAvailability will be called
                        // when loadCompletedCpdStats completes and calls updateCpdProgress
                    }
                });
            }
        }
        
        /**
         * Load completed CPD stats
         */
        function loadCompletedCpdStats() {
            // Use cpdLoggingYear which accounts for January deadline extension
            console.log('Loading CPD stats for year:', cpdLoggingYear);
            
            const formData = new FormData();
            formData.append('action', 'iipm_get_cpd_stats');
            formData.append('year', cpdLoggingYear);
            
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
            
            // Update is_submitted status based on the selected year from the response
            // submission_data.submitted indicates if CPD is submitted for the selected year
            if (data.submission_data && typeof data.submission_data.submitted !== 'undefined') {
                // Update the data object with is_submitted for use in other functions
                data.is_submitted = data.submission_data.submitted;
                // Also update the global isSubmitted variable based on the selected year
                // This ensures functions like updateYearDependentUI and renderTrainingInPortal
                // use the correct submission status for the selected year
                isSubmitted = data.submission_data.submitted;
            } else {
                // Fallback: if submission_data is not available, assume not submitted
                data.is_submitted = false;
                isSubmitted = false;
            }
            
            // Update high-level requirement/hours stats
            updateCpdStats(data);
            updateCourseSummary(data);
            
            // Update category progress
            const categories = ['pensions', 'savings & investments', 'ethics', 'life assurance'];
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
            
            // Update CPD stats and course summary
            updateCpdStats(data);
            updateCourseSummary(data);
            
            // Update submission deadline alert
            updateSubmissionAlert(data);
            
            // Update CPD action buttons (submit button) - handles current year submit button
            // This will also call checkCertificateAvailability when appropriate
            updateCpdActionButtons(data);
            
            // Update year-dependent UI elements (including browse courses link)
            // This ensures the browse courses link is updated when submission status changes
            updateYearDependentUI();
            
            // Re-render training blocks with updated submission status
            // This ensures remove buttons show/hide correctly when year changes and isSubmitted is updated
            if (isUserAssigned && currentTrainingData) {
                // Re-render existing training data with updated isSubmitted status
                // This avoids unnecessary API call and ensures immediate update
                renderTrainingInPortal(currentTrainingData);
                // Also check if table view exists and re-render it
                const trainingTableSection = document.getElementById('training-table-section');
                if (trainingTableSection && trainingTableSection.innerHTML.trim() !== '') {
                    renderTrainingAsTable(currentTrainingData);
                }
            }
            
            console.log(`Total completed: ${totalCompleted}/4`); // Debug log
        }
        
        /**
         * Check certificate availability and show/hide download button
         * Download button logic:
         * - For 2025+: Show only if submitted AND all requirements met
         * - For <=2024: Show if all requirements met (no submission needed)
         * - Never show together with submit button
         */
        function checkCertificateAvailability(data) {
            const yearSelect = document.getElementById('cpd-year-select');
            const selectedYear = yearSelect ? parseInt(yearSelect.value, 10) : getCpdYear();
            
            // Check if submit container is visible - if so, don't show download button
            const submitContainer = document.getElementById('cpd-action-buttons-submit');
            const downloadContainer = document.getElementById('cpd-action-buttons-download');
            const submitBtn = document.getElementById('submit-cpd-btn');
            
            if (submitContainer && submitContainer.style.display !== 'none' && submitContainer.offsetParent !== null) {
                // Submit button is visible - hide download button and return
                const downloadBtn = document.getElementById('download-certificate-btn');
                if (downloadBtn) {
                    downloadBtn.style.display = 'none';
                }
                if (downloadContainer) {
                    downloadContainer.style.display = 'none';
                }
                return;
            }
            
            // Hide submit container when showing download button (they should never show together)
            if (submitContainer) {
                submitContainer.style.display = 'none';
            }
            if (submitBtn) {
                submitBtn.style.display = 'none';
            }
            
            // Call backend endpoint - it handles all logic based on year
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'iipm_check_certificate_availability',
                    year: selectedYear,
                    nonce: '<?php echo wp_create_nonce("iipm_certificate_nonce"); ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                // Check if submit container is visible - if so, don't show download button
                const submitContainer = document.getElementById('cpd-action-buttons-submit');
                const downloadContainer = document.getElementById('cpd-action-buttons-download');
                
                if (submitContainer && submitContainer.style.display !== 'none' && submitContainer.offsetParent !== null) {
                    // Submit button is showing - don't show download button
                    const downloadBtn = document.getElementById('download-certificate-btn');
                    if (downloadBtn) {
                        downloadBtn.style.display = 'none';
                    }
                    if (downloadContainer) {
                        downloadContainer.style.display = 'none';
                    }
                    return;
                }
                
                let downloadBtn = document.getElementById('download-certificate-btn');
                
                if (data.success && data.data.has_certificate) {
                    // Certificate is available - use the existing download container
                    if (!downloadContainer) {
                        // Container doesn't exist - create it
                        const courseSummarySection = document.querySelector('.course-summary-section');
                        const cpdCourseCard = document.querySelector('.cpd-course-card');
                        
                        // Create the container
                        const newDownloadContainer = document.createElement('div');
                        newDownloadContainer.className = 'cpd-action-buttons';
                        newDownloadContainer.id = 'cpd-action-buttons-download';
                        newDownloadContainer.style.display = 'flex';
                        
                        // Insert in appropriate location (after course summary, before submission alert if exists)
                        if (courseSummarySection && courseSummarySection.parentNode) {
                            // Insert after course summary section
                            const nextSibling = courseSummarySection.nextElementSibling;
                            if (nextSibling) {
                                courseSummarySection.parentNode.insertBefore(newDownloadContainer, nextSibling);
                            } else {
                                courseSummarySection.parentNode.appendChild(newDownloadContainer);
                            }
                        } else if (cpdCourseCard) {
                            // Insert at the end of cpd-course-card
                            cpdCourseCard.appendChild(newDownloadContainer);
                        } else {
                            // Fallback: find cpd-course-panel
                            const cpdCoursePanel = document.getElementById('cpd-course-panel');
                            if (cpdCoursePanel) {
                                cpdCoursePanel.appendChild(newDownloadContainer);
                            }
                        }
                    }
                    
                    // Get the download container (either existing or newly created)
                    const finalDownloadContainer = document.getElementById('cpd-action-buttons-download');
                    
                    if (!downloadBtn && finalDownloadContainer) {
                        // Create the download button
                        downloadBtn = document.createElement('button');
                        downloadBtn.className = 'btn btn-success';
                        downloadBtn.id = 'download-certificate-btn';
                        downloadBtn.onclick = directDownloadCertificate;
                        downloadBtn.innerHTML = '<span class="btn-icon"><i class="fas fa-download"></i></span>Download Certificate';
                        finalDownloadContainer.appendChild(downloadBtn);
                    }
                    
                    // Ensure submit container is hidden (they should never show together)
                    if (submitContainer) {
                        submitContainer.style.display = 'none';
                    }
                    const submitBtn = document.getElementById('submit-cpd-btn');
                    if (submitBtn) {
                        submitBtn.style.display = 'none';
                    }
                    
                    // Show the download button and container
                    if (downloadBtn) {
                        downloadBtn.style.display = 'block';
                    }
                    if (finalDownloadContainer) {
                        finalDownloadContainer.style.display = 'flex';
                    }
                } else {
                    // No certificate available, hide the download button and container
                    if (downloadBtn) {
                        downloadBtn.style.display = 'none';
                    }
                    if (downloadContainer) {
                        downloadContainer.style.display = 'none';
                    }
                }
            })
            .catch(error => {
                console.error('Error checking certificate availability:', error);
                // On error, hide the buttons if they exist
                const downloadBtn = document.getElementById('download-certificate-btn');
                const cpdActionButtons = document.getElementById('cpd-action-buttons');
                if (downloadBtn) {
                    downloadBtn.style.display = 'none';
                }
                if (cpdActionButtons) {
                    cpdActionButtons.style.display = 'none';
                }
            });
        }
        
        /**
         * Update submission deadline alert
         * Members can submit CPD until next year's Jan 30th
         */
        function updateSubmissionAlert(data) {
            const submissionAlert = document.getElementById('submission-alert');
            const submissionDeadlineText = document.getElementById('submission-deadline-text');
            
            if (!submissionAlert || !submissionDeadlineText) return;
            
            // Show alert only if user is assigned and in submission period (not submitted)
            const isUserAssigned = data.is_user_assigned;
            const isSubmitted = data.is_submitted;
            
            const yearSelect = document.getElementById('cpd-year-select');
            const selectedYear = yearSelect ? parseInt(yearSelect.value, 10) : getCpdYear();
            const isInExtendedPeriod = isInExtendedSubmissionPeriod(selectedYear);
            const isSubmissionPeriod = data.is_submission_period_available || isInExtendedPeriod;
            
            if (isUserAssigned && isSubmissionPeriod && !isSubmitted) {
                // Calculate next year's January 30th deadline
                const nextYear = selectedYear + 1;
                const deadlineDate = new Date(nextYear, 0, 30); // Jan 30 of next year
                const formattedDeadline = deadlineDate.toLocaleDateString('en-GB', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric'
                });
                submissionDeadlineText.textContent = `Submit your CPD return before ${formattedDeadline}`;
                submissionAlert.style.display = 'block';
            } else {
                submissionAlert.style.display = 'none';
            }
        }
        
        /**
         * Check if we're in the submission period for a given year
         * Submission period formula: X year's submission duration is from X's Jan 1st to X+1's Jan 30th
         * Example: For CPD year 2025, submission period is Jan 1, 2025 to Jan 30, 2026
         */
        function isInExtendedSubmissionPeriod(selectedYear) {
            const now = new Date();
            const currentYear = now.getFullYear();
            const currentMonth = now.getMonth() + 1; // 1-12
            const currentDay = now.getDate();
            
            const yearNum = parseInt(selectedYear, 10);
            const nextYear = yearNum + 1;
            
            // Submission period: Jan 1 of year X to Jan 30 of year X+1
            // Case 1: We're in the CPD year itself (year X)
            if (currentYear === yearNum) {
                // Any date in year X is within submission period
                return true;
            }
            
            // Case 2: We're in the year after the CPD year (year X+1)
            if (currentYear === nextYear) {
                // Check if it's January and on or before the 30th
                if (currentMonth === 1 && currentDay <= 30) {
                    return true; // On or before Jan 30
                }
                return false; // After Jan 30
            }
            
            // Case 3: We're before the CPD year or after the submission deadline
            return false;
        }
        
        /**
         * Update CPD action buttons based on period and assignment status
         * Submit button: Show for any year that's in its submission period (Jan 1 of year X to Jan 30 of year X+1)
         * Submission period formula: X year's submission duration is from X's Jan 1st to X+1's Jan 30th
         */
        function updateCpdActionButtons(data) {
            const submitContainer = document.getElementById('cpd-action-buttons-submit');
            const downloadContainer = document.getElementById('cpd-action-buttons-download');
            const submitBtn = document.getElementById('submit-cpd-btn');
            const submitBtnText = document.getElementById('submit-btn-text');
            const downloadBtn = document.getElementById('download-certificate-btn');
            
            // Hide download container first - we'll show it separately in checkCertificateAvailability
            if (downloadContainer) {
                downloadContainer.style.display = 'none';
            }
            if (downloadBtn) {
                downloadBtn.style.display = 'none';
            }

            const yearSelect = document.getElementById('cpd-year-select');
            const selectedYear = yearSelect ? parseInt(yearSelect.value, 10) : getCpdYear();
            
            // Update submit button text to show selected year
            if (submitBtnText) {
                submitBtnText.textContent = `Submit ${selectedYear} CPD return`;
            }
            
            if (!submitContainer || !submitBtn) return;
            
            // Check if we're in the submission period for the selected year
            // Submission period: Jan 1 of year X to Jan 30 of year X+1
            const isInSubmissionPeriod = isInExtendedSubmissionPeriod(selectedYear);
            const isSubmissionPeriod = data.is_submission_period_available || isInSubmissionPeriod;
            const isUserAssigned = data.is_user_assigned;
            const isSubmitted = data.is_submitted || false;
            
            // If already submitted, hide submit button immediately and check for certificate
            if (isSubmitted) {
                submitBtn.style.display = 'none';
                submitContainer.style.display = 'none';
                hideValidationMessage();
                // Check for certificate since it's already submitted
                checkCertificateAvailability(data);
                checkAndShowSuccessAlert(data);
                return;
            }
            
            // If not in submission period, hide submit button and check certificate
            if (!isSubmissionPeriod) {
                submitBtn.style.display = 'none';
                submitContainer.style.display = 'none';
                hideValidationMessage();
                // Check certificate availability for years outside submission period
                checkCertificateAvailability(data);
                checkAndShowSuccessAlert(data);
                return;
            }
            
            // Check if user meets all submission requirements
            const meetsSubmissionRequirements = checkSubmissionRequirements(data);
            
            // User is assigned, show submit button if:
            // - In submission period (Jan 1 of year X to Jan 30 of year X+1)
            // - Meets all requirements
            // - Not already submitted (checked above)
            if (isUserAssigned && isSubmissionPeriod && meetsSubmissionRequirements) {
                // Hide download container - submit and download should never show together
                if (downloadContainer) {
                    downloadContainer.style.display = 'none';
                }
                if (downloadBtn) {
                    downloadBtn.style.display = 'none';
                }
                // Show submit button and its container
                submitContainer.style.display = 'flex';
                submitBtn.style.display = 'block';
                hideValidationMessage();
            } else {
                // Hide submit button if requirements not met
                submitBtn.style.display = 'none';
                submitContainer.style.display = 'none';
                
                // Show validation message if user is assigned and in submission period but doesn't meet requirements
                if (isUserAssigned && isSubmissionPeriod) {
                    showValidationMessage(data);
                } else {
                    hideValidationMessage();
                }
                
                // If not showing submit button, check for certificate
                checkCertificateAvailability(data);
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
            // Skip forgoable categories (categories that are not required for this user/year)
            for (let category of data.courses_summary) {
                // Skip if category is forgoable (not required for this user/year)
                // Forgoable categories have required = 0 or is_forgoable = true
                if (category.is_forgoable || category.required === 0) {
                    console.log(`Category "${category.category}" is forgoable (not required) - skipping requirement check`);
                    continue;
                }
                
                // Only check categories that are required (required > 0)
                if (category.required > 0 && category.total_hours < 1) {
                    console.log(`Category "${category.category}" has insufficient training hours (${category.total_hours} hours, required: at least 1 hour)`);
                    return false;
                }
            }
            
            console.log('All required (non-forgoable) categories have at least 1 hour of training');
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
         * Check if user has completed all CPD requirements and show success alert in sidebar
         */
        function checkAndShowSuccessAlert(data) {
            if (!data.courses_summary || data.courses_summary.length === 0) {
                // If no courses summary, restore Log Training button
                restoreLogTrainingButton();
                return;
            }
            
            // Get the selected year
            const yearSelect = document.getElementById('cpd-year-select');
            const selectedYear = yearSelect ? parseInt(yearSelect.value, 10) : getCpdYear();
            
            // Check if CPD is submitted for this year
            const isSubmitted = data.is_submitted || false;
            
            let totalCompleted = 0;
            let totalRequired = 0;
            
            data.courses_summary.forEach(category => {
                // Skip forgoable categories (not required for this user/year)
                if (category.is_forgoable || category.required === 0) {
                    return; // Skip this category
                }
                totalCompleted += category.count;
                totalRequired += category.required;
            });
            
            const isFullyCompleted = (totalCompleted >= totalRequired && data.completion_percentage >= 100);
            const sidebarActionContainer = document.getElementById('sidebar-action-container');
            const existingAlert = document.getElementById('cpd-success-alert');
            
            // For 2025 and above: Only show congratulations alert when CPD is submitted
            // For 2024 and below: Show congratulations alert when all requirements are fulfilled (like download certificate button)
            if (selectedYear >= 2025) {
                // 2025+: Only show when submitted
                if (isSubmitted && isFullyCompleted) {
                    if (!existingAlert) {
                        showSuccessAlert();
                    }
                } else {
                    // If not submitted or not fully completed, restore Log Training button
                    if (existingAlert) {
                        restoreLogTrainingButton();
                    }
                }
            } else {
                // <=2024: Show when all requirements fulfilled (regardless of submission)
                if (isFullyCompleted) {
                    // Show success alert if fully completed and not already shown
                    if (!existingAlert) {
                        showSuccessAlert();
                    }
                } else {
                    // If not fully completed, restore Log Training button
                    if (existingAlert) {
                        restoreLogTrainingButton();
                    }
                }
            }
        }
        
        /**
         * Restore Log Training button in sidebar
         */
        function restoreLogTrainingButton() {
            const sidebarActionContainer = document.getElementById('sidebar-action-container');
            const existingAlert = document.getElementById('cpd-success-alert');
            
            if (existingAlert) {
                existingAlert.remove();
            }
            
            if (sidebarActionContainer) {
                const memberStatus = <?php echo $member_status === 'inactive' ? "'inactive'" : "false"; ?>;
                const isSubmitted = <?php echo $is_submitted ? 'true' : 'false'; ?>;
                
                let buttonText = 'Log Training';
                let disabled = '';
                if (memberStatus === 'inactive') {
                    buttonText = 'Membership Inactive';
                    disabled = 'disabled';
                } else if (isSubmitted) {
                    buttonText = 'Training Logging Disabled';
                    disabled = 'disabled';
                }
                
                sidebarActionContainer.innerHTML = `
                    <button class="btn btn-primary" id="log-training-btn" ${disabled}>
                        ${buttonText}
                    </button>
                `;
                
                // Re-attach event listener if needed
                const newBtn = document.getElementById('log-training-btn');
                if (newBtn && !disabled) {
                    newBtn.addEventListener('click', showLogTrainingModal);
                }
            }
        }
        
        /**
         * Show success alert dynamically in sidebar instead of Log Training button
         */
        function showSuccessAlert() {
            // Check if alert already exists
            if (document.getElementById('cpd-success-alert')) {
                return;
            }
            
            const currentCpdYear = getCpdYear();
            const yearSelect = document.getElementById('cpd-year-select');
            const selectedYear = yearSelect ? parseInt(yearSelect.value, 10) : currentCpdYear;
            
            // Find the sidebar action container (where Log Training button is)
            const sidebarActionContainer = document.getElementById('sidebar-action-container');
            const logTrainingBtn = document.getElementById('log-training-btn');
            
            if (!sidebarActionContainer) {
                console.error('Sidebar action container not found');
                return;
            }
            
            // Hide the Log Training button
            if (logTrainingBtn) {
                logTrainingBtn.style.display = 'none';
            }
            
            // Create the alert HTML for sidebar (compact version)
            const alertHTML = `
                <div class="cpd-success-alert cpd-success-alert-sidebar" id="cpd-success-alert">
                    <div class="alert-content">
                        <div class="alert-icon">🎉</div>
                        <div class="alert-message">
                            <h4>Congratulations!</h4>
                            <p>You have successfully completed all required CPD courses for ${selectedYear}.</p>
                        </div>
                        <button class="alert-close" onclick="closeSuccessAlert()">×</button>
                    </div>
                </div>
            `;
            
            // Replace the Log Training button with the success alert
            sidebarActionContainer.innerHTML = alertHTML;
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
         * Update CPD stats display (Requirement, Hours Logged, Start Date, Completion Date)
         */
        function updateCpdStats(data) {
            console.log('Updating CPD stats:', data);
            
            // Update CPD Requirement
            const requirementElement = document.getElementById('cpd-requirement');
            if (requirementElement && data.target_minutes) {
                const targetHours = Math.round((data.target_minutes / 60) * 2) / 2; // Round to nearest 0.5
                requirementElement.textContent = targetHours.toFixed(1) + ' hours';
            }
            
            // Update CPD Hours Logged
            const loggedElement = document.getElementById('cpd-hours-logged');
            if (loggedElement && data.total_cpd_minutes !== undefined) {
                const loggedHours = Math.round((data.total_cpd_minutes / 60) * 2) / 2; // Round to nearest 0.5
                loggedElement.textContent = loggedHours.toFixed(1) + ' hours';
            }
            
            // Update Start Date (January 1 of the selected year)
            const startDateElement = document.getElementById('cpd-start-date');
            if (startDateElement) {
                const yearSelect = document.getElementById('cpd-year-select');
                const selectedYear = yearSelect ? parseInt(yearSelect.value, 10) : getCpdYear();
                const startDate = new Date(selectedYear, 0, 1); // January 1 of selected year
                startDateElement.textContent = startDate.toLocaleDateString('en-GB', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric'
                });
            }
            
            // Update Completion Date (submission date if submitted, otherwise show "-")
            const completionDateElement = document.getElementById('cpd-completion-date');
            if (completionDateElement) {
                if (data.submission_data && data.submission_data.submitted && data.submission_data.submission_date) {
                    const completionDate = new Date(data.submission_data.submission_date);
                    completionDateElement.textContent = completionDate.toLocaleDateString('en-GB', {
                        day: 'numeric',
                        month: 'long',
                        year: 'numeric'
                    });
                } else {
                    completionDateElement.textContent = '-';
                }
            }
        }
        
        /**
         * Update course summary table
         */
        function updateCourseSummary(data) {
            console.log('Updating course summary:', data);
            
            const summaryContent = document.getElementById('course-summary-content');
            
            if (!summaryContent) return;
            
            if (!data.courses_summary || data.courses_summary.length === 0) {
                summaryContent.innerHTML = '<div class="no-data-message" style="text-align: center; padding: 20px; color: #6b7280;">No courses completed for this year.</div>';
                return;
            }
            
            let html = `
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Progress</th>
                            <th>Hours</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            data.courses_summary.forEach(item => {
                // Check if category is forgoable (not required for this user/year)
                const isForgoable = item.is_forgoable || item.required === 0;
                
                // Calculate hours from minutes and round to nearest 0.5
                const hours = Math.round((item.total_minutes / 60) * 2) / 2;
                
                // For forgoable categories, show "Not Required" instead of progress
                let progressText, statusClass, iconColor, iconType;
                
                if (isForgoable) {
                    progressText = 'Not Required';
                    statusClass = 'status-forgoable';
                    iconColor = '#6b7280'; // Gray for forgoable
                    iconType = 'minus-circle';
                } else {
                    progressText = `${hours} / 1`;
                    const isCompleted = hours >= 1;
                    statusClass = isCompleted ? 'status-completed' : 'status-incomplete';
                    iconColor = isCompleted ? '#10b981' : '#ef4444';
                    iconType = isCompleted ? 'check-circle' : 'times-circle';
                }
                
                html += `
                    <tr>
                        <td class="summary-category">
                            <i class="fas fa-${iconType}" style="font-size: 11px; color: ${iconColor}; margin-right: 6px;"></i>
                            ${item.category}
                            ${isForgoable ? '<span style="font-size: 10px; color: #6b7280; margin-left: 6px;">(Forgoable)</span>' : ''}
                        </td>
                        <td class="completion-status ${statusClass}">${progressText}</td>
                        <td class="summary-hours">${hours.toFixed(1)}</td>
                    </tr>
                `;
            });
            
            html += `
                    </tbody>
                </table>
            `;
            
            summaryContent.innerHTML = html;
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
            console.log('Loading recently logged training for year:', cpdLoggingYear);
            const formData = new FormData();
            formData.append('action', 'iipm_get_recently_logged_training');
            formData.append('year', cpdLoggingYear);
            
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    console.log('Training data response:', response);
                    if (response.success) {
                        updateTrainingDisplay(response.data);
                    } else {
                        console.error('Failed to load training:', response.data);
                        // Show error message in training content
                        const trainingContentEl = document.getElementById('training-content');
                        if (trainingContentEl) {
                            trainingContentEl.innerHTML = '<div class="no-training-message"><p style="color: #ef4444;">Error loading training data. Please try again.</p></div>';
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error loading training:', error);
                    console.error('Status:', status);
                    console.error('Response:', xhr.responseText);
                    // Show error message in training content
                    const trainingContentEl = document.getElementById('training-content');
                    if (trainingContentEl) {
                        trainingContentEl.innerHTML = '<div class="no-training-message"><p style="color: #ef4444;">Error loading training data. Please try again.</p></div>';
                    }
                }
            });
        }
        
        /**
         * Update training display
         */
        // Store training data globally so we can re-render it when isSubmitted changes
        var currentTrainingData = null;
        
        function updateTrainingDisplay(training) {
            console.log('Updating training display with data:', training);
            // Store training data for potential re-rendering
            currentTrainingData = training;
            // Always render in portal (even when submitted, but without delete buttons)
            renderTrainingInPortal(training);
        }

        /**
         * Enable/disable UI elements based on selected CPD year
         * - If viewing a year other than the current logging year, disable:
         *   - Log Training buttons
         *   - Browse Courses link
         *   - Submit Leave Request link
         *   - Training delete buttons (handled in renderTrainingInPortal)
         */
        function updateYearDependentUI() {
            const isCurrentYearView = (cpdLoggingYear === baseCpdYear);
            const lastYear = baseCpdYear - 1;
            const isLastYearView = (cpdLoggingYear === lastYear);
            
            // Check if we're before last year's deadline (Jan 30 of current year)
            const now = new Date();
            const currentYear = now.getFullYear();
            const currentMonth = now.getMonth() + 1; // 1-12
            const currentDay = now.getDate();
            const isBeforeLastYearDeadline = (currentMonth === 1 && currentDay <= 30);
            
            // Browse Courses should be enabled for:
            // 1. Current year
            // 2. Last year if today is before Jan 30 (last year's submission deadline)
            const shouldEnableBrowseCourses = isCurrentYearView || (isLastYearView && isBeforeLastYearDeadline);
            
            // If submitted, always disable actions regardless of year (except Browse Courses which has its own logic)
            const shouldDisableActions = isSubmitted || !isCurrentYearView;
            
            // Log Training main button
            if (logTrainingBtn) {
                if (shouldDisableActions) {
                    logTrainingBtn.disabled = true;
                    logTrainingBtn.classList.add('disabled');
                } else {
                    // Only enable if not inactive (check PHP condition)
                    const isInactive = <?php echo $member_status === 'inactive' ? 'true' : 'false'; ?>;
                    if (!isInactive) {
                        logTrainingBtn.disabled = false;
                        logTrainingBtn.classList.remove('disabled');
                    }
                }
            }
            
            // "Log your first training" button (when no training yet) will be handled when rendering cards
            
            // Browse courses link - special logic: enabled for current year and last year before deadline
            // Also disable if the selected year is already submitted
            const browseCoursesLink = document.getElementById('browse-courses-link');
            if (browseCoursesLink) {
                const baseUrl = browseCoursesLink.dataset.baseUrl || browseCoursesLink.getAttribute('href') || '<?php echo home_url('/cpd-courses/'); ?>';
                
                // Check if selected year is submitted
                const yearSelect = document.getElementById('cpd-year-select');
                const selectedYear = yearSelect ? parseInt(yearSelect.value, 10) : cpdLoggingYear;
                
                // Disable if:
                // 1. Not in allowed years (current year or last year before deadline)
                // 2. Selected year is already submitted
                if (!shouldEnableBrowseCourses || isSubmitted) {
                    browseCoursesLink.classList.add('disabled');
                    browseCoursesLink.setAttribute('href', 'javascript:void(0)');
                    browseCoursesLink.style.cursor = 'not-allowed';
                    browseCoursesLink.style.color = '#9ca3af';
                } else {
                    browseCoursesLink.classList.remove('disabled');
                    // Update URL to include selected year (tyear parameter)
                    const coursesUrl = `${baseUrl}?tyear=${selectedYear}`;
                    browseCoursesLink.setAttribute('href', coursesUrl);
                    browseCoursesLink.style.cursor = 'pointer';
                    browseCoursesLink.style.color = '';
                }
            }
            
            // Submit leave request link - Always enabled (not disabled by year or submission status)
            // Removed disabling logic as per user request
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
                    <div class="training-table-container">
                        <table class="training-table">
                            <thead>
                                <tr>
                                    <th>Course Name</th>
                                    <th>Category</th>
                                    <th>Completed Date</th>
                                    <th>Duration</th>
                                    <th>Status</th>
                                    <th>Action</th>
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
                                    
                                    const yearSelect = document.getElementById('cpd-year-select');
                                    const selectedYear = yearSelect ? parseInt(yearSelect.value, 10) : getCpdYear();
                                    // Show remove button only for 2025 and above, and only if the selected CPD year is not submitted
                                    const canDelete = selectedYear >= 2025 && !isSubmitted;
                                    
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
                                            ${canDelete ? `
                                            <td class="action-cell">
                                                <button class="action-btn delete-btn" onclick="deleteCourse(${item.id}, event)" title="Remove" style="background: #ef4444; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 14px;">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </td>` : '<td class="action-cell"></td>'}
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
            // Get training content element dynamically (in case it wasn't available at page load)
            const trainingContentEl = document.getElementById('training-content');
            if (!trainingContentEl) {
                console.error('Training content element not found');
                return;
            }
            
            // Show remove button only for 2025 and above, and only if the selected CPD year is not submitted
            const canDelete = cpdLoggingYear >= 2025 && !isSubmitted;
            
            if (!training || training.length === 0) {
                trainingContentEl.innerHTML = `
                    <div class="no-training-message">
                        <div class="no-training-icon">💻</div>
                        <h4>No training history yet</h4>
                        <p>Start your CPD journey by logging your first training session</p>
                        ${canDelete ? '<button class="btn btn-primary" id="log-first-training-btn">Log your first training</button>' : ''}
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
                            ${canDelete ? `
                            <div class="training-actions">
                                <button class="action-btn delete-btn" onclick="deleteCourse(${item.id})" title="Remove"><i class="fas fa-trash-alt"></i></button>
                            </div>` : ''}
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
            
            // Set the HTML content
            trainingContentEl.innerHTML = html;
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
                        ">You have successfully submitted your CPD training for ${cpdLoggingYear}!</p>
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
                    year: cpdLoggingYear
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
         * Direct download certificate for submitted CPD (uses current year from year selector)
         */
        window.directDownloadCertificate = function() {
            const yearSelect = document.getElementById('cpd-year-select');
            const selectedYear = yearSelect ? yearSelect.value : getCpdYear();
            const certificateBtn = document.getElementById('download-certificate-btn');
            const originalText = certificateBtn ? certificateBtn.innerHTML : '';
            
            // Show loading state
            if (certificateBtn) {
                certificateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating PDF...';
                certificateBtn.disabled = true;
            }
            
            // Fetch certificate data
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'iipm_get_certificate_data',
                    year: selectedYear,
                    nonce: '<?php echo wp_create_nonce("iipm_certificate_nonce"); ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.certificate) {
                    const certificate = data.data.certificate;
                    const user = data.data.user;
                    
                    // Directly trigger download
                    const params = new URLSearchParams({
                        action: 'iipm_download_certificate_direct',
                        certificate_id: certificate.id,
                        user_name: user.name,
                        user_email: user.email,
                        contact_address: user.contact_address,
                        submission_year: data.data.year
                    });
                    
                    const downloadUrl = `${ajaxurl}?${params.toString()}`;
                    
                    const link = document.createElement('a');
                    link.href = downloadUrl;
                    link.style.display = 'none';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    // Reset button state
                    if (certificateBtn) {
                        setTimeout(() => {
                            certificateBtn.innerHTML = originalText;
                            certificateBtn.disabled = false;
                        }, 2000);
                    }
                } else {
                    alert('Certificate not found for the selected year. Please ensure your CPD has been submitted.');
                    if (certificateBtn) {
                        certificateBtn.innerHTML = originalText;
                        certificateBtn.disabled = false;
                    }
                }
            })
            .catch(error => {
                console.error('Error downloading certificate:', error);
                alert('Error downloading certificate. Please try again.');
                if (certificateBtn) {
                    certificateBtn.innerHTML = originalText;
                    certificateBtn.disabled = false;
                }
            });
        }

        /**
         * Show log training modal
         */
        function showLogTrainingModal() {
            const logTrainingModal = document.getElementById('log-training-modal');
            if (logTrainingModal) {
                logTrainingModal.style.display = 'flex';
            }
        }
        
        /**
         * Hide log training modal
         */
        function hideLogTrainingModal() {
            const logTrainingModal = document.getElementById('log-training-modal');
            if (logTrainingModal) {
                logTrainingModal.style.display = 'none';
            }
        }
        
        /**
         * Select pre-approved training
         */
        function selectPreApprovedTraining() {
            hideLogTrainingModal();
            // Redirect to CPD courses page with selected year
            const yearSelect = document.getElementById('cpd-year-select');
            const selectedYear = yearSelect ? parseInt(yearSelect.value, 10) : getCpdYear();
            const coursesUrl = `<?php echo home_url('/cpd-courses/'); ?>?tyear=${selectedYear}`;
            window.location.href = coursesUrl;
        }
        
        /**
         * Select external training
         */
        function selectExternalTraining() {
            hideLogTrainingModal();
            // Redirect to external courses page
            window.location.href = '<?php echo home_url('/cpd-course-request/'); ?>';
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
        const sidebarActionContainer = document.getElementById('sidebar-action-container');
        const logTrainingBtn = document.getElementById('log-training-btn');
        
        if (alert) {
            alert.style.animation = 'slideUp 0.3s ease-out';
            setTimeout(() => {
                // Remove the alert and restore the Log Training button
                alert.remove();
                
                // Restore Log Training button in sidebar
                if (sidebarActionContainer && logTrainingBtn) {
                    logTrainingBtn.style.display = '';
                } else if (sidebarActionContainer) {
                    // If button doesn't exist, recreate it
                    const memberStatus = <?php echo $member_status === 'inactive' ? "'inactive'" : "false"; ?>;
                    const isSubmitted = <?php echo $is_submitted ? 'true' : 'false'; ?>;
                    
                    let buttonText = 'Log Training';
                    let disabled = '';
                    if (memberStatus === 'inactive') {
                        buttonText = 'Membership Inactive';
                        disabled = 'disabled';
                    } else if (isSubmitted) {
                        buttonText = 'Training Logging Disabled';
                        disabled = 'disabled';
                    }
                    
                    sidebarActionContainer.innerHTML = `
                        <button class="btn btn-primary" id="log-training-btn" ${disabled}>
                            ${buttonText}
                        </button>
                    `;
                }
                
                // Mark submission as notified after alert is closed
                const submissionId = alert.getAttribute('data-submission-id');
                if (submissionId) {
                    markSubmissionAsNotified(submissionId);
                }
            }, 300);
        }
    }
    
    // Function to mark submission as notified
    function markSubmissionAsNotified(submissionId) {
        fetch(ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'iipm_mark_submission_notified',
                submission_id: submissionId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Submission marked as notified successfully');
            } else {
                console.error('Failed to mark submission as notified:', data.data);
            }
        })
        .catch(error => {
            console.error('Error marking submission as notified:', error);
        });
    }
    
    // Global functions for course actions
    // completeCourse function removed - courses are now automatically completed when logged
    // function completeCourse(courseId) { ... } - No longer needed
    
    function deleteCourse(courseId, event) {
        if (event) {
            event.stopPropagation();
        }
        
        if (confirm('Are you sure you want to remove this course from your CPD record?')) {
            // Find the delete button and show loading state
            const deleteBtn = event ? (event.target.closest('.delete-btn') || event.target) : document.querySelector(`[onclick*="deleteCourse(${courseId})"]`);
            if (!deleteBtn) {
                console.error('Delete button not found');
                return;
            }
            
            const originalHtml = deleteBtn.innerHTML;
            deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            deleteBtn.disabled = true;
            deleteBtn.style.background = '#6b7280';
            deleteBtn.style.cursor = 'not-allowed';
            
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
                        deleteBtn.innerHTML = '<i class="fas fa-check"></i>';
                        deleteBtn.style.background = '#10b981';
                        deleteBtn.style.color = 'white';
                        
                        // Reload data after a short delay to show the success state
                        setTimeout(() => {
                            loadRecentlyLoggedTraining();
                            loadCompletedCpdStats();
                        }, 1000);
                    } else {
                        // Show error state
                        deleteBtn.innerHTML = '<i class="fas fa-times"></i>';
                        deleteBtn.style.background = '#ef4444';
                        deleteBtn.style.color = 'white';
                        
                        // Revert after 3 seconds
                        setTimeout(() => {
                            deleteBtn.innerHTML = originalHtml;
                            deleteBtn.style.background = '#ef4444';
                            deleteBtn.style.color = 'white';
                            deleteBtn.disabled = false;
                            deleteBtn.style.cursor = 'pointer';
                        }, 3000);
                        
                        alert('Error: ' + (response.data || 'Failed to remove course'));
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
