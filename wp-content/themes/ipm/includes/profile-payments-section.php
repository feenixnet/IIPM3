<?php
/**
 * User Profile - Payments/Invoice History Section
 * 
 * This file contains the HTML and JavaScript for the user payments section.
 * Include this in template-profile.php where the payment section should appear.
 * 
 * Usage:
 * Add in template-profile.php navigation:
 * <li class="nav-item" data-section="invoices">
 *     <a href="javascript:void(0)" onclick="showSection('invoices')">Invoices</a>
 * </li>
 * 
 * Then include this file:
 * <?php include get_template_directory() . '/includes/profile-payments-section.php'; ?>
 */

// Ensure user is logged in
if (!is_user_logged_in()) {
    return;
}

$user_id = get_current_user_id();

// Check if user belongs to organization that pays
if (!function_exists('iipm_check_user_org_payment')) {
    require_once get_template_directory() . '/includes/payment-management.php';
}

$user_org_payment = iipm_check_user_org_payment($user_id);
?>

<style>
.invoices-main {
    background: white;
    border-radius: 12px;
    padding: 30px;
}

.invoice-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #e5e7eb;
}

.invoice-header h2 {
    color: #1f2937;
    margin: 0;
    font-size: 28px;
}

.invoice-controls {
    display: flex;
    gap: 15px;
    align-items: center;
}

.year-filter-select {
    padding: 10px 15px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 15px;
    background: white;
    cursor: pointer;
    color: #374151;
}

.refresh-btn {
    padding: 10px 20px;
    background: #7c3aed;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 15px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.refresh-btn:hover {
    background: #6d28d9;
    transform: translateY(-2px);
}

.invoices-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.invoices-table thead {
    background: #f9fafb;
}

.invoices-table th {
    padding: 14px;
    text-align: left;
    font-weight: 600;
    color: #6b7280;
    font-size: 13px;
    text-transform: uppercase;
    border-bottom: 2px solid #e5e7eb;
}

.invoices-table td {
    padding: 16px 14px;
    border-bottom: 1px solid #e5e7eb;
    color: #374151;
    font-size: 15px;
}

.invoices-table tr:hover {
    background: #f9fafb;
}

