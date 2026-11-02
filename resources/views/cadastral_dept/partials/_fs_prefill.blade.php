{{-- Opened from Cadastral > File Status > Search (?url=cadastral&fs=<file no.>, user 2026-10-31): the list opens
     filtered to that file. A copy of the Survey File Status tabs' script: these pages search in the browser
     (DataTables, or their own search box), so the number is typed in once the page has set itself up. --}}
<script>
  (function () {
    var fs = new URLSearchParams(window.location.search).get('fs');
    if (!fs) return;
    function type(el) {
      el.value = fs;
      ['input', 'keyup', 'change'].forEach(function (t) { el.dispatchEvent(new Event(t, { bubbles: true })); });
    }
    function run() {
      ['file-search-keyword', 'cofo-search', 'searchDecommissioned', 'log-search-input'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) type(el);
      });
      var runBtn = document.getElementById('file-search-run');
      if (runBtn) runBtn.click();
      if (window.jQuery && jQuery.fn.dataTable) {
        jQuery.fn.dataTable.tables({ visible: false, api: true }).search(fs).draw();
        jQuery('.dataTables_filter input').val(fs);
      }
    }
    window.addEventListener('load', function () { setTimeout(run, 400); });
  })();
</script>
