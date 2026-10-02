/**
 * Lazy loader for the face detector.
 *
 * face-api.js is ~1.3MB and the model another ~190KB. The profile cards are mounted on
 * every page by the app layout, so the library must NOT be pulled in on page load —
 * it is fetched the first time a card actually needs it, and only once.
 *
 * window.FaceDetectionAssets is written by the layout (paths + threshold overrides), so
 * URLs and tuning stay in Blade rather than hard-coded here.
 */
(function (window, document) {
  'use strict';

  var promise = null;

  function loadScript(src) {
    return new Promise(function (resolve, reject) {
      var existing = document.querySelector('script[data-face-detection="' + src + '"]');
      if (existing) {
        resolve();
        return;
      }
      var s = document.createElement('script');
      s.src = src;
      s.async = true;
      s.setAttribute('data-face-detection', src);
      s.onload = function () { resolve(); };
      s.onerror = function () { reject(new Error('Failed to load ' + src)); };
      document.head.appendChild(s);
    });
  }

  var FaceDetection = {
    /**
     * @returns {Promise<Object>} the ProfilePictureDetector module, models loaded
     */
    ensure: function () {
      if (promise) {
        return promise;
      }

      var cfg = window.FaceDetectionAssets;
      if (!cfg || !cfg.api || !cfg.detector) {
        return Promise.reject(new Error('Face detection assets are not configured.'));
      }

      promise = loadScript(cfg.api)
        .then(function () { return loadScript(cfg.detector); })
        .then(function () {
          var detector = window.ProfilePictureDetector;
          if (!detector) {
            throw new Error('Detector module did not initialise.');
          }

          // Thresholds come from the layout so there is still one place to tune them.
          if (cfg.models) { detector.CONFIG.modelUrl = cfg.models; }
          Object.keys(cfg.thresholds || {}).forEach(function (key) {
            if (key in detector.CONFIG) {
              detector.CONFIG[key] = cfg.thresholds[key];
            }
          });

          return detector.load().then(function () { return detector; });
        })
        .catch(function (error) {
          // Allow a later retry rather than caching the failure forever.
          promise = null;
          throw error;
        });

      return promise;
    },

    /** True once the library has been fetched, so callers can skip a spinner. */
    isReady: function () {
      return !!(window.ProfilePictureDetector && promise);
    }
  };

  window.FaceDetection = FaceDetection;
})(window, document);
