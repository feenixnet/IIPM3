<?php
/*
Template Name: CPD Admin Portal
*/

// Check if user has admin permissions
if (!current_user_can('manage_iipm_members') && !current_user_can('administrator')) {
    wp_redirect(home_url());
    exit;
}

// Include the CPD record API for CPD year calculation
require_once get_template_directory() . '/includes/cpd-record-api.php';

get_header();
?>

<div class="cpd-admin-portal main-container">
    <div class="container" style="position: relative; z-index: 2;">
        <!-- Page Header -->
        <div class="page-header" style="text-align: center; margin-bottom: 40px;">
            <div>
                <h1 style="color: white; font-size: 2.5rem; margin-bottom: 10px;">CPD Administration Portal</h1>
                <p style="color: rgba(255,255,255,0.9); font-size: 1.1rem;">
                    Review CPD submissions and manage certificates
                </p>
                <?php IIPM_Navigation_Manager::display_breadcrumbs(); ?>
            </div>
        </div>
        <div>
        <!-- Tab Navigation -->
        <div class="tab-navigation" style="margin-bottom: 30px;">
            <div style="display: flex; justify-content: center; gap: 20px;">
                <button class="tab-button active" data-section="submissions" 
                   style="padding: 12px 24px; background: #f8a135; color: white; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s ease; border: none; cursor: pointer;">
                    <span style="margin-right: 8px;"><i class="fas fa-clipboard-list"></i></span>
                    Review Submissions
                </button>
                <button class="tab-button" data-section="certificates"
                   style="padding: 12px 24px; background: #6b4c93; color: white; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s ease; border: none; cursor: pointer;">
                    <span style="margin-right: 8px;"><i class="fas fa-graduation-cap"></i></span>
                    Certificates
                </button>
            </div>
        </div>

        <!-- Submissions Review Section -->
        <div id="submissions-section" class="admin-section active">
            <div class="section-header">
                <h2>CPD Submissions Review</h2>
                <div class="section-actions">
                    <button id="refresh-submissions" class="btn btn-outline">
                        <span>🔄</span> Refresh
                    </button>
                </div>
            </div>
            
            <!-- Submission Filters -->
            <div class="submission-filters">
                <div class="filters-row">
                    <div class="filter-group">
                        <select id="submission-year">
                            <option value="">All Years</option>
                            <?php 
                            // Use CPD logging year - this returns previous year if we're in January (before Jan 31 deadline)
                            $current_year = iipm_get_cpd_logging_year();
                            $actual_year = date('Y');
                            for ($i = $actual_year; $i >= $actual_year - 5; $i--): 
                            ?>
                                <option value="<?php echo $i; ?>" <?php echo $i == $current_year ? 'selected' : ''; ?>><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <input type="text" id="user-search" placeholder="Name, username, or email..." class="form-control" />
                    </div>
                    
                    <div class="filter-actions">
                        <button type="button" id="clear-filters" class="btn btn-secondary">
                            <span>🗑️</span> Clear
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Submissions Table -->
            <div class="submissions-table-container">
                <div id="submissions-content">
                    <div class="loading-spinner"></div>
                </div>
            </div>
            
            <!-- Pagination -->
            <div id="submissions-pagination" class="pagination-container" style="display: none;">
                <div class="pagination-info">
                    <span id="pagination-info"></span>
                </div>
                <div class="pagination-controls">
                    <button id="prev-page" class="btn btn-outline" disabled>Previous</button>
                    <div id="page-numbers" class="page-numbers"></div>
                    <button id="next-page" class="btn btn-outline" disabled>Next</button>
                </div>
            </div>
        </div>



        <!-- Certificate Management Section -->
        <div id="certificates-section" class="admin-section">
            <div class="section-header">
                <h2>Certificate Management</h2>
                <div class="section-actions">
                    <button id="create-certificate-btn" class="btn btn-success">
                        <span class="icon">🎓</span>
                        Create Certificate
                    </button>
                </div>
            </div>
            
            <div class="certificate-filters">
                <div class="filters-row">
                    <div class="filter-group">
                        <select id="cert-year-filter">
                            <option value="">All Years</option>
                            <?php 
                            // Use CPD logging year - this returns previous year if we're in January (before Jan 31 deadline)
                            $current_year = iipm_get_cpd_logging_year();
                            $actual_year = date('Y');
                            for ($i = $actual_year; $i >= $actual_year - 5; $i--): 
                            ?>
                                <option value="<?php echo $i; ?>" <?php echo $i == $current_year ? 'selected' : ''; ?>><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="button" id="refresh-certificates" class="btn btn-outline">
                            <span>🔄</span> Refresh
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Create/Edit Certificate Modal -->
            <div id="certificate-modal" class="modal">
                <div class="modal-content" style="max-width:720px;">
                    <div class="modal-header">
                        <h2 id="certificate-modal-title">Create Certificate</h2>
                        <button class="modal-close" aria-label="Close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form id="certificate-form" enctype="multipart/form-data">
                            <input type="hidden" id="cert-id" name="id" value="">
                            <input type="hidden" name="avatar_remove" value="0">
                            <div class="form-row" style="display:grid; grid-template-columns: 1fr 220px; gap: 16px;">
                                <div>
                                    <div class="form-group">
                                        <label for="cert-name">Name</label>
                                        <input type="text" id="cert-name" name="name" class="form-control" placeholder="Certificate name" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="cert-description">Description</label>
                                        <textarea id="cert-description" name="description" class="form-control" rows="3" placeholder="Optional description..."></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="cert-year">Year</label>
                                        <input type="text" id="cert-year" name="year" class="form-control" value="<?php echo iipm_get_cpd_logging_year(); ?>" readonly>
                                    </div>
                                </div>
                                <div>
                                    <div class="form-group">
                                        <label for="cert-avatar">Avatar</label>
                                        <div id="cert-avatar-dropzone" style="border: 2px dashed #e5e7eb; border-radius: 8px; padding: 16px; text-align: center; cursor: pointer; background:#fafafa;">
                                            <div id="cert-avatar-empty" style="color:#6b7280;">
                                                <div style="font-size:32px;">📄</div>
                                                <div>Drag & drop image here, or click to browse</div>
                                            </div>
                                            <div id="cert-avatar-preview" style="display:none; align-items:center; gap:12px; justify-content:center;">
                                                <img id="cert-avatar-img" src="" alt="preview" style="width:120px;height:120px;object-fit:cover;border-radius:12px;border:1px solid #e5e7eb;" />
                                                <div>
                                                    <div id="cert-avatar-name" style="font-weight:600"></div>
                                                    <button type="button" id="cert-avatar-remove" class="btn btn-secondary btn-small" style="margin-top:8px;">Remove</button>
                                                </div>
                                            </div>
                                        </div>
                                        <input type="file" id="cert-avatar" name="avatar" accept="image/*" style="display:none;">
                                    </div>
                                </div>
                            </div>
                            <div class="form-actions" style="display:flex; gap:8px; justify-content:flex-end; margin-top: 16px; padding-top: 8px; border-top: 1px solid #e5e7eb;">
                                <button type="button" class="btn btn-secondary" id="cancel-certificate">Cancel</button>
                                <button type="submit" id="save-certificate" class="btn btn-primary">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div id="certificates-content">
                <div class="certificates-table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Avatar</th>
                                <th>Name</th>
                                <th>Year</th>
                                <th>Description</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="certificates-tbody">
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px; color: #6b7280;">
                                    Loading certificates...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>



        <!-- Submission Details Modal -->
        <div id="submission-details-modal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>CPD Submission Details</h2>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <div id="submission-details-content">
                        <div class="loading-spinner"></div>
                        <p>Loading submission details...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Certificate Assignment Modal -->
        <div id="certificate-assignment-modal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Assign Certificate</h2>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <div id="certificate-assignment-content">
                        <div class="loading-spinner"></div>
                    </div>
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
</div>

