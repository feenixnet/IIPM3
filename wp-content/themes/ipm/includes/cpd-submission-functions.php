<?php
// Prevent direct access
if (!defined('ABSPATH')) { exit; }

// User submission functions
add_action('wp_ajax_iipm_submission_save', 'iipm_submission_save');

function iipm_submission_save() {
    global $wpdb; 
    $table = $wpdb->prefix . 'test_iipm_submissions';
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $uid = get_current_user_id();
    $year = sanitize_text_field($_POST['year'] ?? date('Y'));
    $details = sanitize_text_field($_POST['details'] ?? '{}');
    
    $data = array(
        'user_id' => $uid,
        'year' => $year,
        'details' => $details,
        'status' => 'pending'
    );

    if($id > 0) {
        $result = $wpdb->update($table, $data, array('id' => $id));
    } else {
        $result = $wpdb->insert($table, $data);
        if ($result !== false) { $id = intval($wpdb->insert_id); }
    }

    error_log($result);

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
    $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'pending';
    $year = isset($_POST['year']) ? sanitize_text_field($_POST['year']) : '';
    $user_search = isset($_POST['user_search']) ? sanitize_text_field($_POST['user_search']) : '';
    
    $offset = ($page - 1) * $per_page;
    
    // Build WHERE clause
    $where_conditions = array();
    $where_params = array();
    
    if (!empty($status)) {
        $where_conditions[] = "s.status = %s";
        $where_params[] = $status;
    }
    
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
 * Update submission status (approve/reject)
 */
function iipm_update_submission_status() {
    // Check admin permissions
    if (!current_user_can('manage_iipm_members') && !current_user_can('administrator')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    global $wpdb;
    
    $submission_id = isset($_POST['submission_id']) ? intval($_POST['submission_id']) : 0;
    $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
    $admin_notes = isset($_POST['admin_notes']) ? sanitize_textarea_field($_POST['admin_notes']) : '';
    
    if (!in_array($status, array('approved', 'rejected'))) {
        wp_send_json_error('Invalid parameters');
    }
    
    $table = $wpdb->prefix . 'test_iipm_submissions';
    
    $update_data = array(
        'status' => $status,
        'admin_notes' => $admin_notes,
        'reviewed_by' => get_current_user_id(),
        'reviewed_at' => current_time('mysql')
    );
    
    $format = array('%s', '%s', '%d', '%s');
    
    // If rejecting an approved submission, also set certificate_id to null
    if ($status === 'rejected') {
        $current_submission = $wpdb->get_row($wpdb->prepare(
            "SELECT status, certificate_id FROM $table WHERE id = %d",
            $submission_id
        ));
        
        if ($current_submission && $current_submission->status === 'approved' && $current_submission->certificate_id) {
            $update_data['certificate_id'] = null;
            $format[] = '%s';
        }
    }
    
    $result = $wpdb->update(
        $table,
        $update_data,
        array('id' => $submission_id),
        $format,
        array('%d')
    );
    
    if ($result === false) {
        wp_send_json_error('Failed to update submission status');
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
        'message' => 'Submission status updated successfully'
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

    // Check if submission exists and is approved
    $submission = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $submissions_table WHERE id = %d AND status = 'approved'",
        $submission_id
    ));

    if (!$submission) {
        wp_send_json_error('Submission not found or not approved');
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

    // Generate PDF content with avatar image
    $pdf_content = generate_certificate_pdf($certificate, $user_name, $user_email, $contact_address, $submission_year);
    
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
function generate_certificate_pdf($certificate, $user_name, $user_email, $contact_address, $submission_year) {
    // Check if TCPDF is available
    if (!class_exists('TCPDF')) {
        // Fallback to simple PDF if TCPDF is not available
        return generate_simple_pdf($certificate, $user_name, $user_email, $contact_address, $submission_year);
    }
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('IIPM System');
    $pdf->SetAuthor('Institute of Insurance and Pension Management');
    $pdf->SetTitle('CPD Certificate - ' . $certificate->name);
    $pdf->SetSubject('Continuing Professional Development Certificate');
    $pdf->SetKeywords('CPD, Certificate, IIPM, Professional Development');
    
    // Set default header data
    $pdf->SetHeaderData('', 0, 'CERTIFICATE OF COMPLETION', 'Continuing Professional Development (CPD)');
    
    // Set header and footer fonts
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    
    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    
    // Set margins
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    
    // Set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', 'B', 20);
    
    // Certificate title
    $pdf->Cell(0, 15, $certificate->name, 0, 1, 'C');
    $pdf->Ln(10);
    
    
    // Award text
    $pdf->SetFont('helvetica', '', 16);
    $pdf->Cell(0, 10, 'This is to certify that', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Recipient name
    $pdf->SetFont('helvetica', 'B', 18);
    $pdf->Cell(0, 10, $user_name, 0, 1, 'C');
    $pdf->Ln(5);
    
    // Completion text
    $pdf->SetFont('helvetica', '', 16);
    $pdf->Cell(0, 10, 'has successfully completed the Continuing Professional Development', 0, 1, 'C');
    $pdf->Cell(0, 10, 'requirements for the year ' . $submission_year . '.', 0, 1, 'C');
    $pdf->Ln(20);
    
    // Details section
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, 'Certificate Details:', 0, 1, 'L');
    $pdf->Ln(5);
    
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(60, 8, 'Certificate Name:', 0, 0, 'L');
    $pdf->Cell(0, 8, $certificate->name, 0, 1, 'L');
    
    $pdf->Cell(60, 8, 'Certificate Year:', 0, 0, 'L');
    $pdf->Cell(0, 8, $certificate->year, 0, 1, 'L');
    
    $pdf->Cell(60, 8, 'Submission Year:', 0, 0, 'L');
    $pdf->Cell(0, 8, $submission_year, 0, 1, 'L');
    
    $pdf->Cell(60, 8, 'Recipient Name:', 0, 0, 'L');
    $pdf->Cell(0, 8, $user_name, 0, 1, 'L');
    
    $pdf->Cell(60, 8, 'Email Address:', 0, 0, 'L');
    $pdf->Cell(0, 8, $user_email, 0, 1, 'L');
    
    if (!empty($contact_address)) {
        $pdf->Cell(60, 8, 'Contact Address:', 0, 0, 'L');
        $pdf->Cell(0, 8, $contact_address, 0, 1, 'L');
    }
    
    if (!empty($certificate->description)) {
        $pdf->Cell(60, 8, 'Description:', 0, 0, 'L');
        $pdf->Cell(0, 8, $certificate->description, 0, 1, 'L');
    }
    
    $pdf->Ln(20);
    
    // Issue date
    $pdf->SetFont('helvetica', '', 14);
    $pdf->Cell(0, 10, 'Issued on: ' . date('F j, Y'), 0, 1, 'C');
    $pdf->Ln(20);
    
    // Signatures
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(90, 10, 'Administrator', 0, 0, 'C');
    $pdf->Cell(90, 10, 'Date', 0, 1, 'C');
    $pdf->Ln(10);
    
    // Footer
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 8, 'This certificate is issued by the Institute of Insurance and Pension Management (IIPM)', 0, 1, 'C');
    $pdf->Cell(0, 8, 'For verification purposes, please contact: info@iipm.com', 0, 1, 'C');
    
    // Return PDF content
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
        status enum('pending', 'approved', 'rejected') DEFAULT 'pending',
        admin_notes text NULL,
        reviewed_by bigint(20) NULL,
        reviewed_at timestamp NULL,
        certificate_id int(11) NULL,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY year (year),
        KEY status (status),
        KEY reviewed_by (reviewed_by),
        KEY certificate_id (certificate_id),
        KEY created_at (created_at)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_submissions);
}

// Create the table when the file is loaded
add_action('init', 'iipm_create_submissions_table');