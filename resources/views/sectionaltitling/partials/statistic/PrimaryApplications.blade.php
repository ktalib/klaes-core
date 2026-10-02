@php

$PrimaryApplicationCount = DB::connection('sqlsrv')
    ->table('dbo.mother_applications')
    ->where(function ($q) {
        $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
    })
    ->count();

$PendingPrimaryApplications = DB::connection('sqlsrv')
    ->table('dbo.mother_applications')
    ->where(function ($q) {
        $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
    })
    ->where('application_status', 'Pending')
    ->count();

$DeclinedPrimaryApplications = DB::connection('sqlsrv')
    ->table('dbo.mother_applications')
    ->where(function ($q) {
        $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
    })
    ->where('application_status', 'Declined')
    ->count();

$ApprovedPrimaryApplications = DB::connection('sqlsrv')
    ->table('dbo.mother_applications')
    ->where(function ($q) {
        $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
    })
    ->where('application_status', 'Approved')
    ->count();

@endphp
<div class="bg-white rounded-md shadow-sm border border-gray-200 p-6 mb-8">
    <h2 class="text-xl font-bold mb-2">Primary Applications</h2>
    <p class="text-gray-500 text-sm mb-6">Applications from original property owners to initiate sectional
        titling</p>

    <div class="grid grid-cols-4 gap-6 mb-8">
        <div class="rounded-md border border-gray-200 p-6 bg-gradient-to-br from-gray-50 to-gray-100 hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-gray-600 text-sm font-medium">Total Applications</h3>
                <div class="p-2 bg-gray-200 rounded-lg">
                    <i data-lucide="file-text" class="w-5 h-5 text-gray-600"></i>
                </div>
            </div>
            <div class="text-3xl font-bold text-gray-800 mb-2">{{ $PrimaryApplicationCount }}</div>
            <div class="flex items-center">
                <span class="badge badge-primary">All Records</span>
            </div>
        </div>

        <div class="rounded-md border border-gray-200 p-6 bg-gradient-to-br from-green-50 to-green-100 hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-green-700 text-sm font-medium">Approved</h3>
                <div class="p-2 bg-green-200 rounded-lg">
                    <i data-lucide="check-circle" class="w-5 h-5 text-green-600"></i>
                </div>
            </div>
            <div class="text-3xl font-bold text-green-600 mb-2">{{ $ApprovedPrimaryApplications }}</div>
            <div class="flex items-center">
                <span class="badge badge-approved">Completed</span>
            </div>
        </div>

        <div class="rounded-md border border-gray-200 p-6 bg-gradient-to-br from-red-50 to-red-100 hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-red-700 text-sm font-medium">Declined Applications</h3>
                <div class="p-2 bg-red-200 rounded-lg">
                    <i data-lucide="x-circle" class="w-5 h-5 text-red-600"></i>
                </div>
            </div>
            <div class="text-3xl font-bold text-red-800 mb-2">{{$DeclinedPrimaryApplications}}</div>
            <div class="flex items-center">
                <span class="badge badge-declined">Declined</span>
            </div>
        </div>

        <div class="rounded-md border border-gray-200 p-6 bg-gradient-to-br from-blue-50 to-blue-100 hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-blue-700 text-sm font-medium">Pending</h3>
                <div class="p-2 bg-blue-200 rounded-lg">
                    <i data-lucide="clock" class="w-5 h-5 text-blue-600"></i>
                </div>
            </div>
            <div class="text-3xl font-bold text-blue-500 mb-2">{{ $PendingPrimaryApplications }}</div>
            <div class="flex items-center">
                <span class="badge badge-pending">In Review</span>
            </div>
        </div>
    </div>
</div>
