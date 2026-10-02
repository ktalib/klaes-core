/**
 * Wiring for /test/face-detection — TEST PAGE ONLY.
 *
 * Owns the upload field, preview, bounding-box overlay and result panel. All the actual
 * rules live in profile-picture-detector.js, so this file can be thrown away when the
 * detector is wired into the real upload flow.
 *
 * The image never leaves the browser: it is read with FileReader, decoded into an
 * <img>, and passed straight to the model. Nothing is posted, stored or logged.
 */
(function (window, document) {
  'use strict';

  var Detector = window.ProfilePictureDetector;

  var el = {};
  var objectUrl = null;

  function $(id) {
    return document.getElementById(id);
  }

  function init() {
    el.root = $('faceTestRoot');
    if (!el.root || !Detector) {
      return;
    }

    el.input = $('ftFile');
    el.preview = $('ftPreview');
    el.overlay = $('ftOverlay');
    el.previewWrap = $('ftPreviewWrap');
    el.checkBtn = $('ftCheck');
    el.resetBtn = $('ftReset');
    el.status = $('ftStatus');
    el.panel = $('ftPanel');
    el.rows = $('ftRows');
    el.fileNote = $('ftFileNote');
    el.thresholds = $('ftThresholds');

    Detector.CONFIG.modelUrl = el.root.dataset.modelUrl || Detector.CONFIG.modelUrl;

    renderThresholds();
    bind();
    warmUp();
  }

  function bind() {
    el.input.addEventListener('change', onFileChosen);
    el.checkBtn.addEventListener('click', onCheck);
    el.resetBtn.addEventListener('click', reset);
  }

  /** Load the model up front so the first check is not the slow one. */
  function warmUp() {
    setStatus('loading', 'Loading detection model…');
    Detector.load()
      .then(function () {
        setStatus('idle', 'Model ready. Choose a picture to test.');
      })
      .catch(function (error) {
        console.error('[face-detection] model load failed', error);
        setStatus('error', 'Could not load the detection model. Check that the model files exist under '
          + Detector.CONFIG.modelUrl + '.');
      });
  }

  function onFileChosen() {
    clearResult();
    var file = el.input.files && el.input.files[0];

    var check = Detector.validateFile(file);
    if (!check.ok) {
      el.checkBtn.disabled = true;
      el.previewWrap.classList.add('hidden');
      setStatus('error', check.message);
      return;
    }

    if (objectUrl) {
      URL.revokeObjectURL(objectUrl);
    }
    objectUrl = URL.createObjectURL(file);

    el.preview.onload = function () {
      sizeOverlay();
      el.previewWrap.classList.remove('hidden');
      el.checkBtn.disabled = false;
      setStatus('idle', 'Ready to check.');
      el.fileNote.textContent = file.name + ' · ' + (file.size / 1024).toFixed(0) + ' KB · '
        + el.preview.naturalWidth + ' × ' + el.preview.naturalHeight;
    };
    el.preview.src = objectUrl;
  }

  function onCheck() {
    if (!el.preview.src) {
      return;
    }

    el.checkBtn.disabled = true;
    setStatus('loading', 'Checking picture…');

    Detector.detect(el.preview)
      .then(function (verdict) {
        // Debug detail goes to the console only — never to the server log.
        console.log('[face-detection] verdict', verdict);
        drawBoxes(verdict);
        renderVerdict(verdict);
        el.checkBtn.disabled = false;
      })
      .catch(function (error) {
        console.error('[face-detection] detection failed', error);
        setStatus('error', 'Detection failed: ' + (error.message || error));
        el.checkBtn.disabled = false;
      });
  }

  /** Match the overlay canvas to the displayed size of the preview. */
  function sizeOverlay() {
    var rect = el.preview.getBoundingClientRect();
    el.overlay.width = rect.width;
    el.overlay.height = rect.height;
    el.overlay.getContext('2d').clearRect(0, 0, el.overlay.width, el.overlay.height);
  }

  /** One box per detected face, scaled from natural pixels to displayed pixels. */
  function drawBoxes(verdict) {
    sizeOverlay();

    var ctx = el.overlay.getContext('2d');
    var scaleX = el.overlay.width / verdict.imageWidth;
    var scaleY = el.overlay.height / verdict.imageHeight;

    verdict.faces.forEach(function (face, index) {
      var accepted = verdict.accepted && index === 0;
      var colour = accepted ? '#16a34a' : '#dc2626';
      var x = face.x * scaleX;
      var y = face.y * scaleY;
      var w = face.width * scaleX;
      var h = face.height * scaleY;

      ctx.lineWidth = 3;
      ctx.strokeStyle = colour;
      ctx.strokeRect(x, y, w, h);

      var label = Detector.toPercent(face.score);
      ctx.font = '600 13px Inter, system-ui, sans-serif';
      var padding = 5;
      var textWidth = ctx.measureText(label).width;
      var labelY = y > 20 ? y - 19 : y + h + 2;

      ctx.fillStyle = colour;
      ctx.fillRect(x, labelY, textWidth + padding * 2, 18);
      ctx.fillStyle = '#ffffff';
      ctx.fillText(label, x + padding, labelY + 13);
    });
  }

  function renderVerdict(verdict) {
    var face = verdict.primary;

    var rows = [
      ['Status', verdict.accepted ? 'ACCEPTED' : 'REJECTED'],
      ['Faces detected', String(verdict.faceCount)]
    ];

    if (face) {
      rows.push(['Confidence', Detector.toPercent(face.score)]);
    }

    rows.push(['Image size', verdict.imageWidth + ' × ' + verdict.imageHeight]);

    if (face) {
      rows.push(['Face size', face.width + ' × ' + face.height]);
      rows.push(['Face coverage', Detector.toPercent(face.coverage)]);
    }

    // Photograph-vs-drawing signal: high = flat painted colour, low = photo texture.
    if (verdict.flatness !== null && verdict.flatness !== undefined) {
      rows.push([
        'Face flatness',
        Detector.toPercent(verdict.flatness) + ' (limit ' + Detector.toPercent(Detector.CONFIG.maxFlatness) + ')'
      ]);
    }

    rows.push(['Reason', verdict.reason]);

    el.rows.innerHTML = rows.map(function (row) {
      var isStatus = row[0] === 'Status';
      var valueClass = isStatus
        ? (verdict.accepted ? 'font-bold text-green-700' : 'font-bold text-red-700')
        : 'font-semibold text-gray-800';
      return '<div class="flex items-start justify-between gap-4 py-1.5 border-b border-gray-100 last:border-0">'
        + '<span class="text-xs uppercase tracking-wide text-gray-500">' + escapeHtml(row[0]) + '</span>'
        + '<span class="text-sm text-right ' + valueClass + '">' + escapeHtml(row[1]) + '</span></div>';
    }).join('');

    // Every face, when more than one was found — the panel above reports the strongest.
    if (verdict.faceCount > 1) {
      el.rows.innerHTML += '<div class="mt-3 pt-2 border-t border-gray-200">'
        + '<p class="text-xs uppercase tracking-wide text-gray-500 mb-1">All detections</p>'
        + verdict.faces.map(function (f, i) {
          return '<div class="text-xs text-gray-600">#' + (i + 1) + ' — '
            + Detector.toPercent(f.score) + ' · ' + f.width + '×' + f.height
            + ' · ' + Detector.toPercent(f.coverage) + ' coverage</div>';
        }).join('')
        + '</div>';
    }

    el.panel.classList.remove('hidden');
    setStatus(verdict.accepted ? 'ok' : 'error', verdict.headline);
  }

  function renderThresholds() {
    var c = Detector.CONFIG;
    el.thresholds.textContent = 'Detector: TinyFaceDetector @ ' + c.inputSize + 'px · '
      + 'report ≥ ' + Detector.toPercent(c.detectorScoreThreshold) + ' · '
      + 'accept ≥ ' + Detector.toPercent(c.minConfidence) + ' · '
      + 'min face coverage ' + Detector.toPercent(c.minFaceCoverage)
      + (c.rejectIllustrations ? ' · max face flatness ' + Detector.toPercent(c.maxFlatness) : ' · drawing check off');
  }

  function setStatus(kind, message) {
    var styles = {
      idle: 'bg-gray-50 text-gray-700 border-gray-200',
      loading: 'bg-blue-50 text-blue-800 border-blue-200',
      ok: 'bg-green-50 text-green-800 border-green-200',
      error: 'bg-red-50 text-red-800 border-red-200'
    };
    el.status.className = 'rounded-lg border px-4 py-3 text-sm font-medium ' + (styles[kind] || styles.idle);
    el.status.textContent = message;
  }

  function clearResult() {
    el.panel.classList.add('hidden');
    el.rows.innerHTML = '';
    if (el.overlay.getContext) {
      el.overlay.getContext('2d').clearRect(0, 0, el.overlay.width, el.overlay.height);
    }
  }

  function reset() {
    el.input.value = '';
    clearResult();
    el.previewWrap.classList.add('hidden');
    el.preview.removeAttribute('src');
    el.fileNote.textContent = '';
    el.checkBtn.disabled = true;
    if (objectUrl) {
      URL.revokeObjectURL(objectUrl);
      objectUrl = null;
    }
    setStatus('idle', 'Choose a picture to test.');
  }

  function escapeHtml(value) {
    return String(value === undefined || value === null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  document.addEventListener('DOMContentLoaded', init);
  window.addEventListener('resize', function () {
    if (el.preview && el.preview.src) {
      sizeOverlay();
    }
  });
})(window, document);
