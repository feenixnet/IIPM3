<?php
/**
 * Template Name: Organisation Members Management
 * 
 * Organisation Members Management page for managing members of a specific organization
 */

// Security check - only allow admins and org admins
if (!is_user_logged_in()) {
    wp_redirect(home_url('/login/'));
    exit;
}

$current_user = wp_get_current_user();
$is_site_admin = current_user_can('administrator');
$user_organisation = null;
$is_org_admin = in_array('iipm_corporate_admin', $current_user->roles) || current_user_can('manage_organisation_members');

if (!$is_site_admin && !$is_org_admin) {
    wp_redirect(home_url('/dashboard/'));
    exit;
}

// Get employer_id from URL parameter
$employer_id = isset($_GET['employer_id']) ? intval($_GET['employer_id']) : 0;

if (!$employer_id) {
    wp_redirect(home_url('/organisation-management/'));
    exit;
}

// Get organization details
global $wpdb;
$organisation = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}test_iipm_organisations WHERE id = %d",
    $employer_id
));

if (!$organisation) {
    wp_redirect(home_url('/organisation-management/'));
    exit;
}

// Check if org admin can access this organization
if ($is_org_admin && !$is_site_admin) {
    $user_org_id = $wpdb->get_var($wpdb->prepare(
        "SELECT employer_id FROM {$wpdb->prefix}test_iipm_member_profiles WHERE user_id = %d",
        $current_user->ID
    ));
    
    if ($user_org_id != $employer_id) {
        wp_redirect(home_url('/organisation-management/'));
        exit;
    }
}

// Get the active tab from URL, default to 'current-members'
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'current-members';

get_header();
?>

