<?php
/**
 * CPD Record API Handler for crscompleted_by table
 * Handles all CPD record-related API operations
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Parse date from various formats (dd/mm/yyyy or Y-m-d H:i:s)
 */
function iipm_parse_cpd_date($date_string) {
    if (empty($date_string)) {
        return null;
    }
    
    // If it's already in Y-m-d H:i:s format, use strtotime directly
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date_string)) {
        return strtotime($date_string);
    }
    
    // If it's in dd/mm/yyyy format, convert it
    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date_string)) {
        $date_parts = explode('/', $date_string);
        if (count($date_parts) === 3) {
            // Convert dd/mm/yyyy to yyyy-mm-dd
            $formatted_date = $date_parts[2] . '-' . $date_parts[1] . '-' . $date_parts[0];
            return strtotime($formatted_date);
        }
    }
    
    // If it's in yyyy-mm-dd format, use strtotime
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_string)) {
        return strtotime($date_string);
    }
    
    // Fallback to strtotime for other formats
    return strtotime($date_string);
}

/**
 * Register AJAX actions for CPD records
 */
add_action('wp_ajax_iipm_get_cpd_stats', 'iipm_ajax_get_cpd_stats');
add_action('wp_ajax_nopriv_iipm_get_cpd_stats', 'iipm_ajax_get_cpd_stats');

/**
 * AJAX callback for getting CPD stats
 */
function iipm_ajax_get_cpd_stats() {
    $year = intval($_POST['year'] ?? date('Y'));
    $user_id = get_current_user_id();
    
    if (!$user_id) {
        wp_send_json_error('User not logged in');
        return;
    }
    
    $stats = iipm_get_cpd_stats($user_id, $year);
    wp_send_json_success($stats);
}

/**
 * Get CPD stats for a specific user and year
 */
