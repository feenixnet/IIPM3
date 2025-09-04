<?php
/*
Template Name: CPD Admin Portal
*/

// Check if user has admin permissions
if (!current_user_can('manage_iipm_members') && !current_user_can('administrator')) {
    wp_redirect(home_url());
    exit;
}

get_header();
?>

<div class="cpd-admin-portal">
    <!-- Admin Header -->
    <section class="admin-hero">
        <div class="container">
            <div class="hero-content">
                <h1 class="admin-title">CPD Administration Portal</h1>
                <p class="admin-subtitle">Manage CPD courses, review submissions, and generate reports</p>
            </div>
        </div>
    </section>

    <div class="container">
        <!-- Admin Navigation -->
        <div class="admin-nav">
            <button class="nav-btn active" data-section="submissions">
                <span class="icon">📋</span>
                Review Submissions
                <span class="badge" id="pending-count">0</span>
            </button>
            <button class="nav-btn" data-section="courses">
                <span class="icon">📚</span>
                Manage Courses
            </button>
            <button class="nav-btn" data-section="reports">
                <span class="icon">📊</span>
                CPD Reports
            </button>
            <button class="nav-btn" data-section="certificates">
                <span class="icon">🎓</span>
                Certificates
            </button>
            <button class="nav-btn" data-section="bulk-import">
                <span class="icon">📤</span>
                Bulk Import
            </button>
        </div>

        <!-- Submissions Review Section -->
        <div id="submissions-section" class="admin-section active">
            <div class="section-header">
                <h2>CPD Submissions Review</h2>
                <div class="section-filters">
                    <select id="submission-status">
                        <option value="pending">Pending Review</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
            </div>
            <div id="submissions-content">
                <div class="loading-spinner"></div>
                <p>Loading submissions...</p>
            </div>
        </div>

        <!-- Course Management Section -->
        <div id="courses-section" class="admin-section">
            <div class="section-header">
                <h2>Course Management</h2>
                <div class="section-actions">
                    <button class="btn btn-primary" id="add-course-btn">
                        <span class="icon">➕</span>
                        Add New Course
                    </button>
                </div>
            </div>
            
            <!-- Course Management Content -->
            <div class="course-management-content">
                <!-- Filters and Search -->
                <div class="course-filters">
                    <div class="filter-group">
                        <label for="category-filter">Category:</label>
                        <select id="category-filter" class="form-control">
                            <option value="all">All Categories</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="provider-filter">Provider:</label>
                        <select id="provider-filter" class="form-control">
                            <option value="all">All Providers</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="search-courses">Search:</label>
                        <div class="search-input-group">
                            <input type="text" id="search-courses" class="form-control" placeholder="Search courses...">
                            <button class="btn btn-primary" id="search-button" type="button">
                                <span class="btn-icon">🔍</span>
                                Search
                            </button>
                        </div>
                    </div>
                    <div class="filter-group">
                        <button class="btn btn-outline" id="clear-filters">Clear Filters</button>
                    </div>
                </div>

                <!-- Course List -->
                <div class="course-list-container">
                    <div class="course-list-header">
                        <div class="list-info">
                            <span id="course-count">Loading courses...</span>
                        </div>
                        <div class="list-actions">
                            <div class="view-toggle">
                                <button class="btn btn-outline view-btn active" id="card-view-btn" data-view="card">
                                    <span class="btn-icon">📋</span>
                                    Card View
                                </button>
                                <button class="btn btn-outline view-btn" id="table-view-btn" data-view="table">
                                    <span class="btn-icon">📊</span>
                                    Table View
                                </button>
                            </div>
                            <button class="btn btn-outline" id="refresh-courses">
                                <span class="btn-icon">🔄</span>
                                Refresh
                            </button>
                        </div>
                    </div>
                    
                    <div class="course-list" id="course-list">
                        <div class="loading-state">
                            <div class="loading-spinner"></div>
                            <p>Loading courses...</p>
                        </div>
                    </div>
                    
                    <!-- Table View -->
                    <div class="table-container" id="table-container" style="display: none;">
                        <table class="courses-table" id="courses-table">
                            <thead>
                                <tr>
                                    <th>Course Name</th>
                                    <th>Code</th>
                                    <th>Category</th>
                                    <th>Provider</th>
                                    <th>Duration (CPD mins)</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="courses-table-body">
                                <!-- Course rows will be populated here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reports Section -->
        <div id="reports-section" class="admin-section">
            <div class="section-header">
                <h2>CPD Reports</h2>
            </div>
            <div class="reports-grid">
                <div class="report-card">
                    <h3>Compliance Report</h3>
                    <p>View member CPD compliance status</p>
                    <button class="btn btn-outline" onclick="generateComplianceReport()">Generate Report</button>
                </div>
                <div class="report-card">
                    <h3>Course Popularity</h3>
                    <p>Most popular CPD courses</p>
                    <button class="btn btn-outline" onclick="generatePopularityReport()">Generate Report</button>
                </div>
                <div class="report-card">
                    <h3>Provider Analysis</h3>
                    <p>CPD points by provider</p>
                    <button class="btn btn-outline" onclick="generateProviderReport()">Generate Report</button>
                </div>
            </div>
        </div>

        <!-- Certificate Management Section -->
        <div id="certificates-section" class="admin-section">
            <div class="section-header">
                <h2>Certificate Management</h2>
                <div class="section-actions">
                    <button id="auto-generate-certificates" class="btn btn-success">
                        <span class="icon">🎓</span>
                        Auto-Generate Certificates
                    </button>
                </div>
            </div>
            
            <div class="certificate-filters">
                <div class="filters-row">
                    <div class="filter-group">
                        <label for="cert-year-filter">Year</label>
                        <select id="cert-year-filter">
                            <option value="">All Years</option>
                            <?php 
                            $current_year = date('Y');
                            for ($i = $current_year; $i >= $current_year - 5; $i--): 
                            ?>
                                <option value="<?php echo $i; ?>" <?php echo $i == $current_year ? 'selected' : ''; ?>><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="cert-status-filter">Status</label>
                        <select id="cert-status-filter">
                            <option value="">All Status</option>
                            <option value="issued">Issued</option>
                            <option value="pending">Pending</option>
                            <option value="revoked">Revoked</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="cert-compliance-filter">Compliance</label>
                        <select id="cert-compliance-filter">
                            <option value="">All</option>
                            <option value="compliant">Compliant</option>
                            <option value="non_compliant">Non-Compliant</option>
                            <option value="partial">Partial</option>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="button" id="refresh-certificates" class="btn btn-outline">
                            <span>🔄</span> Refresh
                        </button>
                    </div>
                </div>
            </div>
            
            <div id="certificates-content">
                <div class="loading-spinner"></div>
                <p>Loading certificates...</p>
            </div>
        </div>

        <!-- Bulk Import Section -->
        <div id="bulk-import-section" class="admin-section">
            <div class="section-header">
                <h2>Bulk Import LIA Courses</h2>
                <div class="header-notice">
                    <div class="notice-content">
                        <span class="notice-icon">💡</span>
                        <div class="notice-text">
                            <strong>Enhanced Course Import Available!</strong>
                            <p>Use our advanced Course Import Management system for better features, statistics, and bulk operations.</p>
                        </div>
                        <a href="<?php echo home_url('/course-management/'); ?>" class="btn btn-gradient">
                            <span class="btn-icon">🚀</span>
                            Go to Course Import
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="import-alternatives">
                <div class="alternative-option">
                    <h3>🔗 Advanced Course Import</h3>
                    <p>For comprehensive course management with enhanced features:</p>
                    <ul>
                        <li>✅ Better CSV validation and error handling</li>
                        <li>✅ Course statistics and analytics</li>
                        <li>✅ Provider-based organization</li>
                        <li>✅ Sample CSV templates</li>
                        <li>✅ Duplicate detection and management</li>
                    </ul>
                    <a href="<?php echo home_url('/course-management/'); ?>" class="btn btn-primary btn-large">
                        <span>📊</span> Use Advanced Import System
                    </a>
                </div>
                
                <div class="alternative-divider">
                    <span>OR</span>
                </div>
                
                <div class="alternative-option">
                    <h3>⚡ Quick Import (Legacy)</h3>
                    <p>For simple, direct course imports (basic functionality):</p>
                    
                    <div class="import-instructions">
                        <h4>Quick Import Instructions</h4>
                        <ul>
                            <li>Download the LIA course template CSV file</li>
                            <li>Fill in course details: course_name, provider, category, cpd_points, course_code, description</li>
                            <li>Upload the completed CSV file</li>
                            <li>Review and confirm the import</li>
                        </ul>
                        <a href="<?php echo get_template_directory_uri(); ?>/assets/lia-courses-template.csv" class="btn btn-outline" download>
                            📥 Download Template
                        </a>
                    </div>
                    
                    <form id="bulk-import-form" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="lia-courses-file">Select CSV File</label>
                            <input type="file" id="lia-courses-file" name="lia_courses_file" accept=".csv" required>
                        </div>
                        <button type="submit" class="btn btn-secondary">
                            <span class="btn-text">Import Courses (Legacy)</span>
                            <span class="btn-loading" style="display: none;">Importing...</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Add/Edit Course Modal -->
        <div class="modal" id="course-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="modal-title">Add New Course</h3>
                    <button class="modal-close" id="close-modal">×</button>
                </div>
                <div class="modal-body">
                    <form id="course-form">
                        <input type="hidden" id="course-id" name="course_id">
                        
                        <div class="form-group">
                            <label for="course-name">Course Name *</label>
                            <input type="text" id="course-name" name="course_name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="course-code">Course Code</label>
                            <input type="text" id="course-code" name="course_code" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="course-category">Category *</label>
                            <select id="course-category" name="course_category" class="form-control" required>
                                <option value="">Select Category</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="course-provider">Provider *</label>
                            <input type="text" id="course-provider" name="course_provider" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="course-duration">Duration (CPD mins) *</label>
                            <input type="number" id="course-duration" name="course_cpd_mins" class="form-control" min="1" step="1" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" id="cancel-course">Cancel</button>
                    <button type="button" class="btn btn-primary" id="save-course">
                        <span class="btn-icon">💾</span>
                        Save Course
                    </button>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div class="modal" id="delete-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Delete Course</h3>
                    <button class="modal-close" id="close-delete-modal">×</button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this course?</p>
                    <div class="course-preview" id="delete-course-preview"></div>
                    <p class="warning-text">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" id="cancel-delete">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirm-delete">
                        <span class="btn-icon">🗑️</span>
                        Delete Course
                    </button>
                </div>
            </div>
        </div>

        <!-- Review Modal -->
        <div id="review-modal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Review CPD Submission</h2>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <div id="review-content"></div>
                    <form id="review-form">
                        <input type="hidden" id="review-entry-id" name="entry_id">
                        <div class="form-group">
                            <label for="review-cpd-points">CPD Points to Award</label>
                            <input type="number" id="review-cpd-points" name="cpd_points" step="0.5" min="0">
                        </div>
                        <div class="form-group">
                            <label for="review-comments">Admin Comments</label>
                            <textarea id="review-comments" name="admin_comments" rows="3" placeholder="Add comments for the member..."></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn btn-danger" onclick="reviewSubmission('reject')">
                                Reject
                            </button>
                            <button type="button" class="btn btn-success" onclick="reviewSubmission('approve')">
                                Approve
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>


    </div>