<main class="organisation-members-page" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; position: relative; padding-top:120px !important; padding-bottom: 60px">
    <div class="container" style="position: relative; z-index: 2;">
        
        <!-- Page Header -->
        <div class="page-header" style="text-align: center; margin-bottom: 40px;">
            <div>
                <h1 style="color: white; font-size: 2.5rem; margin-bottom: 10px;">
                    <?php echo esc_html($organisation->name); ?> - Members
                </h1>
                <p style="color: rgba(255,255,255,0.9); font-size: 1.1rem;">
                    Manage members and import/export data for this organization
                </p>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="tab-navigation" style="margin-bottom: 30px;">
            <div style="display: flex; justify-content: center; gap: 20px;">
                <a href="?employer_id=<?php echo $employer_id; ?>&tab=current-members" 
                   class="tab-button <?php echo $active_tab === 'current-members' ? 'active' : ''; ?>" 
                   style="padding: 12px 24px; background: <?php echo $active_tab === 'current-members' ? '#f8a135' : '#6b4c93'; ?>; color: white; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s ease;">
                    <span style="margin-right: 8px;"><i class="fas fa-users"></i></span>
                    Current Members
                </a>
                <a href="?employer_id=<?php echo $employer_id; ?>&tab=bulk-import" 
                   class="tab-button <?php echo $active_tab === 'bulk-import' ? 'active' : ''; ?>" 
                   style="padding: 12px 24px; background: <?php echo $active_tab === 'bulk-import' ? '#f8a135' : '#6b4c93'; ?>; color: white; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s ease;">
                    <span style="margin-right: 8px;"><i class="fas fa-upload"></i></span>
                    Bulk Member Import
                </a>
                <a href="?employer_id=<?php echo $employer_id; ?>&tab=export" 
                   class="tab-button <?php echo $active_tab === 'export' ? 'active' : ''; ?>" 
                   style="padding: 12px 24px; background: <?php echo $active_tab === 'export' ? '#f8a135' : '#6b4c93'; ?>; color: white; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s ease;">
                    <span style="margin-right: 8px;"><i class="fas fa-download"></i></span>
                    Export
                </a>
            </div>
        </div>

        <!-- Tab Content -->
        <div class="tab-content" style="background: white; border-radius: 20px; padding: 40px; box-shadow: 0 20px 40px rgba(0,0,0,0.1);">
            
            <?php if ($active_tab === 'current-members'): ?>
                <!-- Current Members Tab -->
                <div class="current-members-content">
                    <!-- Action Buttons -->
                    <div style="display: flex; justify-content: flex-end; gap: 20px; margin-bottom: 30px;">
                        <button id="refresh-members" style="display: inline-flex; align-items: center; padding: 12px 24px; background: #667eea; color: white; border: none; border-radius: 8px; font-weight: 500; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                            <span style="margin-right: 8px;"><i class="fas fa-sync-alt"></i></span>
                            Refresh
                        </button>
                    </div>

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
                            <select id="status-filter" style="padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px;">
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="pending">Pending</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                            
                            <button id="refresh-members-filter" style="padding: 12px 20px; background: #667eea; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">
                                Refresh
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

            <?php elseif ($active_tab === 'bulk-import'): ?>
                <!-- Bulk Import Tab -->
                <div class="bulk-import-content">
                    <h3 style="margin-bottom: 20px; color: #374151;">Import Members from Other Organizations</h3>
                    <p style="margin-bottom: 30px; color: #6b7280;">Search and import members from other organizations to this organization.</p>
                    
                    <!-- Search and Filter Section -->
                    <div class="search-filter-section" style="margin-bottom: 30px; display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                        <div class="search-box" style="flex: 1; min-width: 250px;">
                            <input type="text" id="import-search" placeholder="Search members by name or email..." 
                                   style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px;">
                        </div>
                        <div class="import-count-display" style="padding: 12px 16px; background: #f8fafc; border: 2px solid #e5e7eb; border-radius: 8px; font-weight: 500; color: #374151; min-width: 120px; text-align: center;">
                            <span id="import-count-text">Loading...</span>
                        </div>
                        
                        <div class="filter-controls" style="display: flex; gap: 15px; align-items: center;">
                            <select id="import-status-filter" style="padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px;">
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="pending">Pending</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                            
                            <select id="import-org-filter" style="padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px;">
                                <option value="">All Organizations</option>
                                <!-- Organizations will be loaded here -->
                            </select>
                            
                            <button id="refresh-import" style="padding: 12px 20px; background: #667eea; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">
                                Refresh
                            </button>
                        </div>
                    </div>

                    <!-- Import Table -->
                    <div class="table-container" style="overflow-x: auto; border-radius: 12px; border: 1px solid #e5e7eb;">
                        <table class="import-table" style="width: 100%; border-collapse: collapse;">
                            <thead style="background: #f8fafc;">
                                <tr>
                                    <th style="padding: 16px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">
                                        <input type="checkbox" id="select-all-import" style="margin-right: 8px;">
                                    </th>
                                    <th style="padding: 16px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Name</th>
                                    <th style="padding: 16px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Email</th>
                                    <th style="padding: 16px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Current Organization</th>
                                    <th style="padding: 16px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb;">Status</th>
                                    <th style="padding: 16px; text-align: center; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb; width: 100px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="import-table-body">
                                <!-- Import users will be loaded here -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Import Actions -->
                    <div style="margin-top: 20px; display: flex; gap: 15px; align-items: center;">
                        <button id="import-selected" style="padding: 12px 24px; background: #10B981; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">
                            <i class="fas fa-plus" style="margin-right: 8px;"></i>
                            Import Selected
                        </button>
                        <span id="selected-count" style="color: #6b7280;">0 selected</span>
                    </div>

                    <!-- Pagination -->
                    <div id="import-pagination-container" style="margin-top: 30px; display: flex; justify-content: center; gap: 10px;">
                        <!-- Pagination will be loaded here -->
                    </div>
                </div>

            <?php elseif ($active_tab === 'export'): ?>
                <!-- Export Tab -->
                <div class="export-content">
                    <h3 style="margin-bottom: 20px; color: #374151;">Export Members Data</h3>
                    <p style="margin-bottom: 30px; color: #6b7280;">Download member data for this organization as a CSV file.</p>
                    
                    <!-- Export Options -->
                    <div class="export-options" style="background: #f8fafc; padding: 30px; border-radius: 12px; margin-bottom: 30px;">
                        <h4 style="margin-bottom: 20px; color: #374151;">Export Options</h4>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div>
                                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #374151;">Include Fields</label>
                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                    <label style="display: flex; align-items: center; cursor: pointer;">
                                        <input type="checkbox" id="export-name" checked style="margin-right: 8px;">
                                        <span>Name</span>
                                    </label>
                                    <label style="display: flex; align-items: center; cursor: pointer;">
                                        <input type="checkbox" id="export-email" checked style="margin-right: 8px;">
                                        <span>Email</span>
                                    </label>
                                    <label style="display: flex; align-items: center; cursor: pointer;">
                                        <input type="checkbox" id="export-status" checked style="margin-right: 8px;">
                                        <span>Status</span>
                                    </label>
                                    <label style="display: flex; align-items: center; cursor: pointer;">
                                        <input type="checkbox" id="export-last-login" checked style="margin-right: 8px;">
                                        <span>Last Login</span>
                                    </label>
                                    <label style="display: flex; align-items: center; cursor: pointer;">
                                        <input type="checkbox" id="export-role" checked style="margin-right: 8px;">
                                        <span>Role</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div>
                                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #374151;">Filter by Status</label>
                                <select id="export-status-filter" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;">
                                    <option value="">All Status</option>
                                    <option value="active">Active Only</option>
                                    <option value="pending">Pending Only</option>
                                    <option value="inactive">Inactive Only</option>
                                    <option value="suspended">Suspended Only</option>
                                </select>
                            </div>
                        </div>
                        
                        <button id="export-csv" style="padding: 12px 24px; background: #8B5CF6; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 500;">
                            <i class="fas fa-download" style="margin-right: 8px;"></i>
                            Export CSV
                        </button>
                    </div>
                    
                    <!-- Export Preview -->
                    <div class="export-preview" style="background: #f8fafc; padding: 20px; border-radius: 12px;">
                        <h4 style="margin-bottom: 15px; color: #374151;">Preview (First 5 Records)</h4>
                        <div id="export-preview-content" style="font-family: monospace; font-size: 12px; color: #6b7280;">
                            Click "Export CSV" to generate preview...
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Edit Member Modal -->
<div id="edit-member-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="modal-content" style="background: white; border-radius: 12px; padding: 30px; width: 90%; max-width: 500px;">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0; color: #374151;">Edit Member Status</h3>
            <button id="close-edit-member-modal" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #6b7280;">&times;</button>
        </div>
        
        <form id="edit-member-form">
            <input type="hidden" id="edit-member-id" name="member_id">
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #374151;">Member Name</label>
                <div id="edit-member-name" style="padding: 10px; background: #f9fafb; border: 1px solid #d1d5db; border-radius: 6px; color: #6b7280;"></div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #374151;">Email</label>
                <div id="edit-member-email" style="padding: 10px; background: #f9fafb; border: 1px solid #d1d5db; border-radius: 6px; color: #6b7280;"></div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 500; color: #374151;">Status</label>
                <select id="edit-member-status" name="status" 
                        style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;">
                    <option value="active">Active</option>
                    <option value="pending">Pending</option>
                    <option value="inactive">Inactive</option>
                    <option value="suspended">Suspended</option>
                </select>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" id="cancel-edit-member" 
                        style="padding: 10px 20px; background: #6b7280; color: white; border: none; border-radius: 6px; cursor: pointer;">
                    Cancel
                </button>
                <button type="submit" 
                        style="padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 6px; cursor: pointer;">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<style>
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.member-row:hover {
    background-color: #f8fafc !important;
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

.edit-btn {
    background: #3b82f6;
    color: white;
}

.edit-btn:hover {
    background: #2563eb;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
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
</style>

<script>
jQuery(document).ready(function($) {
    let currentPage = 1;
    let currentSearch = '';
    let currentStatusFilter = '';
    let currentOrgFilter = '';
    let memberToEdit = null;
    let selectedImportUsers = [];

    // Load members on page load
    loadMembers();

    // Load import users if on bulk import tab
    if ('<?php echo $active_tab; ?>' === 'bulk-import') {
        loadImportUsers();
        loadOrganizations();
    }

    // Search functionality
    $('#member-search').on('input', function() {
        currentSearch = $(this).val();
        currentPage = 1;
        loadMembers();
    });

    // Import search functionality
    $('#import-search').on('input', function() {
        currentSearch = $(this).val();
        currentPage = 1;
        loadImportUsers();
    });

    // Filter functionality
    $('#status-filter').on('change', function() {
        currentStatusFilter = $(this).val();
        currentPage = 1;
        loadMembers();
    });

    // Import filter functionality
    $('#import-status-filter, #import-org-filter').on('change', function() {
        if ($(this).attr('id') === 'import-status-filter') {
            currentStatusFilter = $(this).val();
        } else {
            currentOrgFilter = $(this).val();
        }
        currentPage = 1;
        loadImportUsers();
    });

    // Refresh functionality
    $('#refresh-members, #refresh-members-filter').on('click', function() {
        loadMembers();
    });

    // Import refresh functionality
    $('#refresh-import').on('click', function() {
        loadImportUsers();
    });

    // Load members function
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
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'iipm_get_organisation_members',
                nonce: '<?php echo wp_create_nonce('iipm_user_management_nonce'); ?>',
                employer_id: <?php echo $employer_id; ?>,
                page: currentPage,
                search: currentSearch,
                status_filter: currentStatusFilter
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
                    <td style="padding: 16px; color: #374151;">${member.display_name}</td>
                    <td style="padding: 16px; color: #6b7280;">${member.user_email}</td>
                    <td style="padding: 16px;">
                        <span class="status-badge status-${member.membership_status}">${member.membership_status}</span>
                    </td>
                    <td style="padding: 16px; color: #6b7280;">${member.last_login || 'Never'}</td>
                    <td style="padding: 16px; text-align: center; width: 150px;">
                        <button class="action-btn edit-btn" onclick="editMember(${member.ID})">Edit</button>
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

    // Edit member function (global)
    window.editMember = function(memberId) {
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'iipm_get_member_details',
                nonce: '<?php echo wp_create_nonce('iipm_user_management_nonce'); ?>',
                member_id: memberId
            },
            success: function(response) {
                if (response.success) {
                    const member = response.data;
                    $('#edit-member-id').val(member.ID);
                    $('#edit-member-name').text(member.display_name);
                    $('#edit-member-email').text(member.user_email);
                    $('#edit-member-status').val(member.membership_status);
                    $('#edit-member-modal').css('display', 'flex');
                } else {
                    alert('Error loading member details: ' + response.data);
                }
            }
        });
    };

    // Modal close handlers
    $('#close-edit-member-modal, #cancel-edit-member').on('click', function() {
        $('#edit-member-modal').hide();
    });

    // Edit member form submission
    $('#edit-member-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            action: 'iipm_update_member_status',
            nonce: '<?php echo wp_create_nonce('iipm_user_management_nonce'); ?>',
            member_id: $('#edit-member-id').val(),
            status: $('#edit-member-status').val()
        };
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    $('#edit-member-modal').hide();
                    loadMembers();
                    alert('Member status updated successfully!');
                } else {
                    alert('Error updating member status: ' + response.data);
                }
            }
        });
    });

    // Close modal when clicking outside
    $(document).on('click', function(e) {
        if ($(e.target).is('#edit-member-modal')) {
            $('#edit-member-modal').hide();
        }
    });

    // ===== BULK IMPORT FUNCTIONALITY =====
    
    // Load import users function
    function loadImportUsers() {
        $('#import-table-body').html(`
            <tr>
                <td colspan="6" style="padding: 40px; text-align: center; color: #6b7280;">
                    <div class="loading-spinner" style="display: inline-block; width: 20px; height: 20px; border: 2px solid #e5e7eb; border-top: 2px solid #667eea; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                    <span style="margin-left: 10px;">Loading members...</span>
                </td>
            </tr>
        `);
        $('#import-count-text').text('Loading...');

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'iipm_get_import_users',
                nonce: '<?php echo wp_create_nonce('iipm_user_management_nonce'); ?>',
                current_employer_id: <?php echo $employer_id; ?>,
                page: currentPage,
                search: currentSearch,
                status_filter: currentStatusFilter,
                org_filter: currentOrgFilter
            },
            success: function(response) {
                if (response.success) {
                    displayImportUsers(response.data.users);
                    displayImportPagination(response.data.pagination);
                    updateImportCount(response.data.pagination);
                } else {
                    $('#import-table-body').html(`
                        <tr>
                            <td colspan="6" style="padding: 40px; text-align: center; color: #dc2626;">
                                Error: ${response.data}
                            </td>
                        </tr>
                    `);
                    $('#import-count-text').text('Error');
                }
            },
            error: function() {
                $('#import-table-body').html(`
                    <tr>
                        <td colspan="6" style="padding: 40px; text-align: center; color: #dc2626;">
                            Error loading users. Please try again.
                        </td>
                    </tr>
                `);
                $('#import-count-text').text('Error');
            }
        });
    }

    // Display import users in table
    function displayImportUsers(users) {
        if (users.length === 0) {
            $('#import-table-body').html(`
                <tr>
                    <td colspan="6" style="padding: 40px; text-align: center; color: #6b7280;">
                        No users found for import
                    </td>
                </tr>
            `);
            return;
        }

        let html = '';
        users.forEach(function(user) {
            html += `
                <tr class="import-user-row">
                    <td style="padding: 16px; text-align: center;">
                        <input type="checkbox" class="import-user-checkbox" value="${user.ID}" style="margin-right: 8px;">
                    </td>
                    <td style="padding: 16px; color: #374151;">${user.display_name}</td>
                    <td style="padding: 16px; color: #6b7280;">${user.user_email}</td>
                    <td style="padding: 16px; color: #6b7280;">${user.organisation_name}</td>
                    <td style="padding: 16px;">
                        <span class="status-badge status-${user.membership_status}">${user.membership_status}</span>
                    </td>
                    <td style="padding: 16px; text-align: center;">
                        <button class="action-btn edit-btn" onclick="importSingleUser(${user.ID})">Import</button>
                    </td>
                </tr>
            `;
        });
        $('#import-table-body').html(html);
        
        // Update checkbox handlers
        updateImportCheckboxes();
    }

    // Update import count display
    function updateImportCount(pagination) {
        const totalUsers = pagination.total_users || 0;
        const currentPage = pagination.current_page || 1;
        const perPage = pagination.per_page || 20;
        const startUser = ((currentPage - 1) * perPage) + 1;
        const endUser = Math.min(currentPage * perPage, totalUsers);
        
        let countText;
        if (totalUsers === 0) {
            countText = 'No users found';
        } else if (totalUsers <= perPage) {
            countText = `${totalUsers} user${totalUsers === 1 ? '' : 's'}`;
        } else {
            countText = `${startUser}-${endUser} of ${totalUsers} users`;
        }
        
        $('#import-count-text').text(countText);
    }

    // Display import pagination
    function displayImportPagination(pagination) {
        if (pagination.total_pages <= 1) {
            $('#import-pagination-container').html('');
            return;
        }

        let html = '';
        
        // Previous button
        if (pagination.current_page > 1) {
            html += `<button onclick="changeImportPage(${pagination.current_page - 1})" style="padding: 8px 12px; border: 1px solid #d1d5db; background: white; border-radius: 4px; cursor: pointer;">Previous</button>`;
        }

        // Page numbers
        for (let i = Math.max(1, pagination.current_page - 2); i <= Math.min(pagination.total_pages, pagination.current_page + 2); i++) {
            const isActive = i === pagination.current_page;
            html += `<button onclick="changeImportPage(${i})" style="padding: 8px 12px; border: 1px solid #d1d5db; background: ${isActive ? '#667eea' : 'white'}; color: ${isActive ? 'white' : '#374151'}; border-radius: 4px; cursor: pointer;">${i}</button>`;
        }

        // Next button
        if (pagination.current_page < pagination.total_pages) {
            html += `<button onclick="changeImportPage(${pagination.current_page + 1})" style="padding: 8px 12px; border: 1px solid #d1d5db; background: white; border-radius: 4px; cursor: pointer;">Next</button>`;
        }

        $('#import-pagination-container').html(html);
    }

    // Change import page function (global)
    window.changeImportPage = function(page) {
        currentPage = page;
        loadImportUsers();
    };

    // Load organizations for filter
    function loadOrganizations() {
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'iipm_get_all_organisations_for_import',
                nonce: '<?php echo wp_create_nonce('iipm_user_management_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    let html = '<option value="">All Organizations</option>';
                    response.data.forEach(function(org) {
                        html += `<option value="${org.id}">${org.name}</option>`;
                    });
                    $('#import-org-filter').html(html);
                }
            }
        });
    }

    // Update import checkboxes
    function updateImportCheckboxes() {
        $('.import-user-checkbox').on('change', function() {
            const userId = $(this).val();
            if ($(this).is(':checked')) {
                if (!selectedImportUsers.includes(userId)) {
                    selectedImportUsers.push(userId);
                }
            } else {
                selectedImportUsers = selectedImportUsers.filter(id => id !== userId);
            }
            updateSelectedCount();
        });

        // Select all checkbox
        $('#select-all-import').on('change', function() {
            if ($(this).is(':checked')) {
                $('.import-user-checkbox').prop('checked', true).trigger('change');
            } else {
                $('.import-user-checkbox').prop('checked', false).trigger('change');
            }
        });
    }

    // Update selected count
    function updateSelectedCount() {
        const count = selectedImportUsers.length;
        $('#selected-count').text(`${count} selected`);
    }

    // Import single user function (global)
    window.importSingleUser = function(userId) {
        selectedImportUsers = [userId];
        importSelectedUsers();
    };

    // Import selected users
    function importSelectedUsers() {
        if (selectedImportUsers.length === 0) {
            alert('Please select users to import');
            return;
        }

        if (!confirm(`Are you sure you want to import ${selectedImportUsers.length} user(s)?`)) {
            return;
        }

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'iipm_import_users_to_organisation',
                nonce: '<?php echo wp_create_nonce('iipm_user_management_nonce'); ?>',
                user_ids: selectedImportUsers,
                employer_id: <?php echo $employer_id; ?>
            },
            success: function(response) {
                if (response.success) {
                    alert(`Successfully imported ${response.data.imported_count} user(s)!`);
                    selectedImportUsers = [];
                    updateSelectedCount();
                    loadImportUsers();
                    // Refresh members list if on current members tab
                    if ('<?php echo $active_tab; ?>' === 'current-members') {
                        loadMembers();
                    }
                } else {
                    alert('Error importing users: ' + response.data);
                }
            },
            error: function() {
                alert('Error importing users. Please try again.');
            }
        });
    }

    // Import selected button handler
    $('#import-selected').on('click', function() {
        importSelectedUsers();
    });

    // ===== EXPORT FUNCTIONALITY =====
    
    // Export CSV button handler
    $('#export-csv').on('click', function() {
        const includeFields = [];
        if ($('#export-name').is(':checked')) includeFields.push('name');
        if ($('#export-email').is(':checked')) includeFields.push('email');
        if ($('#export-status').is(':checked')) includeFields.push('status');
        if ($('#export-last-login').is(':checked')) includeFields.push('last_login');
        if ($('#export-role').is(':checked')) includeFields.push('role');

        console.log('Selected fields for export:', includeFields);

        if (includeFields.length === 0) {
            alert('Please select at least one field to export');
            return;
        }

        const statusFilter = $('#export-status-filter').val();
        console.log('Status filter:', statusFilter);
        
        // Create form and submit
        const form = $('<form>', {
            method: 'POST',
            action: '<?php echo admin_url('admin-ajax.php'); ?>',
            target: '_blank'
        });
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'action',
            value: 'iipm_export_organisation_members'
        }));
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'nonce',
            value: '<?php echo wp_create_nonce('iipm_user_management_nonce'); ?>'
        }));
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'employer_id',
            value: <?php echo $employer_id; ?>
        }));
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'status_filter',
            value: statusFilter
        }));
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'include_fields',
            value: JSON.stringify(includeFields)
        }));
        
        $('body').append(form);
        form.submit();
        form.remove();
    });
});
</script>

<?php get_footer(); ?>
