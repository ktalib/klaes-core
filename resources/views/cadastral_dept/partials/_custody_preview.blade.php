{{--
    Attach / Detach correspondence file: a preview before the final submit (user, 2026-10-12).
    The land file and the correspondence file sit side by side as two cards; Attach slides
    them together and clips them, Detach unclips them and slides them apart. Confirming
    posts to cadastral-dept.cases.correspondence.custody.save.

    Open it with any element carrying:
      data-custody-open="attach|detach"  data-url  data-ref  data-file  data-title  data-corr  data-csf
--}}
<style>
  .cdc-stage { position: relative; height: 170px; overflow: hidden; }
  .cdc-card {
    position: absolute; top: 18px; left: 50%; width: 40%; height: 132px; padding: 12px 14px;
    border-radius: 12px; box-shadow: 0 6px 16px rgba(0, 0, 0, .08);
    transition: transform .9s cubic-bezier(.45, -0.35, .25, 1.35), box-shadow .9s;
  }
  .cdc-main { background: #eff6ff; border: 2px solid #3b82f6; transform: translateX(calc(-100% - 24px)) rotate(-3deg); }
  .cdc-corr { background: #fffbeb; border: 2px solid #f59e0b; transform: translateX(24px) rotate(3deg); }
  .cdc-joined .cdc-main { transform: translateX(calc(-100% + 2px)) rotate(0); box-shadow: 0 10px 24px rgba(0, 0, 0, .14); }
  .cdc-joined .cdc-corr { transform: translateX(-2px) rotate(0); box-shadow: 0 10px 24px rgba(0, 0, 0, .14); }
  .cdc-clip {
    position: absolute; top: 4px; left: 50%; width: 30px; height: 54px; margin-left: -15px; z-index: 2; color: #475569;
    opacity: 0; transform: translateY(-36px) rotate(-35deg) scale(.6);
    transition: opacity .35s, transform .45s cubic-bezier(.3, 1.6, .5, 1);
  }
  .cdc-joined .cdc-clip { opacity: 1; transform: translateY(0) rotate(0) scale(1); transition-delay: .75s; }
  .cdc-gap {
    position: absolute; top: 74px; left: 50%; margin-left: -12px; width: 24px; height: 24px; color: #94a3b8;
    opacity: 1; transition: opacity .3s .6s;
  }
  .cdc-joined .cdc-gap { opacity: 0; transition-delay: 0s; }
  .cdc-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
  .cdc-no { font-family: ui-monospace, monospace; font-weight: 700; font-size: 15px; margin-top: 6px; overflow-wrap: anywhere; }
  @media (max-width: 480px) {
    .cdc-card { width: 41%; padding: 10px; } .cdc-no { font-size: 12px; }
    .cdc-main { transform: translateX(calc(-100% - 12px)) rotate(-3deg); } .cdc-corr { transform: translateX(12px) rotate(3deg); }
  }
  @media (prefers-reduced-motion: reduce) { .cdc-card, .cdc-clip, .cdc-gap { transition: none !important; } }
</style>

<div id="cdc-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 p-4" role="dialog" aria-modal="true" aria-labelledby="cdc-title">
  <form id="cdc-form" method="POST" action="" class="w-full max-w-lg rounded-xl bg-white shadow-xl p-5 space-y-4 max-h-full overflow-y-auto">
    @csrf
    <input type="hidden" name="action" id="cdc-action" value="">
    <div class="flex items-start justify-between gap-3">
      <div>
        <div class="text-xs font-bold uppercase text-gray-500" id="cdc-ref"></div>
        <div class="text-lg font-bold text-gray-800" id="cdc-title"></div>
      </div>
      <button type="button" data-cdc-close class="text-gray-400 hover:text-gray-600 text-2xl leading-none" aria-label="Close">&times;</button>
    </div>

    <div class="cdc-stage" id="cdc-stage">
      <div class="cdc-card cdc-main">
        <div class="cdc-label text-blue-700">Land file (main)</div>
        <div class="cdc-no text-gray-900" id="cdc-file"></div>
        <div class="text-xs text-gray-600 mt-1 truncate" id="cdc-file-title"></div>
      </div>
      <div class="cdc-card cdc-corr">
        <div class="cdc-label text-yellow-700">Correspondence file</div>
        <div class="cdc-no text-gray-900" id="cdc-corr"></div>
        <div class="text-xs text-gray-600 mt-1 truncate" id="cdc-corr-sub"></div>
      </div>
      {{-- The clip that holds them together, and the broken link shown while apart. --}}
      <svg class="cdc-clip" viewBox="0 0 24 44" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true">
        <path d="M17 14v18a5 5 0 0 1-10 0V9a3.5 3.5 0 0 1 7 0v21a1.5 1.5 0 0 1-3 0V14"/>
      </svg>
      <svg class="cdc-gap" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
        <path d="M9 17H7A5 5 0 0 1 7 7"/><path d="M15 7h2a5 5 0 0 1 4 8"/><line x1="8" y1="12" x2="10" y2="12"/><line x1="2" y1="2" x2="22" y2="22"/>
      </svg>
    </div>

    <p class="text-sm text-gray-700" id="cdc-text"></p>
    <div class="flex justify-center">
      <button type="button" id="cdc-replay" class="text-xs font-semibold text-blue-700 hover:underline">&#8635; Replay preview</button>
    </div>

    <div>
      <label class="block text-xs font-semibold text-gray-600 mb-1" for="cdc-note" id="cdc-note-label">Note (optional)</label>
      <input type="text" name="note" id="cdc-note" maxlength="1000" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
    </div>

    <div class="flex justify-end gap-2">
      <button type="button" data-cdc-close class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-semibold text-gray-700">Cancel</button>
      <button type="submit" id="cdc-submit" class="px-4 py-2 rounded-lg text-white text-sm font-semibold"></button>
    </div>
  </form>
</div>

<script>
  (function () {
    var modal = document.getElementById('cdc-modal');
    var stage = document.getElementById('cdc-stage');
    var action = null;
    var $ = function (id) { return document.getElementById(id); };

    // Attach starts apart and ends joined; Detach the other way round.
    function play() {
      stage.classList.toggle('cdc-joined', action === 'detach');
      void stage.offsetWidth;   // restart the transition from the start state
      setTimeout(function () { stage.classList.toggle('cdc-joined', action === 'attach'); }, 250);
    }

    function open(el) {
      action = el.getAttribute('data-custody-open');
      var attach = action === 'attach';
      var file = el.getAttribute('data-file') || '';
      var corr = el.getAttribute('data-csf') || el.getAttribute('data-corr') || '';
      $('cdc-form').setAttribute('action', el.getAttribute('data-url'));   // not .action: the hidden input named "action" shadows it
      $('cdc-action').value = action;
      $('cdc-ref').textContent = el.getAttribute('data-ref') || '';
      $('cdc-title').textContent = attach ? 'Attach Land File' : 'Detach Correspondence';
      $('cdc-file').textContent = file;
      $('cdc-file-title').textContent = el.getAttribute('data-title') || '';
      $('cdc-corr').textContent = corr;
      $('cdc-corr-sub').textContent = el.getAttribute('data-csf') && el.getAttribute('data-corr') && el.getAttribute('data-corr') !== corr
        ? 'For ' + el.getAttribute('data-corr') : '';
      $('cdc-text').textContent = attach
        ? 'Preview: correspondence file ' + corr + ' is attached to land file ' + file + ' when you confirm.'
        : 'Preview: correspondence file ' + corr + ' is detached from land file ' + file + ' and kept here when you confirm.';
      $('cdc-note').value = '';
      $('cdc-note').placeholder = attach ? 'Note (optional)' : 'Archived where? (optional)';
      var submit = $('cdc-submit');
      submit.textContent = attach ? 'Attach' : 'Detach';
      submit.className = 'px-4 py-2 rounded-lg text-white text-sm font-semibold ' + (attach ? 'bg-green-700 hover:bg-green-800' : 'bg-yellow-600 hover:bg-yellow-700');
      modal.classList.remove('hidden');
      play();
    }

    function close() { modal.classList.add('hidden'); }

    document.addEventListener('click', function (e) {
      var el = e.target.closest('[data-custody-open]');
      if (el) { e.preventDefault(); open(el); return; }
      if (e.target.closest('[data-cdc-close]') || e.target === modal) close();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.classList.contains('hidden')) close(); });
    $('cdc-replay').addEventListener('click', play);
    // One submit only.
    $('cdc-form').addEventListener('submit', function () { $('cdc-submit').disabled = true; });
  })();
</script>
