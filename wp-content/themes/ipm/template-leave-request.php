<?php
/**
 * Template Name: Leave Request
 * 
 * @package IPM
 */

// Include required functions
include_once get_template_directory() . '/includes/global-functions.php';
include_once get_template_directory() . '/includes/leave-request-functions.php';

// Redirect if not logged in
if (!is_user_logged_in()) {
    wp_redirect(home_url('/login/'));
    exit;
}

$current_user = wp_get_current_user();
$user_id = $current_user->ID;
$is_admin = current_user_can('administrator') || current_user_can('iipm_admin');

// Middleware: Validate admin access requires tYear and user_id params
if ($is_admin) {
    $has_user_id = isset($_GET['user_id']) && !empty($_GET['user_id']);
    $has_tyear = isset($_GET['tYear']) && !empty($_GET['tYear']);
    
    // Admin must provide both parameters
    if (!$has_user_id || !$has_tyear) {
        wp_die(
            '<h1>Access Denied</h1><p>Administrators must access this page with valid <code>user_id</code> and <code>tYear</code> parameters.</p>',
            'Invalid Access',
            array('response' => 403, 'back_link' => true)
        );
    }
    
    $target_user_id = intval($_GET['user_id']);
    $target_year = intval($_GET['tYear']);
    
    // Validate user_id exists
    $target_user = get_userdata($target_user_id);
    if (!$target_user) {
        wp_die(
            '<h1>Invalid User</h1><p>The specified user ID does not exist.</p>',
            'User Not Found',
            array('response' => 404, 'back_link' => true)
        );
    }
    
    // Validate tYear is >= user's registration year
    $user_registered = $target_user->user_registered;
    $user_registration_year = date('Y', strtotime($user_registered));
    
    if ($target_year < $user_registration_year) {
        wp_die(
            '<h1>Invalid Year</h1><p>The specified year (' . $target_year . ') is before the user\'s registration year (' . $user_registration_year . ').</p>',
            'Invalid Year',
            array('response' => 400, 'back_link' => true)
        );
    }
    
    $is_admin_mode = true;
} else {
    // Regular user access - use their own ID and current/requested year
    $target_user_id = $user_id;
    $target_year = isset($_GET['tYear']) ? intval($_GET['tYear']) : date('Y');
    $is_admin_mode = false;
    $target_user = $current_user;
    
    // Get regular user's registration year
    $user_registered = $target_user->user_registered;
    $user_registration_year = date('Y', strtotime($user_registered));
}

// FORCE LOAD MAIN THEME CSS AND JQUERY
wp_enqueue_style('iipm-main-style', get_template_directory_uri() . '/assets/css/main.min.css', array(), '1.0.0');
wp_enqueue_script('jquery');

// Check if the modular header function exists, otherwise use default header
// if (function_exists('iipm_load_header')) {
//     iipm_load_header();
// } else {
//     get_header();
// }
get_header();

// Get user's leave requests for ALL years (for display in lists)
global $wpdb;
$all_leave_requests = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}test_iipm_leave_requests 
     WHERE user_id = %d 
     ORDER BY created_at DESC",
    $target_user_id
));

// Filter leave requests for the target year ONLY (for calendar blocking)
$leave_requests = array();
foreach ($all_leave_requests as $request) {
    // Extract year from leave_start_date (format: DD-MM-YYYY)
    $date_parts = explode('-', $request->leave_start_date);
    $request_year = isset($date_parts[2]) ? intval($date_parts[2]) : 0;
    
    // Only include requests from the target year for calendar
    if ($request_year === $target_year) {
        $leave_requests[] = $request;
    }
}

// Separate current and past requests with better logic (using all requests for display)
$current_requests = array();
$past_requests = array();
$current_date = date('Y-m-d');

foreach ($all_leave_requests as $request) {
    if ($request->status === 'pending') {
        // All pending requests go to "Your Leave Requests"
        $current_requests[] = $request;
    } elseif ($request->status === 'approved') {
        // Approved requests: check if they're still valid (current date is within or before the leave period)
        if ($current_date <= $request->leave_end_date) {
            $current_requests[] = $request;
        } else {
            // Approved but already ended
            $past_requests[] = $request;
        }
    } else {
        // Rejected, cancelled, or any other status goes to "Past Requests"
        $past_requests[] = $request;
    }
}
?>

