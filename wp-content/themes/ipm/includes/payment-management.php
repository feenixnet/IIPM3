<?php
/**
 * Payment Management APIs
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!class_exists('TCPDF')) {
	require_once get_template_directory() . '/includes/tcpdf/tcpdf.php';
}

define('IIPM_PM_ORDER_AMOUNT', 100.00);
define('IIPM_PM_CURRENCY_SYMBOL', '$');

// Using WooCommerce wp_wc_orders table instead of custom table
// add_action('after_setup_theme', 'iipm_pm_maybe_create_tables');

/**
 * Get available payment order statuses (WooCommerce format with wc- prefix).
 */
function iipm_pm_get_order_statuses() {
	return array(
		'wc-pending'    => __('Pending', 'iipm'),
		'wc-processing' => __('Processing', 'iipm'),
		'wc-on-hold'    => __('On Hold', 'iipm'),
		'wc-completed'  => __('Completed', 'iipm'),
		'wc-cancelled'  => __('Cancelled', 'iipm'),
		'wc-refunded'   => __('Refunded', 'iipm'),
		'wc-failed'     => __('Failed', 'iipm')
	);
}

/**
 * Get status label by status key (removes wc- prefix and capitalizes).
 */
function iipm_pm_get_status_label($status) {
	if (empty($status)) {
		return '';
	}
	
	$statuses = iipm_pm_get_order_statuses();
	
	// If exact match found, return it
	if (isset($statuses[$status])) {
		return $statuses[$status];
	}
	
	// Remove wc- prefix if present
	$clean_status = str_replace('wc-', '', $status);
	
	// Convert to title case
	$label = str_replace(array('-', '_'), ' ', $clean_status);
	$label = ucwords($label);
	
	return $label;
}

/**
 * Helper to verify capability.
 */
function iipm_pm_user_can_manage() {
	return current_user_can('administrator') || current_user_can('manage_iipm_members');
}

/**
 * AJAX: Get payment statistics for dashboard.
 */
function iipm_get_payment_stats() {
	$nonce = sanitize_text_field($_POST['nonce'] ?? '');
	if (!wp_verify_nonce($nonce, 'iipm_payment_nonce')) {
		wp_send_json_error('Security check failed');
	}

	if (!iipm_pm_user_can_manage()) {
		wp_send_json_error('Insufficient permissions');
	}

	global $wpdb;

	$filter_year = intval($_POST['filter_year'] ?? date('Y'));
	if ($filter_year < 2019 || $filter_year > date('Y')) {
		$filter_year = date('Y');
	}

	// Get total target (sum of all orders for the year)
	$total_target = $wpdb->get_var($wpdb->prepare(
		"SELECT COALESCE(SUM(o.total_amount), 0)
		FROM {$wpdb->prefix}wc_orders o
		INNER JOIN {$wpdb->prefix}wc_orders_meta om ON o.id = om.order_id AND om.meta_key = '_iipm_invoice_year'
		WHERE o.type = 'shop_order' AND om.meta_value = %d",
		$filter_year
	));

	// Get total paid (sum of completed orders for the year)
	$total_paid = $wpdb->get_var($wpdb->prepare(
		"SELECT COALESCE(SUM(o.total_amount), 0)
		FROM {$wpdb->prefix}wc_orders o
		INNER JOIN {$wpdb->prefix}wc_orders_meta om ON o.id = om.order_id AND om.meta_key = '_iipm_invoice_year'
		WHERE o.type = 'shop_order' AND o.status = 'wc-completed' AND om.meta_value = %d",
		$filter_year
	));

	// Get organization paid amount (completed orders for organizations)
	$org_paid = $wpdb->get_var($wpdb->prepare(
		"SELECT COALESCE(SUM(o.total_amount), 0)
		FROM {$wpdb->prefix}wc_orders o
		INNER JOIN {$wpdb->prefix}wc_orders_meta om_year ON o.id = om_year.order_id AND om_year.meta_key = '_iipm_invoice_year'
		INNER JOIN {$wpdb->prefix}wc_orders_meta om_org ON o.id = om_org.order_id AND om_org.meta_key = '_iipm_org_id'
		WHERE o.type = 'shop_order' AND o.status = 'wc-completed' AND om_year.meta_value = %d",
		$filter_year
	));

	// Get individual paid amount (completed orders for individuals)
	$individual_paid = $wpdb->get_var($wpdb->prepare(
		"SELECT COALESCE(SUM(o.total_amount), 0)
		FROM {$wpdb->prefix}wc_orders o
		INNER JOIN {$wpdb->prefix}wc_orders_meta om_year ON o.id = om_year.order_id AND om_year.meta_key = '_iipm_invoice_year'
		INNER JOIN {$wpdb->prefix}wc_orders_meta om_user ON o.id = om_user.order_id AND om_user.meta_key = '_iipm_user_id'
		WHERE o.type = 'shop_order' AND o.status = 'wc-completed' AND om_year.meta_value = %d",
		$filter_year
	));

	// Get total order count
	$total_orders = $wpdb->get_var($wpdb->prepare(
		"SELECT COUNT(*)
		FROM {$wpdb->prefix}wc_orders o
		INNER JOIN {$wpdb->prefix}wc_orders_meta om ON o.id = om.order_id AND om.meta_key = '_iipm_invoice_year'
		WHERE o.type = 'shop_order' AND om.meta_value = %d",
		$filter_year
	));

	wp_send_json_success(array(
		'total_target' => floatval($total_target),
		'total_paid' => floatval($total_paid),
		'org_paid' => floatval($org_paid),
		'individual_paid' => floatval($individual_paid),
		'total_orders' => intval($total_orders)
	));
}
add_action('wp_ajax_iipm_get_payment_stats', 'iipm_get_payment_stats');

