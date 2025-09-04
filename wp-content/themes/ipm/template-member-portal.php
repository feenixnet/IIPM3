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

// Get CPD stats to check if user is assigned
$cpd_stats = iipm_get_cpd_stats($current_user_id, $current_year);
$is_user_assigned = isset($cpd_stats['is_user_assigned']) ? $cpd_stats['is_user_assigned'] : false;

get_header(); 
?>

<div class="member-portal-page">
    <!-- Header -->
    <div class="portal-header">
        <div class="container">
            <div class="header-content">
                <div class="breadcrumb">
                    <a href="<?php echo home_url('/dashboard/'); ?>">🏠</a>
                    <span class="separator">></span>
                    <span>Member Portal</span>
                </div>
                <h1>Member Portal</h1>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Success Alert for Completed CPD -->
        <?php if ($is_user_assigned && !empty($cpd_stats['courses_summary'])): 
            $total_completed = 0;
            $total_required = 0;
            foreach ($cpd_stats['courses_summary'] as $category) {
                $total_completed += $category['count'];
                $total_required += $category['required'];
            }
            $is_fully_completed = ($total_completed >= $total_required && $cpd_stats['completion_percentage'] >= 100);
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
        
        <div class="portal-layout <?php echo !$is_user_assigned ? 'welcome-layout' : ''; ?>">
            <?php if (!$is_user_assigned): ?>
            <!-- Unassigned User Section -->
            <div class="cpd-welcome-panel">
                <div class="cpd-course-card">
                    <div class="unassigned-user-section" id="unassigned-user-section">
                        <div class="unassigned-content">
                            <div class="unassigned-icon">🎓</div>
                            <h3>Welcome to CPD Training</h3>
                            <p class="unassigned-description">
                                You haven't been assigned to the current CPD cycle yet. Click the button below to join and start your professional development journey.
                            </p>
                            
                            <button class="btn btn-primary assign-btn-large" id="assign-to-cpd-btn-large">
                                <span class="btn-icon">📝</span>
                                Assign to CPD
                            </button>
                            
                            <div class="logging-period-info" id="logging-period-info">
                                <div class="period-icon">📅</div>
                                <div class="period-details">
                                    <h4>Logging Period</h4>
                                    <p id="logging-period-text">Loading dates...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Left Side - My CPD Course (for assigned users) -->
            <div class="cpd-course-panel">
                <div class="cpd-course-card">
                    <h3>My CPD Course</h3>
                    
                    <button class="btn btn-primary" id="log-training-btn">Log Training</button>
                    
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
                                <span class="time-label">Remaining:</span>
                                <span class="time-value" id="remaining-minutes">330 minutes</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- <button class="btn btn-outline" id="submit-return-btn">Submit my return</button> -->
                    
                    <!-- CPD Assignment and Submission Buttons -->
                    <div class="cpd-action-buttons" id="cpd-action-buttons" style="display: none;">
                        <button class="btn btn-success" id="submit-cpd-btn">
                            <span class="btn-icon">✅</span>
                            Submit CPD
                        </button>
                    </div>
                    
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
                        <span class="alert-icon">⚠️</span>
                        <span class="alert-text" id="deadline-warning">Submit your CPD before the deadline</span>
                    </div>
                </div>
            </div>

            <!-- Right Side (for assigned users) -->
            <div class="portal-right-panel">
                <!-- Quick Links -->
                <div class="quick-links">
                    <div class="quick-link-card" id="all-courses-card">
                        <div class="card-icon">📚</div>
                        <h4>All Courses</h4>
                        <a href="<?php echo home_url('/cpd-courses/'); ?>" class="card-link">Browse courses →</a>
                    </div>
                    
                    <div class="quick-link-card">
                        <div class="card-icon">📅</div>
                        <h4>Submit Leave Request</h4>
                        <a href="#" class="card-link">Submit →</a>
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
                            <button class="btn btn-primary" id="log-first-training-btn">Log your first training</button>
                        </div>
                    </div>
                    
                    <a href="#" class="see-history-link">See training history →</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
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
                    <div class="option-icon">📚</div>
                    <h4>Pre-approved Course</h4>
                    <p>Select from our library of approved courses. Automatically approved upon submission.</p>
                </div>
                
                <div class="training-option" id="external-training-option">
                    <div class="option-icon">📝</div>
                    <h4>External Training</h4>
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
        background: #f8fafc;
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

    /* Unassigned User Section */
    .unassigned-user-section {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        border-radius: 16px;
        padding: 50px;
        text-align: center;
        margin: 0;
        border: 2px solid #e2e8f0;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }

    .unassigned-content {
        max-width: 100%;
        margin: 0 auto;
    }

    .unassigned-icon {
        font-size: 64px;
        margin-bottom: 20px;
    }

    .unassigned-content h3 {
        color: #1e293b;
        font-size: 28px;
        font-weight: 600;
        margin: 0 0 16px 0;
    }

    .unassigned-description {
        color: #64748b;
        font-size: 16px;
        line-height: 1.6;
        margin: 0 0 30px 0;
    }

    .assign-btn-large {
        padding: 16px 32px;
        font-size: 18px;
        font-weight: 600;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 30px;
        transition: all 0.3s ease;
    }

    .assign-btn-large:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(139, 90, 150, 0.3);
    }

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

    .training-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
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

    .delete-btn {
        background: #ef4444;
        color: white;
    }

    .delete-btn:hover {
        background: #dc2626;
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
    }
