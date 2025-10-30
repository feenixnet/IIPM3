<?php
/*
Template Name: Login Page
*/

// Extend auth cookie expiration when "Remember me" is checked (30 days)
add_filter('auth_cookie_expiration', function($seconds, $user_id, $remember) {
    if ($remember) {
        return 30 * DAY_IN_SECONDS;
    }
    return $seconds;
}, 10, 3);

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    $identifier = isset($_POST['login']) ? sanitize_text_field($_POST['login']) : '';
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;
    
    // Perform sign-on directly (supports username or email in user_login)
    $credentials = array(
        'user_login' => $identifier,
        'user_password' => $password,
        'remember' => $remember
    );

    $user = wp_signon($credentials, is_ssl());

    if (is_wp_error($user)) {
        $login_error = $user->get_error_message();
    } else {
        // Redirect to dashboard
        wp_redirect(home_url('/dashboard/'));
        exit;
    }
}

// If user is already logged in, redirect them to dashboard
if (is_user_logged_in()) {
    wp_redirect(home_url('/dashboard/'));
    exit;
}

get_header();
?>

<main class="login" style="padding-top: 0; position: relative;">
    <div class="banner">
        <?php
        $image = get_field('image');
        if ($image): ?>
            <img src="<?php echo esc_url($image); ?>" alt="" class="w-full">
        <?php endif; ?>

        <div class="container">
            <h1 class="title-lg"><?php echo get_field('title'); ?></h1>
        </div>
    </div>
    
    <div class="login-content">
        <div class="container">
            <div class="login-content__wrapper">
                <div class="login-content__left">
                    <div class="title-md text-purple"><?php echo get_field('login_title'); ?></div>
                    <div class="text"><?php echo get_field('login_text'); ?></div>
                </div>
                
                <div class="login-content__right" style="z-index:10;position: relative;top: -184px;">
                    <div class="title-md text-purple" style="font-size:50px">Log in</div>
                    
                    <?php 
                    // Check for login failure from URL parameter
                    $login_failed = isset($_GET['login']) && $_GET['login'] === 'failed';
                    $error_message = '';
                    
                    if ($login_failed) {
                        $error_message = 'Invalid username/email or password. Please try again.';
                    } elseif (isset($login_error)) {
                        $error_message = $login_error;
                    }
                    
                    if ($error_message): ?>
                        <div class="login-error" style="background-color: #fee; border: 1px solid #fcc; color: #c33; padding: 10px; margin: 15px 0; border-radius: 4px; font-size: 14px;">
                            <p><?php echo esc_html($error_message); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="" class="login-form">
                        <?php wp_nonce_field('iipm_login_nonce', 'login_nonce'); ?>
                        
                        <label style="text-align: left;">
                            E-mail Address/Username
                            <input type="text" name="login" placeholder="johndoe or johndoe@iipm.ie" 
                                   value="<?php echo isset($_POST['login']) ? esc_attr($_POST['login']) : ''; ?>" 
                                   required>
                        </label>

                        <label>
                            Password
                            <input type="password" name="password" placeholder="Enter your password" required>
                        </label>
                        
                        <?php $remember_checked = (!isset($_POST['login_submit'])) || (isset($_POST['remember'])); ?>
                        <div class="iipm-custom-checkbox-wrapper">
                            <div class="iipm-checkbox-container">
                                <input type="checkbox" name="remember" value="1" class="iipm-checkbox-input" <?php echo $remember_checked ? 'checked' : ''; ?>>
                                <span class="iipm-checkbox-checkmark"></span>
                                <span class="iipm-checkbox-text">Remember me</span>
                            </div>
                        </div>

                        <button type="submit" name="login_submit" class="login-btn">Log In</button>

                        <a href="<?php echo wp_lostpassword_url(); ?>" class="forgot-password">I forgot my password</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sync checkbox input when clicking checkmark
    const checkboxContainer = document.querySelector('.iipm-checkbox-container');
    if (checkboxContainer) {
        const checkboxInput = checkboxContainer.querySelector('.iipm-checkbox-input');
        const checkboxCheckmark = checkboxContainer.querySelector('.iipm-checkbox-checkmark');
        
        if (checkboxCheckmark && checkboxInput) {
            // Sync checkmark when clicking on it
            checkboxCheckmark.addEventListener('click', function() {
                checkboxInput.checked = !checkboxInput.checked;
            });
            
            // Sync checkmark visual state when checkbox input changes
            checkboxInput.addEventListener('change', function() {
                // The visual state will be handled by CSS based on :checked pseudo-class
                // This ensures the checkmark span reflects the input state
            });
        }
    }
});
</script>

<?php get_footer(); ?>
