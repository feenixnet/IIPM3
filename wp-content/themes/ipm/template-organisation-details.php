<?php
/**
 * Template Name: Organisation Details
 * 
 * Organisation Details page for displaying organisation information with tabs
 */

// Security check - only allow admins
if (!is_user_logged_in()) {
    wp_redirect(home_url('/login/'));
    exit;
}

$current_user = wp_get_current_user();
$is_site_admin = current_user_can('administrator') || current_user_can('manage_iipm_members');

if (!$is_site_admin) {
    wp_redirect(home_url('/organisation-management/'));
    exit;
}

// Get organisation ID from URL parameter
$org_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$org_id) {
    wp_redirect(home_url('/organisation-management/'));
    exit;
}

get_header();

// Include notification system if not already loaded
if (!function_exists('add_success_notification')) {
    include_once get_template_directory() . '/includes/notification-system.php';
}

// Enqueue and localize script for AJAX
wp_enqueue_script('jquery');
wp_add_inline_script('jquery', 'var iipm_ajax = ' . json_encode(array(
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('iipm_portal_nonce')
)) . ';', 'before');
?>

<main class="organisation-details-page main-container">
    <div class="container" style="position: relative; z-index: 2;">
        
        <!-- Page Header -->
        <div class="page-header" style="text-align: center; margin-bottom: 40px;">
            <div>
                <h1 id="page-title" style="color: white; font-size: 2.5rem; margin-bottom: 10px;">Loading Organisation Details...</h1>
                <p style="color: rgba(255,255,255,0.9); font-size: 1.1rem;">
                    View comprehensive organisation information
                </p>
                <?php IIPM_Navigation_Manager::display_breadcrumbs(); ?>
            </div>
        </div>

        <!-- Back Button -->
        <div style="margin-bottom: 30px;">
            <a href="<?php echo home_url('/organisation-management/'); ?>" style="display: inline-flex; align-items: center; padding: 12px 24px; background: #715091; color: white; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s ease;">
                <span style="margin-right: 8px;"><i class="fas fa-arrow-left"></i></span>
                Back to Organisations
            </a>
        </div>

        <!-- Organisation Details Card -->
        <div class="organisation-details-card" style="background: white; border-radius: 12px; padding: 40px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
            <div id="loading-message" style="text-align: center; padding: 40px; color: #6b7280;">
                <div class="loading-spinner" style="display: inline-block; width: 24px; height: 24px; border: 3px solid #e5e7eb; border-top: 3px solid #667eea; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                <span style="margin-left: 10px;">Loading organisation details...</span>
            </div>
            
            <!-- Tab Navigation -->
            <div id="tab-navigation" style="display: none; margin-bottom: 30px;">
                <div class="tab-buttons" style="display: flex; border-bottom: 2px solid #e5e7eb;">
                    <button class="tab-btn active" data-tab="information" style="padding: 12px 24px; background: none; border: none; border-bottom: 2px solid #667eea; color: #667eea; font-weight: 500; cursor: pointer;">
                        <i class="fas fa-building"></i> Information
                    </button>
                    <button class="tab-btn" data-tab="members" style="padding: 12px 24px; background: none; border: none; border-bottom: 2px solid transparent; color: #6b7280; font-weight: 500; cursor: pointer;">
                        <i class="fas fa-users"></i> Members
                    </button>
                    <button class="tab-btn" data-tab="payment-history" style="padding: 12px 24px; background: none; border: none; border-bottom: 2px solid transparent; color: #6b7280; font-weight: 500; cursor: pointer;">
                        <i class="fas fa-credit-card"></i> Payment History
                    </button>
                </div>
            </div>
            
            <!-- Information Tab -->
            <div id="information-tab" class="tab-content" style="display: none;">
                <div id="organisation-info-content">
                    <!-- Organisation information will be loaded here -->
                </div>
            </div>
            
            <!-- Members Tab -->
            <div id="members-tab" class="tab-content" style="display: none;">
                <div class="members-tab-content">
                    <!-- Search and Filter Section -->
                    <div class="search-filter-section" style="margin-bottom: 30px; display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                        <div class="search-box" style="flex: 1; min-width: 250px;">
                            <input type="text" id="member-search" placeholder="Search members by name or email..." 
                                   style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px;">
                        </div>
                        <div class="member-count-display" style="padding: 12px 16px; background: #f8fafc; border: 2px solid #e5e7eb; border-radius: 8px; font-weight: 500; color: #374151; min-width: 120px; text-align: center;">
                            <span id="member-count-text">Loading...</span>
                        </div>
                        
                        <div class="filter-controls" style="display: flex; gap: 15px; align-items: center;">
                            <button id="refresh-members-filter" style="padding: 12px 20px; background: #667eea; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">
                                Refresh
                            </button>
                            <button id="export-members-csv" style="padding: 12px 20px; background: #10b981; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">
                                <i class="fas fa-download" style="margin-right: 6px;"></i>
                                Export member list
                            </button>
                        </div>
                    </div>

                    <!-- Members Table -->
                    <div class="table-container" style="overflow-x: auto; border-radius: 12px; border: 1px solid #e5e7eb;">
                        <table class="members-table" style="width: 100%; border-collapse: collapse;">
                            <thead style="background: #f8fafc;">
                                <tr>
                                    <th style="padding: 16px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Name</th>
                                    <th style="padding: 16px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Email</th>
                                    <th style="padding: 16px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Status</th>
                                    <th style="padding: 16px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Last Login</th>
                                    <th style="padding: 16px; text-align: center; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb; width: 150px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="members-table-body">
                                <!-- Members will be loaded here -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div id="pagination-container" style="margin-top: 30px; display: flex; justify-content: center; gap: 10px;">
                        <!-- Pagination will be loaded here -->
                    </div>
                </div>
            </div>
            
            <!-- Payment History Tab -->
            <div id="payment-history-tab" class="tab-content" style="display: none;">
                <div class="payment-history-content">
                    <!-- Year Selector -->
                    <div style="margin-bottom: 30px; display: flex; align-items: center; gap: 15px;">
                        <select id="payment-year-selector" style="padding: 10px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; min-width: 150px;">
                            <option value="">Loading years...</option>
                        </select>
                        <button id="refresh-payments" style="padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">
                            Refresh
                        </button>
                    </div>

                    <!-- Payment History Summary -->
                    <div id="payment-history-summary" style="display: none; background: #f8fafc; border-radius: 12px; border: 1px solid #e5e7eb; padding: 24px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <span style="font-weight: 600; color: #374151;">Status:</span>
                                <span id="order-status-badge" class="status-badge"></span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <span style="font-weight: 600; color: #374151;">Total Organisation Fee:</span>
                                <span id="total-org-fee" style="font-weight: 600; color: #374151; font-size: 1.1rem;">€0.00</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Loading/Empty Message -->
                    <div id="payment-history-message" style="text-align: center; padding: 40px; color: #6b7280;">
                        Select a year to view payment history
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.info-section {
    margin-bottom: 30px;
    padding: 24px;
    background: #f9fafb;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 2px solid #e5e7eb;
}