<main id="primary" class="iipm-leave-request-page main-container">
    <!-- Hero Section -->
    <div class="container" style="position: relative; z-index: 2;">
        <div class="page-header" style="text-align: center; margin-bottom: 40px;">
            <div>
                <h1 style="color: white; font-size: 2.5rem; margin-bottom: 10px;">Leave Request<?php echo $is_admin_mode ? ' - Admin Mode' : ''; ?></h1>
                <p style="color: rgba(255,255,255,0.9); font-size: 1.1rem;">
                <?php 
                if ($is_admin_mode) {
                    echo 'Creating leave request for ' . esc_html($target_user->display_name);
                } else {
                    echo 'Submit and manage your leave requests';
                }
                ?>
                </p>
            </div>
        </div>
        
        <div class="leave-request-content">
            <!-- Submit Leave Request Section -->
            <div class="leave-request-submit-section">
                <div class="submit-card">
                    <h2>Submit your leave request</h2>
                    <button class="leave-request-btn" onclick="openLeaveRequestForm()">
                        Leave Request Form
                    </button>
                </div>
                
                <div class="alert alert-warning" style="margin: 0px !important;">
                    <div class="alert-icon">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.516-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="alert-content">
                        <h3 style="margin-top: 0px !important;">Warning</h3>
                        <p>If you request a leave from the course, a pro-rata adjustment will be applied based on the portion of the course completed up to the date of your request. This ensures fair and accurate allocation of course credit or fees.</p>
                    </div>
                </div>
            </div>

            <!-- Leave Requests Sections -->
            <div class="leave-requests-grid main-content" style="padding: 30px;">
                <!-- Your Leave Requests -->
                <div class="leave-requests-section">
                    <h2>Your Leave Requests</h2>
                    <div class="leave-requests-list">
                        <?php if (!empty($current_requests)): ?>
                            <?php foreach ($current_requests as $request): ?>
                                <div class="leave-request-item" onclick="openLeaveRequestDetail(<?php echo $request->id; ?>)">
                                    <div class="request-header">
                                        <span class="request-date">Requested on <?php echo date('F j, Y, g:iA', strtotime($request->created_at)); ?></span>
                                        <span class="status-badge status-<?php echo esc_attr($request->status); ?>">
                                            <?php echo ucfirst($request->status); ?>
                                        </span>
                                    </div>
                                    <h4><?php echo esc_html($request->title); ?></h4>
                                    <div class="request-details">
                                        <p><strong>Leave Date:</strong> <?php echo iipm_format_date_for_display($request->leave_start_date, 'm/d/Y'); ?> - <?php echo iipm_format_date_for_display($request->leave_end_date, 'm/d/Y'); ?></p>
                                        <p><strong>Leave Duration:</strong> <?php echo $request->duration_days; ?> days</p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-requests">
                                <p>No current leave requests found.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Past Requests -->
                <div class="leave-requests-section">
                    <h2>Past Requests</h2>
                    <div class="leave-requests-list">
                        <?php if (!empty($past_requests)): ?>
                            <?php foreach ($past_requests as $request): ?>
                                <div class="leave-request-item" onclick="openLeaveRequestDetail(<?php echo $request->id; ?>)">
                                    <div class="request-header">
                                        <span class="request-date">Requested on <?php echo date('F j, Y, g:iA', strtotime($request->created_at)); ?></span>
                                        <span class="status-badge status-<?php echo esc_attr($request->status); ?>">
                                            <?php echo ucfirst($request->status); ?>
                                        </span>
                                    </div>
                                    <h4><?php echo esc_html($request->title); ?></h4>
                                    <div class="request-details">
                                        <p><strong>Leave Date:</strong> <?php echo iipm_format_date_for_display($request->leave_start_date, 'm/d/Y'); ?> - <?php echo iipm_format_date_for_display($request->leave_end_date, 'm/d/Y'); ?></p>
                                        <p><strong>Leave Duration:</strong> <?php echo $request->duration_days; ?> days</p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-requests">
                                <p>No past leave requests found.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Helpful Links -->
            <div class="helpful-links-section">
                <h3>Helpful Links</h3>
                <div class="helpful-links">
                    <a href="#" class="helpful-link" onclick="showLeaveGuide()">
                        How to request for leave →
                    </a>
                    <a href="#" class="helpful-link" onclick="showCancelGuide()">
                        Cancel your leave request →
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Leave Request Form Modal -->
<div id="leaveRequestModal" class="modal" style="display: none;">
    <div class="modal-content leave-form-modal">
        <div class="modal-header">
            <h2>Leave Request Form</h2>
            <span class="close" onclick="closeLeaveRequestForm()">&times;</span>
        </div>
        <div class="modal-body">
            <div class="form-layout">
                <div class="calendar-section">
                    <h3>Choose date of leave</h3>
                    <div class="calendar-container">
                        <div class="calendar-header">
                            <button type="button" class="calendar-nav" id="prevMonth">&lt;</button>
                            <span class="calendar-month-year" id="currentMonthYear"></span>
                            <button type="button" class="calendar-nav" id="nextMonth">&gt;</button>
                        </div>
                        <div class="calendar-year-selector" style="text-align: center; margin: 0px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                            <select id="calendarYearSelect" style="padding: 6px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                                <!-- Populated by JavaScript -->
                            </select>
                        </div>
                        <div class="calendar-grid">
                            <div class="calendar-weekdays">
                                <div class="weekday">Mon</div>
                                <div class="weekday">Tue</div>
                                <div class="weekday">Wed</div>
                                <div class="weekday">Thu</div>
                                <div class="weekday">Fri</div>
                                <div class="weekday">Sat</div>
                                <div class="weekday">Sun</div>
                            </div>
                            <div class="calendar-days" id="calendarDays"></div>
                        </div>
                    </div>
                </div>

                <div class="form-and-calculation-section">
                    <!-- CPD Impact Display -->
                    <div class="cpd-impact-section" id="cpdImpactSection" style="display: none;margin-bottom: 10px;">
                        <div class="cpd-impact-card">
                            <h4>📊 CPD Requirements Impact</h4>
                            <div class="cpd-impact-content" id="cpdImpactContent">
                                <!-- This will be populated by JavaScript -->
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <form id="leaveRequestForm" class="leave-request-form">
                            <div class="form-group">
                                <label for="date_of_leave">Start and end of Leave</label>
                                <input type="text" id="date_of_leave" name="date_of_leave" readonly>
                            </div>
                            
                            <div class="form-group">
                                <label for="duration_of_leave">Duration of Leave</label>
                                <input type="text" id="duration_of_leave" name="duration_of_leave" readonly>
                            </div>
                            
                            <div class="form-group">
                                <label for="leave_note">Reason for Leave <span class="required">*</span></label>
                                <textarea id="leave_note" name="leave_note" rows="3" placeholder="Enter reason for leave..." required></textarea>
                            </div>
                            
                            <button type="submit" class="btn-submit-leave">Submit Leave Request</button>
                        </form>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div id="successModal" class="modal success-modal" style="display: none;">
    <div class="modal-content success-modal-content">
        <div class="success-icon">
            <svg width="60" height="60" viewBox="0 0 60 60" fill="none">
                <circle cx="30" cy="30" r="30" fill="#4ECDC4"/>
                <path d="M18 30L26 38L42 22" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <h2>Leave request submitted</h2>
        <p>Your leave request has been submitted.<br>You will be notified of its approval.</p>
        <button class="btn-ok" onclick="closeSuccessModal()">OK</button>
    </div>
</div>