/**
 * AJAX: Fetch users for payment management.
 */
function iipm_get_payment_users() {
	$nonce = sanitize_text_field($_POST['nonce'] ?? '');
	if (!wp_verify_nonce($nonce, 'iipm_payment_nonce')) {
		wp_send_json_error('Security check failed');
	}

	if (!iipm_pm_user_can_manage()) {
		wp_send_json_error('Insufficient permissions');
	}

	global $wpdb;

	$page     = max(1, intval($_POST['page'] ?? 1));
	$per_page = intval($_POST['per_page'] ?? 20);
	$per_page = min(50, max(5, $per_page));
	$offset   = ($page - 1) * $per_page;
	$search   = sanitize_text_field($_POST['search'] ?? '');
	$filter_year = intval($_POST['filter_year'] ?? date('Y'));
	if ($filter_year < 2019 || $filter_year > date('Y')) {
		$filter_year = date('Y');
	}

	$where_clauses = array(
		"NOT EXISTS (
			SELECT 1 
			FROM {$wpdb->usermeta} um 
			WHERE um.user_id = u.ID 
			  AND um.meta_key = '{$wpdb->prefix}capabilities' 
			  AND um.meta_value LIKE '%administrator%'
		)",
		"mp.user_designation IS NOT NULL AND mp.user_designation != ''",
		"EXISTS (
			SELECT 1 
			FROM {$wpdb->prefix}memberships mem 
			WHERE mem.designation = mp.user_designation
		)",
		"EXISTS (
			SELECT 1 
			FROM {$wpdb->prefix}test_iipm_members m 
			WHERE m.user_id = u.ID 
			  AND m.member_type = 'individual'
		)"
	);
	$params = array();

	if (!empty($search)) {
		$where_clauses[] = "(u.user_email LIKE %s OR u.user_login LIKE %s OR mp.first_name LIKE %s OR mp.sur_name LIKE %s)";
		$like = '%' . $wpdb->esc_like($search) . '%';
		$params[] = $like;
		$params[] = $like;
		$params[] = $like;
		$params[] = $like;
	}

	$where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

	$count_sql = "
		SELECT COUNT(*)
		FROM {$wpdb->users} u
		LEFT JOIN {$wpdb->prefix}test_iipm_member_profiles mp ON mp.user_id = u.ID
		{$where_sql}
	";

	$total_users = !empty($params) ? $wpdb->get_var($wpdb->prepare($count_sql, $params)) : $wpdb->get_var($count_sql);

	$users_sql = "
		SELECT u.ID, u.user_login, u.user_email,
			   mp.first_name, mp.sur_name, mp.user_designation,
			   mp.Address_1, mp.Address_2
		FROM {$wpdb->users} u
		LEFT JOIN {$wpdb->prefix}test_iipm_member_profiles mp ON mp.user_id = u.ID
		{$where_sql}
		ORDER BY mp.first_name ASC, mp.sur_name ASC
		LIMIT %d OFFSET %d
	";

	$list_params = $params;
	$list_params[] = $per_page;
	$list_params[] = $offset;

	$results = $wpdb->get_results($wpdb->prepare($users_sql, $list_params));

	$users = array();
	foreach ($results as $row) {
		$full_name = trim(($row->first_name ?? '') . ' ' . ($row->sur_name ?? ''));
		if (empty($full_name)) {
			$full_name = $row->user_login;
		}

		// Get customer_id from lookup table
		$customer_lookup = $wpdb->get_row($wpdb->prepare(
			"SELECT customer_id FROM {$wpdb->prefix}wc_customer_lookup WHERE user_id = %d",
			$row->ID
		));

		// Get latest order status and date for this user
		$order_status = '';
		$last_invoiced = '';
		if ($customer_lookup) {
			$latest_order = $wpdb->get_row($wpdb->prepare(
				"SELECT o.status, o.date_updated_gmt
				FROM {$wpdb->prefix}wc_orders o
				INNER JOIN {$wpdb->prefix}wc_orders_meta om_year ON o.id = om_year.order_id AND om_year.meta_key = '_iipm_invoice_year'
				WHERE o.type = 'shop_order'
				  AND o.customer_id = %d
				  AND om_year.meta_value = %d
				ORDER BY o.date_updated_gmt DESC
				LIMIT 1",
				$customer_lookup->customer_id,
				$filter_year
			));

			if ($latest_order) {
				$order_status = $latest_order->status;
				if (!empty($latest_order->date_updated_gmt)) {
					$date = new DateTime($latest_order->date_updated_gmt);
					$last_invoiced = $date->format('M j, Y');
				}
			}
		}

		// Get status label
		$status_label = '';
		if (!empty($order_status)) {
			$status_label = iipm_pm_get_status_label($order_status);
		}

		// Get membership fee by finding product matching designation
		$membership_fee = 0;
		if (!empty($row->user_designation)) {
			$products = get_posts(array(
				'post_type' => 'product',
				'posts_per_page' => 1,
				'post_status' => 'publish',
				'title' => $row->user_designation,
				's' => $row->user_designation
			));

			if (!empty($products)) {
				$product = wc_get_product($products[0]->ID);
				if ($product) {
					$membership_fee = floatval($product->get_price());
				}
			}
		}

		$users[] = array(
			'id' => intval($row->ID),
			'user_email' => $row->user_email,
			'user_login' => $row->user_login,
			'first_name' => $row->first_name ?? '',
			'sur_name' => $row->sur_name ?? '',
			'full_name' => $full_name,
			'designation' => $row->user_designation ?? '',
			'membership_fee' => $membership_fee,
			'Address_1' => $row->Address_1 ?? '',
			'Address_2' => $row->Address_2 ?? '',
			'order_status' => $order_status,
			'status_label' => $status_label,
			'last_invoiced' => $last_invoiced
		);
	}

	$total_pages = $total_users > 0 ? ceil($total_users / $per_page) : 1;

	wp_send_json_success(array(
		'users' => $users,
		'pagination' => array(
			'current_page' => $page,
			'total_pages' => $total_pages,
			'total_users' => intval($total_users),
			'per_page' => $per_page
		)
	));
}
add_action('wp_ajax_iipm_get_payment_users', 'iipm_get_payment_users');

