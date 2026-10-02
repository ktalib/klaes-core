@extends('layouts.app')

@section('page-title')
    {{ __('VFC Workers Console') }}
@endsection

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle' => 'VFC Workers Console',
        'PageDescription' => 'Manage the global pool of workers for Valuation for Compensation'
    ])

    <div class="p-6 space-y-8 max-w-7xl mx-auto">
        <!-- Header Actions -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-900">Worker Pool</h1>
                <p class="text-slate-500 mt-1">Add or remove staff available for project assignments</p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('valuation-compensations.index') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-white border border-slate-200 text-slate-700 font-semibold shadow-sm hover:bg-slate-50 transition">
                    <i data-lucide="arrow-left" class="h-5 w-5"></i>
                    <span>Back to VFC</span>
                </a>
                <button type="button" onclick="openAddWorkerModal()" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-emerald-600 text-white font-semibold shadow-lg shadow-emerald-100 hover:bg-emerald-700 transition">
                    <i data-lucide="user-plus" class="h-5 w-5"></i>
                    <span>Add Workers to Pool</span>
                </button>
            </div>
        </div>

        <!-- Worker List -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden p-6">
            <div class="overflow-x-auto">
                <table id="worker-pool-table" class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/50">
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100">S/N</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100">Worker ID</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100">Worker Name</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100">Phone</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 text-center">Status</th>
                            <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="worker-pool-body">
                        @forelse($workers as $index => $worker)
                        <tr class="hover:bg-slate-50/50 transition-colors border-b border-slate-50 last:border-0">
                            <td class="px-6 py-5 text-xs font-bold text-slate-400">{{ $index + 1 }}</td>
                            <td class="px-6 py-5">
                                <span class="inline-flex items-center px-3 py-1 rounded-lg bg-emerald-50 text-emerald-700 font-black font-mono text-xs border border-emerald-100">
                                    {{ $worker->vfc_worker_id }}
                                </span>
                            </td>
                            <td class="px-6 py-5">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-xs">
                                        {{ substr($worker->user->first_name, 0, 1) }}{{ substr($worker->user->last_name, 0, 1) }}
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-sm font-bold text-slate-700">{{ $worker->user->first_name }} {{ $worker->user->last_name }}</span>
                                        <span class="text-[10px] text-slate-400 font-medium uppercase tracking-tighter">{{ $worker->user->staff_id ?? '' }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-5">
                                <span class="text-xs font-semibold text-slate-500 uppercase">{{ $worker->user->phone_number ?? 'No Phone' }}</span>
                            </td>
                            <td class="px-6 py-5 text-center">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold {{ $worker->is_active ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $worker->is_active ? 'bg-green-500' : 'bg-slate-400' }}"></span>
                                    {{ $worker->is_active ? 'Active' : 'Inactive' }}
                                </span>
                                @if(!$worker->is_active && $worker->deactivation_reason)
                                <div class="text-[10px] text-slate-400 font-medium mt-1">{{ $worker->deactivation_reason }}</div>
                                @endif
                                <div class="text-[10px] text-slate-400 font-semibold mt-1 uppercase tracking-tight">
                                    {{ $worker->assignments_count }} project{{ $worker->assignments_count == 1 ? '' : 's' }}
                                </div>
                            </td>
                            <td class="px-6 py-5 text-right">
                                <button type="button"
                                    data-worker="{{ json_encode([
                                        'id' => $worker->id,
                                        'worker_id' => $worker->vfc_worker_id,
                                        'name' => trim(($worker->user->first_name ?? '') . ' ' . ($worker->user->last_name ?? '')),
                                        'is_active' => (bool) $worker->is_active,
                                        'reason' => $worker->deactivation_reason,
                                    ]) }}"
                                    onclick="openEditWorkerModal(JSON.parse(this.getAttribute('data-worker')))"
                                    class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-xl transition" title="Edit worker status">
                                    <i data-lucide="edit-3" class="h-5 w-5"></i>
                                </button>
                                <button type="button" onclick="removeWorker({{ $worker->id }}, '{{ $worker->user->first_name }} {{ $worker->user->last_name }}', {{ $worker->assignments_count }})" 
                                    class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition" title="Remove from pool">
                                    <i data-lucide="trash-2" class="h-5 w-5"></i>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-6 py-20 text-center">
                                <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i data-lucide="users" class="h-8 w-8 text-slate-300"></i>
                                </div>
                                <h3 class="text-lg font-bold text-slate-900">No Workers in Pool</h3>
                                <p class="text-sm text-slate-500 mt-1">Add staff members to make them available for VFC project assignments.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Worker Modal -->