<!-- Leave Request Detail Modal -->
<div id="leaveRequestDetailModal" class="modal" style="display: none;">
    <div class="modal-content leave-detail-modal">
        <div class="modal-header">
            <h2>Leave Request</h2>
            <span class="close" onclick="closeLeaveRequestDetail()">&times;</span>
        </div>
        <div class="modal-body">
            <div class="request-progress">
                <div class="progress-step active" id="step-submitted">
                    <div class="progress-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <polyline points="7,10 12,15 17,10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <line x1="12" y1="15" x2="12" y2="3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <span>Request Submitted</span>
                </div>
                <div class="progress-step" id="step-review">
                    <div class="progress-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <polyline points="14,2 14,8 20,8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <line x1="16" y1="13" x2="8" y2="13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            <line x1="16" y1="17" x2="8" y2="17" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <span>Under Review</span>
                </div>
                <div class="progress-step" id="step-approved">
                    <div class="progress-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <polyline points="20,6 9,17 4,12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <span>Approved</span>
                </div>
            </div>
            
            <div class="request-details-grid">
                <div class="detail-item">
                    <label>Submitted</label>
                    <span id="detail-submitted"></span>
                </div>
                <div class="detail-item">
                    <label>Duration of Leave</label>
                    <span id="detail-duration"></span>
                </div>
                <div class="detail-item">
                    <label>Date of Leave</label>
                    <span id="detail-dates"></span>
                </div>
                <div class="detail-item">
                    <label>Reason for Leave</label>
                    <span id="detail-note"></span>
                </div>
            </div>
            
            <div class="modal-actions">
                <button class="btn-cancel-request" id="cancelRequestBtn" onclick="cancelLeaveRequestFromDetail()">
                    Cancel request
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Confirmation Modal -->
<div id="cancelConfirmationModal" class="modal" style="display: none;">
    <div class="modal-content cancel-confirmation-modal">
        <div class="cancel-icon">
            <div class="question-mark-icon">
                <span>?</span>
            </div>
        </div>
        <h2>Cancel your leave request</h2>
        <p id="cancelConfirmationText">Confirm the cancellation of your leave request from [DATE_RANGE]. Leave request will be deleted immediately.</p>
        <div class="cancel-modal-actions">
            <button class="btn-cancel-action" onclick="closeCancelConfirmation()">Cancel</button>
            <button class="btn-confirm-cancel" onclick="confirmCancelRequest()">Confirm</button>
        </div>
    </div>
</div>

<!-- Cancel Success Modal -->
<div id="cancelSuccessModal" class="modal" style="display: none;">
    <div class="modal-content cancel-success-modal">
        <div class="cancel-success-icon">
            <div class="x-icon">
                <span>×</span>
            </div>
        </div>
        <h2>Leave request cancelled</h2>
        <p id="cancelSuccessText">Your leave request from [DATE_RANGE] has been cancelled.</p>
        <button class="btn-ok-cancel" onclick="closeCancelSuccess()">OK</button>
    </div>
</div>

<style>
/* LEAVE REQUEST PAGE STYLES */
.iipm-portal-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.iipm-portal-header {
    margin-bottom: 30px;
}

.portal-title {
    color: #8b5a96;
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0;
}

