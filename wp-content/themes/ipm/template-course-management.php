<?php
/**
 * Template Name: Course Management
 * 
 * Admin page for managing CPD courses - add, edit, delete courses
 */

// Check if user is admin and storm
if (!current_user_can('administrator')) {
    wp_redirect(home_url());
    exit;
}

// Get the active tab from URL, default to 'by-admin'
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'by-admin';

get_header();

// Include notification system if not already loaded
if (!function_exists('add_success_notification')) {
    include_once get_template_directory() . '/includes/notification-system.php';
}
?>

<div class="course-management-page main-container">
    <div class="container" style="position: relative; z-index: 2;">
        <div class="page-header" style="text-align: center; margin-bottom: 40px;">
            <div>
                <h1 style="color: white; font-size: 2.5rem; margin-bottom: 10px;">Course Management</h1>
                <p style="color: rgba(255,255,255,0.9); font-size: 1.1rem;">
                    You can manage your CPD course database here
                </p>
            </div>
        </div>
        <!-- Course Management Content -->
        <div class="by-admin-content">
            <!-- Course Management Actions -->
            <div class="course-management-header">
                <div class="header-left">
                    <h2>Course Library</h2>
                    <p>Manage your CPD course database</p>
                </div>
                <div class="header-right">
                    <button class="btn btn-secondary" id="bulk-import-courses-btn">
                        <span class="btn-icon"><i class="fas fa-upload"></i></span>
                        Bulk Import
                    </button>
                    <button class="btn btn-primary" id="add-course-btn">
                        <span class="btn-icon"><i class="fas fa-plus"></i></span>
                        Add New Course
                    </button>
                </div>
            </div>

        <!-- Filters and Search -->
        <div class="course-filters">
            <div class="filter-group">
                <select id="category-filter" class="form-control">
                    <option value="all">All Categories</option>
                </select>
            </div>
            <div class="filter-group">
                <select id="provider-filter" class="form-control">
                    <option value="all">All Providers</option>
                </select>
            </div>
            <div class="filter-group">
                <div class="search-input-group">
                    <input type="text" id="search-courses" class="form-control" placeholder="Search courses...">
                    <button class="btn btn-primary" id="search-button" type="button">
                        <span class="btn-icon"><i class="fas fa-search"></i></span>
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
                            <span class="btn-icon"><i class="fas fa-clipboard-list"></i></span>
                            Card View
                        </button>
                        <button class="btn btn-outline view-btn" id="table-view-btn" data-view="table">
                            <span class="btn-icon"><i class="fas fa-chart-bar"></i></span>
                            Table View
                        </button>
                    </div>
                    <button class="btn btn-outline" id="refresh-courses">
                        <span class="btn-icon"><i class="fas fa-sync-alt"></i></span>
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
            
            <!-- Course Pagination -->
            <div class="course-pagination-container" id="course-pagination-container" style="display: none;">
                <div class="pagination-info">
                    <span id="course-pagination-info">Loading...</span>
                </div>
                <div class="pagination-controls">
                    <button class="btn btn-outline" id="course-prev-page" disabled>
                        <i class="fas fa-chevron-left"></i> Previous
                    </button>
                    <div class="pagination-numbers" id="course-page-numbers"></div>
                    <button class="btn btn-outline" id="course-next-page" disabled>
                        Next <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
            </div>
            </div>
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
                
                <!-- Duplicate Option -->
                <div class="form-group form-group-checkbox" style="display: none;">
                    <div class="checkbox-container">
                        <input type="checkbox" id="duplicate-course" name="duplicate_course">
                        <label for="duplicate-course" class="checkbox-label">
                            <span class="checkbox-custom"></span>
                            Duplicate existing course
                        </label>
                    </div>
                </div>
                
                <!-- Course Selection for Duplication -->
                <div class="form-group" id="course-selection-group" style="display: none;">
                    <label for="course-select">Select Course to Duplicate *</label>
                    <div class="custom-select-container">
                        <div class="custom-select" id="course-select">
                            <div class="select-trigger">
                                <span class="select-placeholder">Select a course...</span>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="select-dropdown">
                                <div class="select-search">
                                    <input type="text" id="course-search" placeholder="Search courses...">
                                </div>
                                <div class="select-options" id="course-options">
                                    <!-- Options will be loaded here -->
                                </div>
                                <div class="select-pagination" id="course-select-pagination">
                                    <!-- Pagination will be loaded here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
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
                <span class="btn-icon"><i class="fas fa-save"></i></span>
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
                <span class="btn-icon"><i class="fas fa-trash-alt"></i></span>
                Delete Course
            </button>
        </div>
    </div>
</div>

<!-- Bulk Import Modal -->
<div class="modal" id="bulk-import-modal">
    <div class="modal-content large">
        <div class="modal-header">
            <h3>Bulk Import Courses</h3>
            <button class="modal-close" id="close-bulk-import-modal">×</button>
        </div>
        <div class="modal-body">
            <!-- Data Structure Information -->
            <div class="import-info">
                <h4><i class="fas fa-info-circle"></i> CSV Data Structure</h4>
                <p>Your CSV file should contain the following columns in this exact order:</p>
                <div class="data-structure">
                    <div class="structure-item">
                        <span class="field-name">course_name</span>
                        <span class="field-desc">Course Name (Required)</span>
                    </div>
                    <div class="structure-item">
                        <span class="field-name">course_code</span>
                        <span class="field-desc">Course Code (Optional)</span>
                    </div>
                    <div class="structure-item">
                        <span class="field-name">course_category</span>
                        <span class="field-desc">Category Name (Required)</span>
                    </div>
                    <div class="structure-item">
                        <span class="field-name">course_provider</span>
                        <span class="field-desc">Provider Name (Required)</span>
                    </div>
                    <div class="structure-item">
                        <span class="field-name">course_duration</span>
                        <span class="field-desc">Duration in CPD minutes (Required, Number)</span>
                    </div>
                </div>
                
                <div class="sample-csv">
                    <h5>Sample CSV Format:</h5>
                    <pre>course_name,course_code,course_category,course_provider,course_duration
