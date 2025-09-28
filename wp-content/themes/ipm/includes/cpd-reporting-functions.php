<?php
/**
 * CPD Reporting Functions
 * Handle compliance reporting and analytics
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ensure required database columns exist
 */
function iipm_ensure_cpd_reporting_columns() {
    global $wpdb;
    
    $members_table = $wpdb->prefix . 'test_iipm_members';
    
    // Check if cpd_prorata_adjustment column exists
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$members_table} LIKE 'cpd_prorata_adjustment'");
    
    if (empty($column_exists)) {
        // Add the missing column
        $wpdb->query("ALTER TABLE {$members_table} ADD COLUMN cpd_prorata_adjustment DECIMAL(5,2) DEFAULT 0.00 AFTER cpd_points_current");
    }
}

// Ensure columns exist when file is loaded
iipm_ensure_cpd_reporting_columns();

/**
 * Get CPD submissions for a specific year (helper function)
 */
function iipm_get_cpd_submissions_for_year($year = null) {
    if (!$year) {
        $year = date('Y');
    }
    
    global $wpdb;
    
    // Get all submissions for the year
    $submissions = $wpdb->get_results($wpdb->prepare("
        SELECT user_id, status, created_at, reviewed_at
        FROM {$wpdb->prefix}test_iipm_submissions 
        WHERE year = %s
    ", $year));
    
    // Create a lookup array by user_id
    $submission_lookup = array();
    foreach ($submissions as $submission) {
        $submission_lookup[$submission->user_id] = array(
            'status' => $submission->status,
            'created_at' => $submission->created_at,
            'reviewed_at' => $submission->reviewed_at
        );
    }
    
    return $submission_lookup;
}

/**
 * Get CPD compliance statistics - Returns only 4 summary values for stat cards
 */
function iipm_get_cpd_compliance_stats($year = null) {
    if (!$year) {
        $year = date('Y');
    }
    
    global $wpdb;
    
    // Get CPD types to determine required points and assigned users
    $cpd_types = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}cpd_types ORDER BY id ASC");
    $required_points = 8; // Default fallback
    $assigned_user_ids = array(); // Store assigned user IDs
    
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
            $required_points = intval($primary_type->{'Total Hours/Points Required'});
            
            // Get assigned user IDs from the primary CPD type
            if (!empty($primary_type->{'User Ids Assigned'})) {
                $assigned_string = $primary_type->{'User Ids Assigned'};
                error_log('🔍 Raw assigned string: ' . $assigned_string);
                
                // Remove brackets and parse the string
                $assigned_string = trim($assigned_string, '[]');
                
                // Split by comma and clean each value
                $assigned_array = explode(',', $assigned_string);
                $assigned_user_ids = array();
                
                foreach ($assigned_array as $user_id) {
                    $user_id = trim($user_id, " \t\n\r\0\x0B'\"");
                    if (is_numeric($user_id)) {
                        $assigned_user_ids[] = intval($user_id);
                    }
                }
                
                error_log('🔍 Parsed assigned user IDs (count: ' . count($assigned_user_ids) . '): ' . print_r($assigned_user_ids, true));
            }
        }
    }
    
    // Get all leave requests for the year once for efficiency
    $leave_requests_table = $wpdb->prefix . 'test_iipm_leave_requests';
    $all_leave_requests = $wpdb->get_results($wpdb->prepare("
        SELECT user_id, duration_days, status
        FROM {$leave_requests_table} 
        WHERE YEAR(leave_start_date) = %d AND status = 'approved'
    ", $year));
    
    // Group leave requests by user
    $user_leave_duration = array();
    foreach ($all_leave_requests as $leave) {
        $user_id = intval($leave->user_id);
        if (!isset($user_leave_duration[$user_id])) {
            $user_leave_duration[$user_id] = 0;
        }
        $user_leave_duration[$user_id] += intval($leave->duration_days);
    }
    
    // Get all active members
    $members_table = $wpdb->prefix . 'test_iipm_members';
    $cpd_table = $wpdb->prefix . 'fullcpd_confirmations';
    
    $total_members = $wpdb->get_var("
        SELECT COUNT(*) 
        FROM {$members_table} 
        WHERE membership_status = 'active'
    ");
    
    // Get members with their CPD progress from fullcpd_confirmations using hrsAndCategory parsing
    $member_courses = $wpdb->get_results($wpdb->prepare("
        SELECT 
            m.user_id,
            c.hrsAndCategory
        FROM {$members_table} m
        LEFT JOIN {$cpd_table} c ON m.user_id = c.user_id AND c.year = %d AND c.dateOfReturn IS NOT NULL
        WHERE m.membership_status = 'active'
    ", $year));
    
    // Group courses by user and process hrsAndCategory using the exact algorithm from CPD record API
    $user_stats = array();
    foreach ($member_courses as $course) {
        if (!isset($user_stats[$course->user_id])) {
            $user_stats[$course->user_id] = array(
                'total_hours' => 0,
                'courses' => array()
            );
        }
        
        if ($course->hrsAndCategory) {
            $user_stats[$course->user_id]['courses'][] = $course->hrsAndCategory;
        }
    }
    
    $compliant_members = 0;
    $at_risk_members = 0;
    $non_compliant_members = 0;
    $total_cpd_points = 0;
    $total_assigned_members = 0;
    
    // First, count total assigned members (only those who exist in wp_users and are active members)
    $total_assigned_members = 0;
    if (!empty($assigned_user_ids)) {
        $assigned_user_ids_str = implode(',', array_map('intval', $assigned_user_ids));
        $total_assigned_members = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT u.ID) 
            FROM {$wpdb->users} u
            INNER JOIN {$members_table} m ON u.ID = m.user_id
            WHERE u.ID IN ($assigned_user_ids_str) 
            AND m.membership_status = 'active'
        "));
    }
    error_log('🔍 Total assigned members in CPD type (active users only): ' . $total_assigned_members);
    
    foreach ($user_stats as $user_id => $user_data) {
        $total_hours = 0;
        
        foreach ($user_data['courses'] as $hrs_and_category) {
            // Parse hrsAndCategory field (format: "2hrs: Pensions") - EXACT ALGORITHM FROM CPD RECORD API
            $hrs_and_category_parts = explode(': ', $hrs_and_category, 2);
            $hours = 0;
            
            if (count($hrs_and_category_parts) >= 2) {
                // Extract hours from "2hrs" format
                $hours_text = trim($hrs_and_category_parts[0]);
                $hours = floatval(preg_replace('/[^0-9.]/', '', $hours_text));
            }
            
            $total_hours += $hours;
        }
        
        $earned = $total_hours;
        $total_cpd_points += $earned; // Add to total CPD points
        
        // Calculate adjusted target for this user based on their leave requests
        $user_leave_days = isset($user_leave_duration[$user_id]) ? $user_leave_duration[$user_id] : 0;
        $total_days_in_year = 365;
        if (date('L', mktime(0, 0, 0, 1, 1, $year))) {
            $total_days_in_year = 366; // Leap year
        }
        $adjusted_target = (($total_days_in_year - $user_leave_days) / $total_days_in_year) * $required_points;
        $adjusted_target = round($adjusted_target, 1);
        
        error_log('🔍 Member ID: ' . $user_id . ' earned points: ' . $earned . ', original target: ' . $required_points . ', adjusted target: ' . $adjusted_target . ', leave days: ' . $user_leave_days . ', is assigned: ' . (in_array($user_id, $assigned_user_ids) ? 'Yes' : 'No'));
        
        $progress_percentage = $adjusted_target > 0 ? round(min(100, ($earned / $adjusted_target) * 100), 2) : 0;
        
        if ($progress_percentage >= 100) {
            $compliant_members++;
        } elseif ($progress_percentage == 0) { // 0% = high risk (only for non-compliant members)
            $at_risk_members++;
            $non_compliant_members++;
        } else {
            $non_compliant_members++;
        }
    }
    
    // Calculate percentages
    $compliant_percentage = $total_members > 0 ? round(($compliant_members / $total_members) * 100, 1) : 0;
    $at_risk_percentage = $total_members > 0 ? round(($at_risk_members / $total_members) * 100, 1) : 0;
    $non_compliant_percentage = $total_members > 0 ? round(($non_compliant_members / $total_members) * 100, 1) : 0;
    
    // Calculate average CPD points for assigned members who have CPD data
    $assigned_members_with_cpd = 0;
    foreach ($user_stats as $user_id => $user_data) {
        if (in_array($user_id, $assigned_user_ids)) {
            $assigned_members_with_cpd++;
        }
    }
    
    $average_points = $assigned_members_with_cpd > 0 ? round($total_cpd_points / $assigned_members_with_cpd, 2) : 0;
    
    error_log('📊 Final stats - Total members: ' . $total_members . ', Assigned members: ' . $total_assigned_members . ', Assigned with CPD: ' . $assigned_members_with_cpd . ', Total CPD points: ' . $total_cpd_points . ', Average points: ' . $average_points);
    
    // Return summary values for stat cards and Quick Stats
    return array(
        'total_members' => intval($total_members),
        'compliant_members' => $compliant_members,
        'compliant_percentage' => $compliant_percentage,
        'at_risk_members' => $at_risk_members,
        'at_risk_percentage' => $at_risk_percentage,
        'non_compliant_members' => $non_compliant_members,
        'non_compliant_percentage' => $non_compliant_percentage,
        'average_points' => $average_points,
        'total_cpd_logged' => $total_assigned_members
    );
}

/**
 * Get detailed compliance data for reports
 */
function iipm_get_detailed_compliance_data($year = null, $type = null, $page = 1, $per_page = 20) {
    if (!$year) {
        $year = date('Y');
    }
    
    global $wpdb;
    
    // Get CPD submissions for the year
    $submission_lookup = iipm_get_cpd_submissions_for_year($year);
    
    // Get CPD types to determine required points
    $cpd_types = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}cpd_types ORDER BY id ASC");
    $required_points = 8; // Default fallback
    
    if (!empty($cpd_types)) {
        // Find CPD type by matching year with Start of logging date
        $primary_type = null;
        foreach ($cpd_types as $type_item) {
            $start_logging_year = date('Y', strtotime($type_item->{'Start of logging date'}));
            if ($start_logging_year == $year) {
                $primary_type = $type_item;
                break;
            }
        }
        
        if ($primary_type) {
            $required_points = intval($primary_type->{'Total Hours/Points Required'});
        }
    }
    
    // Get all leave requests for the year once for efficiency
    $leave_requests_table = $wpdb->prefix . 'test_iipm_leave_requests';
    $all_leave_requests = $wpdb->get_results($wpdb->prepare("
        SELECT user_id, duration_days, status
        FROM {$leave_requests_table} 
        WHERE YEAR(leave_start_date) = %d AND status = 'approved'
    ", $year));
    
    // Group leave requests by user
    $user_leave_duration = array();
    foreach ($all_leave_requests as $leave) {
        $user_id = intval($leave->user_id);
        if (!isset($user_leave_duration[$user_id])) {
            $user_leave_duration[$user_id] = 0;
        }
        $user_leave_duration[$user_id] += intval($leave->duration_days);
    }
    
    $members_table = $wpdb->prefix . 'test_iipm_members';
    $cpd_table = $wpdb->prefix . 'fullcpd_confirmations';
    $users_table = $wpdb->prefix . 'users';
    
    $offset = ($page - 1) * $per_page;
    
    // Build WHERE clause based on type
    $where_clause = "WHERE m.membership_status = 'active'";
    $order_clause = "ORDER BY u.display_name";
    
    // We'll filter after processing hrsAndCategory data since we need to parse the text
    $having_clause = "";
    
    // Get total count for the specific type - we'll calculate this after processing the data
    // since we need to apply the hrsAndCategory parsing logic
    $total_members = 0;
    
    // Get ALL member data first (no pagination in SQL)
    $sql = "SELECT 
                u.ID as user_id,
                u.display_name as name,
                u.user_email as email,
                m.membership_status,
                mp.theUsersStatus,
                mp.employer_id,
                c.hrsAndCategory
            FROM {$users_table} u
            LEFT JOIN {$members_table} m ON u.ID = m.user_id
            LEFT JOIN {$wpdb->prefix}test_iipm_member_profiles mp ON u.ID = mp.user_id
            LEFT JOIN {$cpd_table} c ON u.ID = c.user_id AND c.year = %d AND c.dateOfReturn IS NOT NULL
            {$where_clause}
            {$order_clause}";

    error_log($sql . ' ' . $year);
    
    $member_data = $wpdb->get_results($wpdb->prepare($sql, $year));
    
    $year_end = strtotime($year . '-12-31');
    $today = time();
    $days_left = max(0, ceil(($year_end - $today) / (60 * 60 * 24)));
    
    // Group courses by user_id and process hrsAndCategory using the exact algorithm from CPD record API
    $user_courses = array();
    foreach ($member_data as $member) {
        if (!isset($user_courses[$member->user_id])) {
            $user_courses[$member->user_id] = array(
                'user_id' => $member->user_id,
                'name' => $member->name ?: 'Unknown User',
                'email' => $member->email,
                'membership_status' => $member->membership_status,
                'theUsersStatus' => $member->theUsersStatus,
                'employer_id' => $member->employer_id,
                'courses' => array()
            );
        }
        
        if ($member->hrsAndCategory) {
            $user_courses[$member->user_id]['courses'][] = $member->hrsAndCategory;
        }
    }
    
    // Process all users first to determine compliance status
    $all_users = array();
    foreach ($user_courses as $user_id => $user_data) {
        // Process hrsAndCategory using the exact algorithm from iipm_get_cpd_stats
        $total_hours = 0;
        
        foreach ($user_data['courses'] as $hrs_and_category) {
            // Parse hrsAndCategory field (format: "2hrs: Pensions") - EXACT ALGORITHM FROM CPD RECORD API
            $hrs_and_category_parts = explode(': ', $hrs_and_category, 2);
            $hours = 0;
            
            if (count($hrs_and_category_parts) >= 2) {
                // Extract hours from "2hrs" format
                $hours_text = trim($hrs_and_category_parts[0]);
                $hours = floatval(preg_replace('/[^0-9.]/', '', $hours_text));
            }
            
            $total_hours += $hours;
        }
        
        // Convert to earned_points (matching CPD record API logic)
        $earned_points = $total_hours;
        
        // Calculate adjusted target for this user based on their leave requests
        $user_leave_days = isset($user_leave_duration[$user_data['user_id']]) ? $user_leave_duration[$user_data['user_id']] : 0;
        $total_days_in_year = 365;
        if (date('L', mktime(0, 0, 0, 1, 1, $year))) {
            $total_days_in_year = 366; // Leap year
        }
        $adjusted_target = (($total_days_in_year - $user_leave_days) / $total_days_in_year) * $required_points;
        $adjusted_target = round($adjusted_target, 1);
        
        $progress_percentage = $adjusted_target > 0 ? round(min(100, ($earned_points / $adjusted_target) * 100), 2) : 0;
        
        // Get role display name
        $role_display = 'Member';
        if ($user_data['theUsersStatus'] === 'Systems Admin') {
            $role_display = 'Systems Admin';
        } elseif ($user_data['theUsersStatus'] === 'EmployerContact') {
            $role_display = 'Employer Contact';
        } elseif ($user_data['theUsersStatus'] === 'Full Member') {
            $role_display = 'Full Member';
        } elseif ($user_data['theUsersStatus'] === 'Life Member') {
            $role_display = 'Life Member';
        } elseif ($user_data['theUsersStatus'] === 'QPT Member') {
            $role_display = 'QPT Member';
        }
        
        // Calculate high risk status in backend
        $is_high_risk = ($progress_percentage === 0) || 
                       (!$user_data['employer_id'] || $user_data['employer_id'] === '0') ||
                       ($earned_points === 0);
        
        $member_info = array(
            'user_id' => $user_id,
            'name' => $user_data['name'],
            'email' => $user_data['email'],
            'membership_level' => $user_data['membership_status'],
            'role' => $role_display,
            'employer_id' => $user_data['employer_id'],
            'earned_points' => $earned_points,
            'required_points' => $adjusted_target, // Use adjusted target
            'original_target' => $required_points, // Keep original for reference
            'shortage' => max(0, $adjusted_target - $earned_points), // Use adjusted target for shortage
            'progress_percentage' => $progress_percentage,
            'days_left' => $days_left,
            'compliance_status' => $earned_points >= $adjusted_target ? 'Yes' : 'No', // Use adjusted target
            'is_high_risk' => $is_high_risk, // High risk calculated in backend
            'submission_status' => isset($submission_lookup[$user_id]) ? $submission_lookup[$user_id]['status'] : null,
            'submission_created_at' => isset($submission_lookup[$user_id]) ? $submission_lookup[$user_id]['created_at'] : null,
            'submission_reviewed_at' => isset($submission_lookup[$user_id]) ? $submission_lookup[$user_id]['reviewed_at'] : null
        );
        
        $all_users[] = $member_info;
    }

    error_log(print_r("all_users: " . count($all_users), true));
    
    // Filter based on type (compliant vs non-compliant)
    $filtered_users = array();
    foreach ($all_users as $user) {
        $is_compliant = $user['progress_percentage'] >= 100;
        
        if ($type === 'compliant' && $is_compliant) {
            $filtered_users[] = $user;
        } elseif ($type === 'non_compliant' && !$is_compliant) {
            // Apply high-risk filter if requested for non-compliant
            $high_risk_only = isset($_POST['high_risk_only']) && $_POST['high_risk_only'] === '1';
            
            if (!$high_risk_only || $user['is_high_risk']) {
                $filtered_users[] = $user;
            }
        }
    }
    
    // Apply pagination to filtered results
    $total_members = count($filtered_users);
    $offset = ($page - 1) * $per_page;
    $members = array_slice($filtered_users, $offset, $per_page);
    
    $result = array(
        'members' => $members,
        'total_members' => $total_members,
        'total_pages' => ceil($total_members / $per_page),
        'current_page' => $page,
        'per_page' => $per_page
    );
    
    error_log('Pagination Debug - Type: ' . $type . ', Total Members: ' . $total_members . ', Members Count: ' . count($members) . ', Total Pages: ' . ceil($total_members / $per_page) . ', Current Page: ' . $page);
    
    return $result;
}

/**
 * Get CPD data by categories
 */
function iipm_get_cpd_by_categories($year = null) {
    if (!$year) {
        $year = date('Y');
    }
    
    global $wpdb;
    
    $categories_table = $wpdb->prefix . 'test_iipm_cpd_categories';
    $cpd_table = $wpdb->prefix . 'test_iipm_cpd_records';
    
    return $wpdb->get_results($wpdb->prepare("
        SELECT 
            cat.name as category_name,
            cat.min_points_required,
            COUNT(c.id) as total_entries,
            SUM(CASE WHEN c.status = 'approved' THEN c.cpd_points ELSE 0 END) as total_points,
            AVG(CASE WHEN c.status = 'approved' THEN c.cpd_points ELSE 0 END) as avg_points,
            COUNT(DISTINCT c.user_id) as unique_members
        FROM {$categories_table} cat
        LEFT JOIN {$cpd_table} c ON cat.id = c.category_id AND c.cpd_year = %d
        WHERE cat.is_active = 1
        GROUP BY cat.id
        ORDER BY cat.sort_order
    ", $year));
}

/**
 * AJAX handler for compliance data
 */
function iipm_handle_get_compliance_data() {
    error_log('🔍 iipm_handle_get_compliance_data called');
    error_log('📥 POST data: ' . print_r($_POST, true));
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'iipm_reports_nonce')) {
        error_log('❌ Nonce verification failed');
        wp_die('Security check failed');
    }
    
    if (!current_user_can('administrator')) {
        error_log('❌ Insufficient permissions');
        wp_send_json_error(array('message' => 'Insufficient permissions'));
    }
    
    $year = intval($_POST['year'] ?? date('Y'));
    $type = sanitize_text_field($_POST['type'] ?? '');
    $page = intval($_POST['page'] ?? 1);
    $high_risk_only = isset($_POST['high_risk_only']) && $_POST['high_risk_only'] === '1';
    
    error_log('📊 Processing request - Year: ' . $year . ', Type: ' . $type . ', Page: ' . $page);
    
    if ($type) {
        // Get specific type with pagination
        $data = iipm_get_detailed_compliance_data($year, $type, $page);
    } else {
        // Get all data (legacy mode) - get all members and categorize them
        $all_members = iipm_get_detailed_compliance_data($year, '', 1, 9999); // Get all members
        
        $compliant = array();
        $at_risk = array();
        $non_compliant = array();
        
        foreach ($all_members['members'] as $member) {
            if ($member['earned_points'] >= $member['required_points']) {
                $compliant[] = $member;
            } elseif ($member['progress_percentage'] >= 75) {
                $at_risk[] = $member;
            } else {
                $non_compliant[] = $member;
            }
        }
        
        $data = array(
            'compliant' => $compliant,
            'at_risk' => $at_risk,
            'non_compliant' => $non_compliant,
            'compliant_members' => count($compliant),
            'at_risk_members' => count($at_risk),
            'non_compliant_members' => count($non_compliant)
        );
        
        error_log('📊 Counts calculated - Compliant: ' . count($compliant) . ', At Risk: ' . count($at_risk) . ', Non-Compliant: ' . count($non_compliant));
    }
    
    error_log('📤 Sending response: ' . print_r($data, true));
    wp_send_json_success($data);
}

/**
 * AJAX handler for exporting reports
 */
function iipm_handle_export_report() {
    // Verify nonce
    if (!wp_verify_nonce($_GET['nonce'], 'iipm_reports_nonce')) {
        wp_die('Security check failed');
    }
    
    if (!current_user_can('administrator')) {
        wp_die('Insufficient permissions');
    }
    
    $type = sanitize_text_field($_GET['type']);
    $year = intval($_GET['year'] ?? date('Y'));
    
    switch ($type) {
        case 'compliance':
            iipm_export_compliance_report($year);
            break;
        case 'non-compliant':
            iipm_export_non_compliant_report($year);
            break;
        case 'all-members':
            iipm_export_all_members_report($year);
            break;
        case 'categories':
            iipm_export_compliance_report($year);
            break;
        default:
            wp_die('Invalid report type');
    }
}

/**
 * Export compliance report as CSV
 */
function iipm_export_compliance_report($year) {
    // Get ALL compliant members (no pagination for export)
    $data = iipm_get_detailed_compliance_data($year, 'compliant', 1, 999999);
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=cpd-compliance-report-' . $year . '.csv');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Create file handle
    $output = fopen('php://output', 'w');
    
    // Write CSV headers
    fputcsv($output, array(
        'Name',
        'Email',
        'Role',
        'Membership Level',
        'CPD Points Earned',
        'CPD Points Required',
        'Shortage',
        'Progress %',
        'Compliance Status',
        'Days Left'
    ));
    
    // Write compliant members data
    foreach ($data['members'] as $member) {
        fputcsv($output, array(
            $member['name'],
            $member['email'],
            $member['role'],
            $member['membership_level'],
            $member['earned_points'],
            $member['required_points'],
            $member['shortage'],
            $member['progress_percentage'] . '%',
            $member['compliance_status'],
            $member['days_left']
        ));
    }
    
    fclose($output);
    exit;
}

/**
 * Export non-compliant members only
 */
function iipm_export_non_compliant_report($year) {
    // Get ALL non-compliant members (no pagination for export)
    $data = iipm_get_detailed_compliance_data($year, 'non_compliant', 1, 999999);
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=non-compliant-members-' . $year . '.csv');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    fputcsv($output, array(
        'Name',
        'Email',
        'Role',
        'Membership Level',
        'CPD Points Earned',
        'CPD Points Required',
        'Shortage',
        'Progress %',
        'Compliance Status',
        'Days Left'
    ));
    
    foreach ($data['members'] as $member) {
        fputcsv($output, array(
            $member['name'],
            $member['email'],
            $member['role'],
            $member['membership_level'],
            $member['earned_points'],
            $member['required_points'],
            $member['shortage'],
            $member['progress_percentage'] . '%',
            $member['compliance_status'],
            $member['days_left']
        ));
    }
    
    fclose($output);
    exit;
}

/**
 * Export all members report as CSV
 */
function iipm_export_all_members_report($year) {
    // Get ALL members (both individual and employed) - no pagination for export
    $individual_data = iipm_get_all_members_with_progress($year, 'individual', 1, 999999);
    $employed_data = iipm_get_all_members_with_progress($year, 'employed', 1, 999999);
    
    // Combine both datasets
    $all_members = array_merge($individual_data['members'], $employed_data['members']);
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=all-members-report-' . $year . '.csv');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Create file handle
    $output = fopen('php://output', 'w');
    
    // Write CSV headers
    fputcsv($output, array(
        'Name',
        'Email',
        'Role',
        'Membership Level',
        'Member Type',
        'Employer ID',
        'CPD Points Earned',
        'CPD Points Required',
        'Shortage',
        'Progress %',
        'Compliance Status',
        'Days Left'
    ));
    
    // Write all members data
    foreach ($all_members as $member) {
        fputcsv($output, array(
            $member['name'],
            $member['email'],
            $member['role'],
            $member['membership_level'],
            $member['member_type'],
            $member['employer_id'],
            $member['earned_points'],
            $member['required_points'],
            $member['shortage'],
            $member['progress_percentage'] . '%',
            $member['compliance_status'],
            $member['days_left']
        ));
    }
    
    fclose($output);
    exit;
}

/**
 * Send bulk reminders to members
 */
function iipm_handle_send_bulk_reminders() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'iipm_reports_nonce')) {
        wp_die('Security check failed');
    }
    
    if (!current_user_can('administrator')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
    }
    
    $type = sanitize_text_field($_POST['type']);
    $year = intval($_POST['year'] ?? date('Y'));
    
    // Get all members data (no pagination, get all members)
    $data = iipm_get_detailed_compliance_data($year, '', 1, 999999);
    $all_members = $data['members'];
    
    $members_to_remind = array();
    
    switch ($type) {
        case 'non-compliant':
            // Filter non-compliant members (progress < 100%)
            foreach ($all_members as $member) {
                if ($member['progress_percentage'] < 100) {
                    $members_to_remind[] = $member;
                }
            }
            $subject = 'URGENT: CPD Compliance Required';
            break;
        case 'at-risk':
            // Filter at-risk members (progress >= 75% but < 100%)
            foreach ($all_members as $member) {
                if ($member['progress_percentage'] >= 75 && $member['progress_percentage'] < 100) {
                    $members_to_remind[] = $member;
                }
            }
            $subject = 'CPD Progress Reminder';
            break;
        case 'all':
            // Get all members
            $members_to_remind = $all_members;
            $subject = 'CPD Progress Update';
            break;
        default:
            wp_send_json_error(array('message' => 'Invalid reminder type'));
    }
    
    $sent_count = 0;
    
    foreach ($members_to_remind as $member) {
        $email_sent = iipm_send_cpd_reminder_email($member, $subject, $year);
        if ($email_sent) {
            $sent_count++;
        }
    }
    
    wp_send_json_success(array(
        'sent_count' => $sent_count,
        'total_members' => count($members_to_remind)
    ));
}