.leave-request-submit-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.submit-card {
    background: white;
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.submit-card h2 {
    color: #333;
    margin-bottom: 20px;
    font-weight: 600;
}

.alert {
    background: white !important;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    display: flex !important;
    align-items: flex-start;
    gap: 15px;
    margin-bottom: 20px;
    visibility: visible !important;
    opacity: 1 !important;
    position: relative;
    z-index: 10;
}

.alert-warning {
    border-left: 4px solid #f59e0b !important;
    background-color: #fffbeb !important;
    border: 1px solid #fbbf24 !important;
    animation: fadeIn 0.5s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.top-warning-alert {
    position: relative;
    z-index: 999 !important;
}

.top-warning-alert .alert {
    background: #fef3c7 !important;
    border: 2px solid #f59e0b !important;
    box-shadow: 0 8px 16px rgba(245, 158, 11, 0.3) !important;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { box-shadow: 0 8px 16px rgba(245, 158, 11, 0.3); }
    50% { box-shadow: 0 8px 20px rgba(245, 158, 11, 0.5); }
    100% { box-shadow: 0 8px 16px rgba(245, 158, 11, 0.3); }
}

.cpd-impact-section {
    margin-bottom: 20px;
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.cpd-impact-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.cpd-impact-card h4 {
    margin: 0 0 15px 0;
    font-size: 1.1rem;
    font-weight: 600;
}

.cpd-impact-content {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 15px;
}

.cpd-stat-item {
    text-align: center;
    background: rgba(255, 255, 255, 0.1);
    padding: 15px;
    border-radius: 8px;
    backdrop-filter: blur(10px);
}

.cpd-stat-value {
    display: block;
    font-size: 1.5rem;
    font-weight: bold;
    margin-bottom: 5px;
}

.cpd-stat-label {
    font-size: 0.85rem;
    opacity: 0.9;
}

.cpd-stat-item.positive {
    background: rgba(34, 197, 94, 0.2);
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.cpd-stat-item.negative {
    background: rgba(239, 68, 68, 0.2);
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.cpd-stat-item.primary {
    background: rgba(74, 144, 226, 0.2);
    border: 1px solid rgba(74, 144, 226, 0.3);
}

.cpd-stat-item.current {
    background: rgba(255, 193, 7, 0.2);
    border: 1px solid rgba(255, 193, 7, 0.3);
}

.alert-icon {
    flex-shrink: 0;
    margin-top: 5px;
}

.alert-warning .alert-icon svg {
    color: #f59e0b;
}

.alert-content h3 {
    color: #92400e;
    margin-bottom: 10px;
    font-weight: 600;
    font-size: 1.1rem;
}

.alert-content p {
    color: #92400e;
    line-height: 1.6;
    margin: 0;
}

.leave-request-btn {
    background: #ff6b35;
    color: white;
    border: none;
    padding: 15px 30px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    width: 100%;
    font-size: 16px;
}

.leave-request-btn:hover {
    background: #e55a2b;
    transform: translateY(-2px);
}

.required {
    color: #ef4444;
    font-weight: bold;
}

.leave-requests-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.leave-requests-section h2 {
    color: #8b5a96;
    font-size: 1.5rem;
    margin-bottom: 20px;
    font-weight: 600;
}

.leave-request-item {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 15px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    border-left: 4px solid #8b5a96;
}

.request-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.request-date {
    font-size: 12px;
    color: #666;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-approved {
    background: #d4edda;
    color: #155724;
}

.status-rejected {
    background: #f8d7da;
    color: #721c24;
}

.status-cancelled {
    background: #e2e3e5;
    color: #383d41;
}

.leave-request-item h4 {
    color: #333;
    margin: 10px 0;
    font-weight: 600;
}

.request-details p {
    margin: 5px 0;
    color: #666;
    font-size: 14px;
}

.no-requests {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}

.helpful-links-section {
    background: white;
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.helpful-links-section h3 {
    color: #333;
    margin-bottom: 20px;
    font-weight: 600;
}

.helpful-links {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.helpful-link {
    color: #8b5a96;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.3s ease;
}

.helpful-link:hover {
    color: #6d4576;
}

/* Modal Styles */
.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
}

.leave-form-modal {
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 900px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 30px;
    border-bottom: 1px solid #eee;
}

.modal-header h2 {
    margin: 0;
    color: #8b5a96;
    font-size: 1.5rem;
    font-weight: 600;
}

.close {
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    color: #aaa;
    background: none;
    border: none;
}

.close:hover {
    color: #000;
}

.modal-body {
    padding: 30px;
}

.form-layout {
    display: flex;
}

.calendar-section {
    width: 50%;
}

.form-and-calculation-section {
    width: calc(50% - 20px);
    margin-left: 20px;
}

.calendar-section h3 {
    margin-top: 0px;
    color: #333;
    margin-bottom: 20px;
    font-weight: 600;
}

.calendar-container {
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
}

.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: #f8f9fa;
    border-bottom: 1px solid #ddd;
}

.calendar-nav {
    background: none;
    border: none;
    font-size: 18px;
    cursor: pointer;
    padding: 5px 10px;
    border-radius: 4px;
    transition: background 0.3s ease;
}

.calendar-nav:hover {
    background: #e9ecef;
}

.calendar-month-year {
    font-weight: 600;
    color: #333;
}

.calendar-grid {
    padding: 15px;
}

.calendar-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 5px;
    margin-bottom: 10px;
}

.weekday {
    text-align: center;
    font-size: 12px;
    font-weight: 600;
    color: #666;
    padding: 8px 0;
}

.calendar-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 5px;
}

.calendar-day {
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    border-radius: 4px;
    font-size: 14px;
    transition: all 0.3s ease;
}

.calendar-day:hover {
    background: #f0f0f0;
}

.calendar-day.other-month {
    color: #ccc;
}

.calendar-day.selected {
    background: #8b5a96;
    color: white;
}

.calendar-day.in-range {
    background: #e8d5ea;
    color: #8b5a96;
}

.form-section {
    display: flex;
    flex-direction: column;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.3s ease;
    box-sizing: border-box;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #8b5a96;
}

.btn-submit-leave {
    background: #8b5a96;
    color: white;
    border: none;
    padding: 15px 30px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    width: 100%;
    font-size: 16px;
    margin-top: auto;
}

.btn-submit-leave:hover {
    background: #6d4576;
}

/* Success Modal */
.success-modal-content {
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 400px;
    text-align: center;
    padding: 40px 30px;
}

.success-icon {
    margin-bottom: 20px;
}

.success-modal-content h2 {
    color: #333;
    margin-bottom: 15px;
    font-size: 1.5rem;
    font-weight: 600;
}

.success-modal-content p {
    color: #666;
    line-height: 1.6;
    margin-bottom: 30px;
}

.btn-ok {
    background: #8b5a96;
    color: white;
    border: none;
    padding: 12px 40px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 16px;
}

.btn-ok:hover {
    background: #6d4576;
}

/* Responsive Design */
@media (max-width: 768px) {
    .leave-request-submit-section,
    .leave-requests-grid,
    .form-layout {
        grid-template-columns: 1fr;
    }
    
    .portal-title {
        font-size: 2rem;
    }
    
    .leave-form-modal {
        width: 95%;
        margin: 20px;
    }
}

/* Leave Request Detail Modal */
.leave-detail-modal {
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
}

.request-progress {
    display: flex;
    justify-content: space-between;
    margin-bottom: 30px;
    position: relative;
}

.request-progress::before {
    content: '';
    position: absolute;
    top: 20px;
    left: 20px;
    right: 20px;
    height: 2px;
    background: #e0e0e0;
    z-index: 1;
}

.progress-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    position: relative;
    z-index: 2;
}

.progress-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e0e0e0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #999;
    transition: all 0.3s ease;
}

.progress-step.active .progress-icon {
    background: #4ECDC4;
    color: white;
}

.progress-step.completed .progress-icon {
    background: #4ECDC4;
    color: white;
}

.progress-step span {
    font-size: 12px;
    color: #666;
    text-align: center;
    max-width: 80px;
}

.progress-step.active span {
    color: #4ECDC4;
    font-weight: 600;
}

.request-details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.detail-item label {
    font-weight: 600;
    color: #333;
    font-size: 14px;
}

.detail-item span {
    color: #666;
    font-size: 14px;
}

.modal-actions {
    text-align: center;
}

.btn-cancel-request {
    background: #dc3545;
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 14px;
}

.btn-cancel-request:hover {
    background: #c82333;
}

.btn-cancel-request:disabled {
    background: #6c757d;
    cursor: not-allowed;
}

.leave-request-item {
    cursor: pointer;
    transition: transform 0.2s ease;
}

.leave-request-item:hover {
    transform: translateY(-2px);
}

.progress-step.rejected .progress-icon {
    background: #dc3545;
    color: white;
}

.progress-step.cancelled .progress-icon {
    background: #6c757d;
    color: white;
}

.progress-step.rejected span {
    color: #dc3545;
    font-weight: 600;
}

.progress-step.cancelled span {
    color: #6c757d;
    font-weight: 600;
}

/* Cancel Confirmation Modal */
.cancel-confirmation-modal {
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 400px;
    text-align: center;
    padding: 40px 30px;
}

.cancel-icon {
    margin-bottom: 20px;
}

.question-mark-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: #dc3545;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
}

.question-mark-icon span {
    color: white;
    font-size: 30px;
    font-weight: bold;
}

.cancel-confirmation-modal h2 {
    color: #333;
    margin-bottom: 15px;
    font-size: 1.5rem;
    font-weight: 600;
}

.cancel-confirmation-modal p {
    color: #666;
    line-height: 1.6;
    margin-bottom: 30px;
    font-size: 14px;
}

.cancel-modal-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
}

.btn-cancel-action {
    background: #6c757d;
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 14px;
}

.btn-cancel-action:hover {
    background: #5a6268;
}

.btn-confirm-cancel {
    background: #dc3545;
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 14px;
}

.btn-confirm-cancel:hover {
    background: #c82333;
}

/* Cancel Success Modal */
.cancel-success-modal {
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 400px;
    text-align: center;
    padding: 40px 30px;
}

.cancel-success-icon {
    margin-bottom: 20px;
}

.x-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: #333;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
}

.x-icon span {
    color: white;
    font-size: 30px;
    font-weight: bold;
}

.cancel-success-modal h2 {
    color: #333;
    margin-bottom: 15px;
    font-size: 1.5rem;
    font-weight: 600;
}

