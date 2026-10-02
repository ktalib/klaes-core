@extends('layouts.app')

@section('page-title')
    {{ __('System Settings') }}
@endsection

@section('content')
@php
    $admin_logo = getSettingsValByName('company_logo');
    $profile = asset(Storage::url('upload/profile'));
    $activeTab = session('tab', 'user_profile_settings');
    $workStationModules = workStationModuleMap();

    $settingsTabs = [
        ['key' => 'user_profile_settings',    'icon' => 'ti ti-user-check',      'title' => __('User Profile'),             'subtitle' => __('User Account Profile Settings'),      'partial' => 'settings.partials.user-profile'],
        ['key' => 'password_settings',         'icon' => 'ti ti-key',             'title' => __('Password'),                 'subtitle' => __('Password Settings'),                  'partial' => 'settings.partials.password'],
        ['key' => 'general_settings',          'icon' => 'ti ti-settings',        'title' => __('General'),                  'subtitle' => __('General Settings'),                   'partial' => 'settings.partials.general'],
        ['key' => 'multipurpose_settings',     'icon' => 'ti ti-adjustments-alt', 'title' => __('Multipurpose'),             'subtitle' => __('Brand & Work Station Targets'),       'partial' => 'settings.partials.multipurpose'],
        ['key' => 'company_settings',          'icon' => 'ti ti-building',        'title' => __('Company'),                  'subtitle' => __('Company Settings'),                   'partial' => 'settings.partials.company'],
        ['key' => 'email_SMTP_settings',       'icon' => 'ti ti-mail',            'title' => __('Email'),                    'subtitle' => __('Email SMTP Settings'),                'partial' => 'settings.partials.email-smtp'],
        ['key' => 'payment_settings',          'icon' => 'ti ti-credit-card',     'title' => __('Payment'),                  'subtitle' => __('Payment Settings'),                   'partial' => 'settings.partials.payment'],
        ['key' => 'site_SEO_settings',         'icon' => 'ti ti-sitemap',         'title' => __('Site SEO'),                 'subtitle' => __('Site SEO Settings'),                  'partial' => 'settings.partials.site-seo'],
        ['key' => 'google_recaptcha_settings', 'icon' => 'ti ti-brand-google',    'title' => __('Google Recaptcha'),         'subtitle' => __('Google Recaptcha Settings'),          'partial' => 'settings.partials.google-recaptcha'],
        ['key' => '2FA',                       'icon' => 'ti ti-barcode',         'title' => __('2 Factors Authentication'), 'subtitle' => __('2 Factors Authentication Settings'), 'partial' => 'settings.partials.two-factor-auth'],
    ];

    $inputClass      = 'block w-full rounded-2xl border border-slate-200 bg-white/80 px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:ring-offset-1';
    $textareaClass   = $inputClass . ' min-h-[120px]';
    $fileInputClass  = $inputClass . ' cursor-pointer';
    $labelClass      = 'text-xs font-semibold tracking-wide text-slate-500 uppercase';
    $sectionCard     = 'bg-white border border-slate-200 rounded-3xl shadow-sm';
    $sectionBody     = 'space-y-6 p-6';
    $sectionHeader   = 'border-b border-slate-100 px-6 py-5';
    $primaryButton   = 'inline-flex items-center justify-center rounded-full bg-blue-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2';
    $secondaryButton = 'inline-flex items-center justify-center rounded-full border border-slate-200 px-5 py-2 text-sm font-semibold text-slate-600 shadow-sm transition hover:border-blue-300 hover:text-blue-600';
    $dangerButton    = 'inline-flex items-center justify-center rounded-full bg-rose-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-400 focus:ring-offset-2';
    $twoColumnGrid   = 'grid grid-cols-1 gap-5 md:grid-cols-2';
    $threeColumnGrid = 'grid grid-cols-1 gap-5 lg:grid-cols-3';
@endphp

<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle'       => 'System Settings',
        'PageDescription' => 'Manage account preferences, branding, integrations, payments, security policies and more.'
    ])

    <div class="py-8 bg-slate-50 min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- Page Header --}}
            <div class="mb-8">
                <p class="text-[10px] font-black text-blue-600 uppercase tracking-widest">System Admin</p>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight mt-1">System Settings</h1>
                <p class="text-slate-500 text-sm mt-1">Manage account preferences, branding, integrations, payments, security policies and more.</p>
            </div>

            {{-- Settings Shell — Alpine.js tab switching --}}
            <div x-data="{ activeTab: '{{ $activeTab }}' }" class="bg-white rounded-2xl shadow-sm border border-slate-200">
                <div class="grid gap-0 lg:grid-cols-3">

                    {{-- Sidebar Navigation --}}
                    <div class="lg:col-span-1 border-b lg:border-b-0 lg:border-r border-slate-200 p-4 md:p-6">
                        <ul class="space-y-2">
                            @foreach ($settingsTabs as $tab)
                                <li>
                                    <button type="button"
                                        @click="activeTab = '{{ $tab['key'] }}'; window.location.hash = '{{ $tab['key'] }}'"
                                        :class="activeTab === '{{ $tab['key'] }}'
                                            ? 'border-blue-500 bg-blue-600 text-white shadow-lg'
                                            : 'border-slate-200 bg-white text-slate-700 hover:border-blue-400 hover:text-blue-600'"
                                        class="flex w-full items-start gap-3 rounded-2xl border px-4 py-3 text-sm font-semibold shadow-sm transition-all duration-200 text-left"
                                    >
                                        <span
                                            :class="activeTab === '{{ $tab['key'] }}' ? 'bg-white/20 text-white' : 'bg-blue-50 text-blue-600'"
                                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl"
                                        >
                                            <i class="{{ $tab['icon'] }} text-lg"></i>
                                        </span>
                                        <span class="flex flex-col">
                                            <span class="text-base font-bold tracking-tight">{{ $tab['title'] }}</span>
                                            <span
                                                :class="activeTab === '{{ $tab['key'] }}' ? 'text-white/80' : 'text-slate-500'"
                                                class="text-xs font-medium"
                                            >{{ $tab['subtitle'] }}</span>
                                        </span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    {{-- Tab Content Panels --}}
                    <div class="lg:col-span-2 p-4 md:p-6">
                        @foreach ($settingsTabs as $tab)
                            <div x-show="activeTab === '{{ $tab['key'] }}'" x-cloak>
                                @include($tab['partial'])
                            </div>
                        @endforeach
                    </div>

                </div>
            </div>
        </div>
    </div>

    @include('admin.footer')
</div>
@endsection
