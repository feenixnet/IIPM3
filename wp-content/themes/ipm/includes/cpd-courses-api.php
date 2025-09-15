<?php
/**
 * CPD Courses API Handler for coursesbyadminbku table
 * Handles all course-related API operations
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register AJAX actions for courses
 */
add_action('wp_ajax_iipm_get_courses', 'iipm_ajax_get_courses');
add_action('wp_ajax_nopriv_iipm_get_courses', 'iipm_ajax_get_courses');

add_action('wp_ajax_iipm_get_course_categories', 'iipm_ajax_get_course_categories');
add_action('wp_ajax_nopriv_iipm_get_course_categories', 'iipm_ajax_get_course_categories');

add_action('wp_ajax_iipm_get_course_providers', 'iipm_ajax_get_course_providers');
add_action('wp_ajax_nopriv_iipm_get_course_providers', 'iipm_ajax_get_course_providers');

// Course Management CRUD Actions
add_action('wp_ajax_iipm_get_all_courses', 'iipm_ajax_get_all_courses');
add_action('wp_ajax_iipm_get_all_courses_paginated', 'iipm_ajax_get_all_courses_paginated');
add_action('wp_ajax_iipm_get_categories', 'iipm_ajax_get_categories');
add_action('wp_ajax_iipm_get_providers', 'iipm_ajax_get_providers');
add_action('wp_ajax_iipm_add_course', 'iipm_ajax_add_course');
add_action('wp_ajax_iipm_update_course_v1', 'iipm_ajax_update_course');
add_action('wp_ajax_iipm_delete_course_v1', 'iipm_ajax_delete_course');

// User Course Management Actions
add_action('wp_ajax_iipm_get_user_courses', 'iipm_ajax_get_user_courses');
add_action('wp_ajax_iipm_get_user_course', 'iipm_ajax_get_user_course');
add_action('wp_ajax_iipm_add_user_course', 'iipm_ajax_add_user_course');
add_action('wp_ajax_iipm_update_user_course', 'iipm_ajax_update_user_course');
add_action('wp_ajax_iipm_delete_user_course', 'iipm_ajax_delete_user_course');

// Admin Course Management Actions
add_action('wp_ajax_iipm_get_pending_courses', 'iipm_ajax_get_pending_courses');
add_action('wp_ajax_iipm_approve_course', 'iipm_ajax_approve_course');
add_action('wp_ajax_iipm_reject_course', 'iipm_ajax_reject_course');
add_action('wp_ajax_iipm_get_user_courses_admin', 'iipm_ajax_get_user_courses_admin');

/**
 * AJAX callback for getting courses
 */
function iipm_ajax_get_courses() {
    global $wpdb;
    
    $filters = array(
        'title_search' => $_POST['title_search'] ?? '',
        'lia_code_search' => $_POST['lia_code_search'] ?? '',
        'date_from' => $_POST['date_from'] ?? '',
        'date_to' => $_POST['date_to'] ?? '',
        'categories' => $_POST['categories'] ?? '',
        'providers' => $_POST['providers'] ?? '',
        'my_courses' => $_POST['my_courses'] ?? false
    );
    
    $pagination = array(
        'current_page' => intval($_POST['page'] ?? 1),
        'per_page' => intval($_POST['per_page'] ?? 12)
    );
    
    $result = iipm_get_courses($filters, $pagination);
    
    wp_send_json_success($result);
}

/**
 * AJAX callback for getting course categories
 */
function iipm_ajax_get_course_categories() {
    $categories = iipm_get_course_categories();
    wp_send_json_success(array('categories' => $categories));
}

/**
 * AJAX callback for getting course providers
 */
function iipm_ajax_get_course_providers() {
    $providers = iipm_get_course_providers();
    wp_send_json_success(array('providers' => $providers));
}

/**
 * Get courses from coursebyadminbku table with filters
 */
