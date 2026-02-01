<?php
/*
Template Name: Payment Management
*/

if (!is_user_logged_in()) {
    wp_redirect(home_url('/login/'));
    exit;
}

if (!current_user_can('administrator') && !current_user_can('manage_iipm_members')) {
    wp_redirect(home_url('/member-portal/'));
    exit;
}

get_header();
?>

<main class="payment-management-page main-container">
    <div class="container" style="position: relative; z-index: 2;">
        <div class="page-header" style="text-align: center; margin-bottom: 20px;">
            <div>
                <div style="display: flex; justify-content: center; align-items: center; gap: 15px; margin-bottom: 10px;">
                    <h1 style="color: white; font-size: 2.5rem; margin: 0;">Payment Management</h1>
                    <a href="<?php echo admin_url('admin.php?page=wc-orders'); ?>" class="btn-wc-orders-link" title="View WooCommerce Orders" target="_blank">
                        <i class="fas fa-external-link-alt"></i>
                    </a>
                </div>
                <div class="year-selector-main" style="display: flex; justify-content: center; align-items: center; gap: 12px; margin-top: 15px;">
                    <select id="year-selector" class="year-select-main">
                        <?php
                        // Payment Management: Year selector shows CPD years
                        // CPD year N corresponds to membership expiration Feb 1, (N+1)
                        // Example: CPD year 2025 = membership expiration Feb 1, 2026
                        // Note: Member portal and member details use current date year, not CPD years
                        $current_year = date('Y');
                        for ($year = $current_year; $year >= 2019; $year--) {
                            echo '<option value="' . $year . '">' . $year . '</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>
        </div>

        <div id="payment-feedback" class="payment-feedback" aria-live="polite"></div>

        <!-- Dashboard Stats -->
        <section class="stats-grid">
            <div class="stat-card stat-target">
                <div class="stat-icon"><i class="fas fa-bullseye"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Total Target</div>
                    <div class="stat-value" id="stat-total-target">€0.00</div>
                    <div class="stat-sub" id="stat-total-orders">0 orders</div>
                </div>
            </div>
            <div class="stat-card stat-paid">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Total Earnings</div>
                    <div class="stat-value" id="stat-total-paid">€0.00</div>
                    <div class="stat-sub">Completed</div>
                </div>
            </div>
            <div class="stat-card stat-org">
                <div class="stat-icon"><i class="fas fa-building"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Organisations</div>
                    <div class="stat-value" id="stat-org-paid">€0.00</div>
                    <div class="stat-sub">Paid</div>
                </div>
            </div>
            <div class="stat-card stat-individual">
                <div class="stat-icon"><i class="fas fa-user"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Individuals</div>
                    <div class="stat-value" id="stat-individual-paid">€0.00</div>
                    <div class="stat-sub">Paid</div>
                </div>
            </div>
        </section>

        <section class="payment-card main-content">
            <!-- Organisations Table -->
            <div class="section-header">
                <h2>Organisations <span class="count-badge" id="org-total-count">0</span></h2>
            </div>
            <div class="header-actions" style="margin-bottom: 20px;">
                <div class="search-input-wrapper">
                    <input type="text" id="org-search" placeholder="Search by organisation name or email">
                    <button id="org-search-btn" class="btn-icon-square" title="Search">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                <button id="org-refresh" class="btn-icon-square" title="Refresh">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
            <div class="table-responsive">
                <table class="payment-table org-payment-table">
                    <thead>
                        <tr>
                            <th>Organisation</th>
                            <th>Email</th>
                            <th>Members</th>
                            <th>Total Fees</th>
                            <th>Status</th>
                            <th>Last Invoiced</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="payment-orgs-body">
                        <tr>
                            <td colspan="7" class="text-center">Loading organizations...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="pagination">
                <button id="org-prev" class="btn btn-secondary" disabled>Previous</button>
                <span id="org-page-info">Page 1 of 1</span>
                <button id="org-next" class="btn btn-secondary" disabled>Next</button>
            </div>

            <!-- Individual Members Table -->
            <div class="section-header" style="margin-top: 40px;">
                <h2>Individual Members <span class="count-badge" id="user-total-count">0</span></h2>
            </div>
            <div class="header-actions" style="margin-bottom: 20px;">
                <div class="search-input-wrapper">
                    <input type="text" id="payment-search" placeholder="Search by name or email">
                    <button id="payment-search-btn" class="btn-icon-square" title="Search">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                <button id="payment-refresh" class="btn-icon-square" title="Refresh">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
            <div class="table-responsive">
                <table class="payment-table user-payment-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Designation</th>
                            <th>Membership Fee</th>
                            <th>Status</th>
                            <th>Last Invoiced</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="payment-users-body">
                        <tr>
                            <td colspan="7" class="text-center">Loading users...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="pagination">
                <button id="payment-prev" class="btn btn-secondary" disabled>Previous</button>
                <span id="payment-page-info">Page 1 of 1</span>
                <button id="payment-next" class="btn btn-secondary" disabled>Next</button>
            </div>
        </section>
    </div>
</main>

<!-- Decline Reason Modal -->
<div id="decline-reason-modal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header" style="border-bottom: 2px solid #e5e7eb; padding-bottom: 15px; margin-bottom: 20px;">
            <h3 style="margin: 0; color: #ef4444;"><i class="fas fa-times-circle"></i> Invoice Declined</h3>
            <button class="modal-close" style="position: absolute; top: 20px; right: 20px; background: none; border: none; font-size: 24px; color: #6b7280; cursor: pointer;">&times;</button>
        </div>
        <div class="modal-body">
            <div style="margin-bottom: 15px;">
                <strong style="color: #374151;">Order ID:</strong>
                <span id="decline-order-id" style="color: #6b7280;"></span>
            </div>
            <div style="margin-bottom: 15px;">
                <strong style="color: #374151;">Declined At:</strong>
                <span id="decline-timestamp" style="color: #6b7280;"></span>
            </div>
            <div>
                <strong style="color: #374151; display: block; margin-bottom: 8px;">Reason:</strong>
                <div id="decline-reason-text" style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 15px; color: #7f1d1d; line-height: 1.6; white-space: pre-wrap;"></div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(function($) {
    const state = {
        page: 1,
        totalPages: 1,
        search: '',
        selectedYear: $('#year-selector').val()
    };
    const orgState = {
        page: 1,
        totalPages: 1,
        search: '',
        selectedYear: $('#year-selector').val()
    };
    const perPage = 10;
    const $tbody = $('#payment-users-body');
    const $pageInfo = $('#payment-page-info');
    const $prev = $('#payment-prev');
    const $next = $('#payment-next');
    const $feedback = $('#payment-feedback');
    const $yearSelector = $('#year-selector');
    
    // Organization elements
    const $orgTbody = $('#payment-orgs-body');
    const $orgPageInfo = $('#org-page-info');
    const $orgPrev = $('#org-prev');
    const $orgNext = $('#org-next');

    function loadStats() {
        if (!window.iipm_ajax || !iipm_ajax.payment_nonce) {
            return;
        }

        $.ajax({
            url: iipm_ajax.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'iipm_get_payment_stats',
                nonce: iipm_ajax.payment_nonce,
                filter_year: state.selectedYear
            }
        }).done(function(response) {
            if (response.success) {
                const data = response.data;
                $('#stat-total-target').text(formatCurrency(data.total_target));
                $('#stat-total-paid').text(formatCurrency(data.total_paid));
                $('#stat-org-paid').text(formatCurrency(data.org_paid));
                $('#stat-individual-paid').text(formatCurrency(data.individual_paid));
                $('#stat-total-orders').text(data.total_orders + ' orders');
            }
        }).fail(function() {
            console.error('Failed to load stats');
        });
    }

    function formatCurrency(amount) {
        const num = parseFloat(amount).toFixed(2);
        const parts = num.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return '€' + parts.join('.');
    }

    function notify(message, type = 'info') {
        const titleMap = {
            success: 'Success',
            error: 'Error',
            warning: 'Warning',
            info: 'Notice'
        };

        if (window.notifications && typeof window.notifications[type] === 'function') {
            window.notifications[type]('Payment Management · ' + (titleMap[type] || 'Notice'), message);
            $feedback.hide();
            return;
        }

        $feedback.text(message).removeClass('success error info').addClass(type).fadeIn(150);
        if (type === 'success') {
            setTimeout(() => $feedback.fadeOut(200), 4000);
        }
    }

    function loadUsers() {
        if (!window.iipm_ajax || !iipm_ajax.payment_nonce) {
            notify('Missing security token. Please refresh the page.', 'error');
            return;
        }

        $tbody.html('<tr><td colspan="5" class="text-center">Loading users...</td></tr>');

        $.ajax({
            url: iipm_ajax.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'iipm_get_payment_users',
                nonce: iipm_ajax.payment_nonce,
                page: state.page,
                per_page: perPage,
                search: state.search,
                filter_year: state.selectedYear
            }
        }).done(function(response) {
            if (response.success) {
                renderUsers(response.data.users);
                updatePagination(response.data.pagination);
                $('#user-total-count').text(response.data.pagination.total_users.toLocaleString());
            } else {
                $tbody.html('<tr><td colspan="7" class="text-center text-error">' + (response.data || 'Unable to load users.') + '</td></tr>');
                notify(response.data || 'Unable to load users.', 'error');
            }
        }).fail(function() {
            const errorMsg = 'Server error while loading users.';
            $tbody.html('<tr><td colspan="7" class="text-center text-error">' + errorMsg + '</td></tr>');
            notify(errorMsg, 'error');
        });
    }

    function renderUsers(users) {
        if (!users.length) {
            $tbody.html('<tr><td colspan="7" class="text-center">No users found.</td></tr>');
            return;
        }

        const rows = users.map(user => {
            const designation = user.designation ? user.designation : '—';
            const fullName = user.full_name || 'Unknown';
            let statusLabel = user.status_label || '—';
            const lastInvoiced = user.last_invoiced || '—';
            
            // Check if at least one address field exists
            const hasAddress = (user.Address_1 && user.Address_1.trim() !== '') || 
                              (user.Address_2 && user.Address_2.trim() !== '');
            
            // Disable if no address OR if user already has completed order for this year
            const shouldDisable = !hasAddress || user.has_processing_order;
            
            // Add status badge styling (remove wc- prefix for matching)
            let statusClass = 'status-badge';
            let isCancelled = false;
            if (user.order_status) {
                const cleanStatus = user.order_status.replace('wc-', '');
                if (cleanStatus === 'completed') statusClass += ' status-completed';
                else if (cleanStatus === 'pending') statusClass += ' status-pending';
                else if (cleanStatus === 'processing') statusClass += ' status-processing';
                else if (cleanStatus === 'on-hold') statusClass += ' status-hold';
                else if (cleanStatus === 'cancelled' || cleanStatus === 'refunded') {
                    statusClass += ' status-cancelled';
                    isCancelled = true;
                }
                else if (cleanStatus === 'failed') {
                    statusClass += ' status-cancelled';
                    isCancelled = true;
                }
                else if (cleanStatus === 'trash') {
                    statusClass += ' status-trash';
                    statusLabel = 'Trash';
                }
            }
            
            // Make cancelled badge clickable if order_id exists
            let statusBadge = '';
            if (isCancelled && user.order_id) {
                statusBadge = '<span class="' + statusClass + ' clickable-status" data-order-id="' + user.order_id + '" title="Click to view decline reason" style="cursor: pointer;"><i class="fas fa-info-circle"></i> ' + statusLabel + '</span>';
            } else {
                statusBadge = '<span class="' + statusClass + '">' + statusLabel + '</span>';
            }
            
            const buttonDisabled = shouldDisable ? ' disabled' : '';
            const buttonClass = shouldDisable ? 'btn btn-primary send-invoice-btn disabled' : 'btn btn-primary send-invoice-btn';
            
            // Enable Download Invoice button only if at least one order exists
            const hasOrder = user.order_id && user.order_id > 0;
            const downloadInvoiceButtonDisabled = hasOrder ? '' : ' disabled';
            const downloadInvoiceButtonClass = hasOrder ? 'btn-icon-action download-invoice-btn' : 'btn-icon-action download-invoice-btn disabled';
            const sendEmailButtonDisabled = hasOrder ? '' : ' disabled';
            const sendEmailButtonClass = hasOrder ? 'btn-icon-action send-invoice-email-btn' : 'btn-icon-action send-invoice-email-btn disabled';
            
            return '<tr>' +
                '<td class="user-name-cell"><strong>' + fullName + '</strong><br></td>' +
                '<td>' + user.user_email + '</td>' +
                '<td>' + designation + '</td>' +
                '<td>' + formatCurrency(user.membership_fee || 0) + '</td>' +
                '<td>' + statusBadge + '</td>' +
                '<td>' + lastInvoiced + '</td>' +
                '<td class="payment-actions-cell">' +
                    '<button class="btn-icon-action send-invoice-btn' + (shouldDisable ? ' disabled' : '') + '" data-user-id="' + user.id + '"' + buttonDisabled + ' title="Generate Order" style="margin-right: 5px;">' +
                        '<i class="fas fa-pencil"></i>' +
                    '</button>' +
                    '<button class="' + sendEmailButtonClass + '" data-order-id="' + (user.order_id || '') + '" title="Send Invoice Email"' + sendEmailButtonDisabled + ' style="margin-right: 5px;">' +
                        '<i class="fas fa-envelope"></i>' +
                    '</button>' +
                    '<button class="' + downloadInvoiceButtonClass + '" data-user-id="' + user.id + '" data-order-id="' + (user.order_id || '') + '" data-name="' + fullName + '" title="Download Invoice"' + downloadInvoiceButtonDisabled + ' style="margin-right: 5px;">' +
                        '<i class="fas fa-download"></i>' +
                    '</button>' +
                    '<button class="btn-icon-action view-orders-btn" data-user-id="' + user.id + '" data-name="' + fullName + '" title="View Orders">' +
                        '<i class="fas fa-list-alt"></i>' +
                    '</button>' +
                '</td>' +
            '</tr>';
        }).join('');

        $tbody.html(rows);
    }

    function updatePagination(pagination) {
        state.totalPages = pagination.total_pages || 1;
        $pageInfo.text(`Page ${pagination.current_page} of ${state.totalPages}`);
        $prev.prop('disabled', pagination.current_page <= 1);
        $next.prop('disabled', pagination.current_page >= state.totalPages);
    }

    function generateOrder(userId, $button, forceCreate = false) {
        if (!window.iipm_ajax || !iipm_ajax.payment_nonce) {
            notify('Missing security token. Please refresh the page.', 'error');
            return;
        }

        // Use the global year selector value
        const invoiceYear = state.selectedYear;

        // Store original icon and show spinner
        const originalHtml = $button.html();
        $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: iipm_ajax.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'iipm_send_payment_invoice',
                nonce: iipm_ajax.payment_nonce,
                user_id: userId,
                invoice_year: invoiceYear,
                force_create: forceCreate ? 'true' : 'false'
            }
        }).done(function(response) {
            if (response.success) {
                notify(response.data.message || 'Order generated successfully.', 'success');
                // Reload users to show updated status
                loadUsers();
            } else if (response.needs_confirmation) {
                // Show confirmation dialog for processing orders
                $button.prop('disabled', false).html(originalHtml);
                if (confirm(response.message)) {
                    // User confirmed, resend with force_create flag
                    generateOrder(userId, $button, true);
                }
                return; // Don't execute the always block yet
            } else {
                notify(response.data || 'Failed to generate order.', 'error');
            }
        }).fail(function() {
            notify('Server error while generating order.', 'error');
        }).always(function() {
            if (!arguments[0] || !arguments[0].needs_confirmation) {
                $button.prop('disabled', false).html(originalHtml);
            }
        });
    }

    function sendInvoiceEmail(orderId, $button) {
        if (!window.iipm_ajax || !iipm_ajax.payment_nonce) {
            notify('Missing security token. Please refresh the page.', 'error');
            return;
        }

        if (!orderId || orderId <= 0) {
            notify('No order found to send.', 'error');
            return;
        }

        const originalHtml = $button.html();
        $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            method: 'POST',
            data: {
                action: 'iipm_resend_invoice_email',
                nonce: iipm_ajax.payment_nonce,
                order_id: orderId
            },
            success: function(response) {
                if (response.success) {
                    notify('Invoice email sent successfully.', 'success');
                } else {
                    notify('Failed to send email: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function() {
                notify('Error sending email.', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).html(originalHtml);
            }
        });
    }

    // Organization functions
    function loadOrganizations() {
        if (!window.iipm_ajax || !iipm_ajax.payment_nonce) {
            notify('Missing security token. Please refresh the page.', 'error');
            return;
        }

        $orgTbody.html('<tr><td colspan="7" class="text-center">Loading organizations...</td></tr>');

        $.ajax({
            url: iipm_ajax.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'iipm_get_payment_organizations',
                nonce: iipm_ajax.payment_nonce,
                page: orgState.page,
                per_page: perPage,
                search: orgState.search,
                filter_year: orgState.selectedYear
            }
        }).done(function(response) {
            if (response.success) {
                renderOrganizations(response.data.organizations);
                updateOrgPagination(response.data.pagination);
                $('#org-total-count').text(response.data.pagination.total_orgs.toLocaleString());
            } else {
                $orgTbody.html('<tr><td colspan="7" class="text-center text-error">' + (response.data || 'Unable to load organizations.') + '</td></tr>');
                notify(response.data || 'Unable to load organizations.', 'error');
            }
        }).fail(function() {
            const errorMsg = 'Server error while loading organizations.';
            $orgTbody.html('<tr><td colspan="7" class="text-center text-error">' + errorMsg + '</td></tr>');
            notify(errorMsg, 'error');
        });
    }

    function renderOrganizations(orgs) {
        if (!orgs.length) {
            $orgTbody.html('<tr><td colspan="7" class="text-center">No organizations found.</td></tr>');
            return;
        }

        const rows = orgs.map(org => {
            let statusLabel = org.status_label || '—';
            const lastInvoiced = org.last_invoiced || '—';
            
            // Check if admin email exists
            const hasEmail = org.admin_email && org.admin_email.trim() !== '';
            const hasAddress = (org.address_line1 && org.address_line1.trim() !== '') || 
                              (org.address_line2 && org.address_line2.trim() !== '');
            
            // Disable if no admin email/address OR if org already has completed order for this year
            const shouldDisable = !hasEmail || !hasAddress || org.has_processing_order;
            
            // Add status badge styling
            let statusClass = 'status-badge';
            let isCancelled = false;
            if (org.order_status) {
                const cleanStatus = org.order_status.replace('wc-', '');
                if (cleanStatus === 'completed') statusClass += ' status-completed';
                else if (cleanStatus === 'pending') statusClass += ' status-pending';
                else if (cleanStatus === 'processing') statusClass += ' status-processing';
                else if (cleanStatus === 'on-hold') statusClass += ' status-hold';
                else if (cleanStatus === 'cancelled' || cleanStatus === 'refunded') {
                    statusClass += ' status-cancelled';
                    isCancelled = true;
                }
                else if (cleanStatus === 'failed') {
                    statusClass += ' status-cancelled';
                    isCancelled = true;
                }
                else if (cleanStatus === 'trash') {
                    statusClass += ' status-trash';
                    statusLabel = 'Trash';
                }
            }
            
            // Make cancelled badge clickable if order_id exists
            let statusBadge = '';
            if (isCancelled && org.order_id) {
                statusBadge = '<span class="' + statusClass + ' clickable-status" data-order-id="' + org.order_id + '" title="Click to view decline reason" style="cursor: pointer;"><i class="fas fa-info-circle"></i> ' + statusLabel + '</span>';
            } else {
                statusBadge = '<span class="' + statusClass + '">' + statusLabel + '</span>';
            }
            
            const buttonDisabled = shouldDisable ? ' disabled' : '';
            const buttonClass = shouldDisable ? 'btn btn-primary send-org-invoice-btn disabled' : 'btn btn-primary send-org-invoice-btn';
            
            // Enable PO Code button only if at least one order exists
            const hasOrder = org.order_id && org.order_id > 0;
            const poCodeButtonDisabled = hasOrder ? '' : ' disabled';
            const poCodeButtonClass = hasOrder ? 'btn-icon-action set-po-code-btn' : 'btn-icon-action set-po-code-btn disabled';
            
            // Enable Download Invoice button only if at least one order exists
            const downloadInvoiceButtonDisabled = hasOrder ? '' : ' disabled';
            const downloadInvoiceButtonClass = hasOrder ? 'btn-icon-action download-invoice-btn' : 'btn-icon-action download-invoice-btn disabled';
            const sendEmailButtonDisabled = hasOrder ? '' : ' disabled';
            const sendEmailButtonClass = hasOrder ? 'btn-icon-action send-invoice-email-btn' : 'btn-icon-action send-invoice-email-btn disabled';
            
            return '<tr>' +
                '<td class="org-name-cell"><strong>' + (org.organisation_name || '—') + '</strong></td>' +
                '<td>' + (org.admin_email || '—') + '</td>' +
                '<td>' + org.member_count + '</td>' +
                '<td>' + formatCurrency(org.total_fees) + '</td>' +
                '<td>' + statusBadge + '</td>' +
                '<td>' + lastInvoiced + '</td>' +
                '<td class="payment-actions-cell">' +
                    '<button class="btn-icon-action send-org-invoice-btn' + (shouldDisable ? ' disabled' : '') + '" data-org-id="' + org.id + '"' + buttonDisabled + ' title="Generate Order" style="margin-right: 5px;">' +
                        '<i class="fas fa-pencil"></i>' +
                    '</button>' +
                    '<button class="' + sendEmailButtonClass + '" data-order-id="' + (org.order_id || '') + '" title="Send Invoice Email"' + sendEmailButtonDisabled + ' style="margin-right: 5px;">' +
                        '<i class="fas fa-envelope"></i>' +
                    '</button>' +
                    '<button class="' + downloadInvoiceButtonClass + '" data-org-id="' + org.id + '" data-order-id="' + (org.order_id || '') + '" data-name="' + (org.organisation_name || '—') + '" title="Download Invoice"' + downloadInvoiceButtonDisabled + ' style="margin-right: 5px;">' +
                        '<i class="fas fa-download"></i>' +
                    '</button>' +
                    '<button class="' + poCodeButtonClass + '" data-org-id="' + org.id + '" data-order-id="' + (org.order_id || '') + '" data-name="' + (org.organisation_name || '—') + '" title="Set PO Code"' + poCodeButtonDisabled + ' style="margin-right: 5px;">' +
                        '<i class="fas fa-file-invoice"></i>' +
                    '</button>' +
                    '<button class="btn-icon-action view-orders-btn" data-org-id="' + org.id + '" data-name="' + (org.organisation_name || '—') + '" title="View Orders">' +
                        '<i class="fas fa-list-alt"></i>' +
                    '</button>' +
                '</td>' +
            '</tr>';
        }).join('');

        $orgTbody.html(rows);
    }

    function updateOrgPagination(pagination) {
        orgState.totalPages = pagination.total_pages || 1;
        $orgPageInfo.text('Page ' + pagination.current_page + ' of ' + orgState.totalPages);
        $orgPrev.prop('disabled', pagination.current_page <= 1);
        $orgNext.prop('disabled', pagination.current_page >= orgState.totalPages);
    }

    function generateOrgOrder(orgId, $button, forceCreate = false) {
        if (!window.iipm_ajax || !iipm_ajax.payment_nonce) {
            notify('Missing security token. Please refresh the page.', 'error');
            return;
        }

        const invoiceYear = orgState.selectedYear;
        // Store original icon and show spinner
        const originalHtml = $button.html();
        $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: iipm_ajax.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'iipm_send_payment_invoice',
                nonce: iipm_ajax.payment_nonce,
                org_id: orgId,
                invoice_year: invoiceYear,
                force_create: forceCreate ? 'true' : 'false'
            }
        }).done(function(response) {
            if (response.success) {
                notify(response.data.message || 'Order generated successfully.', 'success');
                loadOrganizations();
            } else if (response.needs_confirmation) {
                // Show confirmation dialog for processing orders
                $button.prop('disabled', false).html(originalHtml);
                if (confirm(response.message)) {
                    // User confirmed, resend with force_create flag
                    generateOrgOrder(orgId, $button, true);
                }
                return; // Don't execute the always block yet
            } else {
                notify(response.data || 'Failed to generate order.', 'error');
            }
        }).fail(function() {
            notify('Server error while generating order.', 'error');
        }).always(function() {
            if (!arguments[0] || !arguments[0].needs_confirmation) {
                $button.prop('disabled', false).html(originalHtml);
            }
        });
    }

    $('#payment-search-btn').on('click', function() {
        state.search = $('#payment-search').val();
        state.page = 1;
        loadUsers();
    });

    $('#payment-search').on('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            $('#payment-search-btn').click();
        }
    });

    $('#payment-refresh').on('click', function() {
        loadUsers();
    });

    $yearSelector.on('change', function() {
        state.selectedYear = $(this).val();
        state.page = 1;
        loadUsers();
    });

    $prev.on('click', function() {
        if (state.page > 1) {
            state.page -= 1;
            loadUsers();
        }
    });

    $next.on('click', function() {
        if (state.page < state.totalPages) {
            state.page += 1;
            loadUsers();
        }
    });

    $tbody.on('click', '.send-invoice-btn', function() {
        if ($(this).prop('disabled')) {
            return;
        }
        const userId = $(this).data('user-id');
        generateOrder(userId, $(this));
    });

    $tbody.on('click', '.send-invoice-email-btn', function() {
        if ($(this).prop('disabled')) {
            return;
        }
        const orderId = $(this).data('order-id');
        sendInvoiceEmail(orderId, $(this));
    });

    // Organization event handlers
    $('#org-search-btn').on('click', function() {
        orgState.search = $('#org-search').val();
        orgState.page = 1;
        loadOrganizations();
    });

    $('#org-search').on('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            $('#org-search-btn').click();
        }
    });

    $('#org-refresh').on('click', function() {
        loadOrganizations();
    });

    $orgPrev.on('click', function() {
        if (orgState.page > 1) {
            orgState.page -= 1;
            loadOrganizations();
        }
    });

    $orgNext.on('click', function() {
        if (orgState.page < orgState.totalPages) {
            orgState.page += 1;
            loadOrganizations();
        }
    });

    $orgTbody.on('click', '.send-org-invoice-btn', function() {
        if ($(this).prop('disabled')) {
            return;
        }
        const orgId = $(this).data('org-id');
        generateOrgOrder(orgId, $(this));
    });

    $orgTbody.on('click', '.send-invoice-email-btn', function() {
        if ($(this).prop('disabled')) {
            return;
        }
        const orderId = $(this).data('order-id');
        sendInvoiceEmail(orderId, $(this));
    });

    $yearSelector.on('change', function() {
        state.selectedYear = $(this).val();
        orgState.selectedYear = $(this).val();
        state.page = 1;
        orgState.page = 1;
        loadStats();
        loadUsers();
        loadOrganizations();
    });

    // Initialize tooltips
    $(document).on('mouseenter', '[title]', function() {
        if (!$(this).attr('data-title')) {
            $(this).attr('data-title', $(this).attr('title'));
        }
        $(this).removeAttr('title');
    });

    // Handle decline reason modal
    function showDeclineReason(orderId) {
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            method: 'POST',
            data: {
                action: 'iipm_get_decline_reason',
                nonce: iipm_ajax.payment_nonce,
                order_id: orderId
            },
            beforeSend: function() {
                $('#decline-order-id').text('Loading...');
                $('#decline-timestamp').text('Loading...');
                $('#decline-reason-text').text('Loading...');
                $('#decline-reason-modal').fadeIn(200);
            },
            success: function(response) {
                if (response.success) {
                    $('#decline-order-id').text('#' + orderId);
                    $('#decline-timestamp').text(response.data.declined_at);
                    $('#decline-reason-text').text(response.data.reason);
                } else {
                    $('#decline-order-id').text('#' + orderId);
                    $('#decline-timestamp').text('N/A');
                    $('#decline-reason-text').text('No decline reason available for this order.');
                }
            },
            error: function() {
                $('#decline-order-id').text('#' + orderId);
                $('#decline-timestamp').text('Error');
                $('#decline-reason-text').text('Failed to load decline reason. Please try again.');
            }
        });
    }

    // Handle View Orders button - for individual users
    $tbody.on('click', '.view-orders-btn', function() {
        const userId = $(this).data('user-id');
        const name = $(this).data('name');
        showOrdersModal(userId, 0, name);
    });

    // Handle View Orders button - for organizations
    $orgTbody.on('click', '.view-orders-btn', function() {
        const orgId = $(this).data('org-id');
        const name = $(this).data('name');
        showOrdersModal(0, orgId, name);
    });
    
    // Handle PO Code button - for organizations
    $orgTbody.on('click', '.set-po-code-btn:not(.disabled)', function() {
        const orgId = $(this).data('org-id');
        const orgName = $(this).data('name');
        const orderId = $(this).data('order-id');
        showPOCodeModal(orgId, orgName, orderId);
    });
    
    // Handle Download Invoice button - for organizations
    $orgTbody.on('click', '.download-invoice-btn:not(.disabled)', function() {
        const orderId = $(this).data('order-id');
        const orgName = $(this).data('name');
        downloadOrgInvoice(orderId, orgName);
    });
    
    // Handle Download Invoice button - for individual users
    $tbody.on('click', '.download-invoice-btn:not(.disabled)', function() {
        const orderId = $(this).data('order-id');
        const userName = $(this).data('name');
        downloadUserInvoice(orderId, userName);
    });
    
    // PO Code Modal handlers
    $('#po-code-modal-close, #po-code-cancel').on('click', function() {
        $('#po-code-modal').fadeOut(200);
        $('#po-code-input').val('');
        $('#po-code-order-id-hidden').val('');
    });
    
    $('#po-code-modal').on('click', function(e) {
        if ($(e.target).is('#po-code-modal')) {
            $('#po-code-modal').fadeOut(200);
            $('#po-code-input').val('');
            $('#po-code-order-id-hidden').val('');
        }
    });
    
    // Show PO Code Modal
    function showPOCodeModal(orgId, orgName, orderId) {
        $('#po-code-org-name').text(orgName);
        $('#po-code-input').val('');
        $('#po-code-order-id-hidden').val('');
        
        // If orderId is provided, use it directly
        if (orderId && orderId > 0) {
            $('#po-code-order-id').text(orderId);
            $('#po-code-order-id-hidden').val(orderId);
            
            // Get current PO Code for this order
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                method: 'POST',
                data: {
                    action: 'iipm_get_order_po_code',
                    nonce: iipm_ajax.payment_nonce,
                    order_id: orderId
                },
                success: function(response) {
                    if (response.success) {
                        const currentPOCode = response.data.po_code || '';
                        $('#po-code-input').val(currentPOCode);
                    }
                },
                error: function() {
                    console.error('Failed to load PO Code');
                }
            });
        } else {
            // Fallback: Get latest order for this organisation
            $('#po-code-order-id').text('Loading...');
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                method: 'POST',
                data: {
                    action: 'iipm_get_latest_org_order',
                    nonce: iipm_ajax.payment_nonce,
                    org_id: orgId,
                    year: state.selectedYear
                },
                success: function(response) {
                    if (response.success && response.data && response.data.order_id) {
                        const latestOrderId = response.data.order_id;
                        const currentPOCode = response.data.po_code || '';
                        
                        $('#po-code-order-id').text(latestOrderId);
                        $('#po-code-order-id-hidden').val(latestOrderId);
                        $('#po-code-input').val(currentPOCode);
                    } else {
                        $('#po-code-order-id').text('No order found');
                        notify('No order found for this organisation in the selected year.', 'warning');
                    }
                },
                error: function() {
                    $('#po-code-order-id').text('Error');
                    notify('Failed to load order information.', 'error');
                }
            });
        }
        
        $('#po-code-modal').fadeIn(200);
    }
    
    // Download Invoice for Organisation
    function downloadOrgInvoice(orderId, orgName) {
        if (!orderId || orderId <= 0) {
            notify('No order found for this organisation.', 'error');
            return;
        }
        
        // Show loading notification
        if (window.notifications) {
            window.notifications.info('Preparing Invoice', 'Generating invoice PDF...');
        }
        
        // Generate invoice PDF via AJAX
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            method: 'POST',
            data: {
                action: 'iipm_download_org_invoice',
                nonce: iipm_ajax.payment_nonce,
                order_id: orderId
            },
            xhrFields: {
                responseType: 'blob' // Important for downloading files
            },
            success: function(blob, status, xhr) {
                // Get filename from response header or use default
                let filename = 'invoice-' + orderId + '.pdf';
                const contentDisposition = xhr.getResponseHeader('Content-Disposition');
                if (contentDisposition) {
                    const filenameMatch = contentDisposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
                    if (filenameMatch && filenameMatch[1]) {
                        filename = filenameMatch[1].replace(/['"]/g, '');
                    }
                }
                
                // Create download link
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);
                
                if (window.notifications) {
                    window.notifications.success('Download Started', 'Invoice PDF download started.');
                }
            },
            error: function(xhr) {
                let errorMessage = 'Failed to download invoice.';
                
                // Try to parse error response
                if (xhr.responseType === 'blob' && xhr.response) {
                    const reader = new FileReader();
                    reader.onload = function() {
                        try {
                            const errorData = JSON.parse(reader.result);
                            errorMessage = errorData.data || errorMessage;
                        } catch (e) {
                            // If not JSON, use default message
                        }
                        if (window.notifications) {
                            window.notifications.error('Download Failed', errorMessage);
                        }
                    };
                    reader.readAsText(xhr.response);
                } else {
                    if (window.notifications) {
                        window.notifications.error('Download Failed', errorMessage);
                    }
                }
            }
        });
    }
    
    // Download Invoice for Individual User
    function downloadUserInvoice(orderId, userName) {
        if (!orderId || orderId <= 0) {
            notify('No order found for this user.', 'error');
            return;
        }
        
        // Show loading notification
        if (window.notifications) {
            window.notifications.info('Preparing Invoice', 'Generating invoice PDF...');
        }
        
        // Generate invoice PDF via AJAX
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            method: 'POST',
            data: {
                action: 'iipm_download_org_invoice',
                nonce: iipm_ajax.payment_nonce,
                order_id: orderId
            },
            xhrFields: {
                responseType: 'blob' // Important for downloading files
            },
            success: function(blob, status, xhr) {
                // Get filename from response header or use default
                let filename = 'invoice-' + orderId + '.pdf';
                const contentDisposition = xhr.getResponseHeader('Content-Disposition');
                if (contentDisposition) {
                    const filenameMatch = contentDisposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
                    if (filenameMatch && filenameMatch[1]) {
                        filename = filenameMatch[1].replace(/['"]/g, '');
                    }
                }
                
                // Create download link
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);
                
                if (window.notifications) {
                    window.notifications.success('Download Started', 'Invoice PDF download started.');
                }
            },
            error: function(xhr) {
                let errorMessage = 'Failed to download invoice.';
                
                // Try to parse error response
                if (xhr.responseType === 'blob' && xhr.response) {
                    const reader = new FileReader();
                    reader.onload = function() {
                        try {
                            const errorData = JSON.parse(reader.result);
                            errorMessage = errorData.data || errorMessage;
                        } catch (e) {
                            // If not JSON, use default message
                        }
                        if (window.notifications) {
                            window.notifications.error('Download Failed', errorMessage);
                        }
                    };
                    reader.readAsText(xhr.response);
                } else {
                    if (window.notifications) {
                        window.notifications.error('Download Failed', errorMessage);
                    }
                }
            }
        });
    }
    
    // Save PO Code
    $('#po-code-save').on('click', function() {
        const orderId = $('#po-code-order-id-hidden').val();
        const poCode = $('#po-code-input').val().trim();
        
        if (!orderId) {
            notify('No order found. Please ensure an order exists for this organisation.', 'error');
            return;
        }
        
        if (!poCode) {
            notify('Please enter a PO Code.', 'error');
            $('#po-code-input').focus();
            return;
        }
        
        const $btn = $(this);
        const originalText = $btn.text();
        $btn.prop('disabled', true).text('Saving...');
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            method: 'POST',
            data: {
                action: 'iipm_save_po_code',
                nonce: iipm_ajax.payment_nonce,
                order_id: orderId,
                po_code: poCode
            },
            success: function(response) {
                $btn.prop('disabled', false).text(originalText);
                if (response.success) {
                    notify('PO Code saved successfully.', 'success');
                    $('#po-code-modal').fadeOut(200);
                    $('#po-code-input').val('');
                    $('#po-code-order-id-hidden').val('');
                } else {
                    notify(response.data || 'Failed to save PO Code.', 'error');
                }
            },
            error: function() {
                $btn.prop('disabled', false).text(originalText);
                notify('Server error while saving PO Code.', 'error');
            }
        });
    });

    // Show orders modal
    function showOrdersModal(userId, orgId, entityName) {
        $('#orders-entity-name').text(entityName);
        $('#orders-loading').show();
        $('#orders-content').hide();
        $('#orders-modal').fadeIn(200);

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            method: 'POST',
            data: {
                action: 'iipm_get_orders',
                user_id: userId,
                org_id: orgId,
                year: state.selectedYear
            },
            success: function(response) {
                $('#orders-loading').hide();
                if (response.success && response.data.orders && response.data.orders.length > 0) {
                    renderOrdersTable(response.data.orders);
                    $('#orders-content').show();
                    $('#orders-empty').hide();
                } else {
                    $('#orders-content').show();
                    $('#orders-empty').show();
                    $('#orders-tbody').empty();
                }
            },
            error: function() {
                $('#orders-loading').hide();
                $('#orders-content').show();
                $('#orders-empty').show();
                $('#orders-tbody').html('<tr><td colspan="5" style="text-align: center; color: #ef4444;">Error loading orders</td></tr>');
            }
        });
    }

    // Render orders table
    function renderOrdersTable(orders) {
        const adminUrl = '<?php echo admin_url("admin.php?page=wc-orders&action=edit&id="); ?>';
        
        const rows = orders.map(order => {
            const date = new Date(order.date_created_gmt);
            const formattedDate = date.toLocaleDateString('en-GB', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });

            // Map status
            let statusClass = 'status-badge';
            if (order.status.includes('processing')) {
                statusClass += ' status-processing';
            } else if (order.status.includes('completed')) {
                statusClass += ' status-completed';
            } else if (order.status.includes('pending')) {
                statusClass += ' status-pending';
            } else if (order.status.includes('cancelled')) {
                statusClass += ' status-cancelled';
            } else if (order.status.includes('trash')) {
                statusClass += ' status-trash';
            }

            const statusLabel = order.status.replace('wc-', '').replace('-', ' ').toUpperCase();
            
            return '<tr>' +
                '<td><strong>#' + order.id + '</strong></td>' +
                '<td>' + formattedDate + '</td>' +
                '<td><strong>' + formatCurrency(order.total_amount) + '</strong></td>' +
                '<td><span class="' + statusClass + '">' + statusLabel + '</span></td>' +
                '<td>' +
                    '<a href="' + adminUrl + order.id + '" class="btn-order-link" title="View Order in WooCommerce" target="_blank">' +
                        '<i class="fas fa-link"></i>' +
                    '</a>' +
                    '<button class="btn-resend-email" data-order-id="' + order.id + '" title="Resend Invoice Email">' +
                        '<i class="fas fa-envelope"></i>' +
                    '</button>' +
                '</td>' +
            '</tr>';
        }).join('');

        $('#orders-tbody').html(rows);
    }

    // Handle resend email button
    $(document).on('click', '.btn-resend-email', function() {
        const orderId = $(this).data('order-id');
        const $btn = $(this);
        
        if (confirm('Resend invoice email for Order #' + orderId + '?')) {
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                method: 'POST',
                data: {
                    action: 'iipm_resend_invoice_email',
                    nonce: iipm_ajax.payment_nonce,
                    order_id: orderId
                },
                success: function(response) {
                    if (response.success) {
                        notify('Invoice email sent successfully', 'success');
                    } else {
                        notify('Failed to send email: ' + (response.data || 'Unknown error'), 'error');
                    }
                },
                error: function() {
                    notify('Error sending email', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<i class="fas fa-envelope"></i>');
                }
            });
        }
    });

    // Close orders modal
    $('#orders-modal .modal-close').on('click', function() {
        $('#orders-modal').fadeOut(200);
    });

    // Close modal on background click
    $('#orders-modal').on('click', function(e) {
        if (e.target.id === 'orders-modal') {
            $('#orders-modal').fadeOut(200);
        }
    });

    // Click event for cancelled status badges
    $(document).on('click', '.clickable-status', function() {
        const orderId = $(this).data('order-id');
        if (orderId) {
            showDeclineReason(orderId);
        }
    });

    // Close modal
    $(document).on('click', '.modal-close, #decline-reason-modal', function(e) {
        if (e.target === this) {
            $('#decline-reason-modal').fadeOut(200);
        }
    });

    // Prevent modal content clicks from closing modal
    $(document).on('click', '#decline-reason-modal .modal-content', function(e) {
        e.stopPropagation();
    });

    // Load stats and tables on page load
    loadStats();
    loadUsers();
    loadOrganizations();
});
</script>

