# Payment System Implementation Summary

## ✅ COMPLETED COMPONENTS

### 1. Backend Functions (payment-management.php)

#### ✅ Organization Check Function
- `iipm_check_user_org_payment($user_id)` - Checks if user belongs to org that pays
- Returns organization data if user is part of org

#### ✅ User Payment History Function
- `iipm_get_user_payment_history($user_id, $year)` - Gets user's payment/invoice history
- Filters by year
- Returns array of orders with status, amount, dates

#### ✅ Organization Invoice Token System
- `iipm_generate_org_invoice_token($order_id)` - Creates unique 32-char token
- Stores token and 30-day expiry in order meta
- Token format: `_iipm_org_review_token`

#### ✅ Organization Invoice Retrieval
- `iipm_get_org_invoice_by_token($token)` - Fetches complete invoice data by token
- Validates token and expiry
- Returns order, organization, and member list with individual fees

#### ✅ Email Notification System
- `iipm_send_org_invoice_email($order_id, $org_id, $token)` - Sends review link to org admin
- Email includes unique URL: `/org-invoice-review/?token={token}`
- 30-day expiration notice included

#### ✅ Invoice Send Function Updated
- Modified `iipm_send_payment_invoice()` to handle organizations differently
- For orgs: generates token, sends review email (NOT PDF)
- For individuals: sends regular PDF invoice email
- Stores token in order meta for tracking

#### ✅ AJAX Endpoints

**Approve Invoice:**
```php
wp_ajax_iipm_approve_org_invoice
wp_ajax_nopriv_iipm_approve_org_invoice
```
- Updates order status to `wc-processing`
- Stores approval timestamp
- Available without login (uses token auth)

**Decline Invoice:**
```php
wp_ajax_iipm_decline_org_invoice
wp_ajax_nopriv_iipm_decline_org_invoice
```
- Updates order status to `wc-cancelled`
- Stores decline reason and timestamp
- Requires reason text
- Available without login (uses token auth)

**User Payment History:**
```php
wp_ajax_iipm_get_user_payment_history
```
- Returns user's invoice history filtered by year
- Requires user login

### 2. Organization Invoice Review Page (template-org-invoice-review.php)

#### ✅ Complete Template Created
- **Location:** `wp-content/themes/ipm/template-org-invoice-review.php`
- **Access:** Via unique token link (no login required)
- **Template Name:** "Organization Invoice Review"

#### ✅ Features Implemented:
1. **Token Validation**
   - Validates token on page load
   - Checks expiry (30 days)
   - Shows error if invalid/expired

2. **Invoice Display**
   - Organization name and details
   - Order number and date
   - Current status with badge
   - Member list table with:
     * Name
     * Email
     * Designation
     * Individual fee
     * Total calculated fee

3. **Actions Available**
   - **Download Invoice** - Print/PDF the page
   - **Approve Button** - Sets status to `wc-processing`
   - **Decline Button** - Opens modal for reason, sets status to `wc-cancelled`

4. **Status Handling**
   - If already approved/declined, shows message
   - Disables action buttons after decision
   - Visual feedback with colored badges

5. **Decline Modal**
   - Popup form for decline reason
   - Required textarea field
   - Submit/Cancel buttons
   - AJAX submission

#### ✅ Styling
- Modern, professional design
- Responsive layout
- Color-coded status badges
- Hover effects on tables
- Mobile-friendly

---

## ⏳ REMAINING TASK

### User Profile "Payments" Tab

The profile page (`template-profile.php`) already has a payment section navigation, but it currently shows subscription orders. 

#### What Needs to Be Done:

**Option A: Modify Existing Payment Section**
Replace/update the current payment section (starting at line 526) to:
1. Check if user belongs to org using `iipm_check_user_org_payment($user_id)`
2. If YES → Show "Organizational Account" message box
3. If NO → Show invoice history table with:
   - Year selector dropdown
   - Invoice/order list with columns:
     * Invoice Number
     * Date
     * Designation/Product
     * Amount
     * Status
     * Download PDF link
   - Use AJAX call to `iipm_get_user_payment_history`

