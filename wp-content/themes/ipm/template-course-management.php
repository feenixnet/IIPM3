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

get_header();
?>

<div class="course-management-page">
    <!-- Header -->
    <div class="course-header">
        <div class="container">
            <div class="header-content">
                <h1>Course Management</h1>
            </div>
        </div>
    </div>

    <div class="container" style="margin-bottom: 40px;">
    <!-- Course Management Actions -->
    <div class="course-management-header">
        <div class="header-left">
            <h2>Course Library</h2>
            <p>Manage your CPD course database</p>
        </div>
        <div class="header-right">
            <button class="btn btn-primary" id="add-course-btn">
                <span class="btn-icon"><i class="fas fa-plus"></i></span>
                Add New Course
            </button>
        </div>
    </div>

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

<style>
/* Course Management Page */
.course-management-page {
    min-height: 100vh;
    background: #f8fafc;
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
    // var iipm_ajax = {
    //     nonce: '<?php echo wp_create_nonce('iipm_portal_nonce'); ?>'
    // };

    let courses = [];
    let categories = [];
    let providers = [];
    let currentCourseId = null;
    let currentPage = 1;
    let isLoading = false;
    let hasMoreCourses = true;
    let coursesPerPage = 10;
    
    // Initialize page
    initializePage();
    
    function initializePage() {
        loadCategories();
        loadProviders();
        loadCourses();
        setupEventListeners();
        setupInfiniteScroll();
    }
    
    function setupEventListeners() {
        // Add course button
        $('#add-course-btn').on('click', function() {
            console.log('Add course button clicked');
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
            filterCourses();
        });
        
        // Search button
        $('#search-button').on('click', function() {
            filterCourses();
        });
        
        // Search on Enter key
        $('#search-courses').on('keypress', function(e) {
            if (e.which === 13) { // Enter key
                filterCourses();
            }
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
            }
        });
        
        // Close modal on escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                closeCourseModal();
                closeDeleteModal();
            }
        });
    }
    
    function setupInfiniteScroll() {
        $(window).on('scroll', function() {
            if (isLoading || !hasMoreCourses) return;
            
            const scrollTop = $(window).scrollTop();
            const windowHeight = $(window).height();
            const documentHeight = $(document).height();
            
            // Load more when user is 200px from bottom
            if (scrollTop + windowHeight >= documentHeight - 200) {
                loadCourses(false);
            }
        });
    }
    
    function showLoadMoreIndicator() {
        if ($('#load-more-indicator').length === 0) {
            $('#course-list').append(`
                <div id="load-more-indicator" class="load-more-indicator">
                    <div class="loading-spinner"></div>
                    <p>Loading more courses...</p>
                </div>
            `);
        }
    }
    
    function hideLoadMoreIndicator() {
        $('#load-more-indicator').remove();
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
                    updateCategorySelects();
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
                    updateProviderSelects();
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
        const searchTerm = filters ? filters.search : $('#search-courses').val().toLowerCase();
        
        console.log('loadCourses - Filter values:', { 
            categoryFilter, 
            providerFilter, 
            searchTerm,
            filtersPassed: filters,
            reset: reset
        });
        
        const hasActiveFilters = (categoryFilter && categoryFilter !== 'all') || 
                                (providerFilter && providerFilter !== 'all') || 
                                searchTerm;
        
        // Note: We now allow pagination even with filters since backend handles filtering
        
        if (reset) {
            currentPage = 1;
            courses = [];
            hasMoreCourses = true;
            showLoading();
        } else {
            showLoadMoreIndicator();
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
        
        console.log('Sending filter data to backend:', ajaxData);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: ajaxData,
            success: function(response) {
                if (response.success) {
                    const newCourses = response.data.courses || response.data;
                    const pagination = response.data.pagination;
                    
                    if (reset) {
                        courses = newCourses;
                    } else {
                        courses = courses.concat(newCourses);
                    }
                    
                    displayCourses(courses);
                    updateCourseCount();
                    
                    // Check if there are more courses based on pagination
                    if (pagination) {
                        hasMoreCourses = pagination.page < pagination.total_pages;
                        currentPage = pagination.page + 1;
                    } else {
                        // Fallback to old logic
                        hasMoreCourses = newCourses.length === coursesPerPage;
                        currentPage++;
                    }
                    
                    if (!hasMoreCourses) {
                        hideLoadMoreIndicator();
                    }
                } else {
                    showError('Failed to load courses: ' + response.data);
                }
            },
            error: function() {
                showError('Failed to load courses');
            },
            complete: function() {
                isLoading = false;
                if (reset) {
                    hideLoading();
                } else {
                    hideLoadMoreIndicator();
                }
            }
        });
    }
    
    function updateCategorySelects() {
        const categorySelects = ['#category-filter', '#course-category'];
        
        categorySelects.forEach(selector => {
            const $select = $(selector);
            const currentValue = $select.val();
            
            // Clear existing options (except first)
            $select.find('option:not(:first)').remove();
            
            // Add category options
            categories.forEach(category => {
                $select.append(`<option value="${category.id}">${category.name}</option>`);
            });
            
            // Restore selection or set to "all" for filter
            if (currentValue) {
                $select.val(currentValue);
            } else if (selector === '#category-filter') {
                $select.val('all');
            }
        });
    }
    
    function updateProviderSelects() {
        const $select = $('#provider-filter');
        const currentValue = $select.val();
        
        // Clear existing options (except first)
        $select.find('option:not(:first)').remove();
        
        // Add provider options
        providers.forEach(provider => {
            $select.append(`<option value="${provider}">${provider}</option>`);
        });
        
        // Restore selection or set to "all"
        if (currentValue) {
            $select.val(currentValue);
        } else {
            $select.val('all');
        }
    }
    
    function displayCourses(coursesToShow) {
        const currentView = $('.view-btn.active').data('view') || 'card';
        
        if (coursesToShow.length === 0) {
            const emptyStateHtml = `
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="fas fa-book"></i></div>
                    <h3>No courses found</h3>
                    <p>No courses match your current filters.</p>
                </div>
            `;
            $('#course-list').html(emptyStateHtml);
            $('#courses-table-body').html('');
            return;
        }
        
        if (currentView === 'table') {
            displayTableView(coursesToShow);
        } else {
            displayCardView(coursesToShow);
        }
    }
    
    function displayCardView(coursesToShow) {
        const $courseList = $('#course-list');
        
        let html = '';
        coursesToShow.forEach(course => {
            // Use actual database field names
            const categoryName = course.course_category || 'Unknown';
            const status = course.course_status === 'Active' ? 'active' : 'inactive';
            
            html += `
                <div class="course-item" data-course-id="${course.id}">
                    <div class="course-info">
                        <h4 class="course-name">${course.course_name}</h4>
                        <div class="course-details">
                            <span><strong>Code:</strong> ${course.LIA_Code || 'N/A'}</span>
                            <span><strong>Category:</strong> ${categoryName}</span>
                            <span><strong>Provider:</strong> ${course.crs_provider}</span>
                            <span><strong>Duration:</strong> ${course.course_cpd_mins} CPD mins</span>
                        </div>
                        ${course.course_description ? `<p class="course-description">${course.course_description}</p>` : ''}
                    </div>
                    <div class="course-actions">
                        <button class="btn btn-outline edit-course" data-course-id="${course.id}">
                            <span class="btn-icon"><i class="fas fa-edit"></i></span>
                            Edit
                        </button>
                        <button class="btn btn-danger delete-course" data-course-id="${course.id}">
                            <span class="btn-icon"><i class="fas fa-trash-alt"></i></span>
                            Delete
                        </button>
                    </div>
                </div>
            `;
        });
        
        $courseList.html(html);
        setupCourseEventListeners();
    }
    
    function displayTableView(coursesToShow) {
        const $tableBody = $('#courses-table-body');
        
        let html = '';
        coursesToShow.forEach(course => {
            const categoryName = course.course_category || 'Unknown';
            const status = course.course_status === 'Active' ? 'active' : 'inactive';
            
            html += `
                <tr data-course-id="${course.id}">
                    <td>
                        <div class="course-name-cell">
                            <strong>${course.course_name}</strong>
                            ${course.course_description ? `<div class="course-description-small">${course.course_description.substring(0, 100)}${course.course_description.length > 100 ? '...' : ''}</div>` : ''}
                        </div>
                    </td>
                    <td>${course.LIA_Code || 'N/A'}</td>
                    <td>${categoryName}</td>
                    <td>${course.crs_provider}</td>
                    <td>${course.course_cpd_mins} CPD mins</td>
                    <td>
                        <div class="table-actions">
                            <button class="btn btn-outline edit-course" data-course-id="${course.id}">
                                <span class="btn-icon"><i class="fas fa-edit"></i></span>
                                Edit
                            </button>
                            <button class="btn btn-danger delete-course" data-course-id="${course.id}">
                                <span class="btn-icon"><i class="fas fa-trash-alt"></i></span>
                                Delete
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });
        
        $tableBody.html(html);
        setupCourseEventListeners();
    }
    
    function setupCourseEventListeners() {
        // Add event listeners for edit and delete buttons
        $('.edit-course').on('click', function() {
            const courseId = $(this).data('course-id');
            console.log('Edit button clicked for course:', courseId);
            openCourseModal(courseId);
        });
        
        $('.delete-course').on('click', function() {
            const courseId = $(this).data('course-id');
            console.log('Delete button clicked for course:', courseId);
            confirmDeleteCourse(courseId);
        });
    }
    
    function openCourseModal(courseId = null) {
        console.log('Opening course modal, courseId:', courseId);
        currentCourseId = courseId;
        
        if (courseId) {
            $('#modal-title').text('Edit Course');
            loadCourseData(courseId);
        } else {
            $('#modal-title').text('Add New Course');
            $('#course-form')[0].reset();
            $('#course-id').val('');
        }
        
        // Ensure modal is properly shown
        $('#course-modal').addClass('show').show();
        $('body').addClass('modal-open');
        console.log('Modal should be visible now');
    }
    
    function closeCourseModal() {
        $('#course-modal').removeClass('show').hide();
        $('body').removeClass('modal-open');
        currentCourseId = null;
    }
    
    function loadCourseData(courseId) {
        console.log('Loading course data for ID:', courseId);
        const course = courses.find(c => c.id == courseId);
        console.log('Found course:', course);
        
        if (course) {
            // Find category ID by matching category name
            const category = categories.find(cat => cat.name === course.course_category);
            const categoryId = category ? category.id : '';
            console.log('Category ID for form:', categoryId);
            
            $('#course-id').val(course.id);
            $('#course-name').val(course.course_name);
            $('#course-code').val(course.LIA_Code);
            $('#course-category').val(categoryId);
            $('#course-provider').val(course.crs_provider);
            $('#course-duration').val(course.course_cpd_mins);
            
            console.log('Form fields populated');
        } else {
            console.error('Course not found with ID:', courseId);
        }
    }
    
    function saveCourse() {
        console.log('Saving course, currentCourseId:', currentCourseId);
        
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
        
        console.log('Form data:', formData);
        
        // Validate required fields
        if (!formData.course_name || !formData.course_category || !formData.course_provider || !formData.course_cpd_mins) {
            alert('Please fill in all required fields.');
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
                    showSuccess(currentCourseId ? 'Course updated successfully!' : 'Course added successfully!');
                    closeCourseModal();
                    loadCourses();
                } else {
                    showError('Failed to save course: ' + response.data);
                }
            },
            error: function() {
                showError('Failed to save course');
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
                    showSuccess('Course deleted successfully!');
                    closeDeleteModal();
                    loadCourses();
                } else {
                    showError('Failed to delete course: ' + response.data);
                }
            },
            error: function() {
                showError('Failed to delete course');
            },
            complete: function() {
                $deleteBtn.html(originalText);
                $deleteBtn.prop('disabled', false);
            }
        });
    }
    
    function filterCourses() {
        console.log('Filtering courses...');
        
        const categoryFilter = $('#category-filter').val();
        const providerFilter = $('#provider-filter').val();
        const searchTerm = $('#search-courses').val().toLowerCase();
        
        console.log('Current filter values:', { 
            categoryFilter, 
            providerFilter, 
            searchTerm,
            categoryElement: $('#category-filter').val(),
            providerElement: $('#provider-filter').val(),
            searchElement: $('#search-courses').val()
        });
        
        // Always load courses from backend with current filter values
        loadCourses(true, {
            category: categoryFilter,
            provider: providerFilter,
            search: searchTerm
        });
    }
    
    function clearFilters() {
        $('#category-filter').val('all');
        $('#provider-filter').val('all');
        $('#search-courses').val('');
        filterCourses();
    }
    
    function updateCourseCount(count = null) {
        const totalCount = count !== null ? count : courses.length;
        $('#course-count').text(`${totalCount} course${totalCount !== 1 ? 's' : ''} found`);
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
        // Loading state is automatically replaced when courses are displayed
        // This function exists for consistency with the loading pattern
    }
    
    function showSuccess(message) {
        // Create success notification
        const notification = $(`
            <div class="success-notification" style="
                position: fixed;
                top: 20px;
                right: 20px;
                background: #10b981;
                color: white;
                padding: 15px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
                z-index: 1001;
                animation: slideInRight 0.3s ease-out;
            ">
                <span style="margin-right: 10px;">✅</span>
                ${message}
            </div>
        `);
        
        $('body').append(notification);
        
        setTimeout(() => {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }
    
    function showError(message) {
        // Create error notification
        const notification = $(`
            <div class="error-notification" style="
                position: fixed;
                top: 20px;
                right: 20px;
                background: #e53e3e;
                color: white;
                padding: 15px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(229, 62, 62, 0.3);
                z-index: 1001;
                animation: slideInRight 0.3s ease-out;
            ">
                <span style="margin-right: 10px;">❌</span>
                ${message}
            </div>
        `);
        
        $('body').append(notification);
        
        setTimeout(() => {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Add CSS for notification animations
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            @keyframes slideInRight {
                from {
                    opacity: 0;
                    transform: translateX(100%);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }
        `)
        .appendTo('head');
});
</script>

</div> <!-- End course-management-page -->

<?php get_footer(); ?>
