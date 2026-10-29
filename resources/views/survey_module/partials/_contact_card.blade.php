{{-- Outreach (contact) card for Survey Department staff (user, 2026-10-28). Copied from
     MLS File Number Commissioning (generate_fileno/mlsfno + mls_js), not shared with it.
     Opened by any [data-sv-contact] element carrying data-phone / data-name / data-email /
     data-ref. A centred card rather than a popover anchored to the cell: the table
     scrolls both ways. SMS goes out from the server (file-numbers.send-sms) under the
     Ministry sender ID and is logged with every other message.
     Used by Surveyors and Beneficiaries; $contactWho names the person (default "Surveyor"). --}}
@php $contactWho = $contactWho ?? 'Surveyor'; @endphp
<div id="svContactModal" class="fixed inset-0 hidden" style="z-index:10060;" role="dialog" aria-modal="true" aria-labelledby="svContactTitle">
    <div class="absolute inset-0 bg-black/50" onclick="svContact.close()"></div>
    <div class="relative mx-auto my-32 w-[92%] max-w-sm rounded-lg bg-white shadow-xl">
        <div class="flex items-start justify-between gap-3 border-b border-gray-200 px-5 py-3">
            <div class="min-w-0">
                <h3 id="svContactTitle" class="text-sm font-bold text-gray-900">Contact {{ $contactWho }}</h3>
                <p id="svContactSubtitle" class="truncate text-xs text-gray-500"></p>
            </div>
            <button type="button" onclick="svContact.close()" aria-label="Close"
                    class="rounded-md p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>
        <div id="svContactBody" class="grid grid-cols-2 gap-3 p-5"></div>
    </div>
</div>

<div id="svSmsModal" class="fixed inset-0 hidden" style="z-index:10070;" role="dialog" aria-modal="true">
    <div class="absolute inset-0 bg-black/50" onclick="svContact.closeSms()"></div>
    <div class="relative mx-auto my-24 w-[92%] max-w-md rounded-lg bg-white shadow-xl">
        <div class="flex items-start justify-between gap-3 border-b border-gray-200 px-5 py-3">
            <div class="min-w-0">
                <h3 class="text-sm font-bold text-gray-900">Send SMS</h3>
                <p id="svSmsSubtitle" class="truncate text-xs text-gray-500"></p>
            </div>
            <button type="button" onclick="svContact.closeSms()" aria-label="Close"
                    class="rounded-md p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>
        <div class="space-y-3 px-5 py-4">
            <div>
                <label for="svSmsText" class="mb-1 block text-xs font-medium text-gray-600">Message</label>
                <textarea id="svSmsText" rows="5" maxlength="480" placeholder="Type the message to send to the {{ strtolower($contactWho) }}..."
                          oninput="svContact.count()"
                          class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-transparent focus:outline-none focus:ring-2 focus:ring-sky-500"></textarea>
                {{-- 160 characters to a page; each extra page bills again. --}}
                <div class="mt-1 flex items-center justify-between text-[11px] text-gray-500">
                    <span id="svSmsCount">0 characters &middot; 0 pages</span>
                    <span class="text-gray-400">Sent as KANOMLPP</span>
                </div>
            </div>
            <div id="svSmsNotice" class="hidden rounded-md border px-3 py-2 text-xs"></div>
        </div>
        <div class="flex items-center justify-end gap-2 border-t border-gray-200 px-5 py-3">
            <button type="button" onclick="svContact.closeSms()"
                    class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-600 transition-colors hover:bg-gray-50">Cancel</button>
            <button type="button" id="svSmsSend" onclick="svContact.send()" disabled
                    class="inline-flex items-center gap-1.5 rounded-md bg-sky-600 px-4 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-sky-700 disabled:cursor-not-allowed disabled:bg-gray-300">
                <i data-lucide="send" class="h-3.5 w-3.5"></i><span>Send SMS</span>
            </button>
        </div>
    </div>
</div>

