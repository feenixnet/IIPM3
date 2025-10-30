<?php
// Prevent direct access
if (!defined('ABSPATH')) { exit; }

// User submission functions
add_action('wp_ajax_iipm_submission_save', 'iipm_submission_save');

function iipm_submission_save() {
    global $wpdb; 
    $table = $wpdb->prefix . 'test_iipm_submissions';
    $certificates_table = $wpdb->prefix . 'test_iipm_certifications';
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $uid = get_current_user_id();
    $year = sanitize_text_field($_POST['year'] ?? date('Y'));
    $details = sanitize_text_field($_POST['details'] ?? '{}');
    
    // Find certificate for the submission year
    $certificate_id = 0;
    $certificate = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM $certificates_table WHERE year = %s LIMIT 1",
        $year
    ));
    
    if ($certificate) {
        $certificate_id = $certificate->id;
    }
    
    $data = array(
        'user_id' => $uid,
        'year' => $year,
        'details' => $details,
        'certificate_id' => $certificate_id, // Auto-assign certificate
        'reviewed_by' => 0, // System auto-approval
        'reviewed_at' => current_time('mysql')
    );

    if($id > 0) {
        $result = $wpdb->update($table, $data, array('id' => $id));
    } else {
        $result = $wpdb->insert($table, $data);
        if ($result !== false) { $id = intval($wpdb->insert_id); }
    }

    error_log("WHAHAHAHAHA", $result);

    if ($result === false) { wp_send_json_error('Database error saving submission'); }

    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    wp_send_json_success($row);
}

// Admin functions for CPD submission management
add_action('wp_ajax_iipm_get_admin_submissions', 'iipm_get_admin_submissions');
add_action('wp_ajax_iipm_update_submission_status', 'iipm_update_submission_status');
add_action('wp_ajax_iipm_assign_certificate', 'iipm_assign_certificate');
add_action('wp_ajax_iipm_get_available_certificates', 'iipm_get_available_certificates');
add_action('wp_ajax_iipm_remove_certificate', 'iipm_remove_certificate');
add_action('wp_ajax_iipm_get_user_submission_status', 'iipm_get_user_submission_status');
add_action('wp_ajax_iipm_download_certificate', 'iipm_download_certificate');
add_action('wp_ajax_iipm_download_certificate_direct', 'iipm_download_certificate_direct');
add_action('wp_ajax_nopriv_iipm_download_certificate_direct', 'iipm_download_certificate_direct');

/**
 * Get CPD submissions for admin with filtering and pagination
 */
function iipm_get_admin_submissions() {
    // Check admin permissions
    if (!current_user_can('manage_iipm_members') && !current_user_can('administrator')) {
        wp_send_json_error('Insufficient permissions');
    }

    global $wpdb;
    
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 10;
    $year = isset($_POST['year']) ? sanitize_text_field($_POST['year']) : '';
    $user_search = isset($_POST['user_search']) ? sanitize_text_field($_POST['user_search']) : '';
    
    $offset = ($page - 1) * $per_page;
    
    // Build WHERE clause
    $where_conditions = array();
    $where_params = array();
    
    if (!empty($year)) {
        $where_conditions[] = "s.year = %s";
        $where_params[] = $year;
    }
    
    if (!empty($user_search)) {
        $where_conditions[] = "(u.display_name LIKE %s OR u.user_login LIKE %s OR u.user_email LIKE %s)";
        $search_term = '%' . $wpdb->esc_like($user_search) . '%';
        $where_params[] = $search_term;
        $where_params[] = $search_term;
        $where_params[] = $search_term;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Get total count
    $count_sql = "SELECT COUNT(*) 
                  FROM {$wpdb->prefix}test_iipm_submissions s
                  LEFT JOIN {$wpdb->prefix}users u ON s.user_id = u.ID
                  LEFT JOIN {$wpdb->prefix}test_iipm_certifications c ON s.certificate_id = c.id
                  $where_clause";
    
    if (!empty($where_params)) {
        $total = $wpdb->get_var($wpdb->prepare($count_sql, $where_params));
    } else {
        $total = $wpdb->get_var($count_sql);
    }
    
    // Get submissions with user info, details, and certificate information
    $sql = "SELECT s.*, u.display_name, u.user_login, u.user_email, c.name as certificate_name, c.year as certificate_year
            FROM {$wpdb->prefix}test_iipm_submissions s
            LEFT JOIN {$wpdb->prefix}users u ON s.user_id = u.ID
            LEFT JOIN {$wpdb->prefix}test_iipm_certifications c ON s.certificate_id = c.id
            $where_clause
            ORDER BY s.id DESC
            LIMIT %d OFFSET %d";
    
    $query_params = array_merge($where_params, array($per_page, $offset));
    $submissions = $wpdb->get_results($wpdb->prepare($sql, $query_params));
    
    // Decode details for each submission
    foreach ($submissions as $submission) {
        $details_string = $submission->details;
        
        // Handle double-encoded JSON strings
        // First, try direct JSON decode
        $submission->details_decoded = json_decode($details_string, true);
        
        // If that fails, try to handle double-encoded strings
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Remove outer quotes if present
            if (strpos($details_string, '"') === 0 && substr($details_string, -1) === '"') {
                $details_string = substr($details_string, 1, -1);
            }
            
            // Unescape the JSON string
            $details_string = stripslashes($details_string);
            
            // Try to decode again
            $submission->details_decoded = json_decode($details_string, true);
            
            // If still fails, set empty array
            if (json_last_error() !== JSON_ERROR_NONE) {
                $submission->details_decoded = array();
            }
        }
    }
    
    // Calculate pagination info
    $total_pages = ceil($total / $per_page);
    
    wp_send_json_success(array(
        'submissions' => $submissions,
        'pagination' => array(
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_items' => $total,
            'per_page' => $per_page
        )
    ));
}