.cancel-success-modal p {
    color: #666;
    line-height: 1.6;
    margin-bottom: 30px;
    font-size: 14px;
}

.btn-ok-cancel {
    background: #8b5a96;
    color: white;
    border: none;
    padding: 12px 40px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 16px;
}

.btn-ok-cancel:hover {
    background: #6d4576;
}
</style>

<script>
// Global AJAX configuration - Available immediately
window.iipm_ajax = {
    ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
    nonce: '<?php echo wp_create_nonce('iipm_user_nonce'); ?>',
    portal_nonce: '<?php echo wp_create_nonce('iipm_portal_nonce'); ?>'
};

// Admin mode variables
const targetUserId = <?php echo $target_user_id; ?>;
const targetYear = <?php echo $target_year; ?>;
const isAdminMode = <?php echo $is_admin_mode ? 'true' : 'false'; ?>;
const enrollmentYear = <?php echo $user_registration_year; ?>;

// Existing leave requests dates for disabling (filtered by target year)
// Using let instead of const because we update it when year changes
let existingLeaveRequests = <?php echo json_encode($leave_requests); ?>;

// Calendar functionality - Initialize calendar date
// If target year is current year, show current month; otherwise show January
const currentYear = new Date().getFullYear();
const currentMonth = new Date().getMonth();
let currentDate = targetYear === currentYear 
    ? new Date(targetYear, currentMonth, 1) 
    : new Date(targetYear, 0, 1);
    
let selectedStartDate = null;
let selectedEndDate = null;
let nonce = '<?php echo wp_create_nonce('iipm_portal_nonce'); ?>';

// Function to fetch and display CPD hours impact
function fetchAndDisplayCpdImpact() {
    if (!selectedStartDate || !selectedEndDate) {
        document.getElementById('cpdImpactSection').style.display = 'none';
        return;
    }
    
    // Show loading state
    const cpdSection = document.getElementById('cpdImpactSection');
    const cpdContent = document.getElementById('cpdImpactContent');
    cpdSection.style.display = 'block';
    cpdContent.innerHTML = '<div style="text-align: center; padding: 20px;"><span style="color: rgba(255,255,255,0.8);">Calculating CPD impact...</span></div>';
    
    // Get form data for CPD calculation
    const startDate = document.getElementById('date_of_leave').value.split(' - ')[0];
    const endDate = document.getElementById('date_of_leave').value.split(' - ')[1];
    const duration = document.getElementById('duration_of_leave').value;
    
    // Calculate days from duration string (e.g., "17 days" -> 17)
    const durationDays = duration ? parseInt(duration.replace(/[^0-9]/g, '')) : 0;
    
    // Prepare form data
    const formData = new FormData();
    formData.append('action', 'iipm_get_adjusted_cpd_hours');
    formData.append('nonce', nonce);
    formData.append('year', targetYear);
    formData.append('manual_duration', durationDays);
    if (isAdminMode) {
        formData.append('user_id', targetUserId);
    }
    
    // Fetch CPD data
    fetch(window.iipm_ajax.ajax_url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('CPD AJAX Response:', data);
        if (data.success) {
            displayCpdImpact(data.data);
        } else {
            console.error('CPD AJAX Error:', data.data);
            cpdContent.innerHTML = `<div style="text-align: center; padding: 20px;"><span style="color: rgba(255,255,255,0.8);">Error: ${data.data}</span></div>`;
        }
    })
    .catch(error => {
        console.error('Error fetching CPD impact:', error);
        cpdContent.innerHTML = '<div style="text-align: center; padding: 20px;"><span style="color: rgba(255,255,255,0.8);">Network Error</span></div>';
    });
}

// Global variable to store deducted hours
let deductedHoursFromCPD = 0;

// Function to display CPD impact data
function displayCpdImpact(data) {
    const cpdContent = document.getElementById('cpdImpactContent');
    
    const duration = Math.ceil((selectedEndDate - selectedStartDate) / (1000 * 60 * 60 * 24)) + 1;
    
    // Store the deducted hours for form submission
    deductedHoursFromCPD = Math.abs(data.difference);
    
    cpdContent.innerHTML = `
        <div class="cpd-stat-item primary">
            <span class="cpd-stat-value">${data.original_hours}</span>
            <span class="cpd-stat-label">Original Target</span>
        </div>
        <div class="cpd-stat-item current">
            <span class="cpd-stat-value">${data.current_hours}</span>
            <span class="cpd-stat-label">Current Target</span>
        </div>
        <div class="cpd-stat-item ${data.difference > 0 ? 'negative' : 'positive'}">
            <span class="cpd-stat-value">${data.adjusted_hours}</span>
            <span class="cpd-stat-label">After New Leave</span>
        </div>
        <div class="cpd-stat-item ${data.difference > 0 ? 'negative' : 'positive'}">
            <span class="cpd-stat-value">${data.difference > 0 ? '-' : '+'}${Math.abs(data.difference).toFixed(1)}</span>
            <span class="cpd-stat-label">Change from Current</span>
        </div>
        <div class="cpd-stat-item ${data.percentage_reduction > 0 ? 'negative' : ''}">
            <span class="cpd-stat-value">${duration} day${duration > 1 ? 's' : ''}</span>
            <span class="cpd-stat-label">Leave Duration</span>
        </div>
        <div class="cpd-stat-item ${data.percentage_reduction > 0 ? 'negative' : ''}">
            <span class="cpd-stat-value">${data.percentage_reduction}%</span>
            <span class="cpd-stat-label">Reduction from Current</span>
        </div>
    `;
}
let isSelectingRange = false;