<style>

/* Dashboard Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.stat-card {
    background: #fff;
    border-radius: 12px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
}

.stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
    color: #fff !important;
}

.stat-icon i {
    color: #fff !important;
    margin-right: 0px !important;
}

.stat-target .stat-icon {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.stat-paid .stat-icon {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.stat-org .stat-icon {
    background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
}

.stat-individual .stat-icon {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.stat-content {
    flex: 1;
}

.stat-label {
    font-size: 14px;
    color: #6b7280;
    margin-bottom: 6px;
    font-weight: 500;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 4px;
}

.stat-sub {
    font-size: 13px;
    color: #9ca3af;
}

/* Section Headers with Count */
.section-header {
    margin-bottom: 20px;
}

.section-header h2 {
    color: #1e40af;
    font-size: 1.5rem;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.count-badge {
    background: #e0e7ff;
    color: #4338ca;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.875rem;
    font-weight: 600;
}

.header-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}

.search-input-wrapper {
    display: flex;
    align-items: stretch;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    overflow: hidden;
    flex: 1;
    max-width: 400px;
}

.search-input-wrapper input {
    padding: 11px 16px;
    border: none;
    min-width: 240px;
    flex: 1;
    font-size: 14px;
}

.search-input-wrapper input:focus {
    outline: none;
}