</div>

<style>
/* CPD Admin Portal Styles */
.cpd-admin-portal {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background: #f8fafc;
    min-height: 100vh;
}

.admin-hero {
    background: linear-gradient(135deg, #1f2937 0%, #374151 100%);
    color: white;
    padding: 80px 0 60px;
    margin-top: -120px;
    padding-top: 200px;
}

.hero-content {
    text-align: center;
}

.admin-title {
    font-size: 3rem;
    font-weight: 700;
    margin-bottom: 16px;
}

.admin-subtitle {
    font-size: 1.25rem;
    opacity: 0.9;
}

.admin-nav {
    display: flex;
    gap: 8px;
    margin: 40px 0;
    background: white;
    padding: 8px;
    border-radius: 12px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.nav-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    background: transparent;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-weight: 500;
    color: #6b7280;
    position: relative;
}

.nav-btn:hover {
    background: #f3f4f6;
    color: #1f2937;
}

.nav-btn.active {
    background: #8b5a96;
    color: white;
}

.nav-btn .badge {
    background: #ef4444;
    color: white;
    border-radius: 10px;
    padding: 2px 8px;
    font-size: 0.75rem;
    font-weight: 600;
    min-width: 20px;
    text-align: center;
}

.admin-section {
    display: none;
    background: white;
    border-radius: 16px;
    padding: 32px;
    margin-bottom: 40px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.admin-section.active {
    display: block;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    padding-bottom: 20px;
    border-bottom: 2px solid #e5e7eb;
    background: linear-gradient(135deg, rgba(139, 90, 150, 0.02) 0%, rgba(139, 90, 150, 0.05) 100%);
    padding: 20px 24px;
    border-radius: 12px;
    margin-bottom: 0;
}

.section-header h2 {
    font-size: 1.75rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.section-header h2:before {
    content: "📚";
    font-size: 1.5rem;
}

.section-header .btn {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    padding: 12px 24px;
    border-radius: 8px;
}

.section-header .btn .icon {
    font-size: 1rem;
}

.section-filters {
    display: flex;
    gap: 12px;
}

.section-filters select {
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: white;
}

.submission-item {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 16px;
    transition: all 0.2s ease;
}

.submission-item:hover {
    border-color: #8b5a96;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(139, 90, 150, 0.15);
}

.submission-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
}

.submission-info h3 {
    font-size: 1.125rem;
    font-weight: 600;
    color: #1f2937;
    margin: 0 0 8px 0;
}

.submission-meta {
    font-size: 0.875rem;
    color: #6b7280;
    line-height: 1.4;
}

.submission-actions {
    display: flex;
    gap: 8px;
}

.submission-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 16px;
    padding: 16px;
    background: #f9fafb;
    border-radius: 8px;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.detail-label {
    font-size: 0.75rem;
    font-weight: 500;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.detail-value {
    font-weight: 500;
    color: #1f2937;
}

/* Courses Table Styles */
.courses-table {
    overflow-x: auto;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    margin-top: 20px;
}

.courses-table table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    min-width: 800px;
}

.courses-table th,
.courses-table td {
    padding: 16px 20px;
    text-align: left;
    border-bottom: 1px solid #f3f4f6;
    vertical-align: middle;
}

.courses-table th {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    font-weight: 600;
    color: #1e293b;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    position: sticky;
    top: 0;
    z-index: 10;
    border-bottom: 2px solid #e2e8f0;
}

.courses-table tbody tr {
    transition: all 0.2s ease;
}

.courses-table tbody tr:hover {
    background: #f8fafc;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.courses-table tbody tr:last-child td {
    border-bottom: none;
}

.courses-table td:first-child {
    font-weight: 500;
    color: #1e293b;
}

.courses-table td:nth-child(2) {
    color: #64748b;
    font-size: 0.9rem;
}

.courses-table td:nth-child(3) {
    font-size: 0.9rem;
}

.courses-table td:nth-child(4) {
    font-weight: 600;
    color: #8b5a96;
}

.courses-table .status {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    min-width: 80px;
    justify-content: center;
}

.courses-table .status.active {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: #065f46;
    border: 1px solid #34d399;
}

.courses-table .status.inactive {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #991b1b;
    border: 1px solid #f87171;
}

.courses-table .actions-cell {
    white-space: nowrap;
}

.courses-table .actions-cell .btn {
    margin-right: 8px;
    padding: 8px 16px;
    font-size: 0.8rem;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.courses-table .actions-cell .btn:last-child {
    margin-right: 0;
}

.courses-table .actions-cell .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.courses-table .actions-cell .btn-edit {
    background: linear-gradient(135deg, #8b5a96 0%, #7c5087 100%);
    color: white;
}

.courses-table .actions-cell .btn-edit:hover {
    background: linear-gradient(135deg, #7c5087 0%, #6d4578 100%);
}

.courses-table .actions-cell .btn-delete {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
}

.courses-table .actions-cell .btn-delete:hover {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
}

/* Loading State */
.courses-table .loading-row {
    text-align: center;
    padding: 40px 20px;
    color: #6b7280;
    font-style: italic;
}

/* Empty State */
.courses-table .empty-state {
    text-align: center;
    padding: 60px 20px;
}

.courses-table .empty-state h3 {
    color: #374151;
    margin-bottom: 8px;
}

.courses-table .empty-state p {
    color: #6b7280;
    margin-bottom: 20px;
}

.courses-table .empty-state a {
    color: #8b5a96;
    text-decoration: none;
    font-weight: 500;
}

.courses-table .empty-state a:hover {
    text-decoration: underline;
}

/* Search and Filter Styles */
.courses-filters {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 24px;
    margin: 20px 0;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
}

.filters-row {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr auto;
    gap: 20px;
    align-items: end;
    margin-bottom: 16px;
}

.search-group,
.filter-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.search-group label,
.filter-group label {
    font-weight: 500;
    color: #374151;
    font-size: 0.875rem;
}

.search-input-wrapper {
    position: relative;
}

.search-input-wrapper input {
    width: 100%;
    padding: 12px 16px 12px 44px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.875rem;
    transition: all 0.2s ease;
    background: #fafbfc;
}

.search-input-wrapper input:focus {
    outline: none;
    border-color: #8b5a96;
    box-shadow: 0 0 0 3px rgba(139, 90, 150, 0.1);
    background: white;
}

.search-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #6b7280;
    font-size: 1rem;
}

.filter-group select {
    padding: 12px 16px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.875rem;
    background: #fafbfc;
    cursor: pointer;
    transition: all 0.2s ease;
}

.filter-group select:focus {
    outline: none;
    border-color: #8b5a96;
    box-shadow: 0 0 0 3px rgba(139, 90, 150, 0.1);
    background: white;
}

.filter-actions {
    display: flex;
    gap: 8px;
    align-items: end;
}

.filter-actions .btn {
    padding: 12px 16px;
    font-size: 0.875rem;
    white-space: nowrap;
}

.filters-summary {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 16px;
    border-top: 1px solid #f3f4f6;
}

.summary-left {
    display: flex;
    align-items: center;
    gap: 20px;
}

.page-size-selector {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.875rem;
    color: #6b7280;
}

.page-size-selector select {
    padding: 6px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.875rem;
    background: white;
    cursor: pointer;
}

.page-size-selector select:focus {
    outline: none;
    border-color: #8b5a96;
    box-shadow: 0 0 0 2px rgba(139, 90, 150, 0.1);
}

#courses-count {
    font-weight: 500;
    color: #6b7280;
    font-size: 0.875rem;
}

.active-filters {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.filter-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    background: linear-gradient(135deg, #8b5a96 0%, #7c5087 100%);
    color: white;
    border-radius: 16px;
    font-size: 0.75rem;
    font-weight: 500;
}

.filter-tag .remove {
    cursor: pointer;
    font-weight: bold;
    margin-left: 4px;
    opacity: 0.8;
}

.filter-tag .remove:hover {
    opacity: 1;
}

/* Button Enhancements */
.btn-outline {
    background: transparent;
    border: 1px solid #d1d5db;
    color: #6b7280;
}

.btn-outline:hover {
    background: #f9fafb;
    border-color: #9ca3af;
    color: #374151;
}

.btn-secondary {
    background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
    color: white;
}

.btn-secondary:hover {
    background: linear-gradient(135deg, #4b5563 0%, #374151 100%);
    transform: translateY(-1px);
}

/* Pagination Styles */
.pagination-container {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px 24px;
    margin: 20px 0;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
}

.pagination-info {
    text-align: center;
    margin-bottom: 16px;
}

#pagination-info-text {
    font-size: 0.875rem;
    color: #6b7280;
    font-weight: 500;
}

.pagination-controls {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
}

.pagination-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border: 1px solid #d1d5db;
    background: white;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 1rem;
}

.pagination-btn:hover:not(:disabled) {
    border-color: #8b5a96;
    background: #f8f9fa;
    transform: translateY(-1px);
}

.pagination-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: #f9fafb;
}

.page-numbers {
    display: flex;
    gap: 4px;
    margin: 0 8px;
}

.page-number {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 40px;
    padding: 0 8px;
    border: 1px solid #d1d5db;
    background: white;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.875rem;
    font-weight: 500;
    color: #374151;
}

.page-number:hover {
    border-color: #8b5a96;
    background: #f8f9fa;
    transform: translateY(-1px);
}

.page-number.active {
    background: linear-gradient(135deg, #8b5a96 0%, #7c5087 100%);
    border-color: #8b5a96;
    color: white;
    font-weight: 600;
}

.page-number.ellipsis {
    border: none;
    background: transparent;
    cursor: default;
    color: #9ca3af;
}

.page-number.ellipsis:hover {
    transform: none;
    background: transparent;
    border: none;
}

.pagination-jump {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    padding-top: 16px;
    border-top: 1px solid #f3f4f6;
}

.pagination-jump label {
    font-size: 0.875rem;
    color: #6b7280;
    font-weight: 500;
}

.pagination-jump input {
    width: 60px;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.875rem;
    text-align: center;
}

.pagination-jump input:focus {
    outline: none;
    border-color: #8b5a96;
    box-shadow: 0 0 0 2px rgba(139, 90, 150, 0.1);
}

.btn-small {
    padding: 8px 16px;
    font-size: 0.875rem;
    border-radius: 6px;
}

/* Success Modal Styles */
.success-modal {
    z-index: 10001;
}

.success-content {
    max-width: 450px;
    text-align: center;
    padding: 40px 30px;
    border-radius: 16px;
    background: white;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.success-icon {
    margin-bottom: 24px;
}

.checkmark-wrapper {
    margin: 0 auto;
    width: 80px;
    height: 80px;
}

.checkmark {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: block;
    stroke-width: 2;
    stroke: #10b981;
    stroke-miterlimit: 10;
    margin: 0 auto;
    box-shadow: inset 0 0 0 #10b981;
    animation: fill 0.4s ease-in-out 0.4s forwards, scale 0.3s ease-in-out 0.9s both;
}

.checkmark-circle {
    stroke-dasharray: 166;
    stroke-dashoffset: 166;
    stroke-width: 2;
    stroke-miterlimit: 10;
    stroke: #10b981;
    fill: none;
    animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
}

.checkmark-check {
    stroke-dasharray: 48;
    stroke-dashoffset: 48;
    stroke-width: 3;
    stroke-miterlimit: 10;
    stroke: #10b981;
    fill: none;
    animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
}

@keyframes stroke {
    100% {
        stroke-dashoffset: 0;
    }
}

@keyframes scale {
    0%, 100% {
        transform: none;
    }
    50% {
        transform: scale3d(1.1, 1.1, 1);
    }
}

@keyframes fill {
    100% {
        box-shadow: inset 0 0 0 30px #10b981;
    }
}

.success-text h2 {
    color: #065f46;
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 12px;
}

.success-text p {
    color: #6b7280;
    font-size: 1rem;
    line-height: 1.5;
    margin-bottom: 0;
}

.success-actions {
    margin-top: 32px;
}

.success-actions .btn {
    min-width: 120px;
    padding: 12px 24px;
    font-weight: 500;
}

/* Alert Styles */
.alert {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 16px 20px;
    border-radius: 8px;
    font-weight: 500;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    z-index: 10000;
    max-width: 400px;
    animation: slideInRight 0.3s ease-out;
}

.alert-success {
    background: #10b981;
    color: white;
    border: 1px solid #059669;
}

.alert-error {
    background: #ef4444;
    color: white;
    border: 1px solid #dc2626;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Bulk Import Navigation Styles */
.header-notice {
    margin-top: 20px;
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    border-radius: 12px;
    padding: 20px;
    color: white;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
}

.notice-content {
    display: flex;
    align-items: center;
    gap: 16px;
}

.notice-icon {
    font-size: 1.5rem;
    flex-shrink: 0;
}

.notice-text {
    flex: 1;
}

.notice-text strong {
    font-size: 1.1rem;
    display: block;
    margin-bottom: 4px;
}

.notice-text p {
    margin: 0;
    opacity: 0.9;
    font-size: 0.9rem;
}

.btn-gradient {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.btn-gradient:hover {
    background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}

.btn-icon {
    font-size: 1rem;
}

.import-alternatives {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: 30px;
    align-items: start;
    margin-top: 30px;
}

.alternative-option {
    background: white;
    border: 2px solid #e5e7eb;
    border-radius: 16px;
    padding: 30px;
    transition: all 0.3s ease;
}

.alternative-option:hover {
    border-color: #3b82f6;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
    transform: translateY(-2px);
}

.alternative-option h3 {
    color: #1f2937;
    margin: 0 0 16px 0;
    font-size: 1.25rem;
    font-weight: 700;
}

.alternative-option p {
    color: #6b7280;
    margin: 0 0 16px 0;
    line-height: 1.5;
}

.alternative-option ul {
    margin: 0 0 24px 0;
    padding-left: 0;
    list-style: none;
}

.alternative-option ul li {
    color: #374151;
    margin-bottom: 8px;
    padding-left: 0;
    font-size: 0.9rem;
}

.alternative-divider {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px 0;
}

.alternative-divider span {
    background: #f3f4f6;
    color: #6b7280;
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.875rem;
    border: 2px solid #e5e7eb;
}

.btn-large {
    padding: 16px 24px;
    font-size: 1rem;
    border-radius: 10px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-large span {
    font-size: 1.2rem;
}

.import-instructions {
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    margin: 20px 0;
}

.import-instructions h4 {
    color: #1f2937;
    margin: 0 0 12px 0;
    font-size: 1rem;
    font-weight: 600;
}

.import-instructions ul {
    margin: 0 0 16px 0;
    padding-left: 20px;
    color: #6b7280;
}

.import-instructions ul li {
    margin-bottom: 6px;
    font-size: 0.9rem;
}

/* Course Management Content Styles */
.course-management-content {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

/* Course Filters */
.course-filters {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
    padding: 20px;
    background: #f8fafc;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    flex-wrap: wrap;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    min-width: 150px;
}

.filter-group label {
    font-weight: 500;
    color: #374151;
    margin-bottom: 6px;
    font-size: 0.875rem;
}

.search-input-group {
    display: flex;
    gap: 8px;
    align-items: stretch;
}

.search-input-group .form-control {
    flex: 1;
    min-width: 0;
}

.search-input-group .btn {
    flex-shrink: 0;
    white-space: nowrap;
}

/* Course List Container */
.course-list-container {
    background: white;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
}

.course-list-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.list-info {
    font-weight: 500;
    color: #6b7280;
}

.list-actions {
    display: flex;
    gap: 12px;
    align-items: center;
}

.view-toggle {
    display: flex;
    gap: 4px;
}

.view-btn {
    padding: 8px 16px;
    font-size: 0.875rem;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.view-btn.active {
    background: #8b5a96;
    color: white;
    border-color: #8b5a96;
}

/* Course List */
.course-list {
    padding: 20px;
    min-height: 200px;
}

.loading-state {
    text-align: center;
    padding: 40px 20px;
    color: #6b7280;
}

.loading-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f4f6;
    border-top: 4px solid #8b5a96;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 16px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Course Cards */
.course-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 16px;
    transition: all 0.2s ease;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.course-card:hover {
    border-color: #8b5a96;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(139, 90, 150, 0.15);
}

.course-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.course-name {
    font-size: 1.125rem;
    font-weight: 600;
    color: #1f2937;
    margin: 0;
}

.course-actions {
    display: flex;
    gap: 8px;
}

.course-meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}

.meta-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.meta-label {
    font-size: 0.75rem;
    font-weight: 500;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.meta-value {
    font-weight: 500;
    color: #1f2937;
}

/* Table View */
.table-container {
    overflow-x: auto;
}

.courses-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}

.courses-table th,
.courses-table td {
    padding: 16px 20px;
    text-align: left;
    border-bottom: 1px solid #f3f4f6;
    vertical-align: middle;
}

.courses-table th {
    background: #f8fafc;
    font-weight: 600;
    color: #1e293b;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #e2e8f0;
}

.courses-table tbody tr {
    transition: all 0.2s ease;
}

.courses-table tbody tr:hover {
    background: #f8fafc;
}

.courses-table td:first-child {
    font-weight: 500;
    color: #1e293b;
}

.course-name-cell {
    font-weight: 500;
    color: #1e293b;
}

.course-description-small {
    font-size: 0.875rem;
    color: #6b7280;
    margin-top: 4px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Form Controls */
.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.875rem;
    transition: all 0.2s ease;
    background: white;
}

.form-control:focus {
    outline: none;
    border-color: #8b5a96;
    box-shadow: 0 0 0 3px rgba(139, 90, 150, 0.1);
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
}

.modal-content {
    background-color: white;
    margin: 5% auto;
    border-radius: 16px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow: hidden;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    display: flex;
    flex-direction: column;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24px 32px;
    border-bottom: 1px solid #e5e7eb;
    background: #f8fafc;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
    color: #1f2937;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #6b7280;
    padding: 4px;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.modal-close:hover {
    background: #f3f4f6;
    color: #1f2937;
}

.modal-body {
    padding: 32px;
    overflow-y: auto;
    flex: 1;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 24px 32px;
    border-top: 1px solid #e5e7eb;
    background: #f8fafc;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    color: #374151;
    font-size: 0.875rem;
}

/* Button Styles */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.875rem;
    text-align: center;
    justify-content: center;
}

.btn-primary {
    background: linear-gradient(135deg, #8b5a96 0%, #7c5087 100%);
    color: white;
    box-shadow: 0 2px 4px rgba(139, 90, 150, 0.2);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #7c5087 0%, #6d4578 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(139, 90, 150, 0.3);
}

.btn-outline {
    background: transparent;
    border: 1px solid #d1d5db;
    color: #374151;
}

.btn-outline:hover {
    background: #f9fafb;
    border-color: #9ca3af;
    color: #1f2937;
}

.btn-danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
}

.btn-danger:hover {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    transform: translateY(-1px);
}

.btn-icon {
    font-size: 1rem;
}

/* Warning Text */
.warning-text {
    color: #ef4444;
    font-size: 0.875rem;
    font-weight: 500;
    margin-top: 12px;
}

.course-preview {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 16px;
    margin: 16px 0;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .course-filters {
        flex-direction: column;
        gap: 16px;
    }
    
    .filter-group {
        min-width: auto;
    }
    
    .course-list-header {
        flex-direction: column;
        gap: 16px;
        align-items: flex-start;
    }
    
    .list-actions {
        width: 100%;
        justify-content: space-between;
    }
    
    .course-meta {
        grid-template-columns: 1fr;
    }
    
    .modal-content {
        width: 95%;
        margin: 10% auto;
    }
    
    .modal-header,
    .modal-body,
    .modal-footer {
        padding: 20px;
    }
}

/* Mobile Responsive */
@media (max-width: 1024px) {
    .filters-row {
        grid-template-columns: 1fr;
        gap: 16px;
    }
    
    .filter-actions {
        justify-content: flex-start;
    }
    
    .filters-summary {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    
    .summary-left {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    
    .pagination-controls {
        flex-wrap: wrap;
        gap: 6px;
    }
    
    .pagination-jump {
        flex-direction: column;
        gap: 12px;
        text-align: center;
    }
}

@media (max-width: 768px) {
    .courses-table {
        margin: 20px -20px 0;
        border-radius: 0;
        border-left: none;
        border-right: none;
    }
    
    .courses-table th,
    .courses-table td {
        padding: 12px 16px;
        font-size: 0.85rem;
    }
    
    .courses-table .actions-cell .btn {
        padding: 6px 12px;
        font-size: 0.75rem;
        margin-right: 4px;
    }
    
    .courses-filters {
        margin: 20px -20px;
        border-radius: 0;
        border-left: none;
        border-right: none;
    }
    
    .pagination-container {
        margin: 20px -20px;
        border-radius: 0;
        border-left: none;
        border-right: none;
        padding: 16px 20px;
    }
    
    .pagination-btn,
    .page-number {
        width: 36px;
        height: 36px;
        font-size: 0.75rem;
    }
    
    .page-numbers {
        margin: 0 4px;
        gap: 2px;
    }
    
    .pagination-jump input {
        width: 50px;
    }
    
    /* Mobile styles for bulk import navigation */
    .header-notice {
        margin: 20px -20px 20px -20px;
        border-radius: 0;
        padding: 16px 20px;
    }
    
    .notice-content {
        flex-direction: column;
        gap: 12px;
        text-align: center;
    }
    
    .import-alternatives {
        grid-template-columns: 1fr;
        gap: 20px;
        margin: 20px -20px 0;
    }
    
    .alternative-option {
        margin: 0 20px;
        padding: 20px;
        border-radius: 12px;
    }
    
    .alternative-divider {
        display: none;
    }
    
    .btn-large {
        width: 100%;
        justify-content: center;
        padding: 14px 20px;
    }
    
    .btn-gradient {
        width: 100%;
        justify-content: center;
        padding: 10px 16px;
    }
}

.reports-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 24px;
}

.report-card {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 24px;
    text-align: center;
    transition: all 0.2s ease;
}

.report-card:hover {
    border-color: #8b5a96;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(139, 90, 150, 0.15);
}

.report-card h3 {
    font-size: 1.125rem;
    font-weight: 600;
    color: #1f2937;
    margin: 0 0 12px 0;
}

.report-card p {
    color: #6b7280;
    margin: 0 0 20px 0;
}

.import-instructions {
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 32px;
}

.import-instructions h3 {
    color: #0369a1;
    margin: 0 0 16px 0;
}

.import-instructions ul {
    color: #0c4a6e;
    margin: 16px 0;
    padding-left: 20px;
}

.import-instructions li {
    margin-bottom: 8px;
}

/* Button Styles */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.875rem;
}

.btn-primary {
    background: linear-gradient(135deg, #8b5a96 0%, #7c5087 100%);
    color: white;
    box-shadow: 0 2px 4px rgba(139, 90, 150, 0.2);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #7c5087 0%, #6d4578 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(139, 90, 150, 0.3);
}

.btn-secondary {
    background: #6b7280;
    color: white;
}

.btn-secondary:hover {
    background: #4b5563;
}

.btn-success {
    background: #10b981;
    color: white;
}

.btn-success:hover {
    background: #059669;
}

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
}

.btn-outline {
    background: transparent;
    border: 1px solid #d1d5db;
    color: #374151;
}

.btn-outline:hover {
    background: #f9fafb;
    border-color: #9ca3af;
}

.btn-small {
    padding: 6px 12px;
    font-size: 0.75rem;
}

/* Form Styles */
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    color: #374151;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 1rem;
    transition: border-color 0.2s;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #8b5a96;
    box-shadow: 0 0 0 3px rgba(139, 90, 150, 0.1);
}

.form-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
}

.modal-content {
    background-color: white;
    margin: 5% auto;
    border-radius: 16px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24px 32px;
    border-bottom: 1px solid #e5e7eb;
}

.modal-header h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
    color: #1f2937;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #6b7280;
    padding: 4px;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.modal-close:hover {
    background: #f3f4f6;
    color: #1f2937;
}

.modal-body {
    padding: 32px;
}

/* Loading Spinner */
.loading-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f4f6;
    border-top: 4px solid #8b5a96;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 16px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsive Design */
@media (max-width: 768px) {
    .admin-nav {
        flex-direction: column;
    }
    
    .section-header {
        flex-direction: column;
        gap: 16px;
        align-items: flex-start;
    }
    
    .submission-header {
        flex-direction: column;
        gap: 16px;
    }
    
    .submission-actions {
        width: 100%;
        justify-content: flex-start;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .reports-grid {
        grid-template-columns: 1fr;
    }
    
    .admin-title {
        font-size: 2rem;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    let currentSection = 'submissions';
    let allCourses = [];
    let filteredCourses = [];
    let availableProviders = [];
    let currentPage = 1;
    let pageSize = 25;
    let totalPages = 1;
    
    // Navigation handling
    $('.nav-btn').click(function() {
        const section = $(this).data('section');
        switchSection(section);
    });
    
    function switchSection(section) {
        $('.nav-btn').removeClass('active');
        $(`.nav-btn[data-section="${section}"]`).addClass('active');
        
        $('.admin-section').removeClass('active');
        $(`#${section}-section`).addClass('active');
        
        currentSection = section;
        
        // Load section data
        switch(section) {
            case 'submissions':
                loadSubmissions();
                break;
            case 'courses':
                loadCourses();
                break;
            case 'reports':
                // Reports are static for now
                break;
            case 'bulk-import':
                // Bulk import is static form
                break;
        }
    }
    
    // Load CPD submissions
    function loadSubmissions() {
        const status = $('#submission-status').val() || 'pending';
        
        $.ajax({
            url: iipm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'iipm_get_cpd_submissions',
                status: status,
                nonce: iipm_ajax.cpd_admin_nonce
            },
            success: function(response) {
                if (response.success) {
                    renderSubmissions(response.data.submissions);
                    updatePendingCount();
                } else {
                    $('#submissions-content').html('<p>Error loading submissions</p>');
                }
            },
            error: function() {
                $('#submissions-content').html('<p>Failed to load submissions</p>');
            }
        });
    }
    
    // Render submissions
    function renderSubmissions(submissions) {
        let html = '';
        
        if (submissions && submissions.length > 0) {
            submissions.forEach(function(submission) {
                html += `
                    <div class="submission-item">
                        <div class="submission-header">
                            <div class="submission-info">
                                <h3>${submission.activity_title}</h3>
                                <div class="submission-meta">
                                    Member: ${submission.display_name} (${submission.user_email})<br>
                                    Provider: ${submission.external_provider || 'N/A'}<br>
                                    Submitted: ${new Date(submission.created_at).toLocaleDateString()}
                                </div>
                            </div>
                            <div class="submission-actions">
                                <button class="btn btn-primary btn-small" onclick="reviewSubmissionModal(${submission.id})">
                                    Review
                                </button>
                            </div>
                        </div>
                        <div class="submission-details">
                            <div class="detail-item">
                                <span class="detail-label">Category</span>
                                <span class="detail-value">${submission.category_name || 'N/A'}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">CPD Points</span>
                                <span class="detail-value">${submission.cpd_points}</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Completion Date</span>
                                <span class="detail-value">${submission.completion_date}</span>
                            </div>
                        </div>
                        ${submission.description ? `<div class="submission-description"><strong>Description:</strong> ${submission.description}</div>` : ''}
                        ${submission.certificate_path ? `<div class="submission-certificate"><a href="${submission.certificate_path}" target="_blank" class="btn btn-outline btn-small">View Certificate</a></div>` : ''}
                    </div>
                `;
            });
        } else {
            html = '<p>No submissions found.</p>';
        }
        
        $('#submissions-content').html(html);
    }
    
    // Course Management Variables
    let courses = [];
    let categories = [];
    let providers = [];
    let currentCourseId = null;
    let isLoading = false;
    let hasMoreCourses = true;
    let coursesPerPage = 10;
    let currentView = 'card'; // 'card' or 'table'
    
    // Course Management Functions
    function loadCourses() {
        if (isLoading) return;
        
        isLoading = true;
        showLoading();
        
        const ajaxData = {
            action: 'iipm_get_all_courses_paginated',
            page: currentPage,
            per_page: coursesPerPage,
            nonce: iipm_ajax.nonce
        };
        
        // Add filter parameters
        const categoryFilter = $('#category-filter').val();
        const providerFilter = $('#provider-filter').val();
        const searchTerm = $('#search-courses').val();
        
        if (categoryFilter && categoryFilter !== 'all') {
            ajaxData.category_filter = categoryFilter;
        }
        if (providerFilter && providerFilter !== 'all') {
            ajaxData.provider_filter = providerFilter;
        }
        if (searchTerm) {
            ajaxData.search_term = searchTerm;
        }
        
        $.ajax({
            url: iipm_ajax.ajax_url,
            type: 'POST',
            data: ajaxData,
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    
                    if (currentPage === 1) {
                        courses = data.courses || [];
                    } else {
                        courses = courses.concat(data.courses || []);
                    }
                    
                    hasMoreCourses = data.pagination && data.pagination.page < data.pagination.total_pages;
                    updateCourseCount(data.pagination);
                    displayCourses(courses);
                    
                    if (currentPage === 1) {
                        loadCategories();
                        loadProviders();
                    }
                } else {
                    showError('Failed to load courses');
                }
            },
            error: function() {
                showError('Failed to load courses');
            },
            complete: function() {
                isLoading = false;
                hideLoading();
            }
        });
    }
    
    function loadCategories() {
        $.ajax({
            url: iipm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'iipm_get_categories',
                nonce: iipm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    categories = response.data;
                    updateCategorySelects();
                }
            }
        });
    }
    
    function loadProviders() {
        $.ajax({
            url: iipm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'iipm_get_providers',
                nonce: iipm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    providers = response.data;
                    updateProviderSelects();
                }
            }
        });
    }
    
    function updateCategorySelects() {
        const $selects = $('#category-filter, #course-category');
        $selects.each(function() {
            const $select = $(this);
            const currentValue = $select.val();
            
            // Clear existing options except first
            $select.find('option:not(:first)').remove();
            
            // Add category options
            categories.forEach(function(category) {
                $select.append(`<option value="${category.id}">${category.name}</option>`);
            });
            
            // Restore previous value if it exists
            if (currentValue) {
                $select.val(currentValue);
            }
        });
    }
    
    function updateProviderSelects() {
        const $selects = $('#provider-filter');
        $selects.each(function() {
            const $select = $(this);
            const currentValue = $select.val();
            
            // Clear existing options except first
            $select.find('option:not(:first)').remove();
            
            // Add provider options
            providers.forEach(function(provider) {
                $select.append(`<option value="${provider}">${provider}</option>`);
            });
            
            // Restore previous value if it exists
            if (currentValue) {
                $select.val(currentValue);
            }
        });
    }
    
    // Update pending count badge
    function updatePendingCount() {
        $.ajax({
            url: iipm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'iipm_get_cpd_submissions',
                status: 'pending',
                nonce: iipm_ajax.cpd_admin_nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#pending-count').text(response.data.total_submissions);
                }
            }
        });
    }
    
    // Review submission modal
    window.reviewSubmissionModal = function(entryId) {
        // Load submission details and show modal
        $.ajax({
            url: iipm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'iipm_get_cpd_submission_details',
                entry_id: entryId,
                nonce: iipm_ajax.cpd_admin_nonce
            },
            success: function(response) {
                if (response.success) {
                    const submission = response.data;
                    $('#review-entry-id').val(entryId);
                    $('#review-cpd-points').val(submission.cpd_points);
                    
                    const reviewHtml = `
                        <div class="review-details">
                            <h3>${submission.activity_title}</h3>
                            <p><strong>Member:</strong> ${submission.display_name}</p>
                            <p><strong>Provider:</strong> ${submission.external_provider || 'N/A'}</p>
                            <p><strong>Category:</strong> ${submission.category_name || 'N/A'}</p>
                            <p><strong>Completion Date:</strong> ${submission.completion_date}</p>
                            ${submission.description ? `<p><strong>Description:</strong> ${submission.description}</p>` : ''}
                            ${submission.certificate_path ? `<p><strong>Certificate:</strong> <a href="${submission.certificate_path}" target="_blank">View Certificate</a></p>` : ''}
                        </div>
                    `;
                    
                    $('#review-content').html(reviewHtml);
                    $('#review-modal').show();
                }
            }
        });
    };
    
    // Review submission
    window.reviewSubmission = function(action) {
        const entryId = $('#review-entry-id').val();
        const cpdPoints = $('#review-cpd-points').val();
        const comments = $('#review-comments').val();
        
        $.ajax({
            url: iipm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'iipm_review_cpd_submission',
                entry_id: entryId,
                action_type: action,
                cpd_points: cpdPoints,
                admin_comments: comments,
                nonce: iipm_ajax.cpd_admin_nonce
            },
            success: function(response) {
                if (response.success) {
                    showAlert(response.data.message, 'success');
                    $('#review-modal').hide();
                    loadSubmissions();
                } else {
                    showAlert('Error: ' + response.data, 'error');
                }
            },
            error: function() {
                showAlert('An error occurred', 'error');
            }
        });
    };
    

    
    // Bulk import form
    $('#bulk-import-form').submit(function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $submitBtn = $form.find('button[type="submit"]');
        const formData = new FormData(this);
        formData.append('action', 'iipm_bulk_import_lia_courses');
        formData.append('nonce', iipm_ajax.cpd_admin_nonce);
        
        $submitBtn.addClass('loading');
        
        $.ajax({
            url: iipm_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    showAlert(`Import completed: ${data.successful} successful, ${data.failed} failed`, 'success');
                    $form[0].reset();
                } else {
                    showAlert('Error: ' + response.data, 'error');
                }
            },
            error: function() {
                showAlert('Import failed', 'error');
            },
            complete: function() {
                $submitBtn.removeClass('loading');
            }
        });
    });
    
    // Modal handlers
    $('.modal-close').click(function() {
        $('.modal').hide();
    });
    
    $('.modal').click(function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });
    
    // Filter change handler
    $('#submission-status').change(function() {
        loadSubmissions();
    });
    
    // Course Management Display Functions
    function displayCourses(coursesToShow) {
        if (currentView === 'card') {
            displayCardView(coursesToShow);
        } else {
            displayTableView(coursesToShow);
        }
        setupCourseEventListeners();
    }
    
    function displayCardView(coursesToShow) {
        let html = '';
        
        if (coursesToShow && coursesToShow.length > 0) {
            coursesToShow.forEach(function(course) {
                html += `
                    <div class="course-card" data-course-id="${course.id}">
                        <div class="course-card-header">
                            <h3 class="course-name">${course.course_name}</h3>
                            <div class="course-actions">
                                <button class="btn btn-outline btn-sm edit-course-btn" data-course-id="${course.id}">
                                    <span class="btn-icon">✏️</span>
                                    Edit
                                </button>
                                <button class="btn btn-danger btn-sm delete-course-btn" data-course-id="${course.id}">
                                    <span class="btn-icon">🗑️</span>
                                    Delete
                                </button>
                            </div>
                        </div>
                        <div class="course-meta">
                            <div class="meta-item">
                                <span class="meta-label">Code</span>
                                <span class="meta-value">${course.LIA_Code || 'N/A'}</span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Category</span>
                                <span class="meta-value">${course.course_category || 'N/A'}</span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Provider</span>
                                <span class="meta-value">${course.crs_provider || 'N/A'}</span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Duration</span>
                                <span class="meta-value">${course.course_cpd_mins} CPD mins</span>
                            </div>
                        </div>
                    </div>
                `;
            });
        } else {
            html = `
                <div class="empty-state">
                    <h3>No courses found</h3>
                    <p>Start by adding your first course to the database.</p>
                    <button class="btn btn-primary" id="add-first-course">
                        <span class="btn-icon">➕</span>
                        Add First Course
                    </button>
                </div>
            `;
        }
        
        $('#course-list').html(html);
    }
    
    function displayTableView(coursesToShow) {
        let html = '';
        
        if (coursesToShow && coursesToShow.length > 0) {
            coursesToShow.forEach(function(course) {
                html += `
                    <tr data-course-id="${course.id}">
                        <td class="course-name-cell">
                            <strong>${course.course_name}</strong>
                        </td>
                        <td>${course.LIA_Code || 'N/A'}</td>
                        <td>${course.course_category || 'N/A'}</td>
                        <td>${course.crs_provider || 'N/A'}</td>
                        <td>${course.course_cpd_mins} CPD mins</td>
                        <td>
                            <button class="btn btn-outline btn-sm edit-course-btn" data-course-id="${course.id}">
                                <span class="btn-icon">✏️</span>
                                Edit
                            </button>
                            <button class="btn btn-danger btn-sm delete-course-btn" data-course-id="${course.id}">
                                <span class="btn-icon">🗑️</span>
                                Delete
                            </button>
                        </td>
                    </tr>
                `;
            });
        } else {
            html = `
                <tr>
                    <td colspan="6" class="text-center">
                        <div class="empty-state">
                            <h3>No courses found</h3>
                            <p>Start by adding your first course to the database.</p>
                            <button class="btn btn-primary" id="add-first-course">
                                <span class="btn-icon">➕</span>
                                Add First Course
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }
        
        $('#courses-table-body').html(html);
    }
    
    function setupCourseEventListeners() {
        // Edit course buttons
        $('.edit-course-btn').off('click').on('click', function() {
            const courseId = $(this).data('course-id');
            editCourse(courseId);
        });
        
        // Delete course buttons
        $('.delete-course-btn').off('click').on('click', function() {
            const courseId = $(this).data('course-id');
            deleteCourse(courseId);
        });
        
        // Add first course button
        $('#add-first-course').off('click').on('click', function() {
            openAddCourseModal();
        });
    }
    
    function editCourse(courseId) {
        const course = courses.find(c => c.id == courseId);
        if (!course) return;
        
        currentCourseId = courseId;
        $('#modal-title').text('Edit Course');
        $('#course-id').val(course.id);
        $('#course-name').val(course.course_name);
        $('#course-code').val(course.LIA_Code || '');
        $('#course-provider').val(course.crs_provider || '');
        $('#course-duration').val(course.course_cpd_mins || '');
        
        // Set category
        const categoryId = categories.find(c => c.name === course.course_category)?.id;
        if (categoryId) {
            $('#course-category').val(categoryId);
        }
        
        $('#course-modal').show();
    }
    
    function deleteCourse(courseId) {
        const course = courses.find(c => c.id == courseId);
        if (!course) return;
        
        currentCourseId = courseId;
        $('#delete-course-preview').html(`
            <strong>${course.course_name}</strong><br>
            <small>Provider: ${course.crs_provider || 'N/A'}</small>
        `);
        $('#delete-modal').show();
    }
    
    function openAddCourseModal() {
        currentCourseId = null;
        $('#modal-title').text('Add New Course');
        $('#course-form')[0].reset();
        $('#course-modal').show();
    }
    
    function saveCourse() {
        const formData = {
            action: currentCourseId ? 'iipm_update_course_v1' : 'iipm_add_course',
            course_id: $('#course-id').val(),
            course_name: $('#course-name').val(),
            course_code: $('#course-code').val(),
            course_category: $('#course-category').val(),
            course_provider: $('#course-provider').val(),
            course_cpd_mins: $('#course-duration').val(),
            nonce: iipm_ajax.nonce
        };
        
        // Validate required fields
        if (!formData.course_name || !formData.course_category || !formData.course_provider || !formData.course_cpd_mins) {
            showError('Please fill in all required fields');
            return;
        }
        
        $.ajax({
            url: iipm_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showSuccess(currentCourseId ? 'Course updated successfully!' : 'Course added successfully!');
                    $('#course-modal').hide();
                    currentPage = 1;
                    courses = [];
                    loadCourses();
                } else {
                    showError(response.data || 'Failed to save course');
                }
            },
            error: function() {
                showError('Failed to save course');
            }
        });
    }
    
    function confirmDelete() {
        $.ajax({
            url: iipm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'iipm_delete_course_v1',
                course_id: currentCourseId,
                nonce: iipm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showSuccess('Course deleted successfully!');
                    $('#delete-modal').hide();
                    currentPage = 1;
                    courses = [];
                    loadCourses();
                } else {
                    showError(response.data || 'Failed to delete course');
                }
            },
            error: function() {
                showError('Failed to delete course');
            }
        });
    }
    
    function filterCourses() {
        currentPage = 1;
        courses = [];
        loadCourses();
    }
    
    function clearFilters() {
        $('#category-filter').val('all');
        $('#provider-filter').val('all');
        $('#search-courses').val('');
        filterCourses();
    }
    
    function updateCourseCount(pagination) {
        if (pagination) {
            const total = pagination.total;
            const current = courses.length;
            $('#course-count').text(`Showing ${current} of ${total} courses`);
        }
    }
    
    function showLoading() {
        if (currentView === 'card') {
            $('#course-list').html(`
                <div class="loading-state">
                    <div class="loading-spinner"></div>
                    <p>Loading courses...</p>
                </div>
            `);
        } else {
            $('#courses-table-body').html(`
                <tr>
                    <td colspan="6" class="text-center">
                        <div class="loading-state">
                            <div class="loading-spinner"></div>
                            <p>Loading courses...</p>
                        </div>
                    </td>
                </tr>
            `);
        }
    }
    
    function hideLoading() {
        // Loading state is replaced by displayCourses
    }
    
    function showSuccess(message) {
        showAlert(message, 'success');
    }
    
    function showError(message) {
        showAlert(message, 'error');
    }
    
    // Course Management Event Listeners
    $('#add-course-btn').click(function() {
        openAddCourseModal();
    });
    
    $('#save-course').click(function() {
        saveCourse();
    });
    
    $('#cancel-course').click(function() {
        $('#course-modal').hide();
    });
    
    $('#confirm-delete').click(function() {
        confirmDelete();
    });
    
    $('#cancel-delete').click(function() {
        $('#delete-modal').hide();
    });
    
    $('#close-modal, #close-delete-modal').click(function() {
        $(this).closest('.modal').hide();
    });
    
    // Filter event listeners
    $('#category-filter, #provider-filter').change(function() {
        filterCourses();
    });
    
    $('#search-button').click(function() {
        filterCourses();
    });
    
    $('#search-courses').keypress(function(e) {
        if (e.which === 13) {
            filterCourses();
        }
    });
    
    $('#clear-filters').click(function() {
        clearFilters();
    });
    
    // View toggle
    $('.view-btn').click(function() {
        const view = $(this).data('view');
        currentView = view;
        
        $('.view-btn').removeClass('active');
        $(this).addClass('active');
        
        if (view === 'card') {
            $('#course-list').show();
            $('#table-container').hide();
        } else {
            $('#course-list').hide();
            $('#table-container').show();
        }
        
        displayCourses(courses);
    });
    
    // Refresh courses
    $('#refresh-courses').click(function() {
        currentPage = 1;
        courses = [];
        loadCourses();
    });
    
    // Utility functions
    function showAlert(message, type) {
        const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
        const alertHtml = `<div class="alert ${alertClass}">${message}</div>`;
        
        $('.alert').remove();
        $('body').prepend(alertHtml);
        
        setTimeout(() => {
            $('.alert').fadeOut();
        }, 5000);
    }
    
    // Enhanced success modal for course addition
    function showSuccessModal(title, message, onClose = null) {
        const modalHtml = `
            <div id="success-modal" class="modal success-modal" style="display: block;">
                <div class="modal-content success-content">
                    <div class="success-icon">
                        <div class="checkmark-wrapper">
                            <svg class="checkmark" viewBox="0 0 52 52">
                                <circle class="checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
                                <path class="checkmark-check" fill="none" d="m14.1 27.2l7.1 7.2 16.7-16.8"/>
                            </svg>
                        </div>
                    </div>
                    <div class="success-text">
                        <h2>${title}</h2>
                        <p>${message}</p>
                    </div>
                    <div class="success-actions">
                        <button class="btn btn-primary" id="success-ok-btn">OK</button>
                    </div>
                </div>
            </div>
        `;
        
        $('.modal').hide();
        $('body').append(modalHtml);
        
        $('#success-ok-btn').click(function() {
            $('#success-modal').remove();
            if (onClose && typeof onClose === 'function') {
                onClose();
            }
        });
        
        $('#success-modal').click(function(e) {
            if (e.target === this) {
                $('#success-modal').remove();
                if (onClose && typeof onClose === 'function') {
                    onClose();
                }
            }
        });
    }
    
    // Report generation functions
    window.generateComplianceReport = function() {
        window.open(`${iipm_ajax.ajax_url}?action=iipm_generate_compliance_report&nonce=${iipm_ajax.cpd_admin_nonce}`, '_blank');
    };
    
    window.generatePopularityReport = function() {
        window.open(`${iipm_ajax.ajax_url}?action=iipm_generate_popularity_report&nonce=${iipm_ajax.cpd_admin_nonce}`, '_blank');
    };
    
    window.generateProviderReport = function() {
        window.open(`${iipm_ajax.ajax_url}?action=iipm_generate_provider_report&nonce=${iipm_ajax.cpd_admin_nonce}`, '_blank');
    };
    

    
    // Report generation functions
    window.generateComplianceReport = function() {
        // Redirect to the CPD Reports page with compliance section
        window.location.href = '<?php echo home_url('/cpd-reports/'); ?>?report=compliance';
    };

    window.generatePopularityReport = function() {
        // Redirect to the CPD Reports page with popularity section
        window.location.href = '<?php echo home_url('/cpd-reports/'); ?>?report=popularity';
    };

    window.generateProviderReport = function() {
        // Redirect to the CPD Reports page with provider section
        window.location.href = '<?php echo home_url('/cpd-reports/'); ?>?report=provider';
    };

    // Initialize
    loadSubmissions();
    updatePendingCount();
});
</script>

<?php get_footer(); ?>
