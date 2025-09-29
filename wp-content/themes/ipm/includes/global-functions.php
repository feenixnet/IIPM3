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

// Function to display login/logout notifications from session
if (!function_exists('iipm_display_session_notifications')) {
    function iipm_display_session_notifications() {
        if (!session_id()) {
            session_start();
        }
        
        $output = '';
        
        // Check for login notification
        if (isset($_SESSION['iipm_login_notification'])) {
            $notification = $_SESSION['iipm_login_notification'];
            $output .= iipm_notification_script($notification['type'], $notification['title'], $notification['message']);
            unset($_SESSION['iipm_login_notification']); // Clear after displaying
        }
        
        // Check for logout notification
        if (isset($_SESSION['iipm_logout_notification'])) {
            $notification = $_SESSION['iipm_logout_notification'];
            $output .= iipm_notification_script($notification['type'], $notification['title'], $notification['message']);
            unset($_SESSION['iipm_logout_notification']); // Clear after displaying
        }
        
        return $output;
    }
}

/**
 * Calculate sum of leave request duration for a user in a specific year
 * 
 * @param int $user_id User ID
 * @param int $year Year (default: current year)
 * @return int Total leave duration in days
 */
if (!function_exists('iipm_calculate_user_leave_duration')) {
    function iipm_calculate_user_leave_duration($user_id, $year = null) {
        global $wpdb;
        
        if (!$year) {
            $year = date('Y');
        }
        
        $table_name = $wpdb->prefix . 'test_iipm_leave_requests';
        
        // Get all approved leave requests for the user in the specified year
        $leave_requests = $wpdb->get_results($wpdb->prepare(
            "SELECT duration_days, status 
             FROM {$table_name} 
             WHERE user_id = %d 
             AND YEAR(leave_start_date) = %d 
             AND status = 'approved'",
            $user_id, $year
        ));

        error_log('IIPM: Leave requests: ' . print_r($leave_requests, true));
        
        $total_duration = 0;
        
        foreach ($leave_requests as $request) {
            $total_duration += intval($request->duration_days);
        }

        error_log('IIPM: Total duration: ' . $total_duration);
        
        return $total_duration;
    }
}

/**
 * Get membership constant for CPD calculation
 */
if (!function_exists('iipm_get_membership_constant')) {
    function iipm_get_membership_constant($user_id) {
        global $wpdb;
        
        // Get user's membership level
        $member = $wpdb->get_row($wpdb->prepare(
            "SELECT membership_level FROM {$wpdb->prefix}test_iipm_members WHERE user_id = %d",
            $user_id
        ));
        
        if (!$member || !$member->membership_level) {
            return 1.0; // Default constant for unknown memberships
        }
        
        $membership_level = $member->membership_level;
        
        // If membership level is numeric, it's a membership ID from memberships table
        if (is_numeric($membership_level)) {
            $membership_info = $wpdb->get_row($wpdb->prepare(
                "SELECT name FROM {$wpdb->prefix}memberships WHERE id = %d",
                intval($membership_level)
            ));
            
            $membership_name = $membership_info ? $membership_info->name : '';
        } else {
            $membership_name = $membership_level;
        }
        
        // Return constant based on membership type
        if (stripos($membership_name, 'Member') !== false && stripos($membership_name, 'QPT Trustee Member') === false) {
            return 1.25; // Member
        } elseif (stripos($membership_name, 'QPT Trustee Member') !== false) {
            return 0.85; // QPT Trustee Member
        } else {
            return 1.0; // All other types
        }
    }
}

/**
 * Round to nearest 0.5
 */
if (!function_exists('iipm_round_to_nearest_half')) {
    function iipm_round_to_nearest_half($value) {
        return round($value * 2) / 2;
    }
}

/**
 * Calculate adjusted target points for a user based on leave requests
 * 
 * @param int $user_id User ID
 * @param int $year Year (default: current year)
 * @return float Adjusted target hours
 */
if (!function_exists('iipm_calculate_adjusted_target_points')) {
    function iipm_calculate_adjusted_target_points($user_id, $year = null) {
        global $wpdb;
        
        if (!$year) {
            $year = date('Y');
        }
        
        // Get the original target hours from CPD types table
        // Find CPD type by matching year with Start of logging date
        $cpd_types_table = $wpdb->prefix . 'cpd_types';
        $original_target = $wpdb->get_var($wpdb->prepare(
            "SELECT `Total Hours/Points Required` FROM {$cpd_types_table} WHERE YEAR(`Start of logging date`) = %d LIMIT 1",
            $year
        ));
        
        if (!$original_target) {
            // Fallback to default target if not found
            $original_target = 8; // Default 8 hours
        }
        
        // Get total leave duration for the user
        $leave_duration = iipm_calculate_user_leave_duration($user_id, $year);
        
        // Get membership constant
        $membership_constant = iipm_get_membership_constant($user_id);
        
        // Calculate adjusted target using the NEW formula:
        // adjusted_target = original_target - CONSTANT_OF_MEMBERSHIP * (leave_duration / 30)
        $adjusted_target = $original_target - ($membership_constant * ($leave_duration / 30));
        
        // If adjusted_target is less than 2, show 2
        if ($adjusted_target < 2) {
            $adjusted_target = 2;
        }

        error_log('IIPM: Adjusted target calculation - Original: ' . $original_target . ', Leave days: ' . $leave_duration . ', Membership constant: ' . $membership_constant . ', Adjusted: ' . $adjusted_target);
        
        // Round to nearest 0.5
        return iipm_round_to_nearest_half($adjusted_target);
    }
}
?>