/**
 * AJAX: Fetch organizations for payment management.
 */
function iipm_get_payment_organizations() {
	$nonce = sanitize_text_field($_POST['nonce'] ?? '');
	if (!wp_verify_nonce($nonce, 'iipm_payment_nonce')) {
		wp_send_json_error('Security check failed');
	}

	if (!iipm_pm_user_can_manage()) {
		wp_send_json_error('Insufficient permissions');
	}

	global $wpdb;

	$page     = max(1, intval($_POST['page'] ?? 1));
	$per_page = intval($_POST['per_page'] ?? 20);
	$per_page = min(50, max(5, $per_page));
	$offset   = ($page - 1) * $per_page;
	$search   = sanitize_text_field($_POST['search'] ?? '');
	$filter_year = intval($_POST['filter_year'] ?? date('Y'));
	if ($filter_year < 2019 || $filter_year > date('Y')) {
		$filter_year = date('Y');
	}

	$where_clauses = array();
	$params = array();

	if (!empty($search)) {
		$where_clauses[] = "(org.name LIKE %s OR org.contact_email LIKE %s)";
		$like = '%' . $wpdb->esc_like($search) . '%';
		$params[] = $like;
		$params[] = $like;
	}

	$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

	$count_sql = "
		SELECT COUNT(DISTINCT org.id)
		FROM {$wpdb->prefix}test_iipm_organisations org
		{$where_sql}
	";

	$total_orgs = !empty($params) ? $wpdb->get_var($wpdb->prepare($count_sql, $params)) : $wpdb->get_var($count_sql);

	$orgs_sql = "
		SELECT org.id, org.name, org.contact_email, org.contact_phone,
			   org.address_line1, org.address_line2, org.admin_user_id
		FROM {$wpdb->prefix}test_iipm_organisations org
		{$where_sql}
		ORDER BY org.name ASC
		LIMIT %d OFFSET %d
	";

	$list_params = $params;
	$list_params[] = $per_page;
	$list_params[] = $offset;

	$results = $wpdb->get_results($wpdb->prepare($orgs_sql, $list_params));

	$organizations = array();
	foreach ($results as $row) {
		// Get member count
		$member_count = $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(DISTINCT mp.user_id)
			FROM {$wpdb->prefix}test_iipm_member_profiles mp
			WHERE mp.employer_id = %d",
			$row->id
		));

		// Get all members with their designations
		$members = $wpdb->get_results($wpdb->prepare(
			"SELECT mp.id, mp.user_designation, mp.user_id
			FROM {$wpdb->prefix}test_iipm_member_profiles mp
			WHERE mp.employer_id = %d AND mp.user_designation IS NOT NULL AND mp.user_designation != ''",
			$row->id
		));

		// Calculate total fees by matching product names with designation names
		$total_fees = 0;
		$designation_fees = array(); // Cache for designation->fee mapping
		
		foreach ($members as $member) {
			if (empty($member->user_designation)) {
				continue;
			}

			// Check cache first to avoid repeated queries
			if (!isset($designation_fees[$member->user_designation])) {
				// Find product matching this designation name
				$products = get_posts(array(
					'post_type' => 'product',
					'posts_per_page' => 1,
					'post_status' => 'publish',
					'title' => $member->user_designation,
					's' => $member->user_designation
				));

				if (!empty($products)) {
					$product = wc_get_product($products[0]->ID);
					if ($product) {
						$designation_fees[$member->user_designation] = floatval($product->get_price());
					} else {
						$designation_fees[$member->user_designation] = 0;
					}
				} else {
					$designation_fees[$member->user_designation] = 0;
				}
			}
			
			$total_fees += $designation_fees[$member->user_designation];
		}

		// Get customer_id from lookup table (by username = org name)
		$customer_lookup = $wpdb->get_row($wpdb->prepare(
			"SELECT customer_id FROM {$wpdb->prefix}wc_customer_lookup WHERE username = %s AND user_id IS NULL",
			$row->name
		));

		// Get latest order status and date for this organization
		$order_status = '';
		$last_invoiced = '';
		if ($customer_lookup) {
			$latest_order = $wpdb->get_row($wpdb->prepare(
				"SELECT o.status, o.date_updated_gmt
				FROM {$wpdb->prefix}wc_orders o
				INNER JOIN {$wpdb->prefix}wc_orders_meta om_year ON o.id = om_year.order_id AND om_year.meta_key = '_iipm_invoice_year'
				WHERE o.type = 'shop_order'
				  AND o.customer_id = %d
				  AND om_year.meta_value = %d
				ORDER BY o.date_updated_gmt DESC
				LIMIT 1",
				$customer_lookup->customer_id,
				$filter_year
			));

			if ($latest_order) {
				$order_status = $latest_order->status;
				if (!empty($latest_order->date_updated_gmt)) {
					$date = new DateTime($latest_order->date_updated_gmt);
					$last_invoiced = $date->format('M j, Y');
				}
			}
		}

		// Get status label
		$status_label = '';
		if (!empty($order_status)) {
			$status_label = iipm_pm_get_status_label($order_status);
		}

		$organizations[] = array(
			'id' => intval($row->id),
			'organisation_name' => $row->name ?? '',
			'contact_email' => $row->contact_email ?? '',
			'contact_phone' => $row->contact_phone ?? '',
			'address_line1' => $row->address_line1 ?? '',
			'address_line2' => $row->address_line2 ?? '',
			'admin_user_id' => intval($row->admin_user_id),
			'member_count' => intval($member_count),
			'total_fees' => floatval($total_fees),
			'order_status' => $order_status,
			'status_label' => $status_label,
			'last_invoiced' => $last_invoiced
		);
	}

	$total_pages = $total_orgs > 0 ? ceil($total_orgs / $per_page) : 1;

	wp_send_json_success(array(
		'organizations' => $organizations,
		'pagination' => array(
			'current_page' => $page,
			'total_pages' => $total_pages,
			'total_orgs' => intval($total_orgs),
			'per_page' => $per_page
		)
	));
}
add_action('wp_ajax_iipm_get_payment_organizations', 'iipm_get_payment_organizations');