"Project Management Fundamentals","PM101","Project Management","Training Institute","120"
"Leadership Skills","LS201","Leadership","Corporate Academy","90"</pre>
                </div>
            </div>
            
            <!-- File Upload Area -->
            <div class="file-upload-area" id="file-upload-area">
                <div class="upload-content">
                    <div class="upload-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <h4>Drop your CSV file here</h4>
                    <p>or <span class="upload-link">click to browse</span></p>
                    <input type="file" id="csv-file-input" accept=".csv" style="display: none;">
                </div>
            </div>
            
            <!-- File Preview -->
            <div class="file-preview" id="file-preview" style="display: none;">
                <div class="preview-header">
                    <h5><i class="fas fa-file-csv"></i> File Preview</h5>
                    <button type="button" class="btn btn-sm btn-outline" id="remove-file">Remove</button>
                </div>
                <div class="preview-content">
                    <div class="file-info">
                        <span id="file-name"></span>
                        <span id="file-size"></span>
                    </div>
                    <div class="preview-table" id="preview-table">
                        <!-- CSV preview will be loaded here -->
                    </div>
                </div>
            </div>
            
            <!-- Import Progress -->
            <div class="import-progress" id="import-progress" style="display: none;">
                <div class="progress-header">
                    <h5><i class="fas fa-spinner fa-spin"></i> Importing Courses...</h5>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" id="progress-fill"></div>
                </div>
                <div class="progress-text" id="progress-text">0% Complete</div>
            </div>
            
            <!-- Import Results -->
            <div class="import-results" id="import-results" style="display: none;">
                <div class="results-header">
                    <h5><i class="fas fa-check-circle"></i> Import Complete</h5>
                </div>
                <div class="results-content" id="results-content">
                    <!-- Results will be displayed here -->
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" id="cancel-bulk-import">Cancel</button>
            <button type="button" class="btn btn-primary" id="start-import" disabled>
                <span class="btn-icon"><i class="fas fa-upload"></i></span>
                Start Import
            </button>
        </div>
    </div>
</div>

<style>
/* Course Management Page */
.course-management-page {
    min-height: 100vh;
}

/* Bulk Import Modal Styles */
.modal-content.large {
    max-width: 800px;
    width: 95%;
}

.import-info {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 24px;
}

.import-info h4 {
    margin: 0 0 16px 0;
    color: #1e293b;
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.import-info p {
    margin: 0 0 16px 0;
    color: #64748b;
    font-size: 14px;
}

.data-structure {
    display: grid;
    grid-template-columns: 1fr;
    gap: 8px;
    margin-bottom: 16px;
}

.structure-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 12px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
}

.field-name {
    font-family: 'Courier New', monospace;
    font-weight: 600;
    color: #1e293b;
    min-width: 120px;
    font-size: 13px;
}

.field-desc {
    color: #64748b;
    font-size: 13px;
}

.sample-csv {
    margin-top: 16px;
}

.sample-csv h5 {
    margin: 0 0 8px 0;
    color: #1e293b;
    font-size: 14px;
    font-weight: 600;
}

.sample-csv pre {
    background: #1e293b;
    color: #e2e8f0;
    padding: 12px;
    border-radius: 6px;
    font-size: 12px;
    overflow-x: auto;
    margin: 0;
}

/* File Upload Area */
.file-upload-area {
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    padding: 40px 20px;
    text-align: center;
    background: #f8fafc;
    transition: all 0.3s ease;
    cursor: pointer;
    margin-bottom: 24px;
}

.file-upload-area:hover {
    border-color: #8b5a96;
    background: #f1f5f9;
}

.file-upload-area.dragover {
    border-color: #8b5a96;
    background: #f0f9ff;
    transform: scale(1.02);
}

.upload-content {
    pointer-events: none;
}

.upload-icon {
    font-size: 48px;
    color: #94a3b8;
    margin-bottom: 16px;
}

.upload-content h4 {
    margin: 0 0 8px 0;
    color: #1e293b;
    font-size: 18px;
    font-weight: 600;
}

.upload-content p {
    margin: 0;
    color: #64748b;
    font-size: 14px;
}

.upload-link {
    color: #8b5a96;
    font-weight: 600;
    text-decoration: underline;
    cursor: pointer;
}

/* File Preview */
.file-preview {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    margin-bottom: 24px;
}

.preview-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
    border-radius: 8px 8px 0 0;
}

.preview-header h5 {
    margin: 0;
    color: #1e293b;
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.preview-content {
    padding: 20px;
}

.file-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    padding: 12px;
    background: #f1f5f9;
    border-radius: 6px;
}

.file-info span:first-child {
    font-weight: 600;
    color: #1e293b;
}

.file-info span:last-child {
    color: #64748b;
    font-size: 14px;
}

.preview-table {
    max-height: 300px;
    overflow: auto;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
}

.preview-table table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.preview-table th,
.preview-table td {
    padding: 8px 12px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
}

.preview-table th {
    background: #f8fafc;
    font-weight: 600;
    color: #1e293b;
    position: sticky;
    top: 0;
}

.preview-table td {
    color: #475569;
}

.preview-table tr:last-child td {
    border-bottom: none;
}

/* Import Progress */
.import-progress {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 24px;
}

