<?php
/**
 * Template Name: Organization Invoice Review
 * Description: Page for organization admins to review and approve/decline invoices
 */

if (!function_exists('iipm_get_org_invoice_by_token')) {
    require_once get_template_directory() . '/includes/payment-management.php';
}

$token = sanitize_text_field($_GET['token'] ?? '');
$invoice_data = false;
$error_message = '';

if (empty($token)) {
    $error_message = 'Invalid access link. Please check your email for the correct link.';
} else {
    $invoice_data = iipm_get_org_invoice_by_token($token);
    if (!$invoice_data) {
        $error_message = 'Invalid or expired link. Please contact the administrator.';
    }
}

get_header();
?>

<main class="payment-management-page main-container">
    <div class="container" style="position: relative; z-index: 2;">
        <div class="page-header" style="text-align: center; margin-bottom: 30px;">
            <h1 style="color: white; font-size: 2.5rem; margin-bottom: 10px;">Organization Invoice Review</h1>
            <p style="color: rgba(255,255,255,0.9); font-size: 1.1rem;">Review and approve your organization's membership invoice</p>
        </div>

<style>
.invoice-card {
    padding: 20px;
    margin-bottom: 20px;
}

.invoice-header {
    border-bottom: 3px solid #1e40af;
    padding-bottom: 20px;
    margin-bottom: 30px;
}

.invoice-header h1 {
    color: #1e40af;
    margin: 0 0 10px 0;
    font-size: 32px;
}

.invoice-header .org-name {
    color: #1e40af;
    font-size: 24px;
    font-weight: 600;
}

.invoice-meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
}

.meta-item label {
    display: block;
    color: #6b7280;
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    margin-bottom: 5px;
}

.meta-item value {
    display: block;
    color: #1f2937;
    font-size: 16px;
    font-weight: 500;
}

.members-section {
    margin: 30px 0;
}

.members-section h2 {
    color: #1e40af;
    margin-bottom: 20px;
    font-size: 1.5rem;
    font-weight: 600;
}

.members-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}

.members-table thead {
    background: #f8f9fa;
}

.members-table th {
    padding: 12px;
    text-align: left;
    font-weight: 600;
    color: #6b7280;
    font-size: 13px;
    text-transform: uppercase;
    border-bottom: 2px solid #e5e7eb;
}

.members-table td {
    padding: 12px;
    border-bottom: 1px solid #e5e7eb;
    color: #1f2937;
}

.members-table tr:hover {
    background: #f9fafb;
}

.total-row {
    background: #dbeafe !important;
    font-weight: 600;
    font-size: 18px;
}

.total-row td {
    border-top: 2px solid #1e40af;
    padding: 16px 12px;
}

.actions-section {
    margin-top: 40px;
    padding: 30px;
    background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
    border-radius: 12px;
    text-align: center;
    border: 1px solid #e5e7eb;
}

.actions-section h3 {
    color: #1e40af;
    margin-bottom: 20px;
    font-size: 1.5rem;
    font-weight: 600;
}

.action-buttons {
    display: flex;
    gap: 20px;
    justify-content: center;
    flex-wrap: wrap;
}