.status-badge {
    display: inline-block;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-processing {
    background: #dbeafe;
    color: #1e40af;
}

.status-completed {
    background: #d1fae5;
    color: #065f46;
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

.download-link {
    color: #7c3aed;
    text-decoration: none;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.download-link:hover {
    color: #6d28d9;
    text-decoration: underline;
}

.org-account-notice {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 40px;
    border-radius: 12px;
    text-align: center;
}

.org-account-notice h3 {
    margin: 0 0 15px 0;
    font-size: 32px;
    font-weight: 700;
}

.org-account-notice p {
    margin: 10px 0;
    font-size: 16px;
    line-height: 1.6;
    opacity: 0.95;
}

.org-account-icon {
    font-size: 64px;
    margin-bottom: 20px;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6b7280;
}

.empty-state-icon {
    font-size: 48px;
    margin-bottom: 20px;
    opacity: 0.5;
}

.empty-state h3 {
    color: #374151;
    margin-bottom: 10px;
}
</style>

<div class="invoices-main payment-main" id="payment-section" style="display: none;">
    <?php if ($user_org_payment && is_object($user_org_payment)): ?>
        <!-- Organization Account Message -->
        <div class="org-account-notice">
            <div class="org-account-icon">🏢</div>
            <h3>Organisational Account</h3>
            <?php if (!empty($user_org_payment->contact_email)): ?>
                <p><strong><?php echo esc_html($user_org_payment->contact_email); ?></strong></p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Invoice History for Individual Members -->
        <div class="invoice-header">
            <h2>📄 Invoice History</h2>
            <div class="invoice-controls">
                <select id="invoice-year-filter" class="year-filter-select">
                    <?php for ($year = date('Y'); $year >= 2019; $year--): ?>
                        <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                    <?php endfor; ?>
                </select>
                <button id="refresh-invoices" class="refresh-btn">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>

        <table class="invoices-table">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="invoices-tbody">
                <tr>
                    <td colspan="5" style="text-align: center; padding: 40px; color: #6b7280;">
                        <i class="fas fa-spinner fa-spin"></i> Loading invoices...
                    </td>
                </tr>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if (!$user_org_payment): ?>
<script>
// Global function to load user invoices - exposed for template-profile.php
window.loadUserPaymentInvoices = function(year) {
    const $ = jQuery;
    const $tbody = $('#invoices-tbody');
    
    if (!year) {
        year = $('#invoice-year-filter').val() || new Date().getFullYear();
    }
    
    console.log('Loading user payment invoices for year:', year);
    $tbody.html('<tr><td colspan="6" style="text-align: center; padding: 40px; color: #6b7280;"><i class="fas fa-spinner fa-spin"></i> Loading invoices...</td></tr>');
    
    $.ajax({
        url: '<?php echo admin_url('admin-ajax.php'); ?>',
        method: 'POST',
        data: {
            action: 'iipm_get_orders',
            year: year
        },
        success: function(response) {
            console.log('Payment API Response:', response);
            if (response.success && response.data.orders && response.data.orders.length > 0) {
                window.renderPaymentInvoices(response.data.orders);
            } else {
                $tbody.html('<tr><td colspan="5" class="empty-state"><div class="empty-state-icon">📭</div><h3>No invoices found</h3><p>You don\'t have any invoices for ' + year + '</p></td></tr>');
            }
        },
        error: function(xhr, status, error) {
            console.error('Payment AJAX Error:', xhr.responseText, status, error);
            $tbody.html('<tr><td colspan="5" style="text-align: center; padding: 40px; color: #ef4444;"><i class="fas fa-exclamation-circle"></i> Error loading invoices. Please try again.</td></tr>');
        }
    });
};

jQuery(document).ready(function($) {
    // Internal alias for backward compatibility
    function loadUserInvoices(year) {
        window.loadUserPaymentInvoices(year);
    }
    
    // Expose render function globally
    // Helper function to format currency with thousands separator
    function formatCurrency(amount) {
        const num = parseFloat(amount).toFixed(2);
        const parts = num.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return '€' + parts.join('.');
    }

    window.renderPaymentInvoices = function(orders) {
        const $ = jQuery;
        const $tbody = $('#invoices-tbody');
        
        console.log('Rendering invoices:', orders);
        
        if (!orders || orders.length === 0) {
            $tbody.html('<tr><td colspan="5" class="empty-state"><div class="empty-state-icon">📭</div><h3>No invoices found</h3><p>You don\'t have any invoices for the selected year</p></td></tr>');
            return;
        }
        
        let html = '';
        orders.forEach(function(order) {
            const date = new Date(order.date_created_gmt);
            const formattedDate = date.toLocaleDateString('en-GB', { 
                day: '2-digit', 
                month: 'short', 
                year: 'numeric' 
            });
            
            // Map status
            let statusClass = 'status-pending';
            let statusLabel = 'Pending';
            
            if (order.status.includes('completed')) {
                statusClass = 'status-completed';
                statusLabel = 'Paid';
            } else if (order.status.includes('cancelled')) {
                statusClass = 'status-cancelled';
                statusLabel = 'Cancelled';
            } else if (order.status.includes('trash')) {
                statusClass = 'status-trash';
                statusLabel = 'Trash';
            } else if (order.status.includes('processing')) {
                // Processing status shouldn't occur, but handle just in case
                statusClass = 'status-processing';
                statusLabel = 'Processing';
            }
            
            // Action buttons - only download button
            let actionButtons = '';
            
            // Download button (always visible)
            actionButtons += '<button class="action-icon-btn download-btn" onclick="downloadInvoice(' + order.id + ')" title="Download Invoice" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer;"><i class="fas fa-download"></i></button>';
            
            html += '<tr>';
            html += '<td><strong>#' + order.id + '</strong></td>';
            html += '<td>' + formattedDate + '</td>';
            html += '<td><strong>' + formatCurrency(order.total_amount) + '</strong></td>';
            html += '<td><span class="status-badge ' + statusClass + '">' + statusLabel + '</span></td>';
            html += '<td>' + actionButtons + '</td>';
            html += '</tr>';
        });
        
        $tbody.html(html);
        console.log('Invoices rendered successfully:', orders.length + ' orders');
    };
    
    // Internal alias for backward compatibility
    function renderInvoices(orders) {
        window.renderPaymentInvoices(orders);
    }
    
    // Year filter change
    $('#invoice-year-filter').on('change', function() {
        loadUserInvoices($(this).val());
    });
    
    // Refresh button
    $('#refresh-invoices').on('click', function() {
        loadUserInvoices($('#invoice-year-filter').val());
    });
    
    // Load initially if this section is active on page load
    if ($('#payment-section').is(':visible')) {
        loadUserInvoices(new Date().getFullYear());
    }
});

// Download invoice function
function downloadInvoice(orderId) {
    // Create a temporary anchor element to force download
    const link = document.createElement('a');
    link.href = '<?php echo content_url('/uploads/wpo-wcpdf-temp/invoice-'); ?>' + orderId + '.pdf';
    link.download = 'invoice-' + orderId + '.pdf';
    link.target = '_blank';
    
    // Append to body, click, and remove
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>
<?php endif; ?>

