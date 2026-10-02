@extends('layouts.app')

@section('page-title', 'User Activity Logs')

@section('content')
<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include('admin.header')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        @include('user_activity_logs.partials.header')

        <!-- Tab Navigation -->
        @include('user_activity_logs.partials.tabs')

        <!-- Tab Content -->
        <div>
            <!-- Activity Logs Tab -->
            <div id="activity-logs-content" class="tab-content">
                @include('user_activity_logs.partials.filters')
                @include('user_activity_logs.partials.activity-table')
            </div>

            <!-- Online Users Tab -->
            @include('user_activity_logs.partials.online-users')

            <!-- Security Tab -->
            <div id="security-content" class="tab-content hidden">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Suspicious Activities -->
                    <div class="bg-white shadow rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                            <i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>
                            Suspicious Activities
                        </h3>
                        <div id="suspicious-activities" class="space-y-3">
                            <!-- Suspicious activities will be loaded here -->
                        </div>
                    </div>

                    <!-- IP Address Analysis -->
                    <div class="bg-white shadow rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">IP Address Analysis</h3>
                        <div id="ip-analysis" class="space-y-3">
                            <!-- IP analysis will be loaded here -->
                        </div>
                    </div>
                </div>

                <!-- Security Settings -->
                <div class="bg-white shadow rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Security Settings</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <label class="text-sm font-medium text-gray-900">Auto-logout inactive sessions</label>
                                <p class="text-sm text-gray-500">Automatically logout users after 30 minutes of inactivity</p>
                            </div>
                            <input type="checkbox" id="auto-logout" class="rounded border-gray-300 text-blue-600 shadow-sm">
                        </div>
                        <div class="flex items-center justify-between">
                            <div>
                                <label class="text-sm font-medium text-gray-900">Track failed login attempts</label>
                                <p class="text-sm text-gray-500">Monitor and log failed authentication attempts</p>
                            </div>
                            <input type="checkbox" id="track-failed-logins" class="rounded border-gray-300 text-blue-600 shadow-sm">
                        </div>
                        <div class="flex items-center justify-between">
                            <div>
                                <label class="text-sm font-medium text-gray-900">IP-based alerts</label>
                                <p class="text-sm text-gray-500">Send alerts for logins from new IP addresses</p>
                            </div>
                            <input type="checkbox" id="ip-alerts" class="rounded border-gray-300 text-blue-600 shadow-sm">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    @include('user_activity_logs.partials.modals')

    <!-- Footer -->
    @include('admin.footer')
</div>

@endsection

@section('footer-scripts')
<script>
let activityTable;
let selectedRows = [];
let currentTab = 'activity-logs';
let autoRefreshInterval;

$(document).ready(function() {
    loadUsers();
    setupEventListeners();
    setDatesToTodayAndDisable();
    initializeDataTable(); // Initialize DataTable since Activity Logs is the default tab
    startAutoRefresh();
    loadSettings();
    setupOfflineDetection(); // Add offline detection
});

// Tab Management
function switchTab(tabName) {
    // Hide all tab contents
    $('.tab-content').addClass('hidden');
    
    // Show selected tab content
    $('#' + tabName + '-content').removeClass('hidden');
    
    // Update active tab button styling based on tab color
    const tabButton = $('#' + tabName + '-tab');
    
    // Animate underline
    if (typeof window.animateTabUnderline === 'function') {
        window.animateTabUnderline(tabName);
    }
    
    // Update text color based on tab
    $('.tab-button').removeClass('text-blue-600 text-emerald-600 text-rose-600').addClass('text-gray-600');
    
    switch(tabName) {
        case 'activity-logs':
            tabButton.removeClass('text-gray-600').addClass('text-blue-600');
            break;
        case 'online-users':
            tabButton.removeClass('text-gray-600').addClass('text-emerald-600');
            break;
        case 'security':
            tabButton.removeClass('text-gray-600').addClass('text-rose-600');
            break;
    }
    
    currentTab = tabName;
    
    // Update tab info message
    if (typeof window.updateTabInfo === 'function') {
        window.updateTabInfo(tabName);
    }
    
    // Load tab-specific data
    switch(tabName) {
        case 'dashboard':
            loadDashboardData();
            break;
        case 'activity-logs':
            if (!activityTable) {
                initializeDataTable();
            } else {
                activityTable.ajax.reload();
            }
            break;
        case 'online-users':
            refreshOnlineUsers();
            break;
        case 'security':
            loadSecurityData();
            break;
    }
}

