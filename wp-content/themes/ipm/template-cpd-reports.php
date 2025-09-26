<?php
/*
Template Name: CPD Reports & Analytics
*/

// Security check - only allow admins
if (!is_user_logged_in() || !current_user_can('administrator')) {
    wp_redirect(home_url('/login/'));
    exit;
}

// Include the CPD reporting functions for admin functions
require_once get_template_directory() . '/includes/cpd-reporting-functions.php';

get_header();

// Get reporting data
global $wpdb;
$current_year = date('Y');

// Get current year from CPD types table
$cpd_types = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}cpd_types ORDER BY id ASC");
$cpd_year = $current_year; // Default fallback

if (!empty($cpd_types)) {
    // Get the primary CPD type or first one to determine the current year
    $primary_type = null;
    foreach ($cpd_types as $type) {
        if ($type->{'Is primary CPD Type'} == 1) {
            $primary_type = $type;
            break;
        }
    }
    if (!$primary_type && !empty($cpd_types)) {
        $primary_type = $cpd_types[0];
    }
    
    if ($primary_type && $primary_type->{'Start of logging date'}) {
        $start_date = $primary_type->{'Start of logging date'};
        $cpd_year = date('Y', strtotime($start_date));
    }
}

$selected_year = isset($_GET['cpd_year']) ? intval($_GET['cpd_year']) : $cpd_year;
$selected_report = isset($_GET['cpd_report']) ? sanitize_text_field($_GET['cpd_report']) : 'compliance';

// Get overall statistics
$stats = iipm_get_cpd_compliance_stats($selected_year);
?>

<style>
/* Report card styling */
.report-card.active {
    border: 2px solid #667eea !important;
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15) !important;
    transform: translateY(-2px) !important;
}

/* Ensure button text is visible */
.report-card button {
    color: white !important;
}

/* Smooth transitions */
.report-card {
    transition: all 0.3s ease !important;
}

/* Loading spinner animation */
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.loading-spinner {
    animation: spin 1s linear infinite;
}
</style>

