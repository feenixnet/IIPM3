<?php
/**
 * Navigation Manager for IIPM Portal
 * Handles role-based navigation and breadcrumbs
 */

class IIPM_Navigation_Manager {
    
    /**
     * Get navigation menu based on user role
     */
    public static function get_role_based_navigation() {
        if (!is_user_logged_in()) {
            return array();
        }
        
        $current_user = wp_get_current_user();
        $user_roles = $current_user->roles;
        $navigation = array();
        
        // Site Administrator Navigation - NO Member Portal
        if (in_array('administrator', $user_roles)) {
            $navigation = array(
                'Dashboard' => home_url('/dashboard/'),
                'Admin Management' => home_url('/super-admin-management/'),
                'Member Management' => home_url('/member-management/'),
                'Organisation Management' => home_url('/organisation-management/'),
                
                'Bulk Member Import' => array('url' => home_url('/bulk-import/')),
                'CPD Reports' => home_url('/cpd-reports/'),
                'CPD Administration' => home_url('/cpd-admin/'),
                'Course Management' => home_url('/course-management/'),
                'Payment Management' => home_url('/payment-management/'),
                'Leave Administration' => home_url('/leave-admin/'),
                'Logout' => wp_logout_url(home_url())
            );
        }
        // Organization Administrator Navigation - NO Member Portal
        elseif (in_array('iipm_corporate_admin', $user_roles)) {
            $navigation = array(
                'Dashboard' => home_url('/dashboard/'),
                'Employee Management' => home_url('/member-management/'),
                
                'Bulk Member Import' => home_url('/bulk-import/'),
                'CPD Reports' => home_url('/cpd-reports/'),
                'Leave Management' => home_url('/leave-admin/'),
                'My Leave Requests' => home_url('/leave-request/'),
                'My Profile' => home_url('/profile/'),
                'Logout' => wp_logout_url(home_url())
            );
        }
        // Council Member Navigation - HAS Member Portal
        elseif (in_array('iipm_council_member', $user_roles)) {
            $navigation = array(
                'Member Portal' => home_url('/member-portal/'),
                'Courses' => home_url('/cpd-courses/'),
                'Request a course' => home_url('/cpd-course-request/'),
                'CPD Reports' => home_url('/cpd-reports/'),
                'Leave Requests' => home_url('/leave-request/'),
                'My Profile' => home_url('/profile/'),
                'Logout' => wp_logout_url(home_url())
            );
        }
        // Regular Member Navigation - HAS Member Portal
        else {
            $navigation = array(
                'Member Portal' => home_url('/member-portal/'),
                'Courses' => home_url('/cpd-courses/'),
                'Request a course' => home_url('/cpd-course-request/'),
                'Leave Requests' => home_url('/leave-request/'),
                'My Profile' => home_url('/profile/'),
                'Logout' => wp_logout_url(home_url())
            );
        }
        
        return $navigation;
    }
    