function iipm_get_courses($filters = array(), $pagination = array()) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'coursesbyadminbku';
    
    // Build WHERE clause
    $where_conditions = array("status = 'active'"); // Only show active courses
    $query_params = array();
    
    // Title search filter
    if (!empty($filters['title_search'])) {
        $where_conditions[] = "course_name LIKE %s";
        $query_params[] = '%' . sanitize_text_field($filters['title_search']) . '%';
    }
    
    // LIA code search filter
    if (!empty($filters['lia_code_search'])) {
        $where_conditions[] = "LIA_Code LIKE %s";
        $query_params[] = '%' . sanitize_text_field($filters['lia_code_search']) . '%';
    }
    
    // Date range filters
    if (!empty($filters['date_from'])) {
        $where_conditions[] = "course_date >= %s";
        $query_params[] = sanitize_text_field($filters['date_from']);
    }
    
    if (!empty($filters['date_to'])) {
        $where_conditions[] = "course_date <= %s";
        $query_params[] = sanitize_text_field($filters['date_to']);
    }
    
    // Category filter
    if (!empty($filters['categories'])) {
        $categories = explode(',', sanitize_text_field($filters['categories']));
        $category_placeholders = implode(',', array_fill(0, count($categories), '%s'));
        $where_conditions[] = "course_category IN ($category_placeholders)";
        $query_params = array_merge($query_params, $categories);
    }
    
    // Provider filter
    if (!empty($filters['providers'])) {
        $provider = sanitize_text_field($filters['providers']);
        $where_conditions[] = "crs_provider = %s";
        $query_params[] = $provider;
    }
    
    // My courses filter
    if (!empty($filters['my_courses']) && ($filters['my_courses'] === 'true' || $filters['my_courses'] === true || $filters['my_courses'] === '1')) {
        $user_id = get_current_user_id();
        if ($user_id) {
            $where_conditions[] = "user_id = %d";
            $query_params[] = $user_id;
        }
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    // Get total count for pagination
    $count_query = "SELECT COUNT(*) FROM {$table_name} {$where_clause}";
    
    if (!empty($query_params)) {
        $total_courses = $wpdb->get_var($wpdb->prepare($count_query, $query_params));
    } else {
        $total_courses = $wpdb->get_var($count_query);
    }
    
    // Apply pagination
    $courses_per_page = $pagination['per_page'] ?? 12;
    $current_page = $pagination['current_page'] ?? 1;
    $offset = ($current_page - 1) * $courses_per_page;
    
    // Get courses with pagination
    $courses_query = "SELECT * FROM {$table_name} {$where_clause} ORDER BY TimeStamp DESC LIMIT {$courses_per_page} OFFSET {$offset}";
    
    if (!empty($query_params)) {
        $courses = $wpdb->get_results($wpdb->prepare($courses_query, $query_params));
    } else {
        $courses = $wpdb->get_results($courses_query);
    }
    
    return array(
        'courses' => $courses,
        'total' => $total_courses,
        'total_pages' => ceil($total_courses / $courses_per_page),
        'current_page' => $current_page,
        'per_page' => $courses_per_page
    );
}

/**
 * Get unique course categories from coursebyadminbku table
 */
function iipm_get_course_categories() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'test_iipm_cpd_categories';
    
    $categories = $wpdb->get_results(
        "SELECT * 
         FROM {$table_name}"
    );
    
    return $categories ?: array();
}

/**
 * Get unique course providers from coursebyadminbku table
 */
function iipm_get_course_providers() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'coursesbyadminbku';
    
    $providers = $wpdb->get_col(
        "SELECT DISTINCT crs_provider 
         FROM {$table_name} 
         WHERE crs_provider IS NOT NULL AND crs_provider != '' 
         ORDER BY crs_provider ASC"
    );
    
    return $providers ?: array();
}

/**
 * Get course by ID from coursebyadminbku table
 */
function iipm_get_course_by_id_from_coursebyadminbku($course_id) {
    global $wpdb;
    
    $table_name = 'coursesbyadminbku';
    
    $course = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d",
        $course_id
    ));
    
    return $course;
}

/**
 * Search courses by keyword from coursebyadminbku table
 */