<div class="cpd-reports-page" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding-top: 140px;padding-bottom: 20px;">
    <div class="container" style="max-width: 1400px; margin: 0 auto; padding: 0 20px;">
        
        <!-- Page Header -->
        <div class="page-header" style="text-align: center; margin-bottom: 40px;">
            <h1 style="color: white; font-size: 2.5rem; margin-bottom: 10px;">CPD Reports & Analytics</h1>
            <p style="color: rgba(255,255,255,0.9); font-size: 1.1rem;">
                Monitor member compliance and track CPD progress across the organization
            </p>
            
            <!-- Year Selector -->
            <div style="margin-top: 20px;">
                <select id="year-selector" onchange="changeYear(this.value)" 
                        style="background: white; padding: 10px 15px; border-radius: 8px; border: none; font-size: 16px;">
                    <?php 
                    // Get available years from CPD types
                    $available_years = array();
                    foreach ($cpd_types as $type) {
                        if ($type->{'Start of logging date'}) {
                            $year = date('Y', strtotime($type->{'Start of logging date'}));
                            if (!in_array($year, $available_years)) {
                                $available_years[] = $year;
                            }
                        }
                    }
                    
                    // Sort years descending
                    rsort($available_years);
                    
                    // If no years from CPD types, use current year
                    if (empty($available_years)) {
                        $available_years = array($current_year);
                    }
                    
                    foreach ($available_years as $year): 
                    ?>
                        <option value="<?php echo $year; ?>" <?php selected($year, $selected_year); ?>>
                            <?php echo $year; ?> CPD Year
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Overview Statistics -->
        <div class="stats-overview" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 40px;">
            <div style="background: white; padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                <div style="font-size: 2.5rem; color: #059669; margin-bottom: 10px;">📊</div>
                <h3 style="margin: 0 0 5px 0; color: #374151;">Total Members</h3>
                <p style="font-size: 2rem; font-weight: bold; color: #059669; margin: 0;"><?php echo $stats['total_members']; ?></p>
            </div>
            
            <div style="background: white; padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                <div style="font-size: 2.5rem; color: #10b981; margin-bottom: 10px;">✅</div>
                <h3 style="margin: 0 0 5px 0; color: #374151;">Compliant</h3>
                <p style="font-size: 2rem; font-weight: bold; color: #10b981; margin: 0;"><?php echo $stats['compliant_members']; ?></p>
                <small style="color: #6b7280;"><?php echo round(($stats['compliant_members'] / max($stats['total_members'], 1)) * 100, 1); ?>%</small>
            </div>
            
            <div style="background: white; padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                <div style="font-size: 2.5rem; color: #f59e0b; margin-bottom: 10px;">⚠️</div>
                <h3 style="margin: 0 0 5px 0; color: #374151;">At Risk</h3>
                <p style="font-size: 2rem; font-weight: bold; color: #f59e0b; margin: 0;"><?php echo $stats['at_risk_members']; ?></p>
                <small style="color: #6b7280;"><?php echo round(($stats['at_risk_members'] / max($stats['total_members'], 1)) * 100, 1); ?>%</small>
            </div>
            
            <div style="background: white; padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                <div style="font-size: 2.5rem; color: #ef4444; margin-bottom: 10px;">❌</div>
                <h3 style="margin: 0 0 5px 0; color: #374151;">Non-Compliant</h3>
                <p style="font-size: 2rem; font-weight: bold; color: #ef4444; margin: 0;"><?php echo $stats['non_compliant_members']; ?></p>
                <small style="color: #6b7280;"><?php echo round(($stats['non_compliant_members'] / max($stats['total_members'], 1)) * 100, 1); ?>%</small>
            </div>
        </div>

        <!-- Report Type Selection -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 40px;" id="report-cards-container">
            
            <!-- Compliance Report -->
            <div class="report-card" id="compliance-card" style="background: white; border-radius: 15px; padding: 25px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: 2px solid transparent; transition: all 0.3s ease;">
                <h3 style="color: #374151; margin-bottom: 15px;">Compliance Report</h3>
                <p style="color: #6b7280; margin-bottom: 20px;">View member CPD compliance status</p>
                <button onclick="showComplianceReport()" 
                        style="background: #667eea; color: white !important; padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.3s ease;">
                    Generate Report
                </button>
            </div>
            
            <!-- Course Popularity -->
            <div class="report-card" id="popularity-card" style="background: white; border-radius: 15px; padding: 25px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: 2px solid transparent; transition: all 0.3s ease;">
                <h3 style="color: #374151; margin-bottom: 15px;">Course Popularity</h3>
                <p style="color: #6b7280; margin-bottom: 20px;">Most popular CPD courses</p>
                <button onclick="showCoursePopularity()" 
                        style="background: #10b981; color: white !important; padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.3s ease;">
                    Generate Report
                </button>
            </div>
            
            <!-- Provider Analysis -->
            <div class="report-card" id="provider-card" style="background: white; border-radius: 15px; padding: 25px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: 2px solid transparent; transition: all 0.3s ease;">
                <h3 style="color: #374151; margin-bottom: 15px;">Provider Analysis</h3>
                <p style="color: #6b7280; margin-bottom: 20px;">CPD points by provider</p>
                <button onclick="showProviderAnalysis()" 
                        style="background: #f59e0b; color: white !important; padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.3s ease;">
                    Generate Report
                </button>
            </div>
        </div>

        <!-- Main Content Area -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
            
            <!-- Left Column - Reports -->
            <div style="background: white; border-radius: 20px; padding: 30px; box-shadow: 0 20px 40px rgba(0,0,0,0.1);">
                
                <!-- Report Tabs -->
                <div class="report-tabs" style="border-bottom: 2px solid #e5e7eb; margin-bottom: 30px;">
                    <div style="display: flex; gap: 20px;">
                        <button class="tab-btn active" onclick="showReport('compliance')" 
                                style="padding: 15px 20px; border-top: none; border-right: none; border-left: none; border-image: initial; background: none; color: rgb(102, 126, 234); border-bottom: 3px solid rgb(102, 126, 234); font-weight: 600; cursor: pointer;">
                            Compliance Overview
                        </button>
                        <button class="tab-btn" onclick="showReport('categories')" 
                                style="padding: 15px 20px; border: none; background: none; color: #6b7280; font-weight: 600; cursor: pointer;">
                            By Categories
                        </button>
                        <button class="tab-btn" onclick="showReport('members')" 
                                style="padding: 15px 20px; border: none; background: none; color: #6b7280; font-weight: 600; cursor: pointer;">
                            Member Details
                        </button>
                    </div>
                </div>

                <!-- Compliance Report -->
                <div id="compliance-report" class="report-content">
                    <h3 style="color: #374151; margin-bottom: 20px;">📋 Member Compliance Status</h3>
                    
                    <!-- Compliance Tab Bar -->
                    <div style="margin-bottom: 20px;">
                        <div style="display: flex; border-bottom: 2px solid #e5e7eb; margin-bottom: 20px;">
                            <button id="compliant-tab" onclick="switchComplianceTab('compliant')" style="padding: 12px 24px; border: none; background: #10b981; color: white; font-weight: 600; cursor: pointer; border-radius: 8px 8px 0 0; margin-right: 4px;">
                                ✅ Compliant Members
                            </button>
                            <button id="non-compliant-tab" onclick="switchComplianceTab('non-compliant')" style="padding: 12px 24px; border: none; background: #6b7280; color: white; font-weight: 600; cursor: pointer; border-radius: 8px 8px 0 0; margin-right: 4px;">
                                ❌ Non-Compliant Members
                            </button>
                        </div>
                        
                        <!-- High Risk Filter (only for non-compliant tab) -->
                        <div id="high-risk-filter" style="margin-bottom: 15px; display: none;">
                            <label style="display: flex; align-items: center; gap: 8px; font-weight: 500; color: #374151;">
                                <input type="checkbox" id="show-high-risk-only" onchange="toggleHighRiskFilter()" style="width: 16px; height: 16px;">
                                Show High Risk Only (0% progress, No course trained, Not assigned)
                            </label>
                        </div>
                    </div>
                    
                    <!-- Compliance Members Table -->
                        <div style="overflow-x: auto; border: 1px solid #e5e7eb; border-radius: 8px;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead style="background: #f8fafc;">
                                    <tr>
                                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Member</th>
                                        <th style="padding: 12px; text-align: center; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Points Earned</th>
                                        <th style="padding: 12px; text-align: center; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Required</th>
                        <th style="padding: 12px; text-align: center; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Progress</th>
                                        <th style="padding: 12px; text-align: center; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Actions</th>
                                    </tr>
                                </thead>
                            <tbody id="compliance-table">
                                    <!-- Will be loaded via AJAX -->
                                    <tr>
                                        <td colspan="5" style="padding: 20px; text-align: center; color: #6b7280;">
                                            <div class="loading-spinner" style="display: inline-block; width: 20px; height: 20px; border: 2px solid #e5e7eb; border-top: 2px solid #667eea; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                                            Loading compliance data...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                    </div>

                    <!-- Pagination for Compliance Members -->
                    <div id="compliance-pagination" style="margin-top: 15px; display: flex; justify-content: center; align-items: center; gap: 10px;">
                        <!-- Pagination will be loaded here -->
                    </div>
                </div>

                <!-- Categories Report -->
                <div id="categories-report" class="report-content" style="display: none;">
                    <h3 style="color: #374151; margin-bottom: 20px;">📊 CPD by Categories</h3>
                    <div id="categories-chart" style="height: 400px; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px;">
                        <!-- Chart will be rendered here -->
                        <p style="text-align: center; color: #6b7280; margin-top: 150px;">Category analytics chart will be displayed here</p>
                    </div>
                </div>

                <!-- Members Report -->
                <div id="members-report" class="report-content" style="display: none;">
                    <h3 style="color: #374151; margin-bottom: 20px;">👥 Member Details</h3>
                    
                    <!-- Report Type Selection -->
                    <div style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center;">
                        <label style="font-weight: 600; color: #374151;">Report Type:</label>
                        <select id="member-report-type" onchange="loadMemberDetails()" 
                                style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; background: white;">
                            <option value="individual">Individual Member Reports</option>
                            <option value="employed" selected>Employed Member Reports</option>
                        </select>
                    </div>
                    
                    <!-- Members Table -->
                    <div style="background: white; border-radius: 12px; overflow: hidden; border: 1px solid #e5e7eb;">
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead style="background: #f8fafc;">
                                    <tr>
                                        <th style="padding: 15px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Member</th>
                                        <th style="padding: 15px; text-align: center; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Role</th>
                                        <th style="padding: 15px; text-align: center; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Points Earned</th>
                                        <th style="padding: 15px; text-align: center; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Required</th>
                                        <th style="padding: 15px; text-align: center; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">CPD Progress</th>
                                        <th style="padding: 15px; text-align: center; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Compliance Status</th>
                                        <th style="padding: 15px; text-align: center; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="members-table-body">
                                    <!-- Loading state -->
                                    <tr>
                                        <td colspan="7" style="padding: 40px; text-align: center; color: #6b7280;">
                                            <div class="loading-spinner" style="display: inline-block; width: 24px; height: 24px; border: 3px solid #e5e7eb; border-top: 3px solid #667eea; border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 15px;"></div>
                                            <div>Loading members...</div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Pagination for Member Details -->
                    <div id="member-pagination" style="margin-top: 20px; display: flex; justify-content: center; align-items: center; gap: 10px;">
                        <!-- Pagination will be loaded here -->
                    </div>
                    
                    <!-- Individual Report Content -->
                    <div id="individual-report-content" style="display: none; margin-top: 30px;">
                        <!-- Report content will be displayed here when a member is selected -->
                    </div>
                </div>
            </div>

            <!-- Right Column - Quick Actions -->
            <div style="display: flex; flex-direction: column; gap: 20px;">
                
                <!-- Export Reports -->
                <div style="background: white; border-radius: 15px; padding: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                    <h3 style="color: #374151; margin-bottom: 20px;">📥 Export Reports</h3>
                    
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <button onclick="exportReport('compliance')" 
                                style="background: #059669; color: white; padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                            📊 Export Compliance Report
                        </button>
                        
                        <button onclick="exportReport('non-compliant')" 
                                style="background: #ef4444; color: white; padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                            ❌ Export Non-Compliant List
                        </button>
                        
                        <button onclick="exportReport('all-members')" 
                                style="background: #667eea; color: white; padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                            👥 Export All Members
                        </button>
                        
                        <button onclick="exportReport('categories')" 
                                style="background: #f59e0b; color: white; padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                            📈 Export Category Stats
                        </button>
                    </div>
                </div>

                <!-- Send Reminders -->
                <div style="background: white; border-radius: 15px; padding: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                    <h3 style="color: #374151; margin-bottom: 20px;">📧 Send Reminders</h3>
                    
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <button onclick="sendReminders('non-compliant')" 
                                style="background: #ef4444; color: white; padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                            📮 Remind Non-Compliant
                        </button>
                        
                        <button onclick="sendReminders('at-risk')" 
                                style="background: #f59e0b; color: white; padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                            ⚠️ Remind At-Risk Members
                        </button>
                        
                        <button onclick="sendReminders('all')" 
                                style="background: #6b7280; color: white; padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                            📨 Send General Reminder
                        </button>
                    </div>
                    
                    <div id="reminder-status" style="margin-top: 15px; padding: 10px; border-radius: 8px; display: none;"></div>
                </div>

                <!-- Quick Stats -->
                <div style="background: white; border-radius: 15px; padding: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                    <h3 style="color: #374151; margin-bottom: 20px;">⚡ Quick Stats</h3>
                    
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; background: #f8fafc; border-radius: 8px;">
                            <span style="color: #374151; font-weight: 500;">Average CPD Points:</span>
                            <span style="color: #667eea; font-weight: bold;"><?php echo number_format($stats['average_points'], 2); ?></span>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; background: #f8fafc; border-radius: 8px;">
                            <span style="color: #374151; font-weight: 500;">Total CPD Logged:</span>
                            <span style="color: #059669; font-weight: bold;"><?php echo number_format($stats['total_cpd_logged']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Global variables
let currentYear = <?php echo $selected_year; ?>;
let currentReportData = null; // Store current report data for CSV export

// Make sure this is globally accessible
window.iipm_reports_ajax = {
    ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
    nonce: '<?php echo wp_create_nonce('iipm_reports_nonce'); ?>'
};

// Debug: Check if the object is properly created
console.log('🌍 Global AJAX object created:', window.iipm_reports_ajax);
console.log('🔑 AJAX URL:', window.iipm_reports_ajax.ajax_url);
console.log('🎫 Nonce:', window.iipm_reports_ajax.nonce);

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Check if we have a report parameter in URL
    const urlParams = new URLSearchParams(window.location.search);
    const reportType = urlParams.get('cpd_report');
    
    if (reportType) {
        switch(reportType) {
            case 'compliance':
                showComplianceReport();
                console.log('🎯 About to call loadComplianceCounts...');
                loadComplianceCounts(); // Load counts for tab buttons via AJAX
                
                // Ensure compliant tab is active by default
                console.log('🎯 Setting compliant tab as active by default');
                switchComplianceTab('compliant');
                break;
            case 'popularity':
                showCoursePopularity();
                break;
            case 'provider':
                showProviderAnalysis();
                break;
            case 'members':
                showReport('members');
                break;
            default:
                // Default to compliance report
                setActiveReportCard('compliance');
                loadComplianceData();
        }
    } else {
        // Default to compliance report highlighting
        setActiveReportCard('compliance');
        console.log('🎯 About to call loadComplianceCounts (default)...');
        loadComplianceCounts(); // Load counts for tab buttons via AJAX
        loadComplianceData();
        
        // Ensure compliant tab is active by default
        console.log('🎯 Setting compliant tab as active by default');
        switchComplianceTab('compliant');
    }
});