/**
 * AJAX: Send invoice to selected user.
 */
function iipm_send_payment_invoice() {
	$nonce = sanitize_text_field($_POST['nonce'] ?? '');
	if (!wp_verify_nonce($nonce, 'iipm_payment_nonce')) {
		wp_send_json_error('Security check failed');
	}

	if (!iipm_pm_user_can_manage()) {
		wp_send_json_error('Insufficient permissions');
	}

	$user_id = intval($_POST['user_id'] ?? 0);
	$org_id = intval($_POST['org_id'] ?? 0);
	
	if ($user_id <= 0 && $org_id <= 0) {
		wp_send_json_error('Invalid user or organization selected');
	}

	$invoice_year = intval($_POST['invoice_year'] ?? date('Y'));
	if ($invoice_year < 2019 || $invoice_year > date('Y')) {
		$invoice_year = date('Y');
	}

	global $wpdb;

	// Determine if it's an organization or individual based on which ID is provided
	$is_organization = ($org_id > 0);

	// Based on which ID is provided, find the user or organization
	if ($is_organization) {
		// Handle organization invoice
		$org = $wpdb->get_row($wpdb->prepare(
			"SELECT id, name, contact_email, contact_phone, address_line1, address_line2, admin_user_id FROM {$wpdb->prefix}test_iipm_organisations WHERE id = %d",
			$org_id
		));

		if (!$org) {
			wp_send_json_error('Organization not found');
		}

		// Get all organization members with their membership level designations
		$members = $wpdb->get_results($wpdb->prepare(
			"SELECT mp.user_id, mp.user_designation, mp.first_name, mp.sur_name,
			       u.user_email, u.user_login
			FROM {$wpdb->prefix}test_iipm_member_profiles mp
			LEFT JOIN {$wpdb->users} u ON u.ID = mp.user_id
			WHERE mp.employer_id = %d AND mp.user_designation IS NOT NULL AND mp.user_designation != ''",
			$org_id
		));

		if (empty($members)) {
			wp_send_json_error('No members found for this organization');
		}

		// Calculate total fee by finding products for each member's designation
		$total_fee = 0;
		$member_products = array();
		
		foreach ($members as $member) {
			if (empty($member->user_designation)) {
				continue;
			}
			
			// Find product matching this member's designation
			$product_args = array(
				'post_type' => 'product',
				'posts_per_page' => 1,
				'post_status' => 'publish',
				'title' => $member->user_designation,
				's' => $member->user_designation
			);
			
			$products = get_posts($product_args);
			
			if (!empty($products)) {
				$product = wc_get_product($products[0]->ID);
				if ($product) {
					$product_price = floatval($product->get_price());
					$total_fee += $product_price;
					$member_products[] = array(
						'user_id' => $member->user_id,
						'designation' => $member->user_designation,
						'product_id' => $products[0]->ID,
						'fee' => $product_price
					);
				}
			}
		}

		if ($total_fee <= 0 || empty($member_products)) {
			wp_send_json_error('No products found for organization members or total fee is zero');
		}

		// For organizations, use the first product as the base product (we'll override the price with total_fee)
		$product_id = $member_products[0]['product_id'];
		$product = wc_get_product($product_id);
		if (!$product) {
			wp_send_json_error('Failed to load product for organization');
		}

		$first_name = $org->name;
		$sur_name = '';
		$email = $org->contact_email;
		$phone = $org->contact_phone ?? '';
		$address_1 = $org->address_line1 ?? '';
		$address_2 = $org->address_line2 ?? '';
		$city = '';
		$username = $org->name; // Use org name as username
	} else {
		// Handle individual member invoice
		$user = get_userdata($user_id);
		if (!$user) {
			wp_send_json_error('User not found');
		}

		$profile = $wpdb->get_row($wpdb->prepare(
			"SELECT first_name, sur_name, user_designation, Address_1, Address_2, city_or_town, user_phone FROM {$wpdb->prefix}test_iipm_member_profiles WHERE user_id = %d",
			$user_id
		));

		$first_name = $profile->first_name ?? $user->first_name ?? '';
		$sur_name = $profile->sur_name ?? $user->last_name ?? '';
		$designation = $profile->user_designation ?? '';
		$address_1 = $profile->Address_1 ?? '';
		$address_2 = $profile->Address_2 ?? '';
		$city = $profile->city_or_town ?? '';
		$phone = $profile->user_phone ?? '';
		$email = $user->user_email;
		$username = trim($first_name . ' ' . $sur_name); // Use full name as username
		if (empty($username)) {
			$username = $user->user_login;
		}

		if (empty($designation)) {
			wp_send_json_error('User designation not found');
		}

		// For individual: Find product by designation name
		$args = array(
			'post_type' => 'product',
			'posts_per_page' => 1,
			'post_status' => 'publish',
			'title' => $designation,
			's' => $designation
		);

		$products = get_posts($args);

		if (empty($products)) {
			wp_send_json_error('No product found matching designation: ' . $designation);
		}

		$product_id = $products[0]->ID;
		$product = wc_get_product($product_id);

		if (!$product) {
			wp_send_json_error('Failed to load product');
		}

		// For individual, total_fee is the product price
		$total_fee = floatval($product->get_price());
	}

	// Ensure customer exists in wp_wc_customer_lookup
	if ($is_organization) {
		// For organizations: user_id is NULL, identified by username (org name)
		$customer_lookup = $wpdb->get_row($wpdb->prepare(
			"SELECT customer_id FROM {$wpdb->prefix}wc_customer_lookup WHERE username = %s AND user_id IS NULL",
			$username
		));
	} else {
		// For individuals: user_id is the actual user ID
		$customer_lookup = $wpdb->get_row($wpdb->prepare(
			"SELECT customer_id FROM {$wpdb->prefix}wc_customer_lookup WHERE user_id = %d",
			$user_id
		));
	}

	if (!$customer_lookup) {
		// Insert customer into lookup table
		$insert_data = array(
			'username' => $username,
			'first_name' => $first_name,
			'last_name' => $sur_name,
			'email' => $email,
			'date_registered' => current_time('mysql', 1),
			'date_last_active' => current_time('mysql', 1),
			'country' => '',
			'postcode' => '',
			'city' => $city,
			'state' => ''
		);
		$insert_formats = array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s');
		
		if ($is_organization) {
			// For organizations: user_id is NULL (don't include it in insert)
			// user_id will be NULL by default
		} else {
			// For individuals: user_id is the actual user ID
			$insert_data['user_id'] = $user_id;
			$insert_formats[] = '%d';
		}
		
		$wpdb->insert(
			$wpdb->prefix . 'wc_customer_lookup',
			$insert_data,
			$insert_formats
		);
		$customer_id = $wpdb->insert_id;
	} else {
		$customer_id = intval($customer_lookup->customer_id);
		// Update last active date and username
		$update_data = array(
			'username' => $username,
			'first_name' => $first_name,
			'last_name' => $sur_name,
			'email' => $email,
			'date_last_active' => current_time('mysql', 1)
		);
		$update_formats = array('%s', '%s', '%s', '%s', '%s');
		
		if (!$is_organization) {
			// For individuals, also update user_id in case it changed
			$update_data['user_id'] = $user_id;
			$update_formats[] = '%d';
		}
		
		$wpdb->update(
			$wpdb->prefix . 'wc_customer_lookup',
			$update_data,
			array('customer_id' => $customer_id),
			$update_formats,
			array('%d')
		);
	}

	// Create WooCommerce order with customer_id from lookup table
	try {
		// Create order
		$order = wc_create_order(array(
			'customer_id' => $customer_id,
			'status' => 'pending'
		));

		if (is_wp_error($order)) {
			wp_send_json_error('Failed to create WooCommerce order: ' . $order->get_error_message());
		}

		// Add product to order using WC_Order_Item_Product
		$item = new WC_Order_Item_Product();
		$item->set_product($product);
		$item->set_quantity(1);
		
		// For organizations, use total_fee; for individuals, use product price
		$order_amount = $is_organization ? $total_fee : $product->get_price();
		$item->set_subtotal($order_amount);
		$item->set_total($order_amount);
		$order->add_item($item);

		// Set billing information
		$order->set_billing_first_name($first_name);
		$order->set_billing_last_name($sur_name);
		$order->set_billing_email($email);
		$order->set_billing_phone($phone);
		$order->set_billing_address_1($address_1);
		$order->set_billing_address_2($address_2);
		$order->set_billing_city($city);

		// Calculate totals
		$order->calculate_totals();

		// Save the order
		$order->save();

		$wc_order_id = $order->get_id();
		$order_number = $order->get_order_number();

		// Add order meta for reference
		if ($is_organization) {
			$order->update_meta_data('_iipm_designation', 'Organization'); // Multiple designations
			$order->update_meta_data('_iipm_total_fee', $total_fee);
			$order->update_meta_data('_iipm_org_id', $org_id);
		} else {
			$order->update_meta_data('_iipm_designation', $designation);
			$order->update_meta_data('_iipm_user_id', $user_id);
		}
		$order->update_meta_data('_iipm_product_id', $product_id);
		$order->update_meta_data('_iipm_invoice_year', $invoice_year);
		$order->save();

		// Generate and send invoice PDF using WooCommerce PDF Invoices plugin
		$email_sent = iipm_pm_send_wc_invoice_email($order, $email, $first_name);

		if (is_wp_error($email_sent)) {
			// Log error but don't fail the order creation
			error_log('IIPM Invoice email error: ' . $email_sent->get_error_message());
			
			wp_send_json_success(array(
				'message' => 'WooCommerce order created successfully, but invoice email failed',
				'order_number' => $order_number,
				'wc_order_id' => $wc_order_id,
				'product_name' => $product->get_name(),
				'email_error' => $email_sent->get_error_message()
			));
		} else {
			wp_send_json_success(array(
				'message' => 'WooCommerce order created and invoice email sent successfully',
				'order_number' => $order_number,
				'wc_order_id' => $wc_order_id,
				'product_name' => $product->get_name()
			));
		}

	} catch (Exception $e) {
		error_log('IIPM WC Order creation error: ' . $e->getMessage());
		wp_send_json_error('Failed to create order: ' . $e->getMessage());
	}
}
add_action('wp_ajax_iipm_send_payment_invoice', 'iipm_send_payment_invoice');

