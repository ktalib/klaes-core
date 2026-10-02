/**
 * Shared creator/indexer profile card.
 *
 * Any element with data-user-card opens it; pass data-user-id where the table stores a
 * user id (land recommendations, RofOs) or data-user-name where it stores a display name
 * (file_indexings.created_by). The endpoint resolves either.
 *
 * Delegated from document, so rows rendered later by DataTables work without rebinding.
 */
(function (window, document) {
  'use strict';

  var UserProfileCard = {
    cache: {},

    init: function () {
      this.bind();
      this.resolve();
    },

    /**
     * Look the card's nodes up on demand rather than once at startup: the markup is
     * mounted by the layout, but a page may render it after this file has run (or
     * replace it), and a card that was not on the page at load time must not leave
     * every trigger dead for the rest of the session.
     */
    resolve: function () {
      if (this.root && this.root.isConnected) {
        return true;
      }

      this.root = document.getElementById('userProfileCard');
      if (!this.root) {
        return false;
      }

      this.endpoint = this.root.dataset.endpoint;
      this.faceEndpoint = this.root.dataset.faceEndpoint;
      this.csrf = this.root.dataset.csrf;
      this.photo = document.getElementById('upcPhoto');
      this.fallback = document.getElementById('upcPhotoFallback');
      this.name = document.getElementById('upcName');
      this.username = document.getElementById('upcUsername');
      this.fullNameValue = document.getElementById('upcFullName');
      this.usernameValue = document.getElementById('upcUsernameValue');
      this.phone = document.getElementById('upcPhone');
      this.badge = document.getElementById('upcBadge');
      this.photoState = document.getElementById('upcPhotoState');
      this.faceCheck = document.getElementById('upcFaceCheck');
      this.state = document.getElementById('upcState');

      return true;
    },

    bind: function () {
      var self = this;

      if (this.bound) {
        return;
      }
      this.bound = true;

      document.addEventListener('click', function (event) {
        var target = event.target instanceof Element ? event.target : null;
        if (!target) {
          return;
        }

        var trigger = target.closest('[data-user-card]');
        if (trigger) {
          event.preventDefault();
          // Creator cells often sit inside a row that expands or navigates on click.
          event.stopPropagation();
          if (!self.resolve()) {
            console.warn('[user-profile-card] #userProfileCard is not on this page.');
            return;
          }
          self.open(trigger.dataset.userId || '', trigger.dataset.userName || '');
          return;
        }

        if (self.root && (target.closest('[data-upc-close]') || target === self.root)) {
          self.close();
        }
      });

      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
          self.close();
        }
      });
    },

    open: function (id, name) {
      this.reset(name);
      this.root.classList.add('upc-open');
      this.root.setAttribute('aria-hidden', 'false');

      var key = id ? 'id:' + id : 'name:' + name;
      if (this.cache[key]) {
        this.render(this.cache[key]);
        return;
      }

      this.setState('Loading…');

      var params = new URLSearchParams();
      if (id) {
        params.set('id', id);
      }
      if (name) {
        params.set('name', name);
      }

      var self = this;
      fetch(this.endpoint + '?' + params.toString(), {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
      })
        .then(function (response) {
          return response.json().catch(function () {
            return { found: false };
          });
        })
        .then(function (body) {
          if (!body || !body.found) {
            self.setState((body && body.message) || 'No matching user record was found.');
            return;
          }
          self.cache[key] = body;
          self.render(body);
        })
        .catch(function () {
          self.setState('Could not load this profile.');
        });
    },

    render: function (data) {
      this.setState('');
      this.name.textContent = data.full_name || '—';
      this.username.textContent = data.username ? '@' + data.username : '';
      this.fullNameValue.textContent = data.full_name || '—';
      this.usernameValue.textContent = data.username || '—';
      this.phone.textContent = data.phone_number || '—';

      if (data.profile_url) {
        var self = this;
        // A row can point at a file that is no longer on disk; show initials rather
        // than a broken-image glyph. The badge still reflects what the record says.
        this.photo.onerror = function () {
          self.photo.classList.add('upc-hidden');
          self.fallback.classList.remove('upc-hidden');
          self.fallback.textContent = self.initials(data.full_name || data.username || '');
        };
        this.photo.src = data.profile_url;
        this.photo.alt = data.full_name || '';
        this.photo.classList.remove('upc-hidden');
        this.fallback.classList.add('upc-hidden');
      } else {
        this.photo.classList.add('upc-hidden');
        this.fallback.classList.remove('upc-hidden');
        this.fallback.textContent = this.initials(data.full_name || data.username || '');
      }

      // A rejected picture is not a profile picture, however present the file is: the
      // green tick has to say what the system believes, and the system holds this
      // account exactly as it holds one with nothing on file.
      var hasPhoto = data.has_photo !== undefined ? !!data.has_photo : !!data.profile_url;
      var rejected = hasPhoto && data.face_status === 'fail';
      this.setPhotoBadge(hasPhoto && !rejected, rejected);
      this.runFaceCheck(data);
    },

    /**
     * State whether the profile picture on screen actually contains a single human face.
     *
     * It never changes the stored photo, and a detector that will not load simply leaves
     * the line hidden. It CAN, for a Super Admin looking at a picture nobody has judged
     * yet, record the verdict — the server says so with can_report_face — and a rejection
     * is what holds that account until a real photograph is uploaded.
     */
    runFaceCheck: function (data) {
      var self = this;
      this.setFaceCheck('', '');

      if (!data.profile_url) {
        return;
      }

      // A recorded verdict is what the system actually acts on, so show that rather than
      // re-deciding it on every open — and skip the model download entirely.
      if (data.face_status) {
        this.setFaceCheck.apply(this, this.recordedFaceCheck(data));
        return;
      }

      if (!window.FaceDetection) {
        return;
      }

      // Reopening the same person should not re-run the model.
      if (data._faceCheck) {
        this.setFaceCheck(data._faceCheck.state, data._faceCheck.message);
        return;
      }

      this.setFaceCheck('', 'Checking photo…');

      window.FaceDetection.ensure()
        .then(function (detector) {
          // Detect on a fresh same-origin image so the canvas is never tainted and the
          // natural resolution is used rather than the card's 5.5rem thumbnail.
          var probe = new Image();
          probe.crossOrigin = 'anonymous';

          return new Promise(function (resolve, reject) {
            probe.onload = function () { resolve(probe); };
            probe.onerror = function () { reject(new Error('image load failed')); };
            probe.src = data.profile_url;
          }).then(function (image) {
            return detector.detect(image).then(function (verdict) {
              // The card may already have moved on to another person.
              if (self.name.textContent !== (data.full_name || '—')) {
                return;
              }

              var result = verdict.accepted
                ? { state: 'is-pass', message: 'Face check passed · ' + detector.toPercent(verdict.primary.score) + ' confidence' }
                : { state: 'is-fail', message: 'Face check: ' + verdict.reason };

              data._faceCheck = result;
              self.setFaceCheck(result.state, result.message);

              if (data.can_report_face) {
                self.reportFaceCheck(data, verdict);
              }
            });
          });
        })
        .catch(function (error) {
          console.warn('[face-detection] profile card check skipped', error);
          self.setFaceCheck('', '');
        });
    },

    /**
     * The [state, message] pair for a verdict already stored against this account.
     */
    recordedFaceCheck: function (data) {
      if (data.face_status === 'fail') {
        return ['is-fail', 'Face check: ' + (data.face_reason || 'not a photograph of a face')
          + ' — this account is locked until a real photo is uploaded'];
      }
      if (data.face_status === 'override') {
        return ['is-pass', 'Face check: accepted by an administrator'];
      }
      return ['is-pass', 'Face check passed'];
    },

    /**
     * Save the verdict this card just computed against the person it belongs to.
     *
     * Only ever sent when the server marked the record can_report_face — it grants that
     * to a Super Admin looking at a picture nobody has judged yet, and to nobody else,
     * because a 'fail' locks that person out of the whole system. Best-effort: a failed
     * report leaves the account unjudged, and the owner's own browser checks it at their
     * next sign-in.
     */
    reportFaceCheck: function (data, verdict) {
      if (!this.faceEndpoint || !data.id) {
        return;
      }

      // Once per card open: the record is now judged, whatever this call returns.
      data.can_report_face = false;

      var body = new FormData();
      body.append('status', verdict.accepted ? 'pass' : 'fail');
      body.append('reason', verdict.reason || '');
      body.append('user_id', data.id);
      if (this.csrf) {
        body.append('_token', this.csrf);
      }

      fetch(this.faceEndpoint, {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        body: body
      }).catch(function () { /* Best-effort. */ });
    },

    setFaceCheck: function (state, message) {
      if (!this.faceCheck) {
        return;
      }
      this.faceCheck.className = 'upc-face-check ' + (state || '') + (message ? '' : ' upc-hidden');
      this.faceCheck.textContent = message || '';
    },

    /**
     * Verified tick once a passport photo is on file; an alert mark while it is missing,
     * matching the mandatory-upload rule the account itself is held to.
     */
    setPhotoBadge: function (verified, rejected) {
      var TICK = '<svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>';
      var ALERT = '<svg viewBox="0 0 24 24" aria-hidden="true"><line x1="12" y1="7" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';

      this.badge.className = 'upc-badge ' + (verified ? 'upc-badge-verified' : 'upc-badge-missing');
      this.badge.innerHTML = verified ? TICK : ALERT;
      this.badge.setAttribute('aria-label', verified ? 'Profile picture uploaded' : 'No usable profile picture');
      this.badge.setAttribute('title', verified ? 'Profile picture uploaded' : 'No usable profile picture');

      // Only the missing case needs saying — a photo that is there speaks for itself,
      // and the tick badge already marks it. The face-check line below carries the
      // detail when the picture is present but was rejected.
      this.photoState.className = 'upc-photo-state ' + (verified ? 'is-verified' : 'is-missing');
      this.photoState.textContent = verified
        ? ''
        : (rejected ? 'Picture rejected — not a photograph' : 'No profile picture');
    },

    initials: function (value) {
      return value
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map(function (part) {
          return part.charAt(0).toUpperCase();
        })
        .join('') || '?';
    },

    reset: function (name) {
      this.name.textContent = name || ' ';
      this.username.textContent = '';
      this.fullNameValue.textContent = '—';
      this.usernameValue.textContent = '—';
      this.phone.textContent = '—';
      this.photo.classList.add('upc-hidden');
      this.fallback.classList.remove('upc-hidden');
      this.fallback.textContent = this.initials(name || '');
      this.badge.className = 'upc-badge upc-hidden';
      this.badge.innerHTML = '';
      this.photoState.textContent = '';
      this.photoState.className = 'upc-photo-state';
      this.setFaceCheck('', '');
    },

    setState: function (message) {
      this.state.textContent = message || '';
      this.state.classList.toggle('upc-hidden', !message);
    },

    close: function () {
      if (!this.root) {
        return;
      }
      this.root.classList.remove('upc-open');
      this.root.setAttribute('aria-hidden', 'true');
    }
  };

  window.UserProfileCard = UserProfileCard;

  // Bind straight away rather than only on DOMContentLoaded: the listener lives on
  // document, so it does not need the card markup to exist yet, and a script that
  // lands after the event (deferred behind a slow CDN tag, restored from bfcache,
  // or injected by a page) would otherwise never bind at all.
  UserProfileCard.bind();

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      UserProfileCard.resolve();
    });
  } else {
    UserProfileCard.resolve();
  }
})(window, document);