// Change year
function changeYear(year) {
    const url = new URL(window.location);
    url.searchParams.set('cpd_year', year);
    
    // If no report parameter, set compliance as default
    if (!url.searchParams.has('cpd_report')) {
        url.searchParams.set('cpd_report', 'compliance');
    }
    
    window.location.href = url.toString();
}

// Show different report tabs
function showReport(reportType, clickedElement) {
    // Hide all reports
    document.querySelectorAll('.report-content').forEach(el => el.style.display = 'none');
    
    // Remove active class from all tabs
    console.log('🔄 Removing active styling from all tabs');
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.style.color = '#6b7280';
        btn.style.borderBottom = 'none';
    });
    
    // Show selected report
    document.getElementById(reportType + '-report').style.display = 'block';
    
    // Load data for specific report types
    if (reportType === 'compliance') {
        loadComplianceData();
    } else if (reportType === 'members') {
        loadMemberDetails();
    }
    
    // Activate current tab
    const targetElement = clickedElement || document.querySelector(`.tab-btn[onclick*="${reportType}"]`);
    
    if (targetElement) {
        targetElement.style.color = '#667eea';
        targetElement.style.borderBottom = '3px solid #667eea';
        console.log('🎯 Applied tab styling to:', reportType, targetElement);
    }
    
    // Load data based on report type
    switch(reportType) {
        case 'compliance':
            loadComplianceData();
            break;
        case 'categories':
            loadCategoriesData();
            break;
        case 'members':
            loadMembersData();
            break;
    }
}

// Global variables for compliance tab management
let currentComplianceTab = 'compliant';
let showHighRiskOnly = false;

// Load compliance data
function loadComplianceData(page = 1) {
    console.log('🔄 Loading compliance data for tab:', currentComplianceTab);
    loadComplianceMembers(currentComplianceTab, page);
}

// Load initial compliance counts for tab buttons via AJAX
function loadComplianceCounts() {
    console.log('🔄 Loading compliance counts...', {
        ajax_url: window.iipm_reports_ajax.ajax_url,
        year: currentYear,
        nonce: window.iipm_reports_ajax.nonce
    });
    
    // Test if function is being called
    console.log('✅ loadComplianceCounts function called!');
    
    fetch(window.iipm_reports_ajax.ajax_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'iipm_get_compliance_data',
            year: currentYear,
            nonce: window.iipm_reports_ajax.nonce
        })
    })
    .then(response => {
        console.log('📡 Response received:', response);
        return response.json();
    })
    .then(data => {
        console.log('📦 Data received:', data);
        if (data.success) {
            // Update tab counts from AJAX response
            const compliantCount = document.getElementById('compliant-tab-count');
            const nonCompliantCount = document.getElementById('non-compliant-tab-count');
            
            console.log('📊 Updating counts:', {
                compliant: data.data.compliant_members,
                non_compliant: data.data.non_compliant_members
            });
            
            if (compliantCount) {
                compliantCount.textContent = data.data.compliant_members || 0;
            }
            if (nonCompliantCount) {
                nonCompliantCount.textContent = data.data.non_compliant_members || 0;
            }
        } else {
            console.error('❌ API Error:', data);
        }
    })
    .catch(error => {
        console.error('❌ Error loading compliance counts:', error);
        
        // Fallback: Set default values if AJAX fails
        const compliantCount = document.getElementById('compliant-tab-count');
        const nonCompliantCount = document.getElementById('non-compliant-tab-count');
        
        if (compliantCount) {
            compliantCount.textContent = '0';
        }
        if (nonCompliantCount) {
            nonCompliantCount.textContent = '0';
        }
    });
}


// Switch between compliant and non-compliant tabs
function switchComplianceTab(tab) {
    console.log('🔄 Switching to tab:', tab);
    currentComplianceTab = tab;
    
    // Update tab button styles - active tab is always green
    const compliantTab = document.getElementById('compliant-tab');
    const nonCompliantTab = document.getElementById('non-compliant-tab');
    const highRiskFilter = document.getElementById('high-risk-filter');
    
    if (tab === 'compliant') {
        compliantTab.style.background = '#10b981'; // Green for active
        nonCompliantTab.style.background = '#6b7280'; // Gray for inactive
        highRiskFilter.style.display = 'none';
        showHighRiskOnly = false;
    } else {
        compliantTab.style.background = '#6b7280'; // Gray for inactive
        nonCompliantTab.style.background = '#10b981'; // Green for active
        highRiskFilter.style.display = 'block';
    }
    
    // Load data for the selected tab
    loadComplianceMembers(tab, 1);
}

// Toggle high-risk filter
function toggleHighRiskFilter() {
    showHighRiskOnly = document.getElementById('show-high-risk-only').checked;
    loadComplianceMembers(currentComplianceTab, 1);
}

