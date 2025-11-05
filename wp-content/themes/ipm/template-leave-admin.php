<?php
/**
 * Template Name: Leave Admin
 * 
 * @package IPM
 */

// Redirect if not logged in or not admin
if (!is_user_logged_in() || (!current_user_can('administrator') && !current_user_can('iipm_admin'))) {
    wp_redirect(home_url('/login/'));
    exit;
}

// FORCE LOAD MAIN THEME CSS
wp_enqueue_style('iipm-main-style', get_template_directory_uri() . '/assets/css/main.min.css', array(), '1.0.0');

// Check if the modular header function exists, otherwise use default header
// if (function_exists('iipm_load_header')) {
//     iipm_load_header();
// } else {
//     get_header();
// }

get_header();

// Get all leave requests
global $wpdb;
$leave_requests = $wpdb->get_results(
    "SELECT lr.*, u.display_name, u.user_email 
     FROM {$wpdb->prefix}test_iipm_leave_requests lr
     LEFT JOIN {$wpdb->prefix}users u ON lr.user_id = u.ID
     ORDER BY lr.created_at DESC"
);

// Separate by status
$pending_requests = array();
$approved_requests = array();
$rejected_requests = array();

foreach ($leave_requests as $request) {
    switch ($request->status) {
        case 'pending':
            $pending_requests[] = $request;
            break;
        case 'approved':
            $approved_requests[] = $request;
            break;
        case 'rejected':
            $rejected_requests[] = $request;
            break;
    }
}
?>

