/**
 * One-time face check on the picture the signed-in user already has on file.
 *
 * WHY THIS EXISTS
 * The mandatory-photo gate used to ask only whether a file was present, so a stock
 * cartoon avatar satisfied it. Detection runs in the browser (face-api.js — there is no
 * PHP detector), so the only way the server can gate on "is this actually a photograph
 * of a face" is for a browser to judge the stored picture once and report the verdict.
 * That is all this file does.
 *
 * WHEN IT RUNS
 * Only while the server says this picture has never been judged — the layout emits
 * window.ProfilePhotoSelfCheck only then, and the recorded verdict stops it emitting
 * again. So it is one run per picture, per account, ever; a checked account never pays
 * for the 1.3MB library or the model at all.
 *
 * FAIL-OPEN, like every other part of this feature: a detector that cannot load reports
 * nothing, and the account is left exactly as it was. Only an explicit verdict is sent.
 */
(function (window, document) {
  'use strict';

  var cfg = window.ProfilePhotoSelfCheck;

  if (!cfg || !cfg.photoUrl || !cfg.endpoint || !window.FaceDetection) {
    return;
  }

  // One attempt per picture per browsing session. Without this, a browser that cannot
  // load the model (offline, blocked asset) would re-fetch it on every single page load,
  // since a failed attempt records no verdict and the server keeps asking.
  var sessionKey = 'klaes.photoFaceCheck:' + cfg.photoUrl;

  try {
    if (window.sessionStorage && window.sessionStorage.getItem(sessionKey)) {
      return;
    }
    if (window.sessionStorage) {
      window.sessionStorage.setItem(sessionKey, '1');
    }
  } catch (error) {
    // Private mode or blocked storage: proceed, just without the once-per-session guard.
  }

  // Reloading a page the user has started typing into would throw their work away. The
  // lock applies on their next request regardless, so a dirty page simply skips it.
  var pageIsDirty = false;
  document.addEventListener('input', function () { pageIsDirty = true; }, true);
  document.addEventListener('change', function () { pageIsDirty = true; }, true);

  function report(status, reason) {
    var body = new FormData();
    body.append('status', status);
    if (reason) {
      body.append('reason', reason);
    }
    if (cfg.csrf) {
      body.append('_token', cfg.csrf);
    }

    return fetch(cfg.endpoint, {
      method: 'POST',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
      body: body
    })
      .then(function (response) { return response.json().catch(function () { return {}; }); })
      .then(function (result) {
        // The account has just been held. Reload so the banner, the greyed sidebar and
        // the upload card appear together rather than at the next navigation.
        if (result && result.locked && !pageIsDirty) {
          window.location.reload();
        }
      })
      .catch(function () { /* Reporting is best-effort; the next session retries. */ });
  }

  function run() {
    window.FaceDetection.ensure()
      .then(function (detector) {
        var probe = new Image();
        // Same-origin picture, but the detector reads pixels off a canvas — an untainted
        // one is what makes the illustration test possible at all.
        probe.crossOrigin = 'anonymous';

        return new Promise(function (resolve, reject) {
          probe.onload = function () { resolve(probe); };
          probe.onerror = function () { reject(new Error('image load failed')); };
          probe.src = cfg.photoUrl;
        }).then(function (image) {
          return detector.detect(image).then(function (verdict) {
            return report(verdict.accepted ? 'pass' : 'fail', verdict.reason);
          });
        });
      })
      .catch(function (error) {
        // A picture that cannot be loaded or a model that will not start is NOT a
        // rejection. Say nothing and leave the account alone.
        console.warn('[face-detection] stored-photo check skipped', error);
      });
  }

  // Never compete with the page the user actually asked for.
  function schedule() {
    if (window.requestIdleCallback) {
      window.requestIdleCallback(run, { timeout: 5000 });
    } else {
      window.setTimeout(run, 2000);
    }
  }

  if (document.readyState === 'complete') {
    schedule();
  } else {
    window.addEventListener('load', schedule);
  }
})(window, document);