.year-select-main {
    padding: 10px 20px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.95);
    font-size: 1.1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.year-select-main:hover {
    border-color: #7c3aed;
    background: white;
    box-shadow: 0 6px 16px rgba(124, 58, 237, 0.2);
}

.year-select-main:focus {
    outline: none;
    border-color: #7c3aed;
    background: white;
    box-shadow: 0 0 0 4px rgba(124, 58, 237, 0.15);
}

/* Icon Buttons */
.btn-icon-square {
    background: #7c3aed;
    color: #fff !important;
    border: none;
    width: 44px;
    height: 44px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 16px;
}

.btn-icon-square i {
    color: #fff !important;
}

.btn-icon-square:hover {
    background: #6d28d9;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);
}

.btn-icon-square:active {
    transform: translateY(0);
}

.btn-icon-action {
    background: #7c3aed;
    color: #fff !important;
    border: none;
    width: 36px;
    height: 36px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 14px;
    position: relative;
}

.btn-icon-action i {
    color: #fff !important;
}

.btn-icon-action:hover:not(.disabled) {
    background: #6d28d9;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);
}

.btn-icon-action:active:not(.disabled) {
    transform: translateY(0);
}

.btn-icon-action.disabled {
    background: #e5e7eb;
    color: #9ca3af !important;
    cursor: not-allowed;
    transform: none !important;
    box-shadow: none !important;
}