.progress-header h5 {
    margin: 0 0 16px 0;
    color: #1e293b;
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.progress-bar {
    width: 100%;
    height: 8px;
    background: #e2e8f0;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 8px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #8b5a96, #6b4c93);
    width: 0%;
    transition: width 0.3s ease;
}

.progress-text {
    text-align: center;
    color: #64748b;
    font-size: 14px;
    font-weight: 500;
}

/* Import Results */
.import-results {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 20px;
}

.results-header h5 {
    margin: 0 0 16px 0;
    color: #1e293b;
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.results-content {
    color: #475569;
    font-size: 14px;
}

.results-content .success {
    color: #059669;
}

.results-content .error {
    color: #dc2626;
}

.results-content .warning {
    color: #d97706;
}

/* Course Pagination Styles */
.course-pagination-container {
    display: flex;
    justify-content: center;
    align-items: center;
    flex-direction: column;
    gap: 15px;
    padding: 20px;
    background: white;
    border-top: 1px solid #e2e8f0;
    margin-top: 20px;
}

.pagination-info {
    text-align: center;
    color: #6b7280;
    font-size: 14px;
    font-weight: 500;
}

.pagination-controls {
    display: flex;
    align-items: center;
    gap: 10px;
}

.pagination-numbers {
    display: flex;
    gap: 8px;
    align-items: center;
}

.page-number {
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    background: white;
    color: #374151;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s ease;
    min-width: 40px;
    text-align: center;
}

.page-number:hover {
    background: #f3f4f6;
    border-color: #8b5a96;
}

.page-number.active {
    background: #8b5a96;
    color: white;
    border-color: #8b5a96;
}

.page-number:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Custom Select Styles */
.custom-select-container {
    position: relative;
    width: 100%;
}

.custom-select {
    position: relative;
    width: 100%;
}

.select-trigger {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    background: white;
    cursor: pointer;
    transition: all 0.3s ease;
    min-height: 48px;
}

.select-trigger:hover {
    border-color: #8b5a96;
}

.select-trigger.active {
    border-color: #8b5a96;
    box-shadow: 0 0 0 4px rgba(139, 90, 150, 0.1);
}

.select-placeholder {
    color: #9ca3af;
    font-size: 14px;
}

.select-trigger.selected .select-placeholder {
    color: #1f2937;
    font-weight: 500;
}

.select-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 2px solid #8b5a96;
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    z-index: 1000;
    max-height: 300px;
    display: none;
    flex-direction: column;
}

.select-dropdown.show {
    display: flex;
}

.select-search {
    padding: 12px;
    border-bottom: 1px solid #e5e7eb;
}

.select-search input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
    outline: none;
}

.select-search input:focus {
    border-color: #8b5a96;
    box-shadow: 0 0 0 2px rgba(139, 90, 150, 0.1);
}

.select-options {
    flex: 1;
    overflow-y: auto;
    max-height: 200px;
}

.select-option {
    padding: 12px 16px;
    cursor: pointer;
    border-bottom: 1px solid #f3f4f6;
    transition: background-color 0.2s ease;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.select-option:hover {
    background: #f8fafc;
}

.select-option.selected {
    background: #f0f9ff;
    color: #8b5a96;
}

.option-name {
    font-weight: 500;
    color: #1f2937;
    font-size: 14px;
}

.option-category {
    font-size: 12px;
    color: #6b7280;
}

.select-pagination {
    padding: 12px;
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: center;
    gap: 4px;
}

.select-page-number {
    padding: 4px 8px;
    border: 1px solid #d1d5db;
    background: white;
    color: #374151;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.2s ease;
}

.select-page-number:hover {
    background: #f3f4f6;
    border-color: #8b5a96;
}

.select-page-number.active {
    background: #8b5a96;
    color: white;
    border-color: #8b5a96;
}

/* Checkbox Styles */
.checkbox-container {
    display: flex;
    align-items: center;
    gap: 12px;
}

.checkbox-container input[type="checkbox"] {
    width: 20px;
    height: 20px;
    margin: 0;
    cursor: pointer;
    accent-color: #8b5a96;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    color: #374151;
    margin: 0;
}

.checkbox-custom {
    width: 20px;
    height: 20px;
    border: 2px solid #d1d5db;
    border-radius: 4px;
    position: relative;
    flex-shrink: 0;
    transition: all 0.3s ease;
    background: white;
    display: none; /* Hide custom checkbox since we're using native */
}

.checkbox-container input[type="checkbox"]:checked + .checkbox-label .checkbox-custom {
    background: #8b5a96;
    border-color: #8b5a96;
    transform: scale(1.1);
}

.checkbox-container input[type="checkbox"]:checked + .checkbox-label .checkbox-custom::after {
    content: "✓";
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-size: 12px;
    font-weight: bold;
}

/* Course Header */
.course-header {
    background: linear-gradient(135deg, #8b5a96 0%, #6b4c93 100%);
    color: white;
    padding: 40px 0;
    margin-bottom: 30px;
    padding-top: 120px;
}

.course-header .header-content {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.course-header .breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    opacity: 0.9;
}

.course-header .breadcrumb a {
    color: white;
    text-decoration: none;
    transition: opacity 0.2s;
}

.course-header .breadcrumb a:hover {
    opacity: 0.8;
}

.course-header .separator {
    margin: 0 8px;
}

.course-header h1 {
    margin: 0;
    font-size: 2.5rem;
    font-weight: 600;
}

/* Course Management Header */
.course-management-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding: 20px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.header-left h2 {
    margin: 0 0 5px 0;
    color: #2d3748;
    font-size: 1.8rem;
}

.header-left p {
    margin: 0;
    color: #718096;
}

/* Course Filters */
.course-filters {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
    padding: 20px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    flex-wrap: wrap;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    min-width: 150px;
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

.filter-group label {
    margin-bottom: 5px;
    font-weight: 500;
    color: #4a5568;
}

/* Course List Container */
.course-list-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.course-list-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #e2e8f0;
    background: #f7fafc;
}

.list-actions {
    display: flex;
    gap: 15px;
    align-items: center;
}

.view-toggle {
    display: flex;
    gap: 5px;
    background: #f1f5f9;
    border-radius: 8px;
    padding: 4px;
}

.view-btn {
    padding: 8px 16px;
    font-size: 14px;
    border: none;
    background: transparent;
    color: #64748b;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.view-btn.active {
    background: white;
    color: #1e293b;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.view-btn:hover:not(.active) {
    background: rgba(255, 255, 255, 0.5);
    color: #1e293b;
}

.list-info {
    font-weight: 500;
    color: #4a5568;
}

/* Course List */
.course-list {
    min-height: 400px;
    padding: 10px;
}

/* Table Styles */
.table-container {
    overflow-x: auto;
    min-height: 400px;
}

.courses-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.courses-table th,
.courses-table td {
    padding: 12px 16px;
    text-align: left;
    border-right: 1px solid #e2e8f0;
    vertical-align: middle;
}

.courses-table th {
    background: #f8fafc;
    font-weight: 600;
    color: #2d3748;
    border-bottom: 2px solid #e2e8f0;
    font-size: 14px;
}

.courses-table td {
    border-bottom: 1px solid #e2e8f0;
    font-size: 14px;
}

.courses-table tbody tr:hover {
    background: #f8fafc;
}

.courses-table tbody tr:last-child td {
    border-bottom: none;
}

/* Remove right border from last column */
.courses-table th:last-child,
.courses-table td:last-child {
    border-right: none;
}

/* Action buttons in table */
.table-actions {
    display: flex;
    gap: 8px;
}

.table-actions .btn {
    padding: 6px 12px;
    font-size: 12px;
    min-width: auto;
}

.course-name-cell {
    max-width: 200px;
}

.course-description-small {
    font-size: 12px;
    color: #64748b;
    margin-top: 4px;
    line-height: 1.4;
}

.course-item {
    display: flex;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #e2e8f0;
    transition: background 0.2s;
    margin-bottom: 10px;
}

.course-item:hover {
    background: #f7fafc;
}

.course-item:last-child {
    border-bottom: none;
}

.course-info {
    flex: 1;
    margin-right: 20px;
}

.course-name {
    font-size: 1.1rem;
    font-weight: 600;
    color: #2d3748;
    margin: 0 0 5px 0;
}

.course-details {
    display: flex;
    gap: 15px;
    font-size: 0.9rem;
    color: #718096;
    margin-bottom: 5px;
}

.course-description {
    font-size: 0.9rem;
    color: #4a5568;
    margin: 0;
}

.course-actions {
    display: flex;
    gap: 10px;
}

/* Loading State */
.loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 20px;
    color: #718096;
}

.loading-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #e2e8f0;
    border-top: 4px solid #667eea;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 15px;
}