function iipm_search_courses_from_coursebyadminbku($keyword, $limit = 20) {
    global $wpdb;
    
    $table_name = 'coursesbyadminbku';
    
    $courses = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_name} 
         WHERE course_name LIKE %s 
            OR course_category LIKE %s 
            OR crs_provider LIKE %s 
            OR LIA_Code LIKE %s
         ORDER BY TimeStamp DESC 
         LIMIT %d",
        '%' . $wpdb->esc_like($keyword) . '%',
        '%' . $wpdb->esc_like($keyword) . '%',
        '%' . $wpdb->esc_like($keyword) . '%',
        '%' . $wpdb->esc_like($keyword) . '%',
        $limit
    ));
    
    return $courses;
}

/**
 * Get courses by category from coursebyadminbku table
 */
function iipm_get_courses_by_category_from_coursebyadminbku($category, $limit = 20) {
    global $wpdb;
    
    $table_name = 'coursesbyadminbku';
    
    $courses = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_name} 
         WHERE course_category = %s 
         ORDER BY TimeStamp DESC 
         LIMIT %d",
        $category,
        $limit
    ));
    
    return $courses;
}

/**
 * Get recent courses from coursebyadminbku table
 */
function iipm_get_recent_courses_from_coursebyadminbku($limit = 10) {
    global $wpdb;
    
    $table_name = 'coursesbyadminbku';
    
    $courses = $wpdb->get_results(
        "SELECT * FROM {$table_name} 
         ORDER BY TimeStamp DESC 
         LIMIT {$limit}"
    );
    
    return $courses;
}

/**
 * Format course date from DD-MM-YYYY to Y-m-d
 */
function iipm_format_course_date($date_string) {
    if (empty($date_string)) {
        return '';
    }
    
    // Check if date is already in Y-m-d format
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_string)) {
        return $date_string;
    }
    
    // Convert from DD-MM-YYYY to Y-m-d
    if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $date_string, $matches)) {
        return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
    }
    
    return $date_string;
}

/**
 * Convert minutes to hours and minutes
 */
function iipm_format_cpd_duration($minutes) {
    if (empty($minutes)) {
        return '0h 0m';
    }
    
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    
    if ($hours > 0) {
        return $hours . 'h ' . $mins . 'm';
    } else {
        return $mins . 'm';
    }
}

/**
 * Get CPD points from minutes (assuming 1 hour = 1 CPD point)
 */
function iipm_get_cpd_points_from_minutes($minutes) {
    if (empty($minutes)) {
        return 0;
    }
    
    // Convert minutes to hours and round to nearest 0.5
    $hours = $minutes / 60;
    return round($hours * 2) / 2; // Round to nearest 0.5
} 

/**
 * Register additional AJAX actions for course selection
 */
add_action('wp_ajax_iipm_get_course_details', 'iipm_ajax_get_course_details');
add_action('wp_ajax_nopriv_iipm_get_course_details', 'iipm_ajax_get_course_details');

/**
 * AJAX callback for getting course details
 */
function iipm_ajax_get_course_details() {
    $course_id = intval($_POST['course_id'] ?? 0);
    
    if (!$course_id) {
        wp_send_json_error('Course ID required');
        return;
    }
    
    $course = iipm_get_course_details($course_id);
    
    if ($course) {
        wp_send_json_success($course);
    } else {
        wp_send_json_error('Course not found');
    }
}

/**
 * Get course details by ID
 */
function iipm_get_course_details($course_id) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'coursesbyadminbku';
    
    $course = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d",
        $course_id
    ));
    
    return $course;
}

/**
 * AJAX callback for getting all courses for management
 */
function iipm_ajax_get_all_courses() {
    // Check if user is admin
    if (!current_user_can('administrator')) {
        wp_send_json_error('Unauthorized access');
        return;
    }
    
    $courses = iipm_get_all_courses_management();
    wp_send_json_success($courses);
}

/**
 * AJAX callback for getting paginated courses for management
 */