// Load compliance members with pagination
function loadComplianceMembers(type, page = 1) {
    const tableBody = document.getElementById('compliance-table');
    const pagination = document.getElementById('compliance-pagination');
    
    // Show loading state
    tableBody.innerHTML = `
        <tr>
            <td colspan="5" style="padding: 20px; text-align: center; color: #6b7280;">
                <div class="loading-spinner" style="display: inline-block; width: 20px; height: 20px; border: 2px solid #e5e7eb; border-top: 2px solid #667eea; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                Loading ${type} members...
            </td>
        </tr>
    `;
    
    const params = new URLSearchParams({
        action: 'iipm_get_compliance_data',
        year: currentYear,
        type: type === 'non-compliant' ? 'non_compliant' : type,
        page: page,
        nonce: window.iipm_reports_ajax.nonce
    });
    
    // Add high-risk filter if applicable
    if (type === 'non-compliant' && showHighRiskOnly) {
        params.append('high_risk_only', '1');
    }
    
    fetch(window.iipm_reports_ajax.ajax_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateComplianceTable(data.data, page, type);
        } else {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="6" style="padding: 20px; text-align: center; color: #ef4444;">
                        Error loading ${type} members: ${data.data || 'Unknown error'}
                    </td>
                </tr>
            `;
        }
    })
    .catch(error => {
        console.error(`Error loading ${type} members:`, error);
        tableBody.innerHTML = `
            <tr>
                <td colspan="6" style="padding: 20px; text-align: center; color: #ef4444;">
                    Error loading ${type} members. Please try again.
                </td>
            </tr>
        `;
    });
}

// Update compliance table with badges and status
function updateComplianceTable(data, currentPage, type) {
    const tableBody = document.getElementById('compliance-table');
    const pagination = document.getElementById('compliance-pagination');
    const members = data.members || [];
    
    // Tab counts are loaded via initial AJAX call - no need to update dynamically here
    
    if (members.length === 0) {
        const message = type === 'compliant' ? 'No compliant members found' : 'No non-compliant members found';
        tableBody.innerHTML = `<tr><td colspan="5" style="padding: 20px; text-align: center; color: #6b7280;">${message}</td></tr>`;
        pagination.innerHTML = '';
        return;
    }
    
    tableBody.innerHTML = members.map(member => {
        // Determine status badge
        let statusBadge = '';
        let statusColor = '';
        
        if (member.progress_percentage >= 100) {
            statusBadge = 'Compliant';
            statusColor = '#10b981';
        } else if (member.progress_percentage === 0) {
            statusBadge = 'High Risk';
            statusColor = '#ef4444';
    } else {
            statusBadge = 'Non-Compliant';
            statusColor = '#ef4444';
        }
        
        // Calculate high risk status in frontend (for table view) - only for non-compliant members
        const isHighRisk = 
                          ((member.progress_percentage === 0) || 
                           (member.earned_points === 0));
        
        // High risk badge only for non-compliant members (includes not assigned cases)
        const highRiskBadge = isHighRisk ? '<span style="background: #dc2626; color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px; margin-left: 4px; white-space: nowrap; display: inline-block;">High Risk</span>' : '';
        
        return `
            <tr>
                <td style="padding: 12px; border-bottom: 1px solid #e5e7eb;">
                    <strong>${member.name || 'Unknown User'}</strong><br>
                    <small style="color: #6b7280;">${member.email}</small>
                    ${highRiskBadge}
                </td>
                <td style="padding: 12px; text-align: center; border-bottom: 1px solid #e5e7eb;">${member.earned_points || 0}</td>
                <td style="padding: 12px; text-align: center; border-bottom: 1px solid #e5e7eb;">${member.required_points || 0}</td>
                <td style="padding: 12px; text-align: center; border-bottom: 1px solid #e5e7eb;">
                    <div style="background: #f3f4f6; border-radius: 10px; height: 8px; position: relative; margin-bottom: 4px;">
                        <div style="background: ${statusColor}; height: 100%; border-radius: 10px; width: ${member.progress_percentage || 0}%;"></div>
                    </div>
                    <small style="color: #6b7280;">${member.progress_percentage || 0}%</small>
                </td>
                <td style="padding: 12px; text-align: center; border-bottom: 1px solid #e5e7eb;">
                    <button onclick="sendIndividualReminder(${member.user_id})" style="background: ${statusColor}; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;">
                        Send Reminder
                    </button>
                </td>
            </tr>
        `;
    }).join('');
    
    // Add comprehensive pagination
    const totalPages = data.total_pages || 1;
    console.log('Pagination Debug:', { totalPages, currentPage, totalMembers: data.total_members, membersCount: members.length });
    
    if (totalPages > 1) {
        let paginationHTML = '<div style="display: flex; justify-content: center; align-items: center; gap: 5px; flex-wrap: wrap;">';
        
        // Previous button
        if (currentPage > 1) {
            paginationHTML += `<button onclick="loadComplianceMembers('${type}', ${currentPage - 1})" style="padding: 8px 12px; border: 1px solid #d1d5db; background: white; border-radius: 4px; cursor: pointer; font-size: 14px; transition: all 0.2s;">‹ Previous</button>`;
        }
        
        // First page
        if (currentPage > 3) {
            paginationHTML += `<button onclick="loadComplianceMembers('${type}', 1)" style="padding: 8px 12px; border: 1px solid #d1d5db; background: white; border-radius: 4px; cursor: pointer; font-size: 14px; transition: all 0.2s;">1</button>`;
            if (currentPage > 4) {
                paginationHTML += `<span style="padding: 8px 4px; color: #6b7280;">...</span>`;
            }
        }
        
        // Page numbers around current page
        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, currentPage + 2);
        
        for (let i = startPage; i <= endPage; i++) {
            if (i === currentPage) {
                paginationHTML += `<button style="padding: 8px 12px; border: 1px solid #667eea; background: #667eea; color: white; border-radius: 4px; font-size: 14px; font-weight: 600;">${i}</button>`;
    } else {
                paginationHTML += `<button onclick="loadComplianceMembers('${type}', ${i})" style="padding: 8px 12px; border: 1px solid #d1d5db; background: white; border-radius: 4px; cursor: pointer; font-size: 14px; transition: all 0.2s;">${i}</button>`;
            }
        }
        
        // Last page
        if (currentPage < totalPages - 2) {
            if (currentPage < totalPages - 3) {
                paginationHTML += `<span style="padding: 8px 4px; color: #6b7280;">...</span>`;
            }
            paginationHTML += `<button onclick="loadComplianceMembers('${type}', ${totalPages})" style="padding: 8px 12px; border: 1px solid #d1d5db; background: white; border-radius: 4px; cursor: pointer; font-size: 14px; transition: all 0.2s;">${totalPages}</button>`;
        }
        
        // Next button
        if (currentPage < totalPages) {
            paginationHTML += `<button onclick="loadComplianceMembers('${type}', ${currentPage + 1})" style="padding: 8px 12px; border: 1px solid #d1d5db; background: white; border-radius: 4px; cursor: pointer; font-size: 14px; transition: all 0.2s;">Next ›</button>`;
        }
        
        // Page info
        paginationHTML += `<span style="padding: 8px 12px; color: #6b7280; font-size: 14px; margin-left: 10px;">Page ${currentPage} of ${totalPages} (${data.total_members} total)</span>`;
        
        paginationHTML += '</div>';
        pagination.innerHTML = paginationHTML;
    } else {
        pagination.innerHTML = '';
    }
}

// Load member details (now uses loadMembersTable)
function loadMemberDetails(page = 1) {
    loadMembersTable(page);
}

// Update member details table
function updateMemberDetailsTable(data, currentPage) {
    const tableBody = document.getElementById('members-table-body');
    const pagination = document.getElementById('member-pagination');
    const members = data.members || [];
    
    if (members.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="7" style="padding: 40px; text-align: center; color: #6b7280;">
                    No members found
                </td>
            </tr>
        `;
        pagination.innerHTML = '';
        return;
    }
    
    tableBody.innerHTML = members.map(member => `
        <tr>
            <td style="padding: 15px; border-bottom: 1px solid #e5e7eb;">
                <strong>${member.name || 'Unknown User'}</strong><br>
                    <small style="color: #6b7280;">${member.email}</small>
                </td>
            <td style="padding: 15px; text-align: center; border-bottom: 1px solid #e5e7eb;">${member.role || 'Member'}</td>
            <td style="padding: 15px; text-align: center; border-bottom: 1px solid #e5e7eb;">${member.earned_points || 0}</td>
            <td style="padding: 15px; text-align: center; border-bottom: 1px solid #e5e7eb;">${member.required_points || 0}</td>
            <td style="padding: 15px; text-align: center; border-bottom: 1px solid #e5e7eb;">
                <div style="background: #f3f4f6; border-radius: 10px; height: 8px; position: relative; margin-bottom: 5px;">
                    <div style="background: ${member.progress_percentage >= 100 ? '#10b981' : member.progress_percentage >= 75 ? '#f59e0b' : '#ef4444'}; height: 100%; border-radius: 10px; width: ${Math.min(member.progress_percentage, 100)}%;"></div>
                    </div>
                <small style="color: #6b7280;">${member.progress_percentage || 0}%</small>
                </td>
            <td style="padding: 15px; text-align: center; border-bottom: 1px solid #e5e7eb;">
                <span style="background: ${member.compliance_status === 'Yes' ? '#10b981' : '#ef4444'}; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;">
                    ${member.compliance_status || 'No'}
                </span>
            </td>
            <td style="padding: 15px; text-align: center; border-bottom: 1px solid #e5e7eb;">
                <button onclick="viewMemberReport(${member.user_id})" style="background: #667eea; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;">
                    View Report
                    </button>
                </td>
        </tr>
    `).join('');
    
    // Add comprehensive pagination
    const totalPages = data.total_pages || 1;
    if (totalPages > 1) {
        let paginationHTML = '<div style="display: flex; justify-content: center; align-items: center; gap: 5px; flex-wrap: wrap;">';
        
        // Previous button
        if (currentPage > 1) {
            paginationHTML += `<button onclick="loadMemberDetails(${currentPage - 1})" style="padding: 8px 12px; border: 1px solid #d1d5db; background: white; border-radius: 4px; cursor: pointer; font-size: 14px; transition: all 0.2s;">‹ Previous</button>`;
        }
        
        // First page
        if (currentPage > 3) {
            paginationHTML += `<button onclick="loadMemberDetails(1)" style="padding: 8px 12px; border: 1px solid #d1d5db; background: white; border-radius: 4px; cursor: pointer; font-size: 14px; transition: all 0.2s;">1</button>`;
            if (currentPage > 4) {
                paginationHTML += `<span style="padding: 8px 4px; color: #6b7280;">...</span>`;
            }
        }
        
        // Page numbers around current page
        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, currentPage + 2);
        
        for (let i = startPage; i <= endPage; i++) {
            if (i === currentPage) {
                paginationHTML += `<button style="padding: 8px 12px; border: 1px solid #667eea; background: #667eea; color: white; border-radius: 4px; font-size: 14px; font-weight: 600;">${i}</button>`;
            } else {
                paginationHTML += `<button onclick="loadMemberDetails(${i})" style="padding: 8px 12px; border: 1px solid #d1d5db; background: white; border-radius: 4px; cursor: pointer; font-size: 14px; transition: all 0.2s;">${i}</button>`;
            }
        }
        
        // Last page
        if (currentPage < totalPages - 2) {
            if (currentPage < totalPages - 3) {
                paginationHTML += `<span style="padding: 8px 4px; color: #6b7280;">...</span>`;
            }
            paginationHTML += `<button onclick="loadMemberDetails(${totalPages})" style="padding: 8px 12px; border: 1px solid #d1d5db; background: white; border-radius: 4px; cursor: pointer; font-size: 14px; transition: all 0.2s;">${totalPages}</button>`;
        }
        
        // Next button
        if (currentPage < totalPages) {
            paginationHTML += `<button onclick="loadMemberDetails(${currentPage + 1})" style="padding: 8px 12px; border: 1px solid #d1d5db; background: white; border-radius: 4px; cursor: pointer; font-size: 14px; transition: all 0.2s;">Next ›</button>`;
        }
        
        // Page info
        paginationHTML += `<span style="padding: 8px 12px; color: #6b7280; font-size: 14px; margin-left: 10px;">Page ${currentPage} of ${totalPages} (${data.total_members} total)</span>`;
        
        paginationHTML += '</div>';
        pagination.innerHTML = paginationHTML;
    } else {
        pagination.innerHTML = '';
    }
}