/* Load More Indicator */
.load-more-indicator {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 30px 20px;
    color: #718096;
    border-top: 1px solid #e2e8f0;
    background: #f7fafc;
}

.load-more-indicator .loading-spinner {
    width: 30px;
    height: 30px;
    margin-bottom: 10px;
}

.load-more-indicator p {
    margin: 0;
    font-size: 0.9rem;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Empty State */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 20px;
    color: #718096;
}

.empty-state-icon {
    font-size: 48px;
    margin-bottom: 15px;
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
    background: rgba(0, 0, 0, 0.5);
    animation: fadeIn 0.3s ease-out;
}

body.modal-open {
    overflow: hidden;
}

.modal.show {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 600px;
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

.modal-header h3 {
    margin: 0;
    color: #2d3748;
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

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 20px;
    border-top: 1px solid #e2e8f0;
    background: #f7fafc;
}

/* Form Styles */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #4a5568;
}

.form-control {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.2s;
}

.form-control:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

/* Button Styles */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}

.btn-primary {
    background: #667eea;
    color: white;
}

.btn-primary:hover {
    background: #5a67d8;
}

.btn-secondary {
    background: #8b5a96;
    color: white;
}

.btn-secondary:hover {
    background: #7a4f85;
}

.btn-outline {
    background: white;
    color: #4a5568;
    border: 1px solid #e2e8f0;
}

.btn-outline:hover {
    background: #f7fafc;
}

.btn-danger {
    background: #e53e3e;
    color: white;
}

.btn-danger:hover {
    background: #c53030;
}

.btn-icon {
    font-size: 16px;
}

/* Course Preview in Delete Modal */
.course-preview {
    background: #f7fafc;
    padding: 15px;
    border-radius: 8px;
    margin: 15px 0;
}

.course-preview .course-name {
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 5px;
}

.course-preview .course-details {
    color: #718096;
    font-size: 0.9rem;
}