function iipm_ajax_get_all_courses_paginated() {
    // Check if user is admin
    if (!current_user_can('administrator')) {
        wp_send_json_error('Unauthorized access');
        return;
    }
    
    $page = intval($_POST['page'] ?? 1);
    $per_page = intval($_POST['per_page'] ?? 10);
    
    // Get filter parameters
    $category_filter = sanitize_text_field($_POST['category_filter'] ?? '');
    $provider_filter = sanitize_text_field($_POST['provider_filter'] ?? '');
    $search_term = sanitize_text_field($_POST['search_term'] ?? '');
    
    // Debug logging
    error_log('Backend received filter parameters: ' . json_encode([
        'page' => $page,
        'per_page' => $per_page,
        'category_filter' => $category_filter,
        'provider_filter' => $provider_filter,
        'search_term' => $search_term
    ]));
    
    $result = iipm_get_all_courses_management_paginated($page, $per_page, $category_filter, $provider_filter, $search_term);
    wp_send_json_success($result);
}

/**
 * AJAX callback for getting categories for management
 */
function iipm_ajax_get_categories() {
    // Allow all logged-in users to get categories
    if (!is_user_logged_in()) {
        wp_send_json_error('Please log in to access this feature');
        return;
    }
    
    $categories = iipm_get_course_categories();
    wp_send_json_success($categories);
}

/**
 * AJAX callback for getting providers for management
 */
function iipm_ajax_get_providers() {
    // Allow all logged-in users to get providers
    if (!is_user_logged_in()) {
        wp_send_json_error('Please log in to access this feature');
        return;
    }
    
    $providers = iipm_get_course_providers();
    wp_send_json_success($providers);
}

/**
 * AJAX callback for adding a new course
 */
function iipm_ajax_add_course() {
    // Check if user is admin
    if (!current_user_can('administrator')) {
        wp_send_json_error('Unauthorized access');
        return;
    }
    
    $course_data = array(
        'course_name' => sanitize_text_field($_POST['course_name']),
        'LIA_Code' => sanitize_text_field($_POST['course_code']),
        'category_id' => intval($_POST['course_category']),
        'provider' => sanitize_text_field($_POST['course_provider']),
        'duration' => floatval($_POST['course_cpd_mins']),
        'status' => sanitize_text_field($_POST['course_status']),
        'user_id' => get_current_user_id(),
        'course_id' => rand(100000, 999999),
        'course_date' => date('d-m-Y'),
        'course_enteredBy' => get_current_user_id()
    );
    
    // Validate required fields
    if (empty($course_data['course_name']) || empty($course_data['category_id']) || 
        empty($course_data['provider']) || empty($course_data['duration'])) {
        wp_send_json_error('Required fields are missing');
        return;
    }
    
    $result = iipm_add_course_management($course_data);
    
    if ($result) {
        wp_send_json_success('Course added successfully');
    } else {
        wp_send_json_error('Failed to add course');
    }
}

/**
 * AJAX callback for updating a course
 */
function iipm_ajax_update_course() {
    // Check if user is admin
    if (!current_user_can('administrator')) {
        wp_send_json_error('Unauthorized access');
        return;
    }
    
    $course_id = intval($_POST['course_id']);
    
    if (!$course_id) {
        wp_send_json_error('Course ID is required');
        return;
    }
    
    $course_data = array(
        'course_name' => sanitize_text_field($_POST['course_name']),
        'LIA_Code' => sanitize_text_field($_POST['course_code']),
        'category_id' => intval($_POST['course_category']),
        'provider' => sanitize_text_field($_POST['course_provider']),
        'duration' => floatval($_POST['course_cpd_mins'])
    );
    
    // Validate required fields
    if (empty($course_data['course_name']) || empty($course_data['category_id']) || 
        empty($course_data['provider']) || empty($course_data['duration'])) {
        wp_send_json_error('Required fields are missing');
        return;
    }
    
    $result = iipm_update_course_management($course_id, $course_data);
    
    if ($result) {
        wp_send_json_success('Course updated successfully');
    } else {
        wp_send_json_error('Failed to update course');
    }
}

/**
 * AJAX callback for deleting a course
 */
