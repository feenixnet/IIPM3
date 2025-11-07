<?php
/**
 * Template Name: CPD Record
 * 
 * CPD Record page with yearly stats and course summary
 */

// Redirect if not logged in
if (!is_user_logged_in()) {
    wp_redirect(home_url('/login/'));
    exit;
}

// Include the CPD record API and certificate functions
require_once get_template_directory() . '/includes/cpd-record-api.php';
require_once get_template_directory() . '/includes/cpd-submission-functions.php';

get_header(); 
?>

<div class="cpd-record-page main-container">
    <div class="container" style="position: relative; z-index: 2;">
        <div class="page-header" style="text-align: center; margin-bottom: 40px;">
            <div>
                <h1 style="color: white; font-size: 2.5rem; margin-bottom: 10px;">CPD Record</h1>
                <p style="color: rgba(255,255,255,0.9); font-size: 1.1rem;">
                    You can check your status of cpd with only completed courses.
                </p>
            </div>
        </div>
        <div>
            <div class="year-selector">
                <div>
                    <select id="year-select">
                        <?php
                        $current_year = date('Y');
                        $current_user_id = get_current_user_id();
                        
                        // Get user's enrollment date
                        global $wpdb;
                        $user_registered = $wpdb->get_var($wpdb->prepare(
                            "SELECT user_registered FROM {$wpdb->users} WHERE ID = %d",
                            $current_user_id
                        ));
                        
                        // Extract enrollment year
                        $enrollment_year = 2019; // Default fallback
                        if ($user_registered) {
                            $enrollment_year = (int) date('Y', strtotime($user_registered));
                        }
                        
                        // Generate years from current year down to enrollment year
                        for ($year = $current_year; $year >= $enrollment_year; $year--) {
                            $selected = ($year == $current_year) ? 'selected' : '';
                            echo "<option value='{$year}' {$selected}>{$year}</option>";
                        }
                        ?>
                    </select>
                </div>
                <button id="certificate-btn" class="certificate-btn" style="display: none;" onclick="directDownloadCertificate()">
                    <i class="fas fa-download"></i> Download Certificate
                </button>
                <div id="certificate-warning" class="certificate-warning" style="display: none;" onclick="showRequirementsTooltip(this)">
                    <i class="fas fa-exclamation-circle"></i>
                    <div id="requirements-tooltip" class="requirements-tooltip">
                        <div class="tooltip-content">
                            <h4>Certificate Requirements Not Met</h4>
                            <ul id="missed-requirements-list">
                                <!-- Requirements will be populated here -->
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="cpd-record-layout">
                <!-- Left Side - Stats -->
                <div class="cpd-stats">
                    <div class="stats-card">
                        <h3>CPD Progress</h3>
                        
                        <div class="progress-section">
                            <div class="progress-info">
                                <span class="progress-label">Progress</span>
                                <span class="progress-percentage" id="progress-percentage">0%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" id="progress-fill"></div>
                            </div>
                            <div class="progress-text">
                                <span id="current-hours">0</span> / <span id="target-hours">5.5</span> hours
                            </div>
                        </div>
                        
                        <div class="stats-grid">
                            <div class="stat-item">
                                <div class="stat-label">Start Date</div>
                                <div class="stat-value" id="start-date">-</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">Completion Date</div>
                                <div class="stat-value" id="completion-date">-</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">CPD Requirement</div>
                                <div class="stat-value" id="cpd-requirement">0</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">CPD hours logged</div>
                                <div class="stat-value" id="cpd-hours-logged">0</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Side - Courses Summary -->
                <div class="cpd-summary">
                    <div class="summary-card">
                        <h3>Courses Summary</h3>
                        
                        <div class="summary-content" id="summary-content">
                            <div class="loading-message">
                                <div class="loading-spinner">
                                    <div class="spinner"></div>
                                    <p>Loading summary...</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="summary-total">
                            <div class="total-label">Total</div>
                            <div class="total-value" id="summary-total">0</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Course List Section -->
            <div class="course-list-section">
                <div class="course-list-card">
                    <h3>Course List</h3>
                    <div class="course-list-content" id="course-list-content">
                        <div class="loading-message">
                            <div class="loading-spinner">
                                <div class="spinner"></div>
                                <p>Loading courses...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>              
        </div>
    </div>
</div>

