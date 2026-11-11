{{-- Type-to-search for the FMC dropdowns (1,800+ districts); "Other" reveals its required specify box. --}}
@once
  @push('styles')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"/>
    <style>
      [data-fmc-modal] .select2-container .select2-selection--single { height: 38px; border-color: #d1d5db; border-radius: .5rem; }
      [data-fmc-modal] .select2-container .select2-selection__rendered { line-height: 36px; font-size: .875rem; }
      [data-fmc-modal] .select2-container .select2-selection__arrow { height: 36px; }
    </style>
  @endpush
  @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
      $(function () {
        $('select[data-fmc-select]').each(function () {
          var $s = $(this);
          var $modal = $s.closest('[data-fmc-modal]');
          // The server's value, not whatever the browser restored from an earlier visit.
          $s.val(String($s.data('value') || ''));
          $s.select2({ width: '100%', allowClear: true, placeholder: $s.data('placeholder'), dropdownParent: $modal.length ? $modal : $(document.body) });
          var $other = $s.closest('form').find('[data-fmc-other-for="' + $s.attr('name') + '"]');
          function sync() {
            var on = $s.val() === '__other__';
            $other.toggleClass('hidden', !on).prop('disabled', !on).prop('required', on);
            if (on) { $other.trigger('focus'); }
          }
          $s.on('change', sync);
          var on = $s.val() === '__other__';
          $other.prop('disabled', !on).prop('required', on);
        });
      });
    </script>
  @endpush
@endonce
