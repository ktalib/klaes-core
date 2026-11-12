{{-- Reference beacons on Survey Plan Upload (CR 11112026-2, item 4), shared by KLAES and the MLPP-SG portal.
     One row to start and "+ Add beacon" for more (user, 2026-11-11). The number is picked from the beacons
     captured in KLAES (they fill X and Y when the register holds them) or the Extant Beacons list; one that is
     in neither is typed. Self-styled so it sits in either layout.
     Beacon No. is a dropdown with search (user, 2026-11-12): select2 searching the server as you type (the lists
     hold 120,000+ beacons), with the typed number offered when it is not listed. Without select2 it is a plain box.
     $searchUrl: the beacon search route. --}}
@php
  $rbRows = array_values(array_filter((array) old('beacons', []), fn ($b) => trim(implode('', array_map('strval', (array) $b))) !== ''));
  if (! $rbRows) $rbRows = [['beacon_no' => '', 'x' => '', 'y' => '', 'source' => '']];
@endphp
<style>
  .rb-wrap { display: grid; gap: 10px; }
  .rb-table { width: 100%; border-collapse: collapse; font-size: 13px; }
  .rb-table th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; padding: 6px; border-bottom: 1px solid #e5e7eb; }
  .rb-table td { padding: 6px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
  .rb-table input[type=text], .rb-table select.rb-no { width: 100%; padding: 7px 9px; border: 1.5px solid #d1d5db; border-radius: 6px; font-size: 13px; background: #fff; }
  .rb-table td.rb-n { width: 1%; color: #6b7280; padding-top: 13px; }
  .rb-cell .select2-container { width: 100% !important; }
  .rb-cell .select2-container .select2-selection--single { height: 36px; border: 1.5px solid #d1d5db; border-radius: 6px; }
  .rb-cell .select2-container .select2-selection__rendered { line-height: 33px; padding-left: 9px; font-size: 13px; color: #111827; }
  .rb-cell .select2-container .select2-selection__placeholder { color: #9ca3af; }
  .rb-cell .select2-container .select2-selection__arrow { height: 33px; }
  .rb-opt { display: flex; justify-content: space-between; gap: 10px; }
  .rb-opt small { color: #6b7280; white-space: nowrap; }
  .select2-results__option--highlighted .rb-opt small { color: #e5e7eb; }
  .rb-src { display: inline-block; margin-top: 4px; font-size: 11px; color: #6b7280; }
  .rb-del { border: 1px solid #e5e7eb; background: #fff; border-radius: 6px; padding: 6px 10px; cursor: pointer; color: #6b7280; font-weight: 700; }
  .rb-add { justify-self: start; border: 1px dashed #93c5fd; background: #eff6ff; color: #1d4ed8; border-radius: 6px; padding: 7px 12px; font-weight: 600; cursor: pointer; font-size: 13px; }
  .rb-help { margin: 0; font-size: 12px; color: #6b7280; }
  .rb-err { margin: 0; font-size: 12px; color: #b91c1c; }
</style>
<div class="rb-wrap" id="rbWrap" data-search="{{ $searchUrl }}">
  <p class="rb-help">Pick each reference beacon from the list: beacons captured in KLAES fill X and Y for you. If a beacon is not listed, type its number and choose it. Enter X (Easting) and Y (Northing) as written on the plan.</p>
  @if ($errors->has('beacons') || collect($errors->keys())->contains(fn ($k) => str_starts_with($k, 'beacons.')))
    @foreach (collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'beacons'))->flatten() as $m)
      <p class="rb-err">{{ $m }}</p>
    @endforeach
  @endif
  <div style="overflow-x:auto;">
    <table class="rb-table">
      <thead><tr><th>#</th><th>Beacon No.</th><th>X (Easting)</th><th>Y (Northing)</th><th></th></tr></thead>
      <tbody id="rbRows">
        @foreach ($rbRows as $i => $b)
          <tr class="rb-row">
            <td class="rb-n">{{ $i + 1 }}</td>
            <td class="rb-cell" style="min-width:200px;">
              <select class="rb-no" name="beacons[{{ $i }}][beacon_no]" data-no-search aria-label="Beacon number">
                <option value=""></option>
                @if (($b['beacon_no'] ?? '') !== '')<option value="{{ $b['beacon_no'] }}" selected>{{ $b['beacon_no'] }}</option>@endif
              </select>
              <input type="hidden" class="rb-source" name="beacons[{{ $i }}][source]" value="{{ $b['source'] ?? '' }}">
              <span class="rb-src"></span>
            </td>
            <td style="min-width:120px;"><input type="text" class="rb-x" name="beacons[{{ $i }}][x]" value="{{ $b['x'] ?? '' }}" inputmode="decimal" maxlength="40" aria-label="X (Easting)"></td>
            <td style="min-width:120px;"><input type="text" class="rb-y" name="beacons[{{ $i }}][y]" value="{{ $b['y'] ?? '' }}" inputmode="decimal" maxlength="40" aria-label="Y (Northing)"></td>
            <td><button type="button" class="rb-del" aria-label="Remove beacon">×</button></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  <button type="button" class="rb-add" id="rbAdd">+ Add beacon</button>
</div>
<script>
// Started after the page's own scripts (jQuery / select2 load at the foot of both layouts).
window.addEventListener('load', function () {
  var wrap = document.getElementById('rbWrap');
  if (!wrap || wrap.dataset.ready) return;
  wrap.dataset.ready = '1';
  var body = document.getElementById('rbRows'), url = wrap.dataset.search;
  var $ = window.jQuery && window.jQuery.fn && window.jQuery.fn.select2 ? window.jQuery : null;
  var SRC = { captured: 'From the Beacon Register', extant: 'From the Extant Beacons list', typed: 'Typed (not listed)' };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var ROW = body.querySelector('.rb-row').outerHTML;   // template for "+ Add beacon"

  function renumber() {
    Array.prototype.forEach.call(body.querySelectorAll('.rb-row'), function (tr, i) {
      tr.querySelector('.rb-n').textContent = i + 1;
      tr.querySelectorAll('input, select').forEach(function (el) { if (el.name) el.name = el.name.replace(/beacons\[\d+\]/, 'beacons[' + i + ']'); });
      tr.querySelector('.rb-del').style.visibility = body.querySelectorAll('.rb-row').length > 1 ? '' : 'hidden';
    });
  }
  function noValue(tr) { var n = tr.querySelector('.rb-no'); return n ? String(n.value || '').trim() : ''; }
  function showSource(tr) {
    var s = tr.querySelector('.rb-source').value;
    tr.querySelector('.rb-src').textContent = noValue(tr) ? (SRC[s] || SRC.typed) : '';
  }

  function wire(tr) {
    var sel = tr.querySelector('.rb-no'), src = tr.querySelector('.rb-source'), x = tr.querySelector('.rb-x'), y = tr.querySelector('.rb-y');
    var filled = { x: '', y: '' };
    // Fill X / Y from the register, replacing only what an earlier pick filled, never what was typed.
    function fill(it) {
      [[x, 'x'], [y, 'y']].forEach(function (p) {
        var v = it && it[p[1]] ? String(it[p[1]]) : '';
        if (v && (p[0].value === '' || p[0].value === filled[p[1]])) { p[0].value = v; filled[p[1]] = v; }
        else if (!v && p[0].value === filled[p[1]]) { p[0].value = ''; filled[p[1]] = ''; }
      });
    }

    if (!$) {
      // No select2 on this page: a plain box, typed by hand.
      var input = document.createElement('input');
      input.type = 'text'; input.className = 'rb-no'; input.name = sel.name; input.value = sel.value; input.maxLength = 60;
      input.placeholder = 'Beacon number, e.g. A0876A'; input.setAttribute('aria-label', 'Beacon number');
      sel.replaceWith(input);
      input.addEventListener('input', function () { src.value = 'typed'; showSource(tr); });
    } else {
      $(sel).select2({
        width: '100%', placeholder: 'Search beacon, e.g. A0876A', allowClear: true, minimumInputLength: 2, tags: true,
        ajax: {
          url: url, delay: 250, dataType: 'json',
          data: function (p) { return { q: p.term }; },
          processResults: function (j) {
            return { results: (j.data || []).map(function (it) { return { id: it.beacon_no, text: it.beacon_no, src: it.source, x: it.x, y: it.y }; }) };
          }
        },
        // A number in neither list is offered last as typed.
        createTag: function (p) { var t = String(p.term || '').trim().toUpperCase(); return t ? { id: t, text: t, newTag: true } : null; },
        insertTag: function (data, tag) { data.push(tag); },
        templateResult: function (d) {
          if (d.loading || !d.id) return d.text;
          var hint = d.newTag ? 'Not listed: use as typed' : d.src === 'captured' ? (d.x && d.y ? 'Register · X ' + d.x + ', Y ' + d.y : 'Register') : 'Extant list';
          return $('<span class="rb-opt"><span>' + esc(d.text) + '</span><small>' + esc(hint) + '</small></span>');
        },
        language: {
          inputTooShort: function () { return 'Type 2 or more characters of the beacon number'; },
          searching: function () { return 'Searching…'; },
          noResults: function () { return 'No beacon found'; }
        }
      }).on('select2:select', function (e) {
        var d = e.params.data;
        src.value = d.newTag ? 'typed' : (d.src || 'typed');
        fill(d.newTag ? null : d);
        showSource(tr);
      }).on('select2:clear', function () { src.value = ''; fill(null); showSource(tr); });
    }
    tr.querySelector('.rb-del').addEventListener('click', function () {
      if ($ && tr.querySelector('select.rb-no')) $(tr.querySelector('select.rb-no')).select2('destroy');
      tr.remove(); renumber();
    });
    showSource(tr);
  }

  Array.prototype.forEach.call(body.querySelectorAll('.rb-row'), wire);
  document.getElementById('rbAdd').addEventListener('click', function () {
    var holder = document.createElement('tbody');
    holder.innerHTML = ROW;
    var tr = holder.firstElementChild;
    tr.querySelectorAll('input').forEach(function (el) { el.value = ''; });
    var s = tr.querySelector('select.rb-no'); s.innerHTML = '<option value=""></option>';
    tr.querySelector('.rb-src').textContent = '';
    body.appendChild(tr); renumber(); wire(tr);
    if ($) $(tr.querySelector('select.rb-no')).select2('open');
  });
  renumber();
});
</script>