/**
 * Update submission admin notes (submissions are now auto-approved)
 */
function iipm_update_submission_status() {
    // Check admin permissions
    if (!current_user_can('manage_iipm_members') && !current_user_can('administrator')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    global $wpdb;
    
    $submission_id = isset($_POST['submission_id']) ? intval($_POST['submission_id']) : 0;
    $admin_notes = isset($_POST['admin_notes']) ? sanitize_textarea_field($_POST['admin_notes']) : '';
    
    $table = $wpdb->prefix . 'test_iipm_submissions';
    
    // Only allow updating admin notes, submissions are auto-approved
    $update_data = array(
        'admin_notes' => $admin_notes,
        'reviewed_by' => get_current_user_id(),
        'reviewed_at' => current_time('mysql')
    );
    
    $result = $wpdb->update(
        $table,
        $update_data,
        array('id' => $submission_id),
        array('%s', '%d', '%s'),
        array('%d')
    );
    
    if ($result === false) {
        wp_send_json_error('Failed to update submission notes');
    }
    
    // Get updated submission
    $submission = $wpdb->get_row($wpdb->prepare(
        "SELECT s.*, u.display_name, u.user_login, u.user_email
         FROM $table s
         LEFT JOIN {$wpdb->prefix}users u ON s.user_id = u.ID
         WHERE s.id = %d",
        $submission_id
    ));
    
    wp_send_json_success(array(
        'submission' => $submission,
        'message' => 'Submission notes updated successfully'
    ));
}


/**
 * Get available years for filtering
 */
function iipm_get_submission_years() {
    global $wpdb;
    
    $years = $wpdb->get_col(
        "SELECT DISTINCT year 
         FROM {$wpdb->prefix}test_iipm_submissions 
         ORDER BY year DESC"
    );
    
    return $years;
}

/**
 * Get available certificates for assignment
 */
function iipm_get_available_certificates() {
    // Check admin permissions
    if (!current_user_can('manage_iipm_members') && !current_user_can('administrator')) {
        wp_send_json_error('Insufficient permissions');
    }

    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'iipm_portal_nonce')) {
        wp_send_json_error('Invalid nonce');
    }

    global $wpdb;
    $certificates_table = $wpdb->prefix . 'test_iipm_certifications';
    
    // Get all certificates
    $certificates = $wpdb->get_results("SELECT id, name, year FROM $certificates_table ORDER BY year DESC, name ASC");
    
    wp_send_json_success($certificates);
}

/**
 * Assign certificate to approved submission
 */
function iipm_assign_certificate() {
    // Check admin permissions
    if (!current_user_can('manage_iipm_members') && !current_user_can('administrator')) {
        wp_send_json_error('Insufficient permissions');
    }

    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'iipm_portal_nonce')) {
        wp_send_json_error('Invalid nonce');
    }

    $submission_id = intval($_POST['submission_id'] ?? 0);
    $certificate_id = intval($_POST['certificate_id'] ?? 0);

    // if ($submission_id <= 0 || $certificate_id <= 0) {
    //     wp_send_json_error('Invalid submission or certificate ID');
    // }

    global $wpdb;
    $submissions_table = $wpdb->prefix . 'test_iipm_submissions';
    $certificates_table = $wpdb->prefix . 'test_iipm_certifications';

    // Check if submission exists (submissions are now auto-approved)
    $submission = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $submissions_table WHERE id = %d",
        $submission_id
    ));

    if (!$submission) {
        wp_send_json_error('Submission not found');
    }

    // Check if certificate exists
    $certificate = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $certificates_table WHERE id = %d",
        $certificate_id
    ));

    if (!$certificate) {
        wp_send_json_error('Certificate not found');
    }

    // Update submission with certificate_id
    $result = $wpdb->update(
        $submissions_table,
        array('certificate_id' => $certificate_id),
        array('id' => $submission_id),
        array('%d'),
        array('%d')
    );

    if ($result === false) {
        wp_send_json_error('Failed to assign certificate');
    }

    wp_send_json_success(array(
        'message' => 'Certificate assigned successfully',
        'certificate_name' => $certificate->name,
        'certificate_year' => $certificate->year
    ));
}