    /**
     * Generate breadcrumbs for current page
     */
    public static function get_breadcrumbs() {
        global $post;
        
        $breadcrumbs = array();
        $breadcrumbs[] = array(
            'title' => 'Home',
            'url' => home_url('/')
        );
        
        // Get current page info
        if (is_page()) {
            $page_template = get_page_template_slug($post->ID);
            $page_title = get_the_title();
            $current_url = $_SERVER['REQUEST_URI'];
            
            // Map template files to page titles
            $template_mapping = array(
                'template-member-portal.php' => 'Member Portal',
                'template-profile.php' => 'My Profile',
                'template-cpd-courses.php' => 'Courses',
                'template-cpd-course-request.php' => 'Request a course',
                'template-cpd-reports.php' => 'CPD Reports',
                'template-cpd-admin.php' => 'CPD Administration',
                'template-course-management.php' => 'Course Management',
                'template-member-management.php' => 'Member Management',
                'template-organisation-management.php' => 'Organisation Management',
                'template-organisation-details.php' => 'Organisation Details',
                'template-member-details.php' => 'Member Details',
                'template-bulk-import.php' => 'Bulk Member Import',
                'template-admin-invitations.php' => 'Admin Invitations',
                'template-leave-request.php' => 'Leave Request',
                'template-leave-admin.php' => 'Leave Administration',
                'template-dashboard.php' => 'Dashboard',
                'template-payment-management.php' => 'Payment Management',
                'template-notifications.php' => 'Notifications',
                'template-super-admin.php' => 'Admin Management',
            );
            
            // Check if we have a template mapping
            if ($page_template && isset($template_mapping[$page_template])) {
                $page_title = $template_mapping[$page_template];
            }
            
            // Admin breadcrumb hierarchy logic
            if (is_user_logged_in()) {
                $current_user = wp_get_current_user();
                $user_roles = $current_user->roles;
                
                if (in_array('administrator', $user_roles) || in_array('iipm_corporate_admin', $user_roles)) {
                    // Admin breadcrumb structure
                    // Pages under Dashboard
                    $dashboard_child_pages = array(
                        'template-course-management.php',
                        'template-member-management.php',
                        'template-leave-admin.php',
                    );
                    
                    // Pages that are standalone (not under Dashboard)
                    $standalone_pages = array(
                        'template-organisation-management.php',
                        'template-bulk-import.php', // Only standalone if not accessed from Member Management
                        'template-cpd-reports.php',
                        'template-cpd-admin.php',
                        'template-payment-management.php',
                        'template-super-admin.php', // Admin Management
                    );
                    
                    // Add Dashboard as parent for dashboard child pages (but not if current page IS Dashboard)
                    if (in_array($page_template, $dashboard_child_pages)) {
                        if (!self::breadcrumb_exists($breadcrumbs, 'Dashboard')) {
                            $breadcrumbs[] = array(
                                'title' => 'Dashboard',
                                'url' => home_url('/dashboard/')
                            );
                        }
                    }
                    // Note: If current page IS Dashboard, we don't add it as parent (it will be added as current page later)
                    
                    // Member Management children
                    if ($page_template === 'template-member-details.php') {
                        // Add Dashboard first
                        if (!self::breadcrumb_exists($breadcrumbs, 'Dashboard')) {
                            $breadcrumbs[] = array(
                                'title' => 'Dashboard',
                                'url' => home_url('/dashboard/')
                            );
                        }
                        // Add Member Management
                        if (!self::breadcrumb_exists($breadcrumbs, 'Member Management')) {
                            $breadcrumbs[] = array(
                                'title' => 'Member Management',
                                'url' => home_url('/member-management/')
                            );
                        }
                        // Get member name from URL parameter
                        $user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
                        if ($user_id) {
                            global $wpdb;
                            $member_profile = $wpdb->get_row($wpdb->prepare(
                                "SELECT first_name, sur_name FROM {$wpdb->prefix}test_iipm_member_profiles WHERE user_id = %d",
                                $user_id
                            ));
                            if ($member_profile) {
                                $member_name = trim(($member_profile->first_name ?? '') . ' ' . ($member_profile->sur_name ?? ''));
                                if ($member_name) {
                                    $page_title = $member_name;
                                } else {
                                    // Fallback if name is empty
                                    $page_title = 'Member Details';
                                }
                            } else {
                                // Fallback if profile not found
                                $page_title = 'Member Details';
                            }
                        } else {
                            // Fallback if no user ID
                            $page_title = 'Member Details';
                        }
                    }
                    
                    // Bulk Member Import - check if accessed through Member Management
                    if ($page_template === 'template-bulk-import.php') {
                        // Check referrer or URL parameter to see if accessed from Member Management
                        $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
                        if (strpos($referrer, 'member-management') !== false || isset($_GET['from']) && $_GET['from'] === 'member-management') {
                            // Add Dashboard first
                            if (!self::breadcrumb_exists($breadcrumbs, 'Dashboard')) {
                                $breadcrumbs[] = array(
                                    'title' => 'Dashboard',
                                    'url' => home_url('/dashboard/')
                                );
                            }
                            // Add Member Management
                            if (!self::breadcrumb_exists($breadcrumbs, 'Member Management')) {
                                $breadcrumbs[] = array(
                                    'title' => 'Member Management',
                                    'url' => home_url('/member-management/')
                                );
                            }
                        }
                        // If not from Member Management, it's standalone (no parent)
                    }
                    
                    // Organisation Details
                    if ($page_template === 'template-organisation-details.php') {
                        // Add Organisation Management as parent
                        if (!self::breadcrumb_exists($breadcrumbs, 'Organisation Management')) {
                            $breadcrumbs[] = array(
                                'title' => 'Organisation Management',
                                'url' => home_url('/organisation-management/')
                            );
                        }
                        // Get organisation name from URL parameter
                        $org_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
                        if ($org_id) {
                            global $wpdb;
                            $organisation = $wpdb->get_row($wpdb->prepare(
                                "SELECT name FROM {$wpdb->prefix}test_iipm_organisations WHERE id = %d",
                                $org_id
                            ));
                            if ($organisation && !empty($organisation->name)) {
                                $page_title = $organisation->name;
                            }
                        }
                    }
                } else {
                    // For members: only show Member Portal if current page IS Member Portal itself
                    // Do NOT add Member Portal for other pages like Courses, Profile, etc. (they are siblings, not children)
                    if ($page_template === 'template-member-portal.php') {
                        if (!self::breadcrumb_exists($breadcrumbs, 'Member Portal')) {
                            $breadcrumbs[] = array(
                                'title' => 'Member Portal',
                                'url' => home_url('/member-portal/')
                            );
                        }
                    }
                }
            }
            
            // Add parent pages if they exist
            if ($post->post_parent) {
                $parent_id = $post->post_parent;
                $parents = array();
                while ($parent_id) {
                    $parent = get_post($parent_id);
                    $parents[] = array(
                        'title' => $parent->post_title,
                        'url' => get_permalink($parent->ID)
                    );
                    $parent_id = $parent->post_parent;
                }
                $parents = array_reverse($parents);
                $breadcrumbs = array_merge($breadcrumbs, $parents);
            }
            
            // Add current page only if it's not already in the breadcrumb trail
            // Check if the current page title matches any existing breadcrumb
            $page_already_exists = false;
            foreach ($breadcrumbs as $index => $crumb) {
                if (isset($crumb['title']) && $crumb['title'] === $page_title) {
                    $page_already_exists = true;
                    // Mark the existing one as current
                    $breadcrumbs[$index]['current'] = true;
                    break;
                }
            }
            
            // Only add current page if it doesn't already exist in the trail
            if (!$page_already_exists) {
                $breadcrumbs[] = array(
                    'title' => $page_title,
                    'url' => get_permalink($post->ID),
                    'current' => true
                );
            }
        }
        
        return $breadcrumbs;
    }
    
