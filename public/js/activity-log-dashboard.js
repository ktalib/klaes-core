/**
 * Activity Log Dashboard JavaScript
 * 
 * Handles:
 * - Real-time polling for statistics updates
 * - Heartbeat keep-alive pings
 * - Chart rendering and updates
 * - User list management
 * - Statistics refresh
 * 
 * @version 1.0
 */

class ActivityLogDashboard {
    constructor(options = {}) {
        this.options = {
            pollInterval: 60000,        // Poll every 60 seconds
            heartbeatInterval: 300000,  // Heartbeat every 5 minutes
            chartsEnabled: true,
            debug: false,
            ...options
        };

        this.charts = {};
        this.isInitialized = false;
        this.lastUpdate = null;

        this.init();
    }

    /**
     * Initialize dashboard
     */
    init() {
        if (this.isInitialized) return;

        this.log('Initializing Activity Log Dashboard');

        // Set up event listeners
        this.setupEventListeners();

        // Start polling
        this.startPolling();

        // Start heartbeat
        this.startHeartbeat();

        this.isInitialized = true;
        this.log('Dashboard initialized');
    }

    /**
     * Set up event listeners
     */
    setupEventListeners() {
        // Listen for page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.log('Page hidden - pausing updates');
                this.pausePolling();
            } else {
                this.log('Page visible - resuming updates');
                this.startPolling();
            }
        });

        // Listen for network changes
        window.addEventListener('online', () => {
            this.log('Network online - resuming polling');
            this.startPolling();
        });

        window.addEventListener('offline', () => {
            this.log('Network offline - pausing polling');
            this.pausePolling();
        });
    }

    /**
     * Start polling for updates
     */
    startPolling() {
        if (this.pollTimer) clearInterval(this.pollTimer);

        this.pollTimer = setInterval(() => {
            this.updateStats();
        }, this.options.pollInterval);

        // Initial update
        this.updateStats();
    }

    /**
     * Pause polling
     */
    pausePolling() {
        if (this.pollTimer) {
            clearInterval(this.pollTimer);
            this.pollTimer = null;
        }
    }

    /**
     * Start heartbeat pings
     */
    startHeartbeat() {
        if (this.heartbeatTimer) clearInterval(this.heartbeatTimer);

        this.heartbeatTimer = setInterval(() => {
            this.sendHeartbeat();
        }, this.options.heartbeatInterval);

        // Initial heartbeat
        this.sendHeartbeat();
    }

    /**
     * Send heartbeat ping to server
     */
    sendHeartbeat() {
        const fileNumber = this.getFileNumberFromPage();

        fetch('/api/activity/heartbeat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': this.getCsrfToken(),
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                file_number: fileNumber
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.log('Heartbeat sent successfully');
            } else {
                this.log('Heartbeat failed: ' + data.message, 'warn');
            }
        })
        .catch(error => {
            this.log('Heartbeat error: ' + error.message, 'error');
        });
    }

    /**
     * Update statistics from server
     */
    updateStats() {
        const days = this.getDaysFromUrl();

        Promise.all([
            this.fetchOnlineCount(),
            this.fetchDashboardStats(days),
            this.fetchOnlineUsers()
        ])
        .then(() => {
            this.lastUpdate = new Date();
            this.log('Stats updated at ' + this.lastUpdate.toLocaleTimeString());
        })
        .catch(error => {
            this.log('Error updating stats: ' + error.message, 'error');
        });
    }

    /**
     * Fetch online user count
     */
    fetchOnlineCount() {
        return fetch('/api/activity/online-count', {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': this.getCsrfToken()
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.updateOnlineCountDisplay(data.data.online_count);
            }
        })
        .catch(error => this.log('Error fetching online count: ' + error.message, 'error'));
    }

    /**
     * Fetch dashboard statistics
     */
    fetchDashboardStats(days = 7) {
        return fetch('/api/activity/dashboard-stats?days=' + days, {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': this.getCsrfToken()
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.updateStatsDisplay(data.data);
            }
        })
        .catch(error => this.log('Error fetching stats: ' + error.message, 'error'));
    }

    /**
     * Fetch online users list
     */
    fetchOnlineUsers() {
        return fetch('/api/activity/online-users', {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': this.getCsrfToken()
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.updateOnlineUsersList(data.data.users);
            }
        })
        .catch(error => this.log('Error fetching online users: ' + error.message, 'error'));
    }

    /**
     * Update online count display
     */
    updateOnlineCountDisplay(count) {
        const element = document.getElementById('online-count');
        if (element) {
            // Add animation
            element.style.opacity = '0.5';
            element.textContent = count;

            setTimeout(() => {
                element.style.opacity = '1';
            }, 100);
        }
    }

    /**
     * Update statistics display
     */
    updateStatsDisplay(stats) {
        this.log('Updating stats display', 'debug');
        // Stats are typically updated via server-side rendering
        // This can be extended for real-time updates if needed
    }

    /**
     * Update online users list
     */
    updateOnlineUsersList(users) {
        this.log('Updating online users list: ' + users.length + ' users', 'debug');
        // This can be extended to update a live users widget if present
    }

    /**
     * Render or update charts
     */
    renderCharts(data) {
        if (!this.options.chartsEnabled) return;
        if (!window.Chart) {
            this.log('Chart.js not loaded', 'warn');
            return;
        }

        this.log('Rendering charts');

        // Timeline chart
        this.renderTimelineChart(data.timeline);

        // Browser chart
        this.renderBrowserChart(data.browserStats);

        // Platform chart
        this.renderPlatformChart(data.platformStats);

        // Device chart
        this.renderDeviceChart(data.deviceStats);
    }

    /**
     * Render timeline chart
     */
    renderTimelineChart(data) {
        const canvas = document.getElementById('timeline-chart');
        if (!canvas) return;

        if (this.charts.timeline) {
            this.charts.timeline.data.labels = data.labels;
            this.charts.timeline.data.datasets[0].data = data.data;
            this.charts.timeline.update();
        } else {
            this.charts.timeline = new Chart(canvas, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Sessions',
                        data: data.data,
                        borderColor: '#3B82F6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointRadius: 5,
                        pointBackgroundColor: '#3B82F6',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1 }
                        }
                    }
                }
            });
        }
    }

    /**
     * Render browser distribution chart
     */
    renderBrowserChart(data) {
        const canvas = document.getElementById('browser-chart');
        if (!canvas) return;

        const labels = Object.keys(data);
        const values = Object.values(data);

        if (this.charts.browser) {
            this.charts.browser.data.labels = labels;
            this.charts.browser.data.datasets[0].data = values;
            this.charts.browser.update();
        } else {
            this.charts.browser = new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: [
                            '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6',
                            '#EC4899', '#14B8A6', '#F97316', '#06B6D4', '#6366F1',
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { padding: 15, font: { size: 12 } }
                        }
                    }
                }
            });
        }
    }

    /**
     * Render platform chart (placeholder)
     */
    renderPlatformChart(data) {
        // Similar implementation for platform chart
    }

    /**
     * Render device chart (placeholder)
     */
    renderDeviceChart(data) {
        // Similar implementation for device chart
    }

    /**
     * Get CSRF token from page
     */
    getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    /**
     * Get file number from page (if present)
     */
    getFileNumberFromPage() {
        const input = document.querySelector('input[name="file_number"], input[name="fileno"]');
        return input?.value || null;
    }

    /**
     * Get days parameter from URL
     */
    getDaysFromUrl() {
        const params = new URLSearchParams(window.location.search);
        return parseInt(params.get('days')) || 7;
    }

    /**
     * Logging utility
     */
    log(message, level = 'log') {
        if (!this.options.debug && level === 'debug') return;

        const timestamp = new Date().toLocaleTimeString();
        console.log(`[${timestamp}] [ActivityDashboard] [${level.toUpperCase()}] ${message}`);
    }

    /**
     * Destroy dashboard and clean up
     */
    destroy() {
        this.pausePolling();
        if (this.heartbeatTimer) clearInterval(this.heartbeatTimer);
        this.isInitialized = false;
        this.log('Dashboard destroyed');
    }
}

/**
 * Initialize dashboard on page load
 */
document.addEventListener('DOMContentLoaded', function() {
    // Check if we're on a page that should have the dashboard
    if (document.getElementById('timeline-chart') || 
        document.getElementById('browser-chart') ||
        document.getElementById('online-count')) {
        
        window.activityDashboard = new ActivityLogDashboard({
            pollInterval: 60000,        // Update stats every 60 seconds
            heartbeatInterval: 300000,  // Heartbeat every 5 minutes
            chartsEnabled: true,
            debug: false                // Set to true for debugging
        });
    }
});

/**
 * Public API for global access
 */
window.ActivityLogDashboard = ActivityLogDashboard;