/**
 * AJAX: Get payment orders with status.
 */
function iipm_get_payment_orders() {
	$nonce = sanitize_text_field($_POST['nonce'] ?? '');
	if (!wp_verify_nonce($nonce, 'iipm_payment_nonce')) {
		wp_send_json_error('Security check failed');
	}

	if (!iipm_pm_user_can_manage()) {
		wp_send_json_error('Insufficient permissions');
	}

	global $wpdb;

	$page = max(1, intval($_POST['page'] ?? 1));
	$per_page = intval($_POST['per_page'] ?? 20);
	$per_page = min(50, max(5, $per_page));
	$offset = ($page - 1) * $per_page;
	$status_filter = sanitize_text_field($_POST['status'] ?? '');

	$wc_orders_table = $wpdb->prefix . 'wc_orders';

	$where = "WHERE type = 'shop_order'";
	$params = array();

	if (!empty($status_filter) && $status_filter !== 'all') {
		$where .= " AND status = %s";
		$params[] = $status_filter;
	}

	$count_sql = "SELECT COUNT(*) FROM {$wc_orders_table} {$where}";
	$total_orders = !empty($params) ? $wpdb->get_var($wpdb->prepare($count_sql, $params)) : $wpdb->get_var($count_sql);

	$orders_sql = "SELECT * FROM {$wc_orders_table} {$where} ORDER BY date_created_gmt DESC LIMIT %d OFFSET %d";
	$list_params = $params;
	$list_params[] = $per_page;
	$list_params[] = $offset;

	$results = !empty($params)
		? $wpdb->get_results($wpdb->prepare($orders_sql, $list_params))
		: $wpdb->get_results($wpdb->prepare($orders_sql, $per_page, $offset));

	$orders = array();
	foreach ($results as $row) {
		// Get customer info from lookup table
		$customer = $wpdb->get_row($wpdb->prepare(
			"SELECT user_id, username, email FROM {$wpdb->prefix}wc_customer_lookup WHERE customer_id = %d",
			$row->customer_id
		));
		
		$user_email = $customer ? $customer->email : '';
		$username = $customer ? $customer->username : '';
		$actual_user_id = $customer && $customer->user_id ? intval($customer->user_id) : 0;
		
		// Format date
		$created_at = '';
		if (!empty($row->date_created_gmt)) {
			$date = new DateTime($row->date_created_gmt);
			$created_at = $date->format('M j, Y H:i');
		}

		$updated_at = '';
		if (!empty($row->date_updated_gmt)) {
			$date = new DateTime($row->date_updated_gmt);
			$updated_at = $date->format('M j, Y H:i');
		}
		
		$orders[] = array(
			'id' => intval($row->id),
			'order_number' => $row->id,
			'customer_id' => intval($row->customer_id),
			'user_id' => $actual_user_id,
			'username' => $username,
			'user_email' => $user_email,
			'amount' => floatval($row->total_amount),
			'currency' => $row->currency,
			'status' => $row->status,
			'status_label' => iipm_pm_get_status_label($row->status),
			'created_at' => $created_at,
			'updated_at' => $updated_at
		);
	}

	$total_pages = $total_orders > 0 ? ceil($total_orders / $per_page) : 1;

	wp_send_json_success(array(
		'orders' => $orders,
		'pagination' => array(
			'current_page' => $page,
			'total_pages' => $total_pages,
			'total_orders' => intval($total_orders),
			'per_page' => $per_page
		),
		'statuses' => iipm_pm_get_order_statuses()
	));
}
add_action('wp_ajax_iipm_get_payment_orders', 'iipm_get_payment_orders');