function iipm_get_cpd_stats($user_id, $year) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'fullcpd_confirmations';
    
    // Get all completed courses for the user in the specified year (only courses with dateOfReturn not null)
    $query = $wpdb->prepare(
        "SELECT * FROM {$table_name} 
         WHERE user_id = %d AND year = %d AND dateOfReturn IS NOT NULL
         ORDER BY dateOfCourse ASC",
        $user_id,
        $year
    );
    
    $completed_courses = $wpdb->get_results($query);

    // Get CPD types to get target minutes, dates, and user assignments
    $cpd_types = iipm_get_cpd_types();
    $target_minutes = 330; // Default fallback
    $cpd_dates = array();
    $is_logging_period_available = false;
    $is_submission_period_available = false;
    $is_user_assigned = false;
    
    if (!empty($cpd_types)) {
        // Find CPD type by matching year with Start of logging date
        $primary_type = null;
        foreach ($cpd_types as $type) {
            $start_logging_year = date('Y', strtotime($type->{'Start of logging date'}));
            if ($start_logging_year == $year) {
                $primary_type = $type;
                break;
            }
        }
        
        if ($primary_type) {
            $original_target = intval($primary_type->{'Total Hours/Points Required'});
            // Calculate adjusted target based on leave requests (returns hours with 1 decimal place)
            $target_hours = iipm_calculate_adjusted_target_points($user_id, $year);
            $target_minutes = $target_hours * 60; // Convert hours to minutes for compatibility
            $cpd_dates = array(
                'start_logging' => $primary_type->{'Start of logging date'},
                'end_logging' => $primary_type->{'End of logging date'},
                'start_submission' => $primary_type->{'Start of annual submission date'},
                'end_submission' => $primary_type->{'End of annual submission date'}
            );
            
            // Check if current date is in logging period
            $current_date = current_time('Y-m-d');
            $start_logging = $primary_type->{'Start of logging date'};
            $end_logging = $primary_type->{'End of logging date'};
            
            if ($start_logging && $end_logging) {
                $is_logging_period_available = ($current_date >= $start_logging && $current_date <= $end_logging);
            }
            
            // Check if current date is in submission period
            $start_submission = $primary_type->{'Start of annual submission date'};
            $end_submission = $primary_type->{'End of annual submission date'};
            
            if ($start_submission && $end_submission) {
                $is_submission_period_available = ($current_date >= $start_submission && $current_date <= $end_submission);
            }
            
            // Check if user is assigned to CPD
            $user_ids_assigned = $primary_type->{'User Ids Assigned'};
            if ($user_ids_assigned) {
                // Parse the string array format like "['12', '13', '14']"
                $assigned_user_ids = array();
                
                // Remove brackets and quotes, then split by comma
                $clean_string = trim($user_ids_assigned, "[]");
                if (!empty($clean_string)) {
                    $user_id_strings = explode(',', $clean_string);
                    foreach ($user_id_strings as $user_id_string) {
                        // Remove quotes and trim whitespace
                        $clean_id = trim($user_id_string, " '\"");
                        if (!empty($clean_id)) {
                            $assigned_user_ids[] = $clean_id;
                        }
                    }
                }
                $is_user_assigned = in_array(strval($user_id), $assigned_user_ids);
            }
        }
    }
    
    if (empty($completed_courses)) {
        return array(
            'total_cpd_minutes' => 0,
            'target_minutes' => $target_minutes,
            'completion_percentage' => 0,
            'start_date' => null,
            'completion_date' => null,
            'courses_summary' => array(),
            'total_hours' => 0,
            'cpd_dates' => $cpd_dates,
            'is_logging_period_available' => $is_logging_period_available,
            'is_submission_period_available' => $is_submission_period_available,
            'is_user_assigned' => $is_user_assigned
        );
    }
    
    // Get all available categories from the courses table
    $categories = iipm_get_course_categories();
    
    // Initialize category arrays with completion status
    $category_data = array();
    foreach ($categories as $category) {
        $category_data[$category->name] = array(
            'courses' => array(),
            'total_minutes' => 0,
            'total_hours' => 0,
            'count' => 0,
            'required' => 1, // Each category requires 1 course per year
            'completed' => false,
            'status' => '0/1' // Default: 0 completed out of 1 required
        );
    }
    
    // Process each completed course (only courses with dateOfReturn not null)
    $total_minutes = 0;
    $total_hours = 0;
    $dates = array();
    
    foreach ($completed_courses as $course) {
        // Parse hrsAndCategory field (format: "2hrs: Pensions")
        $hrs_and_category = explode(': ', $course->hrsAndCategory, 2);
        $hours = 0;
        $category_name = 'Unknown';
        
        if (count($hrs_and_category) >= 2) {
            // Extract hours from "2hrs" format
            $hours_text = trim($hrs_and_category[0]);
            $hours = floatval(preg_replace('/[^0-9.]/', '', $hours_text));
            $category_name = trim($hrs_and_category[1]);
        }
        
        $minutes = $hours * 60;
        $total_minutes += $minutes;
        $total_hours += $hours;
        
        // Add to category data
        if (isset($category_data[$category_name])) {
            $category_data[$category_name]['courses'][] = array(
                'id' => $course->id,
                'courseName' => $course->courseName,
                'courseType' => $course->courseType,
                'hours' => $hours,
                'minutes' => $minutes,
                'dateOfCourse' => $course->dateOfCourse,
                'dateOfReturn' => $course->dateOfReturn,
                'crs_provider' => $course->crs_provider
            );
            $category_data[$category_name]['total_minutes'] += $minutes;
            $category_data[$category_name]['total_hours'] += $hours;
            $category_data[$category_name]['count']++;
            $category_data[$category_name]['completed'] = true;
            $category_data[$category_name]['status'] = $category_data[$category_name]['count'] . '/1';
        }
        
        // Collect dates for start/completion calculation
        if (!empty($course->dateOfCourse)) {
            $dates[] = iipm_parse_cpd_date($course->dateOfCourse);
        }
        if (!empty($course->dateOfReturn)) {
            $dates[] = iipm_parse_cpd_date($course->dateOfReturn);
        }
    }
    
    error_log(json_encode($dates));
    
    // Calculate start and completion dates
    $start_date = null;
    $completion_date = null;
    
    if (!empty($dates)) {
        $start_date = date('Y-m-d', min($dates));
        $completion_date = date('Y-m-d', max($dates));
    }
    
    // Calculate completion percentage
    $completion_percentage = $target_minutes > 0 ? min(100, round(($total_minutes / $target_minutes) * 100, 1)) : 0;
    
    // Prepare courses summary with completion status
    $courses_summary = array();
    foreach ($category_data as $category_name => $data) {
        $courses_summary[] = array(
            'category' => $category_name,
            'count' => $data['count'],
            'total_hours' => $data['total_hours'],
            'total_minutes' => $data['total_minutes'],
            'credits' => $data['total_hours'], // 1 hour = 1 credit
            'required' => $data['required'],
            'completed' => $data['completed'],
            'status' => $data['status']
        );
    }
    
    return array(
        'total_cpd_minutes' => $total_minutes,
        'target_minutes' => $target_minutes,
        'completion_percentage' => $completion_percentage,
        'start_date' => $start_date,
        'completion_date' => $completion_date,
        'courses_summary' => $courses_summary,
        'total_hours' => $total_hours,
        'cpd_dates' => $cpd_dates,
        'is_logging_period_available' => $is_logging_period_available,
        'is_submission_period_available' => $is_submission_period_available,
        'is_user_assigned' => $is_user_assigned
    );
}

/**
 * Register additional AJAX actions for CPD reports
 */
add_action('wp_ajax_iipm_get_cpd_types', 'iipm_ajax_get_cpd_types');
add_action('wp_ajax_nopriv_iipm_get_cpd_types', 'iipm_ajax_get_cpd_types');

add_action('wp_ajax_iipm_get_completed_cpd_stats', 'iipm_ajax_get_completed_cpd_stats');
add_action('wp_ajax_nopriv_iipm_get_completed_cpd_stats', 'iipm_ajax_get_completed_cpd_stats');

add_action('wp_ajax_iipm_get_uncompleted_cpd_stats', 'iipm_ajax_get_uncompleted_cpd_stats');
add_action('wp_ajax_nopriv_iipm_get_uncompleted_cpd_stats', 'iipm_ajax_get_uncompleted_cpd_stats');

add_action('wp_ajax_iipm_add_cpd_confirmation', 'iipm_ajax_add_cpd_confirmation');
add_action('wp_ajax_nopriv_iipm_add_cpd_confirmation', 'iipm_ajax_get_cpd_types');

add_action('wp_ajax_iipm_complete_cpd_course', 'iipm_ajax_complete_cpd_course');
add_action('wp_ajax_nopriv_iipm_complete_cpd_course', 'iipm_ajax_complete_cpd_course');