function iipm_ajax_delete_course() {
    // Check if user is admin
    if (!current_user_can('administrator')) {
        wp_send_json_error('Unauthorized access');
        return;
    }
    
    $course_id = intval($_POST['course_id']);
    
    if (!$course_id) {
        wp_send_json_error('Course ID is required');
        return;
    }
    
    $result = iipm_delete_course_management($course_id);
    
    if ($result) {
        wp_send_json_success('Course deleted successfully');
    } else {
        wp_send_json_error('Failed to delete course');
    }
}

/**
 * Get all courses for management interface
 */
function iipm_get_all_courses_management() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'coursesbyadminbku';
    
    $courses = $wpdb->get_results(
        "SELECT * FROM {$table_name} ORDER BY TimeStamp DESC"
    );
    
    return $courses ?: array();
}

/**
 * Get paginated courses for management interface
 */
function iipm_get_all_courses_management_paginated($page = 1, $per_page = 10, $category_filter = '', $provider_filter = '', $search_term = '') {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'coursesbyadminbku';
    
    // Calculate offset
    $offset = ($page - 1) * $per_page;
    
    // Build WHERE clause based on filters
    $where_conditions = array("is_by_admin = 1"); // Only show admin-added courses
    $where_values = array();
    
    if (!empty($category_filter)) {
        // Find category name by ID
        $category_name = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}test_iipm_cpd_categories WHERE id = %d",
            $category_filter
        ));
        
        if ($category_name) {
            $where_conditions[] = "course_category = %s";
            $where_values[] = $category_name;
        }
    }
    
    if (!empty($provider_filter)) {
        $where_conditions[] = "crs_provider = %s";
        $where_values[] = $provider_filter;
    }
    
    if (!empty($search_term)) {
        $where_conditions[] = "course_name LIKE %s";
        $search_like = '%' . $wpdb->esc_like($search_term) . '%';
        $where_values[] = $search_like;
    }
    
    // Build the WHERE clause
    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }
    
    // Debug logging
    error_log('Generated WHERE clause: ' . $where_clause);
    error_log('WHERE values: ' . json_encode($where_values));
    
    // Get total count with filters
    $count_query = "SELECT COUNT(*) FROM {$table_name} {$where_clause}";
    if (!empty($where_values)) {
        $total_courses = $wpdb->get_var($wpdb->prepare($count_query, $where_values));
    } else {
        $total_courses = $wpdb->get_var($count_query);
    }

    error_log('Where values: ' . $where_values);
    
    // Get paginated courses with filters
    $courses_query = "SELECT * FROM {$table_name} {$where_clause} ORDER BY TimeStamp DESC LIMIT %d OFFSET %d";
    $query_values = array_merge($where_values, array($per_page, $offset));
    
    if (!empty($where_values)) {
        $courses = $wpdb->get_results($wpdb->prepare($courses_query, $query_values));
    } else {
        $courses = $wpdb->get_results($wpdb->prepare($courses_query, $per_page, $offset));
    }
    
    return array(
        'courses' => $courses ?: array(),
        'total' => intval($total_courses),
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total_courses / $per_page),
        'pagination' => array(
            'page' => $page,
            'per_page' => $per_page,
            'total' => intval($total_courses),
            'total_pages' => ceil($total_courses / $per_page)
        )
    );
}

/**
 * Add a new course
 */
function iipm_add_course_management($course_data) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'coursesbyadminbku';
    
    // Get category name from category ID
    $category_name = '';
    if (!empty($course_data['category_id'])) {
        $category_table = $wpdb->prefix . 'test_iipm_cpd_categories';
        $category_name = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM {$category_table} WHERE id = %d",
            $course_data['category_id']
        ));
    }
    
    $insert_data = array(
        'course_name' => $course_data['course_name'],
        'LIA_Code' => $course_data['LIA_Code'],
        'course_category' => $category_name,
        'crs_provider' => $course_data['provider'],
        'course_cpd_mins' => $course_data['duration'],
        'user_id' => $course_data['user_id'],
        'course_id' => $course_data['course_id'],
        'course_date' => $course_data['course_date'],
        'course_enteredBy' => $course_data['course_enteredBy'],
        'is_by_admin' => 1, // Mark as admin-added course
        'status' => 'active', // Admin courses are active by default
        'TimeStamp' => current_time('mysql')
    );
    
    $result = $wpdb->insert($table_name, $insert_data);
    
    return $result !== false ? $wpdb->insert_id : false;
}