/**
 * Send individual CPD reminder email
 */
function iipm_send_cpd_reminder_email($member, $subject, $year) {
    $to = $member['email'];
    $headers = array('Content-Type: text/html; charset=UTF-8');
    
    // Determine if member is compliant (100% or more)
    $is_compliant = $member['progress_percentage'] >= 100;
    
    // Different content based on compliance status
    if ($is_compliant) {
        $main_message = "
        <p>Congratulations! You have successfully completed your CPD training for {$year}.</p>
        <p><strong>Please submit your CPD status and get your certificate to finalize your compliance.</strong></p>
        ";
        
        $action_text = "Submit CPD Status & Get Certificate";
        $action_url = home_url('/member-portal/');
        $button_color = "#10b981"; // Green for success
    } else {
        $shortage = $member['required_points'] - $member['earned_points'];
        $main_message = "
        <p><strong>Hurry up! Please pay attention to your CPD training.</strong></p>
        <p>You currently need <strong>{$shortage} more CPD points</strong> to meet your annual requirement for {$year}.</p>
        ";
        
        $action_text = "Log CPD Activities";
        $action_url = home_url('/member-portal/');
        $button_color = "#ef4444"; // Red for urgency
    }
    
    $message = "
    <html>
    <body style='font-family: Arial, sans-serif; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2 style='color: #667eea;'>CPD Progress Update - {$year}</h2>
            
            <p>Dear {$member['name']},</p>
            
            {$main_message}
            
            <div style='background: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                <h3 style='margin-top: 0; color: #374151;'>Your CPD Progress:</h3>
                <ul style='list-style: none; padding: 0;'>
                    <li><strong>Points Earned:</strong> {$member['earned_points']}</li>
                    <li><strong>Points Required:</strong> {$member['required_points']}</li>
                    <li><strong>Progress:</strong> {$member['progress_percentage']}%</li>
                </ul>
            </div>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$action_url}' style='background: {$button_color}; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold;'>
                    {$action_text}
                </a>
            </div>
            
            <p>If you have any questions about CPD requirements or need assistance, please contact us.</p>
            
            <p>Best regards,<br>IIPM Administration</p>
            
            <hr style='margin: 30px 0; border: none; border-top: 1px solid #e5e7eb;'>
            <p style='font-size: 12px; color: #6b7280;'>
                This email was sent from the IIPM Member Portal. 
                Days remaining in {$year}: {$member['days_left']}
            </p>
        </div>
    </body>
    </html>
    ";
    
    return wp_mail($to, $subject, $message, $headers);
}

