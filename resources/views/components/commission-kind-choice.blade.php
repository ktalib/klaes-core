{{-- "Commission Land File" or "Commission OSS File" (user, 2026-11-19): the commissioning button on Land FC
     and on OSS FC asks first. Land opens the Land FC card, OSS the OSS FC card; when the page is the
     other one it goes there and the card opens on arrival (?commission=1). Without the role for the
     choice: not permitted, contact the administrator.
     Include with $here = 'land' | 'oss' and $openHere = the JS function that opens this page's card. --}}
@php
    $ckUser = auth()->user();
    $ckLand = $ckUser && ($ckUser->isSuperAdmin() || $ckUser->canDo('Land', 'view'));
    $ckOss = $ckUser && ($ckUser->isSuperAdmin() || $ckUser->canDo('Land One Stop Shop', 'view'));
@endphp
<script>
    (function () {
        var cfg = {
            here: @js($here),
            allowed: { land: @js($ckLand), oss: @js($ckOss) },
            urls: {
                land: @js(route('file-numbers.index', ['commission' => 1])),
                oss: @js(route('lands-one-stop-shop.applications.index', ['source' => 'lands-one-stop-shop', 'type' => 'change-of-name', 'record_type' => 'fc', 'commission' => 1])),
            },
        };
        var openHere = function () { if (typeof window[@js($openHere)] === 'function') window[@js($openHere)](); };

        function card(kind, title, sub, icon, tint, ink) {
            var off = !cfg.allowed[kind];
            return '<button type="button" class="ck-choice" data-kind="' + kind + '" style="display:flex;align-items:center;gap:12px;width:100%;text-align:left;'
                + 'padding:14px 16px;margin-top:10px;border:1.5px solid ' + (off ? '#e5e7eb' : ink + '55') + ';border-radius:12px;background:' + (off ? '#f9fafb' : tint) + ';cursor:pointer;'
                + (off ? 'opacity:.7;' : '') + '">'
                + '<i data-lucide="' + icon + '" style="width:26px;height:26px;color:' + (off ? '#9ca3af' : ink) + ';flex-shrink:0;"></i>'
                + '<span><strong style="display:block;font-size:15px;color:' + (off ? '#6b7280' : '#111827') + ';">' + title + '</strong>'
                + '<small style="color:#6b7280;">' + (off ? 'Not permitted for your account' : sub) + '</small></span></button>';
        }

        function go(kind) {
            if (!cfg.allowed[kind]) {
                Swal.fire({ icon: 'error', title: 'Not permitted',
                    text: 'You do not have access to ' + (kind === 'land' ? 'Land File Commissioning' : 'OSS File Commissioning') + '. Contact the administrator.' });
                return;
            }
            if (kind === cfg.here) { Swal.close(); openHere(); return; }
            Swal.close();
            window.location.href = cfg.urls[kind];
        }

        window.askCommissionKind = function () {
            Swal.fire({
                title: 'What are you commissioning?',
                html: card('land', 'Commission Land File', 'Opens Land File Commissioning (Land FC)', 'folder-plus', '#eff6ff', '#1d4ed8')
                    + card('oss', 'Commission OSS File', 'Opens OSS File Commissioning (OSS FC)', 'store', '#fff7ed', '#c2410c'),
                showConfirmButton: false, showCancelButton: true, cancelButtonText: 'Cancel', width: 460,
                didOpen: function (popup) {
                    if (window.lucide) window.lucide.createIcons();
                    popup.querySelectorAll('.ck-choice').forEach(function (b) {
                        b.addEventListener('click', function () { go(b.getAttribute('data-kind')); });
                    });
                },
            });
        };

        // Arrived from the other page's choice: open this page's card once, and drop the flag from the URL.
        document.addEventListener('DOMContentLoaded', function () {
            var params = new URLSearchParams(window.location.search);
            if (params.get('commission') !== '1') return;
            params.delete('commission');
            var rest = params.toString();
            history.replaceState(null, '', window.location.pathname + (rest ? '?' + rest : '') + window.location.hash);
            if (cfg.allowed[cfg.here]) setTimeout(openHere, 400);
        });
    })();
</script>