/**
 * Remove certificate from submission
 */
function iipm_remove_certificate() {
    // Check admin permissions
    if (!current_user_can('manage_iipm_members') && !current_user_can('administrator')) {
        wp_send_json_error('Insufficient permissions');
    }

    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'iipm_portal_nonce')) {
        wp_send_json_error('Invalid nonce');
    }

    $submission_id = intval($_POST['submission_id'] ?? 0);

    // if ($submission_id <= 0) {
    //     wp_send_json_error('Invalid submission ID');
    // }

    global $wpdb;
    $submissions_table = $wpdb->prefix . 'test_iipm_submissions';

    // Check if submission exists
    $submission = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $submissions_table WHERE id = %d",
        $submission_id
    ));

    if (!$submission) {
        wp_send_json_error('Submission not found');
    }

    // Update submission to remove certificate_id
    $result = $wpdb->update(
        $submissions_table,
        array('certificate_id' => null),
        array('id' => $submission_id),
        array('%s'),
        array('%d')
    );

    if ($result === false) {
        wp_send_json_error('Failed to remove certificate');
    }

    wp_send_json_success(array(
        'message' => 'Certificate removed successfully'
    ));
}

/**
 * Get user submission status with certificate information
 */
function iipm_get_user_submission_status() {
    if (!is_user_logged_in()) {
        wp_send_json_error('User not logged in');
    }

    $user_id = get_current_user_id();
    $year = isset($_POST['year']) ? sanitize_text_field($_POST['year']) : date('Y');

    global $wpdb;
    $submissions_table = $wpdb->prefix . 'test_iipm_submissions';
    $certificates_table = $wpdb->prefix . 'test_iipm_certifications';

    // Get user's submission with certificate information
    $submission = $wpdb->get_row($wpdb->prepare(
        "SELECT s.*, c.name as certificate_name, c.year as certificate_year, c.description as certificate_description
         FROM $submissions_table s
         LEFT JOIN $certificates_table c ON s.certificate_id = c.id
         WHERE s.user_id = %d AND s.year = %s
         ORDER BY s.created_at DESC
         LIMIT 1",
        $user_id,
        $year
    ));

    if (!$submission) {
        wp_send_json_error('No submission found');
    }

    // Get user information
    $user = get_userdata($user_id);
    $user_meta = get_user_meta($user_id);

    // Get user contact information
    $contact_address = '';
    if (isset($user_meta['contact_address'][0])) {
        $contact_address = $user_meta['contact_address'][0];
    }

    $result = array(
        'submission' => array(
            'id' => $submission->id,
            'status' => $submission->status,
            'year' => $submission->year,
            'created_at' => $submission->created_at,
            'reviewed_at' => $submission->reviewed_at,
            'admin_notes' => $submission->admin_notes
        ),
        'user' => array(
            'name' => $user->display_name,
            'email' => $user->user_email,
            'contact_address' => $contact_address
        ),
        'certificate' => null
    );

    // Add certificate information if exists
    if ($submission->certificate_id && $submission->certificate_name) {
        $result['certificate'] = array(
            'id' => $submission->certificate_id,
            'name' => $submission->certificate_name,
            'year' => $submission->certificate_year,
            'description' => $submission->certificate_description
        );
    }

    // Get completed courses for the user in the submission year
    $cpd_records_table = $wpdb->prefix . 'test_iipm_cpd_records';
    $courses_table = $wpdb->prefix . 'test_iipm_courses';
    $categories_table = $wpdb->prefix . 'test_iipm_cpd_categories';

    $completed_courses = $wpdb->get_results($wpdb->prepare(
        "SELECT c.course_name, c.course_cpd_mins, c.crs_provider, cat.name as course_category
         FROM $cpd_records_table r
         LEFT JOIN $courses_table c ON r.course_id = c.id
         LEFT JOIN $categories_table cat ON c.course_category = cat.id
         WHERE r.user_id = %d AND r.cpd_year = %s AND r.status = 'approved'
         ORDER BY r.created_at DESC",
        $user_id,
        $year
    ));

    $result['completed_courses'] = $completed_courses;

    // Get recent training data with start/end dates
    $recent_training = $wpdb->get_results($wpdb->prepare(
        "SELECT c.course_name, c.course_cpd_mins, c.crs_provider, cat.name as course_category,
                r.start_date, r.end_date, r.created_at
         FROM $cpd_records_table r
         LEFT JOIN $courses_table c ON r.course_id = c.id
         LEFT JOIN $categories_table cat ON c.course_category = cat.id
         WHERE r.user_id = %d AND r.cpd_year = %s AND r.status = 'approved'
         ORDER BY r.created_at DESC",
        $user_id,
        $year
    ));

    $result['recent_training'] = $recent_training;

    wp_send_json_success($result);
}