function initializeDataTable() {
    if (currentTab !== 'activity-logs') return;
    
    activityTable = $('#activity-logs-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("user-activity-logs.index") }}',
            data: function(d) {
                d.user_id = $('#user_filter').val();
                d.status = $('#status_filter').val();
                d.device_type = $('#device_filter').val();
                d.browser = $('#browser_filter').val();
                // Enforce showing only today's logs in the table
                d.date_from = getTodayDate();
                d.date_to = getTodayDate();
            }
        },
        columns: [
            // User Column - with name and email
            {
                data: null,
                name: 'user_name',
                render: function(data, type, row) {
                    return '<div class="whitespace-nowrap">' +
                           '<div class="text-sm font-semibold text-gray-900 truncate" title="' + (row.user_name || '') + '">' + (row.user_name || 'N/A') + '</div>' +
                           '<div class="text-xs text-gray-500 truncate" title="' + (row.user_email || '') + '">' + (row.user_email || 'N/A') + '</div>' +
                           '</div>';
                }
            },
            // IP Address Column - single line, no wrap
            {
                data: 'ip_address',
                name: 'ip_address',
                render: function(data) {
                    return '<span class="whitespace-nowrap font-mono text-sm text-gray-900 px-3 py-2 bg-gray-50 rounded inline-block" title="' + (data || 'N/A') + '">' + (data || 'N/A') + '</span>';
                }
            },
            // Device Info Column - with icons and badges (single line, consolidated)
            {
                data: 'device_info',
                name: 'device_info',
                orderable: false,
                render: function(data, type, row) {
                    const deviceMeta = buildDeviceDisplay(row, data);
                    const detectionText = deviceMeta.detectionSource;
                    let icon = 'fa-laptop';
                    let bgColor = 'bg-blue-50 text-blue-900';

                    if (detectionText.includes('iphone') || detectionText.includes('ios') || detectionText.includes('ipad')) {
                        icon = 'fa-mobile-alt';
                        bgColor = 'bg-indigo-50 text-indigo-900';
                    } else if (detectionText.includes('android')) {
                        icon = 'fa-android';
                        bgColor = 'bg-green-50 text-green-900';
                    } else if (detectionText.includes('windows')) {
                        icon = 'fa-windows';
                        bgColor = 'bg-cyan-50 text-cyan-900';
                    } else if (detectionText.includes('mac') || detectionText.includes('osx')) {
                        icon = 'fa-apple';
                        bgColor = 'bg-gray-50 text-gray-900';
                    } else if (detectionText.includes('linux')) {
                        icon = 'fa-linux';
                        bgColor = 'bg-orange-50 text-orange-900';
                    } else if (detectionText.includes('tablet')) {
                        icon = 'fa-tablet-alt';
                        bgColor = 'bg-purple-50 text-purple-900';
                    } else if (detectionText.includes('mobile')) {
                        icon = 'fa-mobile-alt';
                        bgColor = 'bg-indigo-50 text-indigo-900';
                    } else if (detectionText.includes('desktop')) {
                        icon = 'fa-desktop';
                        bgColor = 'bg-blue-50 text-blue-900';
                    }

                    const tooltipText = deviceMeta.detail ? deviceMeta.deviceLabel + ' — ' + deviceMeta.detail : deviceMeta.combined || deviceMeta.deviceLabel;
                    const safeTooltip = escapeHtml(tooltipText);
                    const safeDisplay = escapeHtml(deviceMeta.combined || deviceMeta.deviceLabel);

                    return '<div class="inline-flex items-center gap-2 max-w-xs px-3 py-2 rounded-lg ' + bgColor + ' border border-current border-opacity-20 font-medium text-sm whitespace-nowrap" title="' + safeTooltip + '">' +
                           '<i class="fas ' + icon + ' opacity-75"></i>' +
                           '<span class="truncate">' + safeDisplay + '</span>' +
                           '</div>';
                }
            },
            // Login Time Column - single line, no wrap
            {
                data: 'login_time',
                name: 'login_time',
                render: function(data) {
                    return '<span class="whitespace-nowrap text-sm text-gray-900 font-medium" title="' + (data || 'N/A') + '">' + (data || 'N/A') + '</span>';
                }
            },
            // Logout Time Column - single line, no wrap
            {
                data: 'logout_time',
                name: 'logout_time',
                render: function(data) {
                    return '<span class="whitespace-nowrap text-sm text-gray-900 font-medium" title="' + (data || 'N/A') + '">' + (data || 'N/A') + '</span>';
                }
            },
            // Duration Column - single line, no wrap
            {
                data: 'session_duration',
                name: 'session_duration',
                orderable: false,
                render: function(data) {
                    return '<span class="whitespace-nowrap text-sm text-gray-900 font-medium px-3 py-2 bg-green-50 rounded inline-block" title="' + (data || 'N/A') + '">' + (data || 'N/A') + '</span>';
                }
            },
            // Status Column - single line, with badge styling
            {
                data: 'online_status',
                name: 'online_status',
                orderable: false,
                render: function(data, type, row) {
                    const statusMeta = buildStatusDisplay(data, row);
                    const statusLower = statusMeta.lower;
                    const isOnline = statusLower === 'online' || statusLower === 'active';
                    const badgeColor = isOnline ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                    const icon = isOnline ? 'fa-circle text-green-500' : 'fa-circle text-red-500';
                    const safeLabel = escapeHtml(statusMeta.label || 'Offline');
                    return '<span class="whitespace-nowrap inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold ' + badgeColor + '" title="' + safeLabel + '">' +
                           '<i class="fas ' + icon + ' mr-2 text-xs"></i>' +
                           '<span>' + safeLabel + '</span>' +
                           '</span>';
                }
            },
            // Actions Column - centered
            {
                data: 'actions',
                name: 'actions',
                orderable: false,
                searchable: false,
                render: function(data) {
                    return '<div class="whitespace-nowrap text-center">' + (data || '<span class="text-gray-400 text-xs">-</span>') + '</div>';
                }
            }
        ],
        order: [[4, 'desc']],
        pageLength: 25,
        responsive: true,
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ]
    });
}

function setupEventListeners() {
    // Select all checkbox
    $('#select-all').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('.row-checkbox').prop('checked', isChecked);
        updateSelectedRows();
    });

    // Individual row checkboxes
    $(document).on('change', '.row-checkbox', function() {
        updateSelectedRows();
    });
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/[&<>"']/g, function(char) {
        switch (char) {
            case '&':
                return '&amp;';
            case '<':
                return '&lt;';
            case '>':
                return '&gt;';
            case '"':
                return '&quot;';
            case '\'':
                return '&#39;';
            default:
                return char;
        }
    });
}

function extractTextContent(htmlString) {
    if (!htmlString) return '';
    const temp = document.createElement('div');
    temp.innerHTML = htmlString;
    return (temp.textContent || temp.innerText || '').replace(/\s+/g, ' ').trim();
}

function buildDeviceDisplay(row, fallbackHtml) {
    // Flatten any HTML the server may have sent so we can render a single badge client-side
    const cleanedFallback = extractTextContent(fallbackHtml);

    const rawType = (row.device_type || '').toString().trim();
    const deviceLabel = rawType ? rawType.charAt(0).toUpperCase() + rawType.slice(1) : 'Unknown';
    const browser = (row.browser || '').toString().trim();
    const platform = (row.platform || '').toString().trim();

    let detail = '';
    if (browser && platform) {
        detail = browser + ' on ' + platform;
    } else if (browser || platform) {
        detail = browser || platform;
    }

    let combined = detail ? deviceLabel + ' · ' + detail : deviceLabel;

    if (!detail && cleanedFallback) {
        combined = cleanedFallback;
    }

    return {
        combined,
        deviceLabel,
        detail,
        detectionSource: (combined || cleanedFallback || deviceLabel).toLowerCase()
    };
}

function buildStatusDisplay(statusHtml, row) {
    const cleaned = extractTextContent(statusHtml);
    let label = cleaned || (row.online_status_text || row.online_status || 'Offline');
    label = label.toString().trim() || 'Offline';
    return {
        label,
        lower: label.toLowerCase()
    };
}

