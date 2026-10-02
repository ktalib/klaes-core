{{--
    Submits the workflow forms without reloading the page.

    Any POST form inside [data-iw-ajax-root] is sent with fetch and the controller
    answers with JSON ({success, message, redirect}). On success the page's
    content is fetched again and swapped in place (Alpine picks up the new
    markup), keeping the scroll position, and a toast shows the message. A
    redirect to another page is followed. Validation and guard errors are shown
    on the form itself. Forms opt out with data-iw-no-ajax; GET forms and forms
    that open a new tab are left alone. Inline onsubmit confirm() still runs first.
--}}
<div id="iw-toasts" class="iw-toasts" aria-live="polite"></div>

<style>
    .iw-toasts { position: fixed; top: 16px; right: 16px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; width: 380px; max-width: calc(100vw - 32px); pointer-events: none; }
    .iw-toast { pointer-events: auto; display: flex; gap: 10px; align-items: flex-start; padding: 12px 14px; border-radius: 12px; font-size: 13px; line-height: 1.4; box-shadow: 0 10px 25px rgba(17, 24, 39, .15); border: 1px solid; background: #fff; transform: translateY(-6px); opacity: 0; transition: opacity .18s ease, transform .18s ease; }
    .iw-toast.show { transform: translateY(0); opacity: 1; }
    .iw-toast.success { border-color: #bbf7d0; color: #14532d; background: #f0fdf4; }
    .iw-toast.warning { border-color: #fde68a; color: #92400e; background: #fffbeb; }
    .iw-toast.error { border-color: #fecaca; color: #7f1d1d; background: #fef2f2; }
    .iw-toast-icon { flex-shrink: 0; margin-top: 1px; }
    .iw-toast-body { flex: 1; min-width: 0; word-wrap: break-word; }
    .iw-toast-close { flex-shrink: 0; border: 0; background: transparent; color: inherit; opacity: .6; cursor: pointer; font-size: 16px; line-height: 1; padding: 0 2px; }
    .iw-toast-close:hover { opacity: 1; }
    .iw-form-alert { display: flex; gap: 8px; align-items: flex-start; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 8px; padding: 8px 10px; font-size: 13px; }
    .iw-form-alert ul { margin: 0; padding-left: 16px; list-style: disc; }
    .iw-field-invalid { border-color: #f87171 !important; box-shadow: 0 0 0 3px rgba(248, 113, 113, .18); }
    .iw-spin { width: 14px; height: 14px; border-radius: 9999px; border: 2px solid currentColor; border-right-color: transparent; display: inline-block; animation: iw-spin .7s linear infinite; vertical-align: -2px; }
    @keyframes iw-spin { to { transform: rotate(360deg); } }
    [data-iw-ajax-root].iw-refreshing { transition: opacity .15s ease; opacity: .6; pointer-events: none; }
</style>

<script>
    (function () {
        if (window.IwAjax) return;

        const ICONS = {
            success: '<svg class="iw-toast-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>',
            warning: '<svg class="iw-toast-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="m10.3 3.4-8 14A2 2 0 0 0 4 20h16a2 2 0 0 0 1.7-3l-8-14a2 2 0 0 0-3.4 0Z"/></svg>',
            error: '<svg class="iw-toast-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>',
        };

        const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || @js(csrf_token());

        function toast(type, message) {
            const host = document.getElementById('iw-toasts');
            if (!host || !message) return;
            const el = document.createElement('div');
            el.className = 'iw-toast ' + type;
            el.setAttribute('role', type === 'error' ? 'alert' : 'status');
            el.innerHTML = ICONS[type] + '<div class="iw-toast-body">' + escapeHtml(message) + '</div><button type="button" class="iw-toast-close" aria-label="Dismiss">&times;</button>';
            const close = () => { el.classList.remove('show'); setTimeout(() => el.remove(), 200); };
            el.querySelector('.iw-toast-close').addEventListener('click', close);
            host.appendChild(el);
            requestAnimationFrame(() => el.classList.add('show'));
            setTimeout(close, type === 'error' || type === 'warning' ? 9000 : 5000);
        }

        function scrollParent(el) {
            for (let node = el.parentElement; node; node = node.parentElement) {
                const style = getComputedStyle(node);
                if (/(auto|scroll)/.test(style.overflowY) && node.scrollHeight > node.clientHeight) return node;
            }
            return document.scrollingElement || document.documentElement;
        }

        function setBusy(form, submitter, busy) {
            form.dataset.iwBusy = busy ? '1' : '';
            form.querySelectorAll('button, input[type="submit"]').forEach((button) => { button.disabled = busy; });
            const target = submitter && submitter.tagName === 'BUTTON' ? submitter : form.querySelector('button[type="submit"], button:not([type])');
            if (!target) return;
            if (busy) {
                target.dataset.iwLabel = target.innerHTML;
                target.innerHTML = '<span class="iw-spin"></span> Working…';
            } else if (target.dataset.iwLabel !== undefined) {
                target.innerHTML = target.dataset.iwLabel;
                delete target.dataset.iwLabel;
            }
        }

        function clearErrors(form) {
            form.querySelectorAll('.iw-form-alert[data-iw-generated]').forEach((el) => el.remove());
            form.querySelectorAll('.iw-field-invalid').forEach((el) => el.classList.remove('iw-field-invalid'));
        }

        function showErrors(form, message, errors) {
            const messages = errors ? Object.values(errors).flat() : [message];
            if (errors) {
                Object.keys(errors).forEach((name) => {
                    // "bir_documents.executed_instrument" → bir_documents[executed_instrument]
                    const field = name.split('.').map((part, i) => (i ? '[' + part + ']' : part)).join('');
                    form.querySelectorAll('[name="' + CSS.escape(field) + '"], [name="' + CSS.escape(name) + '"]').forEach((el) => el.classList.add('iw-field-invalid'));
                });
            }
            const alert = document.createElement('div');
            alert.className = 'iw-form-alert';
            alert.setAttribute('data-iw-generated', '');
            alert.innerHTML = ICONS.error + (messages.length > 1
                ? '<ul>' + messages.map((m) => '<li>' + escapeHtml(m) + '</li>').join('') + '</ul>'
                : '<div>' + escapeHtml(messages[0]) + '</div>');
            form.insertBefore(alert, form.firstElementChild?.name === '_token' ? form.firstElementChild.nextSibling : form.firstChild);
        }

        /** Fetch the current page again and swap its workflow content in place. */
        async function refresh() {
            const root = document.querySelector('[data-iw-ajax-root]');
            if (!root) { window.location.reload(); return; }

            const scroller = scrollParent(root);
            const top = scroller.scrollTop;
            const openDetails = Array.from(root.querySelectorAll('details[open] > summary')).map((s) => s.textContent.trim());
            root.classList.add('iw-refreshing');

            try {
                const response = await fetch(window.location.href, { headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store', credentials: 'same-origin' });
                const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
                const fresh = doc.querySelector('[data-iw-ajax-root]');
                if (!response.ok || !fresh) { window.location.reload(); return; }

                root.innerHTML = fresh.innerHTML; // Alpine initialises the new markup on its own
                document.querySelectorAll('[data-iw-swap]').forEach((el) => {
                    const replacement = doc.querySelector('[data-iw-swap="' + el.dataset.iwSwap + '"]');
                    if (replacement) el.innerHTML = replacement.innerHTML;
                });
                const header = doc.querySelector('h1.truncate + p.truncate');
                const currentHeader = document.querySelector('h1.truncate + p.truncate');
                if (header && currentHeader) currentHeader.textContent = header.textContent;

                root.querySelectorAll('details > summary').forEach((s) => { if (openDetails.includes(s.textContent.trim())) s.parentElement.open = true; });
                window.lucide && window.lucide.createIcons();
                scroller.scrollTop = top;
            } finally {
                root.classList.remove('iw-refreshing');
            }
        }

        async function submit(form, submitter) {
            const data = new FormData(form);
            if (submitter && submitter.name) data.append(submitter.name, submitter.value);
            // A submit button may send the same form somewhere else with formaction --
            // a Remove button beside a Save button, say. Honour it, or the button
            // silently does what the form's own action does.
            const action = submitter && submitter.hasAttribute('formaction') ? submitter.formAction : form.action;

            clearErrors(form);
            setBusy(form, submitter, true);
            let response, body = {};
            try {
                response = await fetch(action, {
                    method: 'POST',
                    body: data,
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
                });
                body = await response.json().catch(() => ({}));
            } catch (e) {
                setBusy(form, submitter, false);
                toast('error', 'Could not reach the server. Check your connection and try again.');
                return { success: false };
            }

            if (response.status === 419) {
                toast('error', 'Your session has expired. The page will reload.');
                setTimeout(() => window.location.reload(), 1500);
                return { success: false };
            }

            if (response.ok && body.success !== false) {
                const redirect = body.redirect ? new URL(body.redirect, window.location.href) : null;
                if (redirect && (redirect.origin !== window.location.origin || redirect.pathname !== window.location.pathname)) {
                    toast('success', body.message);
                    window.location.href = redirect.href;
                    return body;
                }
                await refresh();
                const toastType = String(body.message || '').startsWith('Alert:') ? 'warning' : 'success';
                toast(toastType, body.message || 'Saved.');
                return body;
            }

            setBusy(form, submitter, false);
            const message = response.status === 403
                ? 'You do not have permission to do this.'
                : (body.errors ? Object.values(body.errors).flat()[0] : (body.message || 'The action could not be completed.'));
            if (form.isConnected) showErrors(form, message, body.errors || null);
            toast('error', message);
            return body;
        }

        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
            if (!form.closest('[data-iw-ajax-root]') || form.hasAttribute('data-iw-no-ajax')) return;
            if ((form.getAttribute('method') || 'get').toLowerCase() !== 'post' || (form.target && form.target !== '_self')) return;
            // A submitter may override the method or the target; leave those to the browser.
            const by = event.submitter;
            if (by && ((by.getAttribute('formmethod') || 'post').toLowerCase() !== 'post' || (by.getAttribute('formtarget') || '_self') !== '_self')) return;

            event.preventDefault();
            if (form.dataset.iwBusy) return;
            submit(form, event.submitter);
        });

        window.IwAjax = { submit, refresh, toast };
    })();
</script>