/**
 * Download certificate PDF - Direct URL method (like CSV export)
 */
function iipm_download_certificate_direct() {
    if (!is_user_logged_in()) {
        wp_die('User not logged in');
    }

    // Get parameters from URL
    $certificate_id = intval($_GET['certificate_id'] ?? 0);
    $user_name = sanitize_text_field($_GET['user_name'] ?? '');
    $user_email = sanitize_email($_GET['user_email'] ?? '');
    $contact_address = sanitize_textarea_field($_GET['contact_address'] ?? '');
    $submission_year = sanitize_text_field($_GET['submission_year'] ?? date('Y'));
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : get_current_user_id();

    if ($certificate_id <= 0) {
        wp_die('Invalid certificate ID');
    }

    global $wpdb;
    $certificates_table = $wpdb->prefix . 'test_iipm_certifications';

    // Get certificate details
    $certificate = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $certificates_table WHERE id = %d",
        $certificate_id
    ));

    if (!$certificate) {
        wp_die('Certificate not found');
    }
    
    // Debug: Log certificate data
    error_log('Certificate data: ' . print_r($certificate, true));
    error_log('Avatar URL field: ' . ($certificate->avatar_url ?? 'NOT SET'));

    // Get CPD stats for this user and year and generate PDF content
    if (!function_exists('iipm_get_cpd_stats')) {
        require_once(ABSPATH . 'wp-content/themes/ipm/includes/cpd-record-api.php');
    }
    $cpd_stats = iipm_get_cpd_stats($user_id, intval($submission_year));

    $pdf_content = generate_certificate_pdf($cpd_stats, $certificate, $user_name, $user_email, $contact_address, $submission_year);
    
    // Set headers for PDF download (like CSV export)
    $filename = 'CPD_Certificate_' . $user_name . '_' . $submission_year . '.pdf';
    $filename = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $filename);
    
    // Clear any existing output
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdf_content));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    header('Expires: 0');
    
    // Use fopen/fwrite like CSV export instead of echo
    $output = fopen('php://output', 'w');
    fwrite($output, $pdf_content);
    fclose($output);
    exit;
}

/**
 * Generate certificate PDF content using TCPDF
 */