// Helper: return today's date in YYYY-MM-DD (local time)
function getTodayDate() {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

// Helper: set date inputs to today and disable them to avoid confusion
function setDatesToTodayAndDisable() {
    const today = getTodayDate();
    $('#date_from').val(today).prop('disabled', true);
    $('#date_to').val(today).prop('disabled', true);
}

function updateSelectedRows() {
    selectedRows = [];
    $('.row-checkbox:checked').each(function() {
        selectedRows.push($(this).val());
    });

    if (selectedRows.length > 0) {
        $('#bulk-delete-btn').removeClass('hidden');
        $('#selected-count').text(selectedRows.length + ' selected');
    } else {
        $('#bulk-delete-btn').addClass('hidden');
        $('#selected-count').text('');
    }
}

function loadUsers() {
    // Load users for filter dropdown
    $.get('{{ route("users.index") }}', function(data) {
        const userSelect = $('#user_filter');
        userSelect.empty().append('<option value="">All Users</option>');
        
        if (data.data) {
            data.data.forEach(function(user) {
                userSelect.append('<option value="' + user.id + '">' + user.name + '</option>');
            });
        }
    }).fail(function() {
        console.log('Failed to load users');
    });
}

function loadDashboardData() {
    // Load recent activities
    $.get('{{ route("user-activity-logs.chart-data") }}?days=1', function(response) {
        if (response.success) {
            updateRecentActivities(response.data.recent_activities);
        }
    });
}

function updateRecentActivities(activities) {
    const container = $('#recent-activities');
    container.empty();
    
    if (activities && activities.length > 0) {
        activities.forEach(function(activity) {
            const activityHtml = `
                <div class="flex items-center p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-${activity.activity_type === 'login' ? 'sign-in-alt' : 'sign-out-alt'} text-white text-xs"></i>
                        </div>
                    </div>
                    <div class="ml-3 flex-1">
                        <p class="text-sm font-medium text-gray-900">${activity.user_name}</p>
                        <p class="text-xs text-gray-500">${activity.activity_description}</p>
                        <p class="text-xs text-gray-400">${activity.time_ago}</p>
                    </div>
                </div>
            `;
            container.append(activityHtml);
        });
    } else {
        container.html('<p class="text-gray-500 text-center py-4">No recent activities</p>');
    }
}

function startAutoRefresh() {
    autoRefreshInterval = setInterval(function() {
        if (currentTab === 'dashboard') {
            loadDashboardData();
        } else if (currentTab === 'online-users') {
            refreshOnlineUsers();
        }
        
        // Update statistics
        updateStatistics();
    }, 30000); // 30 seconds
}

function updateStatistics() {
    $.get('{{ route("user-activity-logs.stats") }}', function(response) {
        if (response.success) {
            $('#total-sessions').text(response.data.total_sessions.toLocaleString());
            $('#unique-users').text(response.data.unique_users.toLocaleString());
            $('#online-users').text(response.data.online_users.toLocaleString());
            $('#avg-session').text(response.data.avg_session_duration + ' min');
            $('#online-count-badge').text(response.data.online_users);
            
            // Update last refresh time
            const now = new Date();
            const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            $('#last-refresh-time').text(timeString);
        }
    });
}

function refreshOnlineUsers() {
    $.get('{{ route("user-activity-logs.online-users") }}', function(response) {
        if (response.success) {
            updateOnlineUsersGrid(response.data);
            // Update offline status for newly created buttons
            setTimeout(function() {
                updateOfflineStatus();
            }, 100);
        }
    });
}

function updateOnlineUsersGrid(users) {
    const container = $('#online-users-grid');
    container.empty();
    
    if (users && users.length > 0) {
        users.forEach(function(user) {
            const deviceIcon = user.device_type === 'mobile' ? 'mobile-alt' : 
                              user.device_type === 'tablet' ? 'tablet-alt' : 'desktop';
            
            const isOffline = !navigator.onLine;
            const logoutButtonClass = isOffline ? 
                'logout-btn-offline bg-gray-300 text-gray-500 cursor-not-allowed' : 
                'logout-btn-online bg-red-600 hover:bg-red-700 text-white cursor-pointer';
            
            const userHtml = `
                <div class="bg-white shadow rounded-lg p-4 hover:shadow-md transition-shadow duration-200">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center relative">
                                <span class="text-white font-medium">
                                    ${user.user_name.split(' ').map(n => n[0]).join('').substring(0, 2)}
                                </span>
                                <span class="absolute -top-1 -right-1 w-4 h-4 bg-green-400 rounded-full border-2 border-white animate-pulse"></span>
                            </div>
                        </div>
                        <div class="ml-4 flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">${user.user_name}</p>
                            <p class="text-xs text-gray-500 truncate">${user.user_email}</p>
                            <div class="mt-2 flex items-center space-x-2 text-xs text-gray-500">
                                <span class="flex items-center">
                                    <i class="fas fa-${deviceIcon} mr-1"></i>
                                    ${user.device_type.charAt(0).toUpperCase() + user.device_type.slice(1)}
                                </span>
                                <span>•</span>
                                <span>${user.ip_address}</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Online since ${user.login_time}</p>
                        </div>
                        <div class="ml-3 flex-shrink-0">
                            <button 
                                onclick="${isOffline ? 'return false;' : `logoutUser('${user.user_id}')`}" 
                                class="logout-user-btn px-3 py-1 text-xs font-medium rounded-md transition-colors duration-200 ${logoutButtonClass}"
                                ${isOffline ? 'disabled title="Cannot logout user while offline"' : 'title="Logout user"'}
                                data-user-id="${user.user_id}">
                                <i class="fas fa-sign-out-alt mr-1"></i>
                                Logout
                            </button>
                        </div>
                    </div>
                </div>
            `;
            container.append(userHtml);
        });
    } else {
        container.html(`
            <div class="col-span-full text-center py-12">
                <i class="fas fa-users text-gray-400 text-4xl mb-4"></i>
                <p class="text-gray-500">No users currently online</p>
            </div>
        `);
    }
}

function loadSecurityData() {
    // Load suspicious activities and IP analysis
    $.get('{{ route("user-activity-logs.chart-data") }}?security=1', function(response) {
        if (response.success) {
            updateSuspiciousActivities(response.data.suspicious_activities);
            updateIPAnalysis(response.data.ip_analysis);
        }
    });
}

function updateSuspiciousActivities(activities) {
    const container = $('#suspicious-activities');
    container.empty();
    
    if (activities && activities.length > 0) {
        activities.forEach(function(activity) {
            const activityHtml = `
                <div class="flex items-center p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-yellow-500"></i>
                    </div>
                    <div class="ml-3 flex-1">
                        <p class="text-sm font-medium text-gray-900">${activity.description}</p>
                        <p class="text-xs text-gray-500">${activity.details}</p>
                        <p class="text-xs text-gray-400">${activity.time}</p>
                    </div>
                </div>
            `;
            container.append(activityHtml);
        });
    } else {
        container.html('<p class="text-gray-500 text-center py-4">No suspicious activities detected</p>');
    }
}

function updateIPAnalysis(analysis) {
    const container = $('#ip-analysis');
    container.empty();
    
    if (analysis && analysis.length > 0) {
        analysis.forEach(function(item) {
            const itemHtml = `
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div>
                        <p class="text-sm font-medium text-gray-900">${item.ip_address}</p>
                        <p class="text-xs text-gray-500">${item.location || 'Unknown location'}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-medium text-gray-900">${item.login_count} logins</p>
                        <p class="text-xs text-gray-500">${item.users_count} users</p>
                    </div>
                </div>
            `;
            container.append(itemHtml);
        });
    } else {
        container.html('<p class="text-gray-500 text-center py-4">No IP data available</p>');
    }
}

function applyFilters() {
    if (activityTable) {
        activityTable.ajax.reload();
    }
}

function clearFilters() {
    $('#user_filter').val('');
    $('#status_filter').val('');
    $('#device_filter').val('');
    $('#browser_filter').val('');
    // Keep the table constrained to today's logs
    setDatesToTodayAndDisable();
    if (activityTable) {
        activityTable.ajax.reload();
    }
}

function saveFilters() {
    const filters = {
        user_id: $('#user_filter').val(),
        status: $('#status_filter').val(),
        device_type: $('#device_filter').val(),
        browser: $('#browser_filter').val(),
        date_from: $('#date_from').val(),
        date_to: $('#date_to').val()
    };
    
    localStorage.setItem('activity_logs_filters', JSON.stringify(filters));
    Swal.fire('Success', 'Filters saved successfully', 'success');
}

function refreshData() {
    switch(currentTab) {
        case 'dashboard':
            loadDashboardData();
            break;
        case 'activity-logs':
            if (activityTable) {
                activityTable.ajax.reload();
            }
            break;
        case 'online-users':
            refreshOnlineUsers();
            break;
        case 'security':
            loadSecurityData();
            break;
    }
    
    updateStatistics();
    Swal.fire('Success', 'Data refreshed successfully', 'success');
}

function viewActivityDetails(id) {
    $.get('{{ route("user-activity-logs.show", ['id' => '__ID__']) }}'.replace('__ID__', id))
        .done(function(response) {
            if (response.success) {
                const data = response.data;
                const safeValue = (value, fallback = 'N/A') => escapeHtml(value ? value.toString() : fallback);
                const activityType = safeValue(data.activity_type, 'Activity');
                const activityTypeLower = (data.activity_type || '').toString().toLowerCase();
                let activityIcon = 'fa-user-clock text-blue-600';
                if (activityTypeLower.includes('login')) {
                    activityIcon = 'fa-sign-in-alt text-emerald-600';
                } else if (activityTypeLower.includes('logout')) {
                    activityIcon = 'fa-sign-out-alt text-rose-600';
                } else if (activityTypeLower.includes('password') || activityTypeLower.includes('security')) {
                    activityIcon = 'fa-shield-alt text-indigo-600';
                }

                const rawStatus = data.online_status_text || (data.is_online ? 'Online' : 'Offline');
                const statusText = safeValue(rawStatus, 'Offline');
                const statusBadge = data.is_online
                    ? `<span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700 border border-emerald-200"><i class="fas fa-circle text-emerald-500 text-xs"></i><span>${statusText}</span></span>`
                    : `<span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-rose-100 text-rose-700 border border-rose-200"><i class="fas fa-circle text-rose-500 text-xs"></i><span>${statusText}</span></span>`;

                const loginTime = safeValue(data.login_time);
                const logoutTime = safeValue(data.logout_time);
                const sessionDuration = safeValue(data.session_duration, '-');
                const userName = safeValue(data.user_name, 'Unknown User');
                const userEmail = safeValue(data.user_email);
                const ipAddress = safeValue(data.ip_address);
                const location = safeValue(data.location, 'Unknown');
                const deviceType = safeValue(data.device_type, 'Unknown');
                const browser = safeValue(data.browser);
                const platform = safeValue(data.platform);
                const userAgent = safeValue(data.user_agent);
                const createdAt = safeValue(data.created_at);
                const sessionId = safeValue(data.session_id);
                const activityDescription = data.activity_description ? `<div class="rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
                        <div class="flex items-center gap-2 text-amber-700 font-semibold text-xs uppercase tracking-wide">
                            <i class="fas fa-info-circle"></i>
                            Description
                        </div>
                        <p class="mt-3 text-sm text-amber-900 leading-relaxed">${escapeHtml(data.activity_description)}</p>
                    </div>` : '';

                const deviceTypeLower = (data.device_type || '').toString().toLowerCase();
                let deviceIcon = 'fa-desktop text-indigo-600';
                if (deviceTypeLower.includes('mobile')) {
                    deviceIcon = 'fa-mobile-alt text-indigo-600';
                } else if (deviceTypeLower.includes('tablet')) {
                    deviceIcon = 'fa-tablet-alt text-purple-600';
                } else if (deviceTypeLower.includes('laptop')) {
                    deviceIcon = 'fa-laptop text-blue-600';
                }

                const content = `
                    <div class="space-y-6">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-slate-50 border border-slate-200 rounded-xl p-4">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-full bg-white shadow-inner flex items-center justify-center">
                                    <i class="fas ${activityIcon} text-lg"></i>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Activity Type</p>
                                    <p class="text-lg font-semibold text-slate-900">${activityType}</p>
                                    <p class="mt-1 text-xs text-slate-500 flex items-center gap-1">
                                        <i class="fas fa-calendar-alt"></i>
                                        ${createdAt}
                                    </p>
                                </div>
                            </div>
                            <div class="flex flex-col items-start md:items-end gap-2">
                                ${statusBadge}
                                <div class="w-full md:w-auto max-w-xs md:max-w-sm">
                                    <p class="text-[10px] uppercase tracking-wide text-slate-400 mb-1 md:text-right">Session ID</p>
                                    <code class="block text-xs text-slate-600 bg-slate-100 border border-slate-200 rounded px-2 py-1 break-all">${sessionId}</code>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div class="p-4 rounded-lg border border-sky-100 bg-sky-50">
                                <p class="text-xs font-semibold uppercase tracking-wide text-sky-600">Login Time</p>
                                <p class="mt-1 text-sm font-semibold text-sky-900">${loginTime}</p>
                            </div>
                            <div class="p-4 rounded-lg border border-rose-100 bg-rose-50">
                                <p class="text-xs font-semibold uppercase tracking-wide text-rose-600">Logout Time</p>
                                <p class="mt-1 text-sm font-semibold text-rose-900">${logoutTime}</p>
                            </div>
                            <div class="p-4 rounded-lg border border-emerald-100 bg-emerald-50">
                                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Session Duration</p>
                                <p class="mt-1 text-sm font-semibold text-emerald-900">${sessionDuration}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            <div class="space-y-4">
                                <div class="rounded-xl border border-slate-200 p-4 shadow-sm">
                                    <div class="flex items-center gap-2 text-slate-700 font-semibold text-xs uppercase tracking-wide mb-3">
                                        <i class="fas fa-user-circle text-slate-500"></i>
                                        User Information
                                    </div>
                                    <dl class="space-y-2 text-sm text-slate-900">
                                        <div class="flex items-start justify-between gap-4">
                                            <dt class="text-slate-500 text-xs uppercase tracking-wide">Name</dt>
                                            <dd class="font-medium text-right">${userName}</dd>
                                        </div>
                                        <div class="flex items-start justify-between gap-4">
                                            <dt class="text-slate-500 text-xs uppercase tracking-wide">Email</dt>
                                            <dd class="font-medium text-right">${userEmail}</dd>
                                        </div>
                                        <div class="flex items-start justify-between gap-4">
                                            <dt class="text-slate-500 text-xs uppercase tracking-wide">Location</dt>
                                            <dd class="font-medium text-right">${location}</dd>
                                        </div>
                                    </dl>
                                </div>
                                <div class="rounded-xl border border-indigo-200 bg-indigo-50/50 p-4 shadow-sm">
                                    <div class="flex items-center gap-2 text-indigo-700 font-semibold text-xs uppercase tracking-wide mb-3">
                                        <i class="fas fa-network-wired"></i>
                                        Network Footprint
                                    </div>
                                    <dl class="space-y-2 text-sm text-slate-900">
                                        <div class="flex items-start justify-between gap-4">
                                            <dt class="text-xs uppercase tracking-wide text-slate-500">IP Address</dt>
                                            <dd class="font-mono text-sm text-slate-900">${ipAddress}</dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div class="rounded-xl border border-slate-200 p-4 shadow-sm">
                                    <div class="flex items-center gap-2 text-slate-700 font-semibold text-xs uppercase tracking-wide mb-3">
                                        <i class="fas fa-clipboard-list text-slate-500"></i>
                                        Session Details
                                    </div>
                                    <dl class="space-y-2 text-sm text-slate-900">
                                        <div class="flex items-start justify-between gap-4">
                                            <dt class="text-xs uppercase tracking-wide text-slate-500">Status</dt>
                                            <dd class="font-medium text-right">${statusText}</dd>
                                        </div>
                                        <div class="flex items-start justify-between gap-4">
                                            <dt class="text-xs uppercase tracking-wide text-slate-500">Activity</dt>
                                            <dd class="font-medium text-right">${activityType}</dd>
                                        </div>
                                        <div class="flex items-start justify-between gap-4">
                                            <dt class="text-xs uppercase tracking-wide text-slate-500">Created</dt>
                                            <dd class="font-medium text-right">${createdAt}</dd>
                                        </div>
                                        <div class="flex items-start justify-between gap-4">
                                            <dt class="text-xs uppercase tracking-wide text-slate-500">Session ID</dt>
                                            <dd class="font-mono text-xs text-right break-all">${sessionId}</dd>
                                        </div>
                                    </dl>
                                </div>
                                <div class="rounded-xl border border-slate-200 p-4 shadow-sm">
                                    <div class="flex items-center gap-2 text-slate-700 font-semibold text-xs uppercase tracking-wide mb-3">
                                        <i class="fas ${deviceIcon}"></i>
                                        Device Insights
                                    </div>
                                    <dl class="space-y-2 text-sm text-slate-900">
                                        <div class="flex items-start justify-between gap-4">
                                            <dt class="text-xs uppercase tracking-wide text-slate-500">Device</dt>
                                            <dd class="font-medium text-right">${deviceType}</dd>
                                        </div>
                                        <div class="flex items-start justify-between gap-4">
                                            <dt class="text-xs uppercase tracking-wide text-slate-500">Browser</dt>
                                            <dd class="font-medium text-right">${browser}</dd>
                                        </div>
                                        <div class="flex items-start justify-between gap-4">
                                            <dt class="text-xs uppercase tracking-wide text-slate-500">Platform</dt>
                                            <dd class="font-medium text-right">${platform}</dd>
                                        </div>
                                    </dl>
                                    <div class="mt-3">
                                        <p class="text-xs uppercase tracking-wide text-slate-500 mb-1">User Agent</p>
                                        <code class="block text-xs leading-snug text-slate-600 bg-slate-100 border border-slate-200 rounded-lg p-3">${userAgent}</code>
                                    </div>
                                </div>
                            </div>
                        </div>

                        ${activityDescription}
                    </div>
                `;
                
                $('#activity-details-content').html(content);
                $('#activity-details-modal').removeClass('hidden').show();
            }
        })
        .fail(function() {
            Swal.fire('Error', 'Failed to load activity details', 'error');
        });
}

function closeActivityModal() {
    $('#activity-details-modal').addClass('hidden').hide();
}

function logoutUser(userId) {
    console.log('Logout user called with userId:', userId);
    
    // Validate userId before proceeding
    if (!userId || userId === 'undefined' || userId === 'null' || userId === '' || isNaN(userId)) {
        console.error('Invalid user ID provided:', userId);
        Swal.fire({
            title: 'Error',
            text: 'Invalid user ID. Please refresh the page and try again.',
            icon: 'error',
            confirmButtonText: 'OK'
        });
        return;
    }
    
    // Check if user is offline
    if (!navigator.onLine) {
        Swal.fire({
            title: 'Offline',
            text: 'Cannot logout user while you are offline. Please check your internet connection.',
            icon: 'warning',
            confirmButtonText: 'OK'
        });
        return;
    }
    
    Swal.fire({
        title: 'Are you sure?',
        text: 'This will log out the user from all their active sessions.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#f59e0b',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, logout user!'
    }).then((result) => {
        if (result.isConfirmed) {
            const url = '{{ route("user-activity-logs.logout-user", ['userId' => '__USER_ID__']) }}'.replace('__USER_ID__', userId);
            console.log('Making request to:', url);
            
            $.ajax({
                url: url,
                type: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                beforeSend: function() {
                    console.log('Sending logout request...');
                },
                success: function(response) {
                    console.log('Success response:', response);
                    if (response.success) {
                        Swal.fire('Success!', response.message, 'success');
                        if (activityTable) {
                            activityTable.ajax.reload();
                        }
                        // Also refresh online users if on that tab
                        if (currentTab === 'online-users') {
                            refreshOnlineUsers();
                        }
                        updateStatistics();
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', {
                        status: xhr.status,
                        statusText: xhr.statusText,
                        responseText: xhr.responseText,
                        error: error
                    });
                    
                    let errorMessage = 'Failed to logout user';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    } else if (xhr.responseText) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            errorMessage = response.message || errorMessage;
                        } catch (e) {
                            errorMessage = 'Server error: ' + xhr.status + ' ' + xhr.statusText;
                        }
                    }
                    
                    Swal.fire('Error', errorMessage, 'error');
                }
            });
        }
    });
}