.btn-icon-action.disabled i {
    color: #9ca3af !important;
}

/* Loading state for icon buttons */
.btn-icon-action:disabled:not(.disabled) {
    background: #7c3aed;
    cursor: wait;
    opacity: 0.8;
    transform: none !important;
}

.btn-icon-action:disabled:not(.disabled) i {
    color: #fff !important;
}

/* Tooltip */
.btn-icon-action[data-title]:hover::after {
    content: attr(data-title);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: #1f2937;
    color: white;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 12px;
    white-space: nowrap;
    margin-bottom: 6px;
    z-index: 1000;
    pointer-events: none;
}

.btn-icon-action[data-title]:hover::before {
    content: '';
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    border: 5px solid transparent;
    border-top-color: #1f2937;
    margin-bottom: 1px;
    z-index: 1000;
    pointer-events: none;
}

.btn-icon-square[data-title]:hover::after {
    content: attr(data-title);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: #1f2937;
    color: white;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 12px;
    white-space: nowrap;
    margin-bottom: 8px;
    z-index: 1000;
    pointer-events: none;
}

.btn-icon-square[data-title]:hover::before {
    content: '';
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    border: 5px solid transparent;
    border-top-color: #1f2937;
    margin-bottom: 3px;
    z-index: 1000;
    pointer-events: none;
}