/**
 * Send invoice email with PDF using WooCommerce PDF Invoices plugin.
 */
function iipm_pm_send_wc_invoice_email($order, $email, $first_name) {
	try {
		// Check if WooCommerce PDF Invoices plugin is active
		if (!function_exists('wcpdf_get_document')) {
			return new WP_Error('plugin_not_found', 'PDF Invoices plugin is not active');
		}

		// Get the invoice document
		$invoice = wcpdf_get_document('invoice', $order);
		
		if (!$invoice) {
			return new WP_Error('invoice_not_created', 'Could not create invoice document');
		}

		// Check if invoice exists, if not create it
		if (!$invoice->exists()) {
			// Initialize invoice with date and number
			$invoice->set_date(current_time('timestamp'));
			$invoice->init();
			$invoice->save();
		}

		// Get the PDF output
		$pdf_data = $invoice->get_pdf();
		
		if (empty($pdf_data)) {
			return new WP_Error('pdf_not_generated', 'Invoice PDF data is empty');
		}

		// Save PDF to temporary file for attachment
		$upload_dir = wp_upload_dir();
		$temp_dir = trailingslashit($upload_dir['basedir']) . 'wpo-wcpdf-temp';
		
		if (!file_exists($temp_dir)) {
			wp_mkdir_p($temp_dir);
		}

		$filename = 'invoice-' . $order->get_order_number() . '.pdf';
		$pdf_path = trailingslashit($temp_dir) . $filename;
		
		// Write PDF data to file
		file_put_contents($pdf_path, $pdf_data);
		
		if (!file_exists($pdf_path)) {
			return new WP_Error('pdf_not_saved', 'Could not save PDF file');
		}

		// Prepare email
		$subject = sprintf('Invoice for Order #%s', $order->get_order_number());
		$message = sprintf(
			'<p>Hello %s,</p>
			<p>Thank you for your order. Please find attached your invoice.</p>
			<p><strong>Order Number:</strong> %s</p>
			<p><strong>Total:</strong> %s</p>
			<p>Best regards,<br>IIPM Team</p>',
			esc_html($first_name),
			esc_html($order->get_order_number()),
			wp_kses_post($order->get_formatted_order_total())
		);

		$headers = array('Content-Type: text/html; charset=UTF-8');

		// Send email with PDF attachment
		$sent = wp_mail($email, $subject, $message, $headers, array($pdf_path));

		// Clean up temporary PDF file (commented out for development - uncomment for production)
		// if (file_exists($pdf_path)) {
		// 	@unlink($pdf_path);
		// }

		if (!$sent) {
			return new WP_Error('email_failed', 'Failed to send invoice email');
		}

		return true;

	} catch (Exception $e) {
		error_log('IIPM Invoice email error: ' . $e->getMessage());
		return new WP_Error('email_exception', $e->getMessage());
	}
}

