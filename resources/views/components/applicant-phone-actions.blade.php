{{--
    Compact contact card shared by the Recommendation and RofO registers.
    A table can scroll in either direction, so this is deliberately centred rather
    than anchored to the phone badge that opened it.
--}}
<div id="applicantPhoneActionsModal" class="fixed inset-0 z-[200] hidden" role="dialog" aria-modal="true" aria-labelledby="applicantPhoneActionsTitle">
    <button type="button" class="absolute inset-0 h-full w-full cursor-default bg-black/50" aria-label="Close contact card" data-applicant-phone-close></button>
    <div class="relative mx-auto my-32 w-[92%] max-w-sm overflow-hidden rounded-xl bg-white shadow-2xl">
        <div class="flex items-start justify-between gap-3 border-b border-slate-200 px-5 py-4">
            <div class="min-w-0">
                <h3 id="applicantPhoneActionsTitle" class="text-sm font-bold text-slate-900">Contact Applicant</h3>
                <p id="applicantPhoneActionsSubtitle" class="truncate text-xs text-slate-500"></p>
            </div>
            <button type="button" data-applicant-phone-close class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Close">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>
        <div class="grid grid-cols-2 gap-3 p-5">
            <a id="applicantPhoneCall" class="order-1 flex flex-col items-center justify-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-4 text-xs font-semibold text-emerald-700 transition hover:border-emerald-600 hover:bg-emerald-600 hover:text-white">
                <i data-lucide="phone" class="h-5 w-5"></i><span>Call</span>
            </a>
            <button type="button" id="applicantPhoneSms" class="order-2 flex flex-col items-center justify-center gap-1.5 rounded-lg border border-sky-200 bg-sky-50 px-3 py-4 text-xs font-semibold text-sky-700 transition hover:border-sky-600 hover:bg-sky-600 hover:text-white">
                <i data-lucide="message-square" class="h-5 w-5"></i><span>SMS</span>
            </button>
            <a id="applicantPhoneWhatsapp" target="_blank" rel="noopener" class="order-3 flex flex-col items-center justify-center gap-1.5 rounded-lg border border-green-200 bg-green-50 px-3 py-4 text-xs font-semibold text-green-700 transition hover:border-green-600 hover:bg-green-600 hover:text-white">
                <i data-lucide="message-circle" class="h-5 w-5"></i><span>WhatsApp</span>
            </a>
            <span class="order-4 flex cursor-not-allowed flex-col items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-3 py-4 text-xs font-semibold text-gray-300" title="No email address is available for this recommendation">
                <i data-lucide="mail" class="h-5 w-5"></i><span>Email</span>
            </span>
        </div>
    </div>
</div>

<div id="applicantSmsComposeModal" class="fixed inset-0 z-[210] hidden" role="dialog" aria-modal="true">
    <button type="button" class="absolute inset-0 h-full w-full cursor-default bg-black/50" data-applicant-sms-close></button>
    <div class="relative mx-auto my-24 w-[92%] max-w-md rounded-xl bg-white shadow-2xl"><div class="flex justify-between border-b px-5 py-3"><div><h3 class="text-sm font-bold">Send SMS</h3><p id="applicantSmsSubtitle" class="text-xs text-slate-500"></p></div><button type="button" data-applicant-sms-close>×</button></div><div class="p-5"><textarea id="applicantSmsText" rows="5" maxlength="480" class="w-full rounded-md border px-3 py-2 text-sm"></textarea></div><div class="flex justify-end gap-2 border-t px-5 py-3"><button type="button" data-applicant-sms-close class="rounded border px-3 py-1.5 text-xs">Cancel</button><button type="button" id="applicantSmsSend" class="rounded bg-sky-600 px-4 py-1.5 text-xs font-semibold text-white">Send SMS</button></div></div>
</div>