<script>
    var svContact = (function () {
        var target = null;
        var esc = function (v) {
            return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        };
        var icons = function () { if (window.lucide && typeof window.lucide.createIcons === 'function') window.lucide.createIcons(); };

        // Local 0XXXXXXXXXX numbers need the country code for wa.me; international ones are left alone.
        function waNumber(raw) {
            var d = String(raw || '').replace(/\D/g, '');
            if (!d) return '';
            if (d.indexOf('234') === 0) return d;
            if (d.indexOf('0') === 0) return '234' + d.slice(1);
            return d;
        }

        // Every Nigerian mobile starts 070/080/081/090/091. Warns, never blocks.
        function looksMobile(raw) {
            var d = String(raw || '').replace(/\D/g, '');
            if (d.indexOf('234') === 0) d = d.slice(3); else if (d.indexOf('0') === 0) d = d.slice(1);
            return d.length === 10 && /^[789]/.test(d);
        }

        function open(phone, name, email, ref) {
            phone = String(phone || '').trim();
            if (!phone) return;
            email = String(email || '').trim();
            var who = [name, ref, phone].filter(Boolean).join(' · ');
            target = { phone: phone, who: who };
            document.getElementById('svContactSubtitle').textContent = who;

            var tile = function (inner, href, tone, newTab) {
                return '<a href="' + esc(href) + '"' + (newTab ? ' target="_blank" rel="noopener"' : '')
                    + ' class="flex flex-col items-center justify-center gap-1.5 rounded-lg border ' + tone + ' px-3 py-4 text-xs font-semibold transition-colors">' + inner + '</a>';
            };
            var off = function (inner, title) {
                return '<span title="' + esc(title) + '" class="flex cursor-not-allowed flex-col items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-3 py-4 text-xs font-semibold text-gray-300">' + inner + '</span>';
            };
            var label = function (icon, text) { return '<i data-lucide="' + icon + '" class="h-5 w-5"></i><span>' + text + '</span>'; };
            var wa = waNumber(phone);

            document.getElementById('svContactBody').innerHTML = [
                tile(label('phone', 'Call'), 'tel:' + phone.replace(/[^\d+]/g, ''), 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-600 hover:text-white hover:border-emerald-600'),
                '<button type="button" onclick="svContact.openSms()" class="flex flex-col items-center justify-center gap-1.5 rounded-lg border border-sky-200 bg-sky-50 text-sky-700 hover:bg-sky-600 hover:text-white hover:border-sky-600 px-3 py-4 text-xs font-semibold transition-colors">' + label('message-square', 'SMS') + '</button>',
                wa ? tile(label('message-circle', 'WhatsApp'), 'https://wa.me/' + wa, 'border-green-200 bg-green-50 text-green-700 hover:bg-green-600 hover:text-white hover:border-green-600', true)
                   : off(label('message-circle', 'WhatsApp'), 'Not a usable WhatsApp number'),
                email ? tile(label('mail', 'Email'), 'mailto:' + email, 'border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-600 hover:text-white hover:border-indigo-600')
                      : off(label('mail', 'Email'), @json('No e-mail address on record for this ' . strtolower($contactWho))),
            ].join('');

            document.getElementById('svContactModal').classList.remove('hidden');
            icons();
        }

        function close() { document.getElementById('svContactModal').classList.add('hidden'); }

        function notice(text, tone) {
            var box = document.getElementById('svSmsNotice');
            if (!text) { box.classList.add('hidden'); box.textContent = ''; return; }
            var tones = { error: 'border-red-200 bg-red-50 text-red-700', warn: 'border-amber-200 bg-amber-50 text-amber-800' };
            box.className = 'rounded-md border px-3 py-2 text-xs ' + (tones[tone] || tones.warn);
            box.textContent = text;
        }

        function count() {
            var field = document.getElementById('svSmsText'), len = field.value.length, pages = len === 0 ? 0 : Math.ceil(len / 160);
            document.getElementById('svSmsCount').textContent = len + ' character' + (len === 1 ? '' : 's') + ' · ' + pages + ' page' + (pages === 1 ? '' : 's');
            document.getElementById('svSmsSend').disabled = field.value.trim() === '';
        }

        // The compose box replaces the card rather than stacking two dimmed overlays.
        function openSms() {
            if (!target) return;
            close();
            var field = document.getElementById('svSmsText');
            field.value = ''; field.disabled = false;
            document.getElementById('svSmsSubtitle').textContent = target.who;
            notice(looksMobile(target.phone) ? '' : target.phone + ' does not look like a Nigerian mobile number. The gateway will accept and bill it, but nothing will arrive. Check the number before sending.', 'warn');
            count();
            document.getElementById('svSmsModal').classList.remove('hidden');
            icons();
            setTimeout(function () { field.focus(); }, 50);
        }

        function closeSms() { document.getElementById('svSmsModal').classList.add('hidden'); }

        async function send() {
            var field = document.getElementById('svSmsText'), btn = document.getElementById('svSmsSend');
            var message = field.value.trim();
            if (!target || !message) { notice('Type a message before sending.', 'error'); return; }
            // Locked while in flight: a second click would send, and bill, the message twice.
            var original = btn.innerHTML;
            btn.disabled = true; field.disabled = true; btn.innerHTML = '<span>Sending...</span>';
            notice('');
            try {
                var res = await fetch(@json(route('file-numbers.send-sms', [], false)), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                               'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '' },
                    body: JSON.stringify({ phone: target.phone, message: message, file_number: null })
                });
                var data = await res.json().catch(function () { return {}; });
                if (data.success) {
                    closeSms();
                    if (window.Swal) Swal.fire({ icon: 'success', title: 'Message sent', text: data.message || 'Accepted by the gateway.', confirmButtonColor: '#0284c7' });
                    return;
                }
                notice(data.message || 'The message could not be sent.', 'error');
            } catch (e) {
                notice('The message could not be sent — the server did not answer.', 'error');
            } finally {
                field.disabled = false; btn.innerHTML = original; icons(); count();
            }
        }

        document.addEventListener('click', function (e) {
            var t = e.target.closest('[data-sv-contact]');
            if (!t) return;
            e.preventDefault();
            open(t.dataset.phone, t.dataset.name, t.dataset.email, t.dataset.ref);
        });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { close(); closeSms(); } });

        return { open: open, close: close, openSms: openSms, closeSms: closeSms, send: send, count: count };
    })();
</script>
