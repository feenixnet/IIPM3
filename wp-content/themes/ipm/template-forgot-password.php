<?php
/*
Template Name: Forgot Password
*/

// If user is already logged in, redirect them to dashboard
if (is_user_logged_in()) {
    wp_redirect(home_url('/dashboard/'));
    exit;
}

$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password_submit'])) {
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    
    if (empty($email)) {
        $message = 'Please enter your email address.';
        $message_type = 'error';
    } else {
        // Check if user exists
        $user = get_user_by('email', $email);
        
        if (!$user) {
            // Don't reveal if email exists or not for security
            $message = 'If that email address exists in our system, we have sent a password reset link to it.';
            $message_type = 'success';
        } else {
            // Generate reset key
            $reset_key = get_password_reset_key($user);
            
            if (is_wp_error($reset_key)) {
                $message = 'Unable to generate reset key. Please try again.';
                $message_type = 'error';
            } else {
                // Create reset URL
                $reset_url = home_url('/reset-password/?key=' . $reset_key . '&login=' . rawurlencode($user->user_login));
                
                // Send email
                $subject = 'Password Reset Request - IIPM';
                
                // Beautiful HTML email template
                $message_body = '
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
                                        <td style="background: linear-gradient(135deg, #715091 0%, #715091 100%); padding: 40px 30px; text-align: center;">
                                            <h1 style="color: #ffffff; margin: 0; font-size: 28px; font-weight: 700; line-height: 1.2;">
                                                🔐<br>
                                                Password Reset Request
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
                                                We received a request to reset your password for your IIPM account. If you made this request, please click the button below to reset your password.
                                            </p>
                                            
                                            <!-- CTA Button -->
                                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 30px 0;">
                                                <tr>
                                                    <td align="center">
                                                        <a href="' . esc_url($reset_url) . '" style="display: inline-block; padding: 14px 32px; background-color: #715091; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 16px; text-align: center;">Reset Password</a>
                                                    </td>
                                                </tr>
                                            </table>
                                            
                                            <p style="margin: 20px 0; color: #6b7280; font-size: 14px; line-height: 1.6;">
                                                Or copy and paste this link into your browser:
                                            </p>
                                            <p style="margin: 0 0 20px 0; color: #6b7280; font-size: 14px; line-height: 1.6; word-break: break-all;">
                                                <a href="' . esc_url($reset_url) . '" style="color: #715091; text-decoration: none;">' . esc_html($reset_url) . '</a>
                                            </p>
                                            
                                            <div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 16px; margin: 20px 0; border-radius: 4px;">
                                                <p style="margin: 0; color: #92400e; font-size: 14px; line-height: 1.6;">
                                                    <strong>⏰ Important:</strong> This link will expire in 24 hours for security reasons.
                                                </p>
                                            </div>
                                            
                                            <p style="margin: 20px 0 0 0; color: #6b7280; font-size: 14px; line-height: 1.6;">
                                                If you did not request this password reset, please ignore this email. Your password will remain unchanged.
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
                                                Email: <a href="mailto:info@iipm.ie" style="color: #715091; text-decoration: none;">info@iipm.ie</a>
                                            </p>
                                            <p style="margin: 0; color: #9ca3af; font-size: 12px;">
                                                © ' . date('Y') . ' IIPM. All rights reserved.
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
                
                $sent = wp_mail($user->user_email, $subject, $message_body, $headers);
                
                if ($sent) {
                    $message = 'If that email address exists in our system, we have sent a password reset link to it.';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to send reset email. Please try again.';
                    $message_type = 'error';
                }
            }
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
                <h1>Forgot Password</h1>
                <p>Enter your email address and we'll send you a link to reset your password.</p>
            </div>
        </div>

        <div class="forgot-password-form">
            <div class="registration-form-container">
                <?php if ($message): ?>
                    <div class="login-message <?php echo esc_attr($message_type); ?>" style="background-color: <?php echo $message_type === 'success' ? '#d4edda' : '#f8d7da'; ?>; border: 1px solid <?php echo $message_type === 'success' ? '#c3e6cb' : '#f5c6cb'; ?>; color: <?php echo $message_type === 'success' ? '#155724' : '#721c24'; ?>; padding: 15px; margin: 15px 0 30px 0; border-radius: 4px; font-size: 14px;">
                        <p style="margin: 0;"><?php echo esc_html($message); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($message_type !== 'success'): ?>
                <form method="post" action="" class="registration-form">
                    <?php wp_nonce_field('iipm_forgot_password_nonce', 'forgot_password_nonce'); ?>
                    
                    <div class="form-group">
                        <label for="email">Email Address <span class="required-asterisk">*</span></label>
                        <input type="email" id="email" name="email" placeholder="your.email@example.com" 
                               value="<?php echo isset($_POST['email']) ? esc_attr($_POST['email']) : ''; ?>" 
                               required>
                    </div>

                    <div class="form-group submit-button-container">
                        <button type="submit" name="reset_password_submit" class="btn btn-primary">Reset Password</button>
                    </div>
                    
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="<?php echo home_url('/login/'); ?>" style="color: #715091; text-decoration: none;">Back to Login</a>
                    </div>
                </form>
                <?php else: ?>
                    <div style="text-align: center; margin-top: 30px;">
                        <a href="<?php echo home_url('/login/'); ?>" class="btn btn-primary" style="display: inline-block; text-decoration: none;">Back to Login</a>
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
    .forgot-password-form {
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

<?php get_footer(); ?>