add_action('wp_ajax_iipm_delete_cpd_confirmation', 'iipm_ajax_delete_cpd_confirmation');
add_action('wp_ajax_nopriv_iipm_delete_cpd_confirmation', 'iipm_ajax_delete_cpd_confirmation');

add_action('wp_ajax_iipm_get_completed_courses_history', 'iipm_ajax_get_completed_courses_history');
add_action('wp_ajax_nopriv_iipm_get_completed_courses_history', 'iipm_ajax_get_completed_courses_history');

add_action('wp_ajax_iipm_get_started_courses', 'iipm_ajax_get_started_courses');
add_action('wp_ajax_nopriv_iipm_get_started_courses', 'iipm_ajax_get_started_courses');

add_action('wp_ajax_iipm_get_courses_in_learning_path', 'iipm_ajax_get_courses_in_learning_path');
add_action('wp_ajax_nopriv_iipm_get_courses_in_learning_path', 'iipm_ajax_get_courses_in_learning_path');

add_action('wp_ajax_iipm_assign_to_cpd', 'iipm_ajax_assign_to_cpd');
add_action('wp_ajax_nopriv_iipm_assign_to_cpd', 'iipm_ajax_assign_to_cpd');

add_action('wp_ajax_iipm_submit_cpd', 'iipm_ajax_submit_cpd');
add_action('wp_ajax_nopriv_iipm_submit_cpd', 'iipm_ajax_submit_cpd');

add_action('wp_ajax_iipm_get_recently_logged_training', 'iipm_ajax_get_recently_logged_training');
add_action('wp_ajax_nopriv_iipm_get_recently_logged_training', 'iipm_ajax_get_recently_logged_training');

/**
 * AJAX callback for getting CPD types
 */
function iipm_ajax_get_cpd_types() {
    $cpd_types = iipm_get_cpd_types();
    wp_send_json_success($cpd_types);
}

/**
 * AJAX callback for getting completed CPD stats
 */
function iipm_ajax_get_completed_cpd_stats() {
    $user_id = get_current_user_id();
    $year = intval($_POST['year'] ?? date('Y'));
    
    if (!$user_id) {
        wp_send_json_error('User not logged in');
        return;
    }
    
    $stats = iipm_get_completed_cpd_stats($user_id, $year);
    wp_send_json_success($stats);
}

/**
 * AJAX callback for getting uncompleted CPD stats
 */
function iipm_ajax_get_uncompleted_cpd_stats() {
    $user_id = get_current_user_id();
    $year = intval($_POST['year'] ?? date('Y'));
    
    if (!$user_id) {
        wp_send_json_error('User not logged in');
        return;
    }
    
    $stats = iipm_get_uncompleted_cpd_stats($user_id, $year);
    wp_send_json_success($stats);
}

/**
 * AJAX callback for adding CPD confirmation
 */
function iipm_ajax_add_cpd_confirmation() {
    $user_id = get_current_user_id();
    
    if (!$user_id) {
        wp_send_json_error('User not logged in');
        return;
    }
    
    $course_id = intval($_POST['course_id'] ?? 0);
    $course_name = sanitize_text_field($_POST['course_name'] ?? '');
    $course_category = sanitize_text_field($_POST['course_category'] ?? '');
    $course_cpd_mins = intval($_POST['course_cpd_mins'] ?? 0);
    $crs_provider = sanitize_text_field($_POST['crs_provider'] ?? '');
    
    if (!$course_id || !$course_name || !$course_category || !$course_cpd_mins) {
        wp_send_json_error('Missing required fields');
        return;
    }
    
    $result = iipm_add_cpd_confirmation($user_id, $course_id, $course_name, $course_category, $course_cpd_mins, $crs_provider);
    
    if ($result) {
        wp_send_json_success('Course added successfully');
    } else {
        wp_send_json_error('Failed to add course');
    }
}

/**
 * AJAX callback for completing CPD course
 */
function iipm_ajax_complete_cpd_course() {
    $user_id = get_current_user_id();
    $confirmation_id = intval($_POST['confirmation_id'] ?? 0);
    
    if (!$user_id || !$confirmation_id) {
        wp_send_json_error('Missing required fields');
        return;
    }
    
    $result = iipm_complete_cpd_course($user_id, $confirmation_id);
    
    if ($result) {
        wp_send_json_success('Course completed successfully');
    } else {
        wp_send_json_error('Failed to complete course');
    }
}

/**
 * AJAX callback for deleting CPD confirmation
 */
function iipm_ajax_delete_cpd_confirmation() {
    $user_id = get_current_user_id();
    $confirmation_id = intval($_POST['confirmation_id'] ?? 0);
    
    if (!$user_id || !$confirmation_id) {
        wp_send_json_error('Missing required fields');
        return;
    }
    
    $result = iipm_delete_cpd_confirmation($user_id, $confirmation_id);
    
    if ($result) {
        wp_send_json_success('Course deleted successfully');
    } else {
        wp_send_json_error('Failed to delete course');
    }
}

/**
 * AJAX callback for getting completed courses history (past 5 years)
 */
function iipm_ajax_get_completed_courses_history() {
    $user_id = get_current_user_id();
    
    if (!$user_id) {
        wp_send_json_error('User not logged in');
        return;
    }
    
    $completed_courses = iipm_get_completed_courses_history($user_id);
    
    wp_send_json_success(array(
        'completed_courses' => $completed_courses
    ));
}

