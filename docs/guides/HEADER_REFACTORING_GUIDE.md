# Header Refactoring Guide

This guide documents the new header architecture introduced to replace the monolithic inline scripts and styles that previously lived in `resources/views/admin/header.blade.php`. The refactor extracts every interactive feature into discrete, reusable modules and moves custom styling into dedicated assets so the header can be maintained, tested, and extended in a predictable way.

## File Map

| Feature | Asset | Description |
| --- | --- | --- |
| Theme overrides | `public/js/header-tailwind.js` | Registers Tailwind colour extensions for CDN builds. |
| Shared header styles | `public/css/admin-header.css` | Contains brand colour fallbacks, popup layout rules, and helper classes. |
| Push notifications utility | `public/js/push-notification-center.js` | Polling + browser notification engine used by multiple UIs. |
| File tracker dropdown | `public/js/file-tracker-notifications.js` | Renders the bell dropdown UI and wires it to the utility above. |
| Welcome modal | `public/js/welcome-popup.js` | Controls the first-time greeting and session tracking. |
| Auto logout | `public/js/auto-logout.js` | Optional inactivity watchdog with warning modal + forced logout. |
| Profile dropdown | `public/js/user-profile-dropdown.js` | Handles the account menu toggle, focus trapping, and dismissal. |

All assets are referenced directly from the header partial:

```blade
<link rel="stylesheet" href="{{ asset('css/admin-header.css') }}">
<script src="https://cdn.tailwindcss.com"></script>
<script src="{{ asset('js/header-tailwind.js') }}"></script>
<script src="{{ asset('js/push-notification-center.js') }}" defer></script>
<script src="{{ asset('js/file-tracker-notifications.js') }}" defer></script>
<script src="{{ asset('js/welcome-popup.js') }}" defer></script>
<script src="{{ asset('js/auto-logout.js') }}" defer></script>
<script src="{{ asset('js/user-profile-dropdown.js') }}" defer></script>
```

> **Note:** If you compile assets via Laravel Mix/Vite, update the `asset()` helpers to the relevant `mix()` / `vite()` calls and include the files in your pipeline.

## Data Attributes & Configuration

The Blade template now exposes feature configuration through `data-*` attributes so each module can bootstrap itself without inline JavaScript.

### Header Root

```html
<div data-header-root data-auto-logout-enabled="false">...</div>
```

| Attribute | Description |
| --- | --- |
| `data-auto-logout-enabled` | Enables/disables the inactivity watchdog (`auto-logout.js`). Accepts `true` or `false`. |

### Notification Bell

```html
<div
  id="file-tracker-header-notifications"
  data-sound-url="{{ asset('sound/sound.wav') }}"
  data-icon-url="{{ asset('assets/logo/logo.png') }}"
  data-endpoint="{{ route('file-tracker-dashboard.notifications') }}"
  data-fallback-endpoint="{{ url('api/file-tracker-dashboard/notifications') }}"
  data-poll-interval="20000"
>
```

| Attribute | Description |
| --- | --- |
| `data-endpoint` | Primary authenticated endpoint that returns `{ success, data: { items, unreadCount, count } }`. |
| `data-fallback-endpoint` | Secondary endpoint used when the primary returns 401/419 responses. |
| `data-poll-interval` | Polling interval in milliseconds (default `20000`). |
| `data-sound-url` | Audio file that is played whenever new notifications arrive (primed automatically). |
| `data-icon-url` | Icon used for browser push notifications (falls back to each payload item if omitted). |

### Welcome Popup

```html
<div
  id="welcomePopup"
  data-username="{{ $userName }}"
  data-mark-url="{{ route('markWelcomePopupShown') }}"
  data-should-show="{{ session('show_welcome_popup', true) ? 'true' : 'false' }}"
  data-force-show="false"
  data-test-enabled="false"
>
```

| Attribute | Description |
| --- | --- |
| `data-username` | Display name shown inside the greeting. |
| `data-mark-url` | Endpoint that flags the popup as “shown” (expects a JSON `POST`). |
| `data-should-show` | Server-side gate that determines whether the popup launches automatically. |
| `data-force-show` | Forces the popup to appear regardless of the stored state (handy for QA). |
| `data-test-enabled` | Enables “always show” mode for local smoke tests. |

### Profile Dropdown

The profile dropdown only requires the `data-user-profile-dropdown`, `data-dropdown-toggle`, and `data-dropdown-menu` attributes. The script handles toggling, keyboard escape, and outside clicks.