.payment-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    padding: 28px;
}

.payment-table {
    width: 100%;
    border-collapse: collapse;
}

.payment-table thead {
    background: #f8fafc;
}

.payment-table th {
    padding: 16px 14px;
    text-align: left;
    border-bottom: 2px solid #e5e7eb;
    font-weight: 600;
    color: #374151;
    font-size: 14px;
}

.payment-table td {
    padding: 14px;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
    font-size: 14px;
}

.org-payment-table .org-name-cell {
    max-width: 220px;
    width: 220px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.org-payment-table th:last-child,
.org-payment-table td:last-child {
    width: 260px;
    white-space: nowrap;
}

.payment-actions-cell {
    white-space: nowrap;
}

.payment-table tbody tr:hover {
    background: #f8fafc;
}

.text-center {
    text-align: center;
}

.text-error {
    color: #dc2626;
}

.btn {
    padding: 10px 18px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    font-weight: 500;
    font-size: 14px;
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
    box-shadow: none !important;
}

.btn-primary {
    background: #7c3aed;
    color: #fff;
}

.btn-secondary {
    background: #e5e7eb;
    color: #111827;
}

.btn-secondary:hover:not(:disabled) {
    background: #d1d5db;
}

.btn:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(124, 58, 237, 0.25);
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 12px;
    margin-top: 16px;
}

