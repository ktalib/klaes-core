{{-- KLAES mobile splash — branded intro shown ONCE per app-open (browser session).
     Skipped on refresh and on navigating between pages within the same session;
     re-appears only when the app is opened in a new session/tab. --}}
<div id="klaes-splash" role="status" aria-label="KLAES" style="display:none;">
  <div class="klaes-splash-inner">
    <div class="klaes-splash-logo">
      <img src="//app.klaes.ng/storage/upload/logo/klas_logo.gif" alt="KLAES">
    </div>
    <div class="klaes-splash-title">KLAES</div>
    <div class="klaes-splash-sub">File Tracker</div>
    <div class="klaes-splash-dots"><span></span><span></span><span></span></div>
  </div>
</div>
<style>
  #klaes-splash {
    position: fixed; inset: 0; z-index: 100001;
    display: flex; align-items: center; justify-content: center;
    background: radial-gradient(120% 120% at 50% 0%, #eef0fb 0%, #f7f4fb 60%, #ffffff 100%);
    transition: opacity .5s ease;
  }
  html[data-theme="dark"] #klaes-splash {
    background: radial-gradient(120% 120% at 50% 0%, #161a26 0%, #0f1118 60%, #0c0e15 100%);
  }
  #klaes-splash.hide { opacity: 0; pointer-events: none; }
  .klaes-splash-inner { text-align: center; animation: klaes-splash-rise .6s ease both; }
  .klaes-splash-logo img { width: 110px; height: auto; }
  .klaes-splash-title {
    margin-top: 16px; font-family: 'Inter', sans-serif; font-weight: 800;
    font-size: 30px; letter-spacing: 4px; color: #6366f1;
  }
  .klaes-splash-sub {
    margin-top: 2px; font-family: 'Inter', sans-serif; font-weight: 600;
    font-size: 13px; letter-spacing: 2px; text-transform: uppercase; color: #99a0b3;
  }
  .klaes-splash-dots { margin-top: 22px; display: flex; gap: 7px; justify-content: center; }
  .klaes-splash-dots span {
    width: 8px; height: 8px; border-radius: 50%; background: #6366f1; opacity: .35;
    animation: klaes-splash-bounce 1.2s infinite ease-in-out;
  }
  .klaes-splash-dots span:nth-child(2) { animation-delay: .2s; }
  .klaes-splash-dots span:nth-child(3) { animation-delay: .4s; }
  @keyframes klaes-splash-rise { from { opacity: 0; transform: translateY(14px) scale(.96); } to { opacity: 1; transform: none; } }
  @keyframes klaes-splash-bounce { 0%, 80%, 100% { opacity: .3; transform: scale(.8); } 40% { opacity: 1; transform: scale(1); } }
</style>
<script>
  (function () {
    var KEY = 'klaes-splash-shown';
    var el  = document.getElementById('klaes-splash');
    if (!el) return;
    var alreadyShown = false;
    try { alreadyShown = !!sessionStorage.getItem(KEY); } catch (e) {}
    if (alreadyShown) {                         // refresh / inter-page nav — no splash
      if (el.parentNode) el.parentNode.removeChild(el);
      return;
    }
    try { sessionStorage.setItem(KEY, '1'); } catch (e) {}
    el.style.display = 'flex';                   // show once, immediately (covers preloader)
    setTimeout(function () {
      el.classList.add('hide');
      setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 550);
    }, 1900);
  })();
</script>
