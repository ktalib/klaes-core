/**
 * SessionKeepAlive
 * Silently pings the server every INTERVAL ms to prevent session expiry
 * while the user is filling out a long form. Also refreshes the CSRF token
 * on each ping so submissions never see a stale token.
 *
 * Usage:
 *   SessionKeepAlive.start({ url: '/session/keep-alive', interval: 4 * 60 * 1000 });
 *   SessionKeepAlive.stop();
 */
window.SessionKeepAlive = (function () {
    const DEFAULT_INTERVAL = 4 * 60 * 1000; // 4 minutes

    let _timer = null;
    let _url = '/session/keep-alive';
    let _interval = DEFAULT_INTERVAL;
    let _running = false;

    function updateCsrfToken(token) {
        if (!token) return;

        // Meta tag (read by axios / some fetch helpers)
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) meta.setAttribute('content', token);

        // All _token hidden inputs on the page (Laravel forms)
        document.querySelectorAll('input[name="_token"]').forEach(function (el) {
            el.value = token;
        });

        // Expose globally so form-submission.js can read it on retry
        window._freshCsrfToken = token;
    }

    function ping() {
        fetch(_url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(function (res) {
            if (res.status === 401 || res.status === 403) {
                // Session truly dead — stop pinging and notify any listeners
                stop();
                window.dispatchEvent(new CustomEvent('session:expired'));
                return null;
            }
            return res.json();
        })
        .then(function (data) {
            if (data && data.alive) {
                updateCsrfToken(data.csrf_token);
            }
        })
        .catch(function () {
            // Network blip — stay silent, will retry on next interval
        });
    }

    function handleVisibilityChange() {
        if (document.visibilityState === 'visible') {
            // Tab came back into focus — ping immediately then resume timer
            ping();
            if (_running) {
                clearInterval(_timer);
                _timer = setInterval(ping, _interval);
            }
        } else {
            // Tab hidden — pause to save network/battery
            clearInterval(_timer);
        }
    }

    function start(options) {
        options = options || {};
        _url = options.url || _url;
        _interval = options.interval || _interval;

        if (_running) return;
        _running = true;

        // Immediate first ping
        ping();

        _timer = setInterval(ping, _interval);

        document.addEventListener('visibilitychange', handleVisibilityChange);
    }

    function stop() {
        _running = false;
        clearInterval(_timer);
        _timer = null;
        document.removeEventListener('visibilitychange', handleVisibilityChange);
    }

    return { start: start, stop: stop, ping: ping };
})();