.payment-feedback {
    display: none;
    margin-bottom: 16px;
    padding: 12px 16px;
    border-radius: 6px;
    font-weight: 600;
}

.payment-feedback.info {
    background: #eff6ff;
    color: #1d4ed8;
}

.payment-feedback.success {
    background: #ecfdf5;
    color: #047857;
}

.payment-feedback.error {
    background: #fef2f2;
    color: #b91c1c;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }

    .header-actions {
        width: 100%;
        flex-direction: column;
        align-items: stretch;
    }

    .search-input-wrapper {
        max-width: none;
    }

    .payment-table th, .payment-table td {
        padding: 10px 8px;
        font-size: 13px;
    }

    .stat-value {
        font-size: 24px;
    }
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    text-transform: capitalize;
}

.status-completed {
    background: #d1fae5;
    color: #065f46;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-processing {
    background: #dbeafe;
    color: #1e40af;
}

.status-hold {
    background: #fed7aa;
    color: #9a3412;
}

.status-cancelled {
    background: #fee2e2;
    color: #991b1b;
}

.status-trash {
    background: #f3f4f6;
    color: #6b7280;
    text-decoration: line-through;
    opacity: 0.7;
}

/* Clickable Status Badge */
.clickable-status {
    transition: all 0.2s ease;
}

