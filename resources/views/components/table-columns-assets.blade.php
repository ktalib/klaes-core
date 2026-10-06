@once
<meta name="table-columns-user" content="{{ $columnPreferenceGuard ?? 'web' }}:{{ auth($columnPreferenceGuard ?? 'web')->id() ?? 'guest' }}">
<link rel="stylesheet" href="{{ asset('css/table-columns.css') }}?v={{ filemtime(public_path('css/table-columns.css')) }}">
<script src="{{ asset('js/table-columns.js') }}?v={{ filemtime(public_path('js/table-columns.js')) }}" defer></script>
@endonce
