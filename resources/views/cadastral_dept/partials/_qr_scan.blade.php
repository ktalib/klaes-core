{{--
    Camera QR scanner for the Cadastral Dept (scan-to-register and accepting files).
    Copied from special_assignment/partials/qr_scan (clone per module, not shared).

    window.CdQrScan.open(text => …) opens it and hands back the decoded text unchanged;
    the server (FileQrResolver) works out which file it names. Live camera scanning needs
    https or localhost; on plain http "Scan from a photo" is offered instead.
    A USB / Bluetooth scanner needs none of this: it types into the scan box.
    Library: public/js/vendor/html5-qrcode.min.js (html5-qrcode 2.3.8, Apache-2.0).
--}}
<div id="cd-qr-modal" style="display:none;position:fixed;inset:0;z-index:15000;background:rgba(0,0,0,.6);align-items:center;justify-content:center;padding:16px;">
    <div style="background:#fff;color:#111;border-radius:14px;width:100%;max-width:420px;overflow:hidden;box-shadow:0 12px 40px rgba(0,0,0,.35);font-family:inherit;">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid #eee;">
            <strong style="font-size:14px;">Scan the file's QR code</strong>
            <button type="button" id="cd-qr-close" aria-label="Close" style="background:none;border:0;font-size:22px;line-height:1;color:#888;cursor:pointer;">&times;</button>
        </div>
        <div style="padding:14px 16px;">
            <div id="cd-qr-reader" style="width:100%;min-height:40px;border-radius:10px;overflow:hidden;background:#000;"></div>
            <p id="cd-qr-status" style="font-size:12px;color:#666;margin:10px 0 0;">Starting the camera…</p>
            <div style="display:flex;gap:8px;margin-top:12px;">
                <label style="flex:1;text-align:center;padding:9px 10px;border:1px solid #ddd;border-radius:8px;font-size:13px;cursor:pointer;">
                    Scan from a photo
                    <input type="file" id="cd-qr-file" accept="image/*" capture="environment" style="display:none;">
                </label>
                <button type="button" id="cd-qr-cancel" style="flex:1;padding:9px 10px;border:1px solid #ddd;border-radius:8px;background:#f7f7f7;font-size:13px;cursor:pointer;">Cancel</button>
            </div>
        </div>
    </div>
</div>
<div id="cd-qr-file-reader" style="display:none;"></div>

<script src="{{ asset('js/vendor/html5-qrcode.min.js') }}"></script>
<script>
(function () {
    if (window.CdQrScan) return;
    const modal  = document.getElementById('cd-qr-modal');
    const status = document.getElementById('cd-qr-status');
    const reader = document.getElementById('cd-qr-reader');
    const Lib    = () => window.__Html5QrcodeLibrary__ || {};
    let scanner = null, done = null;

    const say = (msg, tone) => { status.textContent = msg; status.style.color = tone === 'err' ? '#b91c1c' : (tone === 'ok' ? '#15803d' : '#666'); };

    async function stopCamera() {
        if (scanner) {
            try { if (scanner.isScanning) await scanner.stop(); } catch (e) { /* already stopped */ }
            try { scanner.clear(); } catch (e) { /* nothing to clear */ }
            scanner = null;
        }
    }

    async function close() {
        await stopCamera();
        modal.style.display = 'none';
        done = null;
    }

    function finish(text) {
        const cb = done;
        close().then(() => { if (cb) cb(String(text || '').trim()); });
    }

    async function startCamera() {
        const Html5Qrcode = Lib().Html5Qrcode;
        if (!Html5Qrcode) { reader.style.display = 'none'; say('The QR scanner did not load. Type the file number or tracking ID in the scan box instead.', 'err'); return; }
        if (!window.isSecureContext || !navigator.mediaDevices) {
            reader.style.display = 'none';
            say('Live scanning needs a secure (https) connection. Tap “Scan from a photo” and take a picture of the QR code.');
            return;
        }
        reader.style.display = '';
        scanner = new Html5Qrcode('cd-qr-reader');
        try {
            await scanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 220, height: 220 } },
                text => finish(text), () => {});
            say('Point the camera at the QR code on the tracking sheet or label.');
        } catch (e) {
            reader.style.display = 'none';
            say('Could not open the camera (' + (e && e.message ? e.message : e) + '). Use “Scan from a photo”.', 'err');
            scanner = null;
        }
    }

    document.getElementById('cd-qr-file').addEventListener('change', async function () {
        const file = this.files && this.files[0];
        this.value = '';
        if (!file) return;
        const Html5Qrcode = Lib().Html5Qrcode;
        if (!Html5Qrcode) { say('The QR scanner did not load. Type the file number instead.', 'err'); return; }
        await stopCamera();
        say('Reading the QR code…');
        try {
            const text = await new Html5Qrcode('cd-qr-file-reader').scanFile(file, false);
            finish(text);
        } catch (e) {
            say('No QR code found in that photo. Hold the phone closer, keep it steady, and try again.', 'err');
        }
    });

    document.getElementById('cd-qr-close').addEventListener('click', close);
    document.getElementById('cd-qr-cancel').addEventListener('click', close);

    window.CdQrScan = {
        open(onResult) {
            done = onResult;
            modal.style.display = 'flex';
            say('Starting the camera…');
            startCamera();
        },
    };
})();
</script>
