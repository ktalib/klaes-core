@extends('layouts.app')

@section('title', 'Batch Management System')

@section('content')
<div class="container-fluid px-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h3 mb-0">
                    <i data-lucide="package" class="me-2"></i>
                    Batch Management System
                </h1>
                <div class="d-flex gap-2">
                    <button id="refresh-stats" class="btn btn-outline-secondary">
                        <i data-lucide="refresh-cw" class="me-1" style="width: 16px; height: 16px;"></i>
                        Refresh
                    </button>
                    <button id="auto-assign-btn" class="btn btn-warning">
                        <i data-lucide="zap" class="me-1" style="width: 16px; height: 16px;"></i>
                        Auto-Assign Shelves
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                                <i data-lucide="package" class="text-primary" style="width: 24px; height: 24px;"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="small text-muted">Total Batches</div>
                            <div class="h4 mb-0" id="total-batches">-</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-success bg-opacity-10 p-3">
                                <i data-lucide="check-circle" class="text-success" style="width: 24px; height: 24px;"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="small text-muted">Available Batches</div>
                            <div class="h4 mb-0" id="available-batches">-</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                                <i data-lucide="archive" class="text-warning" style="width: 24px; height: 24px;"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="small text-muted">Total Shelves</div>
                            <div class="h4 mb-0" id="total-shelves">-</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-info bg-opacity-10 p-3">
                                <i data-lucide="percent" class="text-info" style="width: 24px; height: 24px;"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="small text-muted">Usage</div>
                            <div class="h4 mb-0" id="usage-percentage">-</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h5 class="card-title mb-0">
                        <i data-lucide="plus-circle" class="me-2" style="width: 20px; height: 20px;"></i>
                        Generate New Batches
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Generate new batches of 100 shelves each.</p>
                    <div class="row">
                        <div class="col-8">
                            <div class="input-group">
                                <input type="number" id="batch-count" class="form-control" value="100" min="1" max="1000">
                                <span class="input-group-text">batches</span>
                            </div>
                        </div>
                        <div class="col-4">
                            <button id="generate-batches-btn" class="btn btn-primary w-100">
                                <i data-lucide="plus" class="me-1" style="width: 16px; height: 16px;"></i>
                                Generate
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h5 class="card-title mb-0">
                        <i data-lucide="shuffle" class="me-2" style="width: 20px; height: 20px;"></i>
                        Gap-Filling Assignment
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Auto-assign shelves using gap-filling logic (1 shelf per 100 files).</p>
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small text-muted">Unassigned files</div>
                            <div class="h6 mb-0" id="unassigned-files">Loading...</div>
                        </div>
                        <button id="auto-assign-shelves-btn" class="btn btn-warning">
                            <i data-lucide="zap" class="me-1" style="width: 16px; height: 16px;"></i>
                            Auto-Assign
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm border-info">
                <div class="card-header bg-white border-0">
                    <h5 class="card-title mb-0">
                        <i data-lucide="database" class="me-2" style="width: 20px; height: 20px;"></i>
                        Data Cleanup
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Clean up shelf_location fields and sync batch statistics.</p>
                    <div class="d-flex gap-2">
                        <button id="dry-run-cleanup-btn" class="btn btn-outline-info flex-1">
                            <i data-lucide="search" class="me-1" style="width: 16px; height: 16px;"></i>
                            Dry Run
                        </button>
                        <button id="run-cleanup-btn" class="btn btn-info flex-1">
                            <i data-lucide="database" class="me-1" style="width: 16px; height: 16px;"></i>
                            Clean Up
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Batches Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0">
            <h5 class="card-title mb-0">
                <i data-lucide="list" class="me-2" style="width: 20px; height: 20px;"></i>
                Recent Batches
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Batch #</th>
                            <th>Total Shelves</th>
                            <th>Used Shelves</th>
                            <th>Available</th>
                            <th>Usage %</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody id="recent-batches-tbody">
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <div class="spinner-border text-muted" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <div class="mt-2 text-muted">Loading batch data...</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Include SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
/**
 * Batch Management System JavaScript
 */