function generate_certificate_pdf($cpd_stats, $certificate, $user_name, $user_email, $contact_address, $submission_year) {
    // Check if TCPDF is available
    if (!class_exists('TCPDF')) {
        // Fallback to simple PDF if TCPDF is not available
        return generate_simple_pdf($certificate, $user_name, $user_email, $contact_address, $submission_year);
    }

    // Create new PDF (Portrait A4 for report layout)
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(14, 14, 14);
    // Enable auto page breaks and keep a bottom margin to avoid row cuts
    $pdf->SetAutoPageBreak(true, 14);
    $pdf->AddPage();

    // Header with logo and title block
    $logo_path = ABSPATH . 'wp-content/uploads/2025/05/logo-1.jpg';
    $logo_html = '';
    if (file_exists($logo_path)) {
        try {
            $image_data = file_get_contents($logo_path);
            $base64_image = base64_encode($image_data);
            $logo_html = '<img src="data:image/jpeg;base64,' . $base64_image . '" style="height:44px; vertical-align:middle;" />';
        } catch (Exception $e) { /* ignore */ }
    }

    $pdf->SetFont('helvetica', '', 11);
    $is_current_period = (strval(date('Y')) === strval($submission_year));

    // First table: stats summary across categories
    $categories_order = array('Savings & Investments', 'Pensions', 'Ethics', 'Life Assurance');
    $cat_hours = array(
        'Savings & Investments' => 0,
        'Pensions' => 0,
        'Ethics' => 0,
        'Life Assurance' => 0
    );
    if (!empty($cpd_stats['courses_summary'])) {
        foreach ($cpd_stats['courses_summary'] as $row) {
            $name = $row['category'];
            if (isset($cat_hours[$name])) {
                $cat_hours[$name] = floatval($row['total_hours']);
            }
        }
    }
    $completed_total = array_sum($cat_hours);

    // Virtual Pro Rata Deduction row
    $pro_rata_total = 6; // as requested

    // Required per category (>=1 each); total equals target hours
    $required_total = isset($cpd_stats['target_minutes']) ? (floatval($cpd_stats['target_minutes']) / 60.0) : 8.0;

    // Requirement met checks
    $checks = array();
    foreach ($categories_order as $label) { $checks[$label] = ($cat_hours[$label] >= 1); }
    $total_ok = ($completed_total >= $required_total);

    // Load check/cross images and convert to base64
    $checkmark_path = get_template_directory() . '/assets/img/check.jpg';
    $closemark_path = get_template_directory() . '/assets/img/close.jpg';
    
    $checkmark_img = '';
    $closemark_img = '';
    
    if (file_exists($checkmark_path)) {
        $checkmark_data = file_get_contents($checkmark_path);
        $checkmark_base64 = base64_encode($checkmark_data);
        $checkmark_img = '<img src="data:image/jpeg;base64,' . $checkmark_base64 . '" style="height:16px;" />';
    }
    
    if (file_exists($closemark_path)) {
        $closemark_data = file_get_contents($closemark_path);
        $closemark_base64 = base64_encode($closemark_data);
        $closemark_img = '<img src="data:image/jpeg;base64,' . $closemark_base64 . '" style="height:16px;" />';
    }
    
    // Prepare check/cross symbols using images
    $check_si = $checks['Savings & Investments'] ? $checkmark_img : $closemark_img;
    $check_pensions = $checks['Pensions'] ? $checkmark_img : $closemark_img;
    $check_ethics = $checks['Ethics'] ? $checkmark_img : $closemark_img;
    $check_life = $checks['Life Assurance'] ? $checkmark_img : $closemark_img;
    $check_total = $total_ok ? $checkmark_img : $closemark_img;
    
    $completed_si = number_format($cat_hours['Savings & Investments'], 0);
    $completed_pensions = number_format($cat_hours['Pensions'], 0);
    $completed_ethics = number_format($cat_hours['Ethics'], 0);
    $completed_life = number_format($cat_hours['Life Assurance'], 0);
    $completed_total_fmt = number_format($completed_total, 0);
    $required_total_fmt = number_format($required_total, 0);
    $pro_rata_total_fmt = number_format($pro_rata_total, 0);
    
    $user_name_safe = htmlspecialchars($user_name, ENT_QUOTES);
    $year_safe = esc_html($submission_year);
    $period_html = $is_current_period ? '<span style="font-size:12px;">(Current Period)</span>' : '';

    // Build second table: completed courses details
    $courses_rows = '';
    if (!empty($cpd_stats['all_courses'])) {
        $row_index = 0;
        foreach ($cpd_stats['all_courses'] as $course) {
            $row_index++;
            $row_bg = ($row_index % 2 === 1) ? '#f9f9f9' : '#ffffff';
            
            $course_name = isset($course['courseName']) ? htmlspecialchars($course['courseName'], ENT_QUOTES) : 'N/A';
            $date_of_course = isset($course['dateOfCourse']) ? htmlspecialchars($course['dateOfCourse'], ENT_QUOTES) : 'N/A';
            $course_type = isset($course['courseType']) ? htmlspecialchars($course['courseType'], ENT_QUOTES) : 'N/A';
            $hours_gained = isset($course['hours']) ? number_format($course['hours'], 1) : '0.0';
            $provider = isset($course['crs_provider']) ? htmlspecialchars($course['crs_provider'], ENT_QUOTES) : 'N/A';
            $date_added = isset($course['dateOfReturn']) ? htmlspecialchars($course['dateOfReturn'], ENT_QUOTES) : 'N/A';
            
            $courses_rows .= <<<ROW
        <tr nobr="true">
            <td bgcolor="{$row_bg}" color="#555555" align="left" style="width:25%;padding:14px 10px;">{$course_name}</td>
            <td bgcolor="{$row_bg}" color="#555555" align="center" style="width:15%;padding:14px 10px;">{$date_of_course}</td>
            <td bgcolor="{$row_bg}" color="#555555" align="center" style="width:15%;padding:14px 10px;">{$course_type}</td>
            <td bgcolor="{$row_bg}" color="#555555" align="center" style="width:15%;padding:14px 10px;">{$hours_gained}</td>
            <td bgcolor="{$row_bg}" color="#555555" align="center" style="width:15%;padding:14px 10px;">{$provider}</td>
            <td bgcolor="{$row_bg}" color="#555555" align="center" style="width:15%;padding:14px 10px;">{$date_added}</td>
        </tr>

ROW;
        }
    } else {
        $courses_rows = '<tr><td colspan="7" align="center" color="#555555">No completed courses found</td></tr>';
    }
    
    $courses_table_html = <<<HTML
<table cellspacing="0" cellpadding="14" style="width:100%;border-color:#c2c2c2;margin-top:10px;">
    <thead>
        <tr>
            <th bgcolor="#5b2c6f" color="#ffffff" align="center" style="width:25%;border-color:#c2c2c2;padding:14px 10px;"><b>Course Name</b></th>
            <th bgcolor="#5b2c6f" color="#ffffff" align="center" style="width:15%;border-color:#c2c2c2;padding:14px 10px;"><b>Course Date</b></th>
            <th bgcolor="#5b2c6f" color="#ffffff" align="center" style="width:15%;border-color:#c2c2c2;padding:14px 10px;"><b>Category</b></th>
            <th bgcolor="#5b2c6f" color="#ffffff" align="center" style="width:15%;border-color:#c2c2c2;padding:14px 10px;"><b>Hours Gained</b></th>
            <th bgcolor="#5b2c6f" color="#ffffff" align="center" style="width:15%;border-color:#c2c2c2;padding:14px 10px;"><b>Provider</b></th>
            <th bgcolor="#5b2c6f" color="#ffffff" align="center" style="width:15%;border-color:#c2c2c2;padding:14px 10px;"><b>When Added</b></th>
        </tr>
    </thead>
    <tbody>
{$courses_rows}
    </tbody>
</table>
HTML;

    // Build table using TCPDF-friendly HTML attributes with clean structure
    $table_html = <<<HTML
<table border="0" cellspacing="0" cellpadding="8" style="width:100%;margin-bottom:16px;">
    <thead>
        <tr>
            <th bgcolor="#5b2c6f" color="#ffffff" align="center" style="width:25%"><b>Competency</b></th>
            <th bgcolor="#5b2c6f" color="#ffffff" align="center" style="width:15%"><b>Savings &amp; Investments</b></th>
            <th bgcolor="#5b2c6f" color="#ffffff" align="center" style="width:15%"><b>Pensions</b></th>
            <th bgcolor="#5b2c6f" color="#ffffff" align="center" style="width:15%"><b>Ethics</b></th>
            <th bgcolor="#5b2c6f" color="#ffffff" align="center" style="width:15%"><b>Life Assurance</b></th>
            <th bgcolor="#5b2c6f" color="#ffffff" align="center" style="width:15%"><b>Total</b></th>
        </tr>
    </thead>
    <tbody>
        <tr nobr="true">
            <td bgcolor="#f7f7f7" color="#555555" align="left" style="width:25%"><b>Completed</b></td>
            <td color="#555555" align="center" style="width:15%">{$completed_si}</td>
            <td color="#555555" align="center" style="width:15%">{$completed_pensions}</td>
            <td color="#555555" align="center" style="width:15%">{$completed_ethics}</td>
            <td color="#555555" align="center" style="width:15%">{$completed_life}</td>
            <td color="#555555" align="center" style="width:15%">{$completed_total_fmt}</td>
        </tr>
        <tr nobr="true">
            <td bgcolor="#f7f7f7" color="#555555" align="left" style="width:25%"><b>Pro Rata Deduction:</b></td>
            <td color="#555555" align="center" style="width:15%">1</td>
            <td color="#555555" align="center" style="width:15%">1</td>
            <td color="#555555" align="center" style="width:15%">1</td>
            <td color="#555555" align="center" style="width:15%">1</td>
            <td color="#555555" align="center" style="width:15%">{$pro_rata_total_fmt}</td>
        </tr>
        <tr nobr="true">
            <td bgcolor="#f7f7f7" color="#555555" align="left" style="width:25%"><b>Required</b></td>
            <td color="#555555" align="center" style="width:15%">1</td>
            <td color="#555555" align="center" style="width:15%">1</td>
            <td color="#555555" align="center" style="width:15%">1</td>
            <td color="#555555" align="center" style="width:15%">1</td>
            <td color="#555555" align="center" style="width:15%">{$required_total_fmt}</td>
        </tr>
        <tr nobr="true">
            <td bgcolor="#f7f7f7" color="#555555" align="left" style="width:25%"><b>Mandatory Requirements Met</b></td>
            <td color="#555555" align="center" style="width:15%;display: flex;align-items:center;">{$check_si}</td>
            <td color="#555555" align="center" style="width:15%;display: flex;align-items:center;">{$check_pensions}</td>
            <td color="#555555" align="center" style="width:15%;display: flex;align-items:center;">{$check_ethics}</td>
            <td color="#555555" align="center" style="width:15%;display: flex;align-items:center;">{$check_life}</td>
            <td color="#555555" align="center" style="width:15%;display: flex;align-items:center;">{$check_total}</td>
        </tr>
    </tbody>
</table>
HTML;

    // Build first HTML (header + first table) and write it
    $logo_section = $logo_html ? "<div align=\"center\" style=\"margin-bottom:8px;\">{$logo_html}</div>" : '';
    $first_html = <<<HTML
{$logo_section}
<h2 align="center" style="color:#6a1b9a;">CPD – CERTIFICATION OF COMPLETION</h2>
<p align="center" style="font-size:11px;">The below tables contain an overview of your CPD progress for {$year_safe} and available actions for this year.</p>
<p align="left" style="font-size:11px;color:#c62828;margin:4px 0 10px 0;"><b>STATUS: COMPLETED AND SUBMITTED</b></p>

<table border="0" cellspacing="0" cellpadding="10" style="width:100%;margin-top:14px;margin-bottom:8px;">
    <tr>
        <td bgcolor="#607d8b" color="#ffffff" align="center">
            <b style="font-size:16px;">{$user_name_safe}</b><br/>
            <b style="font-size:14px;">PROGRESS FOR {$year_safe}</b><br/>
            {$period_html}
        </td>
    </tr>
</table>

{$table_html}
HTML;

    $pdf->writeHTML($first_html, true, false, true, false, '');

    // Use TCPDF spacing between tables
    $pdf->Ln(4); // add vertical space

    // Second HTML (section header + courses table)
    $second_html = <<<HTML
<table border="0" cellspacing="0" cellpadding="10" style="width:100%;margin-bottom:8px;">
    <tr>
        <td bgcolor="#607d8b" color="#ffffff" align="center">
            <b style="font-size:16px;">{$user_name_safe}</b><br/>
            <b style="font-size:14px;">DETAILS OF COMPLETED COURSES ({$year_safe})</b>
        </td>
    </tr>
</table>

{$courses_table_html}
HTML;

    $pdf->writeHTML($second_html, true, false, true, false, '');

    return $pdf->Output('', 'S');
}