/**
 * AJAX callback for getting currently started courses (in progress)
 */
function iipm_ajax_get_started_courses() {
    $user_id = get_current_user_id();
    
    if (!$user_id) {
        wp_send_json_error('User not logged in');
        return;
    }
    
    $started_courses = iipm_get_started_courses($user_id);
    
    wp_send_json_success(array(
        'started_courses' => $started_courses
    ));
}

/**
 * AJAX callback for getting all courses in learning path (both completed and started)
 */
function iipm_ajax_get_courses_in_learning_path() {
    $user_id = get_current_user_id();
    
    if (!$user_id) {
        wp_send_json_error('User not logged in');
        return;
    }
    
    $courses = iipm_get_courses_in_learning_path($user_id);
    
    wp_send_json_success(array(
        'courses' => $courses
    ));
}

/**
 * AJAX callback for assigning user to CPD
 */
function iipm_ajax_assign_to_cpd() {
    $user_id = get_current_user_id();
    
    if (!$user_id) {
        wp_send_json_error('User not logged in');
        return;
    }
    
    $result = iipm_assign_user_to_cpd($user_id);
    
    if ($result) {
        wp_send_json_success('Successfully assigned to CPD');
    } else {
        wp_send_json_error('Failed to assign to CPD');
    }
}

/**
 * AJAX callback for submitting CPD
 */
function iipm_ajax_submit_cpd() {
    $user_id = get_current_user_id();
    
    if (!$user_id) {
        wp_send_json_error('User not logged in');
        return;
    }
    
    $result = iipm_submit_user_cpd($user_id);
    
    if ($result) {
        wp_send_json_success('CPD submission completed successfully');
    } else {
        wp_send_json_error('Failed to submit CPD');
    }
}

/**
 * AJAX callback for getting recently logged training
 */
function iipm_ajax_get_recently_logged_training() {
    $user_id = get_current_user_id();
    $year = intval($_POST['year'] ?? date('Y'));
    
    if (!$user_id) {
        wp_send_json_error('User not logged in');
        return;
    }
    
    $training = iipm_get_recently_logged_training($user_id, $year);
    wp_send_json_success($training);
}

/**
 * Register AJAX actions for CPD reports (admin functions)
 * Note: Some functions are already registered in cpd-reporting-functions.php
 */
add_action('wp_ajax_iipm_get_cpd_compliance_stats', 'iipm_ajax_get_cpd_compliance_stats');
add_action('wp_ajax_iipm_get_compliance_data', 'iipm_ajax_get_compliance_data');
add_action('wp_ajax_iipm_get_all_members_for_reports', 'iipm_ajax_get_all_members_for_reports');
add_action('wp_ajax_iipm_get_individual_report', 'iipm_ajax_get_individual_report');

/**
 * AJAX callback for getting CPD compliance stats (admin)
 */
