<?php
/**
 * Template Name: CPD Courses
 * 
 * All CPD Courses page with filtering and search
 */

// Redirect if not logged in
if (!is_user_logged_in()) {
    wp_redirect(home_url('/login/'));
    exit;
}

// Include the CPD courses API
require_once get_template_directory() . '/includes/cpd-courses-api.php';

// Check logging period status from URL parameter
$logging_available = isset($_GET['logging_available']) ? (int)$_GET['logging_available'] : 1;
$is_logging_period_active = ($logging_available === 1);

get_header(); 
?>

<div class="cpd-courses-page main-container">
    <!-- Header -->
    <div class="container" style="position: relative; z-index: 2;">
        <div class="page-header" style="text-align: center; margin-bottom: 40px;">
            <div>
                <h1 style="color: white; font-size: 2.5rem; margin-bottom: 10px;">All CPD Courses</h1>
                <p style="color: rgba(255,255,255,0.9); font-size: 1.1rem;">
                    Select the course in this library to train CPD.
                </p>
            </div>
        </div>

        <div>
            <?php if (!$is_logging_period_active): ?>
            <!-- Logging Period Closed Banner -->
            <div class="logging-period-banner">
                <div class="banner-content">
                    <div class="banner-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="banner-text">
                        <h3>Logging Period Closed</h3>
                        <p>Course logging is currently disabled. You cannot add new courses to your learning path at this time.</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="cpd-courses-layout">
                <!-- Left Sidebar -->
                <div class="cpd-sidebar">
                    <!-- Filters Section -->
                    <div class="filters-widget">
                        <h3>Filter Courses</h3>
                        
                        <div class="filter-group">
                            <label for="title-search">Title</label>
                            <div class="search-input">
                                <input type="text" id="title-search" placeholder="Search course title">
                                <span class="search-icon"><i class="fas fa-search"></i></span>
                            </div>
                        </div>

                        <div class="filter-group">
                            <label for="lia-code-search">LIA Code</label>
                            <div class="search-input">
                                <input type="text" id="lia-code-search" placeholder="Search LIA code">
                                <span class="search-icon"><i class="fas fa-search"></i></span>
                            </div>
                        </div>

                        <div class="filter-group">
                            <label>Date Range</label>
                            <div class="date-range">
                                <input type="date" id="date-from" placeholder="From">
                                <span class="date-separator">to</span>
                                <input type="date" id="date-to" placeholder="To">
                            </div>
                        </div>

                        <div class="filter-group">
                            <label>Category</label>
                            <div class="category-filters" id="category-filters">
                                <!-- Categories will be loaded via jQuery -->
                            </div>
                        </div>

                        <div class="filter-group">
                            <label for="provider-select">Provider</label>
                            <select id="provider-select" class="provider-select">
                                <option value="">All Providers</option>
                                <!-- Providers will be loaded via jQuery -->
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label class="custom-checkbox-label">
                                <input type="checkbox" id="my-courses-filter" name="my-courses" style="margin-right: 10px;" value="1">
                                <span class="label-text" style="position: relative; top: -2px;">Courses added by me</span>
                            </label>
                        </div>

                        <button class="clear-filters-btn" id="clear-filters">Clear all filters</button>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="cpd-main-content">
                    <!-- Loading Indicator -->
                    <div class="loading-overlay" id="loading-overlay" style="display: none;">
                        <div class="loading-spinner">
                            <div class="spinner"></div>
                            <p>Loading courses...</p>
                        </div>
                    </div>

                    <div class="courses-grid" id="courses-grid">
                        <!-- Courses will be loaded here via jQuery -->
                        <div class="loading-message">
                            <div class="loading-spinner">
                                <div class="spinner"></div>
                                <p>Loading courses...</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div class="pagination" id="pagination" style="display: none;">
                        <button class="pagination-btn prev" id="prev-btn" disabled>‹ Previous</button>
                        
                        <div class="pagination-numbers" id="pagination-numbers">
                            <span class="pagination-number active">1</span>
                        </div>

                        <button class="pagination-btn next" id="next-btn">Next ›</button>
                    </div>
                    
                    <!-- Pagination Info -->
                    <div class="pagination-info" id="pagination-info">
                        Showing 0-0 of 0 courses
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
    .cpd-courses-page {
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

    .cpd-courses-layout {
        display: grid;
        grid-template-columns: 300px 1fr;
        gap: 30px;
        align-items: start;
    }

    /* Notifications */
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        border-radius: 8px;
        padding: 16px 20px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        border-left: 4px solid #3b82f6;
        transform: translateX(400px);
        transition: transform 0.3s ease;
        z-index: 1000;
        max-width: 400px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .notification.show {
        transform: translateX(0);
    }

    .notification-success {
        border-left-color: #10b981;
    }

    .notification-error {
        border-left-color: #ef4444;
    }

    .notification-info {
        border-left-color: #3b82f6;
    }

    .notification-message {
        color: #1f2937;
        font-weight: 500;
        flex: 1;
    }

    .notification-close {
        background: none;
        border: none;
        color: #6b7280;
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

    .notification-close:hover {
        background: #f3f4f6;
        color: #374151;
    }

    .cpd-sidebar {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .filters-widget {
        background: white;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .filters-widget h3 {
        margin: 0 0 20px 0;
        font-size: 1.1rem;
        font-weight: 600;
        color: #1f2937;
    }

    .filter-group {
        margin-bottom: 20px;
    }

    .filter-group label {
        display: block;
        margin-bottom: 8px;
        font-size: 14px;
        font-weight: 500;
        color: #374151;
    }

    .search-input {
        position: relative;
    }

    .search-input input {
        width: 100%;
        padding: 10px 35px 10px 12px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 14px;
    }

    .search-icon {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #9ca3af;
    }

    .date-range {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .date-range input {
        padding: 10px 12px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 14px;
    }

    .date-separator {
        text-align: center;
        font-size: 12px;
        color: #6b7280;
    }

    .category-filters,
    .provider-filters {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .custom-checkbox-label {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 14px;
        color: #374151;
        cursor: pointer;
        padding: 8px 0;
    }

    .custom-checkbox-label input[type="checkbox"] {
        margin: 0;
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: #ff6b35;
    }

    .clear-filters-btn {
        width: 100%;
        padding: 10px;
        background: #f3f4f6;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 14px;
        color: #374151;
        cursor: pointer;
        transition: background 0.2s;
    }

    .clear-filters-btn:hover {
        background: #e5e7eb;
    }

    .provider-select {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 14px;
        background: white;
        cursor: pointer;
        transition: border-color 0.2s;
    }

    .provider-select:focus {
        outline: none;
        border-color: #8b5a96;
        box-shadow: 0 0 0 3px rgba(139, 90, 150, 0.1);
    }

    .provider-select:hover {
        border-color: #9ca3af;
    }
    
    /* Checkbox Styles */
    .checkbox-label {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        font-weight: 500;
        color: #374151;
    }
    
    .form-checkbox {
        width: 18px;
        height: 18px;
        accent-color: #8b5a96;
        cursor: pointer;
    }
    
    .checkmark {
        font-size: 14px;
    }

    .courses-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .course-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .course-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
    }

    .course-card.course-completed {
        opacity: 0.7;
        border: 2px solid #10b981;
    }

    .course-card.course-completed:hover {
        transform: none;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .course-card.course-started {
        border: 2px solid #f59e0b;
        background: linear-gradient(135deg, #fef3c7 0%, #ffffff 100%);
    }

    .course-card.course-started:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(245, 158, 11, 0.2);
    }

    .course-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 16px;
    }

    .course-badges {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .category-badge {
        background: #ff6b35;
        color: white;
        padding: 4px 12px;
        border-radius: 16px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .completed-badge {
        background: #10b981;
        color: white;
        padding: 4px 12px;
        border-radius: 16px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .started-badge {
        background: #f59e0b;
        color: white;
        padding: 4px 12px;
        border-radius: 16px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .favorite-btn,
    .add-course-btn {
        background: none;
        border: none;
        cursor: pointer;
        padding: 4px;
        border-radius: 4px;
        transition: background 0.2s;
    }

    .favorite-btn:hover,
    .add-course-btn:hover {
        background: #f3f4f6;
    }

    .add-course-btn.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        background: #f3f4f6;
    }

    .add-course-btn.disabled:hover {
        background: #f3f4f6;
    }
    
    /* Logging Period Banner */
    .logging-period-banner {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        border: 1px solid #f59e0b;
        border-radius: 12px;
        margin-bottom: 24px;
        padding: 20px;
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.15);
    }
    
    .banner-content {
        display: flex;
        align-items: center;
        gap: 16px;
    }
    
    .banner-icon {
        font-size: 24px;
        color: #d97706;
        flex-shrink: 0;
    }
    
    .banner-text h3 {
        margin: 0 0 4px 0;
        color: #92400e;
        font-size: 18px;
        font-weight: 600;
    }
    
    .banner-text p {
        margin: 0;
        color: #92400e;
        font-size: 14px;
        line-height: 1.4;
    }

    .course-title {
        margin: 0 0 8px 0;
        font-size: 16px;
        font-weight: 600;
        color: #1f2937;
        line-height: 1.4;
    }

    .course-provider {
        margin: 0 0 16px 0;
        font-size: 14px;
        color: #6b7280;
    }

    .course-meta {
        display: flex;
        flex-direction: column;
        gap: 4px;
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

    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 20px;
        padding: 30px 0 20px 0;
    }

    .pagination-numbers {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .pagination-btn {
        background: white;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        padding: 10px 16px;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        color: #374151;
        font-weight: 500;
        font-size: 14px;
        outline: none;
    }

    .pagination-btn:hover:not(:disabled) {
        background: #f9fafb;
        border-color: #8b5a96;
        color: #8b5a96;
    }

    .pagination-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        color: #9ca3af;
        background: #f9fafb;
    }

    .pagination-number {
        background: white;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        padding: 8px 12px;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        color: #374151;
        font-weight: 500;
        font-size: 14px;
        min-width: 40px;
        text-align: center;
        outline: none;
    }

    .pagination-number:hover:not(.active) {
        background: #f9fafb;
        border-color: #8b5a96;
        color: #8b5a96;
    }

    .pagination-number.active {
        background: #8b5a96;
        border-color: #8b5a96;
        color: white;
    }

    .pagination-info {
        font-size: 14px;
        color: #6b7280;
        text-align: center;
        padding: 10px 0;
        border-top: 1px solid #e5e7eb;
        background-color: white;
        border-radius: 12px;
        margin-top: 10px;
    }

    .loading-overlay {
        background: rgba(248, 250, 252, 0.9);
        border-radius: 12px;
        padding: 60px 20px;
        text-align: center;
        margin-bottom: 30px;
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

    .no-courses-message {
        background: white;
        border-radius: 12px;
        padding: 40px;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .no-courses-message h3 {
        margin: 0 0 16px 0;
        color: #1f2937;
    }

    .no-courses-message p {
        color: #6b7280;
        margin: 0;
    }

    .pagination-ellipsis {
        padding: 8px 12px;
        color: #6b7280;
        font-size: 14px;
        user-select: none;
    }

    @media (max-width: 768px) {
        .cpd-courses-layout {
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        .courses-grid {
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
    
    // Global variables for pagination
    let currentPage = 1;
    let totalPages = 1;
    let totalCourses = 0;
    let coursesPerPage = 12;
    
    // Global variable to store all courses in fullcpd_confirmations table (both completed and started)
    let coursesInLearningPath = [];
    
    document.addEventListener('DOMContentLoaded', function() {
        // Get DOM elements
        const titleSearch = document.getElementById('title-search');
        const liaCodeSearch = document.getElementById('lia-code-search');
        const dateFrom = document.getElementById('date-from');
        const dateTo = document.getElementById('date-to');
        const categoryFilters = document.getElementById('category-filters');
        const providerSelect = document.getElementById('provider-select');
        const myCoursesFilter = document.getElementById('my-courses-filter');
        const clearFiltersBtn = document.getElementById('clear-filters');
        const coursesGrid = document.getElementById('courses-grid');
        const pagination = document.getElementById('pagination');
        const paginationNumbers = document.getElementById('pagination-numbers');
        const prevBtn = document.getElementById('prev-btn');
        const nextBtn = document.getElementById('next-btn');
        const paginationInfo = document.getElementById('pagination-info');
        
        // Initialize the page
        initializePage();
        
        /**
         * Initialize the page
         */
        function initializePage() {
            // Load initial data
            loadCategories();
            loadProviders();
            loadCoursesInLearningPath(); // Load all courses in learning path
            loadCourses();
            
            // Set up event listeners
            setupEventListeners();
        }
        
        /**
         * Set up event listeners
         */
        function setupEventListeners() {
            // Search inputs
            if (titleSearch) titleSearch.addEventListener('input', debounce(filterCourses, 300));
            if (liaCodeSearch) liaCodeSearch.addEventListener('input', debounce(filterCourses, 300));
            if (dateFrom) dateFrom.addEventListener('change', filterCourses);
            if (dateTo) dateTo.addEventListener('change', filterCourses);
            if (providerSelect) providerSelect.addEventListener('change', filterCourses);
            if (myCoursesFilter) myCoursesFilter.addEventListener('change', filterCourses);
            
            // Clear filters
            if (clearFiltersBtn) clearFiltersBtn.addEventListener('click', clearFilters);
            
            // Pagination
            if (prevBtn) prevBtn.addEventListener('click', () => loadPage(currentPage - 1));
            if (nextBtn) nextBtn.addEventListener('click', () => loadPage(currentPage + 1));
        }
        
        /**
         * Load courses from API
         */
        function loadCourses(filters = {}) {
            // Show loading
            coursesGrid.innerHTML = `
                <div class="loading-message">
                    <div class="loading-spinner">
                        <div class="spinner"></div>
                        <p>Loading courses...</p>
                    </div>
                </div>
            `;
            
            // Create FormData
            const formData = new FormData();
            formData.append('action', 'iipm_get_courses');
            
            // Add filters
            if (filters.title_search) formData.append('title_search', filters.title_search);
            if (filters.lia_code_search) formData.append('lia_code_search', filters.lia_code_search);
            if (filters.date_from) formData.append('date_from', filters.date_from);
            if (filters.date_to) formData.append('date_to', filters.date_to);
            if (filters.categories) formData.append('categories', filters.categories);
            if (filters.providers) formData.append('providers', filters.providers);
            if (filters.my_courses) formData.append('my_courses', filters.my_courses);
            if (filters.page) formData.append('page', filters.page);
            if (filters.per_page) formData.append('per_page', filters.per_page);
            
            // Make AJAX call
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        displayCourses(response.data.courses);
                        updatePagination(response.data.total, response.data.total_pages);
                    } else {
                        coursesGrid.innerHTML = '<div class="error-message">Error loading courses: ' + (response.data || 'Unknown error') + '</div>';
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading courses:', error);
                    coursesGrid.innerHTML = '<div class="error-message">Error loading courses. Please try again.</div>';
                }
            });
        }
        
        /**
         * Display courses in the grid
         */
        function displayCourses(courses) {
            if (!courses || courses.length === 0) {
                coursesGrid.innerHTML = '<div class="no-courses-message"><h3>No Courses Found</h3><p>No courses match your current filters.</p></div>';
                return;
            }
            
            let html = '';
            courses.forEach(course => {
                // Format date
                let courseDate = new Date();
                if (course.course_date) {
                    try {
                        courseDate = new Date(course.course_date);
                    } catch (e) {
                        courseDate = new Date();
                    }
                }
                
                // Check if course is in learning path (either completed or started)
                const isInLearningPath = isCourseInLearningPath(course);
                const isCompleted = isCourseCompleted(course);
                const isLoggingPeriodActive = <?php echo $is_logging_period_active ? 'true' : 'false'; ?>;
                
                // Determine button state based on logging period and course status
                let addButtonClass, addButtonTitle, addButtonIcon, isDisabled;
                
                if (!isLoggingPeriodActive) {
                    addButtonClass = 'add-course-btn disabled';
                    addButtonTitle = 'Logging period closed';
                    addButtonIcon = '<i class="fas fa-lock"></i>';
                    isDisabled = true;
                } else if (isInLearningPath) {
                    addButtonClass = 'add-course-btn disabled';
                    addButtonTitle = isCompleted ? 'Course completed' : 'Course in progress';
                    addButtonIcon = isCompleted ? '<i class="fas fa-check"></i>' : '<i class="fas fa-clock"></i>';
                    isDisabled = true;
                } else {
                    addButtonClass = 'add-course-btn';
                    addButtonTitle = 'Add to learning path';
                    addButtonIcon = '<i class="fas fa-plus"></i>';
                    isDisabled = false;
                }
                
                html += `
                    <div class="course-card ${isCompleted ? 'course-completed' : ''} ${isInLearningPath && !isCompleted ? 'course-started' : ''}">
                        <div class="course-header">
                            <div class="course-badges">
                                <span class="category-badge">${course.course_category || 'N/A'}</span>
                                ${isCompleted ? '<span class="completed-badge">Completed</span>' : ''}
                                ${isInLearningPath && !isCompleted ? '<span class="started-badge">In Progress</span>' : ''}
                            </div>
                            <button class="${addButtonClass}" title="${addButtonTitle}" ${isDisabled ? 'disabled' : ''} data-course-id="${course.id}">
                                <span class="plus-icon">${addButtonIcon}</span>
                            </button>
                        </div>

                        <div class="course-content">
                            <h4 class="course-title">${course.course_name || 'Untitled Course'}</h4>
                            <p class="course-provider">Provided by ${course.crs_provider || 'Unknown Provider'}</p>
                            
                            <div class="course-meta">
                                <div class="meta-item">
                                    <span class="meta-label">LIA Code:</span>
                                    <span class="meta-value">${course.LIA_Code || 'N/A'}</span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Date:</span>
                                    <span class="meta-value">${courseDate.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Duration:</span>
                                    <span class="meta-value">${course.course_cpd_mins || 0} minutes</span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Entered By:</span>
                                    <span class="meta-value">${course.course_enteredBy || 'N/A'}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            coursesGrid.innerHTML = html;
            
            // Add click event listeners to all add course buttons (only non-disabled ones)
            const addCourseBtns = document.querySelectorAll('.add-course-btn:not(.disabled)');
            addCourseBtns.forEach((btn) => {
                btn.addEventListener('click', function() {
                    // Get course ID from the button's data attribute
                    const courseId = this.getAttribute('data-course-id');
                    console.log('Clicked course ID:', courseId);
                    
                    // Find the course by ID
                    const course = courses.find(c => c.id == courseId);
                    if (!course) {
                        console.error('Course not found for ID:', courseId);
                        return;
                    }
                    
                    console.log('Found course:', course);
                    
                    // Double-check logging period before allowing course addition
                    const isLoggingPeriodActive = <?php echo $is_logging_period_active ? 'true' : 'false'; ?>;
                    if (!isLoggingPeriodActive) {
                        showNotification('Cannot add courses during logging period closure', 'error');
                        return;
                    }
                    addCourseToLearningPath(course);
                });
            });
        }
        
        /**
         * Load categories from API
         */
        function loadCategories() {
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'iipm_get_course_categories'
                },
                success: function(response) {
                    if (response.success && response.data.categories) {
                        updateCategoryFilters(response.data.categories);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading categories:', error);
                }
            });
        }
        
        /**
         * Add course to learning path (add to fullcpd_confirmations table)
         */
        function addCourseToLearningPath(course) {
            console.log('Adding course to learning path:', course);
            
            // Check if course is already in learning path (either completed or started)
            if (isCourseInLearningPath(course)) {
                showNotification('This course is already in your learning path!', 'error');
                return;
            }
            
            // Show loading state on the button
            const addBtn = event.target.closest('.add-course-btn');
            if (addBtn) {
                const originalContent = addBtn.innerHTML;
                addBtn.innerHTML = '<span class="loading-icon"><i class="fas fa-spinner fa-spin"></i></span>';
                addBtn.disabled = true;
                
                // Revert after 2 seconds if there's an error
                setTimeout(() => {
                    if (addBtn.disabled) {
                        addBtn.innerHTML = originalContent;
                        addBtn.disabled = false;
                    }
                }, 2000);
            }
            
            const formData = new FormData();
            formData.append('action', 'iipm_add_cpd_confirmation');
            formData.append('course_id', course.id);
            formData.append('course_name', course.course_name);
            formData.append('course_category', course.course_category);
            formData.append('course_cpd_mins', course.course_cpd_mins);
            formData.append('crs_provider', course.crs_provider);
            
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        // Show success state
                        if (addBtn) {
                            addBtn.innerHTML = '<span class="success-icon"><i class="fas fa-check"></i></span>';
                            addBtn.style.background = '#10b981';
                            addBtn.style.color = 'white';
                            
                            // Revert after 3 seconds
                            setTimeout(() => {
                                addBtn.innerHTML = originalContent;
                                addBtn.style.background = '';
                                addBtn.style.color = '';
                                addBtn.disabled = false;
                            }, 3000);
                        }
                        
                        // Show success message
                        showNotification('Course added to your learning path!', 'success');
                        
                        // Refresh the learning path courses list and reload courses to update the UI
                        loadCoursesInLearningPath();
                        setTimeout(() => {
                            loadCourses(); // Reload courses to update the UI with new status
                        }, 500);
                        
                        // Optionally redirect back to member portal
                        setTimeout(() => {
                            if (confirm('Course added successfully! Would you like to return to your Member Portal?')) {
                                window.location.href = '<?php echo home_url('/member-portal/'); ?>';
                            }
                        }, 1000);
                        
                    } else {
                        // Show error state
                        if (addBtn) {
                            addBtn.innerHTML = '<span class="error-icon"><i class="fas fa-times"></i></span>';
                            addBtn.style.background = '#ef4444';
                            addBtn.style.color = 'white';
                            
                            // Revert after 3 seconds
                            setTimeout(() => {
                                addBtn.innerHTML = originalContent;
                                addBtn.style.background = '';
                                addBtn.style.color = '';
                                addBtn.disabled = false;
                            }, 3000);
                        }
                        
                        showNotification('Error adding course: ' + (response.data || 'Unknown error'), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error adding course:', error);
                    
                    // Show error state
                    if (addBtn) {
                        addBtn.innerHTML = '<span class="error-icon">❌</span>';
                        addBtn.style.background = '#ef4444';
                        addBtn.style.color = 'white';
                        
                        // Revert after 3 seconds
                        setTimeout(() => {
                            addBtn.innerHTML = originalContent;
                            addBtn.style.background = '';
                            addBtn.style.color = '';
                            addBtn.disabled = false;
                        }, 3000);
                    }
                    
                    showNotification('Error adding course. Please try again.', 'error');
                }
            });
        }
        
        /**
         * Show notification message
         */
        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <span class="notification-message">${message}</span>
                <button class="notification-close">×</button>
            `;
            
            // Add to page
            document.body.appendChild(notification);
            
            // Show notification
            setTimeout(() => notification.classList.add('show'), 100);
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 5000);
            
            // Close button functionality
            const closeBtn = notification.querySelector('.notification-close');
            closeBtn.addEventListener('click', () => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            });
        }
        
        /**
         * Load all courses in learning path (both completed and started)
         */
        function loadCoursesInLearningPath() {
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'iipm_get_courses_in_learning_path'
                },
                success: function(response) {
                    if (response.success && response.data.courses) {
                        coursesInLearningPath = response.data.courses;
                        console.log('Loaded courses in learning path:', coursesInLearningPath);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading courses in learning path:', error);
                }
            });
        }
        
        /**
         * Check if a course is in learning path (either completed or started)
         */
        function isCourseInLearningPath(course) {
            return coursesInLearningPath.some(learningPathCourse => {
                // Check by course ID if available, otherwise by course name and provider
                if (learningPathCourse.course_id && course.id) {
                    return learningPathCourse.course_id == course.id;
                }
                return learningPathCourse.course_name === course.course_name && 
                       learningPathCourse.crs_provider === course.crs_provider;
            });
        }
        
        /**
         * Check if a course is completed (has dateOfReturn)
         */
        function isCourseCompleted(course) {
            const learningPathCourse = coursesInLearningPath.find(lpCourse => {
                if (lpCourse.course_id && course.id) {
                    return lpCourse.course_id == course.id;
                }
                return lpCourse.course_name === course.course_name && 
                       lpCourse.crs_provider === course.crs_provider;
            });
            return learningPathCourse && learningPathCourse.date_of_return;
        }
        
        /**
         * Load providers from API
         */
        function loadProviders() {
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'iipm_get_course_providers'
                },
                success: function(response) {
                    if (response.success && response.data.providers) {
                        updateProviderFilters(response.data.providers);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading providers:', error);
                }
            });
        }
        
        /**
         * Update category filter checkboxes
         */
        function updateCategoryFilters(categories) {
            if (!categoryFilters) return;
            
            let html = '';
            console.log(categories);
            categories.forEach(category => {
                html += `
                    <label class="custom-checkbox-label">
                        <input type="checkbox" name="category" style="margin-right: 10px;" value="${category.name}" checked>
                        <span class="label-text" style="position: relative; top: -2px;">${category.name}</span>
                    </label>
                `;
            });
            categoryFilters.innerHTML = html;
            
            // Re-attach event listeners
            const newCategoryCheckboxes = categoryFilters.querySelectorAll('input[name="category"]');
            newCategoryCheckboxes.forEach(cb => {
                cb.addEventListener('change', filterCourses);
            });
        }
        
        /**
         * Update provider filter select box
         */
        function updateProviderFilters(providers) {
            if (!providerSelect) return;
            
            // Clear existing options except the first one
            providerSelect.innerHTML = '<option value="">All Providers</option>';
            
            // Add provider options
            providers.forEach(provider => {
                const option = document.createElement('option');
                option.value = provider;
                option.textContent = provider;
                providerSelect.appendChild(option);
            });
        }
        
        /**
         * Filter courses based on current filters
         */
        function filterCourses() {
            // Reset to page 1 when filtering
            currentPage = 1;
            
            const filters = {
                title_search: titleSearch ? titleSearch.value : '',
                lia_code_search: liaCodeSearch ? liaCodeSearch.value : '',
                date_from: dateFrom ? dateFrom.value : '',
                date_to: dateTo ? dateTo.value : '',
                categories: Array.from(categoryFilters.querySelectorAll('input[name="category"]:checked')).map(cb => cb.value),
                providers: providerSelect ? providerSelect.value : '',
                my_courses: myCoursesFilter ? myCoursesFilter.checked : false,
                page: 1,
                per_page: coursesPerPage
            };
            
            loadCourses(filters);
        }
        
        /**
         * Clear all filters
         */
        function clearFilters() {
            if (titleSearch) titleSearch.value = '';
            if (liaCodeSearch) liaCodeSearch.value = '';
            if (dateFrom) dateFrom.value = '';
            if (dateTo) dateTo.value = '';
            if (providerSelect) providerSelect.value = '';
            if (myCoursesFilter) myCoursesFilter.checked = false;
            
            const categoryCheckboxes = categoryFilters.querySelectorAll('input[name="category"]');
            categoryCheckboxes.forEach(cb => cb.checked = true);
            
            // Reset to page 1 and reload
            currentPage = 1;
            loadCourses();
        }
        
        /**
         * Update pagination
         */
        function updatePagination(total_p, totalPages_p) {
            // Update global variables
            totalCourses = total_p;
            totalPages = totalPages_p;
            
            // Update pagination info
            if (paginationInfo) {
                const startItem = 1;
                const endItem = Math.min(total_p, coursesPerPage);
                paginationInfo.innerHTML = `Showing ${startItem}-${endItem} of ${total_p} courses`;
            }
            
            // Update pagination buttons
            if (pagination) {
                if (totalPages_p > 1) {
                    pagination.style.display = 'flex';
                    updatePaginationNumbers();
                    updatePaginationButtons();
                } else {
                    pagination.style.display = 'none';
                }
            }
        }
        
        /**
         * Update pagination numbers
         */
        function updatePaginationNumbers() {
            if (!paginationNumbers) return;
            
            let html = '';
            const maxVisiblePages = 7; // Show max 7 page numbers

            console.log(totalPages);
            console.log(maxVisiblePages);
            console.log(currentPage);
            
            if (totalPages <= maxVisiblePages) {
                // If total pages is small, show all pages
                for (let i = 1; i <= totalPages; i++) {
                    if (i === currentPage) {
                        html += `<span class="pagination-number active">${i}</span>`;
                    } else {
                        html += `<button class="pagination-number" data-page="${i}">${i}</button>`;
                    }
                }
            } else {
                // If total pages is large, show smart pagination
                if (currentPage <= 4) {
                    // Show first 5 pages + ellipsis + last page
                    for (let i = 1; i <= 5; i++) {
                        if (i === currentPage) {
                            html += `<span class="pagination-number active">${i}</span>`;
                        } else {
                            html += `<button class="pagination-number" data-page="${i}">${i}</button>`;
                        }
                    }
                    html += `<span class="pagination-ellipsis">...</span>`;
                    html += `<button class="pagination-number" data-page="${totalPages}">${totalPages}</button>`;
                } else if (currentPage >= totalPages - 3) {
                    // Show first page + ellipsis + last 5 pages
                    html += `<button class="pagination-number" data-page="1">1</button>`;
                    html += `<span class="pagination-ellipsis">...</span>`;
                    for (let i = totalPages - 4; i <= totalPages; i++) {
                        if (i === currentPage) {
                            html += `<span class="pagination-number active">${i}</span>`;
                        } else {
                            html += `<button class="pagination-number" data-page="${i}">${i}</button>`;
                        }
                    }
                } else {
                    // Show first page + ellipsis + current page and neighbors + ellipsis + last page
                    html += `<button class="pagination-number" data-page="1">1</button>`;
                    html += `<span class="pagination-ellipsis">...</span>`;
                    for (let i = currentPage - 1; i <= currentPage + 1; i++) {
                        if (i === currentPage) {
                            html += `<span class="pagination-number active">${i}</span>`;
                        } else {
                            html += `<button class="pagination-number" data-page="${i}">${i}</button>`;
                        }
                    }
                    html += `<span class="pagination-ellipsis">...</span>`;
                    html += `<button class="pagination-number" data-page="${totalPages}">${totalPages}</button>`;
                }
            }
            
            paginationNumbers.innerHTML = html;
            
            // Add click handlers for page numbers
            const pageButtons = paginationNumbers.querySelectorAll('.pagination-number[data-page]');
            pageButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    const page = parseInt(this.getAttribute('data-page'));
                    loadPage(page);
                });
            });
        }
        
        /**
         * Update pagination buttons
         */
        function updatePaginationButtons() {
            if (prevBtn) {
                if (currentPage > 1) {
                    prevBtn.disabled = false;
                } else {
                    prevBtn.disabled = true;
                }
            }
            
            if (nextBtn) {
                if (currentPage < totalPages) {
                    nextBtn.disabled = false;
                } else {
                    nextBtn.disabled = true;
                }
            }
        }
        
        /**
         * Load specific page
         */
        function loadPage(page) {
            if (page < 1 || page > totalPages) return;
            
            currentPage = page;
            
            const filters = {
                title_search: titleSearch ? titleSearch.value : '',
                lia_code_search: liaCodeSearch ? liaCodeSearch.value : '',
                date_from: dateFrom ? dateFrom.value : '',
                date_to: dateTo ? dateTo.value : '',
                categories: Array.from(categoryFilters.querySelectorAll('input[name="category"]:checked')).map(cb => cb.value),
                providers: providerSelect ? providerSelect.value : '',
                my_courses: myCoursesFilter ? myCoursesFilter.checked : false,
                page: page,
                per_page: coursesPerPage
            };
            
            loadCourses(filters);
            updatePaginationUI();
        }
        
        /**
         * Update pagination UI
         */
        function updatePaginationUI() {
            updatePaginationNumbers();
            updatePaginationButtons();
            
            if (paginationInfo) {
                const startItem = (currentPage - 1) * coursesPerPage + 1;
                const endItem = Math.min(currentPage * coursesPerPage, totalCourses);
                paginationInfo.innerHTML = `Showing ${startItem}-${endItem} of ${totalCourses} courses`;
            }
        }
        
        /**
         * Debounce function for search inputs
         */
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    });
</script>

<?php get_footer(); ?>