// View individual member report
function viewMemberReport(userId) {
    const reportContent = document.getElementById('individual-report-content');
    
    // Show loading state
    reportContent.style.display = 'block';
    reportContent.innerHTML = `
        <div style="text-align: center; padding: 40px; color: #6b7280;">
            <div class="loading-spinner" style="display: inline-block; width: 24px; height: 24px; border: 3px solid #e5e7eb; border-top: 3px solid #667eea; border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 15px;"></div>
            <div>Loading member report...</div>
        </div>
    `;
    
    fetch(window.iipm_reports_ajax.ajax_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'iipm_get_individual_report',
            user_id: userId,
            year: currentYear,
            nonce: window.iipm_reports_ajax.nonce
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const memberName = data.data.member ? 
                `${data.data.member.first_name || ''} ${data.data.member.last_name || ''}`.trim() || data.data.member.display_name : 
                'Unknown Member';
            console.log("Wow", "Wow!");
            displayIndividualReport(data.data, memberName);
            
            // Auto-scroll to the report
            setTimeout(() => {
                const reportElement = document.getElementById('individual-report-content');
                if (reportElement) {
                    reportElement.scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'start',
                        inline: 'nearest'
                    });
                }
            }, 100);
        } else {
            reportContent.innerHTML = `
                <div style="text-align: center; padding: 40px; color: #ef4444;">
                    Error loading member report: ${data.data || 'Unknown error'}
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error loading member report:', error);
        reportContent.innerHTML = `
            <div style="text-align: center; padding: 40px; color: #ef4444;">
                Error loading member report. Please try again.
            </div>
        `;
    });
}

// Display individual member report
function displayIndividualReport(reportData, memberName = null) {
    const reportContent = document.getElementById('individual-report-content');

    console.log("reportData", "wow!");
    
    // Store data globally for export and email functions
    currentIndividualReportData = reportData;
    
    // Generate category progress HTML
    const categoryProgressHTML = Object.values(reportData.categories).map(category => {
        const isCompleted = (category.completed || 0) >= (category.required || 0);
        const progressColor = isCompleted ? '#10b981' : '#ef4444';
        const progressText = (category.required || 0) > 0 ? `${category.completed || 0}/${category.required || 0} courses` : `${category.completed || 0} courses`;
        
        return `
            <div style="margin-bottom: 20px; padding: 15px; background: #f8fafc; border-radius: 8px; border-left: 4px solid ${progressColor};">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <h4 style="margin: 0; color: #374151;">${category.name || 'Unknown Category'}</h4>
                    <span style="font-weight: bold; color: ${progressColor};">${progressText}</span>
                </div>
                <div style="background: #e5e7eb; height: 8px; border-radius: 4px; overflow: hidden;">
                    <div style="background: ${progressColor}; height: 100%; width: ${(category.required || 0) > 0 ? Math.min(100, ((category.completed || 0) / (category.required || 1)) * 100) : 100}%; transition: width 0.3s ease;"></div>
                </div>
                <div style="font-size: 0.875rem; color: #6b7280; margin-top: 5px;">
                    ${(category.required || 0) > 0 ? (isCompleted ? '✅ Requirement met' : '❌ Need at least 1 course') : 'Additional courses'}
                </div>
            </div>
        `;
    }).join('');
    
    // Generate completed courses HTML
    const completedCoursesHTML = reportData.all_courses.length > 0 ? 
        reportData.all_courses.map(course => `
            <tr style="border-bottom: 1px solid #e5e7eb;">
                <td style="padding: 12px; color: #374151;">${course.title}</td>
                <td style="padding: 12px; color: #6b7280;">${course.provider}</td>
                <td style="padding: 12px; text-align: center;">
                    <span style="background: #667eea; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem;">
                        ${course.hours} hrs
                    </span>
                </td>
                <td style="padding: 12px; text-align: center;">
                    <span style="background: #f3f4f6; color: #374151; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem;">
                        ${course.category}
                    </span>
                </td>
                <td style="padding: 12px; text-align: center;">
                    <span style="background: #e0f2fe; color: #0369a1; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem;">
                        ${course.courseType || 'Unknown'}
                    </span>
                </td>
                <td style="padding: 12px; color: #6b7280; font-size: 0.875rem;">${course.date}</td>
            </tr>
        `).join('') : 
        '<tr><td colspan="6" style="text-align: center; padding: 40px; color: #6b7280;">No completed courses found for this year</td></tr>';
    
    reportContent.innerHTML = `
        <div style="background: white; border-radius: 12px; padding: 30px; border: 1px solid #e5e7eb; max-height: 80vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
                <h3 style="color: #374151; margin: 0;">📊 Individual Member Report - ${memberName || reportData.member.display_name}</h3>
                <div style="display: flex; gap: 10px;">
                    <button onclick="exportIndividualReportCSV()" 
                            style="background: #10b981; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 0.875rem;">
                        📄 Export CSV
                    </button>
                    <button onclick="sendIndividualReportEmail()" 
                            style="background: #3b82f6; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 0.875rem;">
                        📧 Send Email
                    </button>
                    <button onclick="document.getElementById('individual-report-content').style.display='none'" 
                            style="background: #6b7280; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 0.875rem;">
                        ✕ Close
                    </button>
                </div>
            </div>
            
            <!-- Member Info -->
            <div style="background: #f8fafc; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div>
                        <strong style="color: #374151;">Name:</strong> 
                        <span style="color: #6b7280;">${reportData.member.first_name || ''} ${reportData.member.last_name || ''} (${reportData.member.display_name})</span>
                    </div>
                    <div>
                        <strong style="color: #374151;">Email:</strong> 
                        <span style="color: #6b7280;">${reportData.member.user_email}</span>
                    </div>
                    <div>
                        <strong style="color: #374151;">Role:</strong> 
                        <span style="color: #6b7280;">${reportData.member.role || 'N/A'}</span>
                    </div>
                    <div>
                        <strong style="color: #374151;">Year:</strong> 
                        <span style="color: #6b7280;">${reportData.year}</span>
                    </div>
                </div>
            </div>
            
            <!-- Summary Stats -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <div style="text-align: center; padding: 20px; background: #f0fdf4; border-radius: 8px; border: 1px solid #bbf7d0;">
                    <div style="font-size: 2rem; color: #10b981; margin-bottom: 10px;">📈</div>
                    <h4 style="margin: 0 0 5px 0; color: #374151;">Points Earned</h4>
                    <p style="font-size: 1.5rem; font-weight: bold; color: #10b981; margin: 0;">${reportData.total_earned.toFixed(1)}</p>
                </div>
                
                <div style="text-align: center; padding: 20px; background: #eff6ff; border-radius: 8px; border: 1px solid #bfdbfe;">
                    <div style="font-size: 2rem; color: #667eea; margin-bottom: 10px;">🎯</div>
                    <h4 style="margin: 0 0 5px 0; color: #374151;">Required Points</h4>
                    <p style="font-size: 1.5rem; font-weight: bold; color: #667eea; margin: 0;">${reportData.required_points}</p>
                </div>
                
                <div style="text-align: center; padding: 20px; background: #fefce8; border-radius: 8px; border: 1px solid #fde68a;">
                    <div style="font-size: 2rem; color: #f59e0b; margin-bottom: 10px;">📊</div>
                    <h4 style="margin: 0 0 5px 0; color: #374151;">Progress</h4>
                    <p style="font-size: 1.5rem; font-weight: bold; color: #f59e0b; margin: 0;">${reportData.progress_percentage.toFixed(1)}%</p>
                </div>
                
                <div style="text-align: center; padding: 20px; background: ${reportData.compliance_status === 'compliant' ? '#f0fdf4' : '#fef2f2'}; border-radius: 8px; border: 1px solid ${reportData.compliance_status === 'compliant' ? '#bbf7d0' : '#fecaca'};">
                    <div style="font-size: 2rem; color: ${reportData.compliance_status === 'compliant' ? '#10b981' : '#ef4444'}; margin-bottom: 10px;">${reportData.compliance_status === 'compliant' ? '✅' : '❌'}</div>
                    <h4 style="margin: 0 0 5px 0; color: #374151;">Status</h4>
                    <p style="font-size: 1.5rem; font-weight: bold; color: ${reportData.compliance_status === 'compliant' ? '#10b981' : '#ef4444'}; margin: 0;">${reportData.compliance_status === 'compliant' ? 'Compliant' : 'Non-Compliant'}</p>
                </div>
            </div>
            
            <!-- CPD Progress by Category -->
            <div style="margin-bottom: 30px;">
                <h4 style="color: #374151; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #e5e7eb;">📚 CPD Progress by Category</h4>
                ${categoryProgressHTML}
            </div>
            
            <!-- Completed Courses Table -->
            <div>
                <h4 style="color: #374151; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #e5e7eb;">📋 Completed Courses (${reportData.all_courses.length} total)</h4>
                <div style="overflow-x: auto;">
                    <table style="width: 150%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        <thead style="background: #f8fafc;">
                            <tr>
                                <th style="padding: 15px; text-align: left; color: #374151; font-weight: 600; border-bottom: 2px solid #e5e7eb;">Course Title</th>
                                <th style="padding: 15px; text-align: left; color: #374151; font-weight: 600; border-bottom: 2px solid #e5e7eb;">Provider</th>
                                <th style="padding: 15px; text-align: center; color: #374151; font-weight: 600; border-bottom: 2px solid #e5e7eb;">Hours</th>
                                <th style="padding: 15px; text-align: center; color: #374151; font-weight: 600; border-bottom: 2px solid #e5e7eb;">Category</th>
                                <th style="padding: 15px; text-align: center; color: #374151; font-weight: 600; border-bottom: 2px solid #e5e7eb;">Type</th>
                                <th style="padding: 15px; text-align: left; color: #374151; font-weight: 600; border-bottom: 2px solid #e5e7eb;">Date Completed</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${completedCoursesHTML}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    `;
}

// Update compliance tables (legacy function for backward compatibility)
function updateComplianceTables(data) {
    // This function is kept for backward compatibility but now uses the new tab system
    // Initialize with compliant tab by default
    currentComplianceTab = 'compliant';
    loadComplianceMembers('compliant', 1);
}

// Export reports
function exportReport(type) {
    const url = `${window.iipm_reports_ajax.ajax_url}?action=iipm_export_report&type=${type}&year=${currentYear}&nonce=${window.iipm_reports_ajax.nonce}`;
    window.open(url, '_blank');
}

// Send reminders
function sendReminders(type) {
    alert('📧 Bulk reminder functionality will be implemented in the next feature update.');
}

// Send individual reminder (referenced in table buttons)
function sendIndividualReminder(userId) {
    alert('📧 Individual reminder functionality will be implemented in the next feature update.');
}

// Load categories data (referenced in tab switching)
function loadCategoriesData() {
    const categoriesChart = document.getElementById('categories-chart');
    categoriesChart.innerHTML = '<p style="text-align: center; color: #6b7280; margin-top: 150px;">Category analytics will be available in the next update</p>';
}

// Load members data (referenced in tab switching)
function loadMembersData() {
    console.log('📋 loadMembersData() called');
    loadMembersTable();
}

// Load all members into the table
function loadMembersTable(page = 1) {
    console.log('📊 Loading members table - Page:', page);
    
    // Show loading state
    const tableBody = document.getElementById('members-table-body');
    const pagination = document.getElementById('member-pagination');
    
    if (!tableBody) {
        console.error('❌ Members table body not found!');
        return;
    }
    
    tableBody.innerHTML = `
        <tr>
            <td colspan="7" style="padding: 40px; text-align: center; color: #6b7280;">
                <div class="loading-spinner" style="display: inline-block; width: 24px; height: 24px; border: 3px solid #e5e7eb; border-top: 3px solid #667eea; border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 15px;"></div>
                <div>Loading members...</div>
            </td>
        </tr>
    `;
    
    // Fetch members with pagination
    const reportType = document.getElementById('member-report-type')?.value || 'employed';
    fetch(window.iipm_reports_ajax.ajax_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'iipm_get_all_members_for_reports',
            year: currentYear,
            report_type: reportType,
            page: page,
            nonce: window.iipm_reports_ajax.nonce
        })
    })
    .then(response => response.json())
    .then(data => {
        console.log('📦 Members data received:', data);
        if (data.success) {
            updateMemberDetailsTable(data.data, page);
        } else {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="7" style="padding: 40px; text-align: center; color: #ef4444;">
                        Error loading members: ${data.data || 'Unknown error'}
                    </td>
                </tr>
            `;
            if (pagination) pagination.innerHTML = '';
        }
    })
    .catch(error => {
        console.error('❌ Error loading members:', error);
        tableBody.innerHTML = `
            <tr>
                <td colspan="7" style="padding: 40px; text-align: center; color: #ef4444;">
                    Error loading members. Please try again.
                </td>
            </tr>
        `;
        if (pagination) pagination.innerHTML = '';
    });
}