<script>
(() => {
    let smsTarget = null;
    const esc = (value) => String(value || '').replace(/[&<>'"]/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#39;', '"':'&quot;' }[c]));
    const waNumber = (value) => {
        const digits = String(value || '').replace(/\D/g, '');
        if (!digits) return '';
        return digits.startsWith('234') ? digits : (digits.startsWith('0') ? '234' + digits.slice(1) : digits);
    };
    const open = (phone, name, fileNumber) => {
        const modal = document.getElementById('applicantPhoneActionsModal');
        if (!modal || !phone) return;
        const dial = String(phone).replace(/[^\d+]/g, '');
        document.getElementById('applicantPhoneActionsSubtitle').textContent = [name, fileNumber, phone].filter(Boolean).join(' · ');
        document.getElementById('applicantPhoneCall').href = 'tel:' + dial;
        document.getElementById('applicantPhoneWhatsapp').href = 'https://wa.me/' + waNumber(phone);
        smsTarget = { phone, name, fileNumber };
        modal.classList.remove('hidden');
        if (window.lucide) window.lucide.createIcons();
    };
    const close = () => document.getElementById('applicantPhoneActionsModal')?.classList.add('hidden');

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-applicant-phone-action]');
        if (trigger) {
            event.preventDefault();
            open(trigger.dataset.phone || '', trigger.dataset.applicantName || '', trigger.dataset.fileNumber || '');
            return;
        }
        if (event.target.closest('[data-applicant-phone-close]')) close();
        if (event.target.closest('[data-applicant-sms-close]')) document.getElementById('applicantSmsComposeModal')?.classList.add('hidden');
    });

    document.getElementById('applicantPhoneSms')?.addEventListener('click', () => {
        if (!smsTarget) return;
        close();
        const modal = document.getElementById('applicantSmsComposeModal');
        const field = document.getElementById('applicantSmsText');
        document.getElementById('applicantSmsSubtitle').textContent = [smsTarget.name, smsTarget.fileNumber, smsTarget.phone].filter(Boolean).join(' · ');
        field.value = '';
        modal.classList.remove('hidden');
        fetch('{{ route('file-numbers.contact-sms-template', [], false) }}?file_number=' + encodeURIComponent(smsTarget.fileNumber || '') + '&application_type=Land%20Recommendation')
            .then(r => r.json()).then(d => { if (d && d.success) field.value = d.message || ''; }).catch(() => {});
    });
    document.getElementById('applicantSmsSend')?.addEventListener('click', async () => {
        const field = document.getElementById('applicantSmsText'); if (!smsTarget || !field.value.trim()) return;
        const btn = document.getElementById('applicantSmsSend'); btn.disabled = true;
        try { const r = await fetch('{{ route('file-numbers.send-sms', [], false) }}', {method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content || ''},body:JSON.stringify({phone:smsTarget.phone,message:field.value.trim(),file_number:smsTarget.fileNumber})}); const d=await r.json(); if(!d.success) throw new Error(d.message); document.getElementById('applicantSmsComposeModal').classList.add('hidden'); if(window.Swal) Swal.fire('Message sent',d.message || 'Accepted by the gateway.','success'); } catch(e) { if(window.Swal) Swal.fire('Unable to send',e.message || 'SMS failed.','error'); } finally { btn.disabled=false; }
    });

    // Used by rows loaded into an expanded batch after the page has rendered.
    window.applicantPhoneActionButtonHtml = (phone, name, fileNumber) => {
        if (!String(phone || '').trim()) return '';
        return '<button type="button" data-applicant-phone-action data-phone="' + esc(phone)
            + '" data-applicant-name="' + esc(name) + '" data-file-number="' + esc(fileNumber)
            + '" class="mt-1 inline-flex items-center gap-1 rounded-md border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 transition hover:border-emerald-600 hover:bg-emerald-600 hover:text-white">'
            + '<i data-lucide="phone" class="h-3 w-3"></i><span>' + esc(phone) + '</span></button>';
    };
})();
</script>
