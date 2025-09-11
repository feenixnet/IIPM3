<?php
/**
 * Global Functions for IIPM Theme
 * 
 * Contains utility functions that can be used across all templates
 */

if (!defined('ABSPATH')) {
    exit;
}

// Notification system should be included individually in templates that need it

// Global function to generate JavaScript notification
if (!function_exists('iipm_notification_script')) {
    function iipm_notification_script($status, $title, $message) {
        $status = strtolower($status);
        $valid_statuses = ['success', 'error', 'warning', 'info'];
        
        // Default to info if invalid status
        if (!in_array($status, $valid_statuses)) {
            $status = 'info';
        }
        
        $escaped_title = esc_js($title);
        $escaped_message = esc_js($message);
        
        return '<script>
            document.addEventListener("DOMContentLoaded", function() {
                if (window.notifications) {
                    notifications.' . $status . '("' . $escaped_title . '", "' . $escaped_message . '");
                }
            });
        </script>';
    }
}

// Helper function to log super admin changes
if (!function_exists('iipm_log_super_admin_change')) {
    function iipm_log_super_admin_change($user_id, $action) {
        $current_user = wp_get_current_user();
        $user = get_user_by('ID', $user_id);
        $message = sprintf(
            '[Super Admin Change] %s performed "%s" action on user %s (%s)',
            $current_user->user_email,
            $action,
            $user ? $user->user_email : $user_id,
            current_time('mysql')
        );
        
        error_log($message);
        
        // You can also store this in a custom table or use WordPress's logging system
        // For now, we'll just use error_log for simplicity
    }
}

// Global function to show notification immediately (echo version)
if (!function_exists('iipm_show_notification')) {
    function iipm_show_notification($status, $title, $message) {
        echo iipm_notification_script($status, $title, $message);
    }
}

// Global function to get notification script without echoing
if (!function_exists('iipm_get_notification')) {
    function iipm_get_notification($status, $title, $message) {
        return iipm_notification_script($status, $title, $message);
    }
}
?>