<main id="primary" class="iipm-leave-admin-page main-container">
    <div class="container" style="position: relative; z-index: 2;">
        <!-- Hero Section -->
        <div class="page-header" style="text-align: center; margin-bottom: 40px;">
            <div>
                <h1 style="color: white; font-size: 2.5rem; margin-bottom: 10px;">Leave Request Administration</h1>
                <p style="color: rgba(255,255,255,0.9); font-size: 1.1rem;">
                Review and manage all leave requests from members
                </p>
            </div>
        </div>


        <div class="leave-admin-page tab-content main-content">

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card pending">
                    <h3><?php echo count($pending_requests); ?></h3>
                    <p>Pending Requests</p>
                </div>
                <div class="stat-card approved">
                    <h3><?php echo count($approved_requests); ?></h3>
                    <p>Approved Requests</p>
                </div>
                <div class="stat-card rejected">
                    <h3><?php echo count($rejected_requests); ?></h3>
                    <p>Rejected Requests</p>
                </div>
                <div class="stat-card total">
                    <h3><?php echo count($leave_requests); ?></h3>
                    <p>Total Requests</p>
                </div>
            </div>

            <!-- Pending Requests Section -->
            <div class="admin-section">
                <h2>Pending Requests (<?php echo count($pending_requests); ?>)</h2>
                <div class="requests-list">
                    <?php if (!empty($pending_requests)): ?>
                        <?php foreach ($pending_requests as $request): ?>
                            <div class="admin-request-item pending">
                                <div class="request-header">
                                    <div class="user-info">
                                        <h4><?php echo esc_html($request->display_name); ?></h4>
                                        <span class="user-email"><?php echo esc_html($request->user_email); ?></span>
                                    </div>
                                    <div class="request-actions">
                                        <button class="btn-approve" onclick="approveRequest(<?php echo $request->id; ?>)">
                                            ✓ Approve
                                        </button>
                                        <button class="btn-reject" onclick="rejectRequest(<?php echo $request->id; ?>)">
                                            ✗ Reject
                                        </button>
                                    </div>
                                </div>
                                <div class="request-details">
                                    <h5><?php 
                                        // Calculate duration if not available or is 0
                                        $duration = $request->duration_days;
                                        if (empty($duration) || $duration == 0) {
                                            if (function_exists('iipm_calculate_duration_days')) {
                                                $duration = iipm_calculate_duration_days($request->leave_start_date, $request->leave_end_date);
                                            } else {
                                                // Fallback calculation
                                                $start = DateTime::createFromFormat('d-m-Y', $request->leave_start_date);
                                                $end = DateTime::createFromFormat('d-m-Y', $request->leave_end_date);
                                                if ($start && $end) {
                                                    $duration = $end->diff($start)->days + 1;
                                                }
                                            }
                                        }
                                        // Show title or default "Leave Request for X days"
                                        if (empty($request->title)) {
                                            echo esc_html('Leave Request for ' . $duration . ' days');
                                        } else {
                                            echo esc_html($request->title);
                                        }
                                    ?></h5>
                                    <div class="detail-grid">
                                        <div class="detail-item">
                                            <strong>Reason:</strong> <?php echo ucfirst($request->reason); ?>
                                        </div>
                                        <div class="detail-item">
                                            <strong>Duration:</strong> <?php echo $duration; ?> days
                                        </div>
                                        <div class="detail-item">
                                            <strong>Start Date:</strong> <?php echo iipm_format_date_for_display($request->leave_start_date, 'M j, Y'); ?>
                                        </div>
                                        <div class="detail-item">
                                            <strong>End Date:</strong> <?php echo iipm_format_date_for_display($request->leave_end_date, 'M j, Y'); ?>
                                        </div>
                                    </div>
                                    <?php if ($request->description): ?>
                                        <div class="request-description">
                                            <strong>Additional Details:</strong>
                                            <p><?php echo esc_html($request->description); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    <div class="request-meta">
                                        <span>Submitted on <?php echo date('F j, Y \a\t g:i A', strtotime($request->created_at)); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-requests">
                            <p>No pending requests found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Approved Requests Section -->
            <div class="admin-section">
                <div class="section-header-with-filters">
                    <h2>Approved Requests (<span id="approved-count"><?php echo count($approved_requests); ?></span>)</h2>
                    <div class="filter-controls">
                        <input type="text" id="approved-search" placeholder="Search by name or email..." class="search-input">
                        <button class="btn-clear" onclick="clearApprovedFilters()">Clear</button>
                    </div>
                </div>
                <div class="requests-list" id="approved-requests-list">
                    <!-- Will be populated by JavaScript -->
                </div>
                <div class="pagination-container" id="approved-pagination"></div>
            </div>

            <!-- Rejected Requests Section -->
            <div class="admin-section">
                <div class="section-header-with-filters">
                    <h2>Rejected Requests (<span id="rejected-count"><?php echo count($rejected_requests); ?></span>)</h2>
                    <div class="filter-controls">
                        <input type="text" id="rejected-search" placeholder="Search by name or email..." class="search-input">
                        <button class="btn-clear" onclick="clearRejectedFilters()">Clear</button>
                    </div>
                </div>
                <div class="requests-list" id="rejected-requests-list">
                    <!-- Will be populated by JavaScript -->
                </div>
                <div class="pagination-container" id="rejected-pagination"></div>
            </div>

        </div>
    </div>
</div>

<!-- Rejection Modal -->
<div id="rejectionModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Reject Leave Request</h2>
            <span class="close" onclick="closeRejectionModal()">&times;</span>
        </div>
        <form id="rejectionForm" method="post">
            <div class="form-group">
                <label for="rejection_reason">Reason for Rejection *</label>
                <textarea id="rejection_reason" name="rejection_reason" rows="4" required placeholder="Please provide a reason for rejecting this request..."></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="closeRejectionModal()">Cancel</button>
                <button type="submit" class="btn-reject">Reject Request</button>
            </div>
        </form>
    </div>
</div>