    /**
     * Get URL for parent breadcrumb
     */
    private static function get_parent_url($parent_title) {
        $parent_urls = array(
            'CPD Management' => home_url('/cpd-reports/'),
            'Member Management' => home_url('/member-management/'),
            'Organisation Management' => home_url('/organisation-management/'),
            'Leave Management' => home_url('/leave-request/'),
        );
        
        return isset($parent_urls[$parent_title]) ? $parent_urls[$parent_title] : null;
    }
    
    /**
     * Display breadcrumbs HTML
     */
    public static function display_breadcrumbs() {
        $breadcrumbs = self::get_breadcrumbs();
        
        // Always show breadcrumbs if we have more than just Home
        // For member details page, ensure we always show breadcrumbs even if get_breadcrumbs() fails
        if (empty($breadcrumbs) || count($breadcrumbs) <= 1) {
            // Check if we're on member details URL (works even if it's not a normal Page context)
            $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            if (strpos($request_uri, 'member-details') !== false) {
                // Force breadcrumbs for member details even if generation failed
                $breadcrumbs = array(
                    array('title' => 'Home', 'url' => home_url('/')),
                    array('title' => 'Dashboard', 'url' => home_url('/dashboard/')),
                    array('title' => 'Member Management', 'url' => home_url('/member-management/')),
                );
                // Try to get member name
                $user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
                if ($user_id) {
                    global $wpdb;
                    $member_profile = $wpdb->get_row($wpdb->prepare(
                        "SELECT first_name, sur_name FROM {$wpdb->prefix}test_iipm_member_profiles WHERE user_id = %d",
                        $user_id
                    ));
                    if ($member_profile) {
                        $member_name = trim(($member_profile->first_name ?? '') . ' ' . ($member_profile->sur_name ?? ''));
                        if ($member_name) {
                            $breadcrumbs[] = array('title' => $member_name, 'url' => '', 'current' => true);
                        } else {
                            $breadcrumbs[] = array('title' => 'Member Details', 'url' => '', 'current' => true);
                        }
                    } else {
                        $breadcrumbs[] = array('title' => 'Member Details', 'url' => '', 'current' => true);
                    }
                } else {
                    $breadcrumbs[] = array('title' => 'Member Details', 'url' => '', 'current' => true);
                }
            } else {
                return; // Don't show breadcrumbs if only Home
            }
        }
        
        echo '<div class="iipm-breadcrumbs" style="margin-top: 10px; margin-bottom: 20px;">';
        echo '<nav aria-label="Breadcrumb" style="display: flex; align-items: center; flex-wrap: wrap; gap: 8px; font-size: 0.9rem;">';
        
        $total = count($breadcrumbs);
        foreach ($breadcrumbs as $index => $crumb) {
            $is_last = ($index === $total - 1);
            
            if ($is_last || isset($crumb['current'])) {
                echo '<span class="breadcrumb-current" style="color: rgba(255,255,255,0.8); font-weight: 500;">' . esc_html($crumb['title']) . '</span>';
            } else {
                echo '<a href="' . esc_url($crumb['url']) . '" class="breadcrumb-link" style="color: rgba(255,255,255,0.9); text-decoration: none; transition: color 0.2s;">' . esc_html($crumb['title']) . '</a>';
            }
            
            if (!$is_last) {
                echo '<span class="breadcrumb-separator" style="color: rgba(255,255,255,0.6); margin: 0 4px;">/</span>';
            }
        }
        
        echo '</nav>';
        echo '</div>';
    }
    