function iipm_ajax_get_cpd_compliance_stats() {
    if (!current_user_can('administrator')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    $year = intval($_POST['year'] ?? date('Y'));
    $stats = iipm_get_cpd_compliance_stats($year);
    wp_send_json_success($stats);
}

/**
 * AJAX callback for getting compliance data (admin)
 */
function iipm_ajax_get_compliance_data() {
    if (!current_user_can('administrator')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    $year = intval($_POST['year'] ?? date('Y'));
    $data = iipm_get_detailed_compliance_data($year);
    wp_send_json_success($data);
}

/**
 * AJAX callback for getting all members for reports (admin)
 */
function iipm_ajax_get_all_members_for_reports() {
    if (!current_user_can('administrator')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    $year = intval($_POST['year'] ?? date('Y'));
    $members = iipm_get_all_members_with_progress($year);
    wp_send_json_success($members);
}

/**
 * AJAX callback for getting individual report (admin)
 */
function iipm_ajax_get_individual_report() {
    if (!current_user_can('administrator')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    $user_id = intval($_POST['user_id'] ?? 0);
    $year = intval($_POST['year'] ?? date('Y'));
    
    if (!$user_id) {
        wp_send_json_error('User ID required');
        return;
    }
    
    $report = iipm_get_individual_member_report($user_id, $year);
    wp_send_json_success($report);
}

// Export and reminder functions are already handled by existing AJAX handlers in cpd-reporting-functions.php

/**
 * Get CPD types from the cpd_types table
 */
function iipm_get_cpd_types() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'cpd_types';
    
    $cpd_types = $wpdb->get_results(
        "SELECT * FROM {$table_name} ORDER BY id ASC"
    );
    
    return $cpd_types ?: array();
}

/**
 * Get completed CPD stats (courses with dateOfReturn)
 */
function iipm_get_completed_cpd_stats($user_id, $year) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'fullcpd_confirmations';
    
    // Get all completed courses for the user in the specified year
    $query = $wpdb->prepare(
        "SELECT * FROM {$table_name} 
         WHERE user_id = %d AND year = %d AND dateOfReturn IS NOT NULL
         ORDER BY dateOfCourse ASC",
        $user_id,
        $year
    );
    
    $completed_courses = $wpdb->get_results($query);
    
    if (empty($completed_courses)) {
        // Get CPD types to get target minutes, dates, and user assignments
        $cpd_types = iipm_get_cpd_types();
        $target_minutes = 330; // Default fallback
        $cpd_dates = array();
        $is_logging_period_available = false;
        $is_submission_period_available = false;
        $is_user_assigned = false;
        
        if (!empty($cpd_types)) {
            // Find CPD type by matching year with Start of logging date
            $primary_type = null;
            foreach ($cpd_types as $type) {
                $start_logging_year = date('Y', strtotime($type->{'Start of logging date'}));
                if ($start_logging_year == $year) {
                    $primary_type = $type;
                    break;
                }
            }
            
            if ($primary_type) {
                $original_target = intval($primary_type->{'Total Hours/Points Required'});
                // Calculate adjusted target based on leave requests (returns hours)
                $target_minutes = iipm_calculate_adjusted_target_points($user_id, $year) * 60; // Convert hours to minutes for compatibility
                $cpd_dates = array(
                    'start_logging' => $primary_type->{'Start of logging date'},
                    'end_logging' => $primary_type->{'End of logging date'},
                    'start_submission' => $primary_type->{'Start of annual submission date'},
                    'end_submission' => $primary_type->{'End of annual submission date'}
                );
                
                // Check if current date is in logging period
                $current_date = current_time('Y-m-d');
                $start_logging = $primary_type->{'Start of logging date'};
                $end_logging = $primary_type->{'End of logging date'};
                
                if ($start_logging && $end_logging) {
                    $is_logging_period_available = ($current_date >= $start_logging && $current_date <= $end_logging);
                }
                
                // Check if current date is in submission period
                $start_submission = $primary_type->{'Start of annual submission date'};
                $end_submission = $primary_type->{'End of annual submission date'};
                
                if ($start_submission && $end_submission) {
                    $is_submission_period_available = ($current_date >= $start_submission && $current_date <= $end_submission);
                }
                
                // Check if user is assigned to CPD
                $user_ids_assigned = $primary_type->{'User Ids Assigned'};
                if ($user_ids_assigned) {
                    // Parse the string array format like "['12', '13', '14']"
                    $assigned_user_ids = array();
                    
                    // Remove brackets and quotes, then split by comma
                    $clean_string = trim($user_ids_assigned, "[]");
                    if (!empty($clean_string)) {
                        $user_id_strings = explode(',', $clean_string);
                        foreach ($user_id_strings as $user_id_string) {
                            // Remove quotes and trim whitespace
                            $clean_id = trim($user_id_string, " '\"");
                            if (!empty($clean_id)) {
                                $assigned_user_ids[] = $clean_id;
                            }
                        }
                    }
                    
                    $is_user_assigned = in_array(strval($user_id), $assigned_user_ids);
                }
            }
        }
        
        return array(
            'total_cpd_minutes' => 0,
            'target_minutes' => $target_minutes,
            'completion_percentage' => 0,
            'start_date' => null,
            'completion_date' => null,
            'courses_summary' => array(),
            'total_hours' => 0,
            'cpd_dates' => $cpd_dates,
            'is_logging_period_available' => $is_logging_period_available,
            'is_submission_period_available' => $is_submission_period_available,
            'is_user_assigned' => $is_user_assigned
        );
    }
    
    // Get CPD types to get target minutes
    $cpd_types = iipm_get_cpd_types();
    $target_minutes = 0;
    
    if (!empty($cpd_types)) {
        // Find CPD type by matching year with Start of logging date
        $primary_type = null;
        foreach ($cpd_types as $type) {
            $start_logging_year = date('Y', strtotime($type->{'Start of logging date'}));
            if ($start_logging_year == $year) {
                $primary_type = $type;
                break;
            }
        }
        
        if ($primary_type) {
            $original_target = intval($primary_type->{'Total Hours/Points Required'});
            // Calculate adjusted target based on leave requests (returns hours with 1 decimal place)
            $target_hours = iipm_calculate_adjusted_target_points($user_id, $year);
            $target_minutes = $target_hours * 60; // Convert hours to minutes for compatibility
        }
    }
    
    // Get all available categories
    $categories = iipm_get_course_categories();
    
    // Initialize category arrays
    $category_data = array();
    foreach ($categories as $category) {
        $category_data[$category] = array(
            'courses' => array(),
            'total_minutes' => 0,
            'total_hours' => 0,
            'count' => 0,
            'required' => 1,
            'completed' => false,
            'status' => '0/1'
        );
    }
    
    // Process each completed course
    $total_minutes = 0;
    $total_hours = 0;
    $dates = array();
    
    foreach ($completed_courses as $course) {
        // Parse hrsAndCategory field
        $hrs_and_category = explode(': ', $course->hrsAndCategory, 2);
        $hours = 0;
        $category_name = 'Unknown';
        
        if (count($hrs_and_category) >= 2) {
            $hours_text = trim($hrs_and_category[0]);
            $hours = floatval(preg_replace('/[^0-9.]/', '', $hours_text));
            $category_name = trim($hrs_and_category[1]);
        }
        
        $minutes = $hours * 60;
        $total_minutes += $minutes;
        $total_hours += $hours;
        
        // Add to category data
        if (isset($category_data[$category_name])) {
            $category_data[$category_name]['courses'][] = $course;
            $category_data[$category_name]['total_minutes'] += $minutes;
            $category_data[$category_name]['total_hours'] += $hours;
            $category_data[$category_name]['count']++;
            $category_data[$category_name]['completed'] = true;
            $category_data[$category_name]['status'] = $category_data[$category_name]['count'] . '/1';
        }
        
        // Collect dates
        if (!empty($course->dateOfCourse)) {
            $dates[] = iipm_parse_cpd_date($course->dateOfCourse);
        }
        if (!empty($course->dateOfReturn)) {
            $dates[] = iipm_parse_cpd_date($course->dateOfReturn);
        }
    }
    
    // Calculate dates
    $start_date = null;
    $completion_date = null;
    
    if (!empty($dates)) {
        $start_date = date('Y-m-d', min($dates));
        $completion_date = date('Y-m-d', max($dates));
    }
    
    // Calculate completion percentage
    $completion_percentage = $target_minutes > 0 ? min(100, round(($total_minutes / $target_minutes) * 100, 1)) : 0;
    
    // Prepare courses summary
    $courses_summary = array();
    foreach ($category_data as $category_name => $data) {
        $courses_summary[] = array(
            'category' => $category_name,
            'count' => $data['count'],
            'total_hours' => $data['total_hours'],
            'total_minutes' => $data['total_minutes'],
            'credits' => $data['total_hours'],
            'required' => $data['required'],
            'completed' => $data['completed'],
            'status' => $data['status']
        );
    }
    
    // Get CPD types to get dates and user assignments
    $cpd_types = iipm_get_cpd_types();
    $cpd_dates = array();
    $is_logging_period_available = false;
    $is_submission_period_available = false;
    $is_user_assigned = false;
    
    if (!empty($cpd_types)) {
        // Get the primary CPD type or first one
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
        
        if ($primary_type) {
            $cpd_dates = array(
                'start_logging' => $primary_type->{'Start of logging date'},
                'end_logging' => $primary_type->{'End of logging date'},
                'start_submission' => $primary_type->{'Start of annual submission date'},
                'end_submission' => $primary_type->{'End of annual submission date'}
            );
            
            // Check if current date is in logging period
            $current_date = current_time('Y-m-d');
            $start_logging = $primary_type->{'Start of logging date'};
            $end_logging = $primary_type->{'End of logging date'};
            
            if ($start_logging && $end_logging) {
                $is_logging_period_available = ($current_date >= $start_logging && $current_date <= $end_logging);
            }
            
            // Check if current date is in submission period
            $start_submission = $primary_type->{'Start of annual submission date'};
            $end_submission = $primary_type->{'End of annual submission date'};
            
            if ($start_submission && $end_submission) {
                $is_submission_period_available = ($current_date >= $start_submission && $current_date <= $end_submission);
            }
            
            // Check if user is assigned to CPD
            $user_ids_assigned = $primary_type->{'User Ids Assigned'};
            if ($user_ids_assigned) {
                // Parse the string array format like "['12', '13', '14']"
                $assigned_user_ids = array();
                
                // Remove brackets and quotes, then split by comma
                $clean_string = trim($user_ids_assigned, "[]");
                if (!empty($clean_string)) {
                    $user_id_strings = explode(',', $clean_string);
                    foreach ($user_id_strings as $user_id_string) {
                        // Remove quotes and trim whitespace
                        $clean_id = trim($user_id_string, " '\"");
                        if (!empty($clean_id)) {
                            $assigned_user_ids[] = $clean_id;
                        }
                    }
                }
                
                $is_user_assigned = in_array(strval($user_id), $assigned_user_ids);
            }
        }
    }
    
    return array(
        'total_cpd_minutes' => $total_minutes,
        'target_minutes' => $target_minutes,
        'completion_percentage' => $completion_percentage,
        'start_date' => $start_date,
        'completion_date' => $completion_date,
        'courses_summary' => $courses_summary,
        'total_hours' => $total_hours,
        'cpd_dates' => $cpd_dates,
        'is_logging_period_available' => $is_logging_period_available,
        'is_submission_period_available' => $is_submission_period_available,
        'is_user_assigned' => $is_user_assigned
    );
}

/**
 * Get uncompleted CPD stats (courses without dateOfReturn)
 */
function iipm_get_uncompleted_cpd_stats($user_id, $year) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'fullcpd_confirmations';
    
    // Get all uncompleted courses for the user in the specified year
    $query = $wpdb->prepare(
        "SELECT * FROM {$table_name} 
         WHERE user_id = %d AND year = %d AND dateOfReturn IS NULL
         ORDER BY dateOfCourse ASC",
        $user_id,
        $year
    );
    
    $uncompleted_courses = $wpdb->get_results($query);
    
    if (empty($uncompleted_courses)) {
        return array(
            'total_cpd_minutes' => 0,
            'target_minutes' => 0,
            'completion_percentage' => 0,
            'start_date' => null,
            'completion_date' => null,
            'courses_summary' => array(),
            'total_hours' => 0
        );
    }
    
    // Get CPD types to get target minutes
    $cpd_types = iipm_get_cpd_types();
    $target_minutes = 0;
    
    if (!empty($cpd_types)) {
        // Find CPD type by matching year with Start of logging date
        $primary_type = null;
        foreach ($cpd_types as $type) {
            $start_logging_year = date('Y', strtotime($type->{'Start of logging date'}));
            if ($start_logging_year == $year) {
                $primary_type = $type;
                break;
            }
        }
        
        if ($primary_type) {
            $original_target = intval($primary_type->{'Total Hours/Points Required'});
            // Calculate adjusted target based on leave requests (returns hours with 1 decimal place)
            $target_hours = iipm_calculate_adjusted_target_points($user_id, $year);
            $target_minutes = $target_hours * 60; // Convert hours to minutes for compatibility
        }
    }
    
    // Get all available categories
    $categories = iipm_get_course_categories();
    
    // Initialize category arrays
    $category_data = array();
    foreach ($categories as $category) {
        $category_data[$category] = array(
            'courses' => array(),
            'total_minutes' => 0,
            'total_hours' => 0,
            'count' => 0,
            'required' => 1,
            'completed' => false,
            'status' => '0/1'
        );
    }
    
    // Process each uncompleted course
    $total_minutes = 0;
    $total_hours = 0;
    $dates = array();
    
    foreach ($uncompleted_courses as $course) {
        // Parse hrsAndCategory field
        $hrs_and_category = explode(': ', $course->hrsAndCategory, 2);
        $hours = 0;
        $category_name = 'Unknown';
        
        if (count($hrs_and_category) >= 2) {
            $hours_text = trim($hrs_and_category[0]);
            $hours = floatval(preg_replace('/[^0-9.]/', '', $hours_text));
            $category_name = trim($hrs_and_category[1]);
        }
        
        $minutes = $hours * 60;
        $total_minutes += $minutes;
        $total_hours += $hours;
        
        // Add to category data
        if (isset($category_data[$category_name])) {
            $category_data[$category_name]['courses'][] = $course;
            $category_data[$category_name]['total_minutes'] += $minutes;
            $category_data[$category_name]['total_hours'] += $hours;
            $category_data[$category_name]['count']++;
            $category_data[$category_name]['completed'] = true;
            $category_data[$category_name]['status'] = $category_data[$category_name]['count'] . '/1';
        }
        
        // Collect dates
        if (!empty($course->dateOfCourse)) {
            $dates[] = iipm_parse_cpd_date($course->dateOfCourse);
        }
    }
    
    // Calculate dates
    $start_date = null;
    $completion_date = null;
    
    if (!empty($dates)) {
        $start_date = date('Y-m-d', min($dates));
        $completion_date = date('Y-m-d', max($dates));
    }
    
    // Calculate completion percentage
    $completion_percentage = $target_minutes > 0 ? min(100, round(($total_minutes / $target_minutes) * 100, 1)) : 0;
    
    // Prepare courses summary
    $courses_summary = array();
    foreach ($category_data as $category_name => $data) {
        $courses_summary[] = array(
            'category' => $category_name,
            'count' => $data['count'],
            'total_hours' => $data['total_hours'],
            'total_minutes' => $data['total_minutes'],
            'credits' => $data['total_hours'],
            'required' => $data['required'],
            'completed' => $data['completed'],
            'status' => $data['status']
        );
    }
    
    return array(
        'total_cpd_minutes' => $total_minutes,
        'target_minutes' => $target_minutes,
        'completion_percentage' => $completion_percentage,
        'start_date' => $start_date,
        'completion_date' => $completion_date,
        'courses_summary' => $courses_summary,
        'total_hours' => $total_hours
    );
}

/**
 * Add CPD confirmation to fullcpd_confirmations table
 */
function iipm_add_cpd_confirmation($user_id, $course_id, $course_name, $course_category, $course_cpd_mins, $crs_provider) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'fullcpd_confirmations';
    
    // Calculate hours from minutes
    $hours = round($course_cpd_mins / 60, 1);
    
    // Format hrsAndCategory as "2hrs: Pensions"
    $hrs_and_category = $hours . 'hrs: ' . $course_category;
    
    $result = $wpdb->insert(
        $table_name,
        array(
            'user_id' => $user_id,
            'courseName' => $course_name,
            'courseType' => 'CPD',
            'hrsAndCategory' => $hrs_and_category,
            'dateOfCourse' => current_time('mysql'),
            'dateOfReturn' => null,
            'year' => date('Y'),
            'crs_provider' => $crs_provider,
            'course_id' => $course_id
        ),
        array('%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d')
    );
    
    return $result !== false;
}

