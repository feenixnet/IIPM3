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
 * Helper function to get membership fee for a designation.
 * Finds a product with matching title and returns its price.
 */
function iipm_pm_get_designation_fee($designation) {
	if (empty($designation)) {
		return 0;
	}
	
	$products = get_posts(array(
		'post_type' => 'product',
		'posts_per_page' => 1,
		'post_status' => 'publish',
		'title' => $designation,
		's' => $designation
	));
	
	if (!empty($products)) {
		$product = wc_get_product($products[0]->ID);
		if ($product) {
			return floatval($product->get_price());
		}
	}
	
	return 0;
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

	// Get total target - sum of all individual member fees + all organization fees
	$total_target = 0;
	
	// 1. Calculate total from all individual users (not part of organizations)
	$individual_users = $wpdb->get_results(
		"SELECT DISTINCT u.ID, mp.user_designation as designation
		FROM {$wpdb->users} u
		INNER JOIN {$wpdb->prefix}test_iipm_member_profiles mp ON u.ID = mp.user_id
		INNER JOIN {$wpdb->prefix}test_iipm_members m ON m.user_id = u.ID
		WHERE m.member_type = 'individual'
		  AND (mp.employer_id IS NULL OR mp.employer_id = 0)
		  AND mp.user_designation IS NOT NULL 
		  AND mp.user_designation != ''"
	);
	
	if ($individual_users) {
		foreach ($individual_users as $user) {
			if (!empty($user->designation)) {
				$fee = iipm_pm_get_designation_fee($user->designation);
				$total_target += $fee;
			}
		}
	}
	
	// 2. Calculate total from all organizations (sum of their members' fees)
	$organizations = $wpdb->get_results(
		"SELECT id, name FROM {$wpdb->prefix}test_iipm_organisations"
	);
	
	if ($organizations) {
		foreach ($organizations as $org) {
			$org_members = $wpdb->get_results($wpdb->prepare(
				"SELECT DISTINCT u.ID, mp.user_designation as designation
				FROM {$wpdb->users} u
				INNER JOIN {$wpdb->prefix}test_iipm_member_profiles mp ON u.ID = mp.user_id
				WHERE mp.employer_id = %d
				  AND mp.user_designation IS NOT NULL 
				  AND mp.user_designation != ''",
				$org->id
			));
			
			if ($org_members) {
				foreach ($org_members as $member) {
					if (!empty($member->designation)) {
						$fee = iipm_pm_get_designation_fee($member->designation);
						$total_target += $fee;
					}
				}
			}
		}
	}

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
		INNER JOIN {$wpdb->prefix}wc_customer_lookup cl ON o.customer_id = cl.customer_id
		WHERE o.type = 'shop_order' AND o.status = 'wc-completed' AND om_year.meta_value = %d AND cl.org_id IS NOT NULL",
		$filter_year
	));

	// Get individual paid amount (completed orders for individuals)
	$individual_paid = $wpdb->get_var($wpdb->prepare(
		"SELECT COALESCE(SUM(o.total_amount), 0)
		FROM {$wpdb->prefix}wc_orders o
		INNER JOIN {$wpdb->prefix}wc_orders_meta om_year ON o.id = om_year.order_id AND om_year.meta_key = '_iipm_invoice_year'
		INNER JOIN {$wpdb->prefix}wc_customer_lookup cl ON o.customer_id = cl.customer_id
		WHERE o.type = 'shop_order' AND o.status = 'wc-completed' AND om_year.meta_value = %d AND cl.user_id IS NOT NULL",
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
		$order_id = null;
		$has_processing_order = false;
		
		if ($customer_lookup) {
			$latest_order = $wpdb->get_row($wpdb->prepare(
				"SELECT o.id, o.status, o.date_updated_gmt
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
				$order_id = intval($latest_order->id);
				$order_status = $latest_order->status;
				if (!empty($latest_order->date_updated_gmt)) {
					$date = new DateTime($latest_order->date_updated_gmt);
					$last_invoiced = $date->format('M j, Y');
				}
				
				// Check if this order is completed (payment done)
				if ($order_status === 'wc-completed') {
					$has_processing_order = true;
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
			'last_invoiced' => $last_invoiced,
			'order_id' => $order_id,
			'has_processing_order' => $has_processing_order
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
		$order_id = null;
		$has_processing_order = false;
		
		if ($customer_lookup) {
			$latest_order = $wpdb->get_row($wpdb->prepare(
				"SELECT o.id, o.status, o.date_updated_gmt
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
				$order_id = intval($latest_order->id);
				$order_status = $latest_order->status;
				if (!empty($latest_order->date_updated_gmt)) {
					$date = new DateTime($latest_order->date_updated_gmt);
					$last_invoiced = $date->format('M j, Y');
				}
				
				// Check if this order is completed (payment done)
				if ($order_status === 'wc-completed') {
					$has_processing_order = true;
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
			'order_id' => $order_id,
			'last_invoiced' => $last_invoiced,
			'has_processing_order' => $has_processing_order
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
	
	$force_create = isset($_POST['force_create']) && $_POST['force_create'] === 'true';

	global $wpdb;

	// Determine if it's an organization or individual based on which ID is provided
	$is_organization = ($org_id > 0);
	
	// Check for existing processing orders (unless force_create is true)
	if (!$force_create) {
		if ($is_organization) {
			// Check if org has processing orders
			$customer_id = $wpdb->get_var($wpdb->prepare(
				"SELECT customer_id FROM {$wpdb->prefix}wc_customer_lookup WHERE org_id = %d",
				$org_id
			));
			
			if ($customer_id) {
				$processing_count = $wpdb->get_var($wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders 
					WHERE customer_id = %d AND status = 'wc-processing'",
					$customer_id
				));
				
				if ($processing_count > 0) {
					wp_send_json(array(
						'success' => false,
						'needs_confirmation' => true,
						'message' => 'This organization currently has ' . $processing_count . ' order(s) in processing status. Do you want to create another invoice?'
					));
					return;
				}
			}
		} else {
			// Check if user has processing orders
			$customer_id = $wpdb->get_var($wpdb->prepare(
				"SELECT customer_id FROM {$wpdb->prefix}wc_customer_lookup WHERE user_id = %d",
				$user_id
			));
			
			if ($customer_id) {
				$processing_count = $wpdb->get_var($wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders 
					WHERE customer_id = %d AND status = 'wc-processing'",
					$customer_id
				));
				
				if ($processing_count > 0) {
					wp_send_json(array(
						'success' => false,
						'needs_confirmation' => true,
						'message' => 'This user currently has ' . $processing_count . ' order(s) in processing status. Do you want to create another invoice?'
					));
					return;
				}
			}
		}
	}

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
		// For organizations: use org_id
		$customer_lookup = $wpdb->get_row($wpdb->prepare(
			"SELECT customer_id FROM {$wpdb->prefix}wc_customer_lookup WHERE org_id = %d",
			$org_id
		));
	} else {
		// For individuals: use user_id
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
			// For organizations: set org_id, user_id is NULL
			$insert_data['org_id'] = $org_id;
			$insert_formats[] = '%d';
		} else {
			// For individuals: set user_id, org_id is NULL
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
		
		if ($is_organization) {
			// For organizations, update org_id
			$update_data['org_id'] = $org_id;
			$update_formats[] = '%d';
		} else {
			// For individuals, update user_id
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
			// org_id is stored in wc_customer_lookup, not in order meta
		} else {
			$order->update_meta_data('_iipm_designation', $designation);
			$order->update_meta_data('_iipm_user_id', $user_id);
		}
		$order->update_meta_data('_iipm_product_id', $product_id);
		$order->update_meta_data('_iipm_invoice_year', $invoice_year);
		$order->save();

		// Handle organization invoice differently - generate PDF and send review link email
		if ($is_organization) {
			// Generate PDF invoice for download (without emailing it)
			$pdf_generated = iipm_pm_generate_invoice_pdf($order);
			
			if (is_wp_error($pdf_generated)) {
				error_log('IIPM: Failed to generate PDF for organization invoice: ' . $pdf_generated->get_error_message());
			}
			
			// Generate unique token for organization invoice review
			$token = iipm_generate_org_invoice_token($wc_order_id);
			
			// Send email to org admin with review link
			$email_sent = iipm_send_org_invoice_email($wc_order_id, $org_id, $token);
			
			if (!$email_sent) {
				error_log('IIPM: Failed to send organization invoice review email');
				wp_send_json_success(array(
					'message' => 'Order created successfully, but failed to send review email to organization',
					'order_number' => $order_number,
					'wc_order_id' => $wc_order_id,
					'email_error' => 'Failed to send review email'
				));
			} else {
				wp_send_json_success(array(
					'message' => 'Order created and review link sent to organization administrator',
					'order_number' => $order_number,
					'wc_order_id' => $wc_order_id,
					'review_link' => home_url('/org-invoice-review/?token=' . $token)
				));
			}
		} else {
			// For individual users, send the regular PDF invoice email
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
 * Generate invoice PDF without emailing.
 * 
 * @param WC_Order $order WooCommerce order object
 * @return bool|WP_Error True on success, WP_Error on failure
 */
function iipm_pm_generate_invoice_pdf($order) {
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

		// Save PDF to temporary file
		$upload_dir = wp_upload_dir();
		$temp_dir = trailingslashit($upload_dir['basedir']) . 'wpo-wcpdf-temp';
		
		if (!file_exists($temp_dir)) {
			wp_mkdir_p($temp_dir);
		}

		$filename = 'invoice-' . $order->get_id() . '.pdf';
		$pdf_path = trailingslashit($temp_dir) . $filename;
		
		// Write PDF data to file
		file_put_contents($pdf_path, $pdf_data);
		
		if (!file_exists($pdf_path)) {
			return new WP_Error('pdf_not_saved', 'Could not save PDF file');
		}

		return true;

	} catch (Exception $e) {
		error_log('IIPM Invoice PDF generation error: ' . $e->getMessage());
		return new WP_Error('pdf_exception', $e->getMessage());
	}
}

/**
 * Send invoice email with PDF using WooCommerce PDF Invoices plugin.
 */
function iipm_pm_send_wc_invoice_email($order, $email, $first_name, $order_status = 'pending') {
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

		// Prepare email content based on order status
		$profile_url = home_url('/profile/?tab=payment');
		
		// Customize subject and message based on status
		if ($order_status === 'pending') {
			$subject = sprintf('New Invoice Generated - Order #%s', $order->get_order_number());
			$status_message = '<p><strong>Your invoice has been generated successfully.</strong> Please review and accept or decline the invoice from your profile page.</p>';
			$button_text = 'Review Invoice';
			$button_color = '#667eea 0%, #764ba2 100%';
		} elseif ($order_status === 'processing') {
			$subject = sprintf('Invoice Processing - Order #%s', $order->get_order_number());
			$status_message = '<p><strong>Your invoice is currently being processed.</strong> We will notify you once the payment is completed.</p>';
			$button_text = 'View Invoice Details';
			$button_color = '#3b82f6 0%, #2563eb 100%';
		} elseif ($order_status === 'cancelled') {
			$subject = sprintf('Invoice Declined - Order #%s', $order->get_order_number());
			$status_message = '<p><strong>Your invoice has been declined successfully.</strong> If you have any questions, please contact us or check your profile for more details.</p>';
			$button_text = 'View Invoice History';
			$button_color = '#ef4444 0%, #dc2626 100%';
		} else {
			// Default for other statuses
			$subject = sprintf('Invoice Update - Order #%s', $order->get_order_number());
			$status_message = '<p>Your invoice status has been updated. Please check your profile for details.</p>';
			$button_text = 'View My Invoices';
			$button_color = '#667eea 0%, #764ba2 100%';
		}
		
		$message = sprintf(
			'<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
				<p>Hello %s,</p>
				%s
				<div style="background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0;">
					<p style="margin: 10px 0;"><strong>Order Number:</strong> %s</p>
					<p style="margin: 10px 0;"><strong>Total:</strong> %s</p>
					<p style="margin: 10px 0;"><strong>Status:</strong> %s</p>
				</div>
				<p>You can view and manage your invoices by visiting your profile page:</p>
				<p style="text-align: center; margin: 30px 0;">
					<a href="%s" style="background: linear-gradient(135deg, %s); color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: bold;">%s</a>
				</p>
				<p style="color: #666; font-size: 14px;">Or copy this link: <a href="%s">%s</a></p>
				<p>Best regards,<br>IIPM Team</p>
			</div>',
			esc_html($first_name),
			$status_message,
			esc_html($order->get_order_number()),
			wp_kses_post($order->get_formatted_order_total()),
			esc_html(ucfirst($order_status)),
			esc_url($profile_url),
			$button_color,
			esc_html($button_text),
			esc_url($profile_url),
			esc_url($profile_url)
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

/**
 * Check if user belongs to an organization that pays for them.
 * 
 * @param int $user_id User ID
 * @return array|false Organization data if user belongs to org, false otherwise
 */
function iipm_check_user_org_payment($user_id) {
	global $wpdb;
	
	$org = $wpdb->get_row($wpdb->prepare(
		"SELECT org.id, org.name, org.contact_email, org.admin_user_id
		FROM {$wpdb->prefix}test_iipm_organisations org
		INNER JOIN {$wpdb->prefix}test_iipm_member_profiles mp ON mp.employer_id = org.id
		WHERE mp.user_id = %d
		LIMIT 1",
		$user_id
	));
	
	return $org ? $org : false;
}

/**
 * Get user payment history.
 * 
 * @param int $user_id User ID
 * @param int $year Filter by year (optional)
 * @return array List of orders
 */
function iipm_get_user_payment_history($user_id, $year = null) {
	global $wpdb;
	
	// Get customer_id from wc_customer_lookup
	$customer_id = $wpdb->get_var($wpdb->prepare(
		"SELECT customer_id FROM {$wpdb->prefix}wc_customer_lookup WHERE user_id = %d",
		$user_id
	));
	
	if (!$customer_id) {
		return array();
	}
	
	$year_condition = '';
	if ($year) {
		$year_condition = $wpdb->prepare(
			" AND YEAR(o.date_created_gmt) = %d",
			$year
		);
	}
	
	$orders = $wpdb->get_results($wpdb->prepare(
		"SELECT o.id, o.status, o.total_amount, o.date_created_gmt,
		        om_user.meta_value as user_id_meta,
		        om_designation.meta_value as designation
		FROM {$wpdb->prefix}wc_orders o
		LEFT JOIN {$wpdb->prefix}wc_orders_meta om_user ON om_user.order_id = o.id AND om_user.meta_key = '_iipm_user_id'
		LEFT JOIN {$wpdb->prefix}wc_orders_meta om_designation ON om_designation.order_id = o.id AND om_designation.meta_key = '_iipm_designation'
		WHERE o.customer_id = %d
		{$year_condition}
		ORDER BY o.date_created_gmt DESC",
		$customer_id
	));
	
	return $orders;
}

/**
 * Generate unique token for organization invoice review.
 * 
 * @param int $order_id Order ID
 * @return string Unique token
 */
function iipm_generate_org_invoice_token($order_id) {
	$token = wp_generate_password(32, false, false);
	
	// Store token in order meta
	global $wpdb;
	$wpdb->insert(
		$wpdb->prefix . 'wc_orders_meta',
		array(
			'order_id' => $order_id,
			'meta_key' => '_iipm_org_review_token',
			'meta_value' => $token
		),
		array('%d', '%s', '%s')
	);
	
	// Store token expiry (30 days)
	$expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
	$wpdb->insert(
		$wpdb->prefix . 'wc_orders_meta',
		array(
			'order_id' => $order_id,
			'meta_key' => '_iipm_org_review_token_expiry',
			'meta_value' => $expiry
		),
		array('%d', '%s', '%s')
	);
	
	return $token;
}

/**
 * Get organization invoice details by token.
 * 
 * @param string $token Review token
 * @return array|false Invoice data or false if invalid
 */
function iipm_get_org_invoice_by_token($token) {
	global $wpdb;
	
	// Find order by token
	$order_id = $wpdb->get_var($wpdb->prepare(
		"SELECT order_id FROM {$wpdb->prefix}wc_orders_meta 
		WHERE meta_key = '_iipm_org_review_token' AND meta_value = %s",
		$token
	));
	
	if (!$order_id) {
		return false;
	}
	
	// Check if token is expired
	$expiry = $wpdb->get_var($wpdb->prepare(
		"SELECT meta_value FROM {$wpdb->prefix}wc_orders_meta 
		WHERE order_id = %d AND meta_key = '_iipm_org_review_token_expiry'",
		$order_id
	));
	
	if ($expiry && strtotime($expiry) < time()) {
		return false;
	}
	
	// Get order details
	$order = $wpdb->get_row($wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}wc_orders WHERE id = %d",
		$order_id
	));
	
	if (!$order) {
		return false;
	}
	
	// Get org_id from wc_customer_lookup using customer_id from order
	$org_id = $wpdb->get_var($wpdb->prepare(
		"SELECT org_id FROM {$wpdb->prefix}wc_customer_lookup 
		WHERE customer_id = %d",
		$order->customer_id
	));
	
	if (!$org_id) {
		return false;
	}
	
	// Get organization details
	$org = $wpdb->get_row($wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}test_iipm_organisations WHERE id = %d",
		$org_id
	));
	
	// Get members with their fees (same query as invoice generation)
	$members = $wpdb->get_results($wpdb->prepare(
		"SELECT mp.user_id as ID, u.user_login, u.user_email,
		        mp.first_name, mp.sur_name, mp.user_designation
		FROM {$wpdb->prefix}test_iipm_member_profiles mp
		LEFT JOIN {$wpdb->users} u ON u.ID = mp.user_id
		WHERE mp.employer_id = %d AND mp.user_designation IS NOT NULL AND mp.user_designation != ''",
		$org_id
	));
	
	// Get designation fees (from products)
	$designation_fees = array();
	foreach ($members as $member) {
		if (!isset($designation_fees[$member->user_designation])) {
			$product_id = wc_get_products(array(
				'name' => $member->user_designation,
				'limit' => 1,
				'return' => 'ids'
			));
			
			if (!empty($product_id)) {
				$product = wc_get_product($product_id[0]);
				$designation_fees[$member->user_designation] = $product ? floatval($product->get_price()) : 0;
			} else {
				$designation_fees[$member->user_designation] = 0;
			}
		}
		$member->fee = $designation_fees[$member->user_designation];
	}
	
	return array(
		'order' => $order,
		'org' => $org,
		'members' => $members,
		'token' => $token
	);
}

/**
 * Send email to organization admin with invoice review link.
 * 
 * @param int $order_id Order ID
 * @param int $org_id Organization ID
 * @param string $token Review token
 * @return bool Success status
 */
function iipm_send_org_invoice_email($order_id, $org_id, $token, $order_status = 'pending') {
	global $wpdb;
	
	// Get organization details
	$org = $wpdb->get_row($wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}test_iipm_organisations WHERE id = %d",
		$org_id
	));
	
	if (!$org || empty($org->contact_email)) {
		return false;
	}
	
	// Customize email content based on order status
	$to = $org->contact_email;
	
	if ($order_status === 'pending') {
		// New invoice - include review link
		$review_url = home_url('/org-invoice-review/?token=' . $token);
		$subject = 'New Invoice Generated - ' . $org->name;
		$message = "Dear " . $org->name . ",\n\n";
		$message .= "A new invoice has been generated for your organization.\n\n";
		$message .= "Please review and approve or decline the invoice by clicking the link below:\n\n";
		$message .= $review_url . "\n\n";
		$message .= "This link will expire in 30 days.\n\n";
		$message .= "Order ID: " . $order_id . "\n\n";
	} elseif ($order_status === 'processing') {
		// Processing status - no review link
		$subject = 'Invoice Processing - ' . $org->name;
		$message = "Dear " . $org->name . ",\n\n";
		$message .= "Your invoice is currently being processed.\n\n";
		$message .= "We will notify you once the payment is completed.\n\n";
		$message .= "Order ID: " . $order_id . "\n\n";
	} elseif ($order_status === 'cancelled') {
		// Declined/Cancelled status - no review link
		$subject = 'Invoice Declined - ' . $org->name;
		$message = "Dear " . $org->name . ",\n\n";
		$message .= "Your invoice has been declined successfully.\n\n";
		$message .= "If you have any questions or would like to request adjustments, please contact us.\n\n";
		$message .= "Order ID: " . $order_id . "\n\n";
	} else {
		// Default for other statuses - no review link
		$subject = 'Invoice Status Update - ' . $org->name;
		$message = "Dear " . $org->name . ",\n\n";
		$message .= "Your invoice status has been updated.\n\n";
		$message .= "Order ID: " . $order_id . "\n";
		$message .= "Status: " . ucfirst($order_status) . "\n\n";
	}
	
	$message .= "Best regards,\n";
	$message .= get_bloginfo('name');
	
	$headers = array('Content-Type: text/plain; charset=UTF-8');
	
	return wp_mail($to, $subject, $message, $headers);
}

/**
 * AJAX: Approve organization invoice.
 */
function iipm_approve_org_invoice() {
	$token = sanitize_text_field($_POST['token'] ?? '');
	
	if (empty($token)) {
		wp_send_json_error('Invalid token');
	}
	
	global $wpdb;
	
	// Find order by token
	$order_id = $wpdb->get_var($wpdb->prepare(
		"SELECT order_id FROM {$wpdb->prefix}wc_orders_meta 
		WHERE meta_key = '_iipm_org_review_token' AND meta_value = %s",
		$token
	));
	
	if (!$order_id) {
		wp_send_json_error('Invalid or expired token');
	}
	
	// Check if token is expired
	$expiry = $wpdb->get_var($wpdb->prepare(
		"SELECT meta_value FROM {$wpdb->prefix}wc_orders_meta 
		WHERE order_id = %d AND meta_key = '_iipm_org_review_token_expiry'",
		$order_id
	));
	
	if ($expiry && strtotime($expiry) < time()) {
		wp_send_json_error('Token has expired');
	}
	
	// Update order status to wc-processing
	$updated = $wpdb->update(
		$wpdb->prefix . 'wc_orders',
		array('status' => 'wc-processing'),
		array('id' => $order_id),
		array('%s'),
		array('%d')
	);
	
	if ($updated === false) {
		wp_send_json_error('Failed to update order status');
	}
	
	// Store approval timestamp
	$wpdb->insert(
		$wpdb->prefix . 'wc_orders_meta',
		array(
			'order_id' => $order_id,
			'meta_key' => '_iipm_org_approved_at',
			'meta_value' => current_time('mysql')
		),
		array('%d', '%s', '%s')
	);
	
	wp_send_json_success(array(
		'message' => 'Invoice approved successfully',
		'order_id' => $order_id
	));
}
add_action('wp_ajax_iipm_approve_org_invoice', 'iipm_approve_org_invoice');
add_action('wp_ajax_nopriv_iipm_approve_org_invoice', 'iipm_approve_org_invoice');

/**
 * AJAX: Decline organization invoice.
 */
function iipm_decline_org_invoice() {
	$token = sanitize_text_field($_POST['token'] ?? '');
	$reason = sanitize_textarea_field($_POST['reason'] ?? '');
	
	if (empty($token)) {
		wp_send_json_error('Invalid token');
	}
	
	if (empty($reason)) {
		wp_send_json_error('Please provide a reason for declining');
	}
	
	global $wpdb;
	
	// Find order by token
	$order_id = $wpdb->get_var($wpdb->prepare(
		"SELECT order_id FROM {$wpdb->prefix}wc_orders_meta 
		WHERE meta_key = '_iipm_org_review_token' AND meta_value = %s",
		$token
	));
	
	if (!$order_id) {
		wp_send_json_error('Invalid or expired token');
	}
	
	// Check if token is expired
	$expiry = $wpdb->get_var($wpdb->prepare(
		"SELECT meta_value FROM {$wpdb->prefix}wc_orders_meta 
		WHERE order_id = %d AND meta_key = '_iipm_org_review_token_expiry'",
		$order_id
	));
	
	if ($expiry && strtotime($expiry) < time()) {
		wp_send_json_error('Token has expired');
	}
	
	// Update order status to wc-cancelled
	$updated = $wpdb->update(
		$wpdb->prefix . 'wc_orders',
		array('status' => 'wc-cancelled'),
		array('id' => $order_id),
		array('%s'),
		array('%d')
	);
	
	if ($updated === false) {
		wp_send_json_error('Failed to update order status');
	}
	
	// Store decline reason
	$wpdb->insert(
		$wpdb->prefix . 'wc_orders_meta',
		array(
			'order_id' => $order_id,
			'meta_key' => '_iipm_org_decline_reason',
			'meta_value' => $reason
		),
		array('%d', '%s', '%s')
	);
	
	// Store decline timestamp
	$wpdb->insert(
		$wpdb->prefix . 'wc_orders_meta',
		array(
			'order_id' => $order_id,
			'meta_key' => '_iipm_org_declined_at',
			'meta_value' => current_time('mysql')
		),
		array('%d', '%s', '%s')
	);
	
	wp_send_json_success(array(
		'message' => 'Invoice declined successfully. Admin will be notified.',
		'order_id' => $order_id
	));
}
add_action('wp_ajax_iipm_decline_org_invoice', 'iipm_decline_org_invoice');
add_action('wp_ajax_nopriv_iipm_decline_org_invoice', 'iipm_decline_org_invoice');

/**
 * AJAX: Get user payment history for profile page.
 */
function iipm_get_user_payment_history_ajax() {
	if (!is_user_logged_in()) {
		wp_send_json_error('You must be logged in');
	}
	
	$user_id = get_current_user_id();
	$year = intval($_POST['year'] ?? date('Y'));
	
	$orders = iipm_get_user_payment_history($user_id, $year);
	
	wp_send_json_success(array(
		'orders' => $orders,
		'total' => count($orders)
	));
}
add_action('wp_ajax_iipm_get_user_payment_history', 'iipm_get_user_payment_history_ajax');

/**
 * Get orders for individual user or organization.
 * 
 * @param int $user_id User ID (for individual) or 0 if org
 * @param int $org_id Organization ID or 0 if user
 * @param int $year Filter by year
 * @return array List of orders
 */
function iipm_get_orders($user_id = 0, $org_id = 0, $year = null) {
	global $wpdb;
	
	if ($user_id <= 0 && $org_id <= 0) {
		return array();
	}
	
	// Default to current year if not provided
	if (!$year) {
		$year = date('Y');
	}
	
	// Determine if this is for an organization or individual user
	$is_org = ($org_id > 0);
	
	// Get customer_id from wc_customer_lookup
	if ($is_org) {
		// For organizations: lookup by org_id
		$customer_id = $wpdb->get_var($wpdb->prepare(
			"SELECT customer_id FROM {$wpdb->prefix}wc_customer_lookup WHERE org_id = %d",
			$org_id
		));
	} else {
		// For individuals: lookup by user_id
		$customer_id = $wpdb->get_var($wpdb->prepare(
			"SELECT customer_id FROM {$wpdb->prefix}wc_customer_lookup WHERE user_id = %d",
			$user_id
		));
	}
	
	if (!$customer_id) {
		return array();
	}
	
	// Get all orders for this customer in the specified year
	$orders = $wpdb->get_results($wpdb->prepare(
		"SELECT o.id, o.status, o.total_amount, o.date_created_gmt, o.date_updated_gmt,
		        om_designation.meta_value as designation,
		        om_product.meta_value as product_id,
		        om_total_fee.meta_value as total_fee,
		        om_year.meta_value as invoice_year
		FROM {$wpdb->prefix}wc_orders o
		LEFT JOIN {$wpdb->prefix}wc_orders_meta om_designation ON om_designation.order_id = o.id AND om_designation.meta_key = '_iipm_designation'
		LEFT JOIN {$wpdb->prefix}wc_orders_meta om_product ON om_product.order_id = o.id AND om_product.meta_key = '_iipm_product_id'
		LEFT JOIN {$wpdb->prefix}wc_orders_meta om_total_fee ON om_total_fee.order_id = o.id AND om_total_fee.meta_key = '_iipm_total_fee'
		LEFT JOIN {$wpdb->prefix}wc_orders_meta om_year ON om_year.order_id = o.id AND om_year.meta_key = '_iipm_invoice_year'
		WHERE o.customer_id = %d
		  AND o.type = 'shop_order'
		  AND om_year.meta_value = %d
		ORDER BY o.date_created_gmt DESC",
		$customer_id,
		$year
	));
	
	// Format orders for response
	$formatted_orders = array();
	foreach ($orders as $order) {
		$formatted_orders[] = array(
			'id' => intval($order->id),
			'order_number' => intval($order->id),
			'status' => $order->status,
			'total_amount' => floatval($order->total_amount),
			'date_created_gmt' => $order->date_created_gmt,
			'date_updated_gmt' => $order->date_updated_gmt,
			'designation' => $order->designation ?? '',
			'product_id' => $order->product_id ?? '',
			'total_fee' => $order->total_fee ? floatval($order->total_fee) : floatval($order->total_amount),
			'invoice_year' => $order->invoice_year ?? $year,
			'is_organization' => $is_org
		);
	}
	
	return $formatted_orders;
}

/**
 * AJAX: Get orders for user or organization.
 */
function iipm_get_orders_ajax() {
	// Check if user is logged in (required for profile page access)
	if (!is_user_logged_in()) {
		wp_send_json_error('Please log in to view your invoices');
		return;
	}
	
	$user_id = intval($_POST['user_id'] ?? 0);
	$org_id = intval($_POST['org_id'] ?? 0);
	$year = intval($_POST['year'] ?? date('Y'));
	
	// If no IDs provided and user is logged in, use current user
	if ($user_id <= 0 && $org_id <= 0) {
		$user_id = get_current_user_id();
	}
	
	if ($user_id <= 0 && $org_id <= 0) {
		wp_send_json_error('User ID or Organization ID required');
		return;
	}
	
	$orders = iipm_get_orders($user_id, $org_id, $year);
	
	wp_send_json_success(array(
		'orders' => $orders,
		'total' => count($orders),
		'year' => $year,
		'is_organization' => ($org_id > 0)
	));
}
add_action('wp_ajax_iipm_get_orders', 'iipm_get_orders_ajax');
add_action('wp_ajax_nopriv_iipm_get_orders', 'iipm_get_orders_ajax');

/**
 * AJAX: Get decline reason for cancelled order.
 */
function iipm_get_decline_reason() {
	$nonce = sanitize_text_field($_POST['nonce'] ?? '');
	if (!wp_verify_nonce($nonce, 'iipm_payment_nonce')) {
		wp_send_json_error('Security check failed');
	}

	if (!iipm_pm_user_can_manage()) {
		wp_send_json_error('Insufficient permissions');
	}

	$order_id = intval($_POST['order_id'] ?? 0);
	
	if ($order_id <= 0) {
		wp_send_json_error('Invalid order ID');
	}

	global $wpdb;
	
	// Get decline reason from order meta
	$decline_reason = $wpdb->get_var($wpdb->prepare(
		"SELECT meta_value FROM {$wpdb->prefix}wc_orders_meta 
		WHERE order_id = %d AND meta_key = '_iipm_org_decline_reason'",
		$order_id
	));
	
	// Get decline timestamp
	$declined_at = $wpdb->get_var($wpdb->prepare(
		"SELECT meta_value FROM {$wpdb->prefix}wc_orders_meta 
		WHERE order_id = %d AND meta_key = '_iipm_org_declined_at'",
		$order_id
	));
	
	if (!$decline_reason) {
		wp_send_json_error('No decline reason found for this order');
	}
	
	// Remove backslashes that WordPress adds
	$decline_reason = stripslashes($decline_reason);
	
	wp_send_json_success(array(
		'reason' => $decline_reason,
		'declined_at' => $declined_at ? date('F j, Y g:i A', strtotime($declined_at)) : 'N/A'
	));
}
add_action('wp_ajax_iipm_get_decline_reason', 'iipm_get_decline_reason');

/**
 * AJAX: User accepts their invoice.
 */
function iipm_user_accept_invoice() {
	// Check if user is logged in
	if (!is_user_logged_in()) {
		wp_send_json_error('Please log in to accept invoices');
		return;
	}
	
	$order_id = intval($_POST['order_id'] ?? 0);
	
	if ($order_id <= 0) {
		wp_send_json_error('Invalid order ID');
		return;
	}
	
	global $wpdb;
	$current_user_id = get_current_user_id();
	
	// Verify this order belongs to the current user
	$order = wc_get_order($order_id);
	if (!$order) {
		wp_send_json_error('Order not found');
		return;
	}
	
	// Get customer_id from order
	$customer_id = $order->get_customer_id();
	
	// Check if this customer_id belongs to the current user
	$user_check = $wpdb->get_var($wpdb->prepare(
		"SELECT user_id FROM {$wpdb->prefix}wc_customer_lookup 
		WHERE customer_id = %d AND user_id = %d",
		$customer_id,
		$current_user_id
	));
	
	if (!$user_check) {
		wp_send_json_error('You do not have permission to accept this invoice');
		return;
	}
	
	// Update order status to processing
	$order->set_status('wc-processing', 'Invoice accepted by user');
	$order->save();
	
	// Store acceptance timestamp
	$order->update_meta_data('_iipm_user_accepted_at', current_time('mysql'));
	$order->save();
	
	wp_send_json_success('Invoice accepted successfully');
}
add_action('wp_ajax_iipm_user_accept_invoice', 'iipm_user_accept_invoice');

/**
 * AJAX: User denies their invoice.
 */
function iipm_user_deny_invoice() {
	// Check if user is logged in
	if (!is_user_logged_in()) {
		wp_send_json_error('Please log in to deny invoices');
		return;
	}
	
	$order_id = intval($_POST['order_id'] ?? 0);
	$reason = sanitize_textarea_field($_POST['reason'] ?? '');
	
	if ($order_id <= 0) {
		wp_send_json_error('Invalid order ID');
		return;
	}
	
	if (empty($reason)) {
		wp_send_json_error('Please provide a reason for denying the invoice');
		return;
	}
	
	global $wpdb;
	$current_user_id = get_current_user_id();
	
	// Verify this order belongs to the current user
	$order = wc_get_order($order_id);
	if (!$order) {
		wp_send_json_error('Order not found');
		return;
	}
	
	// Get customer_id from order
	$customer_id = $order->get_customer_id();
	
	// Check if this customer_id belongs to the current user
	$user_check = $wpdb->get_var($wpdb->prepare(
		"SELECT user_id FROM {$wpdb->prefix}wc_customer_lookup 
		WHERE customer_id = %d AND user_id = %d",
		$customer_id,
		$current_user_id
	));
	
	if (!$user_check) {
		wp_send_json_error('You do not have permission to deny this invoice');
		return;
	}
	
	// Update order status to cancelled
	$order->set_status('wc-cancelled', 'Invoice denied by user: ' . $reason);
	$order->save();
	
	// Store denial reason and timestamp
	$order->update_meta_data('_iipm_user_decline_reason', $reason);
	$order->update_meta_data('_iipm_user_declined_at', current_time('mysql'));
	$order->save();
	
	wp_send_json_success('Invoice denied successfully');
}
add_action('wp_ajax_iipm_user_deny_invoice', 'iipm_user_deny_invoice');

/**
 * AJAX: Resend invoice email for a specific order.
 */
function iipm_resend_invoice_email() {
	// Security check
	$nonce = sanitize_text_field($_POST['nonce'] ?? '');
	if (!wp_verify_nonce($nonce, 'iipm_payment_nonce')) {
		wp_send_json_error('Security check failed');
		return;
	}
	
	// Check permissions
	if (!iipm_pm_user_can_manage()) {
		wp_send_json_error('Insufficient permissions');
		return;
	}
	
	$order_id = intval($_POST['order_id'] ?? 0);
	
	if ($order_id <= 0) {
		wp_send_json_error('Invalid order ID');
		return;
	}
	
	// Get the order
	$order = wc_get_order($order_id);
	if (!$order) {
		wp_send_json_error('Order not found');
		return;
	}
	
	global $wpdb;
	
	// Get customer information
	$customer_id = $order->get_customer_id();
	$customer_lookup = $wpdb->get_row($wpdb->prepare(
		"SELECT user_id, org_id, email, first_name FROM {$wpdb->prefix}wc_customer_lookup 
		WHERE customer_id = %d",
		$customer_id
	));
	
	if (!$customer_lookup) {
		wp_send_json_error('Customer not found');
		return;
	}
	
	$email = $order->get_billing_email() ?: $customer_lookup->email;
	$first_name = $order->get_billing_first_name() ?: $customer_lookup->first_name;
	
	// Get order status
	$order_status = $order->get_status();
	
	// Check if it's an organization or individual
	$is_organization = !empty($customer_lookup->org_id);
	
	if ($is_organization) {
		// For organizations: generate PDF and send email based on status
		$pdf_generated = iipm_pm_generate_invoice_pdf($order);
		
		if (is_wp_error($pdf_generated)) {
			wp_send_json_error('Failed to generate PDF: ' . $pdf_generated->get_error_message());
			return;
		}
		
		// Generate or get existing token
		$token = get_post_meta($order_id, '_iipm_org_review_token', true);
		if (!$token) {
			$token = iipm_generate_org_invoice_token($order_id);
		}
		
		// Send email based on order status
		$email_sent = iipm_send_org_invoice_email($order_id, $customer_lookup->org_id, $token, $order_status);
		
		if (!$email_sent) {
			wp_send_json_error('Failed to send organization invoice email');
			return;
		}
		
		wp_send_json_success('Organization invoice email sent successfully');
	} else {
		// For individuals: send invoice email with profile link based on status
		$email_sent = iipm_pm_send_wc_invoice_email($order, $email, $first_name, $order_status);
		
		if (is_wp_error($email_sent)) {
			wp_send_json_error('Failed to send email: ' . $email_sent->get_error_message());
			return;
		}
		
		wp_send_json_success('Invoice email sent successfully');
	}
}
add_action('wp_ajax_iipm_resend_invoice_email', 'iipm_resend_invoice_email');

