@extends('layouts.app')

@section('page-title', 'Virtual File/Folder System (Folio)')

@section('content')
<div class="flex-1 overflow-auto">
    @include('admin.header')

    <div class="p-6">
        <div class="max-w-xl mx-auto bg-white border border-gray-200 rounded-lg p-6 text-center">
            <i data-lucide="folder-x" class="h-10 w-10 text-gray-400 mx-auto"></i>
            <h2 class="text-lg font-semibold text-gray-900 mt-3">No folio for {{ $number }}</h2>
            <p class="text-sm text-gray-600 mt-2">
                This file has not been indexed yet, so it has no virtual folder. It opens here once it is indexed.
            </p>
            <button type="button" onclick="history.length > 1 ? history.back() : window.close()"
                    class="mt-4 text-sm font-semibold border border-gray-300 text-gray-700 rounded-md py-2 px-4">Go back</button>
        </div>
    </div>
</div>
@endsection