.clickable-status:hover {
    background: #fecaca !important;
    transform: scale(1.05);
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
}

.clickable-status i {
    margin-right: 4px;
}

/* Modal Styles */
.modal {
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(2px);
}

.modal-content {
    background: white;
    padding: 30px;
    border-radius: 12px;
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    position: relative;
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-close:hover {
    color: #ef4444;
    transform: scale(1.1);
}

/* Orders Modal Specific Styles */
#orders-modal .modal-content {
    max-width: 900px;
    max-height: 90vh;
    overflow-y: auto;
}

.orders-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.orders-table th {
    background: #f9fafb;
    padding: 12px;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid #e5e7eb;
}

.orders-table td {
    padding: 12px;
    border-bottom: 1px solid #e5e7eb;
}

.orders-table tr:hover {
    background: #f9fafb;
}

.btn-resend-email {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white !important;
    border: none;
    padding: 6px 10px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
}

.btn-resend-email:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.btn-resend-email i {
    color: white !important;
}

/* WooCommerce Orders Link Button */
.btn-wc-orders-link {
    background: rgba(255, 255, 255, 0.2);
    color: white !important;
    border: 2px solid rgba(255, 255, 255, 0.4);
    padding: 10px 14px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 16px;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(10px);
}

.btn-wc-orders-link:hover {
    background: rgba(255, 255, 255, 0.95);
    color: #7c3aed !important;
    border-color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(255, 255, 255, 0.3);
}

