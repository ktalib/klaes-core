<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'KLAES — Survey Department')</title>
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="app-base-url" content="{{ url('/') }}">
  @auth
    <meta name="user-role" content="{{ auth()->user()->assign_role }}">
    <meta name="user-is-super-admin" content="{{ auth()->user()->isSuperAdmin() ? 'true' : 'false' }}">
  @endauth

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  {{-- Font Awesome — the prototype's icon set (the pages below use fas/fa-*) --}}
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg=="
    crossorigin="anonymous" referrerpolicy="no-referrer" />

  @include('survey_module.partials._prototype_css')

  @yield('styles')
  @stack('styles')
</head>

<body class="survey-body">

  <!-- SIDEBAR OVERLAY -->
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <!-- ============================================================
  SIDEBAR
  ============================================================ -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="logo-icon">K</div>
      <h1>KLAES <small>Survey Department</small></h1>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-label">Main</div>
      <a class="nav-item {{ request()->routeIs('survey-module.dashboard') ? 'active' : '' }}" data-page="dashboard" href="{{ route('survey-module.dashboard') }}">
        <i class="fas fa-th-large"></i> Dashboard
      </a>

      <div class="nav-label">Compensation</div>
      <a class="nav-item {{ request()->routeIs('survey-module.compensation.dashboard') ? 'active' : '' }}" data-page="compensation-dashboard" href="{{ route('survey-module.compensation.dashboard') }}">
        <i class="fas fa-chart-pie"></i> Dashboard
        <span class="badge green">12</span>
      </a>
      <a class="nav-item {{ request()->routeIs('survey-module.compensation.projects') ? 'active' : '' }}" data-page="projects" href="{{ route('survey-module.compensation.projects') }}">
        <i class="fas fa-folder-open"></i> Project Management
        <span class="badge blue">5</span>
      </a>
      <a class="nav-item sub-item {{ request()->routeIs('survey-module.compensation.projects.create') ? 'active' : '' }}" data-page="project-register" href="{{ route('survey-module.compensation.projects.create') }}">
        <i class="fas fa-plus-circle"></i> Create Project
      </a>
      <a class="nav-item {{ request()->routeIs('survey-module.compensation.cases') ? 'active' : '' }}" data-page="compensation-cases" href="{{ route('survey-module.compensation.cases') }}">
        <i class="fas fa-list"></i> Compensation Cases
      </a>
      <a class="nav-item sub-item {{ request()->routeIs('survey-module.compensation.cases.register') ? 'active' : '' }}" data-page="compensation-register" href="{{ route('survey-module.compensation.cases.register') }}">
        <i class="fas fa-plus-circle"></i> Register Case
      </a>
      <a class="nav-item sub-item {{ request()->routeIs('survey-module.compensation.beneficiaries') ? 'active' : '' }}" data-page="compensation-beneficiaries" href="{{ route('survey-module.compensation.beneficiaries') }}">
        <i class="fas fa-users"></i> Beneficiaries
      </a>
      <a class="nav-item sub-item {{ request()->routeIs('survey-module.compensation.trees') ? 'active' : '' }}" data-page="compensation-trees" href="{{ route('survey-module.compensation.trees') }}">
        <i class="fas fa-tree"></i> Economic Trees
      </a>
      <a class="nav-item sub-item {{ request()->routeIs('survey-module.compensation.calculator') ? 'active' : '' }}" data-page="compensation-calculator" href="{{ route('survey-module.compensation.calculator') }}">
        <i class="fas fa-calculator"></i> Compensation Calculator
      </a>
      <a class="nav-item sub-item {{ request()->routeIs('survey-module.compensation.land') ? 'active' : '' }}" data-page="compensation-land" href="{{ route('survey-module.compensation.land') }}">
        <i class="fas fa-map"></i> Land Allocation
      </a>
      <a class="nav-item sub-item {{ request()->routeIs('survey-module.compensation.op') ? 'active' : '' }}" data-page="compensation-op" href="{{ route('survey-module.compensation.op') }}">
        <i class="fas fa-file-signature"></i> OP Generation
      </a>
      <a class="nav-item sub-item {{ request()->routeIs('survey-module.compensation.reports') ? 'active' : '' }}" data-page="compensation-reports" href="{{ route('survey-module.compensation.reports') }}">
        <i class="fas fa-chart-bar"></i> Reports
      </a>

      <div class="nav-label">GKN</div>
      <a class="nav-item {{ request()->routeIs('survey-module.gkn.dashboard') ? 'active' : '' }}" data-page="gkn-dashboard" href="{{ route('survey-module.gkn.dashboard') }}">
        <i class="fas fa-chart-pie"></i> Dashboard
      </a>
      <a class="nav-item {{ request()->routeIs('survey-module.gkn.lands') ? 'active' : '' }}" data-page="gkn-lands" href="{{ route('survey-module.gkn.lands') }}">
        <i class="fas fa-building"></i> Government Lands
        <span class="badge yellow">8</span>
      </a>
      <a class="nav-item sub-item {{ request()->routeIs('survey-module.gkn.register') ? 'active' : '' }}" data-page="gkn-register" href="{{ route('survey-module.gkn.register') }}">
        <i class="fas fa-plus-circle"></i> Register GKN
      </a>
      <a class="nav-item sub-item {{ request()->routeIs('survey-module.gkn.tracking') ? 'active' : '' }}" data-page="gkn-tracking" href="{{ route('survey-module.gkn.tracking') }}">
        <i class="fas fa-search"></i> File Tracking
      </a>
      <a class="nav-item sub-item {{ request()->routeIs('survey-module.gkn.reports') ? 'active' : '' }}" data-page="gkn-reports" href="{{ route('survey-module.gkn.reports') }}">
        <i class="fas fa-chart-bar"></i> Reports
      </a>

      <div class="nav-label">Other Records</div>
      <a class="nav-item {{ request()->routeIs('survey-module.records.misc') ? 'active' : '' }}" data-page="misc-kn" href="{{ route('survey-module.records.misc') }}">
        <i class="fas fa-tags"></i> Misc KN
      </a>
      <a class="nav-item {{ request()->routeIs('survey-module.records.lpkn') ? 'active' : '' }}" data-page="lpkn" href="{{ route('survey-module.records.lpkn') }}">
        <i class="fas fa-layer-group"></i> LPKN
        <span class="badge">4</span>
      </a>

      <div class="nav-label">Workflow</div>
      <a class="nav-item {{ request()->routeIs('survey-module.workflow.examination') ? 'active' : '' }}" data-page="examination" href="{{ route('survey-module.workflow.examination') }}">
        <i class="fas fa-check-double"></i> Examination
        <span class="badge" style="background:var(--accent);">34</span>
      </a>
      <a class="nav-item {{ request()->routeIs('survey-module.workflow.occupancy') ? 'active' : '' }}" data-page="occupancy" href="{{ route('survey-module.workflow.occupancy') }}">
        <i class="fas fa-project-diagram"></i> Occupancy Permit Workflow
        <span class="badge" style="background:var(--accent);">QUICK WIN</span>
      </a>

      <div class="nav-label">Tools</div>
      <a class="nav-item {{ request()->routeIs('survey-module.tools.gis') ? 'active' : '' }}" data-page="gis" href="{{ route('survey-module.tools.gis') }}">
        <i class="fas fa-map-marked-alt"></i> GIS
        <span class="badge blue">KANGIS</span>
      </a>
      <a class="nav-item {{ request()->routeIs('survey-module.tools.plot-allocation') ? 'active' : '' }}" data-page="farm-plot-allocation" href="{{ route('survey-module.tools.plot-allocation') }}">
        <i class="fas fa-table"></i> Plot Allocation
        <span class="badge blue">10</span>
      </a>
      <a class="nav-item {{ request()->routeIs('survey-module.reports') ? 'active' : '' }}" data-page="reports" href="{{ route('survey-module.reports') }}">
        <i class="fas fa-chart-bar"></i> Reports
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="user-card">
        <div class="avatar">CJ</div>
        <div class="user-info">
          <div class="name">{{ auth()->check() ? auth()->user()->name : 'Clement Joseph' }}</div>
          <div class="role">Survey Officer</div>
        </div>
        <i class="fas fa-ellipsis-v" style="opacity:0.4;cursor:pointer;"></i>
      </div>
    </div>
  </aside>

  <!-- ============================================================
  MAIN CONTENT
  ============================================================ -->
  <main class="main-content">

    <!-- HEADER -->
    <header class="header">
      <div class="header-left">
        <button class="menu-toggle" onclick="toggleSidebar()">
          <i class="fas fa-bars"></i>
        </button>
        <span class="page-title" id="pageTitle">{{ $pageTitle ?? 'Dashboard' }}@isset($pageSubtitle)<span class="sub"> / {{ $pageSubtitle }}</span>@endisset</span>
      </div>
      <div class="header-right">
        <div class="search-box">
          <i class="fas fa-search"></i>
          <input type="text" placeholder="Search cases, farmers..." />
        </div>
        <button class="icon-btn" title="Notifications">
          <i class="fas fa-bell"></i>
          <span class="dot"></span>
        </button>
        <button class="icon-btn" title="Help">
          <i class="fas fa-question-circle"></i>
        </button>
      </div>
    </header>

    <!-- PAGE CONTENT -->
    <div class="page-content">
      @yield('content')
    </div>

  </main>

  <script>
    // ============================================================
    // SIDEBAR / MENU
    // ============================================================
    function toggleSidebar() {
      const sb = document.getElementById('sidebar');
      const ov = document.getElementById('sidebarOverlay');
      if (sb) sb.classList.toggle('open');
      if (ov) ov.classList.toggle('active');
    }
    function closeSidebar() {
      const sb = document.getElementById('sidebar');
      const ov = document.getElementById('sidebarOverlay');
      if (sb) sb.classList.remove('open');
      if (ov) ov.classList.remove('active');
    }
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

  </script>

  {{-- Prototype behaviour: stepper, project/scheme inheritance, tables, calculators --}}
  @include('survey_module.partials._scripts')

  @yield('footer-scripts')
  @stack('scripts')
</body>

</html>