<style>
/* CPD Admin Portal Styles */
.cpd-admin-portal {
    min-height: 100vh;
}

.admin-table {
    border: 1px solid #e5e7eb;
    border-collapse: collapse;
}

.admin-table th,
.admin-table td {
    border: 1px solid #e5e7eb;
    padding: 5px 10px;
}

.admin-table th{
    text-align: left !important;
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

/* Submission Filters */
.submission-filters {
    margin-top: 24px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 24px;
}

.filters-row {
    display: grid;
    grid-template-columns: 1fr 2fr auto;
    gap: 16px;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.filter-group label {
    font-weight: 600;
    color: #374151;
    font-size: 0.875rem;
}

.filter-group select,
.filter-group input {
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.875rem;
    background: white;
}

.filter-group select:focus,
.filter-group input:focus {
    outline: none;
    border-color: #8b5a96;
    box-shadow: 0 0 0 3px rgba(139, 90, 150, 0.1);
}

.filter-actions {
    display: flex;
    gap: 8px;
}

/* Submissions Table */
.submissions-table-container {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.submissions-table {
    width: 100%;
    border-collapse: collapse;
}

.submissions-table th {
    background: #f8fafc;
    padding: 16px;
    text-align: left;
    font-weight: 600;
    color: #374151;
    border-bottom: 1px solid #e5e7eb;
    font-size: 0.875rem;
}

.submissions-table td {
    padding: 16px;
    border-bottom: 1px solid #f3f4f6;
    vertical-align: middle;
}

.submissions-table tr:hover {
    background: #f9fafb;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-approved {
    background: #d1fae5;
    color: #065f46;
}

.status-rejected {
    background: #fee2e2;
    color: #991b1b;
}

.action-buttons {
    display: flex;
    gap: 8px;
}

.btn-small {
    padding: 6px 12px;
    font-size: 0.75rem;
    border-radius: 6px;
}

/* Pagination */
.pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 24px;
    padding: 16px 0;
}

.pagination-info {
    color: #6b7280;
    font-size: 0.875rem;
}

.pagination-controls {
    display: flex;
    align-items: center;
    gap: 8px;
}

.page-numbers {
    display: flex;
    gap: 4px;
}

.page-number {
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    background: white;
    color: #374151;
    text-decoration: none;
    border-radius: 6px;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.page-number:hover {
    background: #f3f4f6;
    border-color: #9ca3af;
}

.page-number.active {
    background: #8b5a96;
    color: white;
    border-color: #8b5a96;
}

/* Modal Styles - Same as Course Management */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    animation: fadeIn 0.3s ease-out;
}

body.modal-open {
    overflow: hidden;
}

.modal.show {
    display: flex !important;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    border-radius: 12px;
    width: 98%;
    max-width: 1600px; /* Much wider for better content display */
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    animation: slideUp 0.3s ease-out;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #e2e8f0;
}

.modal-header h2 {
    margin: 0;
    color: #2d3748;
    font-size: 1.5rem;
    font-weight: 600;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #718096;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background 0.2s;
}

.modal-close:hover {
    background: #f7fafc;
}

.modal-body {
    padding: 20px;
    overflow-y: auto;
    flex: 1;
    max-height: calc(90vh - 120px);
}


/* Animations */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.details-section {
    padding: 20px;
    background: #ffffff;
    border-radius: 12px;
    border: 2px solid #e5e7eb;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    transition: all 0.2s ease;
    width: calc(50% - 8px);
}

.details-section h3 {
    margin: 0 0 16px 0;
    color: #1f2937;
    font-size: 1.25rem;
    font-weight: 700;
    padding-bottom: 8px;
    border-bottom: 2px solid #f3f4f6;
}

.details-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 24px;
    min-height: 300px;
}

.detail-item {
    flex: 1 1 calc(50% - 12px);
    min-width: 300px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: 12px;
    padding: 32px 24px;
    background: #f9fafb;
    border-radius: 12px;
    border: 2px solid #e5e7eb;
    text-align: center;
    min-height: 120px;
    transition: all 0.2s ease;
}

.detail-item:hover {
    border-color: #8b5a96;
    background: #f8fafc;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(139, 90, 150, 0.1);
}

.detail-label {
    font-weight: 600;
    color: #6b7280;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.detail-value {
    color: #1f2937;
    font-size: 1.25rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    flex-direction: column;
}

.status-icon {
    font-size: 1.5rem;
    font-weight: bold;
    display: inline-block;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 4px;
}

.status-success {
    background-color: #d1fae5;
    color: #059669;
    border: 2px solid #10b981;
}

.status-error {
    background-color: #fee2e2;
    color: #dc2626;
    border: 2px solid #ef4444;
}

.status-text {
    font-size: 0.9rem;
    font-weight: 500;
    text-align: center;
}

.courses-list {
    display: flex;
    flex-direction: row;
    gap: 12px;
}

.course-item {
    width: calc(25% - 9px);
    padding: 16px;
    background: white;
    border-radius: 10px;
    border: 2px solid #e5e7eb;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    transition: all 0.2s ease;
}

.course-item:hover {
    border-color: #8b5a96;
    box-shadow: 0 4px 8px rgba(139, 90, 150, 0.1);
}

.course-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.course-name {
    font-weight: 600;
    color: #1f2937;
}

.course-status {
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

.course-status.completed {
    background: #d1fae5;
    color: #065f46;
}

.course-status.pending {
    background: #fef3c7;
    color: #92400e;
}

.course-details {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.course-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    font-size: 0.75rem;
}

.meta-item {
    flex: 1 1 calc(50% - 8px);
    min-width: 140px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 8px;
    background: #f8fafc;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
}

.meta-label {
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    font-size: 0.7rem;
}

.meta-value {
    color: #1f2937;
    font-weight: 500;
}

.progress-bar {
    width: 100%;
    height: 8px;
    background: #e5e7eb;
    border-radius: 4px;
    overflow: hidden;
    margin-top: 8px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #10b981, #059669);
    transition: width 0.3s ease;
    border-radius: 4px;
}

/* Responsive */
@media (max-width: 768px) {
    .filters-row {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .submissions-table {
        font-size: 0.75rem;
    }
    
    .submissions-table th,
    .submissions-table td {
        padding: 8px;
    }
    
    .action-buttons {
        flex-direction: column;
        gap: 4px;
    }
    
    .pagination-container {
        flex-direction: column;
        gap: 16px;
        text-align: center;
    }
    
    .details-grid {
        flex-direction: column;
        gap: 20px;
        min-height: auto;
    }
    
    .detail-item {
        flex: 1 1 100%;
        min-width: auto;
        min-height: 100px;
        padding: 24px 20px;
    }
    
    .course-meta {
        flex-direction: column;
        gap: 12px;
    }
    
    .meta-item {
        flex: 1 1 100%;
        min-width: auto;
    }
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
    display: flex;
    flex-wrap: wrap;
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



.filters-row {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr auto;
    gap: 20px;
    align-items: end;
    margin-bottom: 16px;
    margin-top: 16px;
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


.meta-value {
    font-weight: 500;
    color: #1f2937;
}


.course-description-small {
    font-size: 0.875rem;
    color: #6b7280;
    margin-top: 4px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
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

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24px 32px;
    border-bottom: 1px solid #e5e7eb;
    background: white;
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
    margin-bottom: 16px;
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

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24px 32px;
    border-bottom: 1px solid #e5e7eb;
    background: white;
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

#certificate-assignment-modal .modal-content {
    max-width: 600px !important;
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
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    var portal_nonce = '<?php echo wp_create_nonce('iipm_portal_nonce'); ?>';
    
    // Navigation handling
    $('.tab-button').click(function() {
        const section = $(this).data('section');
        switchSection(section);
    });
    
    function switchSection(section) {
        $('.tab-button').removeClass('active');
        $(`.tab-button[data-section="${section}"]`).addClass('active');
        
        // Update button styles
        $('.tab-button').css('background', '#6b4c93');
        $(`.tab-button[data-section="${section}"]`).css('background', '#f8a135');
        
        $('.admin-section').removeClass('active');
        $(`#${section}-section`).addClass('active');
        
        currentSection = section;
        
        // Load section data
        switch(section) {
            case 'submissions':
                loadSubmissions();
                break;
        }
    }
    
    // Load CPD submissions with pagination and filtering
    let currentPage = 1;
    let totalPages = 1;
    let currentSubmissions = []; // Store current submissions data
    
    function loadSubmissions(page = 1) {
        const year = $('#submission-year').val() || '';
        const userSearch = $('#user-search').val() || '';
        
        $('#submissions-content').html('<div class="loading-spinner"></div>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'iipm_get_admin_submissions',
                page: page,
                per_page: 10,
                year: year,
                user_search: userSearch,
                nonce: portal_nonce
            },
            success: function(response) {
                if (response.success) {
                    currentSubmissions = response.data.submissions; // Store submissions data
                    renderSubmissions(response.data.submissions);
                    updatePagination(response.data.pagination);
                } else {
                    $('#submissions-content').html('<p>Error loading submissions: ' + response.data + '</p>');
                }
            },
            error: function() {
                $('#submissions-content').html('<p>Failed to load submissions</p>');
            }
        });
    }
    
    // Render submissions table
    function renderSubmissions(submissions) {
        let html = '';
        
        if (submissions && submissions.length > 0) {
            html = `
                <table class="submissions-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Member</th>
                            <th>Year</th>
                            <th>Certificate</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            submissions.forEach(function(submission) {
                const userName = submission.display_name || submission.user_login || 'Unknown User';
                
                // Certificate display logic
                let certificateDisplay = '';
                if (submission.certificate_id && submission.certificate_name) {
                    certificateDisplay = `
                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <span class="status-badge status-approved" style="background: #10b981; color: white;">
                                <i class="fas fa-certificate" style="margin-right: 4px;"></i>
                                ${submission.certificate_name}
                            </span>
                            <small style="color: #6b7280;">(${submission.certificate_year})</small>
                        </div>
                    `;
                } else {
                    certificateDisplay = `
                        <button class="btn btn-outline btn-small" onclick="showCertificateAssignment(${submission.id}, '${userName}')" style="font-size: 0.75rem; padding: 4px 8px;">
                            <i class="fas fa-plus" style="margin-right: 4px;"></i>
                            Set Certificate
                        </button>
                    `;
                }

                html += `
                    <tr>
                        <td>${submission.id}</td>
                        <td>
                            <div>
                                <strong>${userName}</strong><br>
                                <small style="color: #6b7280;">${submission.user_email}</small>
                            </div>
                        </td>
                        <td>${submission.year}</td>
                        <td>
                            ${certificateDisplay}
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-outline btn-small" onclick="viewSubmissionDetails(${submission.id})">
                                    Details
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            html += `
                    </tbody>
                </table>
            `;
        } else {
            html = '<div style="text-align: center; padding: 40px; color: #6b7280;">No submissions found.</div>';
        }
        
        $('#submissions-content').html(html);
    }
    
    // Update pagination
    function updatePagination(pagination) {
        currentPage = pagination.current_page;
        totalPages = pagination.total_pages;
        
        if (totalPages > 1) {
            $('#submissions-pagination').show();
            $('#pagination-info').text(`Showing ${((currentPage - 1) * pagination.per_page) + 1} to ${Math.min(currentPage * pagination.per_page, pagination.total_items)} of ${pagination.total_items} entries`);
            
            // Update pagination controls
            $('#prev-page').prop('disabled', currentPage === 1);
            $('#next-page').prop('disabled', currentPage === totalPages);
            
            // Generate page numbers
            let pageNumbersHtml = '';
            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(totalPages, currentPage + 2);
            
            for (let i = startPage; i <= endPage; i++) {
                const activeClass = i === currentPage ? 'active' : '';
                pageNumbersHtml += `<span class="page-number ${activeClass}" onclick="goToPage(${i})">${i}</span>`;
            }
            
            $('#page-numbers').html(pageNumbersHtml);
        } else {
            $('#submissions-pagination').hide();
        }
    }
    
    // Go to specific page
    window.goToPage = function(page) {
        if (page >= 1 && page <= totalPages) {
            loadSubmissions(page);
        }
    };
    
    
    
    
    
    // View submission details
    window.viewSubmissionDetails = function(submissionId) {
        // Find the submission in current data
        const submission = currentSubmissions.find(s => s.id == submissionId);
        
        if (!submission) {
            $('#submission-details-content').html('<p>Submission not found</p>');
            showModal('submission-details-modal');
            return;
        }
        
        // Handle double-encoded JSON string
        let details;
        try {
            // First, try to use the pre-decoded details from backend
            if (submission.details_decoded && typeof submission.details_decoded === 'object') {
                details = submission.details_decoded;
            } else {
                // If not available, parse the raw details string
                // The string appears to be double-encoded, so we need to handle it properly
                let detailsString = submission.details;
                
                // Remove outer quotes if present
                if (detailsString.startsWith('"') && detailsString.endsWith('"')) {
                    detailsString = detailsString.slice(1, -1);
                }
                
                // Unescape the JSON string
                detailsString = detailsString.replace(/\\"/g, '"').replace(/\\\\/g, '\\');
                
                // Parse the JSON
                details = JSON.parse(detailsString);
            }
        } catch (e) {
            console.error('Failed to parse details JSON:', e);
            details = {};
        }
        
        console.log("details111", details);
        // Render details directly from stored data
        renderSubmissionDetails(submission, details);
        showModal('submission-details-modal');
    };
    
    // Render submission details in modal
    function renderSubmissionDetails(submission, details) {
        const userName = submission.display_name || submission.user_login || 'Unknown User';
        
        // Ensure details is an object
        if (!details || typeof details !== 'object') {
            details = {};
        }

        console.log("details", details);
        
        let html = `
            <div class="submission-details">
                <div class="details-section">
                    <h3>Submission Information</h3>
                    <div class="details-grid">
                        <div class="detail-item">
                            <div class="detail-label">Submission ID</div>
                            <div class="detail-value">${submission.id}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Member</div>
                            <div class="detail-value">${userName}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Email</div>
                            <div class="detail-value">${submission.user_email}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Year</div>
                            <div class="detail-value">${submission.year}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Status</div>
                            <div class="detail-value">
                                <span class="status-badge status-${submission.status}">${submission.status}</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="details-section">
                    <h3>CPD Progress Summary</h3>
                    <div class="details-grid">
                        <div class="detail-item">
                            <div class="detail-label">Target Minutes</div>
                            <div class="detail-value">${details.target_minutes || 0} minutes</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Total CPD Minutes</div>
                            <div class="detail-value">${details.total_cpd_minutes || 0} minutes</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Completion Percentage</div>
                            <div class="detail-value">${details.completion_percentage || 0}%</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Start Date</div>
                            <div class="detail-value">${details.start_date || 'N/A'}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Completion Date</div>
                            <div class="detail-value">${details.completion_date || 'N/A'}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Total Hours</div>
                            <div class="detail-value">${details.total_hours || 0} hours</div>
                        </div>
                    </div>
                </div>
        `;
        
        // Add CPD dates if available
        if (details.cpd_dates) {
            html += `
                <div class="details-section">
                    <h3>CPD Periods</h3>
                    <div class="details-grid">
                        <div class="detail-item">
                            <div class="detail-label">Start Logging</div>
                            <div class="detail-value">${details.cpd_dates.start_logging || 'N/A'}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">End Logging</div>
                            <div class="detail-value">${details.cpd_dates.end_logging || 'N/A'}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Start Submission</div>
                            <div class="detail-value">${details.cpd_dates.start_submission || 'N/A'}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">End Submission</div>
                            <div class="detail-value">${details.cpd_dates.end_submission || 'N/A'}</div>
                        </div>
                    </div>
                </div>
            `;
        }
        
        html += `
                 <div class="details-section">
                     <h3>System Status</h3>
                     <div class="details-grid">
                         <div class="detail-item">
                             <div class="detail-label">Logging Period Available</div>
                             <div class="detail-value">
                                 <span class="status-icon ${details.is_logging_period_available ? 'status-success' : 'status-error'}">
                                     ${details.is_logging_period_available ? '✓' : '✗'}
                                 </span>
                                 <span class="status-text">${details.is_logging_period_available ? 'Available' : 'Not Available'}</span>
                             </div>
                         </div>
                         <div class="detail-item">
                             <div class="detail-label">Submission Period Available</div>
                             <div class="detail-value">
                                 <span class="status-icon ${details.is_submission_period_available ? 'status-success' : 'status-error'}">
                                     ${details.is_submission_period_available ? '✓' : '✗'}
                                 </span>
                                 <span class="status-text">${details.is_submission_period_available ? 'Available' : 'Not Available'}</span>
                             </div>
                         </div>
                         <div class="detail-item">
                             <div class="detail-label">Member Assigned</div>
                             <div class="detail-value">
                                 <span class="status-icon ${details.is_user_assigned ? 'status-success' : 'status-error'}">
                                     ${details.is_user_assigned ? '✓' : '✗'}
                                 </span>
                                 <span class="status-text">${details.is_user_assigned ? 'Assigned' : 'Not Assigned'}</span>
                             </div>
                         </div>
                     </div>
                 </div>
                
            </div>
        `;
        
                // Add courses summary if available
                if (details.courses_summary && details.courses_summary.length > 0) {
            html += `
                <div class="details-section" style="width: 100%;">
                    <h3>Course Categories Summary</h3>
                    <div class="courses-list">
            `;
            
            details.courses_summary.forEach(function(course) {
                const statusClass = course.completed ? 'completed' : 'pending';
                const progressBar = course.required ? 
                    `<div class="progress-bar">
                        <div class="progress-fill" style="width: ${course.completed ? '100' : '0'}%"></div>
                    </div>` : '';
                
                html += `
                    <div class="course-item">
                        <div class="course-header">
                            <div class="course-name">${course.category}</div>
                            <div class="course-status ${statusClass}">${course.status}</div>
                        </div>
                        <div class="course-details">
                            <div class="course-meta">
                                <div class="meta-item">
                                    <span class="meta-label">Courses Count:</span>
                                    <span class="meta-value">${course.count}</span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Total Hours:</span>
                                    <span class="meta-value">${course.total_hours}</span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Total Minutes:</span>
                                    <span class="meta-value">${course.total_minutes}</span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Credits:</span>
                                    <span class="meta-value">${course.credits}</span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Required:</span>
                                    <span class="meta-value">${course.required ? 'Yes' : 'No'}</span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Completed:</span>
                                    <span class="meta-value">${course.completed ? 'Yes' : 'No'}</span>
                                </div>
                            </div>
                            ${progressBar}
                        </div>
                    </div>
                `;
            });
            
            html += `
                    </div>
                </div>
            `;
        }

        $('#submission-details-content').html(html);
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
                nonce: portal_nonce
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
                    showModal('review-modal');
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
                nonce: portal_nonce
            },
            success: function(response) {
                if (response.success) {
                    showAlert(response.data.message, 'success');
                    hideModal('review-modal');
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
    

    
    
    // Modal handlers
    $('.modal-close').click(function() {
        $('.modal').removeClass('show');
        $('body').removeClass('modal-open');
    });
    
    $('.modal').click(function(e) {
        if (e.target === this) {
            $(this).removeClass('show');
            $('body').removeClass('modal-open');
        }
    });
    
    // Show modal function
    function showModal(modalId) {
        $('#' + modalId).addClass('show');
        $('body').addClass('modal-open');
    }
    
    // Hide modal function
    function hideModal(modalId) {
        $('#' + modalId).removeClass('show');
        $('body').removeClass('modal-open');
    }
    
    // Filter change handlers
    $('#submission-year').change(function() {
        currentPage = 1;
        loadSubmissions(1);
    });
    
    $('#user-search').on('input', function() {
        clearTimeout(this.searchTimeout);
        this.searchTimeout = setTimeout(() => {
            currentPage = 1;
            loadSubmissions(1);
        }, 500);
    });
    
    // Clear filters
    $('#clear-filters').click(function() {
        $('#submission-year').val('');
        $('#user-search').val('');
        currentPage = 1;
        loadSubmissions(1);
    });
    
    // Refresh submissions
    $('#refresh-submissions').click(function() {
        loadSubmissions(currentPage);
    });
    
    // Pagination handlers
    $('#prev-page').click(function() {
        if (currentPage > 1) {
            loadSubmissions(currentPage - 1);
        }
    });
    
    $('#next-page').click(function() {
        if (currentPage < totalPages) {
            loadSubmissions(currentPage + 1);
        }
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
        
        $('.modal').removeClass('show');
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
    
    // Helpers
    function escapeHtml(s){
        return (s||'').replace(/[&<>"]/g, function(c){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]); });
    }
    function formatDate(dateStr){
        if (!dateStr) return '';
        var d = new Date((dateStr+'').replace(' ', 'T'));
        if (isNaN(d.getTime())) return dateStr;
        var pad = function(n){ return n<10 ? ('0'+n) : n; };
        return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate())+' '+pad(d.getHours())+':'+pad(d.getMinutes());
    }

    // Load certificates (list)
    function loadCertificates() {
        const year = $('#cert-year-filter').val();
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: { action: 'iipm_certs_list', nonce: portal_nonce, year: year },
            success: function(resp){
                if (!resp.success) { $('#certificates-tbody').html('<tr><td colspan="7" style="text-align:center; padding: 24px; color:#dc2626;">Failed to load</td></tr>'); return; }
                const items = resp.data.items || [];
                if (items.length === 0) { $('#certificates-tbody').html('<tr><td colspan="7" style="text-align:center; padding:24px; color:#6b7280;">No certificates</td></tr>'); return; }
                let rows = '';
                items.forEach(it => {
                    rows += `
                        <tr>
                            <td>${it.id}</td>
                            <td>${it.avatar_url ? `<img src="${it.avatar_url}" alt="avatar" style="width:40px; height:40px; object-fit:cover; border-radius:6px;">` : ''}</td>
                            <td>${escapeHtml(it.name || '')}</td>
                            <td>${escapeHtml(it.year || '')}</td>
                            <td>${escapeHtml(it.description || '')}</td>
                            <td>${formatDate(it.created_at)}</td>
                            <td>
                                <button class="btn btn-secondary btn-small" data-edit-cert="${it.id}">Edit</button>
                                <button class="btn btn-danger btn-small" data-del-cert="${it.id}">Delete</button>
                            </td>
                        </tr>`;
                });
                $('#certificates-tbody').html(rows);
            },
            error: function(){
                $('#certificates-tbody').html('<tr><td colspan="7" style="text-align:center; padding: 24px; color:#dc2626;">Error</td></tr>');
            }
        });
    }

    // Create / Update
    $('#certificate-form').on('submit', function(e){
        e.preventDefault();
        const form = new FormData(this);
        form.append('action','iipm_certs_save');
        form.append('nonce', portal_nonce);
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: form,
            contentType: false,
            processData: false,
            success: function(resp){
                if (resp.success) {
                    showAlert('Saved successfully','success');
                    $('#certificate-form')[0].reset();
                    $('#cert-id').val('');
                    $('#cert-year').val(new Date().getFullYear());
                    loadCertificates();
                } else {
                    showAlert(resp.data || 'Save failed','error');
                }
            },
            error: function(){ showAlert('Network error','error'); }
        });
    });

    // Reset
    $('#reset-certificate').click(function(){
        $('#certificate-form')[0].reset();
        $('#cert-id').val('');
        $('#cert-year').val(new Date().getFullYear());
    });

    // Edit prefill from row (opens modal)
    $(document).on('click','[data-edit-cert]', function(){
        const row = $(this).closest('tr');
        const id = $(this).data('edit-cert');
        const name = row.find('td').eq(2).text();
        const year = row.find('td').eq(3).text();
        const desc = row.find('td').eq(4).text();
        const avatarSrc = row.find('td').eq(1).find('img').attr('src') || '';

        $('#certificate-modal-title').text('Edit Certificate');
        $('#cert-id').val(id);
        $('#cert-name').val(name);
        $('#cert-year').val(year);
        $('#cert-description').val(desc);
        $('#certificate-form').find('input[name="avatar_remove"]').val('0');

        // Reset file input
        const emptyDt = new DataTransfer();
        if ($('#cert-avatar')[0]) { $('#cert-avatar')[0].files = emptyDt.files; }

        if (avatarSrc) {
            $('#cert-avatar-img').attr('src', avatarSrc);
            $('#cert-avatar-name').text(avatarSrc.split('/').pop());
            $('#cert-avatar-empty').hide();
            $('#cert-avatar-preview').show();
        } else {
            $('#cert-avatar-img').attr('src','');
            $('#cert-avatar-name').text('');
            $('#cert-avatar-preview').hide();
            $('#cert-avatar-empty').show();
        }

        $('#certificate-modal').addClass('show');
        $('body').addClass('modal-open');
    });

    // Delete
    $(document).on('click','[data-del-cert]', function(){
        if (!confirm('Delete this certificate?')) return;
        const id = $(this).data('del-cert');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: { action:'iipm_certs_delete', nonce: portal_nonce, id: id },
            success: function(resp){
                if (resp.success) { showAlert('Deleted','success'); loadCertificates(); }
                else { showAlert(resp.data || 'Delete failed','error'); }
            },
            error: function(){ showAlert('Network error','error'); }
        });
    });

    // Filters
    $('#cert-year-filter').change(loadCertificates);
    $('#refresh-certificates').click(loadCertificates);

    // Open create modal
    $('#create-certificate-btn').click(function(){
        $('#certificate-modal-title').text('Create Certificate');
        $('#certificate-form')[0].reset();
        $('#cert-id').val('');
        $('#cert-year').val(new Date().getFullYear());
        $('#certificate-form').find('input[name="avatar_remove"]').val('0');
        $('#cert-avatar-img').attr('src','');
        $('#cert-avatar-name').text('');
        $('#cert-avatar-preview').hide();
        $('#cert-avatar-empty').show();
        $('#certificate-modal').addClass('show');
        $('body').addClass('modal-open');
    });

    // Close modal handlers
    $('#cancel-certificate, .modal-close').click(function(){
        $('#certificate-modal').removeClass('show');
        $('body').removeClass('modal-open');
    });
    $('#certificate-modal').on('click', function(e){
        if (e.target === this) { 
            $('#certificate-modal').removeClass('show');
            $('body').removeClass('modal-open');
        }
    });

    // Avatar upload interactions (click, drag&drop, preview, remove)
    $('#cert-avatar-dropzone').on('click', function(){ $('#cert-avatar').trigger('click'); });
    $('#cert-avatar').on('change', function(){
        const file = this.files && this.files[0];
        if (!file) { $('#cert-avatar-preview').hide(); $('#cert-avatar-empty').show(); return; }
        const url = URL.createObjectURL(file);
        $('#cert-avatar-img').attr('src', url);
        $('#cert-avatar-name').text(file.name);
        $('#cert-avatar-empty').hide();
        $('#cert-avatar-preview').show();
    });
    $('#cert-avatar-dropzone').on('dragover', function(e){ e.preventDefault(); $(this).css('background','#f3f4f6'); });
    $('#cert-avatar-dropzone').on('dragleave', function(e){ e.preventDefault(); $(this).css('background','#fafafa'); });
    $('#cert-avatar-dropzone').on('drop', function(e){
        e.preventDefault(); $(this).css('background','#fafafa');
        const files = e.originalEvent.dataTransfer.files;
        if (files && files[0]) {
            const dt = new DataTransfer(); dt.items.add(files[0]);
            $('#cert-avatar')[0].files = dt.files; $('#cert-avatar').trigger('change');
        }
    });
    $('#cert-avatar-remove').on('click', function(){
        const dt = new DataTransfer(); $('#cert-avatar')[0].files = dt.files;
        $('#cert-avatar-preview').hide(); $('#cert-avatar-empty').show();
        $('#certificate-form').find('input[name="avatar_remove"]').val('1');
    });

    // After save success, close modal
    const originalSaveHandler = $('#certificate-form').prop('onsubmit');
    $('#certificate-form').off('submit');
    $('#certificate-form').on('submit', function(e){
        e.preventDefault();
        const form = new FormData(this);
        if (!form.has('avatar_remove')) { form.append('avatar_remove', '0'); }
        form.append('action','iipm_certs_save');
        form.append('nonce', portal_nonce);
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: form,
            contentType: false,
            processData: false,
            success: function(resp){
                if (resp.success) {
                    showAlert('Saved successfully','success');
                    $('#certificate-modal').removeClass('show');
                    $('body').removeClass('modal-open');
                    loadCertificates();
                } else {
                    showAlert(resp.data || 'Save failed','error');
                }
            },
            error: function(){ showAlert('Network error','error'); }
        });
    });
    
    // Update switch section to include certificates
    const originalSwitchSection = switchSection;
    switchSection = function(section) {
        originalSwitchSection(section);
        
        if (section === 'certificates') {
            loadCertificates();
        }
    };

    // Certificate Assignment Functions
    window.showCertificateAssignment = function(submissionId, userName) {
        $('#certificate-assignment-content').html('<div class="loading-spinner"></div>');
        showModal('certificate-assignment-modal');
        
        // Load available certificates
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'iipm_get_available_certificates',
                nonce: portal_nonce
            },
            success: function(response) {
                if (response.success) {
                    renderCertificateAssignment(submissionId, userName, response.data);
                } else {
                    $('#certificate-assignment-content').html('<p>Error loading certificates: ' + response.data + '</p>');
                }
            },
            error: function() {
                $('#certificate-assignment-content').html('<p>Failed to load certificates</p>');
            }
        });
    };
    
    function renderCertificateAssignment(submissionId, userName, certificates) {
        let html = `
            <div class="certificate-assignment">
                <p><strong>Assign certificate to ${userName}'s submission</strong></p>
                <div class="form-group">
                    <select id="certificate-select" class="form-control">
                        <option value="">Choose a certificate...</option>
        `;
        
        certificates.forEach(function(cert) {
            html += `<option value="${cert.id}">${cert.name} (${cert.year})</option>`;
        });
        
        html += `
                    </select>
                </div>
                <div class="form-actions" style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 16px;">
                    <button type="button" class="btn btn-secondary" onclick="hideModal('certificate-assignment-modal')">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="assignCertificate(${submissionId})">Assign Certificate</button>
                </div>
            </div>
        `;
        
        $('#certificate-assignment-content').html(html);
    }
    
    window.assignCertificate = function(submissionId) {
        const certificateId = $('#certificate-select').val();
        
        if (!certificateId) {
            showAlert('Please select a certificate', 'error');
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'iipm_assign_certificate',
                submission_id: submissionId,
                certificate_id: certificateId,
                nonce: portal_nonce
            },
            success: function(response) {
                if (response.success) {
                    showAlert(response.data.message, 'success');
                    hideModal('certificate-assignment-modal');
                    loadSubmissions(currentPage); // Refresh the submissions list
                } else {
                    showAlert('Error: ' + response.data, 'error');
                }
            },
            error: function() {
                showAlert('An error occurred. Please try again.', 'error');
            }
        });
    };
    
    window.removeCertificate = function(submissionId, userName) {
        const confirmMessage = `Do you really want to remove the certificate from ${userName}'s submission?`;
        const confirmResult = confirm(confirmMessage);
        
        if (confirmResult) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'iipm_remove_certificate',
                    submission_id: submissionId,
                    nonce: portal_nonce
                },
                success: function(response) {
                    if (response.success) {
                        showAlert(response.data.message, 'success');
                        loadSubmissions(currentPage); // Refresh the submissions list
                    } else {
                        showAlert('Error: ' + response.data, 'error');
                    }
                },
                error: function() {
                    showAlert('An error occurred. Please try again.', 'error');
                }
            });
        }
    };

    // Initialize
    loadCertificates();
    loadSubmissions();
});

