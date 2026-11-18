{{-- Digital Plotting: the plotting goes to the person who checks it, in the format chosen here (Kunle, 2026-11-17: "send the
     data in the selected format to the person that will check ... either the format or the traverse with the reference
     beacon details"). On submit the card builds the file (window.SPC_EXPORT) and it is attached; the traverse sheet is
     made by the server from the saved plotting. Used by KLAES and the KN-MLPP-SG portal. --}}
@php $ckFormats = \App\Models\Survey\SurveyPlanUpload::CHECK_FORMATS; @endphp
<div class="spc-checker-send" style="border:1px solid #c7d2fe;background:#eef2ff;border-radius:10px;padding:12px 14px;">
  <label for="spcCheckerFormat" style="display:block;font-weight:600;font-size:13px;margin-bottom:6px;">
    <i class="fas fa-paper-plane"></i> Send to the checker as <span style="color:#dc2626;">*</span>
  </label>
  <select name="checker_format" id="spcCheckerFormat" required data-no-search style="max-width:420px;width:100%;">
    @foreach ($ckFormats as $k => $label)
      <option value="{{ $k }}" @selected(old('checker_format', 'traverse') === $k)>{{ $label }}</option>
    @endforeach
  </select>
  <input type="file" name="checker_file" id="spcCheckerFile" hidden>
  <p id="spcCheckerNote" style="margin:6px 0 0;font-size:12px;color:#4b5563;">The checking unit receives the plotting in this format with the plan, and is notified.</p>
  @error('checker_format')<p style="margin:4px 0 0;font-size:12px;color:#dc2626;">{{ $message }}</p>@enderror
  @error('checker_file')<p style="margin:4px 0 0;font-size:12px;color:#dc2626;">{{ $message }}</p>@enderror
</div>
<script>
(function () {
  var sel = document.getElementById('spcCheckerFormat'), form = sel && sel.closest('form');
  if (!form) return;
  var note = document.getElementById('spcCheckerNote'), building = false, ready = false;
  form.addEventListener('submit', function (e) {
    if (ready || sel.value === 'traverse') return;            // the server makes the traverse sheet
    e.preventDefault();
    if (building) return;
    if (typeof window.SPC_EXPORT !== 'function') { note.textContent = 'The map card has not loaded; refresh the page.'; return; }
    building = true;
    note.textContent = 'Preparing the ' + sel.options[sel.selectedIndex].text + ' for the checker…';
    window.SPC_EXPORT(sel.value).then(function (file) {
      var dt = new DataTransfer(); dt.items.add(file);
      document.getElementById('spcCheckerFile').files = dt.files;
      ready = true;
      note.textContent = file.name + ' attached. Sending…';
      if (form.requestSubmit) form.requestSubmit(); else form.submit();
    }).catch(function (err) {
      note.textContent = 'Could not make the file: ' + err.message + '. Plot the plan first, or choose the traverse sheet.';
      note.style.color = '#dc2626';
    }).finally(function () { building = false; });
  }, true);
})();
</script>