**Option B: Add New "Invoice History" Tab**
Keep existing "Payment" section, add new navigation item:
- Label: "Invoice History" or "Invoices"
- Create new section with same logic as Option A
- Less disruptive to existing functionality

#### Required Code Changes:

**1. PHP - Check org membership (add near top of template-profile.php):**
```php
// Check if user payment is handled by organization
$user_org_payment = iipm_check_user_org_payment($user_id);
```

**2. HTML - Add section:**
```html
<div class="invoices-main" id="invoices-section" style="display: none;">
    <?php if ($user_org_payment): ?>
        <!-- Show org account message -->
        <div class="org-account-notice">
            <h3>Organizational Account</h3>
            <p>Your membership fees are managed by <?php echo esc_html($user_org_payment->name); ?>.</p>
            <p>For payment inquiries, please contact your organization administrator.</p>
        </div>
    <?php else: ?>
        <!-- Show invoice history -->
        <div class="invoice-header">
            <h2>Invoice History</h2>
            <select id="invoice-year-filter">
                <?php for ($y = date('Y'); $y >= 2019; $y--): ?>
                    <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <table class="invoices-table">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="invoices-tbody">
                <tr><td colspan="6">Loading...</td></tr>
            </tbody>
        </table>
    <?php endif; ?>
</div>
```

**3. JavaScript - Load invoices:**
```javascript
function loadUserInvoices(year) {
    jQuery.ajax({
        url: '<?php echo admin_url('admin-ajax.php'); ?>',
        method: 'POST',
        data: {
            action: 'iipm_get_user_payment_history',
            year: year
        },
        success: function(response) {
            if (response.success) {
                renderInvoices(response.data.orders);
            }
        }
    });
}

function renderInvoices(orders) {
    var html = '';
    orders.forEach(function(order) {
        html += '<tr>';
        html += '<td>#' + order.id + '</td>';
        html += '<td>' + formatDate(order.date_created_gmt) + '</td>';
        html += '<td>' + order.designation + '</td>';
        html += '<td>€' + parseFloat(order.total_amount).toFixed(2) + '</td>';
        html += '<td>' + getStatusBadge(order.status) + '</td>';
        html += '<td><a href="/download-invoice/?order=' + order.id + '">Download</a></td>';
        html += '</tr>';
    });
    jQuery('#invoices-tbody').html(html || '<tr><td colspan="6">No invoices found</td></tr>');
}

// Call on year change
jQuery('#invoice-year-filter').on('change', function() {
    loadUserInvoices(jQuery(this).val());
});

// Initial load
loadUserInvoices(new Date().getFullYear());
```

---

## 📋 SETUP CHECKLIST

### For Organization Invoice Review Page:

1. ✅ Backend functions created
2. ✅ Template file created
3. **TODO:** Create WordPress page with template:
   - Go to WordPress Admin → Pages → Add New
   - Title: "Organization Invoice Review"
   - Template: Select "Organization Invoice Review"
   - Slug: `org-invoice-review`
   - Publish

### For Email Testing:

Test email sending:
```php
// Manually trigger for testing
$test_order_id = 123; // Replace with real order ID
$test_org_id = 1; // Replace with real org ID
$test_token = iipm_generate_org_invoice_token($test_order_id);
iipm_send_org_invoice_email($test_order_id, $test_org_id, $test_token);
```

---

## 🔄 WORKFLOW SUMMARY

### Organization Invoice Flow:
1. Admin generates invoice for organization
2. System creates WooCommerce order (status: `pending`)
3. System generates unique token (32 chars, 30-day expiry)
4. Email sent to org admin with review link
5. Org admin clicks link → sees review page
6. Org admin reviews member list and fees
7. Org admin approves OR declines:
   - **Approve** → Status: `wc-processing` (shows in admin dashboard)
   - **Decline** → Status: `wc-cancelled` + reason stored
8. Admin sees updated status in payment dashboard

### Individual User Invoice Flow:
1. Admin generates invoice for individual
2. System creates WooCommerce order
3. PDF invoice sent directly to user email
4. User can view history in their profile (once implemented)