document.addEventListener('DOMContentLoaded', function() {
    console.log('Leave Request JS loaded');
    
    // Initialize calendar
    initializeCalendar();
    
    // Calendar navigation
    document.getElementById('prevMonth').addEventListener('click', function() {
        const newDate = new Date(currentDate);
        newDate.setMonth(newDate.getMonth() - 1);
        
        // Check if we're still within the selected year
        const selectedYear = parseInt(document.getElementById('calendarYearSelect').value);
        if (newDate.getFullYear() >= selectedYear) {
            currentDate = newDate;
            renderCalendar();
        }
    });
    
    document.getElementById('nextMonth').addEventListener('click', function() {
        const newDate = new Date(currentDate);
        newDate.setMonth(newDate.getMonth() + 1);
        
        // Check if we're still within the selected year
        const selectedYear = parseInt(document.getElementById('calendarYearSelect').value);
        if (newDate.getFullYear() <= selectedYear) {
            currentDate = newDate;
            renderCalendar();
        }
    });
    
    // Year selector change
    document.getElementById('calendarYearSelect').addEventListener('change', function(e) {
        const selectedYear = parseInt(e.target.value);
        
        // If in admin mode (tYear parameter exists), refresh page with new year
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('tYear') && urlParams.has('user_id')) {
            // Update tYear parameter and reload
            urlParams.set('tYear', selectedYear);
            window.location.search = urlParams.toString();
            return;
        }
        
        // Regular mode (non-admin): just update calendar
        const now = new Date();
        const nowYear = now.getFullYear();
        const nowMonth = now.getMonth();
        
        // If selected year is current year, go to current month; otherwise go to January
        currentDate = selectedYear === nowYear 
            ? new Date(selectedYear, nowMonth, 1) 
            : new Date(selectedYear, 0, 1);
        
        // Clear selected dates when changing year
        selectedStartDate = null;
        selectedEndDate = null;
        document.getElementById('date_of_leave').value = '';
        document.getElementById('duration_of_leave').value = '';
        document.getElementById('cpdImpactSection').style.display = 'none';
        
        // Reload leave requests for the selected year
        loadLeaveRequestsForYear(selectedYear);
    });
});

function initializeCalendar() {
    // Populate year selector from enrollment year to current year only
    const yearSelect = document.getElementById('calendarYearSelect');
    const currentYear = new Date().getFullYear();
    const startYear = enrollmentYear;
    const endYear = currentYear;
    
    yearSelect.innerHTML = '';
    for (let year = startYear; year <= endYear; year++) {
        const option = document.createElement('option');
        option.value = year;
        option.textContent = year;
        if (year === targetYear) {
            option.selected = true;
        }
        yearSelect.appendChild(option);
    }
    
    renderCalendar();
}

// Function to load leave requests for a specific year
function loadLeaveRequestsForYear(year) {
    // Make AJAX request to get leave requests for the selected year
    const formData = new FormData();
    formData.append('action', 'iipm_get_leave_requests_by_year');
    formData.append('nonce', nonce);
    formData.append('user_id', targetUserId);
    formData.append('year', year);
    
    fetch(window.iipm_ajax.ajax_url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the existingLeaveRequests array
            existingLeaveRequests.length = 0; // Clear array
            if (data.data && data.data.length > 0) {
                existingLeaveRequests.push(...data.data);
            }
            // Re-render calendar with updated data
            renderCalendar();
        } else {
            console.error('Failed to load leave requests:', data);
            // Still render calendar even if request fails
            renderCalendar();
        }
    })
    .catch(error => {
        console.error('Error loading leave requests:', error);
        // Still render calendar even if request fails
        renderCalendar();
    });
}

function renderCalendar() {
    const monthNames = ["January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"];
    
    document.getElementById('currentMonthYear').textContent = 
        monthNames[currentDate.getMonth()];
    
    const firstDay = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
    const lastDay = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
    const startDate = new Date(firstDay);
    startDate.setDate(startDate.getDate() - (firstDay.getDay() === 0 ? 6 : firstDay.getDay() - 1));
    
    const calendarDays = document.getElementById('calendarDays');
    calendarDays.innerHTML = '';
    
    for (let i = 0; i < 42; i++) {
        const day = new Date(startDate);
        day.setDate(startDate.getDate() + i);
        
        const dayElement = document.createElement('div');
        dayElement.className = 'calendar-day';
        dayElement.textContent = day.getDate();
        
        if (day.getMonth() !== currentDate.getMonth()) {
            dayElement.classList.add('other-month');
        }
        
        // Check if date has approved leave request
        const hasExistingLeave = isDateInExistingLeave(day);
        
        // In non-admin mode, disable past dates
        // In admin mode, only disable dates with approved leave requests
        const isPastDate = day < new Date().setHours(0, 0, 0, 0);
        const shouldDisable = hasExistingLeave || (!isAdminMode && isPastDate);
        
        if (shouldDisable) {
            dayElement.style.opacity = '0.3';
            dayElement.style.cursor = 'not-allowed';
            if (hasExistingLeave) {
                dayElement.style.background = '#ffebee';
                dayElement.title = 'Date already has approved leave';
            }
        } else {
            dayElement.addEventListener('click', function() {
                selectDate(day);
            });
        }
        
        // Highlight selected dates
        if (selectedStartDate && isSameDate(day, selectedStartDate)) {
            dayElement.classList.add('selected');
        }
        if (selectedEndDate && isSameDate(day, selectedEndDate)) {
            dayElement.classList.add('selected');
        }
        if (selectedStartDate && selectedEndDate && day > selectedStartDate && day < selectedEndDate) {
            dayElement.classList.add('in-range');
        }
        
        calendarDays.appendChild(dayElement);
    }
    
    // Update navigation button states based on year boundaries
    const selectedYear = parseInt(document.getElementById('calendarYearSelect').value);
    const prevBtn = document.getElementById('prevMonth');
    const nextBtn = document.getElementById('nextMonth');
    
    // Disable prev button if at January of selected year
    if (currentDate.getMonth() === 0 && currentDate.getFullYear() === selectedYear) {
        prevBtn.disabled = true;
        prevBtn.style.opacity = '0.5';
        prevBtn.style.cursor = 'not-allowed';
    } else {
        prevBtn.disabled = false;
        prevBtn.style.opacity = '1';
        prevBtn.style.cursor = 'pointer';
    }
    
    // Disable next button if at December of selected year
    if (currentDate.getMonth() === 11 && currentDate.getFullYear() === selectedYear) {
        nextBtn.disabled = true;
        nextBtn.style.opacity = '0.5';
        nextBtn.style.cursor = 'not-allowed';
    } else {
        nextBtn.disabled = false;
        nextBtn.style.opacity = '1';
        nextBtn.style.cursor = 'pointer';
    }
}

