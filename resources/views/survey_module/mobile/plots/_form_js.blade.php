{{-- Shared save for the Plot Confirmation forms: a GPS fix, then the SCF offline queue with the photos.
     pcSave(form, label, validate, done): validate(form) returns an error message or ''. --}}
<script>
    window.pcGps = function (form, fresh) {
        var out = form.querySelector('[data-gps-out]');
        return new Promise(function (resolve) {
            if (!fresh && form.latitude.value) return resolve(true);
            if (!navigator.geolocation) { if (out) out.textContent = 'This phone cannot give a position.'; return resolve(false); }
            if (out) out.textContent = 'Getting the position…';
            navigator.geolocation.getCurrentPosition(function (pos) {
                var c = pos.coords;
                form.latitude.value = c.latitude.toFixed(7);
                form.longitude.value = c.longitude.toFixed(7);
                form.accuracy_m.value = Math.round(c.accuracy);
                if (out) out.textContent = c.latitude.toFixed(6) + ', ' + c.longitude.toFixed(6) + ' (±' + Math.round(c.accuracy) + ' m)';
                resolve(true);
            }, function () { if (out) out.textContent = 'No position: allow location for this app to record it.'; resolve(false); },
               { enableHighAccuracy: true, timeout: 15000, maximumAge: 30000 });
        });
    };
    document.querySelectorAll('[data-gps]').forEach(function (b) {
        b.addEventListener('click', function () { pcGps(b.closest('form'), true); });
    });
    // Two-choice "segmented" radios: mark the chosen one.
    document.querySelectorAll('.seg').forEach(function (seg) {
        var sync = function () { seg.querySelectorAll('label').forEach(function (l) { l.classList.toggle('on', l.querySelector('input').checked); }); };
        seg.addEventListener('change', sync); sync();
    });

    window.pcSave = function (form, label, validate, done) {
        var err = form.querySelector('.err-box'), btn = form.querySelector('[type=submit]');
        var show = function (m) { err.textContent = m; err.hidden = !m; };
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            show('');
            var problem = validate(form);
            if (problem) { show(problem); return; }
            btn.disabled = true;
            pcGps(form, false).then(function () {
                var fields = [];
                new FormData(form).forEach(function (v, k) { if (typeof v === 'string' && k !== '_token') fields.push([k, v]); });
                if (!window.SCF) { form.submit(); return null; }
                return SCF.submit(form.action, fields, label(form), window.pcPhotos ? pcPhotos.all() : []);
            }).then(function (res) {
                btn.disabled = false;
                if (!res) return;
                if (res.errors) { var k = Object.keys(res.errors)[0]; show(res.errors[k][0]); return; }
                done(res);
            });
        });
    };
</script>
