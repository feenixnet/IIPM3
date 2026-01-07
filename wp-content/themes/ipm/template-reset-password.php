<?php
/*
Template Name: Reset Password
*/

// If user is already logged in, redirect them to dashboard
if (is_user_logged_in()) {
    wp_redirect(home_url('/dashboard/'));
    exit;
}

$message = '';
$message_type = '';
$show_form = false;
$key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';
$login = isset($_GET['login']) ? sanitize_text_field($_GET['login']) : '';

// Validate reset key and login
if (!empty($key) && !empty($login)) {
    $user = check_password_reset_key($key, $login);
    
    if (is_wp_error($user)) {
        $message = 'Invalid or expired reset link. Please request a new password reset.';
        $message_type = 'error';
        $show_form = false;
    } else {
        $show_form = true;
    }
} else {
    $message = 'Invalid reset link. Please check your email and try again.';
    $message_type = 'error';
    $show_form = false;
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password_submit']) && $show_form) {
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    
    if (empty($new_password)) {
        $message = 'Please enter a new password.';
        $message_type = 'error';
    } elseif (strlen($new_password) < 8) {
        $message = 'Password must be at least 8 characters long.';
        $message_type = 'error';
    } elseif ($new_password !== $confirm_password) {
        $message = 'Passwords do not match.';
        $message_type = 'error';
    } else {
        // Validate key again
        $user = check_password_reset_key($key, $login);
        
        if (is_wp_error($user)) {
            $message = 'Invalid or expired reset link. Please request a new password reset.';
            $message_type = 'error';
            $show_form = false;
        } else {
            // Disable all WordPress default password change notifications
            add_filter('send_password_change_email', '__return_false');
            add_filter('wp_password_change_notification_email', '__return_false');
            
            // Use wp_set_password directly instead of reset_password to avoid triggering email hooks
            wp_set_password($new_password, $user->ID);
            update_user_meta($user->ID, 'default_password_nag', false);
            
            // Remove filters after password is set
            remove_filter('send_password_change_email', '__return_false');
            remove_filter('wp_password_change_notification_email', '__return_false');
            
            // Send password changed confirmation email (our custom email only)
            $subject = 'Password Successfully Changed - IIPM';
            $login_url = home_url('/login/');
            
            // Beautiful HTML email template
            $email_body = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
            </head>
            <body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f3f4f6; padding: 40px 20px;">
                    <tr>
                        <td align="center">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" style="max-width: 600px; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                                <!-- Header -->
                                <tr>
                                    <td style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 40px 30px; text-align: center;">
                                        <h1 style="color: #ffffff; margin: 0; font-size: 28px; font-weight: 700; line-height: 1.2;">
                                            ✅<br>
                                            Password Changed Successfully
                                        </h1>
                                    </td>
                                </tr>
                                
                                <!-- Content -->
                                <tr>
                                    <td style="padding: 40px 30px; background: #ffffff;">
                                        <p style="margin: 0 0 20px 0; color: #374151; font-size: 16px; line-height: 1.6;">
                                            Hello <strong>' . esc_html($user->display_name) . '</strong>,
                                        </p>
                                        
                                        <p style="margin: 0 0 20px 0; color: #374151; font-size: 16px; line-height: 1.6;">
                                            Your password has been successfully changed. You can now log in to your IIPM account using your new password.
                                        </p>
                                        
                                        <!-- CTA Button -->
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 30px 0;">
                                            <tr>
                                                <td align="center">
                                                    <a href="' . esc_url($login_url) . '" style="display: inline-block; padding: 14px 32px; background-color: #10b981; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 16px; text-align: center;">Log In Now</a>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <div style="background-color: #d1fae5; border-left: 4px solid #10b981; padding: 16px; margin: 20px 0; border-radius: 4px;">
                                            <p style="margin: 0; color: #065f46; font-size: 14px; line-height: 1.6;">
                                                <strong>🔒 Security Notice:</strong> If you did not make this change, please contact us immediately at <a href="mailto:info@iipm.ie" style="color: #059669; text-decoration: none;">info@iipm.ie</a>
                                            </p>
                                        </div>
                                        
                                        <p style="margin: 20px 0 0 0; color: #6b7280; font-size: 14px; line-height: 1.6;">
                                            For your security, we recommend using a strong, unique password that you don\'t use for other accounts.
                                        </p>
                                    </td>
                                </tr>
                                
                                <!-- Footer -->
                                <tr>
                                    <td style="background: #f9fafb; padding: 30px; text-align: center; border-top: 1px solid #e5e7eb;">
                                        <p style="margin: 0 0 10px 0; color: #6b7280; font-size: 14px;">
                                            <strong>Irish Institute of Pensions Management</strong>
                                        </p>
                                        <p style="margin: 0 0 10px 0; color: #6b7280; font-size: 14px;">
                                            Email: <a href="mailto:info@iipm.ie" style="color: #10b981; text-decoration: none;">info@iipm.ie</a>
                                        </p>
                                        <p style="margin: 0; color: #9ca3af; font-size: 12px;">
                                            © ' . date(format: 'Y') . ' IIPM. All rights reserved.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </body>
            </html>';
            
            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: IIPM Portal <' . (defined('SMTP_FROM') ? SMTP_FROM : get_option('admin_email')) . '>'
            );
            
            wp_mail($user->user_email, $subject, $email_body, $headers);
            
            $message = 'Your password has been reset successfully. You can now log in with your new password.';
            $message_type = 'success';
            $show_form = false;
        }
    }
}

