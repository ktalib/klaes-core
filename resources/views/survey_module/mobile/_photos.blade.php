{{-- Geo-tagged photos of the land and assets (SCF, 2026-10-25). Each photo is shrunk on the phone,
     stamped with where and when it was taken, and uploaded after the case is saved (or later, offline).
     Kept with the case, not in EDMS (user decision). --}}
<div class="section-title"><i class="fas fa-camera"></i> Photos of the land and assets</div>
<div class="field span-2" data-key="photos">
    <input type="file" id="photoInput" accept="image/*" capture="environment" multiple hidden>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <button type="button" class="btn btn-ghost" onclick="scfPhotos.take()"><i class="fas fa-camera"></i> Take photo</button>
        <span class="hint" id="photoHint" style="align-self:center;">Each photo records where and when it was taken.</span>
    </div>
    <div id="photoList" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;margin-top:10px;"></div>
</div>
<script>
    window.scfPhotos = (function () {
        var list = [], input = document.getElementById('photoInput'), box = document.getElementById('photoList'), hint = document.getElementById('photoHint');
        var MAX = 1600;   // longest side after shrinking, in pixels

        function where() {
            return new Promise(function (resolve) {
                if (!navigator.geolocation) return resolve(null);
                navigator.geolocation.getCurrentPosition(function (p) { resolve(p.coords); }, function () { resolve(null); },
                    { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 });
            });
        }
        function shrink(file) {
            return new Promise(function (resolve) {
                var img = new Image(), url = URL.createObjectURL(file);
                img.onload = function () {
                    var s = Math.min(1, MAX / Math.max(img.width, img.height)), c = document.createElement('canvas');
                    c.width = Math.round(img.width * s); c.height = Math.round(img.height * s);
                    c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
                    URL.revokeObjectURL(url);
                    c.toBlob(function (b) { resolve(b || file); }, 'image/jpeg', 0.82);
                };
                img.onerror = function () { URL.revokeObjectURL(url); resolve(file); };
                img.src = url;
            });
        }
        function draw() {
            box.innerHTML = '';
            list.forEach(function (p, i) {
                var card = document.createElement('div');
                card.style.cssText = 'border:1px solid #efedf3;border-radius:12px;overflow:hidden;background:#fff;';
                var img = document.createElement('img');
                img.src = p.preview; img.alt = 'Photo ' + (i + 1);
                img.style.cssText = 'width:100%;height:110px;object-fit:cover;display:block;';
                var meta = document.createElement('div');
                meta.style.cssText = 'padding:6px 8px;font-size:11.5px;color:#52514e;';
                meta.textContent = p.latitude !== null ? p.latitude.toFixed(5) + ', ' + p.longitude.toFixed(5) + (p.accuracy_m ? ' (±' + Math.round(p.accuracy_m) + ' m)' : '') : 'No location';
                var cap = document.createElement('input');
                cap.className = 'input'; cap.placeholder = 'Caption (optional)'; cap.maxLength = 255; cap.value = p.caption || '';
                cap.style.cssText = 'margin:0 8px 6px;width:calc(100% - 16px);font-size:13px;padding:6px 8px;';
                cap.oninput = function () { p.caption = cap.value; };
                var rm = document.createElement('button');
                rm.type = 'button'; rm.className = 'btn btn-ghost'; rm.textContent = 'Remove';
                rm.style.cssText = 'margin:0 8px 8px;font-size:12px;padding:4px 8px;';
                rm.onclick = function () { list.splice(i, 1); draw(); };
                card.append(img, meta, cap, rm);
                box.appendChild(card);
            });
            hint.textContent = list.length ? list.length + ' photo' + (list.length === 1 ? '' : 's') + ' · uploaded with the case' : 'Each photo records where and when it was taken.';
        }
        input.addEventListener('change', function () {
            var files = Array.prototype.slice.call(input.files || []);
            input.value = '';
            if (!files.length) return;
            hint.textContent = 'Getting the location…';
            where().then(function (c) {
                return Promise.all(files.map(function (f) {
                    return shrink(f).then(function (blob) {
                        list.push({ blob: blob, name: (f.name || 'photo').replace(/\.\w+$/, '') + '.jpg', preview: URL.createObjectURL(blob),
                                    latitude: c ? c.latitude : null, longitude: c ? c.longitude : null, accuracy_m: c ? c.accuracy : null,
                                    taken_at: new Date().toISOString(), caption: '' });
                    });
                }));
            }).then(draw);
        });
        return {
            take: function () { input.click(); },
            // What the offline layer stores and uploads (no preview URLs).
            all: function () { return list.map(function (p) { return { blob: p.blob, name: p.name, latitude: p.latitude, longitude: p.longitude, accuracy_m: p.accuracy_m, taken_at: p.taken_at, caption: p.caption }; }); }
        };
    })();
</script>