// Removed - using updateMemberDetailsTable instead
// function displayMembersTable(members) {
    console.log('🎨 Displaying', members.length, 'members in table');
    
    const tableBody = document.getElementById('members-table-body');
    
    const tableRows = members.map(member => {
        const progressPercentage = member.required_points > 0 ? 
            Math.round((member.earned_points / member.required_points) * 100) : 0;
        
        let statusIcon, statusText, statusColor;
        if (progressPercentage >= 100) {
            statusIcon = '✅';
            statusText = 'Compliant';
            statusColor = '#10b981';
        } else if (progressPercentage >= 75) {
            statusIcon = '⚠️';
            statusText = 'At Risk';
            statusColor = '#f59e0b';
        } else {
            statusIcon = '❌';
            statusText = 'Non-Compliant';
            statusColor = '#ef4444';
        }
        
        let progressColor = '#ef4444';
        if (progressPercentage >= 100) progressColor = '#10b981';
        else if (progressPercentage >= 75) progressColor = '#f59e0b';
        
        return `
            <tr style="border-bottom: 1px solid #e5e7eb; transition: background-color 0.2s;" 
                onmouseover="this.style.backgroundColor='#f8fafc'" 
                onmouseout="this.style.backgroundColor='white'">
                <td style="padding: 15px;">
                    <div style="display: flex; align-items: center;">
                        <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; margin-right: 12px; font-size: 14px;">
                            ${member.display_name.charAt(0).toUpperCase()}
                        </div>
                        <div>
                            <div style="font-weight: 600; color: #374151;">${member.display_name}</div>
                            <div style="font-size: 0.85rem; color: #6b7280;">${member.user_email}</div>
                        </div>
                    </div>
                </td>
                <td style="padding: 15px; text-align: center;">
                    <span style="background: #f3f4f6; color: #374151; padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 500; text-transform: capitalize;">
                        ${member.membership_level || 'Member'}
                    </span>
                </td>
                <td style="padding: 15px; text-align: center;">
                    <div style="margin-bottom: 5px;">
                        <span style="font-weight: 600; color: ${progressColor};">${member.earned_points}</span>
                        <span style="color: #6b7280;">/${member.required_points} points</span>
                    </div>
                    <div style="background: #f3f4f6; border-radius: 10px; height: 6px; overflow: hidden;">
                        <div style="background: ${progressColor}; height: 100%; width: ${Math.min(100, progressPercentage)}%; transition: width 0.3s ease;"></div>
                    </div>
                    <div style="font-size: 0.75rem; color: #6b7280; margin-top: 2px;">${progressPercentage}%</div>
                </td>
                <td style="padding: 15px; text-align: center;">
                    <span style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 500; color: ${statusColor}; background: ${statusColor}20;">
                        ${statusIcon} ${statusText}
                    </span>
                </td>
                <td style="padding: 15px; text-align: center;">
                    <button onclick="generateMemberReport(${member.ID}, '${member.display_name}')" 
                            style="background: #667eea; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 500; font-size: 0.9rem; transition: background 0.2s;"
                            onmouseover="this.style.background='#5a67d8'" 
                            onmouseout="this.style.background='#667eea'">
                        📊 Generate Report
                    </button>
                </td>
            </tr>
        `;
    }).join('');
    
    // OLD FUNCTION - REMOVED FOR EFFICIENCY
    // Now using updateMemberDetailsTable() with pagination
// }