<style>
/* ADMIN LEAVE REQUEST STYLES */
.iipm-portal-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.leave-header {
    background: linear-gradient(135deg, #8b5a96 0%, #6b4c93 100%);
    color: white;
    padding: 40px 0;
    margin-bottom: 30px;
    padding-top: 120px;
}

.portal-subtitle {
    color: #666;
    margin-top: 10px;
    font-size: 1.1rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    text-align: center;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    border-left: 4px solid;
}

.stat-card.pending { border-left-color: #ffc107; }
.stat-card.approved { border-left-color: #28a745; }
.stat-card.rejected { border-left-color: #dc3545; }
.stat-card.total { border-left-color: #8b5a96; }

.stat-card h3 {
    font-size: 2.5rem;
    margin: 0 0 10px 0;
    font-weight: 700;
}

.stat-card.pending h3 { color: #ffc107; }
.stat-card.approved h3 { color: #28a745; }
.stat-card.rejected h3 { color: #dc3545; }
.stat-card.total h3 { color: #8b5a96; }

.stat-card p {
    margin: 0;
    color: #666;
    font-weight: 500;
}

.admin-section {
    margin-bottom: 40px;
}

.admin-section h2 {
    color: #8b5a96;
    font-size: 1.5rem;
    margin-bottom: 20px;
    font-weight: 600;
}

.admin-request-item {
    background: white;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    border-left: 4px solid;
}

.admin-request-item.pending { border-left-color: #ffc107; }
.admin-request-item.approved { border-left-color: #28a745; }
.admin-request-item.rejected { border-left-color: #dc3545; }

.request-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.user-info h4 {
    margin: 0 0 5px 0;
    color: #333;
    font-weight: 600;
}

.user-email {
    color: #666;
    font-size: 14px;
}

.request-actions {
    display: flex;
    gap: 10px;
}

.btn-approve, .btn-reject {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 14px;
}

.btn-approve {
    background: #28a745;
    color: white;
}

.btn-approve:hover {
    background: #218838;
}

.btn-reject {
    background: #dc3545;
    color: white;
}

.btn-reject:hover {
    background: #c82333;
}

.request-details h5 {
    color: #333;
    margin: 0 0 15px 0;
    font-weight: 600;
    font-size: 1.1rem;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}

.detail-item {
    font-size: 14px;
}

.detail-item strong {
    color: #333;
}

.request-description {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin: 15px 0;
}

.request-description p {
    margin: 5px 0 0 0;
    color: #666;
}

.request-meta {
    font-size: 12px;
    color: #999;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-approved {
    background: #d4edda;
    color: #155724;
}

.status-rejected {
    background: #f8d7da;
    color: #721c24;
}

.no-requests {
    text-align: center;
    padding: 40px 20px;
    color: #666;
    background: white;
    border-radius: 12px;
}

/* Section header with filters */
.section-header-with-filters {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.section-header-with-filters h2 {
    color: #8b5a96;
    font-size: 1.5rem;
    margin: 0;
    font-weight: 600;
}

.filter-controls {
    display: flex;
    gap: 10px;
    align-items: center;
}

.search-input {
    padding: 10px 15px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    min-width: 250px;
    transition: border-color 0.3s;
}

.search-input:focus {
    outline: none;
    border-color: #8b5a96;
}

.btn-clear {
    background: #6c757d;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: background 0.3s;
}

.btn-clear:hover {
    background: #5a6268;
}

/* Pagination */
.pagination-container {
    margin-top: 20px;
    display: flex;
    justify-content: center;
}

.pagination {
    display: flex;
    gap: 8px;
    align-items: center;
}

.page-btn {
    padding: 8px 12px;
    border: 1px solid #ddd;
    background: white;
    color: #333;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.3s;
    min-width: 40px;
}

.page-btn:hover {
    background: #f8f9fa;
    border-color: #8b5a96;
}

.page-btn.active {
    background: #8b5a96;
    color: white;
    border-color: #8b5a96;
}

.page-dots {
    padding: 0 5px;
    color: #999;
}

@media (max-width: 768px) {
    .section-header-with-filters {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .filter-controls {
        width: 100%;
    }
    
    .search-input {
        flex: 1;
        min-width: 0;
    }
}

.rejection-reason {
    background: #f8d7da;
    padding: 15px;
    border-radius: 8px;
    margin: 15px 0;
}

.rejection-reason p {
    margin: 5px 0 0 0;
    color: #721c24;
}

/* Modal Styles */
.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 30px;
    border-bottom: 1px solid #eee;
}

.modal-header h2 {
    margin: 0;
    color: #333;
}

.close {
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    color: #aaa;
}

.close:hover {
    color: #000;
}

.form-group {
    padding: 30px;
}

.form-group label {
    display: block;
    margin-bottom: 10px;
    font-weight: 600;
    color: #333;
}

.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.3s ease;
    box-sizing: border-box;
    font-family: inherit;
}

.form-group textarea:focus {
    outline: none;
    border-color: #8b5a96;
}

.form-actions {
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    padding: 0 30px 30px 30px;
}

.btn-primary, .btn-secondary {
    padding: 12px 24px;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
}

/* Responsive Design */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .request-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .detail-grid {
        grid-template-columns: 1fr;
    }
    
    .request-actions {
        width: 100%;
        justify-content: flex-end;
    }
}
</style>

<script>
// Define global AJAX variables IMMEDIATELY (not inside document ready)
window.iipm_ajax = {
    ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
    nonce: '<?php echo wp_create_nonce('iipm_portal_nonce'); ?>'
};

// Global variable for tracking current request ID
let currentRequestId = null;

// Leave requests data
const approvedRequests = <?php echo json_encode($approved_requests); ?>;
const rejectedRequests = <?php echo json_encode($rejected_requests); ?>;

// Pagination state
let approvedCurrentPage = 1;
let rejectedCurrentPage = 1;
const itemsPerPage = 10;

// Wait for DOM to be ready before setting up event handlers
document.addEventListener('DOMContentLoaded', function() {
    console.log('Leave Admin JS loaded');
    console.log('AJAX object:', window.iipm_ajax);
    
    // Initialize pagination and filtering
    renderApprovedRequests(1, '');
    renderRejectedRequests(1, '');
    
    // Set up search handlers with debounce
    let approvedTimeout;
    document.getElementById('approved-search').addEventListener('input', function(e) {
        clearTimeout(approvedTimeout);
        approvedTimeout = setTimeout(() => {
            approvedCurrentPage = 1;
            renderApprovedRequests(1, e.target.value);
        }, 300);
    });
    
    let rejectedTimeout;
    document.getElementById('rejected-search').addEventListener('input', function(e) {
        clearTimeout(rejectedTimeout);
        rejectedTimeout = setTimeout(() => {
            rejectedCurrentPage = 1;
            renderRejectedRequests(1, e.target.value);
        }, 300);
    });
    
    // Set up rejection form handler
    const rejectionForm = document.getElementById('rejectionForm');
    if (rejectionForm) {
        rejectionForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent default form submission
            
            if (!window.iipm_ajax || !window.iipm_ajax.nonce) {
                alert('Error: AJAX configuration not loaded properly. Please refresh the page.');
                return;
            }
            
            const reason = document.getElementById('rejection_reason').value;
            
            if (!reason.trim()) {
                alert('Please provide a reason for rejection.');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'iipm_reject_leave_request');
            formData.append('nonce', window.iipm_ajax.nonce);
            formData.append('request_id', currentRequestId);
            formData.append('rejection_reason', reason);
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Rejecting...';
            submitBtn.disabled = true;
            
            fetch(window.iipm_ajax.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Reject response:', data);
                if (data.success) {
                    alert('Leave request rejected successfully!');
                    closeRejectionModal();
                    location.reload();
                } else {
                    alert('Error: ' + (data.data || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while rejecting the request.');
            })
            .finally(() => {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        });
    }
});

function approveRequest(requestId) {
    console.log('Approve request called with ID:', requestId);
    
    if (!window.iipm_ajax || !window.iipm_ajax.nonce) {
        alert('Error: AJAX configuration not loaded properly. Please refresh the page.');
        return;
    }
    
    if (!confirm('Are you sure you want to approve this leave request?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'iipm_approve_leave_request');
    formData.append('nonce', window.iipm_ajax.nonce);
    formData.append('request_id', requestId);
    
    fetch(window.iipm_ajax.ajax_url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('Approve response:', data);
        if (data.success) {
            alert('Leave request approved successfully!');
            location.reload();
        } else {
            alert('Error: ' + (data.data || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while approving the request.');
    });
}

function rejectRequest(requestId) {
    console.log('Reject request called with ID:', requestId);
    
    if (!window.iipm_ajax || !window.iipm_ajax.nonce) {
        alert('Error: AJAX configuration not loaded properly. Please refresh the page.');
        return;
    }
    
    currentRequestId = requestId;
    document.getElementById('rejectionModal').style.display = 'flex';
}

function closeRejectionModal() {
    document.getElementById('rejectionModal').style.display = 'none';
    document.getElementById('rejectionForm').reset();
    currentRequestId = null;
}

// Pagination and filtering for approved requests
function renderApprovedRequests(page = 1, searchTerm = '') {
    const searchLower = searchTerm.toLowerCase();
    const filtered = approvedRequests.filter(request => {
        const nameMatch = request.display_name ? request.display_name.toLowerCase().includes(searchLower) : false;
        const emailMatch = request.user_email ? request.user_email.toLowerCase().includes(searchLower) : false;
        return nameMatch || emailMatch;
    });
    
    const totalPages = Math.ceil(filtered.length / itemsPerPage);
    const startIndex = (page - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const pageData = filtered.slice(startIndex, endIndex);
    
    const container = document.getElementById('approved-requests-list');
    document.getElementById('approved-count').textContent = filtered.length;
    
    if (pageData.length === 0) {
        container.innerHTML = '<div class="no-requests"><p>No approved requests found.</p></div>';
        document.getElementById('approved-pagination').innerHTML = '';
        return;
    }
    
    let html = '';
    pageData.forEach(request => {
        const startDate = parseDateDDMMYYYY(request.leave_start_date);
        const endDate = parseDateDDMMYYYY(request.leave_end_date);
        const startFormatted = startDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        const endFormatted = endDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        
        // Calculate duration if not available or is 0
        let duration = request.duration_days;
        if (!duration || duration == 0) {
            const timeDiff = endDate.getTime() - startDate.getTime();
            duration = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1;
        }
        
        // Show title or default "Leave Request for X days"
        const displayTitle = request.title && request.title.trim() 
            ? request.title 
            : `Leave Request for ${duration} days`;
        
        html += `
            <div class="admin-request-item approved">
                <div class="request-header">
                    <div class="user-info">
                        <h4>${escapeHtml(request.display_name || 'Unknown User')}</h4>
                        <span class="user-email">${escapeHtml(request.user_email || 'No email')}</span>
                    </div>
                    <div class="status-badge status-approved">Approved</div>
                </div>
                <div class="request-details">
                    <h5>${escapeHtml(displayTitle)}</h5>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <strong>Duration:</strong> ${duration} days
                        </div>
                        <div class="detail-item">
                            <strong>Leave Period:</strong> ${startFormatted} - ${endFormatted}
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    renderPagination('approved', page, totalPages);
}

// Pagination and filtering for rejected requests
function renderRejectedRequests(page = 1, searchTerm = '') {
    const searchLower = searchTerm.toLowerCase();
    const filtered = rejectedRequests.filter(request => {
        const nameMatch = request.display_name ? request.display_name.toLowerCase().includes(searchLower) : false;
        const emailMatch = request.user_email ? request.user_email.toLowerCase().includes(searchLower) : false;
        return nameMatch || emailMatch;
    });
    
    const totalPages = Math.ceil(filtered.length / itemsPerPage);
    const startIndex = (page - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const pageData = filtered.slice(startIndex, endIndex);
    
    const container = document.getElementById('rejected-requests-list');
    document.getElementById('rejected-count').textContent = filtered.length;
    
    if (pageData.length === 0) {
        container.innerHTML = '<div class="no-requests"><p>No rejected requests found.</p></div>';
        document.getElementById('rejected-pagination').innerHTML = '';
        return;
    }
    
    let html = '';
    pageData.forEach(request => {
        // Calculate duration for rejected requests
        const startDate = parseDateDDMMYYYY(request.leave_start_date);
        const endDate = parseDateDDMMYYYY(request.leave_end_date);
        let duration = request.duration_days;
        if (!duration || duration == 0) {
            const timeDiff = endDate.getTime() - startDate.getTime();
            duration = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1;
        }
        
        // Show title or default "Leave Request for X days"
        const displayTitle = request.title && request.title.trim() 
            ? request.title 
            : `Leave Request for ${duration} days`;
        
        html += `
            <div class="admin-request-item rejected">
                <div class="request-header">
                    <div class="user-info">
                        <h4>${escapeHtml(request.display_name || 'Unknown User')}</h4>
                        <span class="user-email">${escapeHtml(request.user_email || 'No email')}</span>
                    </div>
                    <div class="status-badge status-rejected">Rejected</div>
                </div>
                <div class="request-details">
                    <h5>${escapeHtml(displayTitle)}</h5>
                    ${request.rejection_reason ? `
                        <div class="rejection-reason">
                            <strong>Rejection Reason:</strong>
                            <p>${escapeHtml(request.rejection_reason)}</p>
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    renderPagination('rejected', page, totalPages);
}

// Render pagination controls
function renderPagination(type, currentPage, totalPages) {
    const container = document.getElementById(`${type}-pagination`);
    
    if (!container || totalPages <= 1) {
        if (container) container.innerHTML = '';
        return;
    }
    
    let html = '<div class="pagination">';
    
    // Previous button
    if (currentPage > 1) {
        html += `<button class="page-btn" onclick="goto${capitalize(type)}Page(${currentPage - 1})">Previous</button>`;
    }
    
    // Page numbers
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
            html += `<button class="page-btn ${i === currentPage ? 'active' : ''}" onclick="goto${capitalize(type)}Page(${i})">${i}</button>`;
        } else if (i === currentPage - 3 || i === currentPage + 3) {
            html += '<span class="page-dots">...</span>';
        }
    }
    
    // Next button
    if (currentPage < totalPages) {
        html += `<button class="page-btn" onclick="goto${capitalize(type)}Page(${currentPage + 1})">Next</button>`;
    }
    
    html += '</div>';
    container.innerHTML = html;
}

// Navigation functions
function gotoApprovedPage(page) {
    approvedCurrentPage = page;
    const searchTerm = document.getElementById('approved-search').value;
    renderApprovedRequests(page, searchTerm);
    // Scroll to the approved section
    const approvedSection = document.getElementById('approved-requests-list');
    if (approvedSection) {
        approvedSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function gotoRejectedPage(page) {
    rejectedCurrentPage = page;
    const searchTerm = document.getElementById('rejected-search').value;
    renderRejectedRequests(page, searchTerm);
    // Scroll to the rejected section
    const rejectedSection = document.getElementById('rejected-requests-list');
    if (rejectedSection) {
        rejectedSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function clearApprovedFilters() {
    document.getElementById('approved-search').value = '';
    approvedCurrentPage = 1;
    renderApprovedRequests(1, '');
}

function clearRejectedFilters() {
    document.getElementById('rejected-search').value = '';
    rejectedCurrentPage = 1;
    renderRejectedRequests(1, '');
}

// Helper functions
function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

function escapeHtml(text) {
    if (text === null || text === undefined) {
        return '';
    }
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Parse dd-mm-yyyy date format correctly
function parseDateDDMMYYYY(dateString) {
    if (!dateString) return new Date();
    const parts = dateString.split('-');
    if (parts.length !== 3) return new Date(dateString);
    // parts[0] = day, parts[1] = month, parts[2] = year
    // JavaScript Date expects: year, month (0-indexed), day
    return new Date(parts[2], parseInt(parts[1]) - 1, parts[0]);
}


// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('rejectionModal');
    if (event.target === modal) {
        closeRejectionModal();
    }
});
</script>

        </div>
    </div>
</main>

<?php get_footer(); ?>
