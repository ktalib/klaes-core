@if(session('success'))
    <div class="flex items-start gap-2 bg-green-50 border border-green-200 text-green-800 rounded-lg p-3 text-sm">
        <i data-lucide="check-circle" class="h-4 w-4 mt-0.5 flex-shrink-0"></i>
        <span>{{ session('success') }}</span>
    </div>
@endif

@if(session('error'))
    <div class="flex items-start gap-2 bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 text-sm">
        <i data-lucide="alert-circle" class="h-4 w-4 mt-0.5 flex-shrink-0"></i>
        <span>{{ session('error') }}</span>
    </div>
@endif

@if(session('warning'))
    <div class="flex items-start gap-2 bg-amber-50 border border-amber-200 text-amber-900 rounded-lg p-3 text-sm">
        <i data-lucide="alert-triangle" class="h-4 w-4 mt-0.5 flex-shrink-0"></i>
        <span>{{ session('warning') }}</span>
    </div>
@endif

@if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-800 rounded-lg p-3 text-sm">
        <ul class="list-disc pl-5 space-y-0.5">
            @foreach($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif

