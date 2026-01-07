<?php
/**
 * Template Name: External Courses
 * 
 * User page for adding and managing external courses
 */

// Redirect if not logged in
if (!is_user_logged_in()) {
    wp_redirect(home_url('/login/'));
    exit;
}

// Include the CPD courses API
require_once get_template_directory() . '/includes/cpd-courses-api.php';

get_header(); 
?>

<div class="external-courses-page main-container">
    <div class="container" style="position: relative; z-index: 2;">
        <div class="page-header" style="text-align: center; margin-bottom: 40px;">
            <div>
                <h1 style="color: white; font-size: 2.5rem; margin-bottom: 10px;">External Courses</h1>
                <p style="color: rgba(255,255,255,0.9); font-size: 1.1rem;">
                    Add your own training courses for approval
                </p>
                <?php IIPM_Navigation_Manager::display_breadcrumbs(); ?>
            </div>
        </div>

        <!-- Course Management Actions -->
        <div class="course-management-header">
            <div class="header-left">
                <h2>My External Courses</h2>
                <p>Add and manage your own training courses</p>
            </div>
            <div class="header-right">
                <button class="btn btn-primary" id="add-external-course-btn">
                    <span class="btn-icon"><i class="fas fa-plus"></i></span>
                    Add New Course
                </button>
            </div>
        </div>

        <!-- Course Status Filter -->
        <div class="course-filters">
            <div class="filter-group">
                <select id="status-filter" class="form-control">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="active">Active</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <div class="filter-group">
                <input type="text" id="title-search" class="form-control" placeholder="Search course title...">
            </div>
            <button class="btn btn-outline" id="clear-filters">Clear Filters</button>
        </div>

        <!-- Courses Table -->
        <div class="courses-table-container">
            <div class="table-responsive">
                <table class="courses-table">
                    <thead>
                        <tr>
                            <th>Course Name</th>
                            <th>Course Code</th>
                            <th>Category</th>
                            <th>Provider</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Date Added</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="courses-table-body">
                        <!-- Courses will be loaded here -->
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="pagination-container">
                <div class="pagination-info">
                    <span id="pagination-info">Loading...</span>
                </div>
                <div class="pagination-controls">
                    <button class="btn btn-outline" id="prev-page" disabled>Previous</button>
                    <span id="page-numbers"></span>
                    <button class="btn btn-outline" id="next-page" disabled>Next</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Course Modal -->
<div class="modal-overlay" id="course-modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-title">Add New Course</h3>
            <button class="modal-close" id="modal-close">×</button>
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
                    <input type="number" id="course-duration" name="course_duration" class="form-control" required min="1">
                </div>
                
            </form>
        </div>
        
        <div class="modal-footer">
            <button class="btn btn-outline" id="cancel-btn">Cancel</button>
            <button class="btn btn-primary" id="save-course-btn">
                <span class="btn-icon"><i class="fas fa-save"></i></span>
                Save Course
            </button>
        </div>
    </div>
</div>