function bulkDelete() {
    if (selectedRows.length === 0) {
        Swal.fire('Warning', 'Please select at least one activity log to delete', 'warning');
        return;
    }

    Swal.fire({
        title: 'Are you sure?',
        text: `${selectedRows.length} activity logs will be permanently deleted.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, delete them!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '{{ route("user-activity-logs.bulk-delete") }}',
                type: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    ids: selectedRows
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Deleted!', response.message, 'success');
                        if (activityTable) {
                            activityTable.ajax.reload();
                        }
                        selectedRows = [];
                        $('#select-all').prop('checked', false);
                        updateSelectedRows();
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Failed to delete activity logs', 'error');
                }
            });
        }
    });
}

// Offline Detection Functions
function setupOfflineDetection() {
    // Initial status check
    updateOfflineStatus();
    
    // Listen for online/offline events
    window.addEventListener('online', function() {
        updateOfflineStatus();
        showNetworkStatusMessage('online');
    });
    
    window.addEventListener('offline', function() {
        updateOfflineStatus();
        showNetworkStatusMessage('offline');
    });
}

function updateOfflineStatus() {
    const isOffline = !navigator.onLine;
    
    // Update main logout button in header
    updateMainLogoutButton(isOffline);
    
    // Update logout buttons in online users grid
    updateLogoutButtons(isOffline);
    
    // Update other action buttons that require internet
    updateActionButtons(isOffline);
}

function updateMainLogoutButton(isOffline) {
    const logoutForm = document.getElementById('autoLogoutForm');
    const logoutButton = logoutForm ? logoutForm.querySelector('button[type="submit"]') : null;
    
    if (logoutButton) {
        if (isOffline) {
            logoutButton.disabled = true;
            logoutButton.classList.add('opacity-50', 'cursor-not-allowed', 'bg-gray-300', 'text-gray-500');
            logoutButton.classList.remove('hover:bg-gray-100', 'text-gray-700');
            logoutButton.setAttribute('title', 'Cannot logout while offline - Please check your internet connection');
            // Prevent form submission when offline
            logoutForm.addEventListener('submit', preventOfflineSubmit);
            
            // Add visual indicator for offline state
            const icon = logoutButton.querySelector('svg');
            if (icon) {
                icon.classList.add('text-gray-400');
                icon.classList.remove('text-gray-500');
            }
        } else {
            logoutButton.disabled = false;
            logoutButton.classList.remove('opacity-50', 'cursor-not-allowed', 'bg-gray-300', 'text-gray-500');
            logoutButton.classList.add('hover:bg-gray-100', 'text-gray-700');
            logoutButton.setAttribute('title', 'Logout');
            // Remove the offline submit prevention
            logoutForm.removeEventListener('submit', preventOfflineSubmit);
            
            // Restore normal icon styling
            const icon = logoutButton.querySelector('svg');
            if (icon) {
                icon.classList.remove('text-gray-400');
                icon.classList.add('text-gray-500');
            }
        }
    }
}

function preventOfflineSubmit(event) {
    if (!navigator.onLine) {
        event.preventDefault();
        Swal.fire({
            title: 'Offline',
            text: 'Cannot logout while you are offline. Please check your internet connection.',
            icon: 'warning',
            confirmButtonText: 'OK'
        });
    }
}

function updateLogoutButtons(isOffline) {
    // Update logout buttons in online users grid
    const logoutButtons = document.querySelectorAll('.logout-user-btn');
    
    logoutButtons.forEach(function(button) {
        if (isOffline) {
            button.disabled = true;
            button.classList.remove('logout-btn-online', 'text-orange-600', 'hover:text-orange-900', 'text-white', 'cursor-pointer');
            button.classList.add('logout-btn-offline', 'text-gray-400', 'cursor-not-allowed', 'opacity-50');
            button.setAttribute('title', 'Cannot logout user while offline');
            button.onclick = function() { return false; };
        } else {
            const userStatus = button.getAttribute('data-user-status');
            // Only enable if user is online and admin is online
            if (userStatus === 'online') {
                button.disabled = false;
                button.classList.remove('logout-btn-offline', 'text-gray-400', 'cursor-not-allowed', 'opacity-50');
                button.classList.add('logout-btn-online', 'text-orange-600', 'hover:text-orange-900', 'cursor-pointer');
                button.setAttribute('title', 'Logout user');
                const userId = button.getAttribute('data-user-id');
                button.onclick = function() { logoutUser(userId); };
            }
        }
    });
    
    // Also update logout buttons in DataTable (if table exists)
    if (activityTable) {
        // Redraw the table to update action buttons with offline status
        setTimeout(function() {
            updateDataTableLogoutButtons(isOffline);
        }, 100);
    }
}

function updateDataTableLogoutButtons(isOffline) {
    // Update logout buttons in the DataTable
    const tableLogoutButtons = document.querySelectorAll('#activity-logs-table .logout-user-btn');
    
    tableLogoutButtons.forEach(function(button) {
        if (isOffline) {
            button.disabled = true;
            button.classList.remove('text-orange-600', 'hover:text-orange-900');
            button.classList.add('text-gray-400', 'cursor-not-allowed', 'opacity-50');
            button.setAttribute('title', 'Cannot logout user while offline');
            button.onclick = function() { 
                Swal.fire({
                    title: 'Offline',
                    text: 'Cannot logout user while you are offline. Please check your internet connection.',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
                return false; 
            };
        } else {
            const userStatus = button.getAttribute('data-user-status');
            // Only enable if user is online
            if (userStatus === 'online') {
                button.disabled = false;
                button.classList.remove('text-gray-400', 'cursor-not-allowed', 'opacity-50');
                button.classList.add('text-orange-600', 'hover:text-orange-900');
                button.setAttribute('title', 'Logout user');
                const userId = button.getAttribute('data-user-id');
                button.onclick = function() { logoutUser(userId); };
            }
        }
    });
}

function updateActionButtons(isOffline) {
    // Update other buttons that require network access
    const networkButtons = [
        '#bulk-delete-btn',
        'button[onclick="exportLogs()"]',
        'button[onclick="refreshData()"]',
        'button[onclick="refreshOnlineUsers()"]',
        'button[onclick="applyFilters()"]'
    ];
    
    networkButtons.forEach(function(selector) {
        const button = document.querySelector(selector);
        if (button) {
            if (isOffline) {
                button.disabled = true;
                button.classList.add('opacity-50', 'cursor-not-allowed');
                const originalTitle = button.getAttribute('title') || '';
                button.setAttribute('title', originalTitle + ' (Offline - feature disabled)');
            } else {
                button.disabled = false;
                button.classList.remove('opacity-50', 'cursor-not-allowed');
                const title = button.getAttribute('title') || '';
                button.setAttribute('title', title.replace(' (Offline - feature disabled)', ''));
            }
        }
    });
}

function showNetworkStatusMessage(status) {
    const message = status === 'online' ? 
        'Connection restored! All features are now available.' : 
        'You are offline. Some features may be disabled.';
    
    const icon = status === 'online' ? 'success' : 'warning';
    
    // Show a brief toast notification
    Swal.fire({
        title: status === 'online' ? 'Back Online' : 'Offline',
        text: message,
        icon: icon,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });
}

function exportLogs() {
    const params = new URLSearchParams({
        user_id: $('#user_filter').val(),
        status: $('#status_filter').val(),
        device_type: $('#device_filter').val(),
        browser: $('#browser_filter').val(),
        date_from: $('#date_from').val(),
        date_to: $('#date_to').val()
    });

    window.location.href = '{{ route("user-activity-logs.export") }}?' + params.toString();
}

function openCleanupModal() {
    $('#cleanup-modal').removeClass('hidden').show();
}

function closeCleanupModal() {
    $('#cleanup-modal').addClass('hidden').hide();
}

function performCleanup() {
    const days = $('#cleanup-days').val();
    
    Swal.fire({
        title: 'Are you sure?',
        text: `All activity logs older than ${days} days will be permanently deleted.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, delete them!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '{{ route("user-activity-logs.clean-old") }}',
                type: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    days: days
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Success!', response.message, 'success');
                        closeCleanupModal();
                        if (activityTable) {
                            activityTable.ajax.reload();
                        }
                        updateStatistics();
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Failed to cleanup old logs', 'error');
                }
            });
        }
    });
}