/**
 * Update an existing course
 */
function iipm_update_course_management($course_id, $course_data) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'coursesbyadminbku';
    
    // Get category name from category ID
    $category_name = '';
    if (!empty($course_data['category_id'])) {
        $category_table = $wpdb->prefix . 'test_iipm_cpd_categories';
        $category_name = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM {$category_table} WHERE id = %d",
            $course_data['category_id']
        ));
    }
    
    $update_data = array(
        'course_name' => $course_data['course_name'],
        'LIA_Code' => $course_data['course_code'],
        'course_category' => $category_name,
        'crs_provider' => $course_data['provider'],
        'course_cpd_mins' => $course_data['duration'],
    );
    
    $result = $wpdb->update(
        $table_name,
        $update_data,
        array('id' => $course_id),
        array('%s', '%s', '%s', '%s', '%f'),
        array('%d')
    );
    
    return $result !== false;
}

/**
 * Delete a course
 */
function iipm_delete_course_management($course_id) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'coursesbyadminbku';
    
    $result = $wpdb->delete(
        $table_name,
        array('id' => $course_id),
        array('%d')
    );
    
    return $result !== false;
}

/**
 * AJAX callback for getting user courses
 */
function iipm_ajax_get_user_courses() {
    global $wpdb;
    
    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error('User not logged in');
        return;
    }
    
    $page = intval($_POST['page'] ?? 1);
    $per_page = 10;
    $offset = ($page - 1) * $per_page;
    
    $status = sanitize_text_field($_POST['status'] ?? '');
    $search = sanitize_text_field($_POST['search'] ?? '');
    
    $table_name = $wpdb->prefix . 'coursesbyadminbku';
    
    // Build WHERE clause
    $where_conditions = array("user_id = %d");
    $where_values = array($user_id);
    
    if (!empty($status)) {
        $where_conditions[] = "status = %s";
        $where_values[] = $status;
    }
    
    if (!empty($search)) {
        $where_conditions[] = "(course_name LIKE %s OR LIA_Code LIKE %s)";
        $search_term = '%' . $wpdb->esc_like($search) . '%';
        $where_values[] = $search_term;
        $where_values[] = $search_term;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Get total count
    $total_query = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_clause}";
    $total = $wpdb->get_var($wpdb->prepare($total_query, $where_values));
    
    // Get courses
    $courses_query = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY id DESC LIMIT %d OFFSET %d";
    $where_values[] = $per_page;
    $where_values[] = $offset;
    
    $courses = $wpdb->get_results($wpdb->prepare($courses_query, $where_values));
    
    $total_pages = ceil($total / $per_page);
    
    wp_send_json_success(array(
        'courses' => $courses,
        'pagination' => array(
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total' => $total,
            'start' => $offset + 1,
            'end' => min($offset + $per_page, $total)
        )
    ));
}

/**
 * AJAX callback for getting a single user course
 */
function iipm_ajax_get_user_course() {
    global $wpdb;
    
    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error('User not logged in');
        return;
    }
    
    $course_id = intval($_POST['course_id']);
    $table_name = $wpdb->prefix . 'coursesbyadminbku';
    
    $course = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d AND user_id = %d",
        $course_id, $user_id
    ));
    
    if (!$course) {
        wp_send_json_error('Course not found');
        return;
    }
    
    wp_send_json_success($course);
}

/**
 * AJAX callback for adding user course
 */