/**
 * Generate invoice PDF using TCPDF.
 */
function iipm_pm_generate_invoice_pdf($order_data) {
	try {
		$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
		$pdf->SetCreator('IIPM Portal');
		$pdf->SetAuthor('IIPM');
		$pdf->SetTitle('Invoice ' . $order_data['order_number']);
		$pdf->SetMargins(20, 30, 20);
		$pdf->AddPage();

		$html = '
			<h1 style="text-align:center;">IIPM Invoice</h1>
			<p><strong>Invoice Number:</strong> ' . esc_html($order_data['order_number']) . '</p>
			<p><strong>Date:</strong> ' . esc_html(date('F j, Y', strtotime($order_data['created_at']))) . '</p>
			<br>
			<p><strong>Billed To:</strong><br>'
				. esc_html($order_data['first_name'] . ' ' . $order_data['sur_name']) . '<br>'
				. (!empty($order_data['designation']) ? esc_html($order_data['designation']) . '<br>' : '') .
				esc_html($order_data['user_email']) .
			'</p>
			<br>
			<table border="1" cellpadding="8">
				<thead>
					<tr style="background-color:#f5f5f5;">
						<th width="60%">Description</th>
						<th width="40%">Amount</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>Standard Product Order</td>
						<td>' . esc_html($order_data['currency_symbol']) . esc_html($order_data['amount']) . '</td>
					</tr>
				</tbody>
			</table>
			<br>
			<h3>Total: ' . esc_html($order_data['currency_symbol']) . esc_html($order_data['amount']) . '</h3>
			<p>Thank you for your business.</p>
		';

		$pdf->writeHTML($html, true, false, true, false, '');

		$upload_dir = wp_upload_dir();
		if (!empty($upload_dir['error'])) {
			return new WP_Error('upload_dir_error', 'Unable to access upload directory.');
		}

		$invoice_dir = trailingslashit($upload_dir['basedir']) . 'payment-invoices';
		if (!file_exists($invoice_dir)) {
			wp_mkdir_p($invoice_dir);
		}

		$file_name = sanitize_title($order_data['order_number']) . '.pdf';
		$file_path = trailingslashit($invoice_dir) . $file_name;

		$pdf->Output($file_path, 'F');

		if (!file_exists($file_path)) {
			return new WP_Error('pdf_not_found', 'Invoice file was not created.');
		}

		return $file_path;
	} catch (Exception $e) {
		error_log('IIPM Invoice PDF error: ' . $e->getMessage());
		return new WP_Error('pdf_generation_failed', 'Failed to generate invoice PDF.');
	}
}

/**
 * Send invoice email with attachment.
 */
function iipm_pm_send_invoice_email($order_data, $attachment_path) {
	$subject = 'Invoice ' . $order_data['order_number'];
	$message = '
		<p>Hello ' . esc_html($order_data['first_name']) . ',</p>
		<p>Please find attached your invoice for the recent order.</p>
		<p><strong>Amount:</strong> ' . esc_html($order_data['currency_symbol']) . esc_html($order_data['amount']) . '</p>
		<p>Regards,<br>IIPM Team</p>
	';

	$headers = array('Content-Type: text/html; charset=UTF-8');

	$sent = wp_mail($order_data['user_email'], $subject, $message, $headers, array($attachment_path));

	if (!$sent) {
		return new WP_Error('email_failed', 'Failed to send invoice email.');
	}

	return true;
}

/**
 * Helper to create readable order number.
 */
function iipm_pm_generate_order_number() {
	return 'IIPM-INV-' . date('Ymd') . '-' . wp_generate_password(6, false, false);
}