class BatchManager {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadDashboardData();
        this.loadUnassignedFilesCount();
    }

    bindEvents() {
        // Refresh stats
        document.getElementById('refresh-stats').addEventListener('click', () => {
            this.loadDashboardData();
            this.loadUnassignedFilesCount();
        });

        // Generate batches
        document.getElementById('generate-batches-btn').addEventListener('click', () => {
            this.generateBatches();
        });

        // Auto-assign shelves (both buttons)
        document.getElementById('auto-assign-btn').addEventListener('click', () => {
            this.autoAssignShelves();
        });
        document.getElementById('auto-assign-shelves-btn').addEventListener('click', () => {
            this.autoAssignShelves();
        });

        // Cleanup functions
        document.getElementById('dry-run-cleanup-btn').addEventListener('click', () => {
            this.runCleanup(true);
        });
        document.getElementById('run-cleanup-btn').addEventListener('click', () => {
            this.runCleanup(false);
        });
    }

    async loadDashboardData() {
        try {
            const response = await fetch('/fileindexing/batch-management-data');
            const data = await response.json();

            if (data.success) {
                this.updateStats(data.stats);
                this.updateRecentBatches(data.recent_batches);
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('Failed to load dashboard data:', error);
            Swal.fire({
                title: 'Error',
                text: 'Failed to load dashboard data: ' + error.message,
                icon: 'error'
            });
        }
    }

    updateStats(stats) {
        document.getElementById('total-batches').textContent = stats.total_batches.toLocaleString();
        document.getElementById('available-batches').textContent = stats.available_batches.toLocaleString();
        document.getElementById('total-shelves').textContent = stats.total_shelves.toLocaleString();
        document.getElementById('usage-percentage').textContent = stats.usage_percentage + '%';
    }

    updateRecentBatches(batches) {
        const tbody = document.getElementById('recent-batches-tbody');
        
        if (batches.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4 text-muted">
                        No batches found. Generate some batches to get started.
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = batches.map(batch => {
            const usagePercent = batch.shelf_count > 0 
                ? Math.round((batch.used_shelves / batch.shelf_count) * 100) 
                : 0;
            
            const statusClass = batch.is_full ? 'danger' : (batch.is_active ? 'success' : 'secondary');
            const statusText = batch.is_full ? 'Full' : (batch.is_active ? 'Active' : 'Inactive');
            
            const available = batch.shelf_count - batch.used_shelves;
            
            return `
                <tr>
                    <td><strong>${batch.batch_number}</strong></td>
                    <td>${batch.shelf_count.toLocaleString()}</td>
                    <td>${batch.used_shelves.toLocaleString()}</td>
                    <td>${available.toLocaleString()}</td>
                    <td>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar" style="width: ${usagePercent}%"></div>
                        </div>
                        <small>${usagePercent}%</small>
                    </td>
                    <td>
                        <span class="badge bg-${statusClass}">${statusText}</span>
                    </td>
                    <td>
                        <small class="text-muted">${new Date(batch.created_at).toLocaleDateString()}</small>
                    </td>
                </tr>
            `;
        }).join('');
    }

    async loadUnassignedFilesCount() {
        try {
            // This is a simple query - you might want to add a dedicated endpoint
            const response = await fetch('/api/fileindexing/unassigned-count');
            // For now, we'll show a placeholder
            document.getElementById('unassigned-files').textContent = 'Check manually';
        } catch (error) {
            document.getElementById('unassigned-files').textContent = 'Error loading';
        }
    }

    async generateBatches() {
        const countInput = document.getElementById('batch-count');
        const count = parseInt(countInput.value);
        
        if (count < 1 || count > 1000) {
            Swal.fire({
                title: 'Invalid Count',
                text: 'Please enter a count between 1 and 1000.',
                icon: 'error'
            });
            return;
        }

        const result = await Swal.fire({
            title: 'Generate Batches',
            text: `Generate ${count} new batches? Each batch will contain 100 shelf spaces.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Generate',
            cancelButtonText: 'Cancel'
        });

        if (!result.isConfirmed) return;

        const generateBtn = document.getElementById('generate-batches-btn');
        generateBtn.disabled = true;
        generateBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generating...';

        try {
            const response = await fetch('/fileindexing/generate-batches', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ count })
            });

            const data = await response.json();

            if (data.success) {
                Swal.fire({
                    title: 'Success!',
                    text: data.message,
                    icon: 'success'
                });
                this.loadDashboardData(); // Refresh data
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            Swal.fire({
                title: 'Error',
                text: 'Failed to generate batches: ' + error.message,
                icon: 'error'
            });
        } finally {
            generateBtn.disabled = false;
            generateBtn.innerHTML = '<i data-lucide="plus" class="me-1" style="width: 16px; height: 16px;"></i>Generate';
            lucide.createIcons(); // Re-initialize Lucide icons
        }
    }

    async autoAssignShelves() {
        const result = await Swal.fire({
            title: 'Auto-Assign Shelves',
            text: 'This will automatically assign shelves to unassigned files using gap-filling logic (1 shelf per 100 files). Continue?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Auto-Assign',
            cancelButtonText: 'Cancel'
        });

        if (!result.isConfirmed) return;

        // Show progress
        Swal.fire({
            title: 'Processing...',
            text: 'Auto-assigning shelves to files...',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        try {
            const response = await fetch('/fileindexing/auto-assign-shelves', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            const data = await response.json();

            if (data.success) {
                Swal.fire({
                    title: 'Success!',
                    text: data.message,
                    icon: 'success'
                });
                this.loadDashboardData(); // Refresh data
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            Swal.fire({
                title: 'Error',
                text: 'Failed to auto-assign shelves: ' + error.message,
                icon: 'error'
            });
        }
    }

    async runCleanup(isDryRun) {
        const title = isDryRun ? 'Run Cleanup Dry-Run' : 'Run Data Cleanup';
        const text = isDryRun 
            ? 'This will analyze the data and show what changes would be made without actually making them.'
            : 'This will clean up shelf_location fields with full_label values and update batch statistics. This operation modifies your database.';
        
        const result = await Swal.fire({
            title: title,
            text: text,
            icon: isDryRun ? 'info' : 'warning',
            showCancelButton: true,
            confirmButtonText: isDryRun ? 'Run Dry-Run' : 'Clean Up Data',
            cancelButtonText: 'Cancel',
            confirmButtonColor: isDryRun ? '#0ea5e9' : '#dc3545'
        });

        if (!result.isConfirmed) return;

        // Show progress
        Swal.fire({
            title: 'Processing...',
            text: isDryRun ? 'Analyzing data...' : 'Cleaning up data...',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        try {
            const response = await fetch('/fileindexing/run-shelf-cleanup', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ 
                    dry_run: isDryRun 
                })
            });

            const data = await response.json();

            if (data.success) {
                // Show detailed results
                const outputLines = data.output.split('\n');
                const relevantLines = outputLines.filter(line => 
                    line.trim() && 
                    !line.includes('INFO') && 
                    !line.includes('Running') &&
                    !line.includes('Timestamp')
                ).slice(0, 20); // Show first 20 relevant lines

                await Swal.fire({
                    title: isDryRun ? 'Dry-Run Results' : 'Cleanup Complete!',
                    html: `
                        <div class="text-start">
                            <p class="mb-3">${data.message}</p>
                            <div class="bg-light p-3 rounded" style="max-height: 300px; overflow-y: auto;">
                                <small class="text-muted">
                                    ${relevantLines.map(line => 
                                        line.replace(/[✅❌⚠️]/g, '').trim()
                                    ).join('<br>')}
                                </small>
                            </div>
                        </div>
                    `,
                    icon: 'success',
                    confirmButtonText: 'OK',
                    width: '600px'
                });

                // Refresh dashboard if actual cleanup was performed
                if (!isDryRun) {
                    this.loadDashboardData();
                }
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            Swal.fire({
                title: 'Error',
                text: 'Failed to run cleanup: ' + error.message,
                icon: 'error'
            });
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    new BatchManager();
});
</script>

<style>
.card {
    transition: all 0.2s ease;
}

.card:hover {
    transform: translateY(-2px);
}

.progress {
    background-color: #f8f9fa;
}

.table th {
    border-top: none;
    font-weight: 600;
    color: #6c757d;
    font-size: 0.875rem;
}

.spinner-border-sm {
    width: 1rem;
    height: 1rem;
}
</style>
@endsection