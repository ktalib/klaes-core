{{-- Geo-tagged photos for a Plot Confirmation record: shrunk on the phone, stamped with where and when,
     uploaded after the record is saved (or later, offline). A copy of the SCF case photos picker. --}}
<div class="field" style="margin-top:12px;">
    <label>Photographs</label>
    <input type="file" id="photoInput" accept="image/*" capture="environment" multiple hidden>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        <button type="button" class="btn btn-ghost btn-sm" onclick="pcPhotos.take()"><i class="fas fa-camera"></i> Take photo</button>
        <span class="sub" id="photoHint">Each photo records where and when it was taken. Up to 10.</span>
    </div>
    <div id="photoList" class="thumbs"></div>
</div>
<script>
    window.pcPhotos = (function () {
        var list = [], input = document.getElementById('photoInput'), box = document.getElementById('photoList'), hint = document.getElementById('photoHint');
        var MAX = 1600, LIMIT = 10, IDLE = 'Each photo records where and when it was taken. Up to 10.';
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
                var cell = document.createElement('div');
                var img = document.createElement('img');
                img.src = p.preview; img.alt = 'Photo ' + (i + 1);
                var rm = document.createElement('button');
                rm.type = 'button'; rm.className = 'btn btn-ghost btn-sm'; rm.textContent = 'Remove';
                rm.style.cssText = 'width:100%;margin-top:4px;font-size:12px;padding:4px;';
                rm.onclick = function () { list.splice(i, 1); draw(); };
                cell.append(img, rm);
                box.appendChild(cell);
            });
            hint.textContent = list.length ? list.length + ' photo' + (list.length === 1 ? '' : 's') + ' · uploaded after the record is saved' : IDLE;
        }
        input.addEventListener('change', function () {
            var files = Array.prototype.slice.call(input.files || []).slice(0, LIMIT - list.length);
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
            take: function () { if (list.length >= LIMIT) { hint.textContent = 'At most ' + LIMIT + ' photos.'; return; } input.click(); },
            all: function () {
                return list.map(function (p) { return { blob: p.blob, name: p.name, latitude: p.latitude, longitude: p.longitude, accuracy_m: p.accuracy_m, taken_at: p.taken_at, caption: p.caption }; });
            },
            clear: function () { list = []; draw(); }
        };
    })();
</script>
