/**
 * Mandatory profile-picture card.
 *
 * Opens from the "Upload photo" control in the missing-photo banner (the card is not
 * a page of its own, so the banner never navigates to the profile page). On success it
 * swaps every avatar on the page, unlocks the sidebar and drops the banner, so the user
 * carries on without logging out. The server-side gate is RequireProfilePhoto.
 */
(function (window, document) {
  'use strict';

  function escapeHtml(value) {
    return String(value === undefined || value === null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  var ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif'];

  function csrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : null;
  }

  var ProfilePhotoCard = {
    init: function () {
      this.root = document.getElementById('profilePhotoCard');
      if (!this.root) {
        return;
      }

      this.input = document.getElementById('profilePhotoInput');
      this.avatar = document.getElementById('profilePhotoDropzone');
      this.preview = document.getElementById('profilePhotoPreview');
      this.placeholder = document.getElementById('profilePhotoPreviewPlaceholder');
      this.submit = document.getElementById('profilePhotoSubmit');
      this.error = document.getElementById('profilePhotoError');
      this.maxBytes = (parseInt(this.root.dataset.maxKb, 10) || 2048) * 1024;
      this.faceNote = document.getElementById('profilePhotoFaceNote');
      this.box = document.getElementById('profilePhotoBox');
      this.rows = document.getElementById('profilePhotoRows');
      // null = not checked yet, true/false = last verdict. Upload is blocked only on
      // an explicit false; an unavailable detector leaves this null and lets it through.
      this.faceOk = null;
      // The detector's wording for that verdict, sent with the upload and stored.
      this.faceReason = null;

      this.bind();
      this.lockSidebar();

      if (this.root.dataset.autoOpen === 'true') {
        this.open();
      }
    },

    bind: function () {
      var self = this;

      // Any control that asks for the card, wherever it lives on the page.
      document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-profile-photo-trigger]');
        if (trigger) {
          event.preventDefault();
          self.open();
        }
      });

      this.input.addEventListener('change', function () {
        self.clearError();
        var file = self.input.files && self.input.files[0];
        if (!file) {
          self.resetPreview();
          return;
        }
        if (!self.validate(file)) {
          self.input.value = '';
          self.resetPreview();
          return;
        }
        var reader = new FileReader();
        reader.onload = function (e) {
          self.preview.src = e.target.result;
          self.preview.classList.remove('ppc-hidden');
          self.placeholder.classList.add('ppc-hidden');
          self.avatar.classList.add('ppc-filled');
          // The preview element is what the detector reads, so wait for the decode.
          self.preview.decode
            ? self.preview.decode().then(function () { self.runFaceCheck(); }, function () { self.runFaceCheck(); })
            : self.runFaceCheck();
        };
        reader.readAsDataURL(file);
      });

      this.submit.addEventListener('click', function () {
        self.upload();
      });

      // The preview circle doubles as a picker.
      this.avatar.addEventListener('click', function () {
        self.input.click();
      });
      this.avatar.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          self.input.click();
        }
      });
    },

    validate: function (file) {
      if (ALLOWED_TYPES.indexOf(file.type) === -1) {
        this.showError('Please select a JPG, PNG or GIF image.');
        return false;
      }
      if (file.size > this.maxBytes) {
        this.showError('The photo must be 2MB or smaller.');
        return false;
      }
      return true;
    },

    /**
     * Check the chosen picture actually shows one human face before it can be uploaded.
     *
     * FAIL-OPEN BY DESIGN: this card is the gate on the whole system, so a detector that
     * cannot load (offline, blocked asset, unsupported browser) must never leave someone
     * unable to sign in. Any error leaves faceOk null and the upload proceeds.
     */
    runFaceCheck: function () {
      var self = this;
      this.faceOk = null;
      this.faceReason = null;
      this.clearFaceBoxes();
      this.clearVerification();

      if (!window.FaceDetection) {
        this.setFaceNote('', '');
        return;
      }

      this.setFaceNote('checking', 'Checking the picture for a face…');
      this.submit.disabled = true;

      window.FaceDetection.ensure()
        .then(function (detector) {
          return detector.detect(self.preview).then(function (verdict) {
            self.faceOk = verdict.accepted;
            // Kept so the upload can report what was found, in the detector's own words.
            self.faceReason = verdict.reason;
            // Keep the button disabled on a rejected picture, so the only way forward
            // is to choose a different one.
            self.submit.disabled = !verdict.accepted;

            self.drawFaceBoxes(verdict);
            self.renderVerification(verdict, detector);

            if (verdict.accepted) {
              self.setFaceNote('ok', 'Face detected — '
                + detector.toPercent(verdict.primary.score) + ' confidence.');
              self.clearError();
            } else {
              self.setFaceNote('bad', verdict.headline);
              self.showError(verdict.headline);
            }
          });
        })
        .catch(function (error) {
          // Never block on our own failure.
          console.warn('[face-detection] check skipped', error);
          self.faceOk = null;
          self.submit.disabled = false;
          self.setFaceNote('', '');
        });
    },

    /**
     * Box every detected face on the preview: green when the picture is accepted, red
     * when it is not — the same convention as the test page.
     *
     * The preview is a circular object-fit:cover crop, so the detector's coordinates
     * (natural image pixels) have to go through the same cover transform, otherwise the
     * box lands in the wrong place on any image that is not square.
     */
    drawFaceBoxes: function (verdict) {
      if (!this.box || !this.box.getContext) {
        return;
      }

      var rect = this.box.getBoundingClientRect();
      var dpr = window.devicePixelRatio || 1;
      this.box.width = Math.max(1, Math.round(rect.width * dpr));
      this.box.height = Math.max(1, Math.round(rect.height * dpr));

      var ctx = this.box.getContext('2d');
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      ctx.clearRect(0, 0, rect.width, rect.height);

      if (!verdict || !verdict.faces.length) {
        return;
      }

      // object-fit: cover — scale to the larger ratio, then centre the overflow.
      var scale = Math.max(rect.width / verdict.imageWidth, rect.height / verdict.imageHeight);
      var offsetX = (rect.width - verdict.imageWidth * scale) / 2;
      var offsetY = (rect.height - verdict.imageHeight * scale) / 2;

      ctx.lineWidth = 2;
      ctx.strokeStyle = verdict.accepted ? '#16a34a' : '#dc2626';

      verdict.faces.forEach(function (face) {
        ctx.strokeRect(
          face.x * scale + offsetX,
          face.y * scale + offsetY,
          face.width * scale,
          face.height * scale
        );
      });
    },

    clearFaceBoxes: function () {
      if (this.box && this.box.getContext) {
        this.box.getContext('2d').clearRect(0, 0, this.box.width, this.box.height);
      }
    },

    /**
     * What the detector actually measured, so a rejection can be understood and acted
     * on rather than just refused.
     */
    renderVerification: function (verdict, detector) {
      if (!this.rows) {
        return;
      }

      var face = verdict.primary;
      var rows = [
        ['Status', verdict.accepted ? 'ACCEPTED' : 'REJECTED', verdict.accepted ? 'is-pass' : 'is-fail'],
        ['Faces detected', String(verdict.faceCount), verdict.faceCount === 1 ? '' : 'is-fail']
      ];

      if (face) {
        rows.push(['Confidence', detector.toPercent(face.score),
          face.score >= detector.CONFIG.minConfidence ? 'is-pass' : 'is-fail']);
        rows.push(['Face size', face.width + ' x ' + face.height, '']);
        rows.push(['Face coverage', detector.toPercent(face.coverage),
          face.coverage >= detector.CONFIG.minFaceCoverage ? 'is-pass' : 'is-fail']);
      }

      if (verdict.flatness !== null && verdict.flatness !== undefined) {
        rows.push(['Photo texture', detector.toPercent(1 - verdict.flatness) + ' detail',
          verdict.flatness <= detector.CONFIG.maxFlatness ? 'is-pass' : 'is-fail']);
      }

      rows.push(['Image size', verdict.imageWidth + ' x ' + verdict.imageHeight, '']);

      this.rows.innerHTML = rows.map(function (row) {
        return '<dl class="ppc-row"><dt>' + escapeHtml(row[0]) + '</dt>'
          + '<dd class="' + row[2] + '">' + escapeHtml(row[1]) + '</dd></dl>';
      }).join('');
      this.rows.classList.remove('ppc-hidden');
    },

    clearVerification: function () {
      if (this.rows) {
        this.rows.innerHTML = '';
        this.rows.classList.add('ppc-hidden');
      }
    },

    setFaceNote: function (kind, message) {
      if (!this.faceNote) {
        return;
      }
      var colours = { checking: '#64748b', ok: '#107c41', bad: '#b91c1c' };
      this.faceNote.textContent = message || '';
      this.faceNote.style.color = colours[kind] || '#64748b';
      this.faceNote.style.display = message ? 'block' : 'none';
    },

    upload: function () {
      var self = this;
      var file = this.input.files && this.input.files[0];

      if (!file) {
        this.showError('Please choose a passport photo to upload.');
        return;
      }
      if (!this.validate(file)) {
        return;
      }

      // Only an explicit rejection blocks; null (detector unavailable) does not.
      if (this.faceOk === false) {
        this.showError('This picture cannot be used. Please upload a clear photograph of your own face.');
        return;
      }

      var data = new FormData();
      data.append('profile', file);

      // Send the verdict along with the file it belongs to. The picture has just been
      // judged here, so the server can record it now rather than have the browser load
      // the model again on the next page load to reach the same conclusion. Null (the
      // detector never loaded) reports nothing and leaves the picture to be checked later.
      if (this.faceOk !== null) {
        data.append('face_status', this.faceOk ? 'pass' : 'fail');
        if (this.faceReason) {
          data.append('face_reason', this.faceReason);
        }
      }

      this.setBusy(true);

      fetch(this.root.dataset.uploadUrl, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken(),
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json'
        },
        body: data,
        credentials: 'same-origin'
      })
        .then(function (response) {
          return response.json().then(function (body) {
            return { ok: response.ok, body: body };
          });
        })
        .then(function (result) {
          if (!result.ok || !result.body.success) {
            var message = result.body.message
              || (result.body.errors && result.body.errors.profile && result.body.errors.profile[0])
              || 'The photo could not be uploaded. Please try again.';
            self.showError(message);
            self.setBusy(false);
            return;
          }

          self.onUploaded(result.body);
        })
        .catch(function () {
          self.showError('The photo could not be uploaded. Please check your connection and try again.');
          self.setBusy(false);
        });
    },

    onUploaded: function (body) {
      this.refreshAvatars(body.profile_url);
      this.unlockSidebar();

      var banner = document.getElementById('missingProfilePhotoBanner');
      if (banner) {
        banner.remove();
      }

      this.close();
      this.setBusy(false);

      if (window.AppLayout && typeof window.AppLayout.showAlerts === 'function') {
        window.AppLayout.showAlerts({ success: body.message });
      } else if (typeof window.Swal !== 'undefined') {
        window.Swal.fire({ icon: 'success', title: 'Success', text: body.message });
      } else {
        window.alert(body.message);
      }
    },

    /**
     * Swap the freshly uploaded photo into the header, sidebar and welcome card
     * without a reload. Placeholder icons next to a slot are hidden.
     */
    refreshAvatars: function (url) {
      if (!url) {
        return;
      }

      var slots = document.querySelectorAll('[data-user-avatar]');
      slots.forEach(function (slot) {
        var img = slot.querySelector('img');
        if (!img) {
          img = document.createElement('img');
          img.className = 'w-full h-full object-cover';
          img.alt = 'Profile';
          slot.appendChild(img);
        }
        img.src = url;
        img.classList.remove('hidden');

        slot.querySelectorAll('svg').forEach(function (icon) {
          icon.classList.add('hidden');
        });
      });
    },

    lockSidebar: function () {
      var sidebar = document.querySelector('.sidebar');
      if (sidebar) {
        sidebar.classList.add('sidebar-locked');
        sidebar.setAttribute('aria-disabled', 'true');
      }
    },

    unlockSidebar: function () {
      var sidebar = document.querySelector('.sidebar');
      if (sidebar) {
        sidebar.classList.remove('sidebar-locked');
        sidebar.removeAttribute('aria-disabled');
      }
    },

    setBusy: function (busy) {
      this.submit.disabled = busy;
      this.submit.textContent = busy ? 'Uploading…' : 'Upload Profile Picture';
    },

    showError: function (message) {
      this.error.textContent = message;
      this.error.classList.remove('ppc-hidden');
    },

    clearError: function () {
      this.error.textContent = '';
      this.error.classList.add('ppc-hidden');
    },

    resetPreview: function () {
      this.preview.src = '';
      this.preview.classList.add('ppc-hidden');
      this.placeholder.classList.remove('ppc-hidden');
      this.avatar.classList.remove('ppc-filled');
      this.faceOk = null;
      this.faceReason = null;
      this.setFaceNote('', 'Choose a photo to check it.');
      this.clearFaceBoxes();
      this.clearVerification();
      this.submit.disabled = false;
    },

    open: function () {
      this.root.classList.add('ppc-open');
      this.root.setAttribute('aria-hidden', 'false');
    },

    close: function () {
      this.root.classList.remove('ppc-open');
      this.root.setAttribute('aria-hidden', 'true');
    }
  };

  window.ProfilePhotoCard = ProfilePhotoCard;

  document.addEventListener('DOMContentLoaded', function () {
    ProfilePhotoCard.init();
  });
})(window, document);