/**
 * AJAX handler for sending individual CPD reminder
 */
function iipm_handle_send_individual_reminder() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'iipm_reports_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed'));
    }
    
    if (!current_user_can('administrator')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
    }
    
    $user_id = intval($_POST['user_id']);
    $year = intval($_POST['year'] ?? date('Y'));
    
    // Get individual member report data
    $report_data = iipm_get_individual_member_report($user_id, $year);
    
    if (!$report_data) {
        wp_send_json_error(array('message' => 'Member not found or not active'));
    }
    
    // Convert report data to member format for email function
    $member = array(
        'name' => $report_data['member']->display_name,
        'email' => $report_data['member']->user_email,
        'earned_points' => $report_data['total_earned'],
        'required_points' => $report_data['required_points'],
        'progress_percentage' => $report_data['progress_percentage'],
        'shortage' => max(0, $report_data['required_points'] - $report_data['total_earned']),
        'days_left' => 0 // Calculate days left if needed
    );
    
    // Send the reminder email
    $subject = 'CPD Progress Reminder';
    $email_sent = iipm_send_cpd_reminder_email($member, $subject, $year);
    
    if ($email_sent) {
        wp_send_json_success(array(
            'message' => 'Reminder sent successfully to ' . $member['name']
        ));
    } else {
        wp_send_json_error(array('message' => 'Failed to send reminder email'));
    }
}

