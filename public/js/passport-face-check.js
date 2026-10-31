/**
 * Applicant passport check for file commissioning (CR 30102026 item 36, user 2026-10-31).
 *
 * The passport is scanned off the physical file and uploaded straight away, so what
 * arrives is often not a passport photo: the whole file cover with the photo stapled on,
 * a signature, a placeholder silhouette or a blurred shot of the cover. This runs the
 * same in-browser face detector as the profile photo (js/face-detection, face-api.js,
 * models served from this app; detection only, nothing is sent anywhere) and:
 *
 *   - refuses a picture with no face, more than one face, a face too unclear to read,
 *     or a drawn / placeholder face;
 *   - crops a picture whose one clear face is small in the frame (the whole-cover scan)
 *     to passport proportions around the head, and hands back the cropped file.
 *
 * If the detector cannot load, the picture is let through unchanged (skipped: true):
 * an unreachable model must not stop commissioning.
 *
 * PassportFaceCheck.check(file) -> Promise<{ ok, file, cropped, skipped, message }>
 */
(function (window, document) {
  'use strict';

  var RULES = {
    // A face covering less of the picture than this is a scan of more than the photo: crop it.
    cropBelowCoverage: 0.18,
    // Narrower than this (in the original pixels) the face is too small to be read: refuse.
    minFaceWidth: 70,
    // Passport photo proportions, width : height = 35 : 45.
    aspect: 35 / 45,
    // Crop width as a multiple of the face box, and the room left above the face box.
    widthFactor: 1.8,
    headroomFactor: 0.55,
    // Second detector pass when the first finds nothing: glare across a face (phone flash on a
    // glossy print) hides it at the default input size but not at this one.
    retryInputSize: 320,
    // Longest side of the cropped image, so the upload stays well under the 2MB rule.
    maxOutput: 900,
    jpegQuality: 0.92
  };

  function loadImage(file) {
    return new Promise(function (resolve, reject) {
      var url = URL.createObjectURL(file);
      var img = new Image();
      img.onload = function () { resolve({ img: img, url: url }); };
      img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('The image could not be opened.')); };
      img.src = url;
    });
  }

  function crop(img, face, name) {
    var W = img.naturalWidth, H = img.naturalHeight;
    var cw = Math.min(W, face.width * RULES.widthFactor);
    var ch = Math.min(H, cw / RULES.aspect);
    cw = Math.min(cw, ch * RULES.aspect);   // keep the proportions if the height was clamped
    var cx = face.x + face.width / 2;
    var x = Math.max(0, Math.min(W - cw, cx - cw / 2));
    var y = Math.max(0, Math.min(H - ch, face.y - face.height * RULES.headroomFactor));

    var scale = Math.min(1, RULES.maxOutput / Math.max(cw, ch));
    var canvas = document.createElement('canvas');
    canvas.width = Math.round(cw * scale);
    canvas.height = Math.round(ch * scale);
    canvas.getContext('2d').drawImage(img, x, y, cw, ch, 0, 0, canvas.width, canvas.height);

    return new Promise(function (resolve, reject) {
      canvas.toBlob(function (blob) {
        if (!blob) { reject(new Error('The cropped image could not be produced.')); return; }
        var base = String(name || 'passport').replace(/\.[^.]+$/, '');
        resolve(new File([blob], base + '_cropped.jpg', { type: 'image/jpeg', lastModified: Date.now() }));
      }, 'image/jpeg', RULES.jpegQuality);
    });
  }

  function refuse(message) {
    return { ok: false, file: null, cropped: false, skipped: false, message: message };
  }

  function check(file) {
    if (!window.FaceDetection || typeof window.FaceDetection.ensure !== 'function') {
      return Promise.resolve({ ok: true, file: file, cropped: false, skipped: true, message: 'Face check unavailable.' });
    }

    return loadImage(file).then(function (loaded) {
      var img = loaded.img, url = loaded.url;

      return window.FaceDetection.ensure().then(function (detector) {
        return detector.detect(img).then(function (v) {
          if (v.faceCount) return [detector, v];
          var size = detector.CONFIG.inputSize;
          detector.CONFIG.inputSize = RULES.retryInputSize;
          return detector.detect(img).then(function (v2) { return [detector, v2]; })
            .finally(function () { detector.CONFIG.inputSize = size; });
        });
      }).then(function (pair) {
        var detector = pair[0], v = pair[1];
        var cfg = detector.CONFIG;
        var face = v.primary;

        if (!v.faceCount) {
          return refuse('No face was found in this picture. Upload the applicant\'s passport photograph, not a signature, document cover or placeholder.');
        }
        if (v.faceCount > 1) {
          return refuse('More than one face was found. Upload a passport photograph of the applicant alone.');
        }
        if (face.score < cfg.minConfidence) {
          return refuse('The face in this picture is not clear enough. Rescan the passport photograph in focus, well lit and without glare or flash.');
        }
        if (cfg.rejectIllustrations && v.flatness !== null && v.flatness > cfg.maxFlatness) {
          return refuse('This looks like a drawing or placeholder image, not a photograph of the applicant.');
        }
        if (face.width < RULES.minFaceWidth) {
          return refuse('The face is too small to read. Scan the passport photograph closer.');
        }
        if (face.coverage >= RULES.cropBelowCoverage) {
          return { ok: true, file: file, cropped: false, skipped: false, message: 'One clear face found.' };
        }
        return crop(img, face, file.name).then(function (out) {
          return { ok: true, file: out, cropped: true, skipped: false, message: 'Cropped to the applicant\'s face.' };
        });
      }, function (error) {
        // Detector or model unreachable: let the picture through as it is.
        if (window.console) console.warn('[PassportFaceCheck] skipped:', error && error.message);
        return { ok: true, file: file, cropped: false, skipped: true, message: 'Face check unavailable.' };
      }).finally(function () { URL.revokeObjectURL(url); });
    }, function () {
      return refuse('This image could not be opened. Upload a JPG or PNG passport photograph.');
    });
  }

  window.PassportFaceCheck = { RULES: RULES, check: check };
})(window, document);