/**
 * Complete CPD course by setting dateOfReturn
 */
function iipm_complete_cpd_course($user_id, $confirmation_id) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'fullcpd_confirmations';
    
    $result = $wpdb->update(
        $table_name,
        array(
            'dateOfReturn' => current_time('mysql')
        ),
        array(
            'id' => $confirmation_id,
            'user_id' => $user_id
        ),
        array('%s'),
        array('%d', '%d')
    );
    
    return $result !== false;
}

/**
 * Delete CPD confirmation
 */
function iipm_delete_cpd_confirmation($user_id, $confirmation_id) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'fullcpd_confirmations';
    
    $result = $wpdb->delete(
        $table_name,
        array(
            'id' => $confirmation_id,
            'user_id' => $user_id
        ),
        array('%d', '%d')
    );
    
    return $result !== false;
}

/**
 * Get completed courses history (past 5 years)
 */
function iipm_get_completed_courses_history($user_id) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'fullcpd_confirmations';
    
    // Get completed courses from the past 5 years
    $current_year = date('Y');
    $start_year = $current_year - 4; // Past 5 years including current year
    
    $query = $wpdb->prepare(
        "SELECT course_id, courseName, crs_provider, dateOfReturn, year 
         FROM {$table_name} 
         WHERE user_id = %d AND year >= %d AND dateOfReturn IS NOT NULL
         ORDER BY dateOfReturn DESC",
        $user_id,
        $start_year
    );
    
    $completed_courses = $wpdb->get_results($query);
    
    // Format the results
    $formatted_courses = array();
    foreach ($completed_courses as $course) {
        $formatted_courses[] = array(
            'course_id' => $course->course_id,
            'course_name' => $course->courseName,
            'crs_provider' => $course->crs_provider,
            'date_of_return' => $course->dateOfReturn,
            'year' => $course->year
        );
    }
    
    return $formatted_courses;
}