get_header();
?>

<div class="member-portal-page main-container">
    <div class="container">
        <?php if (!function_exists('add_success_notification')) { include_once get_template_directory() . '/includes/notification-system.php'; } ?>
        <div class="page-header">
            <div>
                <h1>Reset Password</h1>
                <p>Enter your new password below. Make sure it's at least 8 characters long.</p>
            </div>
        </div>

        <div class="reset-password-form">
            <div class="registration-form-container">
                <?php if ($message): ?>
                    <div class="login-message <?php echo esc_attr($message_type); ?>" style="background-color: <?php echo $message_type === 'success' ? '#d4edda' : '#f8d7da'; ?>; border: 1px solid <?php echo $message_type === 'success' ? '#c3e6cb' : '#f5c6cb'; ?>; color: <?php echo $message_type === 'success' ? '#155724' : '#721c24'; ?>; padding: 15px; margin: 15px 0 30px 0; border-radius: 4px; font-size: 14px;">
                        <p style="margin: 0;"><?php echo esc_html($message); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($show_form): ?>
                <form method="post" action="" class="registration-form">
                    <?php wp_nonce_field('iipm_reset_password_nonce', 'reset_password_nonce'); ?>
                    <input type="hidden" name="key" value="<?php echo esc_attr($key); ?>">
                    <input type="hidden" name="login" value="<?php echo esc_attr($login); ?>">
                    
                    <div class="form-group">
                        <label for="new_password">New Password <span class="required-asterisk">*</span></label>
                        <input type="password" id="new_password" name="new_password" placeholder="Enter new password (min. 8 characters)" 
                               required minlength="8">
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password <span class="required-asterisk">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" 
                               required minlength="8">
                    </div>

                    <div class="form-group submit-button-container">
                        <button type="submit" name="reset_password_submit" class="btn btn-primary">Reset Password</button>
                    </div>
                    
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="<?php echo home_url('/login/'); ?>" style="color: #6b4c93; text-decoration: none;">Back to Login</a>
                    </div>
                </form>
                <?php elseif ($message_type === 'success'): ?>
                    <div style="text-align: center; margin-top: 30px;">
                        <a href="<?php echo home_url('/login/'); ?>" class="btn btn-primary" style="display: inline-block; text-decoration: none;">Go to Login</a>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; margin-top: 30px;">
                        <a href="<?php echo home_url('/forgot-password/'); ?>" class="btn btn-primary" style="display: inline-block; text-decoration: none; margin-bottom: 15px;">Request New Reset Link</a>
                        <div>
                            <a href="<?php echo home_url('/login/'); ?>" style="color: #6b4c93; text-decoration: none;">Back to Login</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
    .page-header h1, .page-header p {
        color: white;
    }
    .reset-password-form {
        display: grid;
        gap: 20px;
        margin: auto;
        max-width: 900px;
    }
    .submit-button-container {
        width: 100%;
        display: flex;
        margin-top: 30px;
    }
    .submit-button-container button {
        margin: auto;
    }
    .required-asterisk {
        color: #e11d48;
        font-weight: 700;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password match validation
    const newPasswordInput = document.querySelector('input[name="new_password"]');
    const confirmPasswordInput = document.querySelector('input[name="confirm_password"]');
    
    if (newPasswordInput && confirmPasswordInput) {
        function validatePasswords() {
            if (confirmPasswordInput.value && newPasswordInput.value !== confirmPasswordInput.value) {
                confirmPasswordInput.setCustomValidity('Passwords do not match');
            } else {
                confirmPasswordInput.setCustomValidity('');
            }
        }
        
        newPasswordInput.addEventListener('input', validatePasswords);
        confirmPasswordInput.addEventListener('input', validatePasswords);
    }
});
</script>

<?php get_footer(); ?>