## Module APIs

### `PushNotificationCenter`

Global utility available as `window.PushNotificationCenter`.

```js
const center = window.PushNotificationCenter.create({
  endpoints: ['/file-tracker-dashboard/notifications'],
  pollInterval: 15000,
  soundUrl: '/sound/notify.wav',
  iconUrl: '/assets/logo/logo.png',
  fetchOptions: { credentials: 'same-origin' },
});

center
  .on('update', ({ items, unreadCount, totalCount, newItems }) => {
    console.log('notifications', items, unreadCount, totalCount, newItems);
  })
  .on('error', (error) => {
    console.error('Notification error', error);
  })
  .start();
```

**Options**

| Key | Type | Default | Description |
| --- | --- | --- | --- |
| `endpoints` | `string[]` | `[]` | Ordered list of endpoints to query. Auth errors trigger a fallback to the next endpoint. |
| `pollInterval` | `number` | `20000` | Interval (ms) between automatic polls. |
| `fetchOptions` | `object` | `{ credentials: 'same-origin' }` | Extra `fetch` options (headers, credentials, etc.). |
| `soundUrl` | `string` | `null` | Audio file used for in-app alerts. Autoplay is primed on first user interaction. |
| `iconUrl` | `string` | `null` | Icon/badge used for browser notifications. |

**Events**

| Event | Payload |
| --- | --- |
| `update` | `{ items, unreadCount, totalCount, newItems, raw }` |
| `error` | `Error` object with `status` / `isAuthError` hints. |

Available instance methods: `start()`, `stop()`, `refresh()`, `requestBrowserPermission()`.

### `file-tracker-notifications.js`

This module wires the header bell to `PushNotificationCenter`. It automatically:

1. Reads configuration from the bell's `data-*` attributes.
2. Renders/hides loading, empty, and error states.
3. Updates counters & badges.
4. Handles dropdown toggling, outside-click dismissal, and visibility-change refreshes.
5. Requests browser notification permission when the panel opens.

No manual API is required—ensure the markup matches the provided partial and the script will attach itself on `DOMContentLoaded`.

#### Reusing the notification center on another page

To surface live file-tracker notifications outside the header (for example, on a dashboard card), follow the same pattern:

1. **Add markup with the required IDs/data attributes:**
   ```blade
   <section
     id="dashboard-notification-widget"
     data-endpoint="{{ route('file-tracker-dashboard.notifications') }}"
     data-fallback-endpoint="{{ url('api/file-tracker-dashboard/notifications') }}"
     data-sound-url="{{ asset('sound/sound.wav') }}"
     data-icon-url="{{ asset('assets/logo/logo.png') }}"
     data-poll-interval="30000"
   >
     <header class="flex items-center justify-between">
       <h3 class="text-sm font-semibold">Recent Alerts</h3>
       <span id="dashboard-notification-count" class="text-xs text-gray-500">0</span>
     </header>
     <div id="dashboard-notification-loading" class="py-6 text-center text-gray-500">Loading…</div>
     <ul id="dashboard-notification-list" class="hidden divide-y divide-gray-200"></ul>
     <p id="dashboard-notification-empty" class="hidden py-6 text-center text-gray-500">No alerts.</p>
     <p id="dashboard-notification-error" class="hidden py-6 text-center text-red-600"></p>
   </section>
   ```

2. **Instantiate the utility (if you don’t need the bell UI):**
   ```js
   document.addEventListener('DOMContentLoaded', () => {
     const widget = document.querySelector('#dashboard-notification-widget');
     if (!widget || !window.PushNotificationCenter) {
       return;
     }

     const list = widget.querySelector('#dashboard-notification-list');
     const loading = widget.querySelector('#dashboard-notification-loading');
     const empty = widget.querySelector('#dashboard-notification-empty');
     const error = widget.querySelector('#dashboard-notification-error');
     const count = widget.querySelector('#dashboard-notification-count');

     const center = window.PushNotificationCenter.create({
       endpoints: [
         widget.dataset.endpoint,
         widget.dataset.fallbackEndpoint,
       ].filter(Boolean),
       pollInterval: Number(widget.dataset.pollInterval || 30000),
       soundUrl: widget.dataset.soundUrl,
       iconUrl: widget.dataset.iconUrl,
     });

     const render = (items) => {
       if (!items.length) {
         list.classList.add('hidden');
         empty.classList.remove('hidden');
         return;
       }
       empty.classList.add('hidden');
       list.classList.remove('hidden');
       list.innerHTML = items
         .map(
           (item) => `
             <li class="p-3">
               <p class="text-sm font-semibold">${item.title || 'Update'}</p>
               <p class="text-xs text-gray-500 mt-1">${item.body || ''}</p>
             </li>`
         )
         .join('');
     };

     center
       .on('update', ({ items, unreadCount }) => {
         loading.classList.add('hidden');
         error.classList.add('hidden');
         count.textContent = unreadCount;
         render(items);
       })
       .on('error', (err) => {
         loading.classList.add('hidden');
         error.textContent = err?.message || 'Unable to load alerts.';
         error.classList.remove('hidden');
       })
       .start();
   });
   ```