<div id="add-worker-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" onclick="closeAddWorkerModal()"></div>
    <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all scale-95 opacity-0 duration-300" id="modal-container">
        <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="text-xl font-bold text-slate-900">Add Workers to Pool</h3>
                <p class="text-xs text-slate-500 mt-0.5 uppercase tracking-widest font-semibold">New Worker Assignment</p>
            </div>
            <button type="button" onclick="closeAddWorkerModal()" class="text-slate-400 hover:text-slate-600 p-2 hover:bg-slate-50 rounded-xl transition">
                <i data-lucide="x" class="h-6 w-6"></i>
            </button>
        </div>
        <form id="add-worker-form" class="px-8 py-8 space-y-6">
            @csrf
            
            <div class="p-4 bg-emerald-50/50 rounded-2xl border border-emerald-100/50 flex items-center justify-between">
                <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-widest">Assigned Worker ID</span>
                <span id="next_worker_id_preview" class="px-3 py-1 bg-white rounded-lg border border-emerald-100 text-sm font-black font-mono text-emerald-700 shadow-sm">Loading...</span>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Select Staff Member <span class="text-red-500">*</span></label>
                <select name="user_id" id="worker_user_id" required class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-emerald-500 focus:bg-white transition text-sm font-medium">
                    <option value="">Choose Staff...</option>
                    @foreach($availableUsers as $user)
                    <option value="{{ $user->id }}">{{ $user->first_name }} {{ $user->last_name }} @if($user->staff_id) ({{ $user->staff_id }}) @endif</option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeAddWorkerModal()" class="flex-1 px-6 py-3 rounded-xl border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 transition">
                    Cancel
                </button>
                <button type="submit" class="flex-1 px-6 py-3 rounded-xl bg-emerald-600 text-white font-bold shadow-lg shadow-emerald-100 hover:bg-emerald-700 transition">
                    Add to Pool
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Worker Modal -->
<div id="edit-worker-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" onclick="closeEditWorkerModal()"></div>
    <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all scale-95 opacity-0 duration-300" id="edit-modal-container">
        <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="text-xl font-bold text-slate-900">Edit Officer</h3>
                <p class="text-xs text-slate-500 mt-0.5 uppercase tracking-widest font-semibold" id="edit_worker_subtitle">Worker</p>
            </div>
            <button type="button" onclick="closeEditWorkerModal()" class="text-slate-400 hover:text-slate-600 p-2 hover:bg-slate-50 rounded-xl transition">
                <i data-lucide="x" class="h-6 w-6"></i>
            </button>
        </div>
        <form id="edit-worker-form" class="px-8 py-8 space-y-6">
            @csrf
            <input type="hidden" id="edit_worker_row_id">

            <div class="p-4 bg-blue-50/50 rounded-2xl border border-blue-100/50 flex items-center justify-between">
                <span class="text-[10px] font-bold text-blue-600 uppercase tracking-widest">Worker ID</span>
                <span id="edit_worker_id_preview" class="px-3 py-1 bg-white rounded-lg border border-blue-100 text-sm font-black font-mono text-blue-700 shadow-sm">-</span>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Availability <span class="text-red-500">*</span></label>
                <select id="edit_worker_is_active" required onchange="toggleDeactivationReason()" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium">
                    <option value="1">Active &mdash; available for new project assignments</option>
                    <option value="0">Inactive &mdash; retired / re-scheduled</option>
                </select>
            </div>

            <div id="deactivation_reason_wrapper" class="hidden">
                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Reason</label>
                <select id="edit_reason_preset" onchange="applyReasonPreset(this.value)" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium mb-3">
                    <option value="">Choose a reason...</option>
                    <option value="Retired">Retired</option>
                    <option value="Change of schedule">Change of schedule</option>
                    <option value="Transferred">Transferred</option>
                    <option value="On leave">On leave</option>
                </select>
                <input type="text" id="edit_deactivation_reason" maxlength="255" placeholder="Or type a reason" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium">
                <p class="text-[11px] text-slate-400 mt-2">The officer keeps their worker ID and everything they already captured. They simply stop appearing when new projects are staffed.</p>
            </div>

            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeEditWorkerModal()" class="flex-1 px-6 py-3 rounded-xl border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 transition">
                    Cancel
                </button>
                <button type="submit" class="flex-1 px-6 py-3 rounded-xl bg-blue-600 text-white font-bold shadow-lg shadow-blue-100 hover:bg-blue-700 transition">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        $('#worker-pool-table').DataTable({
            responsive: true,
            pageLength: 10,
            dom: '<"flex flex-col md:flex-row justify-between gap-4 mb-4"fB>rt<"flex flex-col md:flex-row justify-between items-center gap-4 mt-4"ip>',
            buttons: [
                'copy', 'csv', 'excel', 'pdf', 'print'
            ],
            language: {
                searchPlaceholder: "Search workers...",
                search: ""
            }
        });
        
        // Style search input
        $('.dataTables_filter input').addClass('px-4 py-2 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:ring-0 transition text-sm');
    });

    function openAddWorkerModal() {
        $('#next_worker_id_preview').text('Loading...');
        $.get("{{ route('valuation-compensations.workers.next-id') }}", function(data) {
            $('#next_worker_id_preview').text(data.next_id);
        });

        const $modal = $('#add-worker-modal');
        const $container = $('#modal-container');
        $modal.removeClass('hidden').addClass('flex');
        setTimeout(() => {
            $container.removeClass('scale-95 opacity-0').addClass('scale-100 opacity-100');
        }, 10);
    }

    function closeAddWorkerModal() {
        const $modal = $('#add-worker-modal');
        const $container = $('#modal-container');
        $container.addClass('scale-95 opacity-0').removeClass('scale-100 opacity-100');
        setTimeout(() => {
            $modal.addClass('hidden').removeClass('flex');
        }, 300);
    }

    function openEditWorkerModal(worker) {
        $('#edit_worker_row_id').val(worker.id);
        $('#edit_worker_id_preview').text(worker.worker_id);
        $('#edit_worker_subtitle').text(worker.name || 'Worker');
        $('#edit_worker_is_active').val(worker.is_active ? '1' : '0');
        $('#edit_deactivation_reason').val(worker.reason || '');
        $('#edit_reason_preset').val('');
        toggleDeactivationReason();

        const $modal = $('#edit-worker-modal');
        const $container = $('#edit-modal-container');
        $modal.removeClass('hidden').addClass('flex');
        setTimeout(() => {
            $container.removeClass('scale-95 opacity-0').addClass('scale-100 opacity-100');
        }, 10);
    }

    function closeEditWorkerModal() {
        const $modal = $('#edit-worker-modal');
        const $container = $('#edit-modal-container');
        $container.addClass('scale-95 opacity-0').removeClass('scale-100 opacity-100');
        setTimeout(() => {
            $modal.addClass('hidden').removeClass('flex');
        }, 300);
    }

    function toggleDeactivationReason() {
        const isActive = $('#edit_worker_is_active').val() === '1';
        $('#deactivation_reason_wrapper').toggleClass('hidden', isActive);
    }

    function applyReasonPreset(value) {
        if (value) {
            $('#edit_deactivation_reason').val(value);
        }
    }

    $('#edit-worker-form').on('submit', function(e) {
        e.preventDefault();
        const id = $('#edit_worker_row_id').val();

        Swal.fire({
            title: 'Saving...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.ajax({
            url: `{{ url('valuation-compensations/workers') }}/${id}`,
            type: 'PUT',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            data: {
                is_active: $('#edit_worker_is_active').val(),
                deactivation_reason: $('#edit_deactivation_reason').val()
            },
            success: function(response) {
                if (response.success) {
                    Swal.fire('Saved!', response.message, 'success').then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire('Error!', response.message || 'Could not update worker.', 'error');
                }
            },
            error: function(xhr) {
                let msg = 'Could not update worker.';
                if (xhr.status === 422) {
                    msg = Object.values(xhr.responseJSON.errors).flat().join('<br>');
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                Swal.fire('Error!', msg, 'error');
            }
        });
    });

    /**
     * Removing an officer who still holds project assignments loses the pool
     * record behind their worker code, so that case gets a stronger warning and
     * an explicit force flag; deactivating is offered as the safer route.
     */
    function removeWorker(id, name, assignmentsCount) {
        const assigned = parseInt(assignmentsCount || 0, 10);

        const config = assigned > 0 ? {
            title: 'Remove an assigned officer?',
            html: `<div style="text-align:left;font-size:13px;line-height:1.6">
                     <p><strong>${name}</strong> is still assigned to <strong>${assigned}</strong> project(s).</p>
                     <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:12px;padding:12px;margin-top:10px;color:#7f1d1d">
                        Removing them from the pool deletes their pool record. Their captured
                        valuations are kept, but the officer will no longer be listed here.
                     </div>
                     <p style="margin-top:10px;color:#64748b">For a retirement or a change of schedule,
                        use <strong>Edit</strong> and set them to Inactive instead &mdash; that keeps the record.</p>
                   </div>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Remove anyway',
            reverseButtons: true
        } : {
            title: 'Remove Worker?',
            text: `Are you sure you want to remove ${name} from the VFC pool? This will not delete their user account.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, remove them'
        };

        Swal.fire(config).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `{{ url('valuation-compensations/workers') }}/${id}`,
                    type: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    data: { force: assigned > 0 ? 1 : 0 },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Removed!', response.message, 'success').then(() => {
                                window.location.reload();
                            });
                        } else {
                            Swal.fire('Error!', response.message || 'Could not remove worker.', 'error');
                        }
                    },
                    error: function(xhr) {
                        const m = (xhr.responseJSON && xhr.responseJSON.message) || 'Could not remove worker.';
                        Swal.fire('Error!', m, 'error');
                    }
                });
            }
        });
    }

    $('#add-worker-form').on('submit', function(e) {
        e.preventDefault();
        const data = $(this).serialize();
        
        Swal.fire({
            title: 'Adding staff...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.ajax({
            url: `{{ route('valuation-compensations.workers.store') }}`,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    Swal.fire('Added!', response.message, 'success').then(() => {
                        window.location.reload();
                    });
                }
            },
            error: function(xhr) {
                let msg = 'Something went wrong.';
                if (xhr.status === 422) {
                    msg = Object.values(xhr.responseJSON.errors).flat().join('<br>');
                }
                Swal.fire('Error!', msg, 'error');
            }
        });
    });
</script>
@endpush
