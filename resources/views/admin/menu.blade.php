@php
    /*
     | Sidebar visibility.
     |
     | $hasRole('Survey - Records') asks whether this user may SEE that module. It now
     | delegates to the one permission gate instead of re-implementing the name matching
     | inline, so the menu and the route guard can never disagree about a module.
     |
     | This is a pure refactor and must stay one: ModulePermissions answers `view` from
     | users.assign_role, exactly as this closure did, and the dash/spacing/case folding
     | moved verbatim into ModuleName::normalize(). Nothing here reads module_permissions.
     |
     | That matters more than it looks. Eight module names are granted to users AND checked
     | below AND missing from the user_roles registry — 38 users, 18 of them on
     | "Deeds - Property Index Cards Assistant (Legacy Records)". Any version of this that
     | resolved through the registry, or through the new grants table, would take those
     | menu items away. Resolving through assign_role cannot.
     */
    $hasRole = function ($role) {
        return auth()->check() && auth()->user()->canDo($role, 'view');
    };

    /* Kept for any partial that still reads the raw list. */
    $userRoles = auth()->user()->assignedRoleNames();
@endphp
{{-- Greyed out and inert while the account has no passport photo, or has not confirmed
     its phone number. This is the visual half of both rules; RequireProfilePhoto and
     RequirePhoneVerification block the URLs themselves. --}}
@php
    $sidebarPhotoLocked = auth()->check()
        && (auth()->user()->needs_profile_photo || auth()->user()->needs_phone_verification);
@endphp
<div class="sidebar border-r border-gray-200 bg-white transition-all duration-300 ease-in-out{{ $sidebarPhotoLocked ? ' sidebar-locked' : '' }}"
  @if($sidebarPhotoLocked) aria-disabled="true" @endif>
  <!-- Sidebar Header -->
  @include('admin.menu.partials.sidebar_header')
 
  <!-- Sidebar Content -->
  <div class="sidebar-content p-2 overflow-y-auto max-h-[calc(100vh-8rem)] scroll-smooth scrollbar-visible">
    @include('admin.menu.partials.modules.dashboard')
    @include('admin.menu.partials.modules.hc_ps_view')
    @include('admin.menu.partials.modules.crm')
    @include('admin.menu.partials.modules.edms')
    @include('admin.menu.partials.modules.digital_archive')
    @include('admin.menu.partials.modules.file_tracking')
    @include('admin.menu.partials.modules.programmes')
    @include('admin.menu.partials.modules.information_products')
    @include('admin.menu.partials.modules.revenue_management')
    @include('admin.menu.partials.modules.kangis')
    @include('admin.menu.partials.modules.deeds')
    @include('admin.menu.partials.modules.search')
    @include('admin.menu.partials.modules.lands')
    @include('admin.menu.partials.modules.dciv')
    @include('admin.menu.partials.modules.physical_planning')
    @include('admin.menu.partials.modules.survey')
    @include('admin.menu.partials.modules.cadastral')
    @include('admin.menu.partials.modules.prs_management')
    @include('admin.menu.partials.modules.gis')
    @include('admin.menu.partials.modules.sectional_titling')
    @include('admin.menu.partials.modules.sltr')
    @include('admin.menu.partials.modules.special_assignment')
    @include('admin.menu.partials.modules.systems')
    @include('admin.menu.partials.modules.legacy_systems')
    @include('admin.menu.partials.modules.payroll')
    @include('admin.menu.partials.modules.system_admin')
  </div>

  <!-- Sidebar Footer -->
  @include('admin.menu.partials.sidebar_footer')

  <!-- Loading Spinner Overlay -->
</div>

@include('admin.menu.partials.sidebar_styles')
@include('admin.menu.partials.sidebar_scripts')
