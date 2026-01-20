<?php
/**
 * The header for our theme
 *
 * This is the template that displays all of the <head> section and everything up until <div id="content">
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package IPM
 */

?>
<!doctype html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="profile" href="https://gmpg.org/xfn/11">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Gabarito:wght@400..900&display=swap" rel="stylesheet">
    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <?php wp_head(); ?>

<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-PTKRHGN');</script>
<!-- End Google Tag Manager -->

<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-PTKRHGN"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->

</head>

<body <?php body_class(); ?>>
    <?php wp_body_open(); ?>
    
    <?php
    // Include notification system if not already loaded
    if (!function_exists('add_success_notification')) {
        include_once get_template_directory() . '/includes/notification-system.php';
    }
    
    // Display login/logout notifications
    echo iipm_display_session_notifications();
    ?>
    
    <a class="skip-link screen-reader-text" href="#primary"><?php esc_html_e('Skip to content', 'ipm'); ?></a>

    <header class="header">
        <div class="container">
            <div class="header__inner flex align-center space-between">

                <div class="header__burger" id="burger">
                    <svg class="opened" xmlns="http://www.w3.org/2000/svg" width="29" height="25" viewBox="0 0 29 25" fill="none">
                        <path
                            d="M0 0H28.3333V3.125H0V0ZM0 10.9375H28.3333V14.0625H0V10.9375ZM0 21.875H28.3333V25H0V21.875Z"
                            fill="white" />
                    </svg>
				
				<svg class="closed" viewBox="0 -0.5 21 21" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" fill="#000000"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <title>close [#ffffff]</title> <desc>Created with Sketch.</desc> <defs> </defs> <g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd"> <g id="Dribbble-Light-Preview" transform="translate(-419.000000, -240.000000)" fill="#ffffff"> <g id="icons" transform="translate(56.000000, 160.000000)"> <polygon id="close-[#ffffff]" points="375.0183 90 384 98.554 382.48065 100 373.5 91.446 364.5183 100 363 98.554 371.98065 90 363 81.446 364.5183 80 373.5 88.554 382.48065 80 384 81.446"> </polygon> </g> </g> </g> </g></svg>
				
                </div>
			
			<style>
				.header.open .closed{
					display: block;
				}
				
				.header.open .opened{
					display: none;
				}
				
				.header .closed{
					width: 32px;
				}
				
				.header:not(.open) .closed{
					display: none;
				}
				
				/* Mobile Menu Styles */
				@media (max-width: 768px) {
					/* Hide legacy white slide-out menu */
					.mobile-menu,
					.mobile-menu-header {
						display: none !important;
					}
				}
				
				@media (min-width: 769px) {
					.mobile-menu {
						display: none !important;
					}
					.overlay {
						display: none;
					}
				}

				/* Fully disable the overlay (no dimming above the menu bar) */
				.overlay {
					display: none !important;
					visibility: hidden !important;
					opacity: 0 !important;
					pointer-events: none !important;
				}
				
				/* Header wrapper transition for smooth height changes */
				.header__wrapper {
					transition: height 0.3s ease;
				}
				
				/* Login button hover effect */
				/* .header .login:hover {
					background: #5a5a5a !important;
					transform: translateY(-1px);
					box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
				} */
				
				/* Header Auth Buttons Styles - High Specificity */
				.header .header__inner .header__auth-buttons {
					display: flex !important;
					flex-direction: row !important;
					align-items: center !important;
					gap: 12px !important;
					justify-content: flex-end !important;
				}
				
				.header .header__inner .header__auth-buttons .login,
				.header .header__inner .header__auth-buttons .register {
					display: inline-flex !important;
					align-items: center !important;
					justify-content: center !important;
					padding: 8px 16px !important;
					border-radius: 6px !important;
					transition: all 0.3s ease !important;
					white-space: nowrap !important;
					min-width: 100px !important;
					text-decoration: none !important;
					font-size: 20px !important;
					font-weight: 500 !important;
					color: white !important;
					margin: 0 !important;
					float: none !important;
					clear: none !important;
					width: auto !important;
					height: auto !important;
				}
				
				/* .header .header__inner .header__auth-buttons .login {
					background: transparent !important;
					border: 1px solid rgba(255, 255, 255, 0.3) !important;
				}
				
				.header .header__inner .header__auth-buttons .login:hover {
					background: rgba(255, 255, 255, 0.1) !important;
					border-color: rgba(255, 255, 255, 0.5) !important;
				} */
				
				.header .header__inner .header__auth-buttons .register {
					background: #ff6b35 !important;
					border: 1px solid #ff6b35 !important;
					color: white !important;
				}
				
				.header .header__inner .header__auth-buttons .register:hover {
					background: #e55a2b !important;
					border-color: #e55a2b !important;
					transform: translateY(-1px) !important;
				}
				
				.header .header__inner .header__auth-buttons svg {
					margin-left: 6px !important;
					flex-shrink: 0 !important;
				}
				
				/* Mobile menu styles - only for the mobile menu, not main header */
				.header__wrapper .header__auth-buttons {
					flex-direction: column !important;
					width: 100% !important;
				}
				
				.header__wrapper .header__auth-buttons .login,
				.header__wrapper .header__auth-buttons .register {
					width: 100% !important;
					justify-content: center !important;
					padding: 12px 16px !important;
					margin-bottom: 8px !important;
				}

				/* Mobile menu: keep desktop look, center items horizontally */
				@media (max-width: 767px) {
					.header.open .header__menu ul {
						display: flex !important;
						flex-direction: column !important;
						align-items: center !important;
						justify-content: center !important;
						gap: 14px !important;
					}

					.header.open .header__menu ul li {
						width: auto !important;
						margin-bottom: 0 !important;
						text-align: center !important;
					}

					.header.open .header__menu ul li a {
						text-align: center !important;
						display: inline-flex !important;
						align-items: center !important;
						justify-content: center !important;
					}
				}
				
				/* Ensure no conflicting styles */
				.header .header__inner .header__auth-buttons * {
					box-sizing: border-box !important;
				}
			
				/* Header Actions Container */
				.header-actions {
					display: flex;
					align-items: center;
					gap: 16px;
					margin-top: 8px;
				}

				/* Enhanced User Menu Styles */
				.user-menu {
					position: relative;
					display: flex;
					align-items: center;
				}

				.user-menu .login {
					background: rgba(255, 255, 255, 0.15) !important;
					border: 2px solid rgba(255, 255, 255, 0.3) !important;
					color: white !important;
					padding: 12px 20px !important;
					border-radius: 50px !important;
					cursor: pointer !important;
					display: flex !important;
					align-items: center !important;
					gap: 12px !important;
					transition: all 0.3s ease !important;
					font-weight: 500 !important;
					text-decoration: none !important;
					font-size: 14px !important;
					min-width: auto !important;
					white-space: nowrap !important;
					margin: 0 !important;
				}

				.user-menu .login:hover {
					background: rgba(255, 255, 255, 0.25) !important;
					border-color: rgba(255, 255, 255, 0.5) !important;
					transform: translateY(-2px) !important;
				}

				.user-menu .login svg {
					width: 20px !important;
					height: 20px !important;
					flex-shrink: 0 !important;
					margin-left: 0 !important;
					margin-right: 0 !important;
				}

				.user-dropdown-menu {
					position: absolute !important;
					top: 100% !important;
					right: 0 !important;
					background: white !important;
					border-radius: 12px !important;
					box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15) !important;
					min-width: 200px !important;
					display: none !important;
					z-index: 1000 !important;
					overflow: hidden !important;
					border: 1px solid #e5e7eb !important;
					animation: dropdownFadeIn 0.3s ease !important;
					margin-top: 2px !important;
					transform: translateY(0) !important;
				}

				.user-menu:hover .user-dropdown-menu {
					display: block !important;
				}

				@keyframes dropdownFadeIn {
					from {
						opacity: 0;
						transform: translateY(-5px);
					}
					to {
						opacity: 1;
						transform: translateY(0);
					}
				}

				.user-dropdown-menu a {
					display: block !important;
					padding: 14px 20px !important;
					color: #374151 !important;
					text-decoration: none !important;
					border-bottom: 1px solid #f3f4f6 !important;
					transition: all 0.2s ease !important;
					font-weight: 500 !important;
					font-size: 14px !important;
					margin: 0 !important;
				}

				.user-dropdown-menu a:last-child {
					border-bottom: none !important;
				}

				.user-dropdown-menu a:hover {
					background: #f8fafc !important;
					color: #8b5a96 !important;
					padding-left: 24px !important;
				}

				.user-dropdown-info {
					padding: 16px 20px !important;
					background: #f8fafc !important;
				}

				.user-dropdown-name {
					font-weight: 600 !important;
					font-size: 15px !important;
					color: #1f2937 !important;
					margin-bottom: 4px !important;
				}

				.user-dropdown-email {
					font-size: 13px !important;
					color: #6b7280 !important;
				}

				.user-dropdown-divider {
					height: 1px !important;
					background: #e5e7eb !important;
					margin: 0 !important;
				}

				.user-dropdown-logout {
					display: flex !important;
					align-items: center !important;
					gap: 10px !important;
					padding: 14px 20px !important;
					color: #ef4444 !important;
					text-decoration: none !important;
					font-weight: 500 !important;
					font-size: 14px !important;
					transition: all 0.2s ease !important;
				}

				.user-dropdown-logout:hover {
					background: #fef2f2 !important;
					color: #dc2626 !important;
					padding-left: 24px !important;
				}

				.user-dropdown-logout svg {
					width: 16px !important;
					height: 16px !important;
					flex-shrink: 0 !important;
				}

				.user-dropdown-training {
					display: flex !important;
					align-items: center !important;
					gap: 10px !important;
					padding: 14px 20px !important;
					color: #8b5a96 !important;
					text-decoration: none !important;
					font-weight: 500 !important;
					font-size: 14px !important;
					transition: all 0.2s ease !important;
				}

				.user-dropdown-training:hover {
					background: #f5f3f7 !important;
					color: #6d4576 !important;
					padding-left: 24px !important;
				}

				.user-dropdown-training svg {
					width: 16px !important;
					height: 16px !important;
					flex-shrink: 0 !important;
				}

				/* Navigation Badge Styles */
				.nav-badge {
					background: #f59e0b !important;
					color: white !important;
					font-size: 10px !important;
					font-weight: 600 !important;
					padding: 2px 6px !important;
					border-radius: 10px !important;
					margin-left: 8px !important;
					text-transform: uppercase !important;
					letter-spacing: 0.5px !important;
					display: inline-block !important;
					line-height: 1.2 !important;
					min-width: 60px !important;
					text-align: center !important;
				}

				/* Arrow indicator */
				.user-menu .login::after {
					content: "\f078" !important;
					font-family: "Font Awesome 6 Free" !important;
					font-weight: 900 !important;
					font-size: 10px !important;
					margin-left: 8px !important;
					transition: transform 0.3s ease !important;
				}

				.user-menu:hover .login::after {
					transform: rotate(180deg) !important;
				}

				/* Override any conflicting styles */
				.user-menu * {
					box-sizing: border-box !important;
				}

				.user-menu .user-dropdown-menu {
					margin: 0 !important;
					padding: 0 !important;
				}
			</style>

                <a href="<?php echo home_url(); ?>" class="logo">
                    <?php
                    $image = get_field('header_logo', 'option');

                    if ($image) {
                        echo '<img src="' . esc_url($image) . '" alt="logo" class="logo__main">';
                    }
                    ?>
                </a>

                <?php if (is_user_logged_in()): ?>
                    <!-- Show user menu for logged in users -->
                    <div class="header-actions">
                        <!-- User Menu -->
                        <div class="user-menu">
                            <a class="login text text-white flex align-center w-max">
                                <span class="user-name-responsive"><?php echo esc_html(wp_get_current_user()->display_name); ?></span>
                                <svg width="29" height="31" viewBox="0 0 29 31" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" clip-rule="evenodd"
                                        d="M14.5 12.4166C15.1074 12.4166 15.7088 12.297 16.2699 12.0646C16.831 11.8322 17.3409 11.4915 17.7704 11.062C18.1998 10.6325 18.5405 10.1227 18.7729 9.56156C19.0054 9.00043 19.125 8.39901 19.125 7.79165C19.125 7.18428 19.0054 6.58287 18.7729 6.02174C18.5405 5.4606 18.1998 4.95075 17.7704 4.52128C17.3409 4.09181 16.831 3.75113 16.2699 3.5187C15.7088 3.28628 15.1074 3.16665 14.5 3.16665C13.2734 3.16665 12.097 3.65392 11.2296 4.52128C10.3623 5.38863 9.875 6.56502 9.875 7.79165C9.875 9.01827 10.3623 10.1947 11.2296 11.062C12.097 11.9294 13.2734 12.4166 14.5 12.4166ZM14.5 15.5C16.5444 15.5 18.505 14.6879 19.9506 13.2423C21.3962 11.7967 22.2083 9.83602 22.2083 7.79165C22.2083 5.74727 21.3962 3.78662 19.9506 2.34103C18.505 0.895439 16.5444 0.083313 14.5 0.083313C12.4556 0.083313 10.495 0.895439 9.04939 2.34103C7.60379 3.78662 6.79167 5.74727 6.79167 7.79165C6.79167 9.83602 7.60379 11.7967 9.04939 13.2423C10.495 14.6879 12.4556 15.5 14.5 15.5ZM3.15179 20.8156C4.917 18.4754 7.57637 17.0416 10.9757 17.0416H18.0243C21.4236 17.0416 24.083 18.4754 25.8482 20.8156C27.5764 23.1081 28.375 26.1621 28.375 29.375C28.375 29.7839 28.2126 30.176 27.9235 30.4651C27.6343 30.7542 27.2422 30.9166 26.8333 30.9166C26.4245 30.9166 26.0323 30.7542 25.7432 30.4651C25.4541 30.176 25.2917 29.7839 25.2917 29.375C25.2917 26.6277 24.6041 24.2875 23.3877 22.6718C22.2083 21.1086 20.4616 20.125 18.0227 20.125H10.9773C8.53837 20.125 6.79167 21.1086 5.61229 22.6718C4.39438 24.2875 3.70833 26.6277 3.70833 29.375C3.70833 29.7839 3.54591 30.176 3.25679 30.4651C2.96767 30.7542 2.57554 30.9166 2.16667 30.9166C1.75779 30.9166 1.36566 30.7542 1.07654 30.4651C0.787425 30.176 0.625 29.7839 0.625 29.375C0.625 26.1621 1.42358 23.1081 3.15179 20.8156Z"
                                        fill="white" />
                                    <path fill-rule="evenodd" clip-rule="evenodd"
                                        d="M0.625 29.375C0.625 28.9661 0.787425 28.574 1.07654 28.2849C1.36566 27.9957 1.75779 27.8333 2.16667 27.8333H26.7748C27.1836 27.8333 27.5758 27.9957 27.8649 28.2849C28.154 28.574 28.3164 28.9661 28.3164 29.375C28.3164 29.7839 28.154 30.176 27.8649 30.4651C27.5758 30.7542 27.1836 30.9166 26.7748 30.9166H2.16667C1.75779 30.9166 1.36566 30.7542 1.07654 30.4651C0.787425 30.176 0.625 29.7839 0.625 29.375Z"
                                        fill="white" />
                                </svg>
                            </a>
                            <div class="user-dropdown-menu">
                                 <?php 
                                 $current_user = wp_get_current_user();
                                 $full_name = trim($current_user->first_name . ' ' . $current_user->last_name);
                                 if (empty($full_name)) {
                                     $full_name = $current_user->display_name;
                                 }
                                 
                                 // Check if user is admin
                                 $admin_roles = array('administrator', 'iipm_admin', 'iipm_corporate_admin');
                                 $is_admin_user = !empty(array_intersect($admin_roles, $current_user->roles));
                                 ?>
                                 <div class="user-dropdown-info">
                                     <div class="user-dropdown-name"><?php echo esc_html($full_name); ?></div>
                                     <div class="user-dropdown-email"><?php echo esc_html($current_user->user_email); ?></div>
                                 </div>
                                 <div class="user-dropdown-divider"></div>
                                 <?php if (!$is_admin_user): ?>
                                     <a href="<?php echo home_url('/member-portal/'); ?>" class="user-dropdown-training">
                                         <svg xmlns="http://www.w3.org/2000/svg" class="match-icon-text" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>
                                         Go to Training
                                     </a>
                                     <div class="user-dropdown-divider"></div>
                                 <?php endif; ?>
                                 <?php if ($is_admin_user): ?>
                                     <?php 
                                     // Get role-based navigation for admins
                                     $navigation = IIPM_Navigation_Manager::get_role_based_navigation();
                                     foreach ($navigation as $menu_title => $menu_data): 
                                         // Handle both string URLs and array with badge
                                         if (is_array($menu_data)) {
                                             $menu_url = $menu_data['url'];
                                             $badge = isset($menu_data['badge']) ? $menu_data['badge'] : '';
                                         } else {
                                             $menu_url = $menu_data;
                                             $badge = '';
                                         }
                                     ?>
                                         <a href="<?php echo esc_url($menu_url); ?>">
                                             <?php echo esc_html($menu_title); ?>
                                             <?php if ($badge): ?>
                                                 <span class="nav-badge"><?php echo esc_html($badge); ?></span>
                                             <?php endif; ?>
                                         </a>
                                     <?php endforeach; ?>
                                     <div class="user-dropdown-divider"></div>
                                 <?php endif; ?>
                                 <a href="<?php echo wp_logout_url(home_url()); ?>" class="user-dropdown-logout">
                                     <svg xmlns="http://www.w3.org/2000/svg" class="match-icon-text" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                                     Log Out
                                 </a>
                             </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Show only login button for non-logged in users -->
                    <div class="header__auth-buttons">
						<a href=<?php echo home_url('/login/'); ?> class="login text text-white flex align-center w-max">Member Login <svg width="37" height="37" viewBox="0 0 37 37" fill="none" xmlns="http://www.w3.org/2000/svg">	
							<path fill-rule="evenodd" clip-rule="evenodd" d="M18.5 15.4166C19.1074 15.4166 19.7088 15.297 20.2699 15.0646C20.831 14.8322 21.3409 14.4915 21.7704 14.062C22.1998 13.6325 22.5405 13.1227 22.7729 12.5616C23.0054 12.0004 23.125 11.399 23.125 10.7916C23.125 10.1843 23.0054 9.58287 22.7729 9.02174C22.5405 8.4606 22.1998 7.95075 21.7704 7.52128C21.3409 7.09181 20.831 6.75113 20.2699 6.5187C19.7088 6.28628 19.1074 6.16665 18.5 6.16665C17.2734 6.16665 16.097 6.65392 15.2296 7.52128C14.3623 8.38863 13.875 9.56502 13.875 10.7916C13.875 12.0183 14.3623 13.1947 15.2296 14.062C16.097 14.9294 17.2734 15.4166 18.5 15.4166ZM18.5 18.5C20.5444 18.5 22.505 17.6879 23.9506 16.2423C25.3962 14.7967 26.2083 12.836 26.2083 10.7916C26.2083 8.74727 25.3962 6.78662 23.9506 5.34103C22.505 3.89544 20.5444 3.08331 18.5 3.08331C16.4556 3.08331 14.495 3.89544 13.0494 5.34103C11.6038 6.78662 10.7917 8.74727 10.7917 10.7916C10.7917 12.836 11.6038 14.7967 13.0494 16.2423C14.495 17.6879 16.4556 18.5 18.5 18.5ZM7.15179 23.8156C8.917 21.4754 11.5764 20.0416 14.9757 20.0416H22.0243C25.4236 20.0416 28.083 21.4754 29.8482 23.8156C31.5764 26.1081 32.375 29.1621 32.375 32.375C32.375 32.7839 32.2126 33.176 31.9235 33.4651C31.6343 33.7542 31.2422 33.9166 30.8333 33.9166C30.4245 33.9166 30.0323 33.7542 29.7432 33.4651C29.4541 33.176 29.2917 32.7839 29.2917 32.375C29.2917 29.6277 28.6041 27.2875 27.3877 25.6718C26.2083 24.1086 24.4616 23.125 22.0227 23.125H14.9773C12.5384 23.125 10.7917 24.1086 9.61229 25.6718C8.39438 27.2875 7.70833 29.6277 7.70833 32.375C7.70833 32.7839 7.54591 33.176 7.25679 33.4651C6.96767 33.7542 6.57554 33.9166 6.16667 33.9166C5.75779 33.9166 5.36566 33.7542 5.07654 33.4651C4.78743 33.176 4.625 32.7839 4.625 32.375C4.625 29.1621 5.42358 26.1081 7.15179 23.8156Z" fill="white"></path>
							<path fill-rule="evenodd" clip-rule="evenodd" d="M4.625 32.375C4.625 31.9661 4.78743 31.574 5.07654 31.2849C5.36566 30.9957 5.75779 30.8333 6.16667 30.8333H30.7748C31.1836 30.8333 31.5758 30.9957 31.8649 31.2849C32.154 31.574 32.3164 31.9661 32.3164 32.375C32.3164 32.7839 32.154 33.176 31.8649 33.4651C31.5758 33.7542 31.1836 33.9166 30.7748 33.9166H6.16667C5.75779 33.9166 5.36566 33.7542 5.07654 33.4651C4.78743 33.176 4.625 32.7839 4.625 32.375Z" fill="white"></path>
							</svg>
						</a>
                    </div>
                <?php endif; ?>

                <div class="header__wrapper">
                    <div class="container">
                        <div class="header__menu">
                            <?php
                            wp_nav_menu(array(
                                'theme_location' => 'header-menu',
                                'container' => false,
                                'menu_class' => '',
                                'depth' => 1,
                            ));
                            ?>
                        </div>
                        <div>
                            <?php if (is_user_logged_in()): ?>
                                <!-- Mobile user menu for logged in users -->
                                <?php 
                                $current_user = wp_get_current_user();
                                $user_roles = $current_user->roles;
                                $is_admin = in_array('administrator', $user_roles) || in_array('iipm_admin', $user_roles);
                                $is_corporate_admin = in_array('iipm_corporate_admin', $user_roles);
                                
                                // Show appropriate mobile link based on user role
                                if ($is_admin || $is_corporate_admin): ?>
                                    <a href="<?php echo home_url('/dashboard/'); ?>" class="login text text-white flex align-center w-max">
                                        <svg width="29" height="31" viewBox="0 0 29 31" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path fill-rule="evenodd" clip-rule="evenodd"
                                                d="M14.5 12.4166C15.1074 12.4166 15.7088 12.297 16.2699 12.0646C16.831 11.8322 17.3409 11.4915 17.7704 11.062C18.1998 10.6325 18.5405 10.1227 18.7729 9.56156C19.0054 9.00043 19.125 8.39901 19.125 7.79165C19.125 7.18428 19.0054 6.58287 18.7729 6.02174C18.5405 5.4606 18.1998 4.95075 17.7704 4.52128C17.3409 4.09181 16.831 3.75113 16.2699 3.5187C15.7088 3.28628 15.1074 3.16665 14.5 3.16665C13.2734 3.16665 12.097 3.65392 11.2296 4.52128C10.3623 5.38863 9.875 6.56502 9.875 7.79165C9.875 9.01827 10.3623 10.1947 11.2296 11.062C12.097 11.9294 13.2734 12.4166 14.5 12.4166ZM14.5 15.5C16.5444 15.5 18.505 14.6879 19.9506 13.2423C21.3962 11.7967 22.2083 9.83602 22.2083 7.79165C22.2083 5.74727 21.3962 3.78662 19.9506 2.34103C18.505 0.895439 16.5444 0.083313 14.5 0.083313C12.4556 0.083313 10.495 0.895439 9.04939 2.34103C7.60379 3.78662 6.79167 5.74727 6.79167 7.79165C6.79167 9.83602 7.60379 11.7967 9.04939 13.2423C10.495 14.6879 12.4556 15.5 14.5 15.5ZM3.15179 20.8156C4.917 18.4754 7.57637 17.0416 10.9757 17.0416H18.0243C21.4236 17.0416 24.083 18.4754 25.8482 20.8156C27.5764 23.1081 28.375 26.1621 28.375 29.375C28.375 29.7839 28.2126 30.176 27.9235 30.4651C27.6343 30.7542 27.2422 30.9166 26.8333 30.9166C26.4245 30.9166 26.0323 30.7542 25.7432 30.4651C25.4541 30.176 25.2917 29.7839 25.2917 29.375C25.2917 26.6277 24.6041 24.2875 23.3877 22.6718C22.2083 21.1086 20.4616 20.125 18.0227 20.125H10.9773C8.53837 20.125 6.79167 21.1086 5.61229 22.6718C4.39438 24.2875 3.70833 26.6277 3.70833 29.375C3.70833 29.7839 3.54591 30.176 3.25679 30.4651C2.96767 30.7542 2.57554 30.9166 2.16667 30.9166C1.75779 30.9166 1.36566 30.7542 1.07654 30.4651C0.787425 30.176 0.625 29.7839 0.625 29.375C0.625 26.1621 1.42358 23.1081 3.15179 20.8156Z"
                                                fill="white" />
                                            <path fill-rule="evenodd" clip-rule="evenodd"
                                                d="M0.625 29.375C0.625 28.9661 0.787425 28.574 1.07654 28.2849C1.36566 27.9957 1.75779 27.8333 2.16667 27.8333H26.7748C27.1836 27.8333 27.5758 27.9957 27.8649 28.2849C28.154 28.574 28.3164 28.9661 28.3164 29.375C28.3164 29.7839 28.154 30.176 27.8649 30.4651C27.5758 30.7542 27.1836 30.9166 26.7748 30.9166H2.16667C1.75779 30.9166 1.36566 30.7542 1.07654 30.4651C0.787425 30.176 0.625 29.7839 0.625 29.375Z"
                                                fill="white" />
                                        </svg>
                                        Dashboard
                                    </a>
                                <?php else: ?>
                                    <a href="<?php echo home_url('/member-portal/'); ?>" class="login text text-white flex align-center w-max">
                                        <svg width="29" height="31" viewBox="0 0 29 31" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path fill-rule="evenodd" clip-rule="evenodd"
                                                d="M14.5 12.4166C15.1074 12.4166 15.7088 12.297 16.2699 12.0646C16.831 11.8322 17.3409 11.4915 17.7704 11.062C18.1998 10.6325 18.5405 10.1227 18.7729 9.56156C19.0054 9.00043 19.125 8.39901 19.125 7.79165C19.125 7.18428 19.0054 6.58287 18.7729 6.02174C18.5405 5.4606 18.1998 4.95075 17.7704 4.52128C17.3409 4.09181 16.831 3.75113 16.2699 3.5187C15.7088 3.28628 15.1074 3.16665 14.5 3.16665C13.2734 3.16665 12.097 3.65392 11.2296 4.52128C10.3623 5.38863 9.875 6.56502 9.875 7.79165C9.875 9.01827 10.3623 10.1947 11.2296 11.062C12.097 11.9294 13.2734 12.4166 14.5 12.4166ZM14.5 15.5C16.5444 15.5 18.505 14.6879 19.9506 13.2423C21.3962 11.7967 22.2083 9.83602 22.2083 7.79165C22.2083 5.74727 21.3962 3.78662 19.9506 2.34103C18.505 0.895439 16.5444 0.083313 14.5 0.083313C12.4556 0.083313 10.495 0.895439 9.04939 2.34103C7.60379 3.78662 6.79167 5.74727 6.79167 7.79165C6.79167 9.83602 7.60379 11.7967 9.04939 13.2423C10.495 14.6879 12.4556 15.5 14.5 15.5ZM3.15179 20.8156C4.917 18.4754 7.57637 17.0416 10.9757 17.0416H18.0243C21.4236 17.0416 24.083 18.4754 25.8482 20.8156C27.5764 23.1081 28.375 26.1621 28.375 29.375C28.375 29.7839 28.2126 30.176 27.9235 30.4651C27.6343 30.7542 27.2422 30.9166 26.8333 30.9166C26.4245 30.9166 26.0323 30.7542 25.7432 30.4651C25.4541 30.176 25.2917 29.7839 25.2917 29.375C25.2917 26.6277 24.6041 24.2875 23.3877 22.6718C22.2083 21.1086 20.4616 20.125 18.0227 20.125H10.9773C8.53837 20.125 6.79167 21.1086 5.61229 22.6718C4.39438 24.2875 3.70833 26.6277 3.70833 29.375C3.70833 29.7839 3.54591 30.176 3.25679 30.4651C2.96767 30.7542 2.57554 30.9166 2.16667 30.9166C1.75779 30.9166 1.36566 30.7542 1.07654 30.4651C0.787425 30.176 0.625 29.7839 0.625 29.375C0.625 26.1621 1.42358 23.1081 3.15179 20.8156Z"
                                                fill="white" />
                                            <path fill-rule="evenodd" clip-rule="evenodd"
                                                d="M0.625 29.375C0.625 28.9661 0.787425 28.574 1.07654 28.2849C1.36566 27.9957 1.75779 27.8333 2.16667 27.8333H26.7748C27.1836 27.8333 27.5758 27.9957 27.8649 28.2849C28.154 28.574 28.3164 28.9661 28.3164 29.375C28.3164 29.7839 28.154 30.176 27.8649 30.4651C27.5758 30.7542 27.1836 30.9166 26.7748 30.9166H2.16667C1.75779 30.9166 1.36566 30.7542 1.07654 30.4651C0.787425 30.176 0.625 29.7839 0.625 29.375Z"
                                                fill="white" />
                                        </svg>
                                        Member Portal
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
								<!-- Show only login button for non-logged in users -->
								<div class="header__auth-buttons">
									<a href=<?php echo home_url('/login/'); ?> class="login text text-white flex align-center w-max">Member Login <svg width="37" height="37" viewBox="0 0 37 37" fill="none" xmlns="http://www.w3.org/2000/svg">	
										<path fill-rule="evenodd" clip-rule="evenodd" d="M18.5 15.4166C19.1074 15.4166 19.7088 15.297 20.2699 15.0646C20.831 14.8322 21.3409 14.4915 21.7704 14.062C22.1998 13.6325 22.5405 13.1227 22.7729 12.5616C23.0054 12.0004 23.125 11.399 23.125 10.7916C23.125 10.1843 23.0054 9.58287 22.7729 9.02174C22.5405 8.4606 22.1998 7.95075 21.7704 7.52128C21.3409 7.09181 20.831 6.75113 20.2699 6.5187C19.7088 6.28628 19.1074 6.16665 18.5 6.16665C17.2734 6.16665 16.097 6.65392 15.2296 7.52128C14.3623 8.38863 13.875 9.56502 13.875 10.7916C13.875 12.0183 14.3623 13.1947 15.2296 14.062C16.097 14.9294 17.2734 15.4166 18.5 15.4166ZM18.5 18.5C20.5444 18.5 22.505 17.6879 23.9506 16.2423C25.3962 14.7967 26.2083 12.836 26.2083 10.7916C26.2083 8.74727 25.3962 6.78662 23.9506 5.34103C22.505 3.89544 20.5444 3.08331 18.5 3.08331C16.4556 3.08331 14.495 3.89544 13.0494 5.34103C11.6038 6.78662 10.7917 8.74727 10.7917 10.7916C10.7917 12.836 11.6038 14.7967 13.0494 16.2423C14.495 17.6879 16.4556 18.5 18.5 18.5ZM7.15179 23.8156C8.917 21.4754 11.5764 20.0416 14.9757 20.0416H22.0243C25.4236 20.0416 28.083 21.4754 29.8482 23.8156C31.5764 26.1081 32.375 29.1621 32.375 32.375C32.375 32.7839 32.2126 33.176 31.9235 33.4651C31.6343 33.7542 31.2422 33.9166 30.8333 33.9166C30.4245 33.9166 30.0323 33.7542 29.7432 33.4651C29.4541 33.176 29.2917 32.7839 29.2917 32.375C29.2917 29.6277 28.6041 27.2875 27.3877 25.6718C26.2083 24.1086 24.4616 23.125 22.0227 23.125H14.9773C12.5384 23.125 10.7917 24.1086 9.61229 25.6718C8.39438 27.2875 7.70833 29.6277 7.70833 32.375C7.70833 32.7839 7.54591 33.176 7.25679 33.4651C6.96767 33.7542 6.57554 33.9166 6.16667 33.9166C5.75779 33.9166 5.36566 33.7542 5.07654 33.4651C4.78743 33.176 4.625 32.7839 4.625 32.375C4.625 29.1621 5.42358 26.1081 7.15179 23.8156Z" fill="white"></path>
										<path fill-rule="evenodd" clip-rule="evenodd" d="M4.625 32.375C4.625 31.9661 4.78743 31.574 5.07654 31.2849C5.36566 30.9957 5.75779 30.8333 6.16667 30.8333H30.7748C31.1836 30.8333 31.5758 30.9957 31.8649 31.2849C32.154 31.574 32.3164 31.9661 32.3164 32.375C32.3164 32.7839 32.154 33.176 31.8649 33.4651C31.5758 33.7542 31.1836 33.9166 30.7748 33.9166H6.16667C5.75779 33.9166 5.36566 33.7542 5.07654 33.4651C4.78743 33.176 4.625 32.7839 4.625 32.375Z" fill="white"></path>
										</svg>
									</a>
								</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Mobile Menu Overlay -->
    <div class="overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9998; visibility: hidden; opacity: 0; transition: all 0.3s ease;"></div>
    
    <!-- Mobile Menu -->
    <div class="mobile-menu" style="position: fixed; top: 0; left: -300px; width: 300px; height: 100%; background: #fff; z-index: 9999; transition: left 0.3s ease; box-shadow: 2px 0 10px rgba(0,0,0,0.1);">
        <div class="mobile-menu-header" style="padding: 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: #333;">Menu</h3>
            <button class="mobile-menu-close" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
        </div>
        <div class="mobile-menu-content" style="padding: 20px;">
            <nav class="mobile-nav">
                <ul style="list-style: none; padding: 0; margin: 0;">
                    <li style="margin-bottom: 15px;"><a href="<?php echo home_url(); ?>" style="text-decoration: none; color: #333; font-size: 16px; display: block; padding: 10px 0; border-bottom: 1px solid #f0f0f0;">Home</a></li>
                    <li style="margin-bottom: 15px;"><a href="<?php echo home_url('/about'); ?>" style="text-decoration: none; color: #333; font-size: 16px; display: block; padding: 10px 0; border-bottom: 1px solid #f0f0f0;">About</a></li>
                    <li style="margin-bottom: 15px;"><a href="<?php echo home_url('/services'); ?>" style="text-decoration: none; color: #333; font-size: 16px; display: block; padding: 10px 0; border-bottom: 1px solid #f0f0f0;">Services</a></li>
                    <li style="margin-bottom: 15px;"><a href="<?php echo home_url('/contact'); ?>" style="text-decoration: none; color: #333; font-size: 16px; display: block; padding: 10px 0; border-bottom: 1px solid #f0f0f0;">Contact</a></li>
                    <?php if (is_user_logged_in()): ?>
                        <li style="margin-bottom: 15px;"><a href="<?php echo home_url('/dashboard'); ?>" style="text-decoration: none; color: #333; font-size: 16px; display: block; padding: 10px 0; border-bottom: 1px solid #f0f0f0;">Dashboard</a></li>
                        <li style="margin-bottom: 15px;"><a href="<?php echo wp_logout_url(); ?>" style="text-decoration: none; color: #333; font-size: 16px; display: block; padding: 10px 0; border-bottom: 1px solid #f0f0f0;">Logout</a></li>
                    <?php else: ?>
                        <li style="margin-bottom: 15px;"><a href="<?php echo home_url('/login'); ?>" style="text-decoration: none; color: #333; font-size: 16px; display: block; padding: 10px 0; border-bottom: 1px solid #f0f0f0;">Login</a></li>
                        <li style="margin-bottom: 15px;"><a href="<?php echo home_url('/register'); ?>" style="text-decoration: none; color: #333; font-size: 16px; display: block; padding: 10px 0; border-bottom: 1px solid #f0f0f0;">Register</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </div>

    <div id="content" class="site-content">

	<style>
		.request-course-item a {
			color: #fff;
			font-family: Gabarito;
			font-size: 55px;
			font-style: normal;
			font-weight: 400;
			line-height: 93%;
			text-transform: capitalize;
			text-decoration: none;
			white-space: nowrap;
		}

		.request-course-item {
			list-style: none;
		}

		.match-icon-text {
			position: relative;
			top: 5px;
			right: 5px;
		}
	</style>