// Mobile menu functionality for CPD Admin Portal
document.addEventListener("DOMContentLoaded", function () {
    const burger = document.getElementById("burger");
    const header = document.querySelector(".header");
    const headerWrapper = document.querySelector(".header__wrapper");
    const body = document.body;
    const overlay = document.querySelector(".overlay");
    const mobileMenu = document.querySelector(".mobile-menu");
    const mobileMenuClose = document.querySelector(".mobile-menu-close");

    // Only add event listeners if all elements exist
    if (burger && header && overlay && mobileMenu && headerWrapper) {
        burger.addEventListener("click", function () {
            const isOpen = header.classList.toggle("open");
            body.classList.toggle("open");

            // Prevent scrolling when menu is open
            document.documentElement.style.overflow = isOpen ? "hidden" : "";
            body.style.overflow = isOpen ? "hidden" : "";

            // Set header__wrapper height to 100vh when burger is clicked
            if (isOpen) {
                headerWrapper.style.height = "100vh";
                overlay.style.visibility = "visible";
                overlay.style.opacity = "1";
                mobileMenu.style.left = "0";
            } else {
                headerWrapper.style.height = "";
                overlay.style.visibility = "hidden";
                overlay.style.opacity = "0";
                mobileMenu.style.left = "-300px";
            }
        });

        // Close menu when clicking overlay
        overlay.addEventListener("click", function () {
            closeMobileMenu();
        });

        // Close menu when clicking close button
        if (mobileMenuClose) {
            mobileMenuClose.addEventListener("click", function () {
                closeMobileMenu();
            });
        }

        // Function to close mobile menu
        function closeMobileMenu() {
            header.classList.remove("open");
            body.classList.remove("open");

            // Restore scrolling
            document.documentElement.style.overflow = "";
            body.style.overflow = "";

            // Reset header__wrapper height
            headerWrapper.style.height = "";

            // Hide overlay and mobile menu
            overlay.style.visibility = "hidden";
            overlay.style.opacity = "0";
            mobileMenu.style.left = "-300px";
        }
    }
});
</script>

<?php get_footer(); ?>