.section-header h3 {
    margin: 0;
    color: #374151;
    font-size: 1.25rem;
    font-weight: 600;
}

.section-header h3 i {
    margin-right: 8px;
    color: #667eea;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    margin-bottom: 8px;
    font-weight: 500;
    color: #374151;
    font-size: 14px;
}

.form-group input,
.form-group select {
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 14px;
    color: #374151;
    background: white;
}

.form-group input:disabled,
.form-group select:disabled {
    background: #f9fafb;
    color: #6b7280;
    cursor: not-allowed;
}

.member-row:hover {
    background-color: #f8fafc !important;
}

.edit-toggle-btn {
    padding: 8px 16px;
    background: #667eea;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.edit-toggle-btn:hover {
    background: #5a67d8;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
    display: inline-block;
}

.status-active {
    background: #d1fae5;
    color: #065f46;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-inactive {
    background: #f3f4f6;
    color: #374151;
}

.status-suspended {
    background: #fee2e2;
    color: #991b1b;
}

.action-btn {
    padding: 6px 12px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 500;
    margin: 0 2px;
    transition: all 0.2s ease;
}

.view-btn {
    background: #3b82f6;
    color: white;
}

.view-btn:hover {
    background: #2563eb;
}
</style>

<script>
jQuery(document).ready(function($) {
    const orgId = <?php echo $org_id; ?>;
    let currentPage = 1;
    let currentSearch = '';
    let selectedYear = new Date().getFullYear();
    let orgCreatedYear = null;

    // Load organisation data on page load
    loadOrganisationData();

    // Tab functionality
    $('.tab-btn').on('click', function() {
        const tabName = $(this).data('tab');
        
        // Update tab buttons
        $('.tab-btn').removeClass('active').css({
            'border-bottom-color': 'transparent',
            'color': '#6b7280'
        });
        $(this).addClass('active').css({
            'border-bottom-color': '#667eea',
            'color': '#667eea'
        });
        
        // Show/hide tab content
        $('.tab-content').hide();
        $(`#${tabName}-tab`).show();
        
        // Load data for specific tabs
        if (tabName === 'members') {
            currentPage = 1;
            loadMembers();
        } else if (tabName === 'payment-history') {
            // Populate year selector if not already done
            if ($('#payment-year-selector option').length <= 1) {
                populateYearSelector();
            }
            // Auto-load current year if year is selected
            if ($('#payment-year-selector').val()) {
                loadPaymentHistory();
            }
        }
    });

    // Load organisation data
    function loadOrganisationData() {
        $.ajax({
            url: iipm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'iipm_get_organisation',
                org_id: orgId,
                nonce: iipm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayOrganisationInfo(response.data);
                    // Get created year for payment history year selector
                    if (response.data.created_at) {
                        const createdDate = new Date(response.data.created_at);
                        orgCreatedYear = createdDate.getFullYear();
                        if (isNaN(orgCreatedYear)) {
                            // Fallback: try parsing different date formats
                            const dateStr = response.data.created_at;
                            const yearMatch = dateStr.match(/(\d{4})/);
                            if (yearMatch) {
                                orgCreatedYear = parseInt(yearMatch[1]);
                            } else {
                                orgCreatedYear = new Date().getFullYear();
                            }
                        }
                        populateYearSelector();
                    } else {
                        orgCreatedYear = new Date().getFullYear();
                        populateYearSelector();
                    }
                    // Hide loading, show content
                    $('#loading-message').hide();
                    $('#tab-navigation').show();
                    $('#information-tab').show();
                } else {
                    showError(response.data || 'Error loading organisation data');
                }
            },
            error: function() {
                showError('An error occurred while loading organisation data.');
            }
        });
    }

    // Store original organisation data
    let originalOrgData = null;

    // Display organisation information
    function displayOrganisationInfo(org) {
        originalOrgData = org;
        const html = `
            <form id="organisation-form">
                <input type="hidden" id="org-id" name="org_id" value="${org.id}">
                <div class="info-section" data-section="org-details">
                    <div class="section-header">
                        <h3><i class="fas fa-building"></i> Organisation Details</h3>
                        <button type="button" class="edit-toggle-btn">Edit</button>
                    </div>
                    <div class="section-content">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Organisation Name:</label>
                                <input type="text" id="org-name" name="name" value="${escapeHtml(org.name || '')}" disabled>
                            </div>
                            <div class="form-group">
                                <label>Contact Email:</label>
                                <input type="email" id="contact-email" name="contact_email" value="${escapeHtml(org.contact_email || '')}" disabled>
                            </div>
                            <div class="form-group">
                                <label>Contact Phone:</label>
                                <input type="tel" id="contact-phone" name="contact_phone" value="${escapeHtml(org.contact_phone || '')}" disabled>
                            </div>
                            <div class="form-group">
                                <label>Billing Contact:</label>
                                <input type="text" id="billing-contact" name="billing_contact" value="${escapeHtml(org.billing_contact || '')}" disabled>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="info-section" data-section="address-info">
                    <div class="section-header">
                        <h3><i class="fas fa-map-marker-alt"></i> Address Information</h3>
                        <button type="button" class="edit-toggle-btn">Edit</button>
                    </div>
                    <div class="section-content">
                        <div class="form-grid">
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label>Address Line 1:</label>
                                <input type="text" id="address-line1" name="address_line1" value="${escapeHtml(org.address_line1 || '')}" disabled>
                            </div>
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label>Address Line 2:</label>
                                <input type="text" id="address-line2" name="address_line2" value="${escapeHtml(org.address_line2 || '')}" disabled>
                            </div>
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label>Address Line 3:</label>
                                <input type="text" id="address-line3" name="address_line3" value="${escapeHtml(org.address_line3 || '')}" disabled>
                            </div>
                            <div class="form-group">
                                <label>City:</label>
                                <input type="text" id="city" name="city" value="${escapeHtml(org.city || '')}" disabled>
                            </div>
                            <div class="form-group">
                                <label>County:</label>
                                <input type="text" id="county" name="county" value="${escapeHtml(org.county || '')}" disabled>
                            </div>
                            <div class="form-group">
                                <label>Eircode:</label>
                                <input type="text" id="eircode" name="eircode" value="${escapeHtml(org.eircode || '')}" disabled>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="info-section" data-section="admin-info">
                    <div class="section-header">
                        <h3><i class="fas fa-user-cog"></i> Administrator Information</h3>
                        <button type="button" class="edit-toggle-btn">Edit</button>
                    </div>
                    <div class="section-content">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Admin Name:</label>
                                <input type="text" id="admin-name" name="admin_name" value="${escapeHtml(org.admin_name || '')}" disabled>
                            </div>
                            <div class="form-group">
                                <label>Admin Email:</label>
                                <input type="email" id="admin-email" name="admin_email" value="${escapeHtml(org.admin_email || '')}" disabled>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 2px solid #e5e7eb; display: none;" id="form-actions">
                    <button type="submit" class="btn-primary" style="padding: 12px 24px; background: #667eea; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; margin-right: 10px;">
                        Save Changes
                    </button>
                    <button type="button" class="btn-secondary cancel-all-btn" style="padding: 12px 24px; background: #6b7280; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">
                        Cancel
                    </button>
                </div>
            </form>
        `;
        
        $('#organisation-info-content').html(html);
        $('#page-title').text(org.name || 'Organisation Details');
        
        // Initialize edit buttons
        initializeEditButtons();
        
        // Initialize form submission
        initializeFormSubmission();
    }

    // Load members
    function loadMembers() {
        $('#members-table-body').html(`
            <tr>
                <td colspan="5" style="padding: 40px; text-align: center; color: #6b7280;">
                    <div class="loading-spinner" style="display: inline-block; width: 20px; height: 20px; border: 2px solid #e5e7eb; border-top: 2px solid #667eea; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                    <span style="margin-left: 10px;">Loading members...</span>
                </td>
            </tr>
        `);
        $('#member-count-text').text('Loading...');

        $.ajax({
            url: iipm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'iipm_get_organisation_members',
                org_id: orgId,
                nonce: iipm_ajax.nonce,
                page: currentPage,
                search: currentSearch
            },
            success: function(response) {
                if (response.success) {
                    displayMembers(response.data.members);
                    displayPagination(response.data.pagination);
                    updateMemberCount(response.data.pagination);
                } else {
                    $('#members-table-body').html(`
                        <tr>
                            <td colspan="5" style="padding: 40px; text-align: center; color: #dc2626;">
                                Error: ${response.data}
                            </td>
                        </tr>
                    `);
                    $('#member-count-text').text('Error');
                }
            },
            error: function() {
                $('#members-table-body').html(`
                    <tr>
                        <td colspan="5" style="padding: 40px; text-align: center; color: #dc2626;">
                            Error loading members. Please try again.
                        </td>
                    </tr>
                `);
                $('#member-count-text').text('Error');
            }
        });
    }

    // Display members in table
    function displayMembers(members) {
        if (members.length === 0) {
            $('#members-table-body').html(`
                <tr>
                    <td colspan="5" style="padding: 40px; text-align: center; color: #6b7280;">
                        No members found
                    </td>
                </tr>
            `);
            return;
        }

        let html = '';
        members.forEach(function(member) {
            html += `
                <tr class="member-row">
                    <td style="padding: 16px; color: #374151;">${escapeHtml(member.display_name)}</td>
                    <td style="padding: 16px; color: #6b7280;">${escapeHtml(member.user_email)}</td>
                    <td style="padding: 16px;">
                        <span class="status-badge status-${member.membership_status}">${member.membership_status}</span>
                    </td>
                    <td style="padding: 16px; color: #6b7280;">${member.last_login || 'Never'}</td>
                    <td style="padding: 16px; text-align: center; width: 150px;">
                        <a href="${homeUrl}/member-details?id=${member.ID}" class="action-btn view-btn" style="text-decoration: none;">View</a>
                    </td>
                </tr>
            `;
        });
        $('#members-table-body').html(html);
    }

    // Update member count display
    function updateMemberCount(pagination) {
        const totalMembers = pagination.total_members || 0;
        const currentPage = pagination.current_page || 1;
        const perPage = pagination.per_page || 20;
        const startMember = ((currentPage - 1) * perPage) + 1;
        const endMember = Math.min(currentPage * perPage, totalMembers);
        
        let countText;
        if (totalMembers === 0) {
            countText = 'No members found';
        } else if (totalMembers <= perPage) {
            countText = `${totalMembers} member${totalMembers === 1 ? '' : 's'}`;
        } else {
            countText = `${startMember}-${endMember} of ${totalMembers} members`;
        }
        
        $('#member-count-text').text(countText);
    }

    // Display pagination
    function displayPagination(pagination) {
        if (pagination.total_pages <= 1) {
            $('#pagination-container').html('');
            return;
        }

        let html = '';
        
        // Previous button
        if (pagination.current_page > 1) {
            html += `<button onclick="changePage(${pagination.current_page - 1})" style="padding: 8px 12px; border: 1px solid #d1d5db; background: white; border-radius: 4px; cursor: pointer;">Previous</button>`;
        }

        // Page numbers
        for (let i = Math.max(1, pagination.current_page - 2); i <= Math.min(pagination.total_pages, pagination.current_page + 2); i++) {
            const isActive = i === pagination.current_page;
            html += `<button onclick="changePage(${i})" style="padding: 8px 12px; border: 1px solid #d1d5db; background: ${isActive ? '#667eea' : 'white'}; color: ${isActive ? 'white' : '#374151'}; border-radius: 4px; cursor: pointer;">${i}</button>`;
        }

        // Next button
        if (pagination.current_page < pagination.total_pages) {
            html += `<button onclick="changePage(${pagination.current_page + 1})" style="padding: 8px 12px; border: 1px solid #d1d5db; background: white; border-radius: 4px; cursor: pointer;">Next</button>`;
        }

        $('#pagination-container').html(html);
    }

    // Change page function (global)
    window.changePage = function(page) {
        currentPage = page;
        loadMembers();
    };

    // Search functionality
    $('#member-search').on('input', function() {
        currentSearch = $(this).val();
        currentPage = 1;
        loadMembers();
    });

    // Refresh members
    $('#refresh-members-filter').on('click', function() {
        loadMembers();
    });
    
    // Export members CSV
    $('#export-members-csv').on('click', function() {
        const $btn = $(this);
        const originalText = $btn.html();
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin" style="margin-right: 6px;"></i> Exporting...');
        
        // Create a form and submit it to trigger download
        const form = $('<form>', {
            method: 'POST',
            action: iipm_ajax.ajax_url,
            style: 'display: none;'
        });
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'action',
            value: 'iipm_export_organisation_members_csv'
        }));
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'org_id',
            value: orgId
        }));
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'nonce',
            value: iipm_ajax.nonce
        }));
        
        $('body').append(form);
        form.submit();
        form.remove();
        
        // Re-enable button after a short delay
        setTimeout(function() {
            $btn.prop('disabled', false).html(originalText);
        }, 2000);
    });

    // Populate year selector for payment history
    function populateYearSelector() {
        if (!orgCreatedYear) {
            // If no created year, use current year as default
            orgCreatedYear = new Date().getFullYear();
        }
        
        const currentYear = new Date().getFullYear();
        let html = '';
        
        for (let year = currentYear; year >= orgCreatedYear; year--) {
            const isSelected = year === selectedYear ? 'selected' : '';
            html += `<option value="${year}" ${isSelected}>${year}</option>`;
        }
        
        $('#payment-year-selector').html(html);
        
        // Set default to current year if not already set
        if (!selectedYear) {
            selectedYear = currentYear;
            $('#payment-year-selector').val(currentYear);
        }
    }

    // Load payment history
    function loadPaymentHistory() {
        const year = $('#payment-year-selector').val() || selectedYear;
        if (!year) {
            $('#payment-history-summary').hide();
            $('#payment-history-message').html('Please select a year to view payment history').css('color', '#6b7280').show();
            return;
        }
        
        selectedYear = year;

        $.ajax({
            url: iipm_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'iipm_get_organisation_payment_history',
                org_id: orgId,
                year: year,
                nonce: iipm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    displayPaymentHistory(response.data.orders);
                } else {
                    $('#payment-history-summary').hide();
                    $('#payment-history-message').html(`Error: ${response.data}`).css('color', '#dc2626').show();
                }
            },
            error: function() {
                $('#payment-history-summary').hide();
                $('#payment-history-message').html('Error loading payment history. Please try again.').css('color', '#dc2626').show();
            }
        });
    }

    // Display payment history
    function displayPaymentHistory(orders) {
        if (orders.length === 0) {
            $('#payment-history-summary').hide();
            $('#payment-history-message').html('No payment history found for the selected year').show();
            return;
        }

        // Get the latest order (most recent)
        const latestOrder = orders[0];
        
        if (!latestOrder.members || latestOrder.members.length === 0) {
            $('#payment-history-summary').hide();
            $('#payment-history-message').html('No members found for this order').show();
            return;
        }

        // Calculate total fee
        let totalFee = 0;
        latestOrder.members.forEach(function(member) {
            const fee = parseFloat(member.fee || 0);
            totalFee += fee;
        });
        
        // Display order status
        const status = latestOrder.status || '';
        const statusText = latestOrder.status_label || status.replace('wc-', '') || 'N/A';
        const statusClass = status === 'wc-completed' ? 'status-active' : 
                          status === 'wc-pending' || status === 'pending' ? 'status-pending' : 'status-inactive';
        
        $('#order-status-badge').removeClass('status-active status-pending status-inactive').addClass(statusClass).text(statusText);
        $('#total-org-fee').text('€' + totalFee.toFixed(2));
        
        // Show summary, hide message
        $('#payment-history-message').hide();
        $('#payment-history-summary').show();
    }

    // Year selector change
    $('#payment-year-selector').on('change', function() {
        selectedYear = $(this).val();
        loadPaymentHistory();
    });

    // Refresh payments
    $('#refresh-payments').on('click', function() {
        loadPaymentHistory();
    });

    // Utility function to escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Show error
    function showError(message) {
        $('#loading-message').hide();
        $('#organisation-details-card').html(`
            <div style="text-align: center; padding: 40px; color: #dc2626;">
                <i class="fas fa-exclamation-triangle" style="font-size: 48px; margin-bottom: 20px;"></i>
                <p>${escapeHtml(message)}</p>
            </div>
        `);
    }

    // Initialize edit buttons
    function initializeEditButtons() {
        $('.edit-toggle-btn').off('click').on('click', function() {
            const section = $(this);
            const isEditing = section.text() === 'Cancel';
            
            if (isEditing) {
                toggleEditMode(section, false);
            } else {
                toggleEditMode(section, true);
            }
        });
    }

    // Toggle edit mode for a section
    function toggleEditMode(section, enable) {
        const $section = section.closest('.info-section');
        const $inputs = $section.find('input:not([type="hidden"]), select');
        
        if (enable) {
            $inputs.prop('disabled', false);
            section.text('Cancel');
            $('#form-actions').show();
        } else {
            $inputs.prop('disabled', true);
            section.text('Edit');
            // Reset to original values if canceling
            if (originalOrgData) {
                resetSectionValues($section);
            }
            // Hide form actions if no sections are being edited
            if ($('.edit-toggle-btn:contains("Cancel")').length === 0) {
                $('#form-actions').hide();
            }
        }
    }

    // Reset section values to original
    function resetSectionValues($section) {
        const sectionType = $section.data('section');
        if (!originalOrgData) return;
        
        if (sectionType === 'org-details') {
            $('#org-name').val(originalOrgData.name || '');
            $('#contact-email').val(originalOrgData.contact_email || '');
            $('#contact-phone').val(originalOrgData.contact_phone || '');
            $('#billing-contact').val(originalOrgData.billing_contact || '');
        } else if (sectionType === 'address-info') {
            $('#address-line1').val(originalOrgData.address_line1 || '');
            $('#address-line2').val(originalOrgData.address_line2 || '');
            $('#address-line3').val(originalOrgData.address_line3 || '');
            $('#city').val(originalOrgData.city || '');
            $('#county').val(originalOrgData.county || '');
            $('#eircode').val(originalOrgData.eircode || '');
        } else if (sectionType === 'admin-info') {
            $('#admin-name').val(originalOrgData.admin_name || '');
            $('#admin-email').val(originalOrgData.admin_email || '');
        }
    }

    // Initialize form submission
    function initializeFormSubmission() {
        $('#organisation-form').off('submit').on('submit', function(e) {
            e.preventDefault();
            
            // Collect ALL organisation data from all sections, regardless of edit state
            const formData = new FormData();
            
            // Add all organisation fields - get values directly from inputs
            formData.append('org_id', $('#org-id').val() || '');
            formData.append('name', $('#org-name').val() || '');
            formData.append('contact_email', $('#contact-email').val() || '');
            formData.append('contact_phone', $('#contact-phone').val() || '');
            formData.append('billing_contact', $('#billing-contact').val() || '');
            formData.append('address_line1', $('#address-line1').val() || '');
            formData.append('address_line2', $('#address-line2').val() || '');
            formData.append('address_line3', $('#address-line3').val() || '');
            formData.append('city', $('#city').val() || '');
            formData.append('county', $('#county').val() || '');
            formData.append('eircode', $('#eircode').val() || '');
            formData.append('admin_name', $('#admin-name').val() || '');
            formData.append('admin_email', $('#admin-email').val() || '');
            
            // Add action and nonce
            formData.append('action', 'iipm_save_organisation');
            formData.append('nonce', '<?php echo wp_create_nonce('iipm_org_management_nonce'); ?>');
            
            // Show loading state
            const $submitBtn = $(this).find('button[type="submit"]');
            const originalText = $submitBtn.text();
            $submitBtn.prop('disabled', true).text('Saving...');
            
            $.ajax({
                url: iipm_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        if (window.notifications) {
                            notifications.success('Organisation Updated', 'Organisation information has been saved successfully.');
                        } else {
                            alert('Organisation information saved successfully!');
                        }
                        
                        // Reload organisation data to get updated values
                        loadOrganisationData();
                    } else {
                        // Show error message
                        if (window.notifications) {
                            notifications.error('Save Failed', response.data || 'Unknown error occurred');
                        } else {
                            alert('Error: ' + (response.data || 'Unknown error occurred'));
                        }
                        $submitBtn.prop('disabled', false).text(originalText);
                    }
                },
                error: function(xhr, status, error) {
                    // Show error message
                    if (window.notifications) {
                        notifications.error('Connection Error', 'Unable to save organisation. Please check your connection and try again.');
                    } else {
                        alert('Connection error. Please try again.');
                    }
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        });

        // Cancel all button
        $('.cancel-all-btn').off('click').on('click', function() {
            $('.edit-toggle-btn').each(function() {
                if ($(this).text() === 'Cancel') {
                    toggleEditMode($(this), false);
                }
            });
        });
    }

    const homeUrl = '<?php echo home_url(); ?>';
});
</script>

<?php get_footer(); ?>