// Generate member report from table button
function generateMemberReport(userId, memberName) {
    console.log('📊 Generating report for member:', userId, memberName);
    console.log('🔍 Debug info:', {
        userId: userId,
        memberName: memberName,
        currentYear: currentYear,
        ajaxUrl: window.iipm_reports_ajax?.ajax_url,
        nonce: window.iipm_reports_ajax?.nonce
    });
    
    // Show the individual report section
    const reportSection = document.getElementById('individual-report-content');
    reportSection.style.display = 'block';
    
    // Show loading state
    reportSection.innerHTML = `
        <div style="background: white; padding: 40px; border-radius: 12px; text-align: center; border: 1px solid #e5e7eb;">
            <div class="loading-spinner" style="display: inline-block; width: 30px; height: 30px; border: 3px solid #e5e7eb; border-top: 3px solid #667eea; border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 15px;"></div>
            <h4 style="color: #374151; margin: 10px 0;">Generating Report for ${memberName}</h4>
            <p style="color: #6b7280; margin: 0;">Please wait while we gather the CPD data...</p>
        </div>
    `;
    
    // Scroll to the report section
    reportSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    
    // Fetch individual report data
    fetch(window.iipm_reports_ajax.ajax_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'iipm_get_individual_report',
            user_id: userId,
            year: currentYear,
            nonce: window.iipm_reports_ajax.nonce
        })
    })
    .then(response => response.json())
    .then(data => {
        console.log('📦 Individual report data:', data);
        if (data.success) {
            displayIndividualReport(data.data, memberName);
        } else {
            reportSection.innerHTML = `
                <div style="background: white; padding: 40px; border-radius: 12px; text-align: center; border: 1px solid #e5e7eb;">
                    <div style="font-size: 2rem; margin-bottom: 15px;">⚠️</div>
                    <h4 style="color: #ef4444; margin: 10px 0;">Failed to Generate Report</h4>
                    <p style="color: #6b7280; margin: 0;">${data.data?.message || 'Unknown error occurred'}</p>
                    <button onclick="document.getElementById('individual-report-content').style.display='none'" 
                            style="background: #6b7280; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; margin-top: 15px;">
                        Close
                    </button>
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('❌ Report generation error:', error);
        reportSection.innerHTML = `
            <div style="background: white; padding: 40px; border-radius: 12px; text-align: center; border: 1px solid #e5e7eb;">
                <div style="font-size: 2rem; margin-bottom: 15px;">❌</div>
                <h4 style="color: #ef4444; margin: 10px 0;">Report Generation Failed</h4>
                <p style="color: #6b7280; margin: 0;">Network error or server issue. Please try again.</p>
                <button onclick="document.getElementById('individual-report-content').style.display='none'" 
                        style="background: #6b7280; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; margin-top: 15px;">
                    Close
                </button>
            </div>
        `;
    });
}

// Legacy functions - no longer needed with table view

// Display individual report (LEGACY - REMOVED)
function displayIndividualReportLegacy(reportData, memberName) {
    console.log('🎨 Displaying individual report for:', memberName);
    
    // Store report data globally for CSV export
    currentReportData = reportData;
    
    // Display the complete report
    document.getElementById('individual-report-content').innerHTML = `
        <!-- Report Header -->
        <div id="report-header" style="background: white; padding: 25px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #e5e7eb;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h4 style="color: #374151; margin: 0;">📊 CPD Report: ${memberName}</h4>
                <div style="display: flex; gap: 10px;">
                    <button onclick="exportIndividualReportCSV()" style="background: #059669; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">
                        📊 Export CSV
                    </button>
                    <button onclick="exportIndividualReport()" style="background: #10b981; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">
                        📄 Export PDF
                    </button>
                    <button onclick="emailIndividualReport()" style="background: #667eea; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">
                        📧 Email Report
                    </button>
                    <button onclick="document.getElementById('individual-report-content').style.display='none'" style="background: #6b7280; color: white; padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">
                        ✕ Close
                    </button>
                </div>
            </div>
            
            <!-- Progress Summary -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
                <div style="text-align: center; padding: 15px; background: #f8fafc; border-radius: 8px;">
                    <div style="font-size: 1.5rem; font-weight: bold; color: #667eea;" id="total-earned">${reportData.total_earned}</div>
                    <div style="font-size: 0.85rem; color: #6b7280;">Points Earned</div>
                </div>
                <div style="text-align: center; padding: 15px; background: #f8fafc; border-radius: 8px;">
                    <div style="font-size: 1.5rem; font-weight: bold; color: #374151;" id="total-required">${reportData.required_points}</div>
                    <div style="font-size: 0.85rem; color: #6b7280;">Points Required</div>
                </div>
                <div style="text-align: center; padding: 15px; background: #f8fafc; border-radius: 8px;">
                    <div style="font-size: 1.5rem; font-weight: bold; color: ${getProgressColor(reportData.progress_percentage)}" id="progress-percentage">${Math.round(reportData.progress_percentage)}%</div>
                    <div style="font-size: 0.85rem; color: #6b7280;">Progress</div>
                </div>
                <div style="text-align: center; padding: 15px; background: #f8fafc; border-radius: 8px;">
                    <div style="font-size: 1.5rem;" id="compliance-status-icon">${getComplianceIcon(reportData.compliance_status)}</div>
                    <div style="font-size: 0.85rem; color: #6b7280;" id="compliance-status-text">${getComplianceText(reportData.compliance_status)}</div>
                </div>
            </div>
        </div>

        <!-- Category Breakdown -->
        <div style="background: white; padding: 25px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #e5e7eb;">
            <h4 style="color: #374151; margin-bottom: 20px;">📊 CPD Progress by Category</h4>
            <div id="category-breakdown" style="display: grid; gap: 15px;">
                ${generateCategoryBreakdown(reportData.categories)}
            </div>
        </div>

        <!-- Course History -->
        <div style="background: white; padding: 25px; border-radius: 12px; border: 1px solid #e5e7eb;">
            <h4 style="color: #374151; margin-bottom: 20px;">📚 Complete Course History</h4>
            <div style="overflow-x: auto;">
                <table id="course-history-table" style="width: 100%; border-collapse: collapse;">
                    <thead style="background: #f8fafc;">
                        <tr>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Date</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Course</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Provider</th>
                            <th style="padding: 12px; text-align: center; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Category</th>
                            <th style="padding: 12px; text-align: center; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Points</th>
                            <th style="padding: 12px; text-align: center; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Status</th>
                        </tr>
                    </thead>
                    <tbody id="course-history-body">
                        ${generateCourseHistory(reportData.all_courses)}
                    </tbody>
                </table>
            </div>
        </div>
    `;
}

// Helper functions for report display
function getProgressColor(percentage) {
    if (percentage >= 100) return '#10b981';
    if (percentage >= 75) return '#f59e0b';
    return '#ef4444';
}

function getComplianceIcon(status) {
    switch(status) {
        case 'compliant': return '✅';
        case 'at_risk': return '⚠️';
        case 'non_compliant': return '❌';
        default: return '⏳';
    }
}

function getComplianceText(status) {
    switch(status) {
        case 'compliant': return 'Compliant';
        case 'at_risk': return 'At Risk';
        case 'non_compliant': return 'Non-Compliant';
        default: return 'Pending';
    }
}

function generateCategoryBreakdown(categories) {
    return Object.entries(categories).map(([key, category]) => {
        const isCompleted = (category.completed || 0) >= (category.required || 0);
        const color = isCompleted ? '#10b981' : '#ef4444';
        const progressText = (category.required || 0) > 0 ? `${category.completed || 0}/${category.required || 0} courses` : `${category.completed || 0} courses`;
        
        return `
            <div style="border: 1px solid #e5e7eb; border-radius: 8px; padding: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <h5 style="margin: 0; color: #374151;">${category.name || 'Unknown Category'}</h5>
                    <span style="font-weight: bold; color: ${color};">${progressText}</span>
                </div>
                <div style="background: #f3f4f6; border-radius: 8px; height: 8px; overflow: hidden;">
                    <div style="background: ${color}; height: 100%; width: ${(category.required || 0) > 0 ? Math.min(100, ((category.completed || 0) / (category.required || 1)) * 100) : 100}%; transition: width 0.3s ease;"></div>
                </div>
                <div style="text-align: right; margin-top: 5px; font-size: 0.85rem; color: #6b7280;">
                    ${(category.required || 0) > 0 ? (isCompleted ? '✅ Requirement met' : '❌ Need at least 1 course') : 'Additional courses'}
                </div>
            </div>
        `;
    }).join('');
}

function generateCourseHistory(courses) {
    if (!courses || courses.length === 0) {
        return '<tr><td colspan="7" style="padding: 20px; text-align: center; color: #6b7280;">No courses found</td></tr>';
    }
    
    return courses.map(course => {
        const statusColor = '#10b981';
        const date = new Date(course.date).toLocaleDateString();
        
        return `
            <tr>
                <td style="padding: 12px; border-bottom: 1px solid #e5e7eb;">${date}</td>
                <td style="padding: 12px; border-bottom: 1px solid #e5e7eb;">${course.title || 'N/A'}</td>
                <td style="padding: 12px; border-bottom: 1px solid #e5e7eb;">${course.provider || 'N/A'}</td>
                <td style="padding: 12px; text-align: center; border-bottom: 1px solid #e5e7eb;">${course.category || 'N/A'}</td>
                <td style="padding: 12px; text-align: center; border-bottom: 1px solid #e5e7eb; font-weight: bold;">${course.hours}</td>
                <td style="padding: 12px; text-align: center; border-bottom: 1px solid #e5e7eb;">
                    <span style="background: ${statusColor}; color: white; padding: 3px 8px; border-radius: 12px; font-size: 0.75rem; text-transform: capitalize;">
                        Completed
                    </span>
                </td>
            </tr>
        `;
    }).join('');
}

// Export individual report as CSV
function exportIndividualReportCSV() {
    if (!currentReportData) {
        alert('No report data available to export');
        return;
    }
    
    console.log('📊 Exporting individual report as CSV');
    
    // Prepare CSV content
    let csvContent = '';
    
    // Header information
    csvContent += `CPD Individual Report\n`;
    csvContent += `Member: ${currentReportData.member.display_name}\n`;
    csvContent += `Email: ${currentReportData.member.user_email}\n`;
    csvContent += `Year: ${currentReportData.year}\n`;
    csvContent += `Report Generated: ${new Date().toLocaleDateString()}\n\n`;
    
    // Summary
    csvContent += `SUMMARY\n`;
    csvContent += `Points Earned,Points Required,Progress,Compliance Status\n`;
    csvContent += `${currentReportData.total_earned},${currentReportData.required_points},${Math.round(currentReportData.progress_percentage)}%,${getComplianceText(currentReportData.compliance_status)}\n\n`;
    
    // Category breakdown
    csvContent += `CATEGORY BREAKDOWN\n`;
    csvContent += `Category,Points Earned,Points Required,Progress\n`;
    Object.entries(currentReportData.categories).forEach(([key, category]) => {
        const percentage = category.min_required > 0 ? Math.round((category.earned / category.min_required) * 100) : 0;
        csvContent += `"${category.name}",${category.earned},${category.min_required},${percentage}%\n`;
    });
    
    // Course history
    if (currentReportData.all_courses && currentReportData.all_courses.length > 0) {
        csvContent += `\nCOURSE HISTORY\n`;
        csvContent += `Date,Course,Provider,Category,Points,Status,Certificate\n`;
        currentReportData.all_courses.forEach(course => {
            const date = course.completion_date ? new Date(course.completion_date).toLocaleDateString() : new Date(course.created_at).toLocaleDateString();
            const courseName = (course.course_title || course.title || 'N/A').replace(/"/g, '""');
            const provider = (course.provider || 'N/A').replace(/"/g, '""');
            const category = (course.category_name || 'N/A').replace(/"/g, '""');
            const certificate = course.certificate_file ? 'Yes' : 'No';
            
            csvContent += `"${date}","${courseName}","${provider}","${category}",${course.cpd_points},"${course.status}","${certificate}"\n`;
        });
    } else {
        csvContent += `\nCOURSE HISTORY\n`;
        csvContent += `No courses found\n`;
    }
    
    // Create and download file
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const filename = `CPD_Report_${currentReportData.member.display_name.replace(/\s+/g, '_')}_${currentReportData.year}.csv`;
    
    if (link.download !== undefined) {
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        console.log('✅ CSV export completed:', filename);
    } else {
        alert('CSV export is not supported in this browser');
    }
}

// Placeholder functions for export and email
function exportIndividualReport() {
    alert('PDF export functionality will be implemented in the next phase');
}

function emailIndividualReport() {
    alert('Email report functionality will be implemented in the next phase');
}

// Function to highlight active report card
function setActiveReportCard(cardType) {
    console.log('🎯 Setting active report card:', cardType);
    
    // Remove active class from all cards
    document.querySelectorAll('.report-card').forEach(card => {
        card.style.border = '2px solid transparent';
        card.style.boxShadow = '0 10px 30px rgba(0,0,0,0.1)';
        card.style.transform = 'none';
    });
    
    // Add active styling to selected card
    const activeCard = document.getElementById(cardType + '-card');
    console.log('🎯 Active card element:', activeCard);
    
    if (activeCard) {
        let borderColor, shadowColor;
        switch(cardType) {
            case 'compliance':
                borderColor = '#667eea';
                shadowColor = 'rgba(102, 126, 234, 0.3)';
                break;
            case 'popularity':
                borderColor = '#10b981';
                shadowColor = 'rgba(16, 185, 129, 0.3)';
                break;
            case 'provider':
                borderColor = '#f59e0b';
                shadowColor = 'rgba(245, 158, 11, 0.3)';
                break;
        }
        
        activeCard.style.border = `2px solid ${borderColor}`;
        activeCard.style.boxShadow = `0 15px 40px ${shadowColor}`;
        activeCard.style.transform = 'translateY(-2px)';
        
        console.log('✅ Applied styling to compliance card:', {
            border: activeCard.style.border,
            boxShadow: activeCard.style.boxShadow,
            transform: activeCard.style.transform
        });
    } else {
        console.error('❌ Compliance card not found!');
    }
}

// Functions for the main report type buttons
function showComplianceReport() {
    
    // Show the compliance section and load data
    showReport('compliance');
    // Highlight the compliance card
    setActiveReportCard('compliance');
    loadComplianceData();
    
    // Update URL without page reload
    const url = new URL(window.location);
    url.searchParams.set('cpd_report', 'compliance');
    window.history.pushState({}, '', url);
    
    // Scroll to the report area
    document.getElementById('compliance-report').scrollIntoView({ behavior: 'smooth' });
}

function showCoursePopularity() {
    // Highlight the popularity card
    setActiveReportCard('popularity');
    
    // Show the categories section 
    showReport('categories');
    loadCategoriesData();
    
    // Update URL without page reload
    const url = new URL(window.location);
    url.searchParams.set('cpd_report', 'popularity');
    window.history.pushState({}, '', url);
    
    // Scroll to the report area
    document.getElementById('categories-report').scrollIntoView({ behavior: 'smooth' });
}

function showProviderAnalysis() {
    // Highlight the provider card
    setActiveReportCard('provider');
    
    // For now, same as course popularity - can be expanded later
    showReport('categories');
    loadCategoriesData();
    
    // Update URL without page reload
    const url = new URL(window.location);
    url.searchParams.set('cpd_report', 'provider');
    window.history.pushState({}, '', url);
    
    // Scroll to the report area
    document.getElementById('categories-report').scrollIntoView({ behavior: 'smooth' });
}

// Global variable to store current individual report data
var currentIndividualReportData = null;

// Export individual report as CSV
function exportIndividualReportCSV() {
    console.log('currentIndividualReportData', currentIndividualReportData);
    if (typeof currentIndividualReportData === 'undefined' || !currentIndividualReportData) {
        alert('No report data available to export. Please view a member report first.');
        return;
    }
    
    const data = currentIndividualReportData;
    let csvContent = '';
    
    // Header
    csvContent += `Individual Member CPD Report\n`;
    csvContent += `Generated: ${new Date().toLocaleDateString()}\n`;
    csvContent += `Year: ${data.year}\n\n`;
    
    // Member Information
    csvContent += `MEMBER INFORMATION\n`;
    csvContent += `Name,${data.member.first_name || ''} ${data.member.last_name || ''} (${data.member.display_name})\n`;
    csvContent += `Email,${data.member.user_email}\n`;
    csvContent += `Role,${data.member.role || 'N/A'}\n`;
    csvContent += `Membership Status,${data.member.membership_status}\n\n`;
    
    // CPD Summary
    csvContent += `CPD SUMMARY\n`;
    csvContent += `Points Earned,${data.total_earned}\n`;
    csvContent += `Points Required,${data.required_points}\n`;
    csvContent += `Progress,${data.progress_percentage.toFixed(1)}%\n`;
    csvContent += `Compliance Status,${data.compliance_status === 'compliant' ? 'Compliant' : 'Non-Compliant'}\n\n`;
    
    // CPD Progress by Category
    csvContent += `CPD PROGRESS BY CATEGORY\n`;
    csvContent += `Category,Required,Completed,Status\n`;
    Object.values(data.categories).forEach(category => {
        const status = category.completed >= category.required ? 'Completed' : 'Incomplete';
        csvContent += `${category.name},${category.required},${category.completed},${status}\n`;
    });
    csvContent += `\n`;
    
    // Completed Course History
    csvContent += `COMPLETED COURSE HISTORY\n`;
    csvContent += `Course Title,Provider,Hours,Category,Type,Date Completed\n`;
    data.all_courses.forEach(course => {
        csvContent += `"${course.title}","${course.provider}",${course.hours},"${course.category}","${course.courseType || 'Unknown'}","${course.date}"\n`;
    });
    
    // Download CSV
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `CPD_Report_${data.member.display_name.replace(/\s+/g, '_')}_${data.year}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Send individual report via email
function sendIndividualReportEmail() {
    if (typeof currentIndividualReportData === 'undefined' || !currentIndividualReportData) {
        alert('No report data available to send. Please view a member report first.');
        return;
    }
    
    const memberEmail = currentIndividualReportData.member.user_email;
    const memberName = currentIndividualReportData.member.display_name;
    
    if (!memberEmail) {
        alert('Member email not available');
        return;
    }
    
    // Get the HTML content from the individual-report-content div
    const reportContent = document.getElementById('individual-report-content');
    if (!reportContent) {
        alert('Report content not found. Please view a member report first.');
        return;
    }
    
    // Clone the content and remove action buttons
    const clonedContent = reportContent.cloneNode(true);
    const actionButtons = clonedContent.querySelectorAll('button[onclick*="exportIndividualReportCSV"], button[onclick*="sendIndividualReportEmail"], button[onclick*="style.display=\'none\'"]');
    actionButtons.forEach(button => button.remove());
    
    // Get the HTML string
    const htmlContent = clonedContent.innerHTML;
    
    if (!confirm(`Send CPD report to ${memberName} (${memberEmail})?`)) {
        return;
    }
    
    // Show loading state
    const button = event.target;
    const originalText = button.textContent;
    button.textContent = '📧 Sending...';
    button.disabled = true;
    
    fetch(window.iipm_reports_ajax.ajax_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'iipm_send_individual_report_email',
            user_id: currentIndividualReportData.member.ID,
            year: currentIndividualReportData.year,
            html_content: htmlContent,
            nonce: window.iipm_reports_ajax.nonce
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Report sent successfully!');
        } else {
            alert('❌ Failed to send report: ' + (data.data || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error sending email:', error);
        alert('❌ Error sending email. Please try again.');
    })
    .finally(() => {
        button.textContent = originalText;
        button.disabled = false;
    });
}

// CSS for loading spinner animation
const style = document.createElement('style');
style.textContent = `
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
`;
document.head.appendChild(style);
</script>

<?php get_footer(); ?> 