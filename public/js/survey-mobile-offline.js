/*
 * SCF Mobile offline layer (KLAES Survey, 2026-10-25).
 *
 * The field app is the /survey-module/mobile pages in a Flutter WebView. This script:
 *  - registers the service worker that keeps the pages on the phone;
 *  - keeps a queue in IndexedDB of cases (with their geo-tagged photos) and field readings
 *    saved while there was no signal;
 *  - syncs the queue when the signal returns: a fresh session token first, then each item.
 *    Every item carries the phone's own id (client_uuid), so a retried sync never saves twice.
 *
 * Config comes from window.SCF_OFFLINE (survey_module/mobile/_offline.blade.php).
 */
(function () {
    'use strict';
    var cfg = window.SCF_OFFLINE || {};
    var DB_NAME = 'scf-offline', STORE = 'queue';
    var syncing = false, lastState = '';

    /* ----------------------------- service worker ----------------------------- */
    if ('serviceWorker' in navigator && cfg.sw && window.isSecureContext) {
        navigator.serviceWorker.register(cfg.sw, { scope: cfg.scope }).catch(function () {});
    }

    /* -------------------------------- storage -------------------------------- */
    function openDb() {
        return new Promise(function (resolve, reject) {
            if (!window.indexedDB) { reject(new Error('This phone cannot store data offline.')); return; }
            var req = indexedDB.open(DB_NAME, 1);
            req.onupgradeneeded = function () { req.result.createObjectStore(STORE, { keyPath: 'id' }); };
            req.onsuccess = function () { resolve(req.result); };
            req.onerror = function () { reject(req.error); };
        });
    }
    function tx(mode, fn) {
        return openDb().then(function (db) {
            return new Promise(function (resolve, reject) {
                var t = db.transaction(STORE, mode), out;
                out = fn(t.objectStore(STORE));
                t.oncomplete = function () { resolve(out && 'result' in out ? out.result : out); };
                t.onerror = function () { reject(t.error); };
            });
        });
    }
    var put = function (item) { return tx('readwrite', function (s) { return s.put(item); }); };
    var del = function (id) { return tx('readwrite', function (s) { return s.delete(id); }); };
    var all = function () { return tx('readonly', function (s) { return s.getAll(); }).then(function (r) { return (r || []).sort(function (a, b) { return a.created - b.created; }); }); };

    function uuid() {
        if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
    }

    /* --------------------------------- server --------------------------------- */
    function Fail(kind, detail) { this.kind = kind; this.detail = detail; }

    function freshToken() {
        return fetch(cfg.session, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) {
                if (r.status === 401 || r.status === 419 || r.redirected) throw new Fail('login');
                if (!r.ok) throw new Fail('server', r.status);
                return r.json();
            }, function () { throw new Fail('network'); })
            .then(function (j) {
                if (!j || !j.token) throw new Fail('login');
                return j.token;
            });
    }

    function post(url, formData, token) {
        formData.set('_token', token);
        return fetch(url, { method: 'POST', body: formData, credentials: 'same-origin',
                            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) {
                if (r.status === 422) return r.json().then(function (j) { throw new Fail('invalid', j.errors || { form: [j.message || 'Please check the form.'] }); });
                if (r.status === 401 || r.status === 419) throw new Fail('login');
                if (!r.ok) throw new Fail('server', r.status);
                return r.json();
            }, function () { throw new Fail('network'); });
    }

    function fieldsToForm(fields) {
        var fd = new FormData();
        (fields || []).forEach(function (p) { fd.append(p[0], p[1]); });
        return fd;
    }

    function sendPhotos(item, token) {
        var chain = Promise.resolve();
        (item.photos || []).forEach(function (ph) {
            chain = chain.then(function () {
                if (ph.done) return;
                var fd = new FormData();
                fd.append('photo', ph.blob, ph.name || 'photo.jpg');
                fd.append('client_uuid', ph.uuid);
                ['latitude', 'longitude', 'accuracy_m', 'taken_at', 'caption'].forEach(function (k) {
                    if (ph[k] !== null && ph[k] !== undefined && ph[k] !== '') fd.append(k, ph[k]);
                });
                return post(item.photosUrl, fd, token).then(function () { ph.done = true; return put(item); });
            });
        });
        return chain;
    }

    function syncItem(item, token) {
        var first = item.serverId ? Promise.resolve() : post(item.url, fieldsToForm(item.fields), token).then(function (res) {
            item.serverId = res.id;
            item.photosUrl = res.photos || null;
            item.serverRef = res.case_ref || null;
            return put(item);
        });
        return first
            .then(function () { return item.kind === 'case' && item.photosUrl ? sendPhotos(item, token) : null; })
            .then(function () { return del(item.id); });
    }

    /** Sync everything waiting. Stops at the first lost signal or expired sign-in. */
    function sync() {
        if (syncing || !navigator.onLine) return Promise.resolve(render());
        return all().then(function (items) {
            var todo = items.filter(function (i) { return !i.blocked; });
            if (!todo.length) return render();
            syncing = true; setState('syncing');
            return freshToken().then(function (token) {
                var chain = Promise.resolve(), stop = false;
                todo.forEach(function (item) {
                    chain = chain.then(function () {
                        if (stop) return;
                        return syncItem(item, token).catch(function (e) {
                            if (e && e.kind === 'invalid') { item.blocked = true; item.errors = e.detail; return put(item); }
                            stop = true;
                            if (e && e.kind === 'login') setState('login');
                        });
                    });
                });
                return chain;
            }).catch(function (e) { if (e && e.kind === 'login') setState('login'); })
              .then(function () { syncing = false; if (lastState === 'syncing') setState(''); return render(); });
        }).catch(function () { syncing = false; });
    }

    /* ------------------------------- status pill ------------------------------- */
    function setState(s) { lastState = s; render(); }
    function render() {
        return all().then(function (items) {
            var pill = document.getElementById('scfPill');
            var waiting = items.length, blocked = items.filter(function (i) { return i.blocked; }).length;
            if (pill) {
                var off = !navigator.onLine, text, cls = '';
                if (lastState === 'login') { text = 'Sign in to sync ' + waiting + ' saved item' + (waiting === 1 ? '' : 's'); cls = 'warn'; }
                else if (off) { text = waiting ? 'Offline · ' + waiting + ' waiting to sync' : 'Offline · saving on this phone'; cls = 'off'; }
                else if (lastState === 'syncing') { text = 'Syncing ' + waiting + '…'; }
                else if (waiting) { text = (blocked ? blocked + ' need attention · ' : '') + waiting + ' waiting to sync'; cls = blocked ? 'warn' : ''; }
                else text = '';
                pill.textContent = text;
                pill.className = 'scf-pill ' + cls;
                pill.hidden = !text;
            }
            document.dispatchEvent(new CustomEvent('scf:queue', { detail: items }));
            return items;
        }).catch(function () {});
    }

    /* --------------------------------- public --------------------------------- */
    window.SCF = {
        uuid: uuid,
        items: all,
        sync: sync,
        discard: function (id) { return del(id).then(render); },
        retry: function (id) {
            return all().then(function (items) {
                var it = items.filter(function (i) { return i.id === id; })[0];
                if (!it) return;
                it.blocked = false; it.errors = null;
                return put(it).then(sync);
            });
        },

        /**
         * Save a case: straight to the server when there is a signal, otherwise kept on the
         * phone. Resolves { saved, queued, errors, redirect }.
         */
        submitCase: function (form, photos, label) {
            var fields = [];
            new FormData(form).forEach(function (v, k) { if (typeof v === 'string' && k !== '_token') fields.push([k, v]); });
            var item = { id: uuid(), kind: 'case', url: form.action, fields: fields, created: Date.now(), label: label || 'Compensation case',
                         photos: (photos || []).map(function (p) { return Object.assign({ uuid: uuid(), done: false }, p); }) };
            var keep = function () {
                return put(item).then(function () { render(); return { queued: true, redirect: cfg.dashboard + '?scf=queued' }; });
            };
            if (!navigator.onLine) return keep();
            return freshToken().then(function (token) {
                return post(item.url, fieldsToForm(item.fields), token).then(function (res) {
                    item.serverId = res.id; item.photosUrl = res.photos; item.serverRef = res.case_ref;
                    if (!item.photos.length) return { saved: true, redirect: res.redirect + '?scf=saved&ref=' + encodeURIComponent(res.case_ref) };
                    // Photos go up after the case; any that do not make it wait in the queue.
                    return put(item).then(function () { return sendPhotos(item, token); }).then(function () { return del(item.id); })
                        .then(function () { return { saved: true, redirect: res.redirect + '?scf=saved&ref=' + encodeURIComponent(res.case_ref) }; },
                              function () { return { saved: true, redirect: res.redirect + '?scf=photos-waiting&ref=' + encodeURIComponent(res.case_ref) }; });
                });
            }).catch(function (e) {
                if (e && e.kind === 'invalid') return { errors: e.detail };
                if (e && e.kind === 'login') return keep().then(function (r) { r.login = true; return r; });
                return keep();
            });
        },

        /** Save one field reading the same way. Resolves { saved, queued, errors }. */
        submitReading: function (url, fields, label) {
            var item = { id: uuid(), kind: 'reading', url: url, fields: fields.concat([['client_uuid', '']]), created: Date.now(), label: label };
            item.fields[item.fields.length - 1][1] = item.id;
            var keep = function () { return put(item).then(function () { render(); return { queued: true }; }); };
            if (!navigator.onLine) return keep();
            return freshToken().then(function (token) {
                return post(url, fieldsToForm(item.fields), token).then(function () { return { saved: true }; });
            }).catch(function (e) {
                if (e && e.kind === 'invalid') return { errors: e.detail };
                return keep();
            });
        }
    };

    window.addEventListener('online', function () { lastState = ''; sync(); });
    window.addEventListener('offline', render);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) sync(); });
    setInterval(sync, 60000);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', sync); else sync();
})();
