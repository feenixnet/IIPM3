<?php
// Prevent direct access
if (!defined('ABSPATH')) { exit; }

/**
 * CPD Certificates - Table + CRUD + Upload (avatar)
 * Fields: id, name, year, description, avatar_url, created_at, updated_at
 */

if (!function_exists('iipm_create_cpd_certifications_table')) {
    function iipm_create_cpd_certifications_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . 'test_iipm_certifications';
        $sql = "CREATE TABLE $table (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            year varchar(10) NOT NULL,
            description text NULL,
            avatar_url varchar(512) NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_year_name (year, name),
            KEY year (year)
        ) $charset_collate;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}

// Ensure table on theme switch/init
add_action('after_switch_theme', 'iipm_create_cpd_certifications_table');

// Permission check helper (WP caps + custom flag)
function iipm_certs_user_can_admin() {
    if (!is_user_logged_in()) return false;
    if (current_user_can('administrator') || current_user_can('manage_iipm_members')) return true;
    global $wpdb; $uid = get_current_user_id();
    $flag = $wpdb->get_var($wpdb->prepare(
        "SELECT user_is_admin FROM {$wpdb->prefix}test_iipm_member_profiles WHERE user_id = %d",
        $uid
    ));
    return intval($flag) === 1;
}

// Upload avatar helper (returns URL)
function iipm_certs_handle_avatar_upload($file_key = 'avatar') {
    if (!isset($_FILES[$file_key]) || empty($_FILES[$file_key]['name'])) {
        return null;
    }
    if (!function_exists('wp_handle_upload')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    $overrides = array('test_form' => false);
    $uploaded = wp_handle_upload($_FILES[$file_key], $overrides);
    if (isset($uploaded['error'])) {
        return new WP_Error('upload_error', $uploaded['error']);
    }
    return $uploaded['url'];
}

// CREATE / UPDATE
function iipm_certs_save() {
    if (!iipm_certs_user_can_admin()) { wp_send_json_error('Insufficient permissions'); }
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'iipm_portal_nonce')) { wp_send_json_error('Bad nonce'); }

    global $wpdb; $table = $wpdb->prefix . 'test_iipm_certifications';
    error_log('Table name: ' . $table);
    error_log('Table exists: ' . ($wpdb->get_var("SHOW TABLES LIKE '$table'") ? 'YES' : 'NO'));
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $name = sanitize_text_field($_POST['name'] ?? '');
    $year = sanitize_text_field($_POST['year'] ?? date('Y'));
    $description = sanitize_textarea_field($_POST['description'] ?? '');
    $avatar_remove = isset($_POST['avatar_remove']) ? intval($_POST['avatar_remove']) : 0;

    if ($name === '') { wp_send_json_error('Name is required'); }

    $avatar_url = null;
    $existing = null;
    if ($id > 0) {
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    }
    $upload_result = iipm_certs_handle_avatar_upload('avatar');
    if (is_wp_error($upload_result)) { wp_send_json_error($upload_result->get_error_message()); }
    if ($upload_result) { $avatar_url = $upload_result; }

    $data = array(
        'name' => $name,
        'year' => $year,
        'description' => $description,
        'created_at' => current_time('mysql')
    );
    $format = array('%s','%s','%s');
    if ($avatar_url) { $data['avatar_url'] = $avatar_url; $format[] = '%s'; }

    // If removing avatar or uploading a new one, and an old one exists, delete old file
    if ($existing && ($avatar_remove === 1 || $avatar_url)) {
        if (!empty($existing->avatar_url)) {
            $uploads = wp_upload_dir();
            $baseurl = rtrim($uploads['baseurl'], '/');
            $basedir = rtrim($uploads['basedir'], DIRECTORY_SEPARATOR);
            $path = str_replace($baseurl, $basedir, $existing->avatar_url);
            if ($path && file_exists($path)) {
                @unlink($path);
            }
        }
        if ($avatar_remove === 1 && !$avatar_url) {
            $data['avatar_url'] = null;
            $format[] = '%s'; // null handled but keep format alignment
        }
    }

    if ($id > 0) {
        $result = $wpdb->update($table, $data, array('id' => $id), $format, array('%d'));
        error_log('Update result: ' . $result);
        error_log('Update data: ' . print_r($data, true));
        error_log('Update format: ' . print_r($format, true));
        error_log('Last error: ' . $wpdb->last_error);
    } else {
        $result = $wpdb->insert($table, $data, $format);
        error_log('Insert result: ' . $result);
        error_log('Insert data: ' . print_r($data, true));
        error_log('Insert format: ' . print_r($format, true));
        error_log('Last error: ' . $wpdb->last_error);
        if ($result !== false) { $id = intval($wpdb->insert_id); }
    }

    if ($result === false) { 
        error_log('Database error: ' . $wpdb->last_error);
        wp_send_json_error('Database error saving certificate: ' . $wpdb->last_error); 
    }

    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    wp_send_json_success($row);
}
add_action('wp_ajax_iipm_certs_save', 'iipm_certs_save');

// READ (list with year filter)
function iipm_certs_list() {
    if (!iipm_certs_user_can_admin()) { wp_send_json_error('Insufficient permissions'); }
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'iipm_portal_nonce')) { wp_send_json_error('Bad nonce'); }
    global $wpdb; $table = $wpdb->prefix . 'test_iipm_certifications';
    $year = sanitize_text_field($_POST['year'] ?? '');
    $where = ''; $vals = array();
    if ($year !== '') { $where = 'WHERE year = %s'; $vals[] = $year; }
    $query = "SELECT * FROM $table $where ORDER BY created_at DESC";
    $rows = empty($vals) ? $wpdb->get_results($query) : $wpdb->get_results($wpdb->prepare($query, $vals));
    wp_send_json_success(array('items' => $rows));
}
add_action('wp_ajax_iipm_certs_list', 'iipm_certs_list');

// DELETE
function iipm_certs_delete() {
    if (!iipm_certs_user_can_admin()) { wp_send_json_error('Insufficient permissions'); }
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'iipm_portal_nonce')) { wp_send_json_error('Bad nonce'); }
    $id = intval($_POST['id'] ?? 0); if ($id <= 0) { wp_send_json_error('Invalid id'); }
    global $wpdb; $table = $wpdb->prefix . 'test_iipm_certifications';
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    if (!$row) { wp_send_json_error('Not found'); }
    $res = $wpdb->delete($table, array('id' => $id), array('%d'));
    if ($res === false) { wp_send_json_error('Delete failed'); }
    wp_send_json_success('Deleted');
}
add_action('wp_ajax_iipm_certs_delete', 'iipm_certs_delete');