function iipm_ajax_add_user_course() {
    global $wpdb;
    
    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error('User not logged in');
        return;
    }
    
    $course_data = array(
        'course_name' => sanitize_text_field($_POST['course_name']),
        'LIA_Code' => sanitize_text_field($_POST['course_code']),
        'course_category' => sanitize_text_field($_POST['course_category']),
        'crs_provider' => sanitize_text_field($_POST['course_provider']),
        'course_cpd_mins' => intval($_POST['course_duration']),
        'user_id' => $user_id,
        'is_by_admin' => intval($_POST['is_by_admin'] ?? 0),
        'status' => sanitize_text_field($_POST['status'] ?? 'pending'),
        'course_id' => rand(100000, 999999),
        'course_date' => date('d-m-Y'),
        'course_enteredBy' => $user_id
    );
    
    $table_name = $wpdb->prefix . 'coursesbyadminbku';
    
    $result = $wpdb->insert($table_name, $course_data);

    error_log($result);
    
    if ($result === false) {
        wp_send_json_error('Failed to add course');
        return;
    }
    
    wp_send_json_success('Course added successfully');
}

/**
 * AJAX callback for updating user course
 */
function iipm_ajax_update_user_course() {
    global $wpdb;
    
    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error('User not logged in');
        return;
    }
    
    $course_id = intval($_POST['course_id']);
    $table_name = $wpdb->prefix . 'coursesbyadminbku';
    
    // Get original course data to compare
    $original_course = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d AND user_id = %d",
        $course_id, $user_id
    ));
    
    if (!$original_course) {
        wp_send_json_error('Course not found');
        return;
    }
    
    // Prepare new data
    $new_course_name = sanitize_text_field($_POST['course_name']);
    $new_lia_code = sanitize_text_field($_POST['course_code']);
    $new_category = sanitize_text_field($_POST['course_category']);
    $new_provider = sanitize_text_field($_POST['course_provider']);
    $new_cpd_mins = intval($_POST['course_duration']);
    
    // Check if any values have changed
    $has_changes = (
        $original_course->course_name !== $new_course_name ||
        $original_course->LIA_Code !== $new_lia_code ||
        $original_course->course_category !== $new_category ||
        $original_course->crs_provider !== $new_provider ||
        $original_course->course_cpd_mins != $new_cpd_mins
    );
    
    $course_data = array(
        'course_name' => $new_course_name,
        'LIA_Code' => $new_lia_code,
        'course_category' => $new_category,
        'crs_provider' => $new_provider,
        'course_cpd_mins' => $new_cpd_mins,
    );
    
    // If any values changed, set status to pending for admin review
    if ($has_changes) {
        $course_data['status'] = 'pending';
    }
    
    // Prepare format string based on whether status is being updated
    $format_strings = array('%s', '%s', '%s', '%s', '%d');
    if ($has_changes) {
        $format_strings[] = '%s'; // Add format for status field
    }
    
    $result = $wpdb->update(
        $table_name,
        $course_data,
        array('id' => $course_id),
        $format_strings,
        array('%d')
    );

    error_log($result);
    
    if ($result === false) {
        wp_send_json_error('Failed to update course');
        return;
    }
    
    $message = $has_changes ? 
        'Course updated successfully and sent for admin review' : 
        'Course updated successfully';
    
    wp_send_json_success($message);
}

/**
 * AJAX callback for deleting user course
 */
function iipm_ajax_delete_user_course() {
    global $wpdb;
    
    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error('User not logged in');
        return;
    }
    
    $course_id = intval($_POST['course_id']);
    $table_name = $wpdb->prefix . 'coursesbyadminbku';
    
    // Check if course belongs to user
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM {$table_name} WHERE id = %d AND user_id = %d",
        $course_id, $user_id
    ));
    
    if (!$existing) {
        wp_send_json_error('Course not found');
        return;
    }
    
    $result = $wpdb->delete($table_name, array('id' => $course_id), array('%d'));
    
    if ($result === false) {
        wp_send_json_error('Failed to delete course');
        return;
    }
    
    wp_send_json_success('Course deleted successfully');
}

/**
 * AJAX callback for getting pending courses (admin)
 */