/**
 * Get currently started courses (in progress - added to learning path but not completed)
 */
function iipm_get_started_courses($user_id) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'fullcpd_confirmations';
    
    // Get started courses (courses added to learning path but not completed)
    $current_year = date('Y');
    
    $query = $wpdb->prepare(
        "SELECT course_id, courseName, crs_provider, dateOfCourse, year 
         FROM {$table_name} 
         WHERE user_id = %d AND year = %d AND dateOfReturn IS NULL
         ORDER BY dateOfCourse DESC",
        $user_id,
        $current_year
    );
    
    $started_courses = $wpdb->get_results($query);
    
    // Format the results
    $formatted_courses = array();
    foreach ($started_courses as $course) {
        $formatted_courses[] = array(
            'course_id' => $course->course_id,
            'course_name' => $course->courseName,
            'crs_provider' => $course->crs_provider,
            'date_of_course' => $course->dateOfCourse,
            'year' => $course->year
        );
    }
    
    return $formatted_courses;
}

/**
 * Get all courses in learning path (both completed and started)
 */
function iipm_get_courses_in_learning_path($user_id) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'fullcpd_confirmations';
    
    // Get all courses in learning path (both completed and started)
    $current_year = date('Y');
    $start_year = $current_year - 4; // Past 5 years including current year
    
    $query = $wpdb->prepare(
        "SELECT course_id, courseName, crs_provider, dateOfCourse, dateOfReturn, year 
         FROM {$table_name} 
         WHERE user_id = %d AND year >= %d
         ORDER BY dateOfCourse DESC",
        $user_id,
        $start_year
    );
    
    $courses = $wpdb->get_results($query);
    
    // Format the results
    $formatted_courses = array();
    foreach ($courses as $course) {
        $formatted_courses[] = array(
            'course_id' => $course->course_id,
            'course_name' => $course->courseName,
            'crs_provider' => $course->crs_provider,
            'date_of_course' => $course->dateOfCourse,
            'date_of_return' => $course->dateOfReturn,
            'year' => $course->year,
            'is_completed' => !empty($course->dateOfReturn)
        );
    }
    
    return $formatted_courses;
}

/**
 * Assign user to CPD by adding their ID to the User Ids Assigned field
 */
function iipm_assign_user_to_cpd($user_id) {
    global $wpdb;
    
    $cpd_types = iipm_get_cpd_types();
    if (empty($cpd_types)) {
        return false;
    }
    
    // Get the primary CPD type or first one
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
    
    if (!$primary_type) {
        return false;
    }
    
    // Get current assigned user IDs
    $current_assigned = $primary_type->{'User Ids Assigned'};
    $assigned_user_ids = array();
    
    if ($current_assigned) {
        // Parse the string array format like "['12', '13', '14']"
        $clean_string = trim($current_assigned, "[]");
        if (!empty($clean_string)) {
            $user_id_strings = explode(',', $clean_string);
            foreach ($user_id_strings as $user_id_string) {
                // Remove quotes and trim whitespace
                $clean_id = trim($user_id_string, " '\"");
                if (!empty($clean_id)) {
                    $assigned_user_ids[] = $clean_id;
                }
            }
        }
    }
    
    // Add current user if not already assigned
    if (!in_array(strval($user_id), $assigned_user_ids)) {
        $assigned_user_ids[] = strval($user_id);
    }
    
    // Update the CPD type with new assigned user IDs in the same string array format
    $table_name = $wpdb->prefix . 'cpd_types';
    $new_assigned_string = "['" . implode("', '", $assigned_user_ids) . "']";
    
    $result = $wpdb->update(
        $table_name,
        array('User Ids Assigned' => $new_assigned_string),
        array('id' => $primary_type->id),
        array('%s'),
        array('%d')
    );
    
    return $result !== false;
}

/**
 * Submit user CPD (placeholder function - can be extended with actual submission logic)
 */
function iipm_submit_user_cpd($user_id) {
    // For now, just return true as submission is successful
    // This can be extended to actually process the submission
    // e.g., mark as submitted, send notifications, etc.
    return true;
}

/**
 * Get recently logged training
 */
function iipm_get_recently_logged_training($user_id, $year) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'fullcpd_confirmations';
    
    $query = $wpdb->prepare(
        "SELECT * FROM {$table_name} 
         WHERE user_id = %d AND year = %d 
         ORDER BY dateOfCourse DESC",
        $user_id,
        $year
    );
    
    $training = $wpdb->get_results($query);
    
    return $training ?: array();
}

// Function iipm_get_cpd_compliance_stats() is already defined in cpd-reporting-functions.php

// Function iipm_get_compliance_data() is already defined in cpd-reporting-functions.php

// Function iipm_get_all_members_for_reports() is already defined in cpd-reporting-functions.php

// Function iipm_get_individual_report() is already defined in cpd-reporting-functions.php

// Functions iipm_export_report() and iipm_send_bulk_reminders() are already defined in cpd-reporting-functions.php