    /**
     * Check if breadcrumb already exists
     */
    private static function breadcrumb_exists($breadcrumbs, $title) {
        foreach ($breadcrumbs as $crumb) {
            if ($crumb['title'] === $title) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Generate page hierarchy suggestions
     */
    public static function get_page_hierarchy() {
        return array(
            'admin' => array(
                'Dashboard' => array(
                    'Member Management' => array(
                        'All Users',
                        'Bulk Member Import',
                        'Admin Invitations',
                        'Organisation Management'
                    ),
                    'CPD Management' => array(
                        'CPD Reports',
                        'CPD Administration',
                        'Course Management'
                    ),
                    'Leave Management' => array(
                        'Leave Administration',
                        'My Leave Requests'
                    )
                )
            ),
            'corporate_admin' => array(
                'Dashboard' => array(
                    'Employee Management' => array(
                        'All Employees',
                        'Bulk Member Import',
                        'Employee Invitations'
                    ),
                    'CPD Oversight' => array(
                        'Employee CPD Reports',
                        'Compliance Monitoring'
                    ),
                    'Leave Management' => array(
                        'Leave Administration',
                        'My Leave Requests'
                    )
                )
            ),
            'member' => array(
                'Member Portal' => array(
                    'CPD Portal' => array(
                        'Browse Courses',
                        'Courses',
                        'My Learning',
                        'Progress Tracking',
                        'Certificates'
                    ),
                    'Leave Management' => array(
                        'My Leave Requests',
                        'Submit New Request'
                    ),
                    'Profile' => array(
                        'Personal Information',
                        'Professional Details',
                        'Account Settings'
                    )
                )
            )
        );
    }
} 