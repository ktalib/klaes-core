@if (session('success'))
    <div class="comp-type-note" style="border-left:4px solid var(--secondary);background:#eafaf1;margin-bottom:16px;">
        <i class="fas fa-check-circle" style="color:var(--secondary-dark);"></i>
        {{ session('success') }}
    </div>
@endif

@if (session('error'))
    <div class="comp-type-note" style="border-left:4px solid var(--danger);background:#fdecea;margin-bottom:16px;">
        <i class="fas fa-exclamation-triangle" style="color:var(--danger-dark);"></i>
        {{ session('error') }}
    </div>
@endif

@if ($errors->any())
    <div class="comp-type-note" style="border-left:4px solid var(--danger);background:#fdecea;margin-bottom:16px;display:block;">
        <div style="font-weight:600;margin-bottom:6px;">
            <i class="fas fa-exclamation-triangle" style="color:var(--danger-dark);"></i>
            Please correct the following:
        </div>
        <ul style="margin:0 0 0 22px;">
            @foreach ($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    </div>
@endif