/**
 * Get individual member CPD report data - Uses existing iipm_get_cpd_stats function
 */
function iipm_get_individual_member_report($user_id, $year = null) {
    if (!$year) {
        $year = date('Y');
    }
    
    global $wpdb;
    
    // Get member basic info - ensure member record exists and is active
    $member = $wpdb->get_row($wpdb->prepare("
        SELECT u.ID, u.display_name, u.user_email,
               m.membership_status, mp.theUsersStatus as role
        FROM {$wpdb->users} u
        INNER JOIN {$wpdb->prefix}test_iipm_members m ON u.ID = m.user_id
        LEFT JOIN {$wpdb->prefix}test_iipm_member_profiles mp ON u.ID = mp.user_id
        WHERE u.ID = %d AND m.membership_status = 'active'
    ", $user_id));
    
    if (!$member) {
        error_log("IIPM: Member not found or not active for user_id: " . $user_id);
        return false;
    }
    
    // Use existing iipm_get_cpd_stats function from cpd-record-api.php
    $cpd_stats = iipm_get_cpd_stats($user_id, $year);
    
    // Convert the stats to the format expected by the frontend
    $required_points = $cpd_stats['target_minutes'] / 60; // Convert minutes to hours
    $total_earned = $cpd_stats['total_hours'];
    $progress_percentage = $cpd_stats['completion_percentage'];
    
    // Initialize categories with course count requirements
    $categories = array(
        'Pensions' => array('name' => 'Pensions', 'required' => 1, 'completed' => 0, 'courses' => array()),
        'Savings & Investment' => array('name' => 'Savings & Investment', 'required' => 1, 'completed' => 0, 'courses' => array()),
        'Ethics' => array('name' => 'Ethics', 'required' => 1, 'completed' => 0, 'courses' => array()),
        'Life Assurance' => array('name' => 'Life Assurance', 'required' => 1, 'completed' => 0, 'courses' => array())
    );
    
    $all_courses = array();
    
    // Get detailed course information for the courses table
    $cpd_courses = $wpdb->get_results($wpdb->prepare("
        SELECT hrsAndCategory, dateOfReturn, courseName, courseName, crs_provider, courseType
        FROM {$wpdb->prefix}fullcpd_confirmations
        WHERE user_id = %d AND year = %d AND dateOfReturn IS NOT NULL
        ORDER BY dateOfReturn DESC
    ", $user_id, $year));
    
    // Process courses for detailed display
    foreach ($cpd_courses as $course) {
        if ($course->hrsAndCategory) {
            // Parse hrsAndCategory field (format: "2hrs: Pensions")
            $hrs_and_category_parts = explode(': ', $course->hrsAndCategory, 2);
            $hours = 0;
            $category = 'Other';
            
            if (count($hrs_and_category_parts) >= 2) {
                $hours_text = trim($hrs_and_category_parts[0]);
                $hours = floatval(preg_replace('/[^0-9.]/', '', $hours_text));
                $category = trim($hrs_and_category_parts[1]);
            }
            
            // Add to all courses list
            $all_courses[] = array(
                'title' => $course->courseName ?: 'Untitled Course',
                'provider' => $course->crs_provider ?: 'Unknown Provider',
                'hours' => $hours,
                'category' => $category,
                'date' => $course->dateOfReturn,
                'courseType' => $course->courseType ?: 'Unknown'
            );
            
            // Add to category courses and count
            if (isset($categories[$category])) {
                $categories[$category]['courses'][] = array(
                    'title' => $course->courseName ?: 'Untitled Course',
                    'provider' => $course->crs_provider ?: 'Unknown Provider',
                    'hours' => $hours,
                    'category' => $category,
                    'date' => $course->dateOfReturn,
                    'courseType' => $course->courseType ?: 'Unknown'
                );
                $categories[$category]['completed']++;
            }
            // Note: Courses that don't match the 4 main categories are not added to any category
            // They are still included in all_courses for the detailed table view
        }
    }
    
    return array(
        'member' => $member,
        'year' => $year,
        'total_earned' => $total_earned,
        'required_points' => $required_points,
        'progress_percentage' => $progress_percentage,
        'categories' => $categories,
        'all_courses' => $all_courses,
        'compliance_status' => $total_earned >= $required_points ? 'compliant' : ($total_earned == 0 ? 'at_risk' : 'non_compliant')
    );
}

/**
 * Get list of members for selection
 */
function iipm_get_members_list($search = '', $limit = 50) {
    global $wpdb;
    
    $where = '';
    $params = array();
    
    if (!empty($search)) {
        $where = "WHERE (u.display_name LIKE %s OR u.user_email LIKE %s OR u.first_name LIKE %s OR u.last_name LIKE %s) AND";
        $search_param = '%' . $wpdb->esc_like($search) . '%';
        $params = array($search_param, $search_param, $search_param, $search_param);
    } else {
        $where = "WHERE";
    }
    
    $sql = "SELECT u.ID, u.display_name, u.user_email, u.first_name, u.last_name, 
                   m.membership_level, m.membership_status
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->prefix}test_iipm_members m ON u.ID = m.user_id
            {$where} m.membership_status = 'active'
            ORDER BY u.display_name ASC
            LIMIT %d";
    
    $params[] = $limit;
    
    if (!empty($params)) {
        $results = $wpdb->get_results($wpdb->prepare($sql, $params));
    } else {
        $results = $wpdb->get_results($wpdb->prepare($sql, $limit));
    }
    
    return $results;
}

/**
 * AJAX handler for individual member report
 */
function iipm_handle_get_individual_report() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'iipm_reports_nonce')) {
        wp_die('Security check failed');
    }
    
    if (!current_user_can('administrator')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
    }
    
    $user_id = intval($_POST['user_id']);
    $year = intval($_POST['year'] ?? date('Y'));
    
    error_log("IIPM: Generating individual report for user_id: " . $user_id . ", year: " . $year);
    
    $report_data = iipm_get_individual_member_report($user_id, $year);
    
    if ($report_data) {
        error_log("IIPM: Report generated successfully for user_id: " . $user_id);
        wp_send_json_success($report_data);
    } else {
        error_log("IIPM: Failed to generate report for user_id: " . $user_id);
        wp_send_json_error(array('message' => 'Member not found or not active'));
    }
}

