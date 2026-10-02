@extends('layouts.app')

@section('page-title', 'Notification Center')

 @section('content')
<link rel="stylesheet" href="{{ asset('css/notification-center.css') }}">
 


  <div class="flex-1 overflow-auto bg-gray-50">
    @include('admin.header')
    <div
      id="notification-center-app"
      class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8"
      data-list-endpoint="{{ route('notifications.api.list') }}"
      data-mark-read-base="{{ url('/notifications/api') }}"
      data-mark-unread-base="{{ url('/notifications/api') }}"
      data-delete-base="{{ url('/notifications/api') }}"
      data-mark-all-endpoint="{{ route('notifications.api.mark-all-read') }}"
      data-settings-endpoint="{{ route('notifications.api.settings.show') }}"
      data-settings-update-endpoint="{{ route('notifications.api.settings.update') }}"
    >
    <header class="notification-page-header">
      <div>
        <p class="notification-page-eyebrow">Command Center</p>
        <h1>Notifications & Alerts</h1>
        <p>Track updates across EDMS, file tracking, approvals, and assignments in one simplified view.</p>
      </div>
      <div class="notification-page-actions">
        <button class="btn btn-outline btn-sm" data-action="refresh-list">
          <i data-lucide="rotate-cw" class="h-4 w-4"></i>
          Refresh
        </button>
        <button class="btn btn-primary btn-sm" data-action="open-settings">
          <i data-lucide="settings-2" class="h-4 w-4"></i>
          Delivery Preferences
        </button>
      </div>
    </header>

    <section class="grid gap-6 md:grid-cols-3">
      <article class="notification-summary-card">
        <div>
          <p class="card-label">Unread notifications</p>
          <p class="card-value" id="notification-unread-count">{{ number_format($initialUnreadCount) }}</p>
          <p class="card-hint">Alerts waiting for your review</p>
        </div>
        <button class="btn btn-outline btn-sm w-full" data-action="mark-all-read">
          Mark everything as read
        </button>
      </article>

      <article class="notification-summary-card">
        <p class="card-label mb-3">Quick filters</p>
        <div class="filter-group">
          <button class="filter-pill active" data-filter="all">
            <i data-lucide="inbox" class="h-4 w-4"></i> All
          </button>
          <button class="filter-pill" data-filter="unread">
            <i data-lucide="dot" class="h-4 w-4"></i> Unread
          </button>
          <button class="filter-pill" data-filter="read">
            <i data-lucide="check-circle-2" class="h-4 w-4"></i> Read
          </button>
        </div>
      </article>

      <article class="notification-summary-card">
        <p class="card-label mb-3">Delivery channels</p>
        <div class="channel-tags">
          <span class="channel-tag">In-app</span>
          <span class="channel-tag">Email</span>
          <span class="channel-tag">Desktop</span>
        </div>
        <p class="card-hint mt-3">Fine-tune how alerts reach you.</p>
      </article>
    </section>

    <section class="notification-panel">
      <div class="notification-panel__header">
        <div class="notification-search">
          <i data-lucide="search" class="h-4 w-4 text-gray-400"></i>
          <input
            type="search"
            placeholder="Search by keyword, file number, or sender..."
            class="notification-search__input"
            data-action="search-input"
          >
        </div>
        <div class="panel-meta">
          <span data-state="status-label">Showing freshest updates</span>
        </div>
      </div>

      <div id="notification-list-container" class="notification-list">
        <div class="notification-empty">
          <i data-lucide="bell-off" class="h-8 w-8 text-gray-400 mb-3"></i>
          <p>Nothing to review right now.</p>
          <p class="text-xs text-gray-500 mt-1">New alerts will land here as soon as they arrive.</p>
        </div>
      </div>

      <div class="notification-pagination" id="notification-pagination"></div>
    </section>
    </div>
    @include('admin.footer')
  </div>

  <aside class="notification-settings-drawer" data-settings-drawer>
    <div class="notification-settings-drawer__content">
      <header class="notification-settings-drawer__header">
        <div>
          <p class="drawer-eyebrow">Delivery Preferences</p>
          <h2>Where should we reach you?</h2>
        </div>
        <button class="drawer-close" data-action="close-settings">
          <i data-lucide="x" class="h-5 w-5"></i>
        </button>
      </header>

      <form id="notification-settings-form" class="notification-settings-form">
        <label class="settings-row">
          <div>
            <p>In-app notifications</p>
            <span>Show alerts directly inside KLAES.</span>
          </div>
          <input type="checkbox" name="enable_in_app" checked>
        </label>

        <label class="settings-row">
          <div>
            <p>Email alerts</p>
            <span>Send critical updates to my inbox.</span>
          </div>
          <input type="checkbox" name="enable_email">
        </label>

        <label class="settings-row">
          <div>
            <p>Desktop push</p>
            <span>Display browser notifications when I’m active.</span>
          </div>
          <input type="checkbox" name="enable_push">
        </label>

        <label class="settings-row">
          <div>
            <p>Sound on new alert</p>
            <span>Play the current notification tone.</span>
          </div>
          <input type="checkbox" name="play_sound" checked>
        </label>

        <label class="settings-row">
          <div>
            <p>Flash on login</p>
            <span>Show bottom-right popups after signing in.</span>
          </div>
          <input type="checkbox" name="flash_on_login" checked>
        </label>

        <div class="drawer-actions">
          <button type="button" class="btn btn-outline" data-action="close-settings">Cancel</button>
          <button type="submit" class="btn btn-primary" data-action="save-settings">Save changes</button>
        </div>
      </form>
    </div>
  </aside>


 
<script>
  window.NotificationCenterConfig = {
    csrf: '{{ csrf_token() }}',
  };
</script>
<script src="{{ asset('js/notification-center.js') }}?v={{ filemtime(public_path('js/notification-center.js')) }}" defer></script>
@endsection