/**
 * Profile-picture face detector — TEST BUILD.
 *
 * Wraps @vladmandic/face-api's TinyFaceDetector behind one small API so the rules live
 * in a single place. Nothing here touches the DOM, the network (beyond loading the local
 * model), or any user record: give it an <img>/<canvas> and it returns a verdict object.
 *
 * If the test page is approved, this module is what gets reused by the real profile
 * picture upload — the page wiring in test-page.js stays behind.
 *
 * Models are served from this application (public/models/face-detection), never a CDN.
 * No face descriptors are computed or stored: detection only, no recognition.
 */
(function (window) {
  'use strict';

  /**
   * Every tunable lives here — change a value once and both the rules and the on-screen
   * debug panel follow it.
   */
  var CONFIG = {
    // Where the model weights are served from (set by the page, defaults to this app).
    modelUrl: '/models/face-detection',

    // Anything the model scores below this is not even reported as a face. Kept well
    // under MIN_CONFIDENCE on purpose, so a weak detection still shows up in the panel
    // as "found but rejected" instead of silently reading as "no face at all".
    detectorScoreThreshold: 0.30,

    // A single face must reach this score to be accepted. The headline threshold.
    minConfidence: 0.75,

    // Face box area ÷ image area. Stops a face in the far background from passing as a
    // portrait. Deliberately lenient for the test build.
    minFaceCoverage: 0.05,

    // --- Photograph vs illustration -----------------------------------------
    // A face detector matches face-like GEOMETRY, so cartoons, avatars and clip-art
    // pass it happily. This second, independent signal asks a different question:
    // is this a photograph at all?
    //
    // Drawings are built from flat fills; photographs are textured everywhere by
    // lighting, skin and sensor noise. flatness = share of pixels inside the FACE BOX
    // whose 3x3 neighbourhood spans fewer than flatnessTolerance levels.
    //
    // Measured on this project's own sample (see the report): real photos scored
    // 0.17-0.53, a flat vector avatar 0.85, an anti-aliased + noised avatar 0.73.
    // 0.62 sits between the two groups with margin on both sides.
    //
    // A heuristic, not a guarantee: a heavily textured illustration can slip under it,
    // and a very smooth or over-compressed photo can rise above it. Turn off with
    // rejectIllustrations = false.
    rejectIllustrations: true,
    maxFlatness: 0.62,
    flatnessTolerance: 8,
    flatnessSampleSize: 128,

    // TinyFaceDetector input resolution: 160/224/320/416/512/608.
    // 416 is the usual accuracy/speed compromise.
    inputSize: 416,

    // Client-side file gate, applied before any model work.
    acceptedMimeTypes: ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'],
    maxFileBytes: 8 * 1024 * 1024, // 8MB
  };

  var loadPromise = null;

  function api() {
    return window.faceapi;
  }

  /**
   * Load the TinyFaceDetector weights once. Only this net is loaded — no landmarks,
   * recognition, age, gender or expression models are needed for a presence check.
   */
  function load(modelUrl) {
    if (modelUrl) {
      CONFIG.modelUrl = modelUrl;
    }

    if (!loadPromise) {
      if (!api()) {
        return Promise.reject(new Error('face-api.js did not load.'));
      }
      loadPromise = ensureBackend().then(function () {
        return api().nets.tinyFaceDetector.loadFromUri(CONFIG.modelUrl);
      });
    }

    return loadPromise;
  }

  /**
   * TensorFlow.js picks a backend lazily and then refuses to run until it is ready —
   * "The highest priority backend 'wasm' has not yet been initialized". Pin it up front:
   * WebGL where the machine offers it, plain CPU otherwise.
   *
   * WASM is deliberately skipped: its binaries are fetched from a CDN by default, and
   * this feature must run entirely from our own server.
   */
  function ensureBackend() {
    var tf = api() && api().tf;

    if (!tf || typeof tf.setBackend !== 'function') {
      return Promise.resolve();
    }

    var order = ['webgl', 'cpu'];

    return order.reduce(function (chain, name) {
      return chain.then(function (settled) {
        if (settled) {
          return true;
        }
        return Promise.resolve()
          .then(function () { return tf.setBackend(name); })
          .then(function (ok) { return ok !== false; })
          .catch(function () { return false; });
      });
    }, Promise.resolve(false)).then(function () {
      return tf.ready();
    }).then(function () {
      CONFIG.backend = tf.getBackend ? tf.getBackend() : 'unknown';
    });
  }

  /**
   * Basic file checks, before the image is even decoded.
   * @returns {{ok: boolean, message?: string}}
   */
  function validateFile(file) {
    if (!file) {
      return { ok: false, message: 'Please choose an image first.' };
    }

    var type = (file.type || '').toLowerCase();
    if (CONFIG.acceptedMimeTypes.indexOf(type) === -1) {
      return {
        ok: false,
        message: 'Unsupported file type. Please upload a JPG, PNG or WEBP image.'
      };
    }

    if (file.size > CONFIG.maxFileBytes) {
      var mb = (CONFIG.maxFileBytes / (1024 * 1024)).toFixed(0);
      return { ok: false, message: 'That image is too large. Maximum size is ' + mb + 'MB.' };
    }

    return { ok: true };
  }

  /**
   * Run detection over an already-loaded image element.
   *
   * @param {HTMLImageElement|HTMLCanvasElement} element
   * @returns {Promise<Object>} verdict — see buildVerdict() for the shape
   */
  function detect(element) {
    return load().then(function () {
      var options = new (api().TinyFaceDetectorOptions)({
        inputSize: CONFIG.inputSize,
        scoreThreshold: CONFIG.detectorScoreThreshold
      });

      return api().detectAllFaces(element, options).then(function (detections) {
        var imageWidth = element.naturalWidth || element.width;
        var imageHeight = element.naturalHeight || element.height;

        // Flatness is measured on the strongest face only — the background is not
        // what we are judging.
        var flatness = null;
        if (detections && detections.length) {
          var strongest = detections.slice().sort(function (a, b) { return b.score - a.score; })[0];
          flatness = measureFlatness(element, strongest.box);
        }

        return buildVerdict(detections, imageWidth, imageHeight, flatness);
      });
    });
  }

  /**
   * Turn raw detections into the accept/reject decision plus everything the result
   * panel wants to show. Pure function — easy to reason about and to re-test.
   */
  /**
   * Share of near-uniform pixels inside the face box: high for painted/vector art,
   * low for photographs. Returns null if a canvas is unavailable (never fatal —
   * the rule is simply skipped).
   */
  function measureFlatness(element, box) {
    try {
      var size = CONFIG.flatnessSampleSize;
      var canvas = document.createElement('canvas');
      canvas.width = size;
      canvas.height = size;

      var ctx = canvas.getContext('2d', { willReadFrequently: true });
      ctx.drawImage(element, box.x, box.y, box.width, box.height, 0, 0, size, size);
      var data = ctx.getImageData(0, 0, size, size).data;

      var luma = new Float32Array(size * size);
      for (var i = 0, p = 0; i < data.length; i += 4, p++) {
        luma[p] = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
      }

      var flat = 0;
      var counted = 0;
      for (var y = 1; y < size - 1; y++) {
        for (var x = 1; x < size - 1; x++) {
          var min = Infinity;
          var max = -Infinity;
          for (var dy = -1; dy <= 1; dy++) {
            for (var dx = -1; dx <= 1; dx++) {
              var v = luma[(y + dy) * size + (x + dx)];
              if (v < min) { min = v; }
              if (v > max) { max = v; }
            }
          }
          if (max - min < CONFIG.flatnessTolerance) { flat++; }
          counted++;
        }
      }

      return counted ? flat / counted : null;
    } catch (error) {
      // A tainted canvas or a missing DOM should never break detection.
      return null;
    }
  }

  function buildVerdict(detections, imageWidth, imageHeight, flatness) {
    var imageArea = Math.max(1, imageWidth * imageHeight);

    var faces = (detections || []).map(function (d) {
      var box = d.box || d.detection.box;
      var width = Math.round(box.width);
      var height = Math.round(box.height);

      return {
        x: Math.round(box.x),
        y: Math.round(box.y),
        width: width,
        height: height,
        score: d.score !== undefined ? d.score : d.detection.score,
        // Area ratio, matching how the panel reports "face coverage".
        coverage: (width * height) / imageArea
      };
    }).sort(function (a, b) {
      return b.score - a.score;
    });

    var verdict = {
      accepted: false,
      faceCount: faces.length,
      faces: faces,
      imageWidth: imageWidth,
      imageHeight: imageHeight,
      primary: faces[0] || null,
      flatness: (flatness === undefined ? null : flatness),
      headline: '',
      reason: '',
      config: CONFIG
    };

    if (faces.length === 0) {
      verdict.headline = 'Rejected — No human face was detected in this picture.';
      verdict.reason = 'No human face detected';
      return verdict;
    }

    if (faces.length > 1) {
      verdict.headline = 'Rejected — Multiple faces were detected. Please use a picture containing only one person.';
      verdict.reason = 'Multiple faces detected (' + faces.length + ')';
      return verdict;
    }

    var face = faces[0];

    if (face.score < CONFIG.minConfidence) {
      verdict.headline = 'Rejected — A face was found, but the detector is not confident enough.';
      verdict.reason = 'Confidence ' + toPercent(face.score) + ' is below the '
        + toPercent(CONFIG.minConfidence) + ' threshold';
      return verdict;
    }

    if (face.coverage < CONFIG.minFaceCoverage) {
      verdict.headline = 'Rejected — The face is too small in this picture.';
      verdict.reason = 'Face covers ' + toPercent(face.coverage) + ' of the image, below the '
        + toPercent(CONFIG.minFaceCoverage) + ' minimum';
      return verdict;
    }

    // Last gate: the face must look photographed, not drawn.
    if (CONFIG.rejectIllustrations && verdict.flatness !== null && verdict.flatness > CONFIG.maxFlatness) {
      verdict.headline = 'Rejected — This looks like a drawing or cartoon, not a photograph.';
      verdict.reason = 'Face area is ' + toPercent(verdict.flatness) + ' flat colour, above the '
        + toPercent(CONFIG.maxFlatness) + ' limit for a photograph';
      return verdict;
    }

    verdict.accepted = true;
    verdict.headline = 'Accepted — One human face was detected.';
    verdict.reason = 'Valid single human face detected';
    return verdict;
  }

  function toPercent(value) {
    return (value * 100).toFixed(1) + '%';
  }

  window.ProfilePictureDetector = {
    CONFIG: CONFIG,
    load: load,
    detect: detect,
    validateFile: validateFile,
    buildVerdict: buildVerdict,
    toPercent: toPercent
  };
})(window);
