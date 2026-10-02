@php

$PrimaryApplicationCount = DB::connection('sqlsrv')->table('dbo.mother_applications')->count();

$PendingPrimaryApplications = DB::connection('sqlsrv')
    ->table('dbo.mother_applications')
    ->where('application_status', 'Pending')
    ->count();

$DeclinedPrimaryApplications = DB::connection('sqlsrv')
    ->table('dbo.mother_applications')
    ->where('application_status', 'Declined')
    ->count();

$ApprovedPrimaryApplications = DB::connection('sqlsrv')
    ->table('dbo.mother_applications')
    ->where('application_status', 'Approved')
    ->count();

@endphp
<div class="bg-white rounded-md shadow-sm border border-gray-200 p-6 mb-8">
    <h2 class="text-xl font-bold mb-2">Mother  Applications</h2>
    <p class="text-gray-500 text-sm mb-6">Applications from original property owners to initiate sectional
        titling</p>

    <div class="grid grid-cols-4 gap-6 mb-8">
        <div class="rounded-md border border-gray-200 p-6 bg-gray-100">
            <h3 class="text-gray-600 text-sm mb-2">Total Applications</h3>
            <div class="text-3xl font-bold text-gray-800">{{ $PrimaryApplicationCount }}</div>
        </div>

        <div class="rounded-md border border-gray-200 p-6 bg-green-50">
            <h3 class="text-green-700 text-sm mb-2">Approved</h3>
            <div class="text-3xl font-bold text-green-600">{{ $ApprovedPrimaryApplications }}</div>
        </div>

        <div class="rounded-md border border-gray-200 p-6 bg-red-50">
            <h3 class="text-red-700 text-sm mb-2">Declined Applications</h3>
            <div class="text-3xl font-bold text-red-800">{{$DeclinedPrimaryApplications}}</div>
        </div>

        <div class="rounded-md border border-gray-200 p-6 bg-blue-50">
            <h3 class="text-blue-700 text-sm mb-2">Pending</h3>
            <div class="text-3xl font-bold text-blue-500">{{ $PendingPrimaryApplications }}</div>
        </div>
    </div>
</div>