/**
 * AJAX handler for members search
 */
function iipm_handle_search_members() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'iipm_reports_nonce')) {
        wp_die('Security check failed');
    }
    
    if (!current_user_can('administrator')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
    }
    
    $search = sanitize_text_field($_POST['search'] ?? '');
    $members = iipm_get_members_list($search);
    
    wp_send_json_success($members);
}

/**
 * Get all members with their CPD progress for the reports table
 */
function iipm_get_all_members_with_progress($year = null, $report_type = 'employed', $page = 1, $per_page = 20) {
    if (!$year) {
        $year = date('Y');
    }
    
    global $wpdb;
    
    // Get CPD submissions for the year
    $submission_lookup = iipm_get_cpd_submissions_for_year($year);
    
    // Get CPD types to determine required points
    $cpd_types = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}cpd_types ORDER BY id ASC");
    $required_points = 8; // Default fallback
    
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
            $required_points = intval($primary_type->{'Total Hours/Points Required'});
        }
    }
    
    // Get all leave requests for the year once for efficiency
    $leave_requests_table = $wpdb->prefix . 'test_iipm_leave_requests';
    $all_leave_requests = $wpdb->get_results($wpdb->prepare("
        SELECT user_id, duration_days, status
        FROM {$leave_requests_table} 
        WHERE YEAR(leave_start_date) = %d AND status = 'approved'
    ", $year));
    
    // Group leave requests by user
    $user_leave_duration = array();
    foreach ($all_leave_requests as $leave) {
        $user_id = intval($leave->user_id);
        if (!isset($user_leave_duration[$user_id])) {
            $user_leave_duration[$user_id] = 0;
        }
        $user_leave_duration[$user_id] += intval($leave->duration_days);
    }
    
    $offset = ($page - 1) * $per_page;
    
    // Build WHERE clause based on report type
    $where_clause = "WHERE m.membership_status = 'active'";
    if ($report_type === 'individual') {
        $where_clause .= " AND (mp.employer_id IS NULL OR mp.employer_id = 0)";
        error_log('Member Details - Filtering for INDIVIDUAL members only (no employer)');
    } elseif ($report_type === 'employed') {
        $where_clause .= " AND mp.employer_id IS NOT NULL AND mp.employer_id != 0";
        error_log('Member Details - Filtering for EMPLOYED members only (has employer)');
    } else {
        error_log('Member Details - Showing ALL members (default)');
    }
    
    // We'll calculate total_members after processing all data
    
    // Get ALL members first (no pagination in SQL)
    $sql = "SELECT 
                u.ID, 
                u.display_name, 
                u.user_email,
                m.membership_status,
                mp.theUsersStatus,
                mp.employer_id,
                c.hrsAndCategory
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->prefix}test_iipm_members m ON u.ID = m.user_id
            LEFT JOIN {$wpdb->prefix}test_iipm_member_profiles mp ON u.ID = mp.user_id
            LEFT JOIN {$wpdb->prefix}fullcpd_confirmations c ON u.ID = c.user_id AND c.year = %d AND c.dateOfReturn IS NOT NULL
            {$where_clause}
            ORDER BY u.display_name ASC";
    
    $member_courses = $wpdb->get_results($wpdb->prepare($sql, $year));
    
    // Group courses by user and process hrsAndCategory using the exact algorithm from CPD record API
    $user_stats = array();
    foreach ($member_courses as $course) {
        if (!isset($user_stats[$course->ID])) {
            $user_stats[$course->ID] = array(
                'user_id' => $course->ID,
                'name' => $course->display_name,
                'email' => $course->user_email,
                'membership_status' => $course->membership_status,
                'theUsersStatus' => $course->theUsersStatus,
                'total_hours' => 0,
                'courses' => array()
            );
        }
        
        if ($course->hrsAndCategory) {
            $user_stats[$course->ID]['courses'][] = $course->hrsAndCategory;
        }
    }
    
    // Process each user's courses using the exact algorithm from CPD record API
    $members = array();
    foreach ($user_stats as $user_id => $user_data) {
        $total_hours = 0;
        
        foreach ($user_data['courses'] as $hrs_and_category) {
            // Parse hrsAndCategory field (format: "2hrs: Pensions") - EXACT ALGORITHM FROM CPD RECORD API
            $hrs_and_category_parts = explode(': ', $hrs_and_category, 2);
            $hours = 0;
            
            if (count($hrs_and_category_parts) >= 2) {
                // Extract hours from "2hrs" format
                $hours_text = trim($hrs_and_category_parts[0]);
                $hours = floatval(preg_replace('/[^0-9.]/', '', $hours_text));
            }
            
            $total_hours += $hours;
        }
        
        $earned = $total_hours;
        
        // Calculate adjusted target for this user based on their leave requests
        $user_leave_days = isset($user_leave_duration[$user_id]) ? $user_leave_duration[$user_id] : 0;
        $total_days_in_year = 365;
        if (date('L', mktime(0, 0, 0, 1, 1, $year))) {
            $total_days_in_year = 366; // Leap year
        }
        $adjusted_target = (($total_days_in_year - $user_leave_days) / $total_days_in_year) * $required_points;
        $adjusted_target = round($adjusted_target, 1);
        
        $user_data['earned_points'] = $earned;
        $user_data['required_points'] = $adjusted_target; // Use adjusted target
        $user_data['original_target'] = $required_points; // Keep original for reference
        
        // Calculate progress percentage
        if ($adjusted_target > 0) {
            $user_data['progress_percentage'] = round(min(100, ($earned / $adjusted_target) * 100), 2);
        } else {
            $user_data['progress_percentage'] = 100;
        }
        
        // Get role display name
        $role_display = 'Member';
        if ($user_data['theUsersStatus'] === 'Systems Admin') {
            $role_display = 'Systems Admin';
        } elseif ($user_data['theUsersStatus'] === 'EmployerContact') {
            $role_display = 'Employer Contact';
        } elseif ($user_data['theUsersStatus'] === 'Full Member') {
            $role_display = 'Full Member';
        } elseif ($user_data['theUsersStatus'] === 'Life Member') {
            $role_display = 'Life Member';
        } elseif ($user_data['theUsersStatus'] === 'QPT Member') {
            $role_display = 'QPT Member';
        }
        $user_data['role'] = $role_display;
        
        // Determine compliance status
        $user_data['compliance_status'] = $earned >= $adjusted_target ? 'Yes' : 'No';
        
        // Add submission status
        $user_data['submission_status'] = isset($submission_lookup[$user_id]) ? $submission_lookup[$user_id]['status'] : null;
        $user_data['submission_created_at'] = isset($submission_lookup[$user_id]) ? $submission_lookup[$user_id]['created_at'] : null;
        $user_data['submission_reviewed_at'] = isset($submission_lookup[$user_id]) ? $submission_lookup[$user_id]['reviewed_at'] : null;
        
        $members[] = $user_data;
    }
    
    // Apply pagination to processed results
    $total_members = count($members);
    $offset = ($page - 1) * $per_page;
    $paginated_members = array_slice($members, $offset, $per_page);
    
    return array(
        'members' => $paginated_members,
        'total_members' => $total_members,
        'total_pages' => ceil($total_members / $per_page),
        'current_page' => $page,
        'per_page' => $per_page
    );
}