---

## 📝 DATABASE STRUCTURE

### ⚠️ REQUIRED: Add `org_id` Column to `wp_wc_customer_lookup`

Run this SQL query to add the new column:

```sql
ALTER TABLE `wp_wc_customer_lookup` 
ADD COLUMN `org_id` BIGINT(20) UNSIGNED NULL DEFAULT NULL AFTER `user_id`,
ADD INDEX `idx_org_id` (`org_id`);
```

This column stores the organization ID for organization customers, while `user_id` stores the individual user ID.

### Order Meta Keys Used:
- `_iipm_user_id` - User ID (for individual invoices only)
- `_iipm_designation` - Member designation or "Organization"
- `_iipm_product_id` - Product ID used
- `_iipm_invoice_year` - Year of invoice
- `_iipm_total_fee` - Total calculated fee (orgs only)
- `_iipm_org_review_token` - Unique review token
- `_iipm_org_review_token_expiry` - Token expiration timestamp
- `_iipm_org_approved_at` - Approval timestamp
- `_iipm_org_declined_at` - Decline timestamp
- `_iipm_org_decline_reason` - Reason for decline

### WooCommerce Tables Used:
- `wp_wc_orders` - Order records
- `wp_wc_orders_meta` - Order metadata
- `wp_wc_customer_lookup` - Customer lookup with org_id and user_id fields:
  - **For Organizations**: `org_id` = organization ID, `user_id` = NULL
  - **For Individuals**: `user_id` = user ID, `org_id` = NULL

---

## 🎨 UI/UX Features

### Organization Review Page:
- Clean, professional design
- Mobile responsive
- Color-coded status badges:
  * Yellow: Pending
  * Blue: Approved/Processing
  * Green: Completed
  * Red: Cancelled/Declined
- Interactive approval/decline
- Modal for decline reason
- Print-friendly invoice view

### User Profile Payment Tab (When Implemented):
- Conditional display based on org membership
- Year filtering
- Status indicators
- Download links
- Clear messaging for org members

---

## 🔐 Security Features

1. **Token-Based Auth**: No login required, but access controlled by secure token
2. **Token Expiry**: 30-day automatic expiration
3. **One-Time Use**: Once approved/declined, cannot be changed
4. **Nonce Verification**: All AJAX calls include nonce (except nopriv)
5. **Data Sanitization**: All inputs sanitized and validated

---

## 📧 Email Template

Current email sent to organization admin:

```
Subject: Invoice Review Required - [Organization Name]

Dear [Organization Name],

A new invoice has been generated for your organization.

Please review and approve or decline the invoice by clicking the link below:

[Review Link]

This link will expire in 30 days.

Order ID: [Order Number]

Best regards,
[Site Name]
```

**Future Enhancement:** Can be styled with HTML template for better presentation.

---

## 🚀 NEXT STEPS

1. **Create WordPress page** for organization invoice review template
2. **Implement user profile payments tab** (see remaining task above)
3. **Test email delivery** with SMTP plugin if needed
4. **Style email template** (optional - HTML email)
5. **Add admin notification** when org declines (optional)
6. **Add Stripe integration** (future - not in current scope)

---

## 📞 Support & Maintenance

### Common Issues:

**Emails not sending:**
- Check WordPress email settings
- Install WP Mail SMTP plugin
- Verify organization contact_email is valid

**Token expired:**
- Tokens expire after 30 days
- Admin must regenerate invoice
- No way to extend expired tokens

**Order status not updating:**
- Check database permissions
- Verify AJAX endpoints are registered
- Check browser console for errors

### Testing Commands:

```php
// Test token generation
$token = iipm_generate_org_invoice_token(123);
echo $token;

// Test invoice retrieval
$data = iipm_get_org_invoice_by_token($token);
print_r($data);

// Test org check
$org = iipm_check_user_org_payment($user_id);
print_r($org);
```

---

**Implementation Date:** November 19, 2025  
**Status:** 90% Complete  
**Remaining:** User profile payments tab integration

