@extends('layouts.app')

@section('page-title', 'Folder Watcher Dashboard')

@section('content')
<div class="flex-1 overflow-auto bg-gray-50 min-h-screen">
    @include('admin.header')

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <p class="text-sm uppercase tracking-wide text-blue-600 font-semibold">System Admin</p>
            <h1 class="text-3xl font-bold text-gray-900 mt-1">Folder Watcher Control Center</h1>
            <p class="text-gray-600 mt-2">
                The live dashboard below is rendered directly from the Python watcher service running on
                <code class="bg-slate-100 px-2 py-0.5 rounded text-sm">http://127.0.0.1:5001/</code>.
                Use it to start or stop the watcher, change configuration, or inspect statistics without leaving the admin panel.
            </p>
            <p class="text-gray-500 text-sm mt-3">
                If the embedded view does not load (for example, if the Python service is stopped), you can
                <a href="http://127.0.0.1:5001/" target="_blank" class="text-blue-600 underline">open the dashboard in a new tab</a>.
            </p>
        </div>

        <div class="bg-white rounded-2xl shadow border border-slate-200 overflow-hidden">
            <embed
                src="http://127.0.0.1:5001/"
                type="text/html"
                class="w-full"
                style="min-height: calc(100vh - 260px);"
            >
        </div>
    </div>
</div>
@endsection