/**
 * AJAX handler for getting all members with progress for reports table
 */
function iipm_handle_get_all_members_for_reports() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'iipm_reports_nonce')) {
        wp_die('Security check failed');
    }
    
    if (!current_user_can('administrator')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
    }
    
    $year = intval($_POST['year'] ?? date('Y'));
    $report_type = sanitize_text_field($_POST['report_type'] ?? 'employed');
    $page = intval($_POST['page'] ?? 1);
    
    error_log('Member Details Debug - Report Type: ' . $report_type . ', Year: ' . $year . ', Page: ' . $page);
    
    $result = iipm_get_all_members_with_progress($year, $report_type, $page);
    
    wp_send_json_success($result);
}

// Register AJAX handlers (for logged-in users only, since these require admin access)
add_action('wp_ajax_iipm_get_compliance_data', 'iipm_handle_get_compliance_data');
add_action('wp_ajax_iipm_export_report', 'iipm_handle_export_report');
add_action('wp_ajax_iipm_send_bulk_reminders', 'iipm_handle_send_bulk_reminders');
add_action('wp_ajax_iipm_send_individual_reminder', 'iipm_handle_send_individual_reminder');
add_action('wp_ajax_iipm_get_individual_report', 'iipm_handle_get_individual_report');
add_action('wp_ajax_iipm_send_individual_report_email', 'iipm_handle_send_individual_report_email');
add_action('wp_ajax_iipm_search_members', 'iipm_handle_search_members');
add_action('wp_ajax_iipm_get_all_members_for_reports', 'iipm_handle_get_all_members_for_reports');