/**
 * Fallback simple PDF generation
 */
function generate_simple_pdf($certificate, $user_name, $user_email, $contact_address, $submission_year) {
    // Generate a simple PDF using basic PDF format
    $pdf_content = "%PDF-1.4\n";
    $pdf_content .= "1 0 obj\n";
    $pdf_content .= "<<\n";
    $pdf_content .= "/Type /Catalog\n";
    $pdf_content .= "/Pages 2 0 R\n";
    $pdf_content .= ">>\n";
    $pdf_content .= "endobj\n\n";
    
    $pdf_content .= "2 0 obj\n";
    $pdf_content .= "<<\n";
    $pdf_content .= "/Type /Pages\n";
    $pdf_content .= "/Kids [3 0 R]\n";
    $pdf_content .= "/Count 1\n";
    $pdf_content .= ">>\n";
    $pdf_content .= "endobj\n\n";
    
    // Create the page content
    $page_content = "BT\n";
    $page_content .= "/F1 24 Tf\n";
    $page_content .= "100 700 Td\n";
    $page_content .= "(CERTIFICATE OF COMPLETION) Tj\n";
    $page_content .= "0 -50 Td\n";
    $page_content .= "/F2 16 Tf\n";
    $page_content .= "(Continuing Professional Development - CPD) Tj\n";
    $page_content .= "0 -80 Td\n";
    $page_content .= "/F1 20 Tf\n";
    $page_content .= "(" . $certificate->name . ") Tj\n";
    
    // Add avatar information if available
    if (!empty($certificate->avatar_url)) {
        $page_content .= "0 -30 Td\n";
        $page_content .= "/F2 12 Tf\n";
        $page_content .= "(Certificate Avatar: " . $certificate->avatar_url . ") Tj\n";
    }
    
    $page_content .= "0 -60 Td\n";
    $page_content .= "/F2 14 Tf\n";
    $page_content .= "(This is to certify that) Tj\n";
    $page_content .= "0 -40 Td\n";
    $page_content .= "/F1 18 Tf\n";
    $page_content .= "(" . $user_name . ") Tj\n";
    $page_content .= "0 -40 Td\n";
    $page_content .= "/F2 14 Tf\n";
    $page_content .= "(has successfully completed the CPD requirements for " . $submission_year . ") Tj\n";
    $page_content .= "0 -60 Td\n";
    $page_content .= "/F2 12 Tf\n";
    $page_content .= "(Certificate: " . $certificate->name . " (" . $certificate->year . ")) Tj\n";
    $page_content .= "0 -20 Td\n";
    $page_content .= "(Recipient: " . $user_name . ") Tj\n";
    $page_content .= "0 -20 Td\n";
    $page_content .= "(Email: " . $user_email . ") Tj\n";
    if (!empty($contact_address)) {
        $page_content .= "0 -20 Td\n";
        $page_content .= "(Address: " . $contact_address . ") Tj\n";
    }
    $page_content .= "0 -40 Td\n";
    $page_content .= "(Issued on: " . date('F j, Y') . ") Tj\n";
    $page_content .= "0 -60 Td\n";
    $page_content .= "/F2 10 Tf\n";
    $page_content .= "(This certificate is issued by the Institute of Insurance and Pension Management) Tj\n";
    $page_content .= "0 -15 Td\n";
    $page_content .= "(For verification: info@iipm.com) Tj\n";
    $page_content .= "ET\n";
    
    $pdf_content .= "3 0 obj\n";
    $pdf_content .= "<<\n";
    $pdf_content .= "/Type /Page\n";
    $pdf_content .= "/Parent 2 0 R\n";
    $pdf_content .= "/MediaBox [0 0 612 792]\n";
    $pdf_content .= "/Contents 4 0 R\n";
    $pdf_content .= "/Resources <<\n";
    $pdf_content .= "/Font <<\n";
    $pdf_content .= "/F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\n";
    $pdf_content .= "/F2 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\n";
    $pdf_content .= ">>\n";
    $pdf_content .= ">>\n";
    $pdf_content .= ">>\n";
    $pdf_content .= "endobj\n\n";
    
    $pdf_content .= "4 0 obj\n";
    $pdf_content .= "<<\n";
    $pdf_content .= "/Length " . strlen($page_content) . "\n";
    $pdf_content .= ">>\n";
    $pdf_content .= "stream\n";
    $pdf_content .= $page_content;
    $pdf_content .= "endstream\n";
    $pdf_content .= "endobj\n\n";
    
    $pdf_content .= "xref\n";
    $pdf_content .= "0 5\n";
    $pdf_content .= "0000000000 65535 f \n";
    $pdf_content .= "0000000009 00000 n \n";
    $pdf_content .= "0000000058 00000 n \n";
    $pdf_content .= "0000000115 00000 n \n";
    $pdf_content .= "0000000274 00000 n \n";
    $pdf_content .= "trailer\n";
    $pdf_content .= "<<\n";
    $pdf_content .= "/Size 5\n";
    $pdf_content .= "/Root 1 0 R\n";
    $pdf_content .= ">>\n";
    $pdf_content .= "startxref\n";
    $pdf_content .= "500\n";
    $pdf_content .= "%%EOF\n";
    
    return $pdf_content;
}

