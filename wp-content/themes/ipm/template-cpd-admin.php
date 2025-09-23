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

<div class="cpd-admin-portal main-container">
    <div class="container" style="position: relative; z-index: 2;">
        <!-- Page Header -->
        <div class="page-header" style="text-align: center; margin-bottom: 40px;">
            <div>
                <h1 style="color: white; font-size: 2.5rem; margin-bottom: 10px;">CPD Administration Portal</h1>
                <p style="color: rgba(255,255,255,0.9); font-size: 1.1rem;">
                    Review CPD submissions and manage certificates
                </p>
            </div>
        </div>
        <div>
        <!-- Admin Navigation -->
        <div class="admin-nav">
            <button class="nav-btn active" data-section="submissions">
                <span class="icon"><i class="fas fa-clipboard-list"></i></span>
                Review Submissions
                <span class="badge" id="pending-count">0</span>
            </button>
            <button class="nav-btn" data-section="certificates">
                <span class="icon"><i class="fas fa-graduation-cap"></i></span>
                Certificates
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
                            $current_year = date('Y');
                            for ($i = $current_year; $i >= $current_year - 5; $i--): 
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
            <div id="certificate-modal" class="modal" style="display:none;">
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
                                        <input type="text" id="cert-year" name="year" class="form-control" value="<?php echo date('Y'); ?>" readonly>
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
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    var portal_nonce = '<?php echo wp_create_nonce('iipm_portal_nonce'); ?>';
    
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
                nonce: portal_nonce
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
    
    
    
    
    // Update pending count badge
    function updatePendingCount() {
        $.ajax({
            url: iipm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'iipm_get_cpd_submissions',
                status: 'pending',
                nonce: portal_nonce
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
                nonce: portal_nonce
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

        $('#certificate-modal').show();
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
        $('#certificate-modal').show();
    });

    // Close modal handlers
    $('#cancel-certificate, .modal-close').click(function(){
        $('#certificate-modal').hide();
    });
    $('#certificate-modal').on('click', function(e){
        if (e.target === this) { $('#certificate-modal').hide(); }
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
                    $('#certificate-modal').hide();
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

    // Initialize
    loadCertificates();
    loadSubmissions();
    updatePendingCount();
});
</script>

<?php get_footer(); ?>
