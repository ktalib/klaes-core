<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
  @include('phs.partials.favicon')
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>KLAES Enterprise - Organization Settings</title>
  <script>
    (function() {
      const t = localStorage.getItem('phs-theme');
      if (t === 'dark' || (!t && matchMedia('(prefers-color-scheme: dark)').matches))
        document.documentElement.classList.add('dark');
    })();
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>

  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            primary: "#3b82f6",
            "primary-foreground": "#ffffff",
            muted: "#f3f4f6",
            "muted-foreground": "#6b7280",
            border: "#e5e7eb",
            destructive: "#ef4444",
            "destructive-foreground": "#ffffff",
            secondary: "#f1f5f9",
            "secondary-foreground": "#0f172a",
          },
        },
      },
    };

    function phsToggleTheme() {
      const isDark = document.documentElement.classList.toggle('dark');
      localStorage.setItem('phs-theme', isDark ? 'dark' : 'light');
      if (window.lucide) window.lucide.createIcons();
    }
  </script>

  <style>
    html, body { margin: 0; padding: 0; }

    .user-card {
      transition: all 0.3s ease;
    }
    .user-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    }

    .modal-overlay {
      background: rgba(0, 0, 0, 0.5);
      backdrop-filter: blur(4px);
    }

    .slide-in {
      animation: slideIn 0.3s ease-out;
    }

    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .tab-active {
      border-bottom: 2px solid #3b82f6;
      color: #3b82f6;
    }

    .token-usage-bar {
      transition: width 0.5s ease;
    }

    .modal-scrollable {
      max-height: 70vh;
      overflow-y: auto;
    }

    .preview-card {
      transition: all 0.3s ease;
    }
    .preview-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    }

    .table-row {
      transition: all 0.2s ease;
    }
    .table-row:hover {
      background-color: var(--phs-table-hover, #f8fafc);
    }

    .dropzone-active {
      border-color: #3b82f6 !important;
      background-color: #eff6ff !important;
    }
    .dark .dropzone-active {
      background-color: #1e3a5f !important;
    }

    .dropzone {
      transition: all 0.2s ease;
    }

    /* Mobile menu styles */
    .mobile-menu-open {
      max-height: 500px;
      opacity: 1;
      visibility: visible;
    }

    .mobile-menu-closed {
      max-height: 0;
      opacity: 0;
      visibility: hidden;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }
      
      .preview-card-container {
        margin-top: 1rem;
      }
    }

    @media (max-width: 640px) {
      .stats-grid {
        grid-template-columns: 1fr;
      }
      
      .tab-buttons {
        gap: 1rem;
      }
    }
    :root {
      --phs-border: #e5e7eb;
      --phs-table-hover: #f8fafc;
      --phs-preloader-bg: #ffffff;
    }
    .dark {
      --phs-border: #374151;
      --phs-table-hover: #1f2937;
      --phs-preloader-bg: #111827;
    }
  </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 dark:text-gray-100">

  <!-- Preloader -->
  <div id="preloader" style="position:fixed;inset:0;background:var(--phs-preloader-bg,#fff);display:flex;align-items:center;justify-content:center;z-index:9999;">
    <img src="http://app.klaes.ng/storage/upload/logo/klas_logo.gif" alt="Loading..." style="width:200px;height:auto;">
  </div>

  <!-- User Management Main View -->
  <div id="user-management-container" class="min-h-screen bg-gray-50 dark:bg-gray-900 lg:pl-64">
    <aside class="hidden lg:flex fixed inset-y-0 left-0 z-50 w-64 flex-col border-r border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
      <div class="h-16 px-5 flex items-center gap-3 border-b border-gray-100 dark:border-gray-700">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-r from-blue-600 to-purple-600 flex items-center justify-center overflow-hidden shadow-sm flex-shrink-0">
          <img src="{{ asset('assets/logo/phs-light-logo.jpeg') }}" class="w-full h-full object-cover dark:hidden" alt="PHS">
          <img src="{{ asset('assets/logo/phs-dark-logo.jpeg') }}" class="w-full h-full object-cover hidden dark:block" alt="PHS">
        </div>
        <div class="min-w-0">
          <p class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate" id="sidebar-org-name">{{ \Illuminate\Support\Str::title($institution->name) ?: 'Organization' }}</p>
          <p class="text-xs text-gray-500 dark:text-gray-400 truncate">Manage workspace</p>
        </div>
      </div>
      <nav class="flex-1 px-3 py-5 space-y-1">
        <a href="{{ route('phs.dashboard') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100">
          <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
          Dashboard
        </a>
        <div>
          <button type="button"
            onclick="const s=document.getElementById('org-search-submenu');s.classList.toggle('hidden');document.getElementById('org-search-chevron').classList.toggle('rotate-180');window.lucide?.createIcons();"
            class="w-full flex items-center justify-between rounded-xl px-3 py-2.5 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100">
            <span class="flex items-center gap-3">
              <i data-lucide="search" class="w-4 h-4"></i>
              Search
            </span>
            <i data-lucide="chevron-down" id="org-search-chevron" class="w-4 h-4 transition-transform duration-200"></i>
          </button>
          <div id="org-search-submenu" class="hidden pl-4 mt-0.5 space-y-0.5">
            <a href="{{ route('phs.dashboard') }}#search"
              class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100">
              <i data-lucide="search" class="w-3.5 h-3.5"></i>
              Search Now
            </a>
            {{-- Corrections raised against a search result. It belongs under Search
                 rather than with the org-management items: it is about the member's
                 own searches, and it is where a corrected result is collected. --}}
            <button type="button" data-sidebar-tab="editrequests"
              class="w-full flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100">
              <i data-lucide="file-warning" class="w-3.5 h-3.5"></i>
              Edit Requests
              @if (($readyForRerunCount ?? 0) > 0)
                <span class="ml-auto inline-flex items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40 px-2 py-0.5 text-[11px] font-bold text-emerald-700 dark:text-emerald-300">
                  {{ $readyForRerunCount }}
                </span>
              @endif
            </button>
            <a href="{{ route('phs.dashboard') }}"
              class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100">
              <i data-lucide="history" class="w-3.5 h-3.5"></i>
              Search History
            </a>
          </div>
        </div>
        @if($member->isSuperAdmin() ?? false)
        <button type="button" data-sidebar-tab="users" class="w-full flex items-center gap-3 rounded-xl bg-blue-50 dark:bg-blue-900/30 px-3 py-2.5 text-sm font-semibold text-blue-700 dark:text-blue-300">
          <i data-lucide="users" class="w-4 h-4"></i>
          Team Members
        </button>
        <button type="button" data-sidebar-tab="roles" class="w-full flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100">
          <i data-lucide="shield" class="w-4 h-4"></i>
          Roles &amp; Permissions
        </button>
        <button type="button" data-sidebar-tab="activity" class="w-full flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100">
          <i data-lucide="activity" class="w-4 h-4"></i>
          Activity Log
        </button>
        <button type="button" data-sidebar-tab="emails" class="w-full flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100">
          <i data-lucide="mail" class="w-4 h-4"></i>
          Email History
        </button>
        <button type="button" data-sidebar-tab="branding" class="w-full flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100">
          <i data-lucide="palette" class="w-4 h-4"></i>
          Branding
        </button>
        <button type="button" data-sidebar-tab="subscription" class="w-full flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100">
          <i data-lucide="credit-card" class="w-4 h-4"></i>
          Subscription
        </button>
        @endif
      </nav>
      <div class="border-t border-gray-100 dark:border-gray-700 p-4 space-y-3">
        <div class="rounded-xl bg-gray-50 dark:bg-gray-800 px-3 py-3" style="display:none">
          <p class="text-xs text-gray-500 dark:text-gray-400">Org Tokens</p>
          <p class="text-2xl font-bold text-blue-600 dark:text-blue-400" id="sidebar-org-tokens-display">0</p>
        </div>
        <button onclick="phsToggleTheme()" title="Toggle dark mode"
          class="w-full flex items-center justify-center gap-2 rounded-xl border border-gray-200 dark:border-gray-600 px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
          <i data-lucide="sun" class="w-4 h-4 dark:hidden"></i>
          <i data-lucide="moon" class="w-4 h-4 hidden dark:block"></i>
          <span class="dark:hidden">Light Mode</span>
          <span class="hidden dark:block">Dark Mode</span>
        </button>
        <button id="sidebar-org-logout-btn" type="button" class="w-full flex items-center justify-center gap-2 rounded-xl border border-gray-300 dark:border-gray-600 px-3 py-2.5 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">
          <i data-lucide="log-out" class="w-4 h-4"></i>
          Logout
        </button>
      </div>
    </aside>
    <!-- Responsive Header -->
    <div class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 dark:border-gray-700 sticky top-0 z-20" id="org-header">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center py-3 sm:py-0 h-auto sm:h-16 gap-3">
          <div class="flex items-center space-x-3 sm:space-x-4">
            <div id="org-header-logo" class="w-8 h-8 sm:w-10 sm:h-10 bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg flex items-center justify-center shadow-md overflow-hidden">
              <img src="{{ asset('assets/logo/phs-light-logo.jpeg') }}" class="w-full h-full object-cover dark:hidden" alt="PHS">
              <img src="{{ asset('assets/logo/phs-dark-logo.jpeg') }}" class="w-full h-full object-cover hidden dark:block" alt="PHS">
            </div>
            <div>
              <h1 class="text-lg sm:text-xl font-bold text-gray-900 dark:text-gray-100">Organization Settings</h1>
              <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 hidden sm:block" id="org-header-name">
                Manage your organization's branding
              </p>
            </div>
          </div>
          <div class="flex items-center space-x-2 sm:space-x-3">
            <div class="text-right">
              <p class="text-xs text-gray-500 dark:text-gray-400">Org Tokens</p>
              <p class="text-base sm:text-lg font-bold text-blue-600 dark:text-blue-400" id="org-tokens-display">5,000</p>
            </div>
            <!-- Theme toggle -->
            <button onclick="phsToggleTheme()" title="Toggle dark mode"
              class="p-1 sm:p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
              <i data-lucide="sun" class="w-4 h-4 sm:w-5 sm:h-5 text-gray-500 dark:hidden"></i>
              <i data-lucide="moon" class="w-4 h-4 sm:w-5 sm:h-5 text-gray-400 hidden dark:block"></i>
            </button>
            <button id="close-user-management" class="p-1 sm:p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition">
              <i data-lucide="x" class="w-4 h-4 sm:w-5 sm:h-5 text-gray-500 dark:text-gray-400"></i>
            </button>
          </div>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400 pb-3 sm:hidden" id="mobile-org-header-name">
          Manage your organization's branding
        </p>
      </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-8">
      <!-- Organization Info Banner - Responsive -->
      <div
        id="org-banner"
        class="rounded-2xl shadow-sm mb-6 sm:mb-8 h-32 sm:h-40 bg-gradient-to-r from-blue-600 to-purple-600 flex items-center justify-center relative overflow-hidden"
      >
        <p id="banner-text" class="text-white text-lg sm:text-xl font-medium z-10 px-4 text-center">
          Organization Banner
        </p>
      </div>

      <!-- Stats Overview - Responsive Grid -->
      <div class="stats-grid grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-6 mb-6 sm:mb-8">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">Total Users</p>
              <p class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-gray-100" id="total-users">0</p>
            </div>
            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-blue-100 dark:bg-blue-900/30 rounded-2xl flex items-center justify-center">
              <i data-lucide="users" class="w-5 h-5 sm:w-6 sm:h-6 text-blue-600 dark:text-blue-400"></i>
            </div>
          </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">Active Users</p>
              <p class="text-2xl sm:text-3xl font-bold text-green-600 dark:text-green-400" id="active-users">0</p>
            </div>
            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-green-100 dark:bg-green-900/30 rounded-2xl flex items-center justify-center">
              <i data-lucide="user-check" class="w-5 h-5 sm:w-6 sm:h-6 text-green-600 dark:text-green-400"></i>
            </div>
          </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">Super Admins</p>
              <p class="text-2xl sm:text-3xl font-bold text-purple-600 dark:text-purple-400" id="super-admin-count">0</p>
            </div>
            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-purple-100 dark:bg-purple-900/30 rounded-2xl flex items-center justify-center">
              <i data-lucide="crown" class="w-5 h-5 sm:w-6 sm:h-6 text-purple-600 dark:text-purple-400"></i>
            </div>
          </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">Regular Users</p>
              <p class="text-2xl sm:text-3xl font-bold text-orange-600 dark:text-orange-400" id="regular-user-count">0</p>
            </div>
            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-orange-100 dark:bg-orange-900/30 rounded-2xl flex items-center justify-center">
              <i data-lucide="user" class="w-5 h-5 sm:w-6 sm:h-6 text-orange-600 dark:text-orange-400"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Content Tabs - Responsive -->
      <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="border-b border-gray-200 dark:border-gray-700 dark:border-gray-700 overflow-x-auto">
          <div class="flex space-x-4 sm:space-x-8 px-4 sm:px-6 min-w-max">
            <button
              class="py-3 sm:py-4 text-xs sm:text-sm font-medium border-b-2 tab-active whitespace-nowrap flex items-center gap-1 sm:gap-2"
              data-tab="users"
            >
              <i data-lucide="users" class="w-3 h-3 sm:w-4 sm:h-4"></i> Team Members
            </button>
            <button
              class="py-3 sm:py-4 text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors whitespace-nowrap flex items-center gap-1 sm:gap-2"
              data-tab="roles"
            >
              <i data-lucide="shield" class="w-3 h-3 sm:w-4 sm:h-4"></i> Roles &amp; Permissions
            </button>
            {{-- Mirrors the sidebar entry. Carries the ready-to-collect count so a
                 corrected result is visible from the top strip too, without
                 opening the tab. --}}
            <button
              class="py-3 sm:py-4 text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors whitespace-nowrap flex items-center gap-1 sm:gap-2"
              data-tab="editrequests"
            >
              <i data-lucide="file-warning" class="w-3 h-3 sm:w-4 sm:h-4"></i> Edit Requests
              @if (($readyForRerunCount ?? 0) > 0)
                <span class="inline-flex items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700 dark:text-emerald-300">
                  {{ $readyForRerunCount }}
                </span>
              @endif
            </button>
            <button
              class="py-3 sm:py-4 text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors whitespace-nowrap flex items-center gap-1 sm:gap-2"
              data-tab="activity"
            >
              <i data-lucide="activity" class="w-3 h-3 sm:w-4 sm:h-4"></i> Activity Log
            </button>
            <button
              class="py-3 sm:py-4 text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors whitespace-nowrap flex items-center gap-1 sm:gap-2"
              data-tab="emails"
            >
              <i data-lucide="mail" class="w-3 h-3 sm:w-4 sm:h-4"></i> Email History
            </button>
            <button
              class="py-3 sm:py-4 text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors whitespace-nowrap flex items-center gap-1 sm:gap-2"
              data-tab="branding"
            >
              <i data-lucide="palette" class="w-3 h-3 sm:w-4 sm:h-4"></i> Branding
            </button>
            <button
              class="py-3 sm:py-4 text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors whitespace-nowrap flex items-center gap-1 sm:gap-2"
              data-tab="subscription"
            >
              <i data-lucide="credit-card" class="w-3 h-3 sm:w-4 sm:h-4"></i> Subscription
            </button>
          </div>
        </div>

        <!-- Team Members Tab -->
        <div id="users-tab" class="p-4 sm:p-6">
          <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <div>
              <h2 class="text-xl sm:text-2xl font-semibold text-gray-900 dark:text-gray-100">Team Members</h2>
              <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">Manage access and permissions</p>
            </div>
            <button
              id="add-user-btn"
              class="inline-flex items-center gap-2 px-4 sm:px-6 py-2 sm:py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl transition shadow-sm text-sm sm:text-base w-full sm:w-auto justify-center"
            >
              <i data-lucide="user-plus" class="w-4 h-4 sm:w-5 sm:h-5"></i>
              <span>Add Member</span>
            </button>
          </div>

          <div class="overflow-x-auto rounded-2xl border border-gray-100 dark:border-gray-700">
            <table class="w-full min-w-[800px]">
              <thead class="bg-gray-50 dark:bg-gray-700/50">
                <tr>
                  <th class="text-left py-3 sm:py-4 px-4 sm:px-6 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Member</th>
                  <th class="text-left py-3 sm:py-4 px-4 sm:px-6 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email</th>
                  <th class="text-left py-3 sm:py-4 px-4 sm:px-6 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider hidden md:table-cell">Job Title</th>
                  <th class="text-left py-3 sm:py-4 px-4 sm:px-6 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">User Type</th>
                  <th class="text-left py-3 sm:py-4 px-4 sm:px-6 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Available Tokens</th>
                  <th class="text-left py-3 sm:py-4 px-4 sm:px-6 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                  <th class="text-right py-3 sm:py-4 px-4 sm:px-6 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody id="users-table-body" class="divide-y divide-gray-100 dark:divide-gray-700"></tbody>
            </table>
          </div>
        </div>

        <!-- Roles Tab -->
        <div id="roles-tab" class="p-4 sm:p-6 hidden">
          <h2 class="text-xl sm:text-2xl font-semibold text-gray-900 dark:text-gray-100 mb-2">Roles &amp; Permissions</h2>
          <p class="text-sm text-gray-500 dark:text-gray-400 mb-6 sm:mb-8">Define what each user type can do</p>

          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Super Admin -->
            <div class="bg-gradient-to-br from-purple-50 to-white dark:from-purple-900/20 dark:to-gray-800 border border-purple-100 dark:border-purple-800/50 rounded-2xl sm:rounded-3xl p-6 sm:p-8">
              <div class="flex gap-3 sm:gap-4 items-start">
                <div class="w-12 h-12 sm:w-14 sm:h-14 bg-purple-100 dark:bg-purple-900/40 rounded-2xl flex items-center justify-center flex-shrink-0">
                  <i data-lucide="crown" class="w-6 h-6 sm:w-7 sm:h-7 text-purple-600 dark:text-purple-400"></i>
                </div>
                <div class="flex-1">
                  <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-2">
                    <h3 class="text-lg sm:text-xl font-semibold">Super Administrator</h3>
                    <span class="px-3 sm:px-4 py-1 text-xs font-medium bg-purple-100 dark:bg-purple-900/50 text-purple-700 dark:text-purple-300 rounded-2xl text-center sm:text-left">Full Access</span>
                  </div>
                  <ul class="mt-4 sm:mt-6 space-y-2 sm:space-y-3 text-xs sm:text-sm">
                    <li class="flex items-center gap-2"><i data-lucide="check-circle" class="w-4 h-4 text-green-500"></i> Full platform management</li>
                    <li class="flex items-center gap-2"><i data-lucide="check-circle" class="w-4 h-4 text-green-500"></i> User &amp; role management</li>
                    <li class="flex items-center gap-2"><i data-lucide="check-circle" class="w-4 h-4 text-green-500"></i> Token allocation &amp; billing</li>
                    <li class="flex items-center gap-2"><i data-lucide="check-circle" class="w-4 h-4 text-green-500"></i> Branding &amp; organization settings</li>
                  </ul>
                </div>
              </div>
            </div>

            <!-- Regular User -->
            <div class="bg-gradient-to-br from-blue-50 to-white dark:from-blue-900/20 dark:to-gray-800 border border-blue-100 dark:border-blue-800/50 rounded-2xl sm:rounded-3xl p-6 sm:p-8">
              <div class="flex gap-3 sm:gap-4 items-start">
                <div class="w-12 h-12 sm:w-14 sm:h-14 bg-blue-100 dark:bg-blue-900/40 rounded-2xl flex items-center justify-center flex-shrink-0">
                  <i data-lucide="user" class="w-6 h-6 sm:w-7 sm:h-7 text-blue-600 dark:text-blue-400"></i>
                </div>
                <div class="flex-1">
                  <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-2">
                    <h3 class="text-lg sm:text-xl font-semibold">Regular User</h3>
                    <span class="px-3 sm:px-4 py-1 text-xs font-medium bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 rounded-2xl text-center sm:text-left">Limited</span>
                  </div>
                  <ul class="mt-4 sm:mt-6 space-y-2 sm:space-y-3 text-xs sm:text-sm">
                    <li class="flex items-center gap-2"><i data-lucide="check-circle" class="w-4 h-4 text-green-500"></i> Perform legal searches</li>
                    <li class="flex items-center gap-2"><i data-lucide="check-circle" class="w-4 h-4 text-green-500"></i> View personal history</li>
                    <li class="flex items-center gap-2"><i data-lucide="check-circle" class="w-4 h-4 text-green-500"></i> Download own documents</li>
                    <li class="flex items-center gap-2 text-gray-400 dark:text-gray-500"><i data-lucide="x-circle" class="w-4 h-4"></i> Cannot manage users</li>
                    <li class="flex items-center gap-2 text-gray-400 dark:text-gray-500"><i data-lucide="x-circle" class="w-4 h-4"></i> Cannot change branding</li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Edit Requests Tab -->
        <div id="editrequests-tab" class="p-4 sm:p-6 hidden">
          <div class="flex flex-wrap items-center justify-between gap-3 mb-4 sm:mb-6">
            <div>
              <h2 class="text-xl sm:text-2xl font-semibold text-gray-900 dark:text-gray-100">Edit Requests</h2>
              <p class="text-sm text-gray-500 dark:text-gray-400">
                Search results your organisation reported as wrong, and the corrections returned by the PHS-P Admin.
              </p>
            </div>
            @if (($readyForRerunCount ?? 0) > 0)
              <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 dark:bg-emerald-900/30 px-3 py-1.5 text-xs font-semibold text-emerald-700 dark:text-emerald-300">
                <i data-lucide="check-circle" class="w-4 h-4"></i>
                {{ $readyForRerunCount }} ready to re-run &mdash; no token will be deducted
              </span>
            @endif
          </div>

          @php $requests = $editRequests ?? collect(); @endphp

          @if ($requests->isEmpty())
            <div class="rounded-2xl border border-dashed border-gray-200 dark:border-gray-700 py-14 text-center">
              <i data-lucide="file-check" class="w-8 h-8 mx-auto text-gray-300 mb-3"></i>
              <p class="text-sm text-gray-500 dark:text-gray-400">
                No edit requests yet. If a search result is wrong, use
                <span class="font-semibold">Send Edit Request</span> on the result to have it corrected.
              </p>
            </div>
          @else
            <div class="space-y-3">
              @foreach ($requests as $req)
                @php
                  $canRerun = $req->authorisesFreeRerun() && (int) $req->phs_member_id === (int) $member->id;
                  $tone = [
                      'ready_for_rerun' => ['emerald', 'check-circle'],
                      'edit_requested'  => ['amber',   'clock'],
                      'completed'       => ['gray',    'check'],
                      'declined'        => ['rose',    'x-circle'],
                  ][$req->status] ?? ['gray', 'circle'];
                @endphp

                <div class="rounded-2xl border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 p-4">
                  <div class="flex flex-col lg:flex-row lg:items-center gap-4">
                    <div class="min-w-0 flex-1">
                      <div class="flex flex-wrap items-center gap-2 mb-1">
                        <span class="inline-flex items-center gap-1 rounded-full bg-{{ $tone[0] }}-50 dark:bg-{{ $tone[0] }}-900/30 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-{{ $tone[0] }}-700 dark:text-{{ $tone[0] }}-300">
                          <i data-lucide="{{ $tone[1] }}" class="w-3 h-3"></i>{{ $req->statusLabel() }}
                        </span>
                        <span class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ $req->file_number ?: '—' }}</span>
                        @if ($req->reference_no)
                          <span class="text-[11px] font-mono text-gray-400">{{ $req->reference_no }}</span>
                        @endif
                      </div>

                      <p class="text-xs text-gray-600 dark:text-gray-300">
                        <span class="font-semibold">{{ $req->reasonLabel() }}</span>
                        @if ($req->reason) &mdash; {{ \Illuminate\Support\Str::limit($req->reason, 140) }} @endif
                      </p>

                      <p class="mt-1 text-[11px] text-gray-400">
                        Raised by {{ optional($req->member)->name ?? $req->requester_name ?? '—' }}
                        · {{ optional($req->requested_at)->format('d M Y H:i') ?? '—' }}
                        @if ($req->corrected_at)
                          · corrected {{ $req->corrected_at->format('d M Y H:i') }}
                          @if ($req->reviewer_name) by {{ $req->reviewer_name }} @endif
                        @endif
                        @if ($req->rerun_at)
                          · re-run {{ $req->rerun_at->format('d M Y H:i') }} at no charge
                        @endif
                      </p>

                      @if ($req->admin_response)
                        <p class="mt-2 rounded-lg bg-gray-50 dark:bg-gray-900/40 px-3 py-2 text-[11px] text-gray-600 dark:text-gray-300">
                          <span class="font-semibold">Admin:</span> {{ $req->admin_response }}
                        </p>
                      @endif
                    </div>

                    <div class="shrink-0">
                      @if ($canRerun)
                        {{-- Label is "Re-run", not "Run Again". --}}
                        <button type="button"
                          class="org-rerun-btn inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700"
                          data-file-number="{{ $req->file_number }}">
                          <i data-lucide="refresh-cw" class="w-4 h-4"></i>Re-run
                        </button>
                        <p class="mt-1 text-center text-[10px] text-emerald-700 dark:text-emerald-400">No token deducted</p>
                      @elseif ($req->authorisesFreeRerun())
                        {{-- Corrected, but the free re-run belongs to the member who
                             reported it — an administrator cannot spend it for them. --}}
                        <p class="max-w-[190px] text-[11px] text-gray-400 text-right">
                          Ready for re-run by
                          <span class="font-semibold">{{ optional($req->member)->name ?? 'the requester' }}</span>
                        </p>
                      @endif
                    </div>
                  </div>
                </div>
              @endforeach
            </div>
          @endif
        </div>

        <!-- Activity Log Tab -->
        <div id="activity-tab" class="p-4 sm:p-6 hidden">
          <h2 class="text-xl sm:text-2xl font-semibold text-gray-900 dark:text-gray-100 mb-4 sm:mb-6">Recent Activity</h2>
          <div id="activity-log-container" class="space-y-3 sm:space-y-4"></div>
        </div>

        <div id="emails-tab" class="p-4 sm:p-6 hidden">
          <div class="flex items-center justify-between mb-4 sm:mb-6">
            <h2 class="text-xl sm:text-2xl font-semibold text-gray-900 dark:text-gray-100">Email History</h2>
            <button id="refresh-email-history" class="inline-flex items-center gap-1 text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
              <i data-lucide="refresh-cw" class="w-4 h-4"></i> Refresh
            </button>
          </div>
          <div class="overflow-x-auto rounded-2xl border border-gray-100 dark:border-gray-700">
            <table class="w-full min-w-[760px]">
              <thead class="bg-gray-50 dark:bg-gray-700/50">
                <tr>
                  <th class="text-left py-3 px-4 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                  <th class="text-left py-3 px-4 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Recipient</th>
                  <th class="text-left py-3 px-4 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Subject</th>
                  <th class="text-left py-3 px-4 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Template</th>
                  <th class="text-right py-3 px-4 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody id="email-history-body" class="divide-y divide-gray-100 dark:divide-gray-700">
                <tr><td colspan="5" class="py-8 text-center text-sm text-gray-400">No email history yet.</td></tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Branding Tab -->
        <div id="branding-tab" class="p-4 sm:p-6 hidden">
          <div class="flex justify-between items-end mb-4 sm:mb-6">
            <div>
              <h2 class="text-xl sm:text-2xl font-semibold text-gray-900 dark:text-gray-100">Organization Branding</h2>
              <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">Customize appearance across the platform</p>
            </div>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 sm:gap-8">
            <!-- Settings Form -->
            <div class="space-y-4 sm:space-y-6">
              <!-- Organization Name -->
              <div class="bg-gray-50 dark:bg-gray-800 rounded-2xl p-4 sm:p-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Organization Name</label>
                <input type="text" id="org-name" class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 dark:border-gray-650 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm sm:text-base" placeholder="Enter organization name" />
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">This will appear in headers and user interfaces</p>
              </div>

              <!-- Organization Username -->
              <div class="bg-gray-50 dark:bg-gray-800 rounded-2xl p-4 sm:p-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Organization Username</label>
                <input type="text" id="org-username" pattern="[a-z0-9_]+" class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 dark:border-gray-650 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm sm:text-base" placeholder="organization_username" />
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Lowercase letters, numbers and underscores only. Must be unique.</p>
              </div>

              <!-- Colors -->
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
                <div class="bg-gray-50 dark:bg-gray-800 rounded-2xl p-4 sm:p-6">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Primary Color</label>
                  <div class="flex gap-2 sm:gap-3">
                    <input type="color" id="primary-color" class="w-10 h-10 sm:w-14 sm:h-14 rounded-xl border border-gray-300 dark:border-gray-600 cursor-pointer" />
                    <input type="text" id="primary-color-hex" class="flex-1 px-2 sm:px-4 py-2 sm:py-3 border border-gray-300 dark:border-gray-650 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-mono text-xs sm:text-sm" />
                  </div>
                </div>

                <div class="bg-gray-50 dark:bg-gray-800 rounded-2xl p-4 sm:p-6">
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Secondary Color</label>
                  <div class="flex gap-2 sm:gap-3">
                    <input type="color" id="secondary-color" class="w-10 h-10 sm:w-14 sm:h-14 rounded-xl border border-gray-300 dark:border-gray-600 cursor-pointer" />
                    <input type="text" id="secondary-color-hex" class="flex-1 px-2 sm:px-4 py-2 sm:py-3 border border-gray-300 dark:border-gray-650 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-mono text-xs sm:text-sm" />
                  </div>
                </div>
              </div>

              <!-- Logo Upload -->
              <div class="bg-gray-50 dark:bg-gray-800 rounded-2xl p-4 sm:p-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Organization Logo</label>
                <div id="logo-dropzone" class="dropzone border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-2xl p-4 sm:p-8 text-center hover:border-blue-400 transition-all cursor-pointer bg-white dark:bg-gray-700">
                  <input type="file" id="logo-upload" accept="image/*" class="hidden" />
                  <div id="logo-preview-area" class="mx-auto w-20 h-20 sm:w-28 sm:h-28 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl flex items-center justify-center overflow-hidden mb-3 sm:mb-4 shadow-sm">
                    <i data-lucide="image" class="w-8 h-8 sm:w-12 sm:h-12 text-gray-300 dark:text-gray-500"></i>
                  </div>
                  <button type="button" id="upload-logo-btn" class="inline-flex items-center px-3 sm:px-5 py-1.5 sm:py-2.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-650 rounded-xl text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <i data-lucide="upload" class="w-3 h-3 sm:w-4 sm:h-4 mr-1 sm:mr-2"></i> Upload Logo
                  </button>
                  <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 sm:mt-3">PNG, JPG or SVG • 200×200px recommended • Max 2MB</p>
                  <button id="remove-logo-btn" class="hidden mt-2 sm:mt-3 text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 text-xs sm:text-sm font-medium flex items-center gap-1 mx-auto">
                    <i data-lucide="trash-2" class="w-3 h-3 sm:w-4 sm:h-4"></i> Remove Logo
                  </button>
                </div>
              </div>

              <!-- Banner Upload -->
              <div class="bg-gray-50 dark:bg-gray-800 rounded-2xl p-4 sm:p-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Banner Image</label>
                <div id="banner-dropzone" class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-2xl p-4 sm:p-8 text-center hover:border-blue-400 transition-all cursor-pointer bg-white dark:bg-gray-700">
                  <input type="file" id="banner-upload" accept="image/*" class="hidden" />
                  <div id="banner-preview-area" class="mx-auto w-full h-20 sm:h-28 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl flex items-center justify-center overflow-hidden mb-3 sm:mb-4 shadow-sm">
                    <i data-lucide="image" class="w-8 h-8 sm:w-12 sm:h-12 text-gray-300 dark:text-gray-500"></i>
                  </div>
                  <button type="button" id="upload-banner-btn" class="inline-flex items-center px-3 sm:px-5 py-1.5 sm:py-2.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-650 rounded-xl text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <i data-lucide="upload" class="w-3 h-3 sm:w-4 sm:h-4 mr-1 sm:mr-2"></i> Upload Banner
                  </button>
                  <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 sm:mt-3">1200×300px recommended</p>
                </div>
              </div>
            </div>

            <!-- Live Preview -->
            <div class="preview-card-container">
              <div class="bg-gradient-to-br from-gray-900 to-gray-800 rounded-2xl sm:rounded-3xl p-5 sm:p-8 text-white h-full flex flex-col">
                <div class="flex justify-between items-center mb-4 sm:mb-6">
                  <h3 class="text-base sm:text-lg font-semibold">Live Preview</h3>
                  <span class="text-xs bg-white/10 px-2 sm:px-3 py-1 rounded-full">How your team sees it</span>
                </div>

                <div class="bg-white dark:bg-gray-700 rounded-xl sm:rounded-2xl p-4 sm:p-5 mb-4 sm:mb-6 text-gray-900 dark:text-gray-100 shadow-inner">
                  <div class="flex items-center gap-3 sm:gap-4">
                    <div id="logo-preview" class="w-10 h-10 sm:w-12 sm:h-12 rounded-2xl flex items-center justify-center overflow-hidden shadow bg-gradient-to-r from-blue-600 to-purple-600">
                      <i data-lucide="building" class="h-5 w-5 sm:h-6 sm:w-6 text-white"></i>
                    </div>
                    <div>
                      <p id="org-name-preview" class="font-semibold text-base sm:text-xl">Your Organization</p>
                      <p class="text-xs sm:text-sm text-gray-500">Legal Intelligence Platform</p>
                    </div>
                  </div>
                </div>

                <div class="space-y-4 sm:space-y-6">
                  <div>
                    <p class="text-xs text-gray-400 mb-2">PRIMARY BUTTON</p>
                    <button id="button-preview-primary" class="px-4 sm:px-6 py-2 sm:py-3 rounded-2xl text-white font-medium w-full text-sm sm:text-base bg-blue-600">Continue to Search</button>
                  </div>
                  <div>
                    <p class="text-xs text-gray-400 mb-2">SECONDARY BUTTON</p>
                    <button id="button-preview-secondary" class="px-4 sm:px-6 py-2 sm:py-3 border-2 rounded-2xl font-medium w-full text-sm sm:text-base border-blue-600 text-blue-600">View Reports</button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="flex flex-col sm:flex-row justify-end gap-3 sm:gap-4 mt-6 sm:mt-10">
            <button id="reset-settings" class="px-4 sm:px-6 py-2 sm:py-3 border border-gray-300 rounded-2xl text-gray-700 hover:bg-gray-50 transition font-medium text-sm sm:text-base order-2 sm:order-1">Reset to Defaults</button>
            <button id="save-settings" class="px-5 sm:px-8 py-2 sm:py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl transition font-medium text-sm sm:text-base order-1 sm:order-2">Save Branding Changes</button>
          </div>
        </div>

        <!-- Subscription Tab -->
        <div id="subscription-tab" class="p-4 sm:p-6 hidden">
          @php
            $activation = optional($institution)->created_at ? \Carbon\Carbon::parse($institution->created_at) : null;
            if ($subscription && $subscription->approved_at) {
                $activation = \Carbon\Carbon::parse($subscription->approved_at);
            }
            $expiry = $subscription && $subscription->expires_at
                ? \Carbon\Carbon::parse($subscription->expires_at)
                : ($activation ? $activation->copy()->addYear() : null);
            $expired = $expiry && $expiry->isPast();
          @endphp

          <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
              <h2 class="text-xl sm:text-2xl font-semibold text-gray-900 dark:text-gray-100">Subscription</h2>
              <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">Your current plan and billing history</p>
            </div>
            <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
              <a href="{{ route('phs.org.invoice.download') }}" class="inline-flex items-center gap-2 px-4 sm:px-6 py-2 sm:py-3 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-2xl transition text-sm sm:text-base justify-center">
                <i data-lucide="file-down" class="w-4 h-4 sm:w-5 sm:h-5"></i>
                <span>Download Invoice</span>
              </a>
              <button id="buy-tokens-btn" class="inline-flex items-center gap-2 px-4 sm:px-6 py-2 sm:py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl transition shadow-sm text-sm sm:text-base justify-center">
                <i data-lucide="shopping-cart" class="w-4 h-4 sm:w-5 sm:h-5"></i>
                <span>Top-up</span>
              </button>
            </div>
          </div>

          <!-- Current Plan Card -->
          <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-gradient-to-br from-blue-50 to-white dark:from-blue-900/20 dark:to-gray-800 p-6 sm:p-8 mb-6">
            <div class="flex flex-col sm:flex-row justify-between gap-4">
              <div>
                <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Current Plan</p>
                <h3 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ $subscription->package_name ?? '—' }}</h3>
                <div class="mt-3">
                  @if (!$subscription)
                    <span class="px-3 py-1 text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-full">No active plan</span>
                  @elseif ($expired)
                    <span class="px-3 py-1 text-xs font-medium bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300 rounded-full">Expired</span>
                  @else
                    <span class="px-3 py-1 text-xs font-medium bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300 rounded-full">Active</span>
                  @endif
                </div>
              </div>
              <div class="text-left sm:text-right">
                <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Token Balance</p>
                <p class="text-3xl font-bold text-blue-600 dark:text-blue-400 mt-1">{{ number_format((int) $institution->token_balance) }}</p>
              </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
              <div>
                <p class="text-xs text-gray-500 dark:text-gray-400">Tokens Included</p>
                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 mt-1">{{ $subscription ? number_format($subscription->tokens) : '—' }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-500 dark:text-gray-400">Amount Paid</p>
                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 mt-1">{{ $subscription && $subscription->amount ? 'NGN ' . number_format((float) $subscription->amount) : '—' }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-500 dark:text-gray-400">Activation Date</p>
                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 mt-1">{{ $activation ? $activation->format('M j, Y') : '—' }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-500 dark:text-gray-400">Expires</p>
                <p class="text-sm font-semibold {{ $expired ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-gray-100' }} mt-1">{{ $expiry ? $expiry->format('M j, Y') : '—' }}</p>
              </div>
            </div>
          </div>

          <!-- Billing History -->
          <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-3">Billing History</h3>
          <div class="overflow-x-auto rounded-2xl border border-gray-100 dark:border-gray-700">
            <table class="w-full min-w-[640px]">
              <thead class="bg-gray-50 dark:bg-gray-700/50">
                <tr>
                  <th class="text-left py-3 px-4 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                  <th class="text-left py-3 px-4 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Package</th>
                  <th class="text-left py-3 px-4 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tokens</th>
                  <th class="text-left py-3 px-4 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                  <th class="text-left py-3 px-4 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Method</th>
                  <th class="text-left py-3 px-4 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                  <th class="text-left py-3 px-4 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Reference</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse ($subscriptionHistory as $row)
                  <tr class="dark:hover:bg-gray-700/30 transition-colors">
                    <td class="py-3 px-4 text-sm text-gray-700 dark:text-gray-300">{{ optional($row->created_at)->format('M j, Y') ?? '—' }}</td>
                    <td class="py-3 px-4 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $row->package_name ?? '—' }}</td>
                    <td class="py-3 px-4 text-sm text-gray-700 dark:text-gray-300">{{ number_format($row->tokens) }}</td>
                    <td class="py-3 px-4 text-sm text-gray-700 dark:text-gray-300">{{ $row->amount ? 'NGN ' . number_format((float) $row->amount) : '—' }}</td>
                    <td class="py-3 px-4 text-sm text-gray-700 dark:text-gray-300 capitalize">{{ $row->payment_method ? str_replace('_', ' ', $row->payment_method) : '—' }}</td>
                    <td class="py-3 px-4 text-sm">
                      <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $row->status === 'completed' ? 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300' : ($row->status === 'pending' ? 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300') }}">
                        {{ ucfirst($row->status) }}
                      </span>
                    </td>
                    <td class="py-3 px-4 text-sm text-gray-500 dark:text-gray-400">{{ $row->reference_no ?? '—' }}</td>
                  </tr>
                @empty
                  <tr><td colspan="7" class="py-8 text-center text-sm text-gray-400 dark:text-gray-500">No subscription history yet.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <!-- Token Wallet Activity (all transaction types) -->
          <div class="flex items-center justify-between mt-8 mb-3">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Token Wallet Activity</h3>
            <button id="refresh-ledger" class="inline-flex items-center gap-1 text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
              <i data-lucide="refresh-cw" class="w-4 h-4"></i> Refresh
            </button>
          </div>
          <div class="overflow-x-auto rounded-2xl border border-gray-100 dark:border-gray-700">
            <table class="w-full min-w-[640px]">
              <thead class="bg-gray-50 dark:bg-gray-700/50">
                <tr>
                  <th class="text-left py-3 px-4 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                  <th class="text-left py-3 px-4 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                  <th class="text-right py-3 px-4 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tokens</th>
                  <th class="text-right py-3 px-4 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Balance</th>
                  <th class="text-left py-3 px-4 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                  <th class="text-left py-3 px-4 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Reference</th>
                </tr>
              </thead>
              <tbody id="wallet-ledger-body" class="divide-y divide-gray-100 dark:divide-gray-700">
                <tr><td colspan="6" class="py-8 text-center text-sm text-gray-400">Loading…</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Add User Modal - Responsive -->
  <div id="add-user-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-3 sm:p-4">
    <div class="modal-overlay absolute inset-0"></div>
    <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-2xl max-w-2xl w-full slide-in relative max-h-[90vh] sm:max-h-[95vh] flex flex-col">
      <div class="p-5 sm:p-8 border-b dark:border-gray-700 flex-shrink-0">
        <div class="flex justify-between items-center">
          <h2 class="text-xl sm:text-2xl font-bold dark:text-gray-100">Add New Team Member</h2>
          <button id="close-modal-btn" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
            <i data-lucide="x" class="w-5 h-5 sm:w-6 sm:h-6"></i>
          </button>
        </div>
      </div>

      <div class="p-5 sm:p-8 space-y-6 sm:space-y-8 overflow-y-auto flex-1">
        <!-- Basic Information -->
        <div>
          <h3 class="font-semibold mb-3 sm:mb-4 flex items-center gap-2 text-base sm:text-lg dark:text-gray-100"><i data-lucide="user" class="w-4 h-4 sm:w-5 sm:h-5"></i> Basic Information</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
            <div>
              <label class="block text-sm font-medium mb-1.5 dark:text-gray-200">Full Name <span class="text-red-500">*</span></label>
              <input id="user-fullname" type="text" class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 rounded-2xl focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base" placeholder="John Doe" />
            </div>
            <div>
              <label class="block text-sm font-medium mb-1.5 dark:text-gray-200">Email Address <span class="text-red-500">*</span></label>
              <input id="user-email" type="email" class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 rounded-2xl focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base" placeholder="john@example.com" />
            </div>
            <div>
              <label class="block text-sm font-medium mb-1.5 dark:text-gray-200">Phone Number</label>
              <input id="user-phone" type="tel" class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 rounded-2xl focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base" placeholder="0803 000 0000" />
            </div>
          </div>
        </div>

        <!-- Job Information -->
        <div>
          <h3 class="font-semibold mb-3 sm:mb-4 flex items-center gap-2 text-base sm:text-lg dark:text-gray-100"><i data-lucide="briefcase" class="w-4 h-4 sm:w-5 sm:h-5"></i> Job Information</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
            <div>
              <label class="block text-sm font-medium mb-1.5 dark:text-gray-200">Job Title <span class="text-red-500">*</span></label>
              <input id="job-title" type="text" class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 rounded-2xl focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base" placeholder="Legal Officer" />
            </div>
            <div>
              <label class="block text-sm font-medium mb-1.5 dark:text-gray-200">Department</label>
              <input id="department" type="text" class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 rounded-2xl focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base" placeholder="Legal Department" />
            </div>
          </div>
        </div>

        <!-- Account Security -->
        <div>
          <h3 class="font-semibold mb-3 sm:mb-4 flex items-center gap-2 text-base sm:text-lg dark:text-gray-100"><i data-lucide="lock" class="w-4 h-4 sm:w-5 sm:h-5"></i> Account Security</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
            <div>
              <label class="block text-sm font-medium mb-1.5 dark:text-gray-200">Temporary Password <span class="text-red-500">*</span></label>
              <div class="relative">
                <i data-lucide="lock" class="absolute left-3 sm:left-4 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4 sm:w-5 sm:h-5"></i>
                <input id="temp-password" type="password" class="w-full pl-9 sm:pl-12 pr-3 sm:pr-4 py-2 sm:py-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 rounded-2xl focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base" placeholder="Enter password" />
              </div>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">User will be prompted to change password on first login</p>
            </div>
            <div>
              <label class="block text-sm font-medium mb-1.5 dark:text-gray-200">Confirm Password <span class="text-red-500">*</span></label>
              <div class="relative">
                <i data-lucide="lock" class="absolute left-3 sm:left-4 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4 sm:w-5 sm:h-5"></i>
                <input id="confirm-password" type="password" class="w-full pl-9 sm:pl-12 pr-3 sm:pr-4 py-2 sm:py-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 rounded-2xl focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base" placeholder="Confirm password" />
              </div>
            </div>
          </div>
        </div>

        <!-- User Type Selection -->
        <div>
          <h3 class="font-semibold mb-3 sm:mb-4 text-base sm:text-lg dark:text-gray-100">User Type</h3>
          <input type="radio" name="user-type" value="regular_user" class="hidden" checked />
          <div class="flex items-center gap-3 border border-blue-500 bg-blue-50 dark:bg-blue-900/30 rounded-2xl p-4 sm:p-5">
            <div class="text-blue-600 dark:text-blue-400"><i data-lucide="user" class="w-5 h-5 sm:w-6 sm:h-6"></i></div>
            <div>
              <p class="font-medium text-sm sm:text-base dark:text-gray-100">Regular User</p>
              <p class="text-xs text-gray-500 dark:text-gray-400">Limited access - personal only</p>
            </div>
          </div>
        </div>

        <!-- Access Role Assignment -->
        <div>
          <h3 class="font-semibold mb-1 text-base sm:text-lg dark:text-gray-100">Access Role</h3>
          <p class="text-xs text-gray-500 dark:text-gray-400 mb-3 sm:mb-4">Select one or more roles</p>
          <div class="grid grid-cols-1 gap-2 sm:gap-3">
            <label class="flex items-center gap-3 sm:gap-4 p-3 sm:p-4 border border-gray-200 dark:border-gray-600 rounded-2xl cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50 dark:has-[:checked]:bg-blue-900/20">
              <input type="checkbox" name="access-role[]" value="search_only" class="w-3 h-3 sm:w-4 sm:h-4 accent-blue-600" checked />
              <div>
                <p class="font-medium text-sm sm:text-base dark:text-gray-100">Search Only</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Can only perform searches and view own results</p>
              </div>
            </label>
            <label class="flex items-center gap-3 sm:gap-4 p-3 sm:p-4 border border-gray-200 dark:border-gray-600 rounded-2xl cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50 dark:has-[:checked]:bg-blue-900/20">
              <input type="checkbox" name="access-role[]" value="report_viewer" class="w-3 h-3 sm:w-4 sm:h-4 accent-blue-600" />
              <div>
                <p class="font-medium text-sm sm:text-base dark:text-gray-100">Report Viewer</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Can view and download all reports within permission scope</p>
              </div>
            </label>
            <label class="flex items-center gap-3 sm:gap-4 p-3 sm:p-4 border border-gray-200 dark:border-gray-600 rounded-2xl cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50 dark:has-[:checked]:bg-blue-900/20">
              <input type="checkbox" name="access-role[]" value="analytics_viewer" class="w-3 h-3 sm:w-4 sm:h-4 accent-blue-600" />
              <div>
                <p class="font-medium text-sm sm:text-base dark:text-gray-100">Analytics Viewer</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Can view organization analytics and reports</p>
              </div>
            </label>
          </div>
        </div>

        <!-- Token Allocation -->
        <div>
          <label class="block text-sm font-medium mb-2">Initial Token Allocation</label>
          <input id="token-allocation" type="number" value="250" class="w-full px-3 sm:px-4 py-2 sm:py-3 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-2xl focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm sm:text-base" />
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Tokens will be deducted from organization balance</p>
        </div>
      </div>

      <div class="p-4 sm:p-6 border-t dark:border-gray-700 flex justify-end gap-3 sm:gap-4 bg-gray-50 dark:bg-gray-800 rounded-b-3xl flex-shrink-0">
        <button id="cancel-add-user" class="px-5 sm:px-8 py-2 sm:py-3.5 border border-gray-300 dark:border-gray-600 dark:text-gray-300 rounded-2xl hover:bg-gray-100 dark:hover:bg-gray-700 transition text-sm sm:text-base">Cancel</button>
        <button id="confirm-add-user" class="px-5 sm:px-8 py-2 sm:py-3.5 bg-blue-600 text-white rounded-2xl hover:bg-blue-700 transition font-medium text-sm sm:text-base">Create Member</button>
      </div>
    </div>
  </div>

  <!-- Token Purchase Modal -->
  <div id="token-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 hidden">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-4xl w-full max-h-[95vh] overflow-y-auto">
      <div class="p-4 sm:p-6 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-t-xl">
        <div class="flex justify-between items-start">
          <div>
            <h2 class="text-xl sm:text-2xl font-bold mb-1.5">Top-up</h2>
            
          </div>
          <button id="close-token-modal" class="text-white/80 hover:text-white hover:bg-white/20 rounded-lg p-2">
            <i data-lucide="x" class="w-5 h-5 sm:w-6 sm:h-6"></i>
          </button>
        </div>
      </div>
      <div class="p-4 sm:p-6 md:p-8">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 mb-6 sm:mb-8 hidden">
          @foreach ($packages as $key => $pkg)
          @if (strtolower($key) === 'starter') @continue @endif
          <div class="package-card bg-white dark:bg-gray-700 rounded-xl shadow-lg border-2 border-gray-200 dark:border-gray-650 cursor-pointer p-6 text-center transition"
            data-key="{{ $key }}" data-name="{{ $pkg['name'] }}" data-tokens="{{ $pkg['tokens'] }}" data-price="{{ $pkg['price'] }}">
            <h3 class="text-xl sm:text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $pkg['name'] }}</h3>
            <div class="text-2xl sm:text-3xl font-bold text-blue-600 dark:text-blue-400 mt-2">₦{{ number_format($pkg['price']) }}</div>
            <p class="text-gray-500 dark:text-gray-400 text-xs sm:text-sm mt-1">{{ number_format($pkg['tokens']) }} tokens / bundle</p>
          </div>
          @endforeach
        </div>

        {{-- Bundle quantity + live total --}}
        <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 p-4 sm:p-5 mb-2">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
              <label for="topup-qty" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Number of bundles</label>
              <div class="flex items-center gap-2">
                <button type="button" id="topup-minus" class="w-9 h-9 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-lg font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">−</button>
                <input type="number" id="topup-qty" min="1" max="10" value="1" class="w-20 text-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500" />
                <button type="button" id="topup-plus" class="w-9 h-9 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-lg font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">+</button>
              </div>
              <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Max 10 bundles per top-up.</p>
            </div>
            <div class="text-left sm:text-right">
              <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">You receive</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-gray-100"><span id="topup-tokens">0</span> <span class="text-sm font-medium text-gray-500 dark:text-gray-400">tokens</span></p>
              <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Total: <span class="font-bold text-blue-600 dark:text-blue-400" id="topup-total">₦0</span></p>
            </div>
          </div>
        </div>
        {{-- Pay with Paystack directly --}}
        <div class="border-t dark:border-gray-700 pt-6 mt-4 flex justify-center">
          <button id="topup-continue"
            class="inline-flex items-center justify-center gap-2 px-8 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-semibold shadow-md text-sm sm:text-base">
            <i data-lucide="credit-card" class="w-4 h-4 sm:w-5 sm:h-5"></i>
            Pay with Paystack
          </button>
        </div>
        <div class="flex justify-center gap-4 mt-6">
          <button id="cancel-token-purchase" class="px-5 sm:px-6 py-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 text-sm sm:text-base">Cancel</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    window.PHS_ORG = {
      institution: @json($institution),
      member: @json($member),
      members: @json($members),
      limits: {
        maxMembers: {{ $memberLimit ?? 'null' }},
        maxAdmins: 1,
        currentAdmins: {{ $currentAdminCount }},
      },
      routes: {
        dashboard: "{{ route('phs.dashboard') }}",
        logout: "{{ route('phs.logout') }}",
        membersStore: "{{ route('phs.org.members.store') }}",
        membersBase: "{{ url('/phs/organization/members') }}",
        branding: "{{ route('phs.org.branding') }}",
        activity: "{{ route('phs.org.activity') }}",
        emailHistory: "{{ route('phs.org.emailHistory') }}",
        payOnline: "{{ route('phs.tokens.payOnline') }}",
        topup: "{{ route('phs.tokens.topup') }}",
        topupPaystackInitiate: "{{ route('phs.tokens.topup.paystack.initiate') }}",
        requestInvoice: "{{ route('phs.tokens.requestInvoice') }}",
        transactions: "{{ route('phs.tokens.transactions') }}"
      },
      assets: {
        logo: "{{ $institution->logo_path ? asset('storage/' . $institution->logo_path) : '' }}",
        banner: "{{ $institution->banner_path ? asset('storage/' . $institution->banner_path) : '' }}"
      }
    };
  </script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="{{ asset('js/phs/organization.js') }}"></script>
  <script>
    // Re-run a corrected search from the Edit Requests tab.
    //
    // The org console has no results panel, so this hands off to the dashboard
    // with ?rerun=<file>; portal.js runs the search there. The token decision is
    // the SERVER's — this only asks for the search, it does not assume it is free.
    document.addEventListener('click', function (event) {
      var btn = event.target.closest('.org-rerun-btn');
      if (!btn) return;

      var fileNumber = btn.dataset.fileNumber || '';
      if (!fileNumber) return;

      btn.disabled = true;
      btn.innerHTML = 'Opening...';

      window.location.href = @json(route('phs.dashboard'))
        + '?rerun=' + encodeURIComponent(fileNumber);
    });

    document.addEventListener('DOMContentLoaded', function () {
      var preloader = document.getElementById('preloader');
      if (preloader) {
        setTimeout(function () {
          preloader.style.transition = 'opacity 0.3s ease';
          preloader.style.opacity = '0';
          setTimeout(function () { preloader.style.display = 'none'; }, 300);
        }, 500);
      }
    });
  </script>
</body>
</html>