</style>

<script>
    // Define ajaxurl for AJAX calls
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    var isUserAssigned = <?php echo $is_user_assigned ? 'true' : 'false'; ?>;
    
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
        
        /**
         * Initialize the page
         */
        function initializePage() {
            // Load initial data
            loadCompletedCpdStats();
            
            // Load training data only for assigned users
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
            const assignBtnLarge = document.getElementById('assign-to-cpd-btn-large');
            const submitBtn = document.getElementById('submit-cpd-btn');
            
            if (assignBtn) {
                assignBtn.addEventListener('click', assignToCpd);
            }
            
            if (assignBtnLarge) {
                assignBtnLarge.addEventListener('click', assignToCpd);
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
            const assignBtnLarge = document.getElementById('assign-to-cpd-btn-large');
            const activeBtn = assignBtn || assignBtnLarge;
            
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
            formData.append('action', 'iipm_submit_cpd');
            
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
                        
                        // Show success message
                        alert('Your CPD submission was submitted successfully!');
                        
                        // Hide button after submission
                        setTimeout(() => {
                            submitBtn.style.display = 'none';
                        }, 2000);
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
                const targetHours = Math.floor(targetMinutes / 60);
                const targetMins = targetMinutes % 60;
                
                if (targetHours > 0) {
                    targetElement.textContent = `${targetHours}h ${targetMins}m`;
                } else {
                    targetElement.textContent = `${targetMins} minutes`;
                }
            }
        }
        
        /**
         * Update CPD action buttons based on period and assignment status
         */
        function updateCpdActionButtons(data) {
            const cpdActionButtons = document.getElementById('cpd-action-buttons');
            const submitBtn = document.getElementById('submit-cpd-btn');
            const unassignedSection = document.getElementById('unassigned-user-section');
            const assignBtnLarge = document.getElementById('assign-to-cpd-btn-large');

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
            
            if (!cpdActionButtons || !submitBtn || !unassignedSection || !assignBtnLarge) return;
            
            // User is assigned, show submit button if in submission period
            if (isUserAssigned && isSubmissionPeriod) {
                cpdActionButtons.style.display = 'flex';
                submitBtn.style.display = 'block';
            }
            
            // Check if user has completed all CPD requirements and show success alert
            checkAndShowSuccessAlert(data);
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
                    <span class="alert-icon">⚠️</span>
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
                const completedHours = Math.floor(completedMinutes / 60);
                const completedMins = completedMinutes % 60;
                
                if (completedHours > 0) {
                    completedElement.textContent = `${completedHours}h ${completedMins}m`;
                } else {
                    completedElement.textContent = `${completedMins} minutes`;
                }
            }
            
            // Update remaining minutes
            const remainingElement = document.getElementById('remaining-minutes');
            if (remainingElement) {
                const remainingMinutes = Math.max(0, targetMinutes - completedMinutes);
                const remainingHours = Math.floor(remainingMinutes / 60);
                const remainingMins = remainingMinutes % 60;
                
                if (remainingHours > 0) {
                    remainingElement.textContent = `${remainingHours}h ${remainingMins}m`;
                } else {
                    remainingElement.textContent = `${remainingMins} minutes`;
                }
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
                const isCompleted = item.dateOfReturn !== null;
                const actionButton = isCompleted ? 
                    '<button class="action-btn completed-btn">Completed</button>' :
                    '<button class="action-btn complete-btn" onclick="completeCourse(' + item.id + ')">Complete</button>';
                
                html += `
                    <div class="training-item">
                        <div class="training-header">
                            <h4 class="training-title">${item.courseName.charAt(0).toUpperCase() + item.courseName.slice(1)}</h4>
                            <div class="training-actions">
                                ${actionButton}
                                <button class="action-btn delete-btn" onclick="deleteCourse(${item.id})">Delete</button>
                            </div>
                        </div>
                        
                        <div class="training-meta">
                            <div class="meta-item">
                                <span class="meta-label">Category:</span>
                                <span class="meta-value">${item.hrsAndCategory.split(': ')[1] || 'N/A'}</span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Duration:</span>
                                <span class="meta-value">${item.hrsAndCategory.split(': ')[0] || 'N/A'}</span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Provider:</span>
                                <span class="meta-value">${item.crs_provider || 'N/A'}</span>
                            </div>
                        </div>
                        
                        <div class="training-date">
                            Started: ${new Date(item.dateOfCourse).toLocaleDateString()}
                            ${isCompleted ? '<br>Completed: ' + new Date(item.dateOfReturn).toLocaleDateString() : ''}
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            
            trainingContent.innerHTML = html;
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
            // Redirect to CPD courses page
            window.location.href = '<?php echo home_url('/cpd-courses/'); ?>';
        }
        
        /**
         * Select external training
         */
        function selectExternalTraining() {
            hideLogTrainingModal();
            // Show external training form or redirect
            alert('External training functionality coming soon!');
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
                        <span class="detail-value">${courseData.course_cpd_mins} minutes</span>
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
            
            // Reload training data
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
    function completeCourse(courseId) {
        if (confirm('Mark this course as completed?')) {
            // Find the complete button and show loading state
            const completeBtn = event.target;
            const originalText = completeBtn.textContent;
            completeBtn.textContent = 'Completing...';
            completeBtn.disabled = true;
            completeBtn.style.background = '#6b7280';
            
            const formData = new FormData();
            formData.append('action', 'iipm_complete_cpd_course');
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
                        completeBtn.textContent = 'Completed!';
                        completeBtn.style.background = '#10b981';
                        completeBtn.style.color = 'white';
                        
                        // Reload data after a short delay to show the success state
                        setTimeout(() => {
                        location.reload();
                        }, 1500);
                    } else {
                        // Show error state
                        completeBtn.textContent = 'Error!';
                        completeBtn.style.background = '#ef4444';
                        completeBtn.style.color = 'white';
                        
                        // Revert after 3 seconds
                        setTimeout(() => {
                            completeBtn.textContent = originalText;
                            completeBtn.style.background = '';
                            completeBtn.style.color = '';
                            completeBtn.disabled = false;
                        }, 3000);
                        
                        alert('Error: ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    // Show error state
                    completeBtn.textContent = 'Error!';
                    completeBtn.style.background = '#ef4444';
                    completeBtn.style.color = 'white';
                    
                    // Revert after 3 seconds
                    setTimeout(() => {
                        completeBtn.textContent = originalText;
                        completeBtn.style.background = '';
                        completeBtn.style.color = '';
                        completeBtn.disabled = false;
                    }, 3000);
                    
                    alert('Error completing course: ' + error);
                }
            });
        }
    }
    
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