// Add this new function at the end of the file
function iipm_handle_get_training_history() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'iipm_cpd_nonce')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    // Check user permissions
    if (!is_user_logged_in()) {
        wp_send_json_error('User not logged in');
        return;
    }
    
    $user_id = intval($_POST['user_id']);
    $current_user = wp_get_current_user();
    
    // Verify user can access this data
    if ($current_user->ID != $user_id && !current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    global $wpdb;
    
    try {
        // Get training records with category information
        $training_records = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                cr.*,
                cc.name as category_name,
                c.title as course_title,
                c.provider,
                c.lia_code
            FROM {$wpdb->prefix}test_iipm_cpd_records cr
            LEFT JOIN {$wpdb->prefix}test_iipm_cpd_categories cc ON cr.category_id = cc.id
            LEFT JOIN {$wpdb->prefix}test_iipm_cpd_courses c ON cr.course_id = c.id
            WHERE cr.user_id = %d
            ORDER BY cr.completion_date DESC, cr.created_at DESC",
            $user_id
        ));
        
        // Get category-based progress summary
        $current_year = date('Y');
        $category_summary = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                cc.name as category_name,
                cc.min_points_required as required_points,
                COALESCE(SUM(CASE WHEN cr.status = 'approved' AND cr.cpd_year = %d THEN cr.cpd_points ELSE 0 END), 0) as earned_points
            FROM {$wpdb->prefix}test_iipm_cpd_categories cc
            LEFT JOIN {$wpdb->prefix}test_iipm_cpd_records cr ON cc.id = cr.category_id AND cr.user_id = %d
            WHERE cc.is_active = 1 AND cc.is_mandatory = 1
            GROUP BY cc.id, cc.name, cc.min_points_required
            ORDER BY cc.sort_order ASC",
            $current_year,
            $user_id
        ), ARRAY_A);
        
        // Format category summary for frontend
        $categories = array();
        foreach ($category_summary as $category) {
            $categories[$category['category_name']] = array(
                'points' => intval($category['earned_points']),
                'required' => intval($category['required_points'])
            );
        }
        
        // Calculate total progress
        $total_points = array_sum(array_column($categories, 'points'));
        $total_required = array_sum(array_column($categories, 'required'));
        
        $response_data = array(
            'records' => $training_records,
            'summary' => array(
                'categories' => $categories,
                'total_points' => $total_points,
                'total_required' => $total_required,
                'total_records' => count($training_records)
            )
        );
        
        wp_send_json_success($response_data);
        
    } catch (Exception $e) {
        error_log('IIPM Training History Error: ' . $e->getMessage());
        wp_send_json_error('Failed to load training history');
    }
}