<style>
    .cpd-record-page {
        min-height: 100vh;
        padding-top: 0;
    }

    .cpd-header {
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

    .cpd-header h1 {
        margin: 0;
        font-size: 2.5rem;
        font-weight: 600;
    }

    .year-selector {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 30px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .year-selector label {
        font-weight: 600;
        color: #374151;
        font-size: 16px;
    }

    .year-selector select {
        padding: 10px 15px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 16px;
        background: white;
        cursor: pointer;
        min-width: 120px;
    }

    .certificate-btn {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        margin-left: 15px;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .certificate-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }

    .certificate-btn i {
        font-size: 16px;
    }

    /* Certificate Warning Styles */
    .certificate-warning {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        background: #fee2e2;
        border: 2px solid #fca5a5;
        border-radius: 50%;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-left: 15px;
    }

    .certificate-warning:hover {
        background: #fecaca;
        border-color: #f87171;
        transform: scale(1.05);
    }

    .certificate-warning i {
        color: #dc2626;
        font-size: 18px;
    }

    .requirements-tooltip {
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        margin-bottom: 10px;
        background: rgb(218, 59, 59);
        border-radius: 8px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        z-index: 1000;
        min-width: 300px;
        max-width: 400px;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }

    .requirements-tooltip.show {
        opacity: 1;
        visibility: visible;
    }

    .requirements-tooltip::after {
        content: '';
        position: absolute;
        top: 100%;
        left: 50%;
        transform: translateX(-50%);
        border: 8px solid transparent;
        border-top-color:rgb(218, 59, 59);
    }

    .requirements-tooltip::before {
        content: '';
        position: absolute;
        top: 100%;
        left: 50%;
        transform: translateX(-50%);
        border: 9px solid transparent;
        border-top-color:rgb(218, 59, 59);
        margin-top: -1px;
    }

    .tooltip-content {
        padding: 20px;
    }

    .tooltip-content h4 {
        margin: 0;
        color: white;
        font-size: 16px;
        font-weight: 600;
    }

    .tooltip-content ul {
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .tooltip-content li {
        padding: 8px 0;
        color: white;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .tooltip-content li:last-child {
        border-bottom: none;
    }

    .tooltip-content li i {
        color: white;
        font-size: 12px;
        width: 16px;
        text-align: center;
    }

    .cpd-record-layout {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        align-items: start;
    }

    .cpd-stats,
    .cpd-summary {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .stats-card,
    .summary-card {
        background: white;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .stats-card h3,
    .summary-card h3 {
        margin: 0 0 20px 0;
        font-size: 1.2rem;
        font-weight: 600;
        color: #1f2937;
    }

    .progress-section {
        margin-bottom: 24px;
    }

    .progress-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }

    .progress-label {
        font-weight: 500;
        color: #374151;
    }

    .progress-percentage {
        font-weight: 600;
        color: #8b5a96;
        font-size: 1.1rem;
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
        background: linear-gradient(90deg, #8b5a96 0%, #6b4c93 100%);
        border-radius: 4px;
        transition: width 0.3s ease;
        width: 0%;
    }

    .progress-text {
        text-align: center;
        font-size: 14px;
        color: #6b7280;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .stat-item {
        text-align: center;
        padding: 16px;
        background: #f9fafb;
        border-radius: 8px;
    }

    .stat-label {
        display: block;
        font-size: 12px;
        color: #6b7280;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .stat-value {
        font-size: 18px;
        font-weight: 600;
        color: #1f2937;
    }

    .summary-content {
        margin-bottom: 20px;
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
        text-transform: uppercase;
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

    .loading-message {
        text-align: center;
        padding: 40px 20px;
    }

    .loading-spinner {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 20px;
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
        font-size: 16px;
    }

    .no-data-message {
        text-align: center;
        padding: 40px 20px;
        color: #6b7280;
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

    .completion-status {
        font-weight: 600;
        min-width: 40px;
        text-align: center;
    }

    /* Course List Styles */
    .course-list-section {
        margin-top: 30px;
    }

    .course-list-card {
        background: white;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .course-list-card h3 {
        margin: 0 0 20px 0;
        font-size: 1.2rem;
        font-weight: 600;
        color: #1f2937;
    }

    .course-list-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 16px;
    }

    .course-list-table th,
    .course-list-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #e5e7eb;
    }

    .course-list-table th {
        background: #f9fafb;
        font-weight: 600;
        color: #374151;
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .course-list-table td {
        color: #1f2937;
        font-size: 14px;
    }

    .course-list-table tr:hover {
        background: #f9fafb;
    }

    .course-name {
        font-weight: 500;
        color: #1f2937;
    }

    .course-category {
        color: #6b7280;
        font-size: 13px;
    }

    .course-hours {
        font-weight: 600;
        color: #8b5a96;
    }

    .course-date {
        color: #6b7280;
        font-size: 13px;
    }

    .course-provider {
        color: #374151;
        font-size: 13px;
    }

    /* Certificate Modal Styles */
    .modal {
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(5px);
    }

    .modal-content {
        background-color: white;
        margin: 5% auto;
        padding: 0;
        border-radius: 12px;
        width: 90%;
        max-width: 500px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        animation: modalSlideIn 0.3s ease-out;
    }

    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-50px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .modal-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
        border-radius: 12px 12px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header h3 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 600;
    }

    .close {
        color: white;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        line-height: 1;
    }

    .close:hover {
        opacity: 0.7;
    }

    .modal-body {
        padding: 30px;
    }

    .certificate-info {
        text-align: center;
    }

    .certificate-avatar {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        margin: 0 auto 20px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 48px;
        color: white;
    }

    .certificate-name {
        font-size: 1.5rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 10px;
    }

    .certificate-description {
        color: #6b7280;
        margin-bottom: 15px;
        line-height: 1.5;
    }

    .certificate-date {
        color: #9ca3af;
        font-size: 0.9rem;
        margin-bottom: 25px;
    }

    .download-certificate-btn {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        border: none;
        padding: 12px 30px;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .download-certificate-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
    }

    @media (max-width: 768px) {
        .cpd-record-layout {
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .cpd-header h1 {
            font-size: 2rem;
        }
    }
</style>

<script>
    // Define ajaxurl for AJAX calls
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    
    document.addEventListener('DOMContentLoaded', function() {
        const yearSelect = document.getElementById('year-select');
        const progressPercentage = document.getElementById('progress-percentage');
        const progressFill = document.getElementById('progress-fill');
        const currentHours = document.getElementById('current-hours');
        const targetHours = document.getElementById('target-hours');
        const startDate = document.getElementById('start-date');
        const completionDate = document.getElementById('completion-date');
        const cpdRequirement = document.getElementById('cpd-requirement');
        const cpdHoursLogged = document.getElementById('cpd-hours-logged');
        const summaryContent = document.getElementById('summary-content');
        const summaryTotal = document.getElementById('summary-total');
        const courseListContent = document.getElementById('course-list-content');
        
        // Initialize the page
        initializePage();
        
        /**
         * Initialize the page
         */
        function initializePage() {
            // Load initial data for current year
            loadCpdStats();
            
            // Set up event listeners
            setupEventListeners();
        
        }
        
        /**
         * Set up event listeners
         */
        function setupEventListeners() {
            if (yearSelect) {
                yearSelect.addEventListener('change', function() {
                    loadCpdStats();
                });
            }
        }
        
        /**
         * Load CPD stats from API
         */
        function loadCpdStats() {
            const selectedYear = yearSelect ? yearSelect.value : new Date().getFullYear();
            
            // Show loading
            summaryContent.innerHTML = `
                <div class="loading-message">
                    <div class="loading-spinner">
                        <div class="spinner"></div>
                        <p>Loading stats...</p>
                    </div>
                </div>
            `;
            
            courseListContent.innerHTML = `
                <div class="loading-message">
                    <div class="loading-spinner">
                        <div class="spinner"></div>
                        <p>Loading courses...</p>
                    </div>
                </div>
            `;
            
            // Create FormData
            const formData = new FormData();
            formData.append('action', 'iipm_get_cpd_stats');
            formData.append('year', selectedYear);
            
            // Make AJAX call
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        updateStats(response.data);
                        updateSummary(response.data);
                        updateCourseList(response.data);
                        
                        // Check certificate requirements with the fetched data
                        const selectedYear = yearSelect ? yearSelect.value : new Date().getFullYear();
                        const certificateBtn = document.getElementById('certificate-btn');
                        checkCertificateRequirements(selectedYear, certificateBtn, response.data);
                    } else {
                        summaryContent.innerHTML = '<div class="no-data-message">Error loading stats: ' + (response.data || 'Unknown error') + '</div>';
                        courseListContent.innerHTML = '<div class="no-data-message">Error loading courses: ' + (response.data || 'Unknown error') + '</div>';
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading CPD stats:', error);
                    summaryContent.innerHTML = '<div class="no-data-message">Error loading stats. Please try again.</div>';
                    courseListContent.innerHTML = '<div class="no-data-message">Error loading courses. Please try again.</div>';
                }
            });
        }
        
        /**
         * Update stats display
         */
        function updateStats(data) {
            // Convert minutes to hours (rounded to 0.5)
            const currentHoursValue = Math.round((data.total_cpd_minutes / 60) * 2) / 2;
            const targetHoursValue = Math.round((data.target_minutes / 60) * 2) / 2;
            
            // Update progress
            if (progressPercentage) progressPercentage.textContent = data.completion_percentage + '%';
            if (progressFill) progressFill.style.width = data.completion_percentage + '%';
            if (currentHours) currentHours.textContent = currentHoursValue;
            if (targetHours) targetHours.textContent = targetHoursValue;
            
            // Update other stats
            if (startDate) startDate.textContent = data.start_date ? formatDate(data.start_date) : '-';
            if (completionDate) completionDate.textContent = data.completion_date ? formatDate(data.completion_date) : '-';
            if (cpdRequirement) cpdRequirement.textContent = targetHoursValue + ' hours';
            if (cpdHoursLogged) cpdHoursLogged.textContent = currentHoursValue + ' hours';
        }
        
        /**
         * Update summary display
         */
        function updateSummary(data) {
            if (!data.courses_summary || data.courses_summary.length === 0) {
                summaryContent.innerHTML = '<div class="no-data-message">No courses completed for this year.</div>';
                if (summaryTotal) summaryTotal.textContent = '0';
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
                // Calculate hours from minutes
                const hours = Math.round((item.total_minutes / 60) * 2) / 2; // Round to nearest 0.5
                const progressText = `${hours} / 1`;
                const statusClass = hours >= 1 ? 'status-completed' : 'status-incomplete';
                const statusIcon = hours >= 1 ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-times-circle"></i>';
                
                html += `
                    <tr>
                        <td class="summary-category">
                            <span class="status-icon ${statusClass}">${statusIcon}</span>
                            ${item.category}
                        </td>
                        <td class="completion-status ${statusClass}">${progressText}</td>
                        <td class="summary-hours">${hours}</td>
                    </tr>
                `;
            });
            
            html += `
                    </tbody>
                </table>
            `;
            
            summaryContent.innerHTML = html;
            
            if (summaryTotal) {
                summaryTotal.textContent = data.total_hours.toFixed(1);
            }
        }
        
        /**
         * Update course list display
         */
        function updateCourseList(data) {
            if (!data.courses_summary || data.courses_summary.length === 0) {
                courseListContent.innerHTML = '<div class="no-data-message">No courses logged for this year.</div>';
                return;
            }
            
            let html = `
                <table class="course-list-table">
                    <thead>
                        <tr>
                            <th>Course Name</th>
                            <th>Category</th>
                            <th>Hours</th>
                            <th>Return Date</th>
                            <th>Provider</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            // Extract courses from courses_summary array
            data.courses_summary.forEach(categoryData => {
                if (categoryData.courses && categoryData.courses.length > 0) {
                    categoryData.courses.forEach(course => {
                        // Format return date
                        const returnDate = course.dateOfReturn ? formatDate(course.dateOfReturn) : '-';
                        
                        html += `
                            <tr>
                                <td class="course-name">${course.courseName || 'N/A'}</td>
                                <td class="course-category">${categoryData.category || 'N/A'}</td>
                                <td class="course-hours">${course.hours || 0} hours</td>
                                <td class="course-date">${returnDate}</td>
                                <td class="course-provider">${course.crs_provider || 'N/A'}</td>
                            </tr>
                        `;
                    });
                }
            });
            
            html += `
                    </tbody>
                </table>
            `;
            
            courseListContent.innerHTML = html;
        }
        
        /**
         * Format date for display
         */
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
        }

        // Certificate functionality - make functions global
        window.directDownloadCertificate = function() {
            const selectedYear = yearSelect ? yearSelect.value : new Date().getFullYear();
            const certificateBtn = document.getElementById('certificate-btn');
            const originalText = certificateBtn.innerHTML;
            
            // Show loading state
            certificateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating PDF...';
            certificateBtn.disabled = true;
            
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
                    
                    // Reset button after delay
                    setTimeout(() => {
                        certificateBtn.innerHTML = originalText;
                        certificateBtn.disabled = false;
                    }, 2000);
                } else {
                    alert('No certificate available for this year.');
                    certificateBtn.innerHTML = originalText;
                    certificateBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error downloading certificate:', error);
                alert('Error downloading certificate. Please try again.');
                certificateBtn.innerHTML = originalText;
                certificateBtn.disabled = false;
            });
        };

        window.showCertificateModal = function() {
            const selectedYear = yearSelect ? yearSelect.value : new Date().getFullYear();
            loadCertificateData(selectedYear);
        };

        window.closeCertificateModal = function() {
            document.getElementById('certificateModal').style.display = 'none';
        };

        function loadCertificateData(year) {
            const modal = document.getElementById('certificateModal');
            const modalBody = document.getElementById('certificateModalBody');
            
            // Show modal with loading state
            modal.style.display = 'block';
            modalBody.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #667eea;"></i><br><br>Loading certificate information...</div>';

            // Fetch certificate data
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'iipm_get_certificate_data',
                    year: year,
                    nonce: '<?php echo wp_create_nonce("iipm_certificate_nonce"); ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.certificate) {
                    displayCertificateInfo(data.data);
                } else {
                    modalBody.innerHTML = '<div style="text-align: center; padding: 40px; color: #6b7280;"><i class="fas fa-exclamation-circle" style="font-size: 48px; margin-bottom: 20px;"></i><br>No certificate available for this year.</div>';
                }
            })
            .catch(error => {
                console.error('Error loading certificate:', error);
                modalBody.innerHTML = '<div style="text-align: center; padding: 40px; color: #dc2626;"><i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 20px;"></i><br>Error loading certificate information.</div>';
            });
        }

        function displayCertificateInfo(data) {
            const modalBody = document.getElementById('certificateModalBody');
            const certificate = data.certificate;
            const user = data.user;
            
            // Handle avatar display
            const avatarHtml = certificate.avatar_url 
                ? `<img src="${certificate.avatar_url}" alt="Certificate Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">`
                : `<i class="fas fa-certificate"></i>`;
            
            modalBody.innerHTML = `
                <div class="certificate-info">
                    <div class="certificate-avatar">
                        ${avatarHtml}
                    </div>
                    <div class="certificate-name">${certificate.name}</div>
                    <div class="certificate-description">${certificate.description || 'Professional Development Certificate'}</div>
                    <button class="download-certificate-btn" onclick="downloadCertificate(${certificate.id}, '${user.name}', '${user.email}', '${user.contact_address}', '${data.year}')">
                        <i class="fas fa-download"></i> Download Certificate
                    </button>
                </div>
            `;
        }

        window.downloadCertificate = function(certificateId, userName, userEmail, contactAddress, submissionYear) {
            const downloadBtn = event.target;
            const originalText = downloadBtn.innerHTML;
            downloadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating PDF...';
            downloadBtn.disabled = true;
            
            const params = new URLSearchParams({
                action: 'iipm_download_certificate_direct',
                certificate_id: certificateId,
                user_name: userName,
                user_email: userEmail,
                contact_address: contactAddress,
                submission_year: submissionYear
            });
            
            const downloadUrl = `${ajaxurl}?${params.toString()}`;
            
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            setTimeout(() => {
                downloadBtn.innerHTML = originalText;
                downloadBtn.disabled = false;
            }, 2000);
        };

        // Check for certificate availability when year changes
        function checkCertificateAvailability() {
            const selectedYear = yearSelect ? yearSelect.value : new Date().getFullYear();
            const certificateBtn = document.getElementById('certificate-btn');
            const certificateWarning = document.getElementById('certificate-warning');
            
            // First check if CPD is submitted
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
                if (data.success && data.data.has_certificate) {
                    // Requirements will be checked in loadCpdStats
                    // Just ensure warning is hidden initially
                    certificateWarning.style.display = 'none';
                } else {
                    certificateBtn.style.display = 'none';
                    certificateWarning.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error checking certificate availability:', error);
                certificateBtn.style.display = 'none';
                certificateWarning.style.display = 'none';
            });
        }

        // Check certificate requirements using already fetched data
        function checkCertificateRequirements(year, certificateBtn, cpdStats) {
            const certificateWarning = document.getElementById('certificate-warning');
            const missedRequirements = [];
            
            // Check if progress is >= 100%
            const progressRequirement = cpdStats.completion_percentage >= 100;
            if (!progressRequirement) {
                missedRequirements.push({
                    icon: 'fas fa-chart-line',
                    text: `Overall progress must be 100% or higher (currently ${cpdStats.completion_percentage}%)`
                });
            }
            
            // Check if each category has at least 1 hour
            let categoryRequirement = true;
            const incompleteCategories = [];
            if (cpdStats.courses_summary && cpdStats.courses_summary.length > 0) {
                cpdStats.courses_summary.forEach(item => {
                    const hours = Math.round((item.total_minutes / 60) * 2) / 2;
                    if (hours < 1) {
                        categoryRequirement = false;
                        incompleteCategories.push(`${item.category} (${hours} hours)`);
                    }
                });
            } else {
                categoryRequirement = false;
                missedRequirements.push({
                    icon: 'fas fa-list',
                    text: 'No courses completed in any category'
                });
            }
            
            if (!categoryRequirement && incompleteCategories.length > 0) {
                missedRequirements.push({
                    icon: 'fas fa-list',
                    text: `Each category must have at least 1 hour: ${incompleteCategories.join(', ')}`
                });
            }
            
            // For 2025 and above, submission is required. For 2024 and earlier, it's not required.
            const numericYear = parseInt(year, 10);
            const submittedRequirement = numericYear >= 2025 ? (cpdStats.submission_data.submitted === true) : true;

            if (!submittedRequirement && numericYear >= 2025) {
                missedRequirements.push({
                    icon: 'fas fa-exclamation-circle',
                    text: 'CPD must be submitted'
                });
            }
            
            // Show certificate button only if requirements are met (submission required only for 2025+)
            if (progressRequirement && categoryRequirement && submittedRequirement) {
                certificateBtn.style.display = 'inline-flex';
                certificateWarning.style.display = 'none';
            } else {
                certificateBtn.style.display = 'none';
                certificateWarning.style.display = 'inline-flex';
                
                // Store missed requirements for popup
                certificateWarning.setAttribute('data-requirements', JSON.stringify(missedRequirements));
            }

            console.log(missedRequirements);

            if (missedRequirements.length > 0) {
                document.getElementById('certificate-btn').style.display = 'none';
                document.getElementById('certificate-warning').style.display = 'inline-flex';
            } else {
                document.getElementById('certificate-btn').style.display = 'inline-flex';
                document.getElementById('certificate-warning').style.display = 'none';
            }
        }

        // Populate the requirements tooltip with missed requirements
        function populateRequirementsTooltip(missedRequirements) {
            const requirementsList = document.getElementById('missed-requirements-list');
            
            if (missedRequirements.length === 0) {
                requirementsList.innerHTML = '<li><i class="fas fa-check"></i> All requirements met!</li>';
            } else {
                let html = '';
                missedRequirements.forEach(requirement => {
                    html += `
                        <li>
                            <i class="${requirement.icon}" style="margin-right: 10px; margin-top: 6px;"></i>
                            ${requirement.text}
                        </li>
                    `;
                });
                requirementsList.innerHTML = html;
            }
        }

        // Show requirements tooltip on click
        window.showRequirementsTooltip = function(el) {
            const tooltip = document.getElementById('requirements-tooltip');
            const requirementsData = (el && el.getAttribute('data-requirements')) || '';
            
            if (requirementsData) {
                const missedRequirements = JSON.parse(requirementsData);
                populateRequirementsTooltip(missedRequirements);
                
                // Toggle tooltip visibility
                tooltip.classList.toggle('show');
                
                // Close tooltip when clicking outside
                setTimeout(() => {
                    document.addEventListener('click', function closeTooltip(e) {
                        if (!e.target.closest('.certificate-warning')) {
                            tooltip.classList.remove('show');
                            document.removeEventListener('click', closeTooltip);
                        }
                    });
                }, 100);
            }
        };
    });
</script>

<!-- Certificate Modal -->
<div id="certificateModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Certificate Information</h3>
            <span class="close" onclick="closeCertificateModal()">&times;</span>
        </div>
        <div class="modal-body" id="certificateModalBody">
            <!-- Certificate content will be loaded here -->
        </div>
    </div>
</div>

<?php get_footer(); ?>