.btn-wc-orders-link i {
    color: inherit !important;
}

/* Order Link Button in Modal */
.btn-order-link {
    background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
    color: white !important;
    border: none;
    padding: 6px 10px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    text-decoration: none;
    display: inline-block;
    margin-right: 8px;
    transition: all 0.2s ease;
}

.btn-order-link:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);
}

.btn-order-link i {
    color: white !important;
}
</style>

<!-- Orders Modal -->
<div id="orders-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="margin: 0;"><i class="fas fa-list-alt"></i> Orders for <span id="orders-entity-name"></span></h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div id="orders-loading" style="text-align: center; padding: 40px;">
                <i class="fas fa-spinner fa-spin" style="font-size: 32px; color: #7c3aed;"></i>
                <p>Loading orders...</p>
            </div>
            <div id="orders-content" style="display: none;">
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="orders-tbody"></tbody>
                </table>
                <div id="orders-empty" style="display: none; text-align: center; padding: 40px; color: #6b7280;">
                    <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px;"></i>
                    <p>No orders found</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- PO Code Modal -->
<div id="po-code-modal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 style="margin: 0;"><i class="fas fa-file-invoice"></i> Set PO Code</h3>
            <button class="modal-close" id="po-code-modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div style="margin-bottom: 15px;">
                <p style="margin: 0 0 10px 0; color: #6b7280;">Organisation: <strong id="po-code-org-name"></strong></p>
                <p style="margin: 0; color: #6b7280; font-size: 14px;">Order #: <strong id="po-code-order-id"></strong></p>
            </div>
            <div class="form-group">
                <label for="po-code-input">PO Code *</label>
                <input type="text" id="po-code-input" class="form-control" placeholder="Enter PO Code" maxlength="100">
                <small style="color: #6b7280; font-size: 12px; margin-top: 5px; display: block;">This code will be displayed on the invoice PDF</small>
            </div>
            <input type="hidden" id="po-code-order-id-hidden" value="">
        </div>
        <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
            <button type="button" class="btn btn-secondary" id="po-code-cancel">Cancel</button>
            <button type="button" class="btn btn-primary" id="po-code-save">Save PO Code</button>
        </div>
    </div>
</div>

<?php get_footer(); ?>