.warning-text {
    color: #e53e3e;
    font-weight: 500;
    margin-top: 10px;
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .course-management-header {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
    }
    
    .course-filters {
        flex-direction: column;
        gap: 15px;
    }
    
    .filter-group {
        min-width: auto;
    }
    
    .course-item {
        flex-direction: column;
        align-items: stretch;
        gap: 15px;
    }
    
    .course-info {
        margin-right: 0;
    }
    
    .course-actions {
        justify-content: flex-end;
    }
    
    .modal-content {
        width: 95%;
        margin: 20px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {

    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    var currentTab = '<?php echo $active_tab; ?>';
    
    var iipm_ajax = {
        nonce: '<?php echo wp_create_nonce('iipm_portal_nonce'); ?>'
    };

    let courses = [];
    let categories = [];
    let providers = [];
    let currentCourseId = null;
    let currentPage = 1;
    let totalPages = 1;
    let isLoading = false;
    let coursesPerPage = 10;
    
    // Duplicate course variables
    let selectedCourseForDuplicate = null;
    let courseSelectCurrentPage = 1;
    let courseSelectTotalPages = 1;
    let courseSelectSearchTerm = '';
    
    // Bulk import variables
    let selectedFile = null;
    let csvData = [];
    
    // Initialize page
    initializePage();
    
    function initializePage() {
        loadCategories();
        loadProviders();
        loadCourses();
        setupEventListeners();
        setupBulkImportListeners();
    }
    
    function setupEventListeners() {
        // Add course button
        $('#add-course-btn').on('click', function() {
            openCourseModal();
        });
        
        // View toggle buttons
        $('.view-btn').on('click', function() {
            const view = $(this).data('view');
            $('.view-btn').removeClass('active');
            $(this).addClass('active');
            
            if (view === 'table') {
                $('#course-list').hide();
                $('#table-container').show();
            } else {
                $('#table-container').hide();
                $('#course-list').show();
            }
            
            // Redisplay courses in the selected view
            displayCourses(courses);
        });
        
        // Modal controls
        $('#close-modal, #cancel-course').on('click', function() {
            closeCourseModal();
        });
        
        $('#close-delete-modal, #cancel-delete').on('click', function() {
            closeDeleteModal();
        });
        
        // Duplicate course functionality
        $('#duplicate-course').on('change', function() {
            if ($(this).is(':checked')) {
                $('#course-selection-group').show();
                loadCoursesForSelection();
            } else {
                $('#course-selection-group').hide();
                resetDuplicateForm();
            }
        });
        
        // Custom select functionality
        $('.select-trigger').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).toggleClass('active');
            $('.select-dropdown').toggleClass('show');
        });
        
        // Course search in select
        $('#course-search').on('input', function() {
            courseSelectSearchTerm = $(this).val();
            courseSelectCurrentPage = 1;
            loadCoursesForSelection();
        });
        
        // Pagination for course selection
        $(document).on('click', '.select-page-number', function(e) {
            e.preventDefault();
            e.stopPropagation();
            courseSelectCurrentPage = parseInt($(this).data('page'));
            loadCoursesForSelection();
        });
        
        // Course selection
        $(document).on('click', '.select-option', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const courseId = $(this).data('course-id');
            const courseName = $(this).find('.option-name').text();
            const courseCategory = $(this).find('.option-category').text();
            
            selectedCourseForDuplicate = {
                id: courseId,
                name: courseName,
                category: courseCategory
            };
            
            // Update select display
            $('.select-trigger .select-placeholder').text(`${courseName} (${courseCategory})`);
            $('.select-dropdown').removeClass('show');
            $('.select-trigger').removeClass('active');
            
            // Load course data and fill form
            loadCourseForDuplication(courseId);
        });
        
        // Course pagination
        $('#course-prev-page').on('click', function() {
            if (currentPage > 1) {
                currentPage--;
                loadCourses(false);
            }
        });
        
        $('#course-next-page').on('click', function() {
            if (currentPage < totalPages) {
                currentPage++;
                loadCourses(false);
            }
        });
        
        $(document).on('click', '.page-number', function() {
            currentPage = parseInt($(this).text());
            loadCourses(false);
        });
        
        // Save course
        $('#save-course').on('click', function() {
            saveCourse();
        });
        
        // Confirm delete
        $('#confirm-delete').on('click', function() {
            deleteCourse();
        });
        
        // Filters
        $('#category-filter, #provider-filter').on('change', function() {
            currentPage = 1;
            loadCourses();
        });
        
        // Search functionality
        $('#search-courses').on('keypress', function(e) {
            if (e.which === 13) { // Enter key
                filterCourses();
            }
        });
        
        $('#search-button').on('click', function() {
            filterCourses();
        });
        
        $('#clear-filters').on('click', function() {
            clearFilters();
        });
        
        $('#refresh-courses').on('click', function() {
            loadCourses(true);
        });
        
        // Close modal on outside click
        $(document).on('click', function(e) {
            if ($(e.target).hasClass('modal')) {
                closeCourseModal();
                closeDeleteModal();
                $('#bulk-import-modal').removeClass('show').hide();
                $('body').removeClass('modal-open');
                resetBulkImportModal();
            }
        });
        
        // Close dropdown when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.custom-select').length) {
                $('.select-dropdown').removeClass('show');
                $('.select-trigger').removeClass('active');
            }
        });
    }
    
    function setupBulkImportListeners() {
        // Open bulk import modal
        $('#bulk-import-courses-btn').on('click', function() {
            resetBulkImportModal();
            $('#bulk-import-modal').addClass('show').show();
            $('body').addClass('modal-open');
        });
        
        // Close bulk import modal
        $('#close-bulk-import-modal, #cancel-bulk-import').on('click', function() {
            $('#bulk-import-modal').removeClass('show').hide();
            $('body').removeClass('modal-open');
            resetBulkImportModal();
        });
        
        // File upload area click
        $('#file-upload-area').on('click', function() {
            $('#csv-file-input').click();
        });
        
        // File input change
        $('#csv-file-input').on('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                handleFileSelect(file);
            }
        });
        
        // Drag and drop functionality
        $('#file-upload-area').on('dragover', function(e) {
            e.preventDefault();
            $(this).addClass('dragover');
        });
        
        $('#file-upload-area').on('dragleave', function(e) {
            e.preventDefault();
            $(this).removeClass('dragover');
        });
        
        $('#file-upload-area').on('drop', function(e) {
            e.preventDefault();
            $(this).removeClass('dragover');
            const files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                handleFileSelect(files[0]);
            }
        });
        
        // Remove file
        $('#remove-file').on('click', function() {
            resetFileSelection();
        });
        
        // Start import
        $('#start-import').on('click', function() {
            if (selectedFile && csvData.length > 0) {
                startBulkImport();
            }
        });
    }
    
    function updateCoursePagination(pagination) {
        if (!pagination) return;
        
        // Show pagination container
        $('#course-pagination-container').show();
        
        // Update pagination info
        $('#course-pagination-info').text(`Showing ${pagination.start} to ${pagination.end} of ${pagination.total} courses`);
        
        // Update pagination controls
        $('#course-prev-page').prop('disabled', pagination.page <= 1);
        $('#course-next-page').prop('disabled', pagination.page >= pagination.total_pages);
        
        // Generate page numbers
        const pageNumbers = $('#course-page-numbers');
        pageNumbers.empty();
        
        const maxVisiblePages = 5;
        let startPage = Math.max(1, pagination.page - Math.floor(maxVisiblePages / 2));
        let endPage = Math.min(pagination.total_pages, startPage + maxVisiblePages - 1);
        
        if (endPage - startPage + 1 < maxVisiblePages) {
            startPage = Math.max(1, endPage - maxVisiblePages + 1);
        }
        
        for (let i = startPage; i <= endPage; i++) {
            const pageBtn = $('<button>').addClass('page-number').text(i);
            if (i === pagination.page) pageBtn.addClass('active');
            pageBtn.on('click', function() { 
                currentPage = i;
                loadCourses(false);
            });
            pageNumbers.append(pageBtn);
        }
    }
    
    function loadCategories() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'iipm_get_categories',
                nonce: iipm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    categories = response.data;
                    const categorySelect = $('#course-category');
                    categorySelect.find('option:not(:first)').remove();
                    categories.forEach(function(category) {
                        categorySelect.append(`<option value="${category.id}">${category.name}</option>`);
                    });
                    
                    // Also populate filter dropdown
                    const filterSelect = $('#category-filter');
                    filterSelect.find('option:not(:first)').remove();
                    categories.forEach(function(category) {
                        filterSelect.append(`<option value="${category.id}">${category.name}</option>`);
                    });
                } else {
                    console.error('Failed to load categories');
                }
            },
            error: function() {
                console.error('Failed to load categories');
            }
        });
    }
    
    function loadProviders() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'iipm_get_providers',
                nonce: iipm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    providers = response.data;
                    const providerSelect = $('#provider-filter');
                    providerSelect.find('option:not(:first)').remove();
                    providers.forEach(function(provider) {
                        // Provider is a string, not an object
                        providerSelect.append(`<option value="${provider}">${provider}</option>`);
                    });
                } else {
                    console.error('Failed to load providers');
                }
            },
            error: function() {
                console.error('Failed to load providers');
            }
        });
    }
    
    function loadCourses(reset = true, filters = null) {
        if (isLoading) return;
        
        // Get current filter values
        const categoryFilter = filters ? filters.category : $('#category-filter').val();
        const providerFilter = filters ? filters.provider : $('#provider-filter').val();
        const searchTerm = filters ? filters.search : $('#search-courses').val().trim();
        
        if (reset) {
            currentPage = 1;
            courses = [];
            showLoading();
        }
        
        isLoading = true;
        
        // Prepare data for backend
        const ajaxData = {
            action: 'iipm_get_all_courses_paginated',
            page: currentPage,
            per_page: coursesPerPage,
            nonce: iipm_ajax.nonce
        };
        
        // Add filter parameters if they exist
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
            url: ajaxurl,
            type: 'POST',
            data: ajaxData,
            success: function(response) {
                if (response.success) {
                    const newCourses = response.data.courses || response.data;
                    const pagination = response.data.pagination;
                    
                    courses = newCourses;
                    totalPages = pagination ? pagination.total_pages : 1;
                    currentPage = pagination ? pagination.page : 1;
                    
                    displayCourses(courses);
                    updateCourseCount();
                    updateCoursePagination(pagination);
                } else {
                    showError('Failed to load courses: ' + response.data);
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
    
    function displayCourses(courses) {
        const $courseList = $('#course-list');
        const $tableBody = $('#courses-table-body');
        
        // Clear existing content
        $courseList.empty();
        $tableBody.empty();
        
        if (!courses || courses.length === 0) {
            $courseList.html(`
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h3>No courses found</h3>
                    <p>Try adjusting your search criteria or add some courses to get started.</p>
                </div>
            `);
            $tableBody.html('<tr><td colspan="6" style="text-align: center; padding: 40px; color: #6b7280;">No courses found</td></tr>');
            return;
        }
        
        // Display courses in card view
        courses.forEach(function(course) {
            const categoryName = course.course_category || 'Uncategorized';
            const courseCard = $(`
                <div class="course-item">
                    <div class="course-info">
                        <h3 class="course-name">${course.course_name}</h3>
                        <div class="course-details">
                            <span><strong>Code:</strong> ${course.LIA_Code || 'N/A'}</span>
                            <span><strong>Category:</strong> ${categoryName}</span>
                            <span><strong>Provider:</strong> ${course.crs_provider}</span>
                            <span><strong>Duration:</strong> ${course.course_cpd_mins} mins</span>
                        </div>
                    </div>
                    <div class="course-actions">
                        <button class="btn btn-outline edit-course" data-course-id="${course.id}">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn btn-danger delete-course" data-course-id="${course.id}">
                            <i class="fas fa-trash-alt"></i> Delete
                        </button>
                    </div>
                </div>
            `);
            $courseList.append(courseCard);
        });
        
        // Display courses in table view
        courses.forEach(function(course) {
            const categoryName = course.course_category || 'Uncategorized';
            const tableRow = $(`
                <tr>
                    <td class="course-name-cell">
                        <div class="course-name">${course.course_name}</div>
                    </td>
                    <td>${course.LIA_Code || 'N/A'}</td>
                    <td>${categoryName}</td>
                    <td>${course.crs_provider}</td>
                    <td>${course.course_cpd_mins} mins</td>
                    <td>
                        <div class="table-actions">
                            <button class="btn btn-outline edit-course" data-course-id="${course.id}">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-danger delete-course" data-course-id="${course.id}">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `);
            $tableBody.append(tableRow);
        });
        
        // Add event listeners for edit and delete buttons
        $('.edit-course').on('click', function() {
            const courseId = $(this).data('course-id');
            openCourseModal(courseId);
        });
        
        $('.delete-course').on('click', function() {
            const courseId = $(this).data('course-id');
            confirmDeleteCourse(courseId);
        });
    }
    
    function updateCourseCount() {
        const count = courses ? courses.length : 0;
        $('#course-count').text(`${count} courses loaded`);
    }
    
    function showLoading() {
        $('#course-list').html(`
            <div class="loading-state">
                <div class="loading-spinner"></div>
                <p>Loading courses...</p>
            </div>
        `);
    }
    
    function hideLoading() {
        // Loading will be replaced by displayCourses
    }
    
    function showError(message) {
        $('#course-list').html(`
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3>Error Loading Courses</h3>
                <p>${message}</p>
            </div>
        `);
    }
    
    function openCourseModal(courseId = null) {
        currentCourseId = courseId;
        
        if (courseId) {
            $('#modal-title').text('Edit Course');
            // Hide duplicate option for editing
            $('#duplicate-course').closest('.form-group').hide();
            $('#course-selection-group').hide();
            resetDuplicateForm();
            loadCourseData(courseId);
        } else {
            $('#modal-title').text('Add New Course');
            $('#course-form')[0].reset();
            $('#course-id').val('');
            // Show duplicate option for new courses
            $('#duplicate-course').closest('.form-group').show();
            resetDuplicateForm();
        }
        
        $('#course-modal').addClass('show').show();
        $('body').addClass('modal-open');
    }
    
    function closeCourseModal() {
        $('#course-modal').removeClass('show').hide();
        $('body').removeClass('modal-open');
        currentCourseId = null;
        resetDuplicateForm();
    }
    
    function loadCourseData(courseId) {
        const course = courses.find(c => c.id == courseId);
        if (course) {
            $('#course-name').val(course.course_name);
            $('#course-code').val(course.LIA_Code || '');
            
            // Find category ID by matching category name
            const categoryId = findCategoryIdByName(course.course_category);
            $('#course-category').val(categoryId);
            
            $('#course-provider').val(course.crs_provider);
            $('#course-duration').val(course.course_cpd_mins);
        }
    }
    
    function findCategoryIdByName(categoryName) {
        if (!categories || !categoryName) return '';
        
        const category = categories.find(cat => cat.name === categoryName);
        return category ? category.id : '';
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
            if (window.notifications) {
                notifications.error("Error", 'Please fill in all required fields.');
            }
            return;
        }
        
        const $saveBtn = $('#save-course');
        const originalText = $saveBtn.html();
        $saveBtn.html('<span class="btn-icon"><i class="fas fa-spinner fa-spin"></i></span> Saving...');
        $saveBtn.prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    if (window.notifications) {
                        notifications.success("Success", "Course saved successfully!");
                    }
                    closeCourseModal();
                    loadCourses();
                } else {
                    if (window.notifications) {
                        notifications.error("Error", 'Failed to save course: ' + response.data);
                    }
                }
            },
            error: function() {
                if (window.notifications) {
                    notifications.error("Error", 'Failed to save course');
                }
            },
            complete: function() {
                $saveBtn.html(originalText);
                $saveBtn.prop('disabled', false);
            }
        });
    }
    
    function confirmDeleteCourse(courseId) {
        const course = courses.find(c => c.id == courseId);
        if (course) {
            const categoryName = course.course_category || 'Unknown';
            
            $('#delete-course-preview').html(`
                <div class="course-name">${course.course_name}</div>
                <div class="course-details">
                    <span><strong>Code:</strong> ${course.LIA_Code || 'N/A'}</span>
                    <span><strong>Category:</strong> ${categoryName}</span>
                    <span><strong>Provider:</strong> ${course.crs_provider}</span>
                </div>
            `);
            
            currentCourseId = courseId;
            $('#delete-modal').addClass('show').show();
            $('body').addClass('modal-open');
        }
    }
    
    function closeDeleteModal() {
        $('#delete-modal').removeClass('show').hide();
        $('body').removeClass('modal-open');
        currentCourseId = null;
    }
    
    function deleteCourse() {
        if (!currentCourseId) return;
        
        const $deleteBtn = $('#confirm-delete');
        const originalText = $deleteBtn.html();
        $deleteBtn.html('<span class="btn-icon"><i class="fas fa-spinner fa-spin"></i></span> Deleting...');
        $deleteBtn.prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'iipm_delete_course_v1',
                course_id: currentCourseId,
                nonce: iipm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    if (window.notifications) {
                        notifications.success("Success", "Course deleted successfully!");
                    }
                    closeDeleteModal();
                    loadCourses();
                } else {
                    if (window.notifications) {
                        notifications.error("Error", 'Failed to delete course: ' + response.data);
                    }
                }
            },
            error: function() {
                if (window.notifications) {
                    notifications.error("Error", 'Failed to delete course');
                }
            },
            complete: function() {
                $deleteBtn.html(originalText);
                $deleteBtn.prop('disabled', false);
            }
        });
    }
    
    function filterCourses() {
        currentPage = 1;
        loadCourses();
    }
    
    function clearFilters() {
        $('#category-filter').val('all');
        $('#provider-filter').val('all');
        $('#search-courses').val('');
        currentPage = 1;
        loadCourses();
    }
    
    function resetDuplicateForm() {
        $('#duplicate-course').prop('checked', false);
        $('#course-selection-group').hide();
        $('.select-trigger .select-placeholder').text('Select a course...');
        $('.select-dropdown').removeClass('show');
        $('.select-trigger').removeClass('active');
        selectedCourseForDuplicate = null;
        
        // Re-enable all form fields
        $('#course-name, #course-code, #course-provider, #course-duration').prop('disabled', false);
        $('#course-category').prop('disabled', false);
    }
    
    function loadCoursesForSelection() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'iipm_get_courses_for_selection',
                page: courseSelectCurrentPage,
                per_page: 10,
                search: courseSelectSearchTerm,
                nonce: iipm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayCourseSelectionOptions(response.data.courses);
                    updateCourseSelectionPagination(response.data.pagination);
                }
            },
            error: function() {
                console.error('Failed to load courses for selection');
            }
        });
    }
    
    function displayCourseSelectionOptions(courses) {
        const $options = $('#course-options');
        $options.empty();
        
        if (!courses || courses.length === 0) {
            $options.html('<div class="select-option">No courses found</div>');
            return;
        }
        
        courses.forEach(function(course) {
            const option = $(`
                <div class="select-option" data-course-id="${course.id}">
                    <div class="option-name">${course.course_name}</div>
                    <div class="option-category">${course.course_category}</div>
                </div>
            `);
            $options.append(option);
        });
    }
    
    function updateCourseSelectionPagination(pagination) {
        if (!pagination) return;
        
        courseSelectTotalPages = pagination.total_pages;
        const paginationContainer = $('#course-select-pagination');
        paginationContainer.empty();
        
        if (pagination.total_pages <= 1) return;
        
        const maxVisiblePages = 3;
        let startPage = Math.max(1, courseSelectCurrentPage - Math.floor(maxVisiblePages / 2));
        let endPage = Math.min(pagination.total_pages, startPage + maxVisiblePages - 1);
        
        if (endPage - startPage + 1 < maxVisiblePages) {
            startPage = Math.max(1, endPage - maxVisiblePages + 1);
        }
        
        for (let i = startPage; i <= endPage; i++) {
            const pageBtn = $('<button>').addClass('select-page-number').text(i);
            if (i === courseSelectCurrentPage) pageBtn.addClass('active');
            pageBtn.attr('data-page', i);
            paginationContainer.append(pageBtn);
        }
    }
    
    function loadCourseForDuplication(courseId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'iipm_get_course_for_duplication',
                course_id: courseId,
                nonce: iipm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    const course = response.data;
                    
                    // Fill form with course data
                    $('#course-name').val(course.course_name).prop('disabled', true);
                    $('#course-code').val(course.LIA_Code || '').prop('disabled', true);
                    $('#course-provider').val(course.crs_provider).prop('disabled', true);
                    $('#course-duration').val(course.course_cpd_mins).prop('disabled', true);
                    
                    // Only category remains editable
                    $('#course-category').val('').prop('disabled', false);
                }
            },
            error: function() {
                console.error('Failed to load course for duplication');
            }
        });
    }
    
    // Bulk Import Functions
    function handleFileSelect(file) {
        if (!file.name.toLowerCase().endsWith('.csv')) {
            alert('Please select a CSV file.');
            return;
        }
        
        selectedFile = file;
        
        // Show file info
        $('#file-name').text(file.name);
        $('#file-size').text(formatFileSize(file.size));
        
        // Parse CSV
        const reader = new FileReader();
        reader.onload = function(e) {
            const csv = e.target.result;
            parseCSV(csv);
        };
        reader.readAsText(file);
    }
    
    function parseCSV(csv) {
        const lines = csv.split('\n').filter(line => line.trim());
        if (lines.length < 2) {
            alert('CSV file must contain at least a header row and one data row.');
            return;
        }
        
        const headers = lines[0].split(',').map(h => h.trim().replace(/"/g, ''));
        const expectedHeaders = ['course_name', 'course_code', 'course_category', 'course_provider', 'course_duration'];
        
        // Validate headers
        const headerMismatch = expectedHeaders.some((expected, index) => 
            headers[index] !== expected
        );
        
        if (headerMismatch) {
            alert('CSV headers do not match expected format. Please check the column order.');
            return;
        }
        
        // Parse data rows
        csvData = [];
        for (let i = 1; i < lines.length; i++) {
            const values = lines[i].split(',').map(v => v.trim().replace(/"/g, ''));
            if (values.length >= 5) {
                csvData.push({
                    course_name: values[0],
                    course_code: values[1],
                    course_category: values[2],
                    course_provider: values[3],
                    course_duration: parseInt(values[4])
                });
            }
        }
        
        if (csvData.length === 0) {
            alert('No valid data rows found in CSV file.');
            return;
        }
        
        displayCSVPreview();
        $('#file-upload-area').hide();
        $('#file-preview').show();
        $('#start-import').prop('disabled', false);
    }
    
    function displayCSVPreview() {
        const previewTable = $('#preview-table');
        let tableHTML = '<table><thead><tr>';
        tableHTML += '<th>Course Name</th><th>Course Code</th><th>Category</th><th>Provider</th><th>Duration</th>';
        tableHTML += '</tr></thead><tbody>';
        
        // Show first 5 rows as preview
        const previewRows = csvData.slice(0, 5);
        previewRows.forEach(row => {
            tableHTML += '<tr>';
            tableHTML += `<td>${row.course_name}</td>`;
            tableHTML += `<td>${row.course_code || 'N/A'}</td>`;
            tableHTML += `<td>${row.course_category}</td>`;
            tableHTML += `<td>${row.course_provider}</td>`;
            tableHTML += `<td>${row.course_duration}</td>`;
            tableHTML += '</tr>';
        });
        
        if (csvData.length > 5) {
            tableHTML += `<tr><td colspan="5" style="text-align: center; font-style: italic;">... and ${csvData.length - 5} more rows</td></tr>`;
        }
        
        tableHTML += '</tbody></table>';
        previewTable.html(tableHTML);
    }
    
    function startBulkImport() {
        $('#file-preview').hide();
        $('#import-progress').show();
        $('#start-import').prop('disabled', true);
        
        let imported = 0;
        let errors = [];
        const total = csvData.length;
        
        function importBatch(startIndex) {
            const batchSize = 5;
            const endIndex = Math.min(startIndex + batchSize, total);
            const batch = csvData.slice(startIndex, endIndex);
            
            const promises = batch.map(courseData => {
                return $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'iipm_bulk_import_courses',
                        course_name: courseData.course_name,
                        course_code: courseData.course_code,
                        course_category: courseData.course_category,
                        course_provider: courseData.course_provider,
                        course_duration: courseData.course_duration,
                        nonce: iipm_ajax.nonce
                    }
                });
            });
            
            Promise.allSettled(promises).then(results => {
                results.forEach((result, index) => {
                    if (result.status === 'fulfilled' && result.value.success) {
                        imported++;
                    } else {
                        const courseData = batch[index];
                        const error = result.status === 'rejected' ? 
                            result.reason.responseText : 
                            result.value.data;
                        errors.push(`${courseData.course_name}: ${error}`);
                    }
                });
                
                // Update progress
                const progress = Math.round((endIndex / total) * 100);
                $('#progress-fill').css('width', progress + '%');
                $('#progress-text').text(progress + '% Complete');
                
                if (endIndex < total) {
                    // Continue with next batch
                    setTimeout(() => importBatch(endIndex), 500);
                } else {
                    // Import complete
                    showImportResults(imported, errors);
                }
            });
        }
        
        importBatch(0);
    }
    
    function showImportResults(imported, errors) {
        $('#import-progress').hide();
        $('#import-results').show();
        
        let resultsHTML = `<div class="success">Successfully imported ${imported} courses.</div>`;
        
        if (errors.length > 0) {
            resultsHTML += `<div class="error">Failed to import ${errors.length} courses:</div>`;
            resultsHTML += '<ul>';
            errors.forEach(error => {
                resultsHTML += `<li class="error">${error}</li>`;
            });
            resultsHTML += '</ul>';
        }
        
        $('#results-content').html(resultsHTML);
        
        // Refresh course list
        loadCourses();
    }
    
    function resetFileSelection() {
        selectedFile = null;
        csvData = [];
        $('#csv-file-input').val('');
        $('#file-preview').hide();
        $('#file-upload-area').show();
        $('#start-import').prop('disabled', true);
    }
    
    function resetBulkImportModal() {
        resetFileSelection();
        $('#import-progress').hide();
        $('#import-results').hide();
        $('#progress-fill').css('width', '0%');
        $('#progress-text').text('0% Complete');
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

});
</script>

</div> <!-- End course-management-page -->

<?php get_footer(); ?>