// Helper function to check if date is in existing leave request
// Note: existingLeaveRequests is already filtered to only include requests from targetYear
// This allows admin to create leave requests for different years without interference
function isDateInExistingLeave(date) {
    if (!existingLeaveRequests || existingLeaveRequests.length === 0) {
        return false;
    }
    
    const checkDate = new Date(date);
    checkDate.setHours(0, 0, 0, 0);
    
    for (const request of existingLeaveRequests) {
        // Only check APPROVED leave requests to disable dates
        if (request.status !== 'approved') {
            continue;
        }
        
        // Parse dates (format: DD-MM-YYYY)
        const startParts = request.leave_start_date.split('-');
        const endParts = request.leave_end_date.split('-');
        
        // Create dates from DD-MM-YYYY format: [day, month, year]
        const startDate = new Date(startParts[2], startParts[1] - 1, startParts[0]);
        const endDate = new Date(endParts[2], endParts[1] - 1, endParts[0]);
        
        startDate.setHours(0, 0, 0, 0);
        endDate.setHours(0, 0, 0, 0);
        
        if (checkDate >= startDate && checkDate <= endDate) {
            return true;
        }
    }
    
    return false;
}

function selectDate(date) {
    // In admin mode, allow past dates; otherwise, block them
    if (!isAdminMode && date < new Date().setHours(0, 0, 0, 0)) return;
    
    // Check if date has existing leave
    if (isDateInExistingLeave(date)) return;
    
    if (!selectedStartDate || (selectedStartDate && selectedEndDate)) {
        // Start new selection
        selectedStartDate = new Date(date);
        selectedEndDate = null;
        isSelectingRange = true;
    } else if (selectedStartDate && !selectedEndDate) {
        // Complete the range
        if (date >= selectedStartDate) {
            selectedEndDate = new Date(date);
        } else {
            selectedEndDate = selectedStartDate;
            selectedStartDate = new Date(date);
        }
        isSelectingRange = false;
        updateFormFields();
    }
    
    renderCalendar();
}

function updateFormFields() {
    if (selectedStartDate && selectedEndDate) {
        const startStr = formatDate(selectedStartDate);
        const endStr = formatDate(selectedEndDate);
        const duration = Math.ceil((selectedEndDate - selectedStartDate) / (1000 * 60 * 60 * 24)) + 1;
        
        document.getElementById('date_of_leave').value = `${startStr} - ${endStr}`;
        document.getElementById('duration_of_leave').value = `${duration} days`;
        
        // Fetch and display CPD impact
        fetchAndDisplayCpdImpact();
    }
}

function formatDate(date) {
    return String(date.getDate()).padStart(2, '0') + '-' + 
           String(date.getMonth() + 1).padStart(2, '0') + '-' + 
           date.getFullYear();
}

function isSameDate(date1, date2) {
    return date1.getDate() === date2.getDate() &&
           date1.getMonth() === date2.getMonth() &&
           date1.getFullYear() === date2.getFullYear();
}

function openLeaveRequestForm() {
    document.getElementById('leaveRequestModal').style.display = 'flex';
    // Reset selections
    selectedStartDate = null;
    selectedEndDate = null;
    renderCalendar();
    document.getElementById('leaveRequestForm').reset();
}

function closeLeaveRequestForm() {
    document.getElementById('leaveRequestModal').style.display = 'none';
    document.getElementById('cpdImpactSection').style.display = 'none';
}

function closeSuccessModal() {
    document.getElementById('successModal').style.display = 'none';
    location.reload(); // Refresh to show new request
}

// Handle form submission
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('leaveRequestForm');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!selectedStartDate || !selectedEndDate) {
                alert('Please select your leave dates from the calendar.');
                return;
            }
            
            // Validate required field
            const note = document.getElementById('leave_note').value.trim();
            if (!note) {
                alert('Please provide a reason for your leave request.');
                document.getElementById('leave_note').focus();
                return;
            }
            
            const duration = Math.ceil((selectedEndDate - selectedStartDate) / (1000 * 60 * 60 * 24)) + 1;
            
            // Use the deducted hours calculated from CPD impact
            const deductedHours = deductedHoursFromCPD || 0;
            
            const formData = new FormData();
            formData.append('action', 'iipm_submit_leave_request');
            formData.append('nonce', nonce);
            formData.append('leave_title', `Leave Request for ${duration} days`);
            formData.append('leave_reason', 'personal');
            formData.append('leave_start_date', formatDate(selectedStartDate));
            formData.append('leave_end_date', formatDate(selectedEndDate));
            formData.append('leave_description', note);
            formData.append('hours_deduct', deductedHours);
            
            // If in admin mode, include target user_id
            if (isAdminMode) {
                formData.append('user_id', targetUserId);
            }
            
            // Disable submit button
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Submitting...';
            submitBtn.disabled = true;
            
            fetch(window.iipm_ajax.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeLeaveRequestForm();
                    document.getElementById('successModal').style.display = 'flex';
                } else {
                    alert('Error: ' + (data.data || 'Unknown error occurred'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while submitting your request. Please try again.');
            })
            .finally(() => {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        });
    }
});

// Cancel leave request function
let pendingCancelRequestId = null;

function cancelLeaveRequestFromDetail() {
    if (!currentRequestDetail) return;
    
    // Store the request ID for later use
    pendingCancelRequestId = currentRequestDetail.id;
    
    // Update the confirmation text with actual dates
    const dateRange = formatDateRangeFromStrings(currentRequestDetail.leave_start_date, currentRequestDetail.leave_end_date);
    document.getElementById('cancelConfirmationText').textContent = 
        `Confirm the cancellation of your leave request from ${dateRange}. Leave request will be deleted immediately.`;
    
    // Close the detail modal and show confirmation
    closeLeaveRequestDetail();
    document.getElementById('cancelConfirmationModal').style.display = 'flex';

}

function closeCancelConfirmation() {
    document.getElementById('cancelConfirmationModal').style.display = 'none';
    pendingCancelRequestId = null;
}

function confirmCancelRequest() {
    if (!pendingCancelRequestId) return;
    
    const formData = new FormData();
    formData.append('action', 'iipm_cancel_leave_request');
    formData.append('nonce', nonce);
    formData.append('request_id', pendingCancelRequestId);
    
    // Get the request data for success message
    const requestData = getRequestData(pendingCancelRequestId);
    const dateRange = formatDateRangeFromStrings(requestData.leave_start_date, requestData.leave_end_date);
    
    fetch(window.iipm_ajax.ajax_url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close confirmation modal
            document.getElementById('cancelConfirmationModal').style.display = 'none';
            
            // Update success message and show success modal
            document.getElementById('cancelSuccessText').textContent = 
                `Your leave request from ${dateRange} has been cancelled.`;
            document.getElementById('cancelSuccessModal').style.display = 'flex';
        } else {
            alert('Error: ' + (data.data || 'Unknown error'));
            closeCancelConfirmation();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while cancelling your request.');
        closeCancelConfirmation();
    });
}