function iipm_ajax_get_pending_courses() {
    if (!current_user_can('administrator')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    global $wpdb;
    
    $page = intval($_POST['page'] ?? 1);
    $per_page = 10;
    $offset = ($page - 1) * $per_page;
    
    $table_name = $wpdb->prefix . 'coursesbyadminbku';
    
    // Get total count - only courses added by users (is_by_admin = 0)
    $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'pending' AND is_by_admin = 0");
    
    // Get courses with user info - only courses added by users (is_by_admin = 0)
    $courses = $wpdb->get_results($wpdb->prepare(
        "SELECT c.*, u.display_name, u.user_email 
         FROM {$table_name} c 
         LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID 
         WHERE c.status = 'pending' AND c.is_by_admin = 0
         ORDER BY c.id DESC 
         LIMIT %d OFFSET %d",
        $per_page, $offset
    ));
    
    $total_pages = ceil($total / $per_page);
    
    wp_send_json_success(array(
        'courses' => $courses,
        'pagination' => array(
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total' => $total,
            'start' => $offset + 1,
            'end' => min($offset + $per_page, $total)
        )
    ));
}

/**
 * AJAX callback for approving course (admin)
 */
function iipm_ajax_approve_course() {
    if (!current_user_can('administrator')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    global $wpdb;
    
    $course_id = intval($_POST['course_id']);
    $table_name = $wpdb->prefix . 'coursesbyadminbku';
    
    $result = $wpdb->update(
        $table_name,
        array('status' => 'active'),
        array('id' => $course_id),
        array('%s'),
        array('%d')
    );
    
    if ($result === false) {
        wp_send_json_error('Failed to approve course');
        return;
    }
    
    wp_send_json_success('Course approved successfully');
}

/**
 * AJAX callback for rejecting course (admin)
 */
function iipm_ajax_reject_course() {
    if (!current_user_can('administrator')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    global $wpdb;
    
    $course_id = intval($_POST['course_id']);
    $table_name = $wpdb->prefix . 'coursesbyadminbku';
    
    $result = $wpdb->update(
        $table_name,
        array('status' => 'rejected'),
        array('id' => $course_id),
        array('%s'),
        array('%d')
    );
    
    if ($result === false) {
        wp_send_json_error('Failed to reject course');
        return;
    }
    
    wp_send_json_success('Course rejected successfully');
}


/**
 * AJAX callback for getting user courses for admin
 */
function iipm_ajax_get_user_courses_admin() {
    if (!current_user_can('administrator')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    global $wpdb;
    
    $page = intval($_POST['page'] ?? 1);
    $per_page = 10;
    $offset = ($page - 1) * $per_page;
    
    $status = sanitize_text_field($_POST['status'] ?? 'active');
    $search = sanitize_text_field($_POST['search'] ?? '');
    
    $table_name = $wpdb->prefix . 'coursesbyadminbku';
    
    // Build WHERE clause - only show courses added by users (is_by_admin = 0)
    $where_conditions = array("status = %s", "is_by_admin = 0");
    $where_values = array($status);
    
    if (!empty($search)) {
        $where_conditions[] = "(course_name LIKE %s OR LIA_Code LIKE %s)";
        $search_term = '%' . $wpdb->esc_like($search) . '%';
        $where_values[] = $search_term;
        $where_values[] = $search_term;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Get total count
    $total_query = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_clause}";
    $total = $wpdb->get_var($wpdb->prepare($total_query, $where_values));
    
    // Get courses with user info
    $courses_query = "SELECT c.*, u.display_name, u.user_email 
                      FROM {$table_name} c 
                      LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID 
                      WHERE {$where_clause} 
                      ORDER BY c.id DESC 
                      LIMIT %d OFFSET %d";
    $where_values[] = $per_page;
    $where_values[] = $offset;
    
    $courses = $wpdb->get_results($wpdb->prepare($courses_query, $where_values));
    
    $total_pages = ceil($total / $per_page);
    
    wp_send_json_success(array(
        'courses' => $courses,
        'pagination' => array(
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total' => $total,
            'start' => $offset + 1,
            'end' => min($offset + $per_page, $total)
        )
    ));
}