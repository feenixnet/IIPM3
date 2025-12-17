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
        $status_label = 'Pending Payment';
        $is_actionable = true;
        
        if (strpos($order->status, 'completed') !== false) {
            $status_class = 'status-completed';
            $status_label = 'Paid';
            $is_actionable = false;
        } elseif (strpos($order->status, 'cancelled') !== false) {
            $status_class = 'status-cancelled';
            $status_label = 'Cancelled';
            $is_actionable = false;
        } elseif (strpos($order->status, 'processing') !== false) {
            // Processing status shouldn't occur, but handle it just in case
            $status_class = 'status-processing';
            $status_label = 'Processing';
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
            
            <?php 
            // Get Stripe checkout URL from order meta
            global $wpdb;
            $stripe_checkout_url = $wpdb->get_var($wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->prefix}wc_orders_meta 
                WHERE order_id = %d AND meta_key = '_stripe_checkout_url'",
                $order->id
            ));
            
            // If no stored URL, generate a new one
            if (empty($stripe_checkout_url) && $is_actionable) {
                $wc_order = wc_get_order($order->id);
                if ($wc_order) {
                    $stripe_checkout_url = iipm_create_stripe_checkout_session($wc_order);
                }
            }
            ?>
            
            <?php if ($is_actionable): ?>
                <div class="actions-section">
                    <h3>Pay Invoice</h3>
                    <p style="color: #6b7280; margin-bottom: 20px;">Please review the member list and fees above, then proceed to payment.</p>
                    <div class="action-buttons">
                        <a href="<?php echo content_url('/uploads/wpo-wcpdf-temp/invoice-' . $order->id . '.pdf'); ?>" 
                           class="btn btn-download" 
                           download="invoice-<?php echo $order->id; ?>.pdf"
                           target="_blank">
                            <i class="fas fa-download"></i> Download Invoice
                        </a>
                        <?php if (!empty($stripe_checkout_url)): ?>
                            <a href="<?php echo esc_url($stripe_checkout_url); ?>" class="btn btn-approve" id="pay-btn">
                                <i class="fas fa-credit-card"></i> Pay Now
                            </a>
                        <?php else: ?>
                            <button class="btn btn-approve" disabled title="Payment link unavailable">
                                <i class="fas fa-credit-card"></i> Pay Now
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="actions-section">
                    <div class="<?php echo (strpos($status_class, 'cancelled') !== false) ? 'error-box' : 'success-box'; ?>">
                        <h3><?php echo $status_label; ?></h3>
                        <p>This invoice has already been <?php echo strtolower($status_label); ?>.</p>
                    </div>
                    <div class="action-buttons" style="margin-top: 20px;">
                        <a href="<?php echo content_url('/uploads/wpo-wcpdf-temp/invoice-' . $order->id . '.pdf'); ?>" 
                           class="btn btn-download" 
                           download="invoice-<?php echo $order->id; ?>.pdf"
                           target="_blank">
                            <i class="fas fa-download"></i> Download Invoice
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>
</main>


<?php get_footer(); ?>