/**
 * Create the CPD submissions table
 */
function iipm_create_submissions_table() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $table_submissions = $wpdb->prefix . 'test_iipm_submissions';
    $sql_submissions = "CREATE TABLE $table_submissions (
        id int(11) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        year varchar(4) NOT NULL,
        details longtext NOT NULL,
        admin_notes text NULL,
        reviewed_by bigint(20) NULL,
        reviewed_at timestamp NULL,
        certificate_id int(11) NULL,
        is_notified tinyint(1) DEFAULT 0,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY year (year),
        KEY status (status),
        KEY reviewed_by (reviewed_by),
        KEY certificate_id (certificate_id),
        KEY created_at (created_at),
        KEY is_notified (is_notified)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_submissions);
}

/**
 * Mark submission as notified
 */
add_action('wp_ajax_iipm_mark_submission_notified', 'iipm_mark_submission_notified');

function iipm_mark_submission_notified() {
    if (!is_user_logged_in()) {
        wp_send_json_error('User not logged in');
    }

    $submission_id = isset($_POST['submission_id']) ? intval($_POST['submission_id']) : 0;
    
    if ($submission_id <= 0) {
        wp_send_json_error('Invalid submission ID');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'test_iipm_submissions';
    
    // Verify the submission belongs to the current user
    $submission = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d AND user_id = %d",
        $submission_id,
        get_current_user_id()
    ));
    
    if (!$submission) {
        wp_send_json_error('Submission not found or access denied');
    }
    
    // Update the is_notified column
    $result = $wpdb->update(
        $table,
        array('is_notified' => 1),
        array('id' => $submission_id),
        array('%d'),
        array('%d')
    );
    
    if ($result === false) {
        wp_send_json_error('Failed to update notification status');
    }
    
    wp_send_json_success(array(
        'message' => 'Submission marked as notified successfully'
    ));
}

// Create the table when the file is loaded
add_action('init', 'iipm_create_submissions_table');