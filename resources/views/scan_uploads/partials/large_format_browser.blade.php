<link rel="stylesheet" href="{{ asset('css/large-format-scans.css') }}?v={{ filemtime(public_path('css/large-format-scans.css')) }}">
<script>
window.largeFormatConfig = {
    browse: @json(route('large-format.browse')),
    save: @json(route('large-format.save'))
};
</script>
<script src="{{ asset('js/large-format-scans.js') }}?v={{ filemtime(public_path('js/large-format-scans.js')) }}"></script>