// Register the AJAX handler
add_action('wp_ajax_iipm_get_training_history', 'iipm_handle_get_training_history');
add_action('wp_ajax_nopriv_iipm_get_training_history', 'iipm_handle_get_training_history');

/**
 * AJAX handler for sending individual report email
 */
function iipm_handle_send_individual_report_email() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'iipm_reports_nonce')) {
        wp_die('Security check failed');
    }
    
    if (!current_user_can('administrator')) {
        wp_send_json_error(array('message' => 'Insufficient permissions'));
    }
    
    $user_id = intval($_POST['user_id']);
    $year = intval($_POST['year'] ?? date('Y'));
    $html_content = wp_unslash($_POST['html_content'] ?? '');
    
    error_log("IIPM: Sending individual report email for user_id: " . $user_id . ", year: " . $year);
    
    // Get member information for email
    $member = get_user_by('ID', $user_id);
    if (!$member) {
        wp_send_json_error(array('message' => 'Member not found'));
    }
    
    $member_email = $member->user_email;
    $member_name = $member->display_name;
    
    if (empty($html_content)) {
        wp_send_json_error(array('message' => 'No HTML content provided'));
    }
    
    // Wrap the HTML content in a proper email structure
    $email_html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>CPD Report - ' . esc_html($member_name) . '</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #374151; max-width: 800px; margin: 0 auto; padding: 20px; }
        </style>
    </head>
    <body>
        <div style="background: white; border-radius: 12px; padding: 30px; border: 1px solid #e5e7eb;">
            <h2 style="color: #374151; margin-bottom: 30px;">📊 Individual Member CPD Report - ' . esc_html($member_name) . '</h2>
            ' . $html_content . '
        </div>
    </body>
    </html>';
    
    // Send email
    $subject = 'CPD Report - ' . $member_name . ' (' . $year . ')';
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: IIPM Portal <' . (defined('SMTP_FROM') ? SMTP_FROM : get_option('admin_email')) . '>'
    );
    
    $sent = wp_mail($member_email, $subject, $email_html, $headers);
    
    if ($sent) {
        error_log("IIPM: Individual report email sent successfully to: " . $member_email);
        wp_send_json_success(array('message' => 'Report sent successfully'));
    } else {
        error_log("IIPM: Failed to send individual report email to: " . $member_email);
        wp_send_json_error(array('message' => 'Failed to send email'));
    }
} 