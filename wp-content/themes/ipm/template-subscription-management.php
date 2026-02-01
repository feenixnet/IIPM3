<?php
/*
Template Name: Subscription Management
*/

// Check if user has admin permissions
if (!current_user_can('manage_iipm_members') && !current_user_can('administrator')) {
    wp_redirect(home_url('/member-portal/'));
    exit;
}

// Check IIPM admin status based on user_is_admin field
if (!iipm_is_user_admin()) {
    wp_redirect(home_url('/member-portal/'));
    exit;
}

get_header();
?>

<div class="subscription-management-page">
    <div class="container">
        <!-- Page Header -->
        <div class="page-header" style="background: linear-gradient(135deg, #715091 0%, #715091 100%); color: white; padding: 60px 0; margin-bottom: 40px;">
            <div class="container">
                <h1 style="color: white; font-size: 2.5rem; font-weight: 700; margin-bottom: 15px;">Subscription Management</h1>
                <p style="color: rgba(255, 255, 255, 0.9); font-size: 1.2rem;">Manage member subscriptions and payment status</p>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="management-controls" style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); margin-bottom: 30px;">
            <div class="controls-row" style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                <div class="search-box">
                    <input type="text" id="subscription-search" placeholder="Search by name, email, or membership..." 
                           style="padding: 10px 15px; border: 1px solid #ddd; border-radius: 6px; width: 300px;">
                </div>
                
                <div class="status-filter">
                    <select id="status-filter" style="padding: 10px 15px; border: 1px solid #ddd; border-radius: 6px;">
                        <option value="">All Statuses</option>
                        <option value="1">Paid</option>
                        <option value="0">Unpaid</option>
                    </select>
                </div>
                
                <button id="refresh-subscriptions" class="btn btn-primary" style="padding: 10px 20px; background: #715091; color: white; border: none; border-radius: 6px; cursor: pointer;">
                    Refresh
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="stat-card" style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                <h3 style="color: #10b981; font-size: 2rem; margin: 0 0 10px 0;">0</h3>
                <p style="color: #6b7280; margin: 0;">Total Subscriptions</p>
            </div>
            <div class="stat-card" style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                <h3 style="color: #3b82f6; font-size: 2rem; margin: 0 0 10px 0;">0</h3>
                <p style="color: #6b7280; margin: 0;">Paid Subscriptions</p>
            </div>
            <div class="stat-card" style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                <h3 style="color: #f59e0b; font-size: 2rem; margin: 0 0 10px 0;">0</h3>
                <p style="color: #6b7280; margin: 0;">Unpaid Subscriptions</p>
            </div>
            <div class="stat-card" style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                <h3 style="color: #715091; font-size: 2rem; margin: 0 0 10px 0;">€0</h3>
                <p style="color: #6b7280; margin: 0;">Total Revenue</p>
            </div>
        </div>

        <!-- Subscriptions Table -->
        <div class="subscriptions-table-container" style="background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); overflow: hidden;">
            <div class="table-header" style="padding: 20px; border-bottom: 1px solid #e5e7eb;">
                <h3 style="margin: 0; color: #1f2937;">Subscription Orders</h3>
            </div>
            
            <div class="table-responsive">
                <table class="subscriptions-table" style="width: 100%; border-collapse: collapse;">
                    <thead style="background: #f8fafc;">
                        <tr>
                            <th style="padding: 15px; text-align: left; border-bottom: 1px solid #e5e7eb; font-weight: 600;">ID</th>
                            <th style="padding: 15px; text-align: left; border-bottom: 1px solid #e5e7eb; font-weight: 600;">User</th>
                            <th style="padding: 15px; text-align: left; border-bottom: 1px solid #e5e7eb; font-weight: 600;">Membership</th>
                            <th style="padding: 15px; text-align: left; border-bottom: 1px solid #e5e7eb; font-weight: 600;">Amount</th>
                            <th style="padding: 15px; text-align: left; border-bottom: 1px solid #e5e7eb; font-weight: 600;">Start Date</th>
                            <th style="padding: 15px; text-align: left; border-bottom: 1px solid #e5e7eb; font-weight: 600;">End Date</th>
                            <th style="padding: 15px; text-align: left; border-bottom: 1px solid #e5e7eb; font-weight: 600;">Status</th>
                            <th style="padding: 15px; text-align: left; border-bottom: 1px solid #e5e7eb; font-weight: 600;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="subscriptions-tbody">
                        <tr>
                            <td colspan="8" style="padding: 40px; text-align: center; color: #6b7280;">
                                Loading subscriptions...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="pagination-container" style="padding: 20px; border-top: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
                <div class="pagination-info">
                    <span id="pagination-info">Showing 0 of 0 results</span>
                </div>
                <div class="pagination-controls">
                    <button id="prev-page" class="btn btn-secondary" disabled style="padding: 8px 16px; margin-right: 10px; background: #6b7280; color: white; border: none; border-radius: 6px; cursor: pointer;">Previous</button>
                    <button id="next-page" class="btn btn-secondary" disabled style="padding: 8px 16px; background: #6b7280; color: white; border: none; border-radius: 6px; cursor: pointer;">Next</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    let currentPage = 1;
    let totalPages = 1;
    let currentFilters = {
        search: '',
        status_filter: ''
    };
    
    // Load subscriptions on page load
    loadSubscriptions();
    
    // Search functionality
    $('#subscription-search').on('input', function() {
        currentFilters.search = $(this).val();
        currentPage = 1;
        loadSubscriptions();
    });
    
    // Status filter
    $('#status-filter').on('change', function() {
        currentFilters.status_filter = $(this).val();
        currentPage = 1;
        loadSubscriptions();
    });
    
    // Refresh button
    $('#refresh-subscriptions').on('click', function() {
        loadSubscriptions();
    });
    
    // Pagination
    $('#prev-page').on('click', function() {
        if (currentPage > 1) {
            currentPage--;
            loadSubscriptions();
        }
    });
    
    $('#next-page').on('click', function() {
        if (currentPage < totalPages) {
            currentPage++;
            loadSubscriptions();
        }
    });
    
    function loadSubscriptions() {
        $.ajax({
            url: iipm_ajax.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'iipm_get_subscriptions_admin',
                page: currentPage,
                per_page: 20,
                status_filter: currentFilters.status_filter,
                search: currentFilters.search,
                nonce: iipm_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    displaySubscriptions(response.data.subscriptions);
                    updatePagination(response.data);
                    updateStats(response.data.subscriptions);
                } else {
                    $('#subscriptions-tbody').html('<tr><td colspan="8" style="padding: 40px; text-align: center; color: #ef4444;">Error loading subscriptions</td></tr>');
                }
            },
            error: function() {
                $('#subscriptions-tbody').html('<tr><td colspan="8" style="padding: 40px; text-align: center; color: #ef4444;">Failed to load subscriptions</td></tr>');
            }
        });
    }
    
    function displaySubscriptions(subscriptions) {
        if (subscriptions.length === 0) {
            $('#subscriptions-tbody').html('<tr><td colspan="8" style="padding: 40px; text-align: center; color: #6b7280;">No subscriptions found</td></tr>');
            return;
        }
        
        let html = '';
        subscriptions.forEach(function(sub) {
            const statusBadge = sub.status == 1 ? 
                '<span class="status-badge status-paid" style="background: #dcfce7; color: #166534; padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">Paid</span>' :
                '<span class="status-badge status-unpaid" style="background: #fee2e2; color: #dc2626; padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">Unpaid</span>';
            
            const startDate = new Date(sub.start_date).toLocaleDateString();
            const endDate = new Date(sub.end_date).toLocaleDateString();
            const paidDate = sub.paid_date ? new Date(sub.paid_date).toLocaleDateString() : '-';
            
            html += `
                <tr style="border-bottom: 1px solid #f3f4f6;">
                    <td style="padding: 15px;">#${sub.id}</td>
                    <td style="padding: 15px;">
                        <div>
                            <strong>${sub.first_name} ${sub.last_name}</strong><br>
                            <small style="color: #6b7280;">${sub.user_email}</small>
                        </div>
                    </td>
                    <td style="padding: 15px;">
                        <div>
                            <strong>${sub.membership_name || 'Unknown'}</strong><br>
                            <small style="color: #6b7280;">€${sub.amount}</small>
                        </div>
                    </td>
                    <td style="padding: 15px;">€${sub.amount}</td>
                    <td style="padding: 15px;">${startDate}</td>
                    <td style="padding: 15px;">${endDate}</td>
                    <td style="padding: 15px;">${statusBadge}</td>
                    <td style="padding: 15px;">
                        <button onclick="toggleSubscriptionStatus(${sub.id}, ${sub.status})" 
                                class="btn btn-small" 
                                style="padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem; background: ${sub.status == 1 ? '#ef4444' : '#10b981'}; color: white;">
                            ${sub.status == 1 ? 'Mark Unpaid' : 'Mark Paid'}
                        </button>
                    </td>
                </tr>
            `;
        });
        
        $('#subscriptions-tbody').html(html);
    }
    
    function updatePagination(data) {
        totalPages = data.total_pages;
        currentPage = data.current_page;
        
        $('#pagination-info').text(`Showing ${data.subscriptions.length} of ${data.total_count} results`);
        
        $('#prev-page').prop('disabled', currentPage <= 1);
        $('#next-page').prop('disabled', currentPage >= totalPages);
    }
    
    function updateStats(subscriptions) {
        const total = subscriptions.length;
        const paid = subscriptions.filter(sub => sub.status == 1).length;
        const unpaid = subscriptions.filter(sub => sub.status == 0).length;
        const revenue = subscriptions.filter(sub => sub.status == 1).reduce((sum, sub) => sum + parseFloat(sub.amount), 0);
        
        $('.stat-card').eq(0).find('h3').text(total);
        $('.stat-card').eq(1).find('h3').text(paid);
        $('.stat-card').eq(2).find('h3').text(unpaid);
        $('.stat-card').eq(3).find('h3').text('€' + revenue.toFixed(2));
    }
    
    // Global function for status toggle
    window.toggleSubscriptionStatus = function(subscriptionId, currentStatus) {
        const newStatus = currentStatus == 1 ? 0 : 1;
        const confirmMessage = newStatus == 1 ? 'Mark this subscription as paid?' : 'Mark this subscription as unpaid?';
        
        if (confirm(confirmMessage)) {
            $.ajax({
                url: iipm_ajax.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'iipm_update_subscription_status',
                    subscription_id: subscriptionId,
                    status: newStatus,
                    nonce: iipm_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        loadSubscriptions(); // Reload the table
                    } else {
                        alert('Failed to update subscription status: ' + response.data);
                    }
                },
                error: function() {
                    alert('Error updating subscription status');
                }
            });
        }
    };
});
</script>

<style>
.subscription-management-page {
    min-height: 100vh;
    background: #f8fafc;
}

.btn {
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.table-responsive {
    overflow-x: auto;
}

.subscriptions-table tr:hover {
    background: #f8fafc;
}

@media (max-width: 768px) {
    .controls-row {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-box input {
        width: 100% !important;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .pagination-container {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
}
</style>

<?php get_footer(); ?>