function closeCancelSuccess() {
    document.getElementById('cancelSuccessModal').style.display = 'none';
    pendingCancelRequestId = null;
    location.reload(); // Refresh to show updated requests
}

// Helper functions
window.showLeaveGuide = function() {
    alert('Leave Request Guide:\n\n1. Click "Leave Request Form" button\n2. Select your leave dates from the calendar\n3. Add any additional notes\n4. Submit your request\n\nYour request will be reviewed by the administration team.');
};

window.showCancelGuide = function() {
    alert('Cancel Leave Request Guide:\n\n1. Find your pending request in "Your Leave Requests" section\n2. Click the "Cancel Request" button\n3. Confirm the cancellation\n\nNote: You can only cancel requests that are still pending.');
};

// Close modal when clicking outside - update the existing event listener
window.addEventListener('click', function(event) {
    const leaveModal = document.getElementById('leaveRequestModal');
    const successModal = document.getElementById('successModal');
    const cancelConfirmModal = document.getElementById('cancelConfirmationModal');
    const cancelSuccessModal = document.getElementById('cancelSuccessModal');
    
    if (event.target === leaveModal) {
        closeLeaveRequestForm();
    }
    if (event.target === successModal) {
        closeSuccessModal();
    }
    if (event.target === cancelConfirmModal) {
        closeCancelConfirmation();
    }
    if (event.target === cancelSuccessModal) {
        closeCancelSuccess();
    }
});

let currentRequestDetail = null;

function openLeaveRequestDetail(requestId) {
    // Find the request data (you'll need to pass this from PHP)
    const requestData = getRequestData(requestId);
    if (!requestData) return;
    
    currentRequestDetail = requestData;
    
    // Update modal content
    document.getElementById('detail-submitted').textContent = formatDateTime(requestData.created_at);
    document.getElementById('detail-duration').textContent = requestData.duration_days + ' days';
    document.getElementById('detail-dates').textContent = formatDateRangeFromStrings(requestData.leave_start_date, requestData.leave_end_date);
    document.getElementById('detail-note').textContent = requestData.description || 'No additional notes';
    
    // Update progress steps
    updateProgressSteps(requestData.status);
    
    // Show/hide cancel button based on status
    const cancelBtn = document.getElementById('cancelRequestBtn');
    if (requestData.status === 'pending') {
        cancelBtn.style.display = 'block';
    } else {
        cancelBtn.style.display = 'none';
    }
    
    document.getElementById('leaveRequestDetailModal').style.display = 'flex';
}

function closeLeaveRequestDetail() {
    document.getElementById('leaveRequestDetailModal').style.display = 'none';
    currentRequestDetail = null;
}

function updateProgressSteps(status) {
    // Reset all steps and restore original content
    document.querySelectorAll('.progress-step').forEach(step => {
        step.classList.remove('active', 'completed', 'rejected', 'cancelled');
    });
    
    // Reset to original content
    document.getElementById('step-approved').style.display = 'flex';
    document.querySelector('#step-review span').textContent = 'Under Review';
    document.querySelector('#step-approved span').textContent = 'Approved';
    
    // Reset icons to original
    document.querySelector('#step-review .progress-icon').innerHTML = `
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <polyline points="14,2 14,8 20,8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <line x1="16" y1="13" x2="8" y2="13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <line x1="16" y1="17" x2="8" y2="17" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
    `;
    
    document.querySelector('#step-approved .progress-icon').innerHTML = `
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
            <polyline points="20,6 9,17 4,12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    `;
    
    // Always mark submitted as completed
    document.getElementById('step-submitted').classList.add('completed');
    
    if (status === 'pending') {
        document.getElementById('step-review').classList.add('active');
    } else if (status === 'approved') {
        document.getElementById('step-review').classList.add('completed');
        document.getElementById('step-approved').classList.add('active');
    } else if (status === 'rejected') {
        document.getElementById('step-review').classList.add('completed');
        document.getElementById('step-approved').classList.add('rejected');
        // Change the text and icon for rejected state
        document.querySelector('#step-approved span').textContent = 'Rejected';
        document.querySelector('#step-approved .progress-icon').innerHTML = `
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                <path d="m15 9-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="m9 9 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        `;
    } else if (status === 'cancelled') {
        document.getElementById('step-review').classList.add('cancelled');
        // Change the text and icon for cancelled state
        document.querySelector('#step-review span').textContent = 'Cancelled';
        document.querySelector('#step-review .progress-icon').innerHTML = `
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                <path d="m15 9-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="m9 9 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        `;
        // Hide the approved step for cancelled requests
        document.getElementById('step-approved').style.display = 'none';
    }
}

function formatDateTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString() + ', ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
}

function formatDateRangeFromStrings(startDateStr, endDateStr) {
    // Handle dd-mm-yyyy format strings
    if (!startDateStr || !endDateStr) {
        return 'Invalid dates';
    }
    
    // Convert dd-mm-yyyy to readable format
    const formatDateString = (dateStr) => {
        const parts = dateStr.split('-');
        if (parts.length === 3) {
            const day = parts[0].padStart(2, '0');
            const month = parts[1].padStart(2, '0');
            const year = parts[2];
            return `${day}-${month}-${year}`;
        }
        return dateStr;
    };
    
    return formatDateString(startDateStr) + ' - ' + formatDateString(endDateStr);
}

function formatDateRange(startDate, endDate) {
    const start = new Date(startDate);
    const end = new Date(endDate);
    return String(start.getDate()).padStart(2, '0') + '-' + 
           String(start.getMonth() + 1).padStart(2, '0') + '-' + 
           start.getFullYear() + ' - ' +
           String(end.getDate()).padStart(2, '0') + '-' + 
           String(end.getMonth() + 1).padStart(2, '0') + '-' + 
           end.getFullYear();
}

// You'll need to create this function to get request data
function getRequestData(requestId) {
    // This should return the request data for the given ID
    // You might need to pass this data from PHP or make an AJAX call
    const requests = <?php echo json_encode(array_merge($current_requests, $past_requests)); ?>;
    return requests.find(request => request.id == requestId);
}
</script>

        </div>
    </div>
</main>

<?php get_footer(); ?>