function openSettingsModal() {
    $('#settings-modal').removeClass('hidden').show();
}

function closeSettingsModal() {
    $('#settings-modal').addClass('hidden').hide();
}

function loadSettings() {
    $.get('{{ route("user-activity-logs.settings.get") }}', function(response) {
        if (response.success) {
            const settings = response.data;
            $('#cleanup-interval').val(settings.cleanup_interval || 'weekly');
            $('#retention-days').val(settings.retention_days || '90');
            $('#refresh-interval').val(settings.refresh_interval || '30');
            $('#records-per-page').val(settings.records_per_page || '25');
        }
    }).fail(function() {
        console.log('Failed to load settings, using defaults');
    });
}

function saveSettings() {
    const settings = {
        cleanup_interval: $('#cleanup-interval').val(),
        retention_days: $('#retention-days').val(),
        refresh_interval: $('#refresh-interval').val(),
        records_per_page: $('#records-per-page').val()
    };
    
    $.ajax({
        url: '{{ route("user-activity-logs.settings.save") }}',
        type: 'POST',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: settings,
        success: function(response) {
            if (response.success) {
                // Apply settings
                if (autoRefreshInterval) {
                    clearInterval(autoRefreshInterval);
                }
                
                const refreshInterval = parseInt(settings.refresh_interval) * 1000;
                autoRefreshInterval = setInterval(function() {
                    if (currentTab === 'dashboard') {
                        loadDashboardData();
                    } else if (currentTab === 'online-users') {
                        refreshOnlineUsers();
                    }
                    updateStatistics();
                }, refreshInterval);
                
                closeSettingsModal();
                Swal.fire('Success', 'Settings saved successfully', 'success');
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        },
        error: function() {
            Swal.fire('Error', 'Failed to save settings', 'error');
        }
    });
}

// Add CSS for tab styling and offline states
const style = document.createElement('style');
style.textContent = `
    /* Enhanced Tab Styling */
    .modal-overlay {
        z-index: 5000 !important;
    }

    .tab-button {
        position: relative;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .tab-button:hover {
        background-color: rgba(0, 0, 0, 0.02);
    }
    
    .tab-button.active {
        color: #2563eb !important;
        border-color: #2563eb !important;
        font-weight: 700;
    }
    
    .tab-button.active.border-green-500 {
        color: #16a34a !important;
        border-color: #16a34a !important;
    }
    
    .tab-button.active.border-red-500 {
        color: #dc2626 !important;
        border-color: #dc2626 !important;
    }
    
    /* Tab Content Animation */
    .tab-content {
        animation: fadeInUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .tab-content.hidden {
        display: none;
    }
    
    @keyframes fadeInUp {
        from { 
            opacity: 0; 
            transform: translateY(12px); 
        }
        to { 
            opacity: 1; 
            transform: translateY(0); 
        }
    }
    
    /* Enhanced Header Gradient */
    .bg-gradient-to-r.from-blue-600 {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 50%, #4f46e5 100%);
        box-shadow: 0 20px 25px -5px rgba(37, 99, 235, 0.1);
    }
    
    /* Statistics Preview Cards */
    .bg-opacity-10 {
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }
    
    /* Gradient Text for Headers */
    .bg-gradient-to-r.from-blue-600.to-blue-700 {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        transition: all 0.3s ease;
    }
    
    .bg-gradient-to-r.from-blue-600.to-blue-700:hover {
        box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.3);
        transform: translateY(-1px);
    }
    
    /* Offline button styling */
    .logout-btn-offline {
        background-color: #d1d5db !important;
        color: #6b7280 !important;
        cursor: not-allowed !important;
        opacity: 0.6 !important;
        transition: all 0.2s ease-in-out;
    }
    
    .logout-btn-offline:hover {
        background-color: #d1d5db !important;
        color: #6b7280 !important;
        transform: none !important;
    }
    
    /* Main logout button offline styling */
    button[disabled].offline-logout {
        background-color: #f3f4f6 !important;
        color: #9ca3af !important;
        border-color: #e5e7eb !important;
        cursor: not-allowed !important;
        opacity: 0.7 !important;
    }
    
    button[disabled].offline-logout:hover {
        background-color: #f3f4f6 !important;
        color: #9ca3af !important;
    }
    
    /* Offline indicator animation */
    .offline-indicator {
        animation: pulse-red 2s infinite;
    }
    
    @keyframes pulse-red {
        0%, 100% { 
            background-color: #fca5a5; 
            opacity: 1; 
        }
        50% { 
            background-color: #ef4444; 
            opacity: 0.7; 
        }
    }
    
    /* Network status indicator */
    .network-status {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1000;
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    
    .network-status.offline {
        background-color: #fef2f2;
        color: #dc2626;
        border: 1px solid #fecaca;
    }
    
    .network-status.online {
        background-color: #f0fdf4;
        color: #16a34a;
        border: 1px solid #bbf7d0;
    }
    
    /* Enhanced Badge Styling */
    .rounded-full {
        animation: badge-pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
    
    @keyframes badge-pulse {
        0%, 100% {
            transform: scale(1);
            opacity: 1;
        }
        50% {
            transform: scale(1.05);
            opacity: 0.9;
        }
    }
    
    /* Tab Info Card */
    .from-blue-50.to-indigo-50 {
        background: linear-gradient(135deg, #eff6ff 0%, #e0e7ff 100%);
    }
    
    /* Button Hover Effects */
    .border-blue-200 {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .border-blue-200:hover {
        border-color: #3b82f6;
        background-color: #dbeafe;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
    }
    
    .border-orange-200:hover {
        border-color: #fb923c;
        background-color: #fed7aa;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(251, 146, 60, 0.15);
    }
    
    .border-gray-200:hover {
        border-color: #9ca3af;
        background-color: #f3f4f6;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(156, 163, 175, 0.15);
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .hidden.lg\\:grid {
            display: none;
        }
        
        .tab-button {
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
        }
    }

    /* ======== ACTIVITY LOGS TABLE STYLING ======== */
    
    /* Main Table Container */
    #activity-logs-table {
        border-collapse: collapse;
        width: 100%;
    }
    
    /* Table Header Styling */
    #activity-logs-table thead {
        position: sticky;
        top: 0;
        z-index: 100;
        background-color: #1f2937;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    #activity-logs-table thead th {
        padding: 1rem 1.5rem;
        text-align: left;
        font-size: 0.75rem;
        font-weight: 800;
        color: #ffffff;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        background-color: #1f2937;
        border-bottom: 2px solid #374151;
        white-space: nowrap;
        vertical-align: middle;
        user-select: none;
    }
    
    /* Table Body Row Styling */
    #activity-logs-table tbody tr {
        border-bottom: 1px solid #e5e7eb;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        background-color: #ffffff;
    }
    
    #activity-logs-table tbody tr:hover {
        background-color: #f9fafb;
        box-shadow: inset 0 0 0 1px rgba(37, 99, 235, 0.1);
        transform: scale(1.001);
    }
    
    /* Alternate Row Coloring */
    #activity-logs-table tbody tr:nth-child(even) {
        background-color: #fafbfc;
    }
    
    #activity-logs-table tbody tr:nth-child(even):hover {
        background-color: #f3f4f6;
    }
    
    /* Table Cell Styling */
    #activity-logs-table tbody td {
        padding: 1rem 1.5rem;
        font-size: 0.875rem;
        color: #374151;
        vertical-align: middle;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    /* Text Non-Breaking Utility */
    .whitespace-nowrap {
        white-space: nowrap !important;
        overflow: hidden;
        text-overflow: ellipsis;
        display: block;
    }
    
    /* User Column Styling */
    #activity-logs-table tbody td:first-child {
        padding-left: 1.5rem;
        max-width: 220px;
    }
    
    #activity-logs-table tbody td:first-child .whitespace-nowrap {
        white-space: normal;
        overflow: visible;
        text-overflow: clip;
    }
    
    #activity-logs-table tbody td:first-child .text-sm {
        display: block;
        padding: 0.25rem 0;
    }
    
    /* IP Address Badge Styling */
    #activity-logs-table tbody td .font-mono {
        font-family: 'Courier New', Courier, monospace;
        letter-spacing: 0.02em;
        background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
        border: 1px solid #d1d5db;
        padding: 0.5rem 0.75rem;
        border-radius: 0.375rem;
        transition: all 0.2s ease;
    }
    
    #activity-logs-table tbody td .font-mono:hover {
        background: linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%);
        border-color: #9ca3af;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    
    /* Device Info Badge */
    #activity-logs-table tbody td .bg-blue-50 {
        background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        border: 1px solid #bfdbfe;
        padding: 0.5rem 0.75rem;
        border-radius: 0.375rem;
        color: #1e40af;
        font-weight: 500;
        transition: all 0.2s ease;
    }
    
    #activity-logs-table tbody td .bg-blue-50:hover {
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        border-color: #93c5fd;
        box-shadow: 0 2px 8px rgba(59, 130, 246, 0.15);
    }
    
    /* Duration Badge */
    #activity-logs-table tbody td .bg-green-50 {
        background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
        border: 1px solid #bbf7d0;
        padding: 0.5rem 0.75rem;
        border-radius: 0.375rem;
        color: #166534;
        font-weight: 600;
        transition: all 0.2s ease;
    }
    
    #activity-logs-table tbody td .bg-green-50:hover {
        background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
        border-color: #86efac;
        box-shadow: 0 2px 8px rgba(34, 197, 94, 0.15);
    }
    
    /* Status Badge Styling */
    #activity-logs-table tbody .inline-flex {
        display: inline-flex !important;
        align-items: center;
        gap: 0.5rem;
        white-space: nowrap;
        transition: all 0.2s ease;
        padding: 0.375rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
    }
    
    /* Online Status */
    #activity-logs-table tbody .bg-green-100 {
        background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
        color: #15803d;
        border: 1px solid #86efac;
        font-weight: 600;
    }
    
    #activity-logs-table tbody .bg-green-100:hover {
        box-shadow: 0 2px 8px rgba(22, 163, 74, 0.2);
        transform: translateY(-1px);
    }
    
    /* Offline Status */
    #activity-logs-table tbody .bg-red-100 {
        background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        color: #b91c1c;
        border: 1px solid #fca5a5;
        font-weight: 600;
    }
    
    #activity-logs-table tbody .bg-red-100:hover {
        box-shadow: 0 2px 8px rgba(220, 38, 38, 0.2);
        transform: translateY(-1px);
    }
    
    /* Status Indicator Dot Animation */
    #activity-logs-table tbody .fa-circle {
        animation: status-dot-pulse 2s ease-in-out infinite;
    }
    
    @keyframes status-dot-pulse {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.6;
        }
    }
    
    /* Actions Column Styling */
    #activity-logs-table tbody td:last-child {
        text-align: center;
        padding-right: 1.5rem;
    }
    
    /* Action Buttons */
    #activity-logs-table tbody .logout-user-btn,
    #activity-logs-table tbody button {
        white-space: nowrap;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        padding: 0.5rem 0.875rem;
        font-size: 0.75rem;
        font-weight: 500;
        border-radius: 0.375rem;
        cursor: pointer;
    }
    
    #activity-logs-table tbody .logout-user-btn {
        background-color: #f97316;
        color: white;
        border: 1px solid #ea580c;
    }
    
    #activity-logs-table tbody .logout-user-btn:hover {
        background-color: #ea580c;
        box-shadow: 0 4px 12px rgba(249, 115, 22, 0.3);
        transform: translateY(-2px);
    }
    
    #activity-logs-table tbody .logout-user-btn:disabled {
        background-color: #d1d5db;
        color: #9ca3af;
        border-color: #d1d5db;
        cursor: not-allowed;
        opacity: 0.6;
    }
    
    /* DataTables Processing Indicator */
    .dataTables_processing {
        background: rgba(255, 255, 255, 0.95) !important;
        border: 2px solid #3b82f6 !important;
        border-radius: 0.5rem !important;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1) !important;
        color: #1f2937 !important;
        font-weight: 600 !important;
    }
    
    /* DataTables Pagination */
    .dataTables_paginate .paginate_button {
        padding: 0.5rem 0.875rem !important;
        margin-left: 0.25rem !important;
        border-radius: 0.375rem !important;
        border: 1px solid #d1d5db !important;
        cursor: pointer !important;
        transition: all 0.2s ease !important;
        background-color: #f9fafb !important;
        color: #374151 !important;
    }
    
    .dataTables_paginate .paginate_button:hover:not(.disabled) {
        background-color: #3b82f6 !important;
        color: white !important;
        border-color: #3b82f6 !important;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3) !important;
    }
    
    .dataTables_paginate .paginate_button.current {
        background-color: #3b82f6 !important;
        color: white !important;
        border-color: #3b82f6 !important;
        font-weight: 600 !important;
    }
    
    /* DataTables Info Text */
    .dataTables_info {
        color: #6b7280 !important;
        font-size: 0.875rem !important;
        padding: 1rem 0.5rem !important;
    }
    
    /* DataTables Length Change */
    .dataTables_length select {
        padding: 0.5rem 0.75rem !important;
        border: 1px solid #d1d5db !important;
        border-radius: 0.375rem !important;
        background-color: #ffffff !important;
        color: #374151 !important;
        cursor: pointer !important;
        font-size: 0.875rem !important;
        transition: all 0.2s ease !important;
    }
    
    .dataTables_length select:hover {
        border-color: #3b82f6 !important;
        box-shadow: 0 2px 8px rgba(59, 130, 246, 0.1) !important;
    }
    
    .dataTables_length select:focus {
        outline: none !important;
        border-color: #3b82f6 !important;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
    }
    
    /* DataTables Search Box */
    .dataTables_filter input {
        padding: 0.5rem 0.75rem !important;
        margin-left: 0.5rem !important;
        border: 1px solid #d1d5db !important;
        border-radius: 0.375rem !important;
        font-size: 0.875rem !important;
        transition: all 0.2s ease !important;
    }
    
    .dataTables_filter input:hover {
        border-color: #9ca3af !important;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05) !important;
    }
    
    .dataTables_filter input:focus {
        outline: none !important;
        border-color: #3b82f6 !important;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
    }
    
    /* No Data Message */
    #activity-logs-table tbody tr.no-results {
        text-align: center;
        color: #9ca3af;
        padding: 3rem 1rem;
        font-size: 0.875rem;
    }
    
    /* Table Scroll Indicator */
    .overflow-x-auto {
        scroll-behavior: smooth;
    }
    
    .overflow-x-auto::-webkit-scrollbar {
        height: 6px;
    }
    
    .overflow-x-auto::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 10px;
    }
    
    .overflow-x-auto::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
        transition: background 0.2s ease;
    }
    
    .overflow-x-auto::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
    
    /* Bulk Delete Button */
    #bulk-delete-btn {
        animation: slide-in-right 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    @keyframes slide-in-right {
        from {
            opacity: 0;
            transform: translateX(20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    #bulk-delete-btn:hover {
        box-shadow: 0 10px 25px rgba(220, 38, 38, 0.3);
        transform: translateY(-2px);
    }
    
    /* Responsive Table Adjustments */
    @media (max-width: 1024px) {
        #activity-logs-table {
            font-size: 0.8125rem;
        }
        
        #activity-logs-table thead th,
        #activity-logs-table tbody td {
            padding: 0.75rem 1rem;
        }
        
        #activity-logs-table tbody .inline-flex {
            padding: 0.325rem 0.625rem;
            font-size: 0.7rem;
        }
    }
    
    @media (max-width: 768px) {
        #activity-logs-table thead th,
        #activity-logs-table tbody td {
            padding: 0.5rem 0.75rem;
            font-size: 0.75rem;
        }
        
        #activity-logs-table tbody td:first-child {
            max-width: 150px;
        }
        
        #activity-logs-table tbody td .font-mono,
        #activity-logs-table tbody td .bg-blue-50,
        #activity-logs-table tbody td .bg-green-50 {
            padding: 0.375rem 0.5rem;
            font-size: 0.7rem;
        }
        
        #activity-logs-table tbody .logout-user-btn {
            padding: 0.375rem 0.625rem;
            font-size: 0.65rem;
        }
    }
`;
document.head.appendChild(style);
</script>
@endsection