{{-- KLAES mobile preloader — shows the logo until the page finishes loading --}}
<div id="klaes-preloader" role="status" aria-label="Loading">
  <img src="//app.klaes.ng/assets/logo/klas_logo.gif" alt="KLAES">
</div>
<style>
  #klaes-preloader {
    position: fixed; inset: 0; z-index: 100000;
    display: flex; align-items: center; justify-content: center;
    background: #fff7f7; transition: opacity .4s ease;
  }
  #klaes-preloader img { width: 130px; height: auto; }
  #klaes-preloader.hide { opacity: 0; pointer-events: none; }
  html[data-theme="dark"] #klaes-preloader { background: #0c0e15; }
</style>
<script>
  (function () {
    function hidePreloader() {
      var p = document.getElementById('klaes-preloader');
      if (!p) return;
      p.classList.add('hide');
      setTimeout(function () { if (p && p.parentNode) p.parentNode.removeChild(p); }, 450);
    }
    if (document.readyState === 'complete') {
      setTimeout(hidePreloader, 300);
    } else {
      window.addEventListener('load', function () { setTimeout(hidePreloader, 300); });
    }
    // Safety net: never let the preloader hang the screen.
    setTimeout(hidePreloader, 6000);
  })();
</script>