.btn {
    padding: 14px 32px;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: all 0.2s ease;
    text-decoration: none !important;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn:hover {
    text-decoration: none !important;
}

.btn-approve {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white !important;
}

.btn-approve i {
    color: white !important;
}

.btn-approve:hover {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.btn-decline {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white !important;
}

.btn-decline i {
    color: white !important;
}

.btn-decline:hover {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.btn-download {
    background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
    color: white !important;
}

.btn-download i {
    color: white !important;
}

.btn-download:hover {
    background: linear-gradient(135deg, #1e3a8a 0%, #1e293b 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(30, 64, 175, 0.3);
}

.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none !important;
}

.error-box {
    background: #fee2e2;
    border: 2px solid #ef4444;
    color: #991b1b;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
}

.success-box {
    background: #d1fae5;
    border: 2px solid #10b981;
    color: #065f46;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
}

.modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    padding: 30px;
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
}

.modal-content h3 {
    margin-top: 0;
    color: #1f2937;
}

.modal-content textarea {
    width: 100%;
    min-height: 120px;
    padding: 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-family: inherit;
    font-size: 14px;
    margin-bottom: 20px;
}

.modal-buttons {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

.btn-cancel {
    background: #6b7280;
    color: white;
}

.btn-cancel:hover {
    background: #4b5563;
}

.status-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 13px;
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
</style>

        <section class="payment-card main-content">
            <?php if ($error_message): ?>
                <div class="invoice-card">
                    <div class="error-box">
                        <h2>Access Error</h2>
                        <p><?php echo esc_html($error_message); ?></p>
                    </div>
                </div>
            <?php else: ?>
        <?php 
        $order = $invoice_data['order'];
        $org = $invoice_data['org'];
        $members = $invoice_data['members'];
        $total_amount = floatval($order->total_amount);
        
        // Map WooCommerce status
        $status_class = 'status-pending';
        $status_label = 'Pending Review';
        $is_actionable = true;
        
        if (strpos($order->status, 'processing') !== false) {
            $status_class = 'status-processing';
            $status_label = 'Approved';
            $is_actionable = false;
        } elseif (strpos($order->status, 'completed') !== false) {
            $status_class = 'status-completed';
            $status_label = 'Completed';
            $is_actionable = false;
        } elseif (strpos($order->status, 'cancelled') !== false) {
            $status_class = 'status-cancelled';
            $status_label = 'Declined';
            $is_actionable = false;
        }
        ?>
        
        <div class="invoice-card">
            <div class="invoice-header">
                <h1>📄 Invoice Review</h1>
                <div class="org-name"><?php echo esc_html($org->name); ?></div>
            </div>
            
            <div class="invoice-meta">
                <div class="meta-item">
                    <label>Order Number</label>
                    <value>#<?php echo esc_html($order->id); ?></value>
                </div>
                <div class="meta-item">
                    <label>Date Created</label>
                    <value><?php echo date('F j, Y', strtotime($order->date_created_gmt)); ?></value>
                </div>
                <div class="meta-item">
                    <label>Status</label>
                    <value><span class="status-badge <?php echo $status_class; ?>"><?php echo $status_label; ?></span></value>
                </div>
                <div class="meta-item">
                    <label>Contact Email</label>
                    <value><?php echo esc_html($org->contact_email); ?></value>
                </div>
            </div>
            
            <div class="members-section">
                <h2>Members & Fees</h2>
                <table class="members-table">
                    <thead>
                        <tr>
                            <th>Member Name</th>
                            <th>Email</th>
                            <th>Designation</th>
                            <th style="text-align: right;">Fee</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $member): ?>
                            <tr>
                                <td><?php echo esc_html(trim($member->first_name . ' ' . $member->sur_name)); ?></td>
                                <td><?php echo esc_html($member->user_email); ?></td>
                                <td><?php echo esc_html($member->user_designation); ?></td>
                                <td style="text-align: right;">€<?php echo number_format($member->fee, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td colspan="3" style="text-align: right;">Total Amount:</td>
                            <td style="text-align: right;">€<?php echo number_format($total_amount, 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <?php if ($is_actionable): ?>
                <div class="actions-section">
                    <h3>Review Invoice</h3>
                    <p style="color: #6b7280; margin-bottom: 20px;">Please review the member list and fees above. You can approve or decline this invoice.</p>
                    <div class="action-buttons">
                        <a href="<?php echo content_url('/uploads/wpo-wcpdf-temp/invoice-' . $order->id . '.pdf'); ?>" 
                           class="btn btn-download" 
                           download="invoice-<?php echo $order->id; ?>.pdf"
                           target="_blank">
                            <i class="fas fa-download"></i> Download Invoice
                        </a>
                        <button class="btn btn-approve" id="approve-btn">
                            <i class="fas fa-check-circle"></i> Approve Invoice
                        </button>
                        <button class="btn btn-decline" id="decline-btn">
                            <i class="fas fa-times-circle"></i> Decline Invoice
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="actions-section">
                    <div class="<?php echo (strpos($status_class, 'cancelled') !== false) ? 'error-box' : 'success-box'; ?>">
                        <h3><?php echo $status_label; ?></h3>
                        <p>This invoice has already been <?php echo strtolower($status_label); ?>.</p>
                    </div>
                </div>
            <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>
</main>

<!-- Decline Modal -->
<div id="decline-modal" class="modal">
    <div class="modal-content">
        <h3>Decline Invoice</h3>
        <p>Please provide a reason for declining this invoice:</p>
        <textarea id="decline-reason" placeholder="Enter your reason here..."></textarea>
        <div class="modal-buttons">
            <button class="btn btn-cancel" id="cancel-decline">Cancel</button>
            <button class="btn btn-decline" id="confirm-decline">Submit Decline</button>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    const token = '<?php echo esc_js($token); ?>';
    
    // Approve invoice
    $('#approve-btn').on('click', function() {
        if (!confirm('Are you sure you want to approve this invoice?')) {
            return;
        }
        
        const $btn = $(this);
        $btn.prop('disabled', true).text('Approving...');
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            method: 'POST',
            data: {
                action: 'iipm_approve_org_invoice',
                token: token
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                    $btn.prop('disabled', false).text('Approve Invoice');
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
                $btn.prop('disabled', false).text('Approve Invoice');
            }
        });
    });
    
    // Show decline modal
    $('#decline-btn').on('click', function() {
        $('#decline-modal').addClass('active');
    });
    
    // Cancel decline
    $('#cancel-decline').on('click', function() {
        $('#decline-modal').removeClass('active');
        $('#decline-reason').val('');
    });
    
    // Confirm decline
    $('#confirm-decline').on('click', function() {
        const reason = $('#decline-reason').val().trim();
        
        if (!reason) {
            alert('Please provide a reason for declining.');
            return;
        }
        
        const $btn = $(this);
        $btn.prop('disabled', true).text('Submitting...');
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            method: 'POST',
            data: {
                action: 'iipm_decline_org_invoice',
                token: token,
                reason: reason
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                    $btn.prop('disabled', false).text('Submit Decline');
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
                $btn.prop('disabled', false).text('Submit Decline');
            }
        });
    });
    
    // Close modal on background click
    $('#decline-modal').on('click', function(e) {
        if (e.target === this) {
            $(this).removeClass('active');
            $('#decline-reason').val('');
        }
    });
});
</script>

<?php get_footer(); ?>

