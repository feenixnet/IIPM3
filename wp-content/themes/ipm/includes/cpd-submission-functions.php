<?php
// Prevent direct access
if (!defined('ABSPATH')) { exit; }

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
        'details' => $details
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