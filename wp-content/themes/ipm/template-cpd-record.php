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

// Include the CPD record API
require_once get_template_directory() . '/includes/cpd-record-api.php';

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
                <label for="year-select">Select Year:</label>
                <select id="year-select">
                    <?php
                    $current_year = date('Y');
                    for ($year = $current_year; $year >= $current_year - 10; $year--) {
                        $selected = ($year == $current_year) ? 'selected' : '';
                        echo "<option value='{$year}' {$selected}>{$year}</option>";
                    }
                    ?>
                </select>
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
                                <span id="current-minutes">0</span> / <span id="target-minutes">330</span> minutes
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
                                <div class="stat-label">Total CPD Minutes</div>
                                <div class="stat-value" id="total-cpd-minutes">0</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">Total Hours</div>
                                <div class="stat-value" id="total-hours">0</div>
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
                            <div class="total-value" id="summary-total">0 hours</div>
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
        gap: 15px;
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

    .summary-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #e5e7eb;
    }

    .summary-item:last-child {
        border-bottom: none;
    }

    .summary-category {
        font-weight: 500;
        color: #374151;
        display: flex;
        align-items: center;
    }

    .summary-details {
        display: flex;
        gap: 20px;
        font-size: 14px;
        color: #6b7280;
    }

    .summary-total {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 0;
        border-top: 2px solid #e5e7eb;
        margin-top: 16px;
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
        margin-right: 8px;
        font-size: 16px;
    }

    .status-completed {
        color: #059669;
    }

    .status-incomplete {
        color: #dc2626;
    }

    .completion-status {
        font-weight: 600;
        min-width: 40px;
        text-align: center;
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
        const currentMinutes = document.getElementById('current-minutes');
        const targetMinutes = document.getElementById('target-minutes');
        const startDate = document.getElementById('start-date');
        const completionDate = document.getElementById('completion-date');
        const totalCpdMinutes = document.getElementById('total-cpd-minutes');
        const totalHours = document.getElementById('total-hours');
        const summaryContent = document.getElementById('summary-content');
        const summaryTotal = document.getElementById('summary-total');
        
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
                    } else {
                        summaryContent.innerHTML = '<div class="no-data-message">Error loading stats: ' + (response.data || 'Unknown error') + '</div>';
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading CPD stats:', error);
                    summaryContent.innerHTML = '<div class="no-data-message">Error loading stats. Please try again.</div>';
                }
            });
        }
        
        /**
         * Update stats display
         */
        function updateStats(data) {
            // Update progress
            if (progressPercentage) progressPercentage.textContent = data.completion_percentage + '%';
            if (progressFill) progressFill.style.width = data.completion_percentage + '%';
            if (currentMinutes) currentMinutes.textContent = data.total_cpd_minutes;
            if (targetMinutes) targetMinutes.textContent = data.target_minutes;
            
            // Update other stats
            if (startDate) startDate.textContent = data.start_date ? formatDate(data.start_date) : '-';
            if (completionDate) completionDate.textContent = data.completion_date ? formatDate(data.completion_date) : '-';
            if (totalCpdMinutes) totalCpdMinutes.textContent = data.total_cpd_minutes;
            if (totalHours) totalHours.textContent = data.total_hours.toFixed(1);
        }
        
        /**
         * Update summary display
         */
        function updateSummary(data) {
            if (!data.courses_summary || data.courses_summary.length === 0) {
                summaryContent.innerHTML = '<div class="no-data-message">No courses completed for this year.</div>';
                if (summaryTotal) summaryTotal.textContent = '0 hours';
                return;
            }
            
            let html = '';
            data.courses_summary.forEach(item => {
                // Create completion status with check/uncheck mark
                const statusIcon = item.completed ? '✅' : '❌';
                const statusClass = item.completed ? 'status-completed' : 'status-incomplete';
                
                html += `
                    <div class="summary-item">
                        <div class="summary-category">
                            <span class="status-icon ${statusClass}">${statusIcon}</span>
                            ${item.category}
                        </div>
                        <div class="summary-details">
                            <span class="completion-status ${statusClass}">${item.status}</span>
                            <span>${item.total_minutes} mins</span>
                            <span>${item.total_hours.toFixed(1)} credits</span>
                        </div>
                    </div>
                `;
            });
            
            summaryContent.innerHTML = html;
            
            if (summaryTotal) {
                summaryTotal.textContent = data.total_hours.toFixed(1) + ' hours';
            }
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
    });
</script>

<?php get_footer(); ?>