<style>
    .external-courses-page {
        min-height: 100vh;
        padding-top: 0;
    }

    .course-management-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        background: white;
        padding: 24px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .header-left h2 {
        margin: 0 0 8px 0;
        color: #1e293b;
        font-size: 24px;
        font-weight: 600;
    }

    .header-left p {
        margin: 0;
        color: #64748b;
        font-size: 14px;
    }

    .course-filters {
        display: flex;
        gap: 20px;
        align-items: end;
        margin-bottom: 30px;
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .filter-group label {
        font-weight: 500;
        color: #374151;
        font-size: 14px;
    }


    .form-control {
        padding: 10px 12px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 14px;
        min-width: 200px;
    }

    .form-control:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .courses-table-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }

    .courses-table {
        width: 100%;
        border-collapse: collapse;
    }

    .courses-table th {
        background: #f8fafc;
        padding: 16px;
        text-align: left;
        font-weight: 600;
        color: #374151;
        border-bottom: 1px solid #e5e7eb;
    }

    .courses-table td {
        padding: 16px;
        border-bottom: 1px solid #f3f4f6;
        color: #4b5563;
    }

    .courses-table tr:hover {
        background: #f9fafb;
    }

    .status-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
        text-transform: uppercase;
    }

    .status-pending {
        background: #fef3c7;
        color: #92400e;
    }

    .status-active {
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

    .btn-sm {
        padding: 6px 12px;
        font-size: 12px;
        border-radius: 6px;
    }

    .pagination-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        background: #f8fafc;
        border-top: 1px solid #e5e7eb;
    }

    .pagination-controls {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .page-number {
        padding: 8px 12px;
        border: 1px solid #d1d5db;
        background: white;
        color: #374151;
        text-decoration: none;
        border-radius: 6px;
        cursor: pointer;
    }

    .page-number.active {
        background: #3b82f6;
        color: white;
        border-color: #3b82f6;
    }

    .page-number:hover:not(.active) {
        background: #f3f4f6;
    }

    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
    }

    .modal-content {
        background: white;
        border-radius: 12px;
        width: 90%;
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 24px;
        border-bottom: 1px solid #e5e7eb;
    }

    .modal-header h3 {
        margin: 0;
        color: #1e293b;
        font-size: 20px;
        font-weight: 600;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 24px;
        color: #6b7280;
        cursor: pointer;
        padding: 0;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
    }

    .modal-close:hover {
        background: #f3f4f6;
    }

    .modal-body {
        padding: 24px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: #374151;
    }

    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        padding: 20px 24px;
        border-top: 1px solid #e5e7eb;
    }

    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        font-weight: 500;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        transition: all 0.2s;
    }

    .btn-primary {
        background: #3b82f6;
        color: white;
    }

    .btn-primary:hover {
        background: #2563eb;
    }

    .btn-outline {
        background: white;
        color: #374151;
        border: 1px solid #d1d5db;
    }

    .btn-outline:hover {
        background: #f9fafb;
    }

    .btn-danger {
        background: #ef4444;
        color: white;
    }

    .btn-danger:hover {
        background: #dc2626;
    }

    .btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentPage = 1;
    let totalPages = 1;
    let currentFilters = {};

    // DOM elements
    const addCourseBtn = document.getElementById('add-external-course-btn');
    const courseModal = document.getElementById('course-modal');
    const modalClose = document.getElementById('modal-close');
    const cancelBtn = document.getElementById('cancel-btn');
    const saveCourseBtn = document.getElementById('save-course-btn');
    const courseForm = document.getElementById('course-form');
    const coursesTableBody = document.getElementById('courses-table-body');
    const statusFilter = document.getElementById('status-filter');
    const titleSearch = document.getElementById('title-search');
    const clearFiltersBtn = document.getElementById('clear-filters');
    const prevPageBtn = document.getElementById('prev-page');
    const nextPageBtn = document.getElementById('next-page');
    const pageNumbers = document.getElementById('page-numbers');

    // Initialize
    loadCategories();
    loadCourses();

    // Event listeners
    addCourseBtn.addEventListener('click', openAddModal);
    modalClose.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);
    saveCourseBtn.addEventListener('click', saveCourse);
    statusFilter.addEventListener('change', applyFilters);
    titleSearch.addEventListener('input', debounce(applyFilters, 300));
    clearFiltersBtn.addEventListener('click', clearFilters);
    prevPageBtn.addEventListener('click', () => changePage(currentPage - 1));
    nextPageBtn.addEventListener('click', () => changePage(currentPage + 1));

    function openAddModal() {
        document.getElementById('modal-title').textContent = 'Add New Course';
        courseForm.reset();
        document.getElementById('course-id').value = '';
        courseModal.style.display = 'flex';
    }

    function closeModal() {
        courseModal.style.display = 'none';
    }

    function loadCategories() {
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=iipm_get_categories'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const categorySelect = document.getElementById('course-category');
                categorySelect.innerHTML = '<option value="">Select Category</option>';
                data.data.forEach(category => {
                    const option = document.createElement('option');
                    option.value = category.name;
                    option.textContent = category.name;
                    categorySelect.appendChild(option);
                });
            }
        })
        .catch(error => console.error('Error loading categories:', error));
    }

    function loadCourses() {
        const formData = new FormData();
        formData.append('action', 'iipm_get_user_courses');
        formData.append('page', currentPage);
        formData.append('status', currentFilters.status || '');
        formData.append('search', currentFilters.search || '');

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayCourses(data.data.courses);
                updatePagination(data.data.pagination);
            } else {
                console.error('Error loading courses:', data.data);
            }
        })
        .catch(error => console.error('Error loading courses:', error));
    }

    function displayCourses(courses) {
        coursesTableBody.innerHTML = '';
        
        if (courses.length === 0) {
            coursesTableBody.innerHTML = `
                <tr>
                    <td colspan="8" style="text-align: center; padding: 40px; color: #6b7280;">
                        No courses found
                    </td>
                </tr>
            `;
            return;
        }

        courses.forEach(course => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${course.course_name || 'N/A'}</td>
                <td>${course.LIA_Code || 'N/A'}</td>
                <td>${course.course_category || 'N/A'}</td>
                <td>${course.crs_provider || 'N/A'}</td>
                <td>${course.course_cpd_mins || 'N/A'} mins</td>
                <td><span class="status-badge status-${course.status}">${course.status}</span></td>
                <td>${formatDate(course.course_date)}</td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-outline btn-sm" onclick="editCourse(${course.id})">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="deleteCourse(${course.id})">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </td>
            `;
            coursesTableBody.appendChild(row);
        });
    }

    function updatePagination(pagination) {
        currentPage = pagination.current_page;
        totalPages = pagination.total_pages;

        prevPageBtn.disabled = currentPage <= 1;
        nextPageBtn.disabled = currentPage >= totalPages;

        // Update page numbers
        pageNumbers.innerHTML = '';
        for (let i = 1; i <= totalPages; i++) {
            const pageBtn = document.createElement('button');
            pageBtn.className = `page-number ${i === currentPage ? 'active' : ''}`;
            pageBtn.textContent = i;
            pageBtn.addEventListener('click', () => changePage(i));
            pageNumbers.appendChild(pageBtn);
        }

        // Update pagination info
        document.getElementById('pagination-info').textContent = 
            `Showing ${pagination.start} to ${pagination.end} of ${pagination.total} courses`;
    }

    function changePage(page) {
        if (page >= 1 && page <= totalPages) {
            currentPage = page;
            loadCourses();
        }
    }

    function applyFilters() {
        currentFilters = {
            status: statusFilter.value,
            search: titleSearch.value
        };
        currentPage = 1;
        loadCourses();
    }

    function clearFilters() {
        statusFilter.value = '';
        titleSearch.value = '';
        currentFilters = {};
        currentPage = 1;
        loadCourses();
    }

    function saveCourse() {
        const formData = new FormData(courseForm);
        const courseId = document.getElementById('course-id').value;
        
        if (courseId) {
            // Editing existing course
            formData.append('action', 'iipm_update_user_course');
            formData.append('course_id', courseId);
        } else {
            // Adding new course
            formData.append('action', 'iipm_add_user_course');
            formData.append('is_by_admin', '0');
            formData.append('status', 'pending');
        }

        saveCourseBtn.disabled = true;
        saveCourseBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeModal();
                loadCourses();
                if (window.notifications) {
                    const message = courseId ? (data.data || "Course updated successfully!") : "Course added successfully! It will be reviewed by admin.";
                    notifications.success("Success", message);
                }
            } else {
                if (window.notifications) {
                    notifications.error("Error", data.data || 'Unknown error');
                } else {
                    alert('Error: ' + (data.data || 'Unknown error'));
                }
            }
        })
        .catch(error => {
            console.error('Error saving course:', error);
            if (window.notifications) {
                notifications.error("Error", "Error saving course. Please try again.");
            } else {
                alert('Error saving course. Please try again.');
            }
        })
        .finally(() => {
            saveCourseBtn.disabled = false;
            saveCourseBtn.innerHTML = '<span class="btn-icon"><i class="fas fa-save"></i></span>Save Course';
        });
    }

    function editCourse(courseId) {
        // Load course data and open edit modal
        const formData = new FormData();
        formData.append('action', 'iipm_get_user_course');
        formData.append('course_id', courseId);

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const course = data.data;
                document.getElementById('modal-title').textContent = 'Edit Course';
                document.getElementById('course-id').value = course.id;
                document.getElementById('course-name').value = course.course_name || '';
                document.getElementById('course-code').value = course.LIA_Code || '';
                document.getElementById('course-category').value = course.course_category || '';
                document.getElementById('course-provider').value = course.crs_provider || '';
                document.getElementById('course-duration').value = course.course_cpd_mins || '';
                courseModal.style.display = 'flex';
            } else {
                if (window.notifications) {
                    notifications.error("Error", "Error loading course: " + (data.data || 'Unknown error'));
                } else {
                    alert('Error loading course: ' + (data.data || 'Unknown error'));
                }
            }
        })
        .catch(error => {
            console.error('Error loading course:', error);
            if (window.notifications) {
                notifications.error("Error", "Error loading course. Please try again.");
            } else {
                alert('Error loading course. Please try again.');
            }
        });
    }

    function deleteCourse(courseId) {
        if (confirm('Are you sure you want to delete this course?')) {
            const formData = new FormData();
            formData.append('action', 'iipm_delete_user_course');
            formData.append('course_id', courseId);

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadCourses();
                    if (window.notifications) {
                        notifications.success("Success", "Course deleted successfully!");
                    }
                } else {
                    if (window.notifications) {
                        notifications.error("Error", data.data || 'Unknown error');
                    } else {
                        alert('Error: ' + (data.data || 'Unknown error'));
                    }
                }
            })
            .catch(error => {
                console.error('Error deleting course:', error);
                if (window.notifications) {
                    notifications.error("Error", "Error deleting course. Please try again.");
                } else {
                    alert('Error deleting course. Please try again.');
                }
            });
        }
    }

    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        
        // Handle the new date format (dd-mm-yyyy)
        if (dateString.includes('-')) {
            const parts = dateString.split('-');
            if (parts.length === 3) {
                const day = parts[0];
                const month = parts[1];
                const year = parts[2];
                return `${day}-${month}-${year}`;
            }
        }
        
        // Handle legacy date format (dd.mm.yy)
        if (dateString.includes('.')) {
            const parts = dateString.split('.');
            if (parts.length === 3) {
                const day = parts[0];
                const month = parts[1];
                const year = parts[2]; // Convert yy to yyyy
                return `${day}.${month}.${year}`;
            }
        }
        
        // Fallback for other date formats
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return 'Invalid Date';
        
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

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

    // Make functions global for onclick handlers
    window.editCourse = editCourse;
    window.deleteCourse = deleteCourse;
});
</script>

<?php get_footer(); ?>