This approach lets any page opt into live notifications without duplicating the dropdown component—simply provide the endpoints, render the list however you like, and reuse the `PushNotificationCenter` event stream.

### `welcome-popup.js`

Two entry points are exposed:

- `WelcomePopup.init(options)` – invoked automatically with attributes from `#welcomePopup`.
- `WelcomePopup.show()` / `WelcomePopup.hide()` – call manually if you need to override behaviour.

**Options**

| Key | Description |
| --- | --- |
| `selector` | CSS selector for the popup root (`#welcomePopup`). |
| `username`, `markAsShownUrl`, `shouldShow`, `forceShow`, `testEnabled` | Values taken from the DOM; can be overridden manually. |

### `auto-logout.js`

Bootstraps automatically using `data-header-root`. To enable the watchdog globally set `data-auto-logout-enabled="true"` (or update the config helper). You can also initialise it manually:

```js
window.AutoLogout.init({
  enabled: true,
  inactivityLimit: 5 * 60 * 1000, // 5 minutes
  warningOffset: 45 * 1000,
});
```

### `user-profile-dropdown.js`

Automatically binds to `[data-user-profile-dropdown]`. To reuse the dropdown logic elsewhere, replicate the markup structure (wrapper with `data-user-profile-dropdown`, button with `data-dropdown-toggle`, menu with `data-dropdown-menu`).

## Integration Steps

1. **Include assets** – ensure `admin-header.css` and every JS module are published (via Mix/Vite or direct `asset()` references).
2. **Update markup** – copy the new Blade structure for the header, paying attention to `data-*` attributes and IDs (`#file-tracker-header-notifications`, `#header-notification-list`, etc.).
3. **Expose backend config** – provide route URLs and feature flags through the Blade attributes. Examples:
   ```blade
   data-endpoint="{{ route('file-tracker-dashboard.notifications') }}"
   data-auto-logout-enabled="{{ config('session.auto_logout_enabled', false) ? 'true' : 'false' }}"
   ```
4. **Verify assets load once** – if the header partial appears on every page, the included scripts will only run where relevant elements exist.

## Troubleshooting

| Symptom | Fix |
| --- | --- |
| Notifications never load | Confirm the endpoints return JSON with `{ success: true, data: { items: [] } }`. Check the console for auth errors (401/419) and ensure the fallback endpoint is accessible. |
| No notification sound | Browser autoplay policies require a user gesture. Click anywhere on the page once to prime audio. |
| Browser push blocked | Call `notificationCenter.requestBrowserPermission()` manually or ask the user to allow notifications. |
| Welcome popup never appears | Ensure `data-should-show="true"` or set `data-force-show="true"` for testing. |
| Auto logout never triggers | Set `data-auto-logout-enabled="true"` on `[data-header-root]` and confirm the inactivity limit is sensible. |
| Dropdowns stay open | Check that the HTML includes the required `data-*` hooks (`data-notification-toggle`, `data-dropdown-toggle`, etc.). |

## Best Practices

- **Keep datasets authoritative.** Modules read configuration exclusively from `data-*` attributes. Update those values instead of editing the scripts.
- **Use the shared utility.** If you need notifications elsewhere, call `PushNotificationCenter.create()` instead of copy/pasting the bell logic.
- **Bundle via Mix/Vite when possible.** Direct `asset()` references work immediately, but bundling gives you cache busting and minification.
- **Prime browser permissions gracefully.** The notification module only requests permission once the user interacts with the bell so they’re not spammed unexpectedly.
- **Document per-environment overrides.** E.g. set `data-force-show="true"` only in staging to avoid surprising production users.

With these modules in place the header is now portable, testable, and ready for additional features without reintroducing inline scripts.
