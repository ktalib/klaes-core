<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>KLAS Online - Legal Search Services</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<style>
.loading-spinner {
    width: 1rem; height: 1rem;
    border: 2px solid #e5e7eb;
    border-top: 2px solid #3b82f6;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    display: inline-block;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-green-50">

<!-- ── Header ──────────────────────────────────────────────────────────── -->
<header class="bg-white shadow-sm border-b sticky top-0 z-40">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center h-16">
      <div class="flex items-center space-x-4">
        <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
          <i data-lucide="building" class="h-6 w-6 text-white"></i>
        </div>
        <div>
          <h1 class="text-xl font-bold text-gray-900">KLAS Online</h1>
          <p class="text-sm text-gray-600">Legal Search Services</p>
        </div>
      </div>
      <div class="flex items-center space-x-3">
        <a href="{{ route('ols.landing') }}" class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 transition border-0">Online Legal Search</a>
      </div>
    </div>
  </div>
</header>

<!-- ── Main ─────────────────────────────────────────────────────────────── -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

  <!-- Hero -->
  <section id="hero-section" class="relative py-16 md:py-20 bg-gradient-to-br from-blue-100 via-sky-100 to-green-100 rounded-xl shadow-lg overflow-hidden mb-12">
    <div class="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="md:flex md:items-center md:gap-12">
        <div class="flex-1 text-center md:text-left">
          <div class="inline-flex items-center rounded-full text-xs font-medium px-3 py-1 bg-white/90 border border-blue-300 text-blue-700 shadow-md mb-4">
            <i data-lucide="globe" class="w-4 h-4 mr-2 text-blue-600"></i>
            Available 24/7 Online
          </div>
          <h1 class="text-4xl sm:text-5xl md:text-6xl font-extrabold text-gray-900 mb-5 leading-tight">
            Official Legal Search,
            <span class="block text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-green-600">
              Made Simple &amp; Instant.
            </span>
          </h1>
          <p class="text-lg md:text-xl text-gray-700 mb-8 max-w-2xl mx-auto md:mx-0">
            Securely access official land records, verify property information, and retrieve legal documents
            with the Kano State Land Administration System.
          </p>
        </div>
      </div>
    </div>
  </section>

  <!-- Search Section -->
  <section id="search-section" class="pb-8">
    <div class="bg-white rounded-xl shadow-xl">
      <div class="p-6 md:p-8">

        <!-- Tabs -->
        <div class="mb-6">
          <div class="inline-flex bg-gray-100 rounded-lg p-1">
            <button id="quick-tab" class="px-4 py-2 rounded-md bg-white text-gray-700 text-sm font-medium shadow-sm border-0 cursor-pointer transition-all">Quick Search</button>
            <button id="advanced-tab" class="px-4 py-2 rounded-md bg-transparent text-gray-500 text-sm font-medium border-0 cursor-pointer hover:bg-gray-50 transition-all">Advanced Search</button>
          </div>
        </div>

        <!-- Quick Search -->
        <div id="quick-search">
          <div class="flex flex-col sm:flex-row items-center gap-3">
            <div class="flex-1 relative w-full">
              <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 w-5 h-5"></i>
              <input type="text" id="search-query"
                placeholder="File number, KANGIS No., Owner, Plot No..."
                class="w-full pl-11 pr-4 py-3 h-12 text-base border border-gray-300 rounded-md focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 transition-all" />
            </div>
            <div class="flex gap-3 w-full sm:w-auto">
              <button id="search-btn"
                class="inline-flex items-center justify-center gap-2 rounded-md font-semibold text-base px-7 h-12 bg-blue-600 text-white hover:bg-blue-700 transition cursor-pointer border-0 flex-grow sm:flex-grow-0">
                <i data-lucide="search" class="w-5 h-5"></i> Search
              </button>
              <button id="reset-btn"
                class="hidden inline-flex items-center justify-center gap-2 rounded-md font-semibold text-base px-7 h-12 bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 transition cursor-pointer flex-grow sm:flex-grow-0">
                <i data-lucide="rotate-ccw" class="w-5 h-5"></i> Reset
              </button>
            </div>
          </div>
          <p class="text-xs text-gray-500 mt-2.5">Examples: "COM-RES-2021-078", "KN12345", "John Doe", "A123"</p>
        </div>

        <!-- Advanced Search -->
        <div id="advanced-search" class="hidden">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-5 gap-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">File Number</label>
              <input id="adv-file-number" placeholder="e.g., COM-RES-2021-078" class="w-full px-3 py-2 h-10 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 transition-all" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">KANGIS File No.</label>
              <input id="adv-kangis" placeholder="e.g., KN12345" class="w-full px-3 py-2 h-10 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 transition-all" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">New KANGIS File No.</label>
              <input id="adv-new-kangis" placeholder="e.g., KN-NEW-12345" class="w-full px-3 py-2 h-10 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 transition-all" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Guarantor Name</label>
              <input id="adv-guarantor" placeholder="Enter guarantor name" class="w-full px-3 py-2 h-10 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 transition-all" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Guarantee Name</label>
              <input id="adv-guarantee" placeholder="Enter guarantee name" class="w-full px-3 py-2 h-10 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 transition-all" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Plot Number</label>
              <input id="adv-plot" placeholder="e.g., A123" class="w-full px-3 py-2 h-10 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 transition-all" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Plan Number</label>
              <input id="adv-plan" placeholder="e.g., KN/2021/001" class="w-full px-3 py-2 h-10 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 transition-all" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">District</label>
              <select id="adv-district" class="w-full px-3 py-2 h-10 border border-gray-300 rounded-md text-sm bg-white cursor-pointer focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10">
                <option value="">Select District</option>
                @foreach($districtOptions as $d)
                <option value="{{ $d }}">{{ $d }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
              <input id="adv-location" placeholder="Enter location" class="w-full px-3 py-2 h-10 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 transition-all" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Registration Particulars</label>
              <input id="adv-reg" placeholder="Enter registration particulars" class="w-full px-3 py-2 h-10 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 transition-all" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Size</label>
              <input id="adv-size" placeholder="e.g., 1000sqm" class="w-full px-3 py-2 h-10 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 transition-all" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Caveat</label>
              <select id="adv-caveat" class="w-full px-3 py-2 h-10 border border-gray-300 rounded-md text-sm bg-white cursor-pointer focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10">
                <option value="">Select caveat status</option>
                <option value="any">Any</option>
                <option value="Yes">Yes</option>
                <option value="No">No</option>
              </select>
            </div>
          </div>
          <div class="flex justify-end gap-3 mt-6">
            <button id="advanced-reset-btn" class="inline-flex items-center px-5 h-10 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium cursor-pointer bg-transparent transition">Reset</button>
            <button id="advanced-search-btn" class="inline-flex items-center gap-2 px-5 h-10 rounded-md bg-blue-600 text-white hover:bg-blue-700 text-sm font-semibold cursor-pointer border-0 transition">
              <i data-lucide="search" class="w-4 h-4"></i> Search
            </button>
          </div>
        </div>

      </div>
    </div>
  </section>

  <!-- Loading -->
  <section id="loading-section" class="hidden pb-12">
    <div class="flex justify-center items-center py-20">
      <div class="loading-spinner" style="width:2rem;height:2rem;border-width:3px;border-top-color:#3b82f6;"></div>
    </div>
  </section>

  <!-- Results -->
  <section id="results-section" class="hidden pb-12">
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-2xl font-semibold text-gray-800">
        Search Results <span id="results-count" class="text-gray-500 font-normal">(0 found)</span>
      </h2>
      <div id="view-toggle" class="hidden flex bg-gray-100 rounded-lg p-1">
        <button id="cards-view-btn" class="px-4 py-2 text-sm rounded-md bg-white text-gray-700 border-0 cursor-pointer shadow-sm">Cards</button>
        <button id="table-view-btn" class="px-4 py-2 text-sm rounded-md bg-transparent text-gray-500 border-0 cursor-pointer hover:bg-gray-50">Table</button>
      </div>
    </div>

    <!-- No Results -->
    <div id="no-results" class="hidden bg-white rounded-xl shadow-lg">
      <div class="p-10 text-center">
        <i data-lucide="file-search" class="w-16 h-16 text-gray-300 mx-auto mb-6"></i>
        <h3 class="text-2xl font-semibold text-gray-700 mb-3">No Results Found</h3>
        <p class="text-gray-500 mb-6 max-w-md mx-auto">We couldn't find any records matching your search criteria. Please try different keywords.</p>
        <button id="try-new-search" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50 cursor-pointer text-base bg-transparent">
          <i data-lucide="rotate-ccw" class="w-4 h-4"></i> Try a New Search
        </button>
      </div>
    </div>

    <!-- Cards -->
    <div id="cards-results" class="grid grid-cols-1 md:grid-cols-2 gap-5"></div>

    <!-- Table -->
    <div id="table-results" class="hidden bg-white rounded-xl shadow-lg overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[900px]">
          <thead class="bg-gray-50">
            <tr>
              <th class="p-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">File Number</th>
              <th class="p-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">KANGIS File No.</th>
              <th class="p-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Guarantor</th>
              <th class="p-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Guarantee</th>
              <th class="p-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">LGA</th>
              <th class="p-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Plot No</th>
              <th class="p-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Caveat</th>
              <th class="p-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Action</th>
            </tr>
          </thead>
          <tbody id="table-body" class="divide-y divide-gray-200 bg-white"></tbody>
        </table>
      </div>
    </div>
  </section>

  <!-- File Details -->
  <section id="file-details-section" class="hidden pb-12">
    <div class="flex items-center gap-3 mb-6">
      <button id="back-to-results" class="inline-flex items-center gap-1.5 px-3 h-9 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50 cursor-pointer text-sm font-medium bg-transparent">
        <i data-lucide="x" class="h-4 w-4"></i> Back to Results
      </button>
      <h2 class="text-2xl font-semibold text-gray-800">File Details</h2>
    </div>
    <div class="bg-white rounded-xl shadow-xl">
      <div class="p-6 bg-gray-50 rounded-t-xl border-b">
        <div class="flex justify-between items-start">
          <div>
            <h3 id="file-title" class="text-2xl md:text-3xl font-bold text-gray-800"></h3>
            <p id="file-subtitle" class="text-sm text-gray-500 mt-1"></p>
          </div>
          <span id="file-caveat-badge" class="inline-flex items-center rounded-full text-xs font-medium px-3 py-1"></span>
        </div>
      </div>
      <div class="p-6 md:p-8">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
          <div class="space-y-4">
            <h4 class="font-semibold text-lg text-gray-700 border-b pb-2">Property Information</h4>
            <div id="property-info" class="space-y-2"></div>
          </div>
          <div class="space-y-4">
            <h4 class="font-semibold text-lg text-gray-700 border-b pb-2">Ownership Information</h4>
            <div id="ownership-info" class="space-y-2"></div>
          </div>
        </div>
        <div class="mt-10 flex flex-col sm:flex-row justify-end gap-4">
          <button id="request-report-btn"
            class="inline-flex items-center gap-2 px-6 py-2.5 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50 cursor-pointer text-base font-medium bg-transparent">
            <i data-lucide="file-text" class="h-5 w-5"></i>
            Request Full Report
          </button>
        </div>
      </div>
    </div>
  </section>

  <!-- Features -->
  <section id="features-section" class="py-16 bg-white rounded-xl shadow-lg mb-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center mb-12">
        <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
          Why choose Ministry of Land and Physical Planning Legal Search?
        </h2>
        <p class="text-lg text-gray-600 max-w-3xl mx-auto">
          Get instant access to official land records with our secure, government-approved platform,
          designed for efficiency and reliability.
        </p>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <div class="text-center p-6 bg-white rounded-xl shadow-lg border border-gray-100 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300">
          <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-5 ring-4 ring-blue-200/50">
            <i data-lucide="shield" class="w-8 h-8 text-blue-600"></i>
          </div>
          <h3 class="text-xl font-semibold text-gray-800 mb-3">Official &amp; Secure</h3>
          <p class="text-gray-600 text-sm leading-relaxed">Government-certified platform with end-to-end encryption and secure data handling.</p>
        </div>
        <div class="text-center p-6 bg-white rounded-xl shadow-lg border border-gray-100 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300">
          <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-5 ring-4 ring-green-200/50">
            <i data-lucide="clock" class="w-8 h-8 text-green-600"></i>
          </div>
          <h3 class="text-xl font-semibold text-gray-800 mb-3">Instant Results</h3>
          <p class="text-gray-600 text-sm leading-relaxed">Get comprehensive legal search reports in minutes, not days or weeks.</p>
        </div>
        <div class="text-center p-6 bg-white rounded-xl shadow-lg border border-gray-100 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300">
          <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-5 ring-4 ring-purple-200/50">
            <i data-lucide="file-text" class="w-8 h-8 text-purple-600"></i>
          </div>
          <h3 class="text-xl font-semibold text-gray-800 mb-3">Comprehensive Reports</h3>
          <p class="text-gray-600 text-sm leading-relaxed">Detailed reports including ownership history, encumbrances, and legal status.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- CTA -->
  <section id="cta-section" class="py-20 bg-gradient-to-r from-blue-600 via-indigo-700 to-purple-700 rounded-xl shadow-2xl mb-8 relative overflow-hidden">
    <div class="absolute inset-0 bg-black/20"></div>
    <div class="relative max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
      <div class="inline-flex items-center rounded-full text-sm font-medium px-3 py-1 bg-white/20 text-white border border-white/30 mb-6">
        <i data-lucide="globe" class="w-4 h-4 mr-1.5"></i>
        Trusted by Professionals Nationwide
      </div>
      <h2 class="text-4xl md:text-5xl font-bold text-white mb-6 leading-tight">Ready to Get Started?</h2>
      <p class="text-xl text-blue-100 mb-10 max-w-3xl mx-auto leading-relaxed">
        Join thousands of legal professionals, property developers, and individuals who trust KLAS Online for
        fast, accurate, and official legal search services.
      </p>
      <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
        <button id="start-search-btn"
          class="inline-flex items-center gap-2.5 rounded-md font-semibold text-lg px-10 py-3.5 bg-slate-100 text-slate-900 hover:bg-slate-200 shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105 cursor-pointer border-0">
          <i data-lucide="search" class="w-5 h-5"></i>
          Start Your Search
        </button>
        <button id="view-pricing-btn"
          class="inline-flex items-center gap-2.5 rounded-md font-semibold text-lg px-10 py-3.5 bg-white/20 text-white border border-white/30 hover:bg-white/30 shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105 cursor-pointer">
          <i data-lucide="file-text" class="w-5 h-5"></i>
          View Pricing Plans
        </button>
      </div>
    </div>
  </section>

</div>{{-- /max-w-7xl --}}

<!-- ── Footer ───────────────────────────────────────────────────────────── -->
<footer class="bg-gray-900 text-white py-12 mt-16">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-10">
      <div>
        <div class="flex items-center space-x-3 mb-4">
          <div class="w-9 h-9 bg-white rounded-md p-0.5 flex items-center justify-center">
            <i data-lucide="building" class="h-6 w-6 text-blue-600"></i>
          </div>
          <span class="text-xl font-semibold">KLAS Online</span>
        </div>
        <p class="text-gray-400 text-sm leading-relaxed">
          Official government platform for legal search services and land record verification in Kano State.
        </p>
      </div>
      <div>
        <h3 class="text-base font-semibold uppercase tracking-wider text-gray-300 mb-4">Services</h3>
        <ul class="space-y-2 text-sm">
          <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Legal Search</a></li>
          <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Property Verification</a></li>
          <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Title Investigation</a></li>
          <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Due Diligence Reports</a></li>
        </ul>
      </div>
      <div>
        <h3 class="text-base font-semibold uppercase tracking-wider text-gray-300 mb-4">Support</h3>
        <ul class="space-y-2 text-sm">
          <li><a href="{{ route('legal-search-online.admin.feedback') }}" class="text-gray-400 hover:text-white transition-colors">Feedback &amp; Complaints</a></li>
          <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Contact Support</a></li>
          <li><a href="#" class="text-gray-400 hover:text-white transition-colors">System Status</a></li>
        </ul>
      </div>
      <div>
        <h3 class="text-base font-semibold uppercase tracking-wider text-gray-300 mb-4">Contact Us</h3>
        <div class="space-y-3 text-sm">
          <div class="flex items-center space-x-2.5 text-gray-400">
            <i data-lucide="mail" class="w-4 h-4 flex-shrink-0"></i>
            <a href="mailto:support@klas.gov.ng" class="hover:text-white transition-colors">support@klas.gov.ng</a>
          </div>
          <div class="flex items-center space-x-2.5 text-gray-400">
            <i data-lucide="phone" class="w-4 h-4 flex-shrink-0"></i>
            <span>+234 (0) 9 123 4567</span>
          </div>
          <div class="flex items-start space-x-2.5 text-gray-400">
            <i data-lucide="map-pin" class="w-4 h-4 flex-shrink-0 mt-0.5"></i>
            <span>KLAS Headquarters, Kano, Nigeria</span>
          </div>
        </div>
      </div>
    </div>
    <div class="border-t border-gray-800 mt-10 pt-8 text-center text-gray-500 text-sm">
      <p>&copy; {{ date('Y') }} Kano State Land Administration System (KLAS). All rights reserved.</p>
      <p class="mt-1">Empowering Kano with transparent and efficient land administration.</p>
    </div>
  </div>
</footer>

<!-- ── Pricing Modal ────────────────────────────────────────────────────── -->
<div id="pricing-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 hidden">
  <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
    <div class="p-6 border-b flex justify-between items-center">
      <div>
        <h2 class="text-2xl font-bold text-gray-800">Ministry of Land and Physical Planning Online Legal Search Fees</h2>
        <p class="text-gray-600 mt-1">Choose the right plan for your legal search needs.</p>
      </div>
      <button id="close-pricing-modal" class="p-2 rounded-md hover:bg-gray-100 cursor-pointer border-0 bg-transparent">
        <i data-lucide="x" class="h-5 w-5 text-gray-700"></i>
      </button>
    </div>
    <div class="p-6 md:p-8">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Basic -->
        <div class="bg-white rounded-xl border border-gray-200 hover:border-green-300 hover:shadow-xl transition-all duration-300">
          <div class="text-center pt-8 pb-4 px-6">
            <h3 class="text-xl font-semibold text-gray-800">Basic Search</h3>
            <div class="text-4xl font-bold text-green-600 mt-2">₦1,000</div>
            <p class="text-gray-500 text-sm">Per search report</p>
          </div>
          <div class="space-y-3 px-6 pb-6">
            @foreach(['Basic property information','Current ownership information','Ownership history','Caveat status','PDF summary report'] as $f)
            <div class="flex items-start gap-2.5"><div class="w-2 h-2 bg-green-500 rounded-full mt-[7px] flex-shrink-0"></div><span class="text-sm text-gray-600">{{ $f }}</span></div>
            @endforeach
            <button onclick="document.getElementById('pricing-modal').classList.add('hidden'); document.getElementById('search-query').focus();"
              class="w-full mt-6 py-2.5 rounded-md bg-green-600 hover:bg-green-700 text-white font-semibold text-base cursor-pointer border-0 transition">
              Start Searching
            </button>
          </div>
        </div>
        <!-- Pro -->
        <div class="bg-white rounded-xl border border-blue-500 shadow-lg relative hover:shadow-xl transition-all duration-300">
          <div class="absolute -top-3.5 left-1/2 -translate-x-1/2 px-3 py-1 bg-blue-500 text-white text-xs font-semibold rounded-full shadow-md">Most Popular</div>
          <div class="text-center pt-8 pb-4 px-6">
            <h3 class="text-xl font-semibold text-gray-800">Search Pro</h3>
            <div class="text-4xl font-bold text-blue-600 mt-2">₦30,000</div>
            <p class="text-gray-500 text-sm">Per search</p>
          </div>
          <div class="space-y-3 px-6 pb-6">
            @foreach(['Everything in Basic','Complete transaction history','Property history timeline','Detailed PDF report','Legal compliance check'] as $f)
            <div class="flex items-start gap-2.5"><div class="w-2 h-2 bg-green-500 rounded-full mt-[7px] flex-shrink-0"></div><span class="text-sm text-gray-600">{{ $f }}</span></div>
            @endforeach
            <button class="w-full mt-6 py-2.5 rounded-md bg-blue-500 hover:bg-blue-600 text-white font-semibold text-base cursor-pointer border-0 transition">Choose Search Pro</button>
          </div>
        </div>
        <!-- Enterprise -->
        <div class="bg-white rounded-xl border border-gray-200 hover:border-purple-300 hover:shadow-xl transition-all duration-300">
          <div class="text-center pt-8 pb-4 px-6">
            <h3 class="text-xl font-semibold text-gray-800">Enterprise</h3>
            <div class="text-4xl font-bold text-purple-600 mt-2">₦50,000</div>
            <p class="text-gray-500 text-sm">Per search</p>
          </div>
          <div class="space-y-3 px-6 pb-6">
            @foreach(['Everything in Professional','Priority processing','API access (soon)','Bulk search discounts','Dedicated support'] as $f)
            <div class="flex items-start gap-2.5"><div class="w-2 h-2 bg-green-500 rounded-full mt-[7px] flex-shrink-0"></div><span class="text-sm text-gray-600">{{ $f }}</span></div>
            @endforeach
            <button class="w-full mt-6 py-2.5 rounded-md bg-purple-600 hover:bg-purple-700 text-white font-semibold text-base cursor-pointer border-0 transition">Choose Enterprise</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── Report/Payment Modal ─────────────────────────────────────────────── -->
<div id="report-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 hidden">
  <div class="bg-white rounded-xl shadow-2xl max-w-md w-full">
    <div class="p-6 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-t-xl">
      <div class="flex justify-between items-start">
        <div>
          <h2 class="text-2xl font-bold mb-1">Request Full Report</h2>
          <div class="flex items-center gap-2 text-blue-100 text-sm">
            <i data-lucide="file-text" class="w-4 h-4"></i>
            <span id="report-file-number">File: </span>
          </div>
        </div>
        <button id="close-report-modal" class="p-1.5 rounded-md hover:bg-white/20 cursor-pointer border-0 bg-transparent text-white">
          <i data-lucide="x" class="h-5 w-5"></i>
        </button>
      </div>
      <p class="text-blue-100 mt-2 text-sm">Pay once to unlock preview and print access for this report.</p>
    </div>
    <div class="p-6 text-center">
      <div class="text-5xl font-bold text-green-600 mb-2">₦1,000</div>
      <p class="text-gray-600 text-sm mb-6">Secured by Paystack — your card details are never stored.</p>
      <button id="pay-now-btn"
        class="w-full py-3 rounded-md bg-green-600 hover:bg-green-700 text-white font-bold text-base cursor-pointer border-0 transition mb-3 flex items-center justify-center gap-2">
        <i data-lucide="credit-card" class="w-4 h-4"></i> Pay ₦1,000 Now
      </button>
      <button id="cancel-report" class="w-full py-2.5 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50 font-medium text-sm cursor-pointer bg-transparent transition">
        Cancel
      </button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://js.paystack.co/v1/inline.js"></script>

<script>
const LS = {
    searchUrl:  "{{ route('legalsearch.online.search') }}",
    verifyUrl:  "{{ route('legal_search.online.payment.verify') }}",
    authCheckUrl: "{{ $config['authCheckUrl'] }}",
    pendUrl:    "{{ $config['pendUrl'] }}",
    csrf:       "{{ csrf_token() }}",
    pstackKey:  "{{ $config['paystackPublicKey'] }}",
    pstackAmt:  {{ $config['paymentAmount'] }},
    isAuth:     {{ $config['isAuthenticated'] ? 'true' : 'false' }},
    loginUrl:   "{{ $config['loginUrl'] }}",
    registerUrl:"{{ $config['registerUrl'] }}",
    pendingFile: {{ $config['pendingFile'] ? "'".e($config['pendingFile'])."'" : 'null' }},
    pendingQuery: {{ $config['pendingQuery'] ? "'".e($config['pendingQuery'])."'" : 'null' }},
};

// ── State ────────────────────────────────────────────────────────────────
let searchResults = [];
let selectedFile  = null;
let viewMode      = 'cards';
let paid          = false;
let pendingCb     = null;

// ── Helpers ───────────────────────────────────────────────────────────────
const $id = id => document.getElementById(id);

const SECS = {
    hero:     $id('hero-section'),
    search:   $id('search-section'),
    loading:  $id('loading-section'),
    results:  $id('results-section'),
    details:  $id('file-details-section'),
    features: $id('features-section'),
    cta:      $id('cta-section'),
};

function show(...ids) {
    Object.entries(SECS).forEach(([k,el]) => el && el.classList.toggle('hidden', !ids.includes(k)));
}

const showHero    = () => show('hero','search','features','cta');
const showLoading = () => show('loading','search');
const showResults = () => show('results','search');
const showDetails = () => show('details');

// ── Normalize backend record ──────────────────────────────────────────────
function norm(r) {
    return {
        fileNumber:    r.file_number     ?? r.fileNumber    ?? '',
        kangisFileNo:  r.kangis_file_no  ?? r.kangisFileNo  ?? '',
        newKangis:     r.new_kangis_file_no ?? r.newKangisFileNo ?? '',
        guarantor:     r.guarantor_name  ?? r.guarantor     ?? '',
        guarantee:     r.guarantee_name  ?? r.guarantee     ?? '',
        plotNumber:    r.plot_number     ?? r.plotNumber    ?? '',
        planNumber:    r.plan_number     ?? r.planNumber    ?? '',
        district:      r.district        ?? '',
        lga:           r.lga             ?? '',
        location:      r.location        ?? '',
        regParticulars:r.registration_particulars ?? r.registrationParticulars ?? '',
        size:          r.size            ?? '',
        caveat:        r.caveat          ?? 'No',
        lastTx:        r.last_transaction ?? r.lastTransaction ?? '',
    };
}

// ── AJAX Search ───────────────────────────────────────────────────────────
function doSearch(params) {
    showLoading();
    paid = false;

    fetch(LS.searchUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': LS.csrf,
            'Accept': 'application/json',
        },
        body: JSON.stringify(params),
    })
    .then(r => r.json())
    .then(data => {
        const raw = Array.isArray(data) ? data : (data.results ?? data.data ?? []);
        searchResults = raw.map(norm);
        renderResults();
        showResults();
    })
    .catch(() => {
        Swal.fire('Error', 'Search failed. Please try again.', 'error');
        showHero();
    });
}

function quickSearch() {
    const q = $id('search-query').value.trim();
    if (!q) return;
    doSearch({ query: q });
}

function advSearch() {
    doSearch({
        file_number:              $id('adv-file-number').value.trim(),
        kangis_file_no:           $id('adv-kangis').value.trim(),
        new_kangis_file_no:       $id('adv-new-kangis').value.trim(),
        guarantorName:            $id('adv-guarantor').value.trim(),
        guaranteeName:            $id('adv-guarantee').value.trim(),
        plotNumber:               $id('adv-plot').value.trim(),
        planNumber:               $id('adv-plan').value.trim(),
        district:                 $id('adv-district').value,
        location:                 $id('adv-location').value.trim(),
        registration_particulars: $id('adv-reg').value.trim(),
        size:                     $id('adv-size').value.trim(),
        caveat:                   $id('adv-caveat').value,
    });
}

function resetSearch() {
    ['search-query','adv-file-number','adv-kangis','adv-new-kangis','adv-guarantor',
     'adv-guarantee','adv-plot','adv-plan','adv-district','adv-location','adv-reg',
     'adv-size','adv-caveat'].forEach(id => { const el = $id(id); if (el) el.value = ''; });
    searchResults = []; selectedFile = null; paid = false;
    $id('reset-btn').classList.add('hidden');
    showHero();
}

// ── Render ────────────────────────────────────────────────────────────────
function badge(c) {
    return c === 'Yes'
        ? 'bg-red-100 text-red-700 border border-red-200'
        : 'bg-green-100 text-green-700 border border-green-200';
}

function renderResults() {
    $id('results-count').textContent = `(${searchResults.length} found)`;
    if (searchResults.length === 0) {
        $id('no-results').classList.remove('hidden');
        $id('cards-results').classList.add('hidden');
        $id('table-results').classList.add('hidden');
        $id('view-toggle').classList.add('hidden');
        return;
    }
    $id('no-results').classList.add('hidden');
    $id('view-toggle').classList.remove('hidden');
    viewMode === 'cards' ? renderCards() : renderTable();
}

function renderCards() {
    $id('cards-results').classList.remove('hidden');
    $id('table-results').classList.add('hidden');
    $id('cards-results').innerHTML = searchResults.map((f, i) => `
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300 overflow-hidden cursor-pointer" onclick="selectFile(${i})">
            <div class="p-5">
                <div class="flex justify-between items-start mb-3.5">
                    <div>
                        <div class="font-semibold text-lg text-gray-800 flex items-center">
                            <i data-lucide="file-text" class="h-5 w-5 mr-2.5 text-blue-500"></i>
                            ${f.fileNumber || '—'}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">KANGIS: ${f.kangisFileNo || '—'}${f.newKangis ? ' | New: '+f.newKangis : ''}</div>
                    </div>
                    <span class="inline-flex items-center rounded-full text-xs font-medium px-2.5 py-1 ${badge(f.caveat)}">Caveat: ${f.caveat}</span>
                </div>
                <div class="grid grid-cols-2 gap-x-4 gap-y-2.5 text-sm">
                    <div><span class="text-gray-500">Guarantor:</span> <span class="text-gray-700 font-medium">${f.guarantor || '—'}</span></div>
                    <div><span class="text-gray-500">Guarantee:</span> <span class="text-gray-700 font-medium">${f.guarantee || '—'}</span></div>
                    <div><span class="text-gray-500">LGA:</span> <span class="text-gray-700 font-medium">${f.lga || '—'}</span></div>
                    <div><span class="text-gray-500">Location:</span> <span class="text-gray-700 font-medium">${f.location || '—'}</span></div>
                    <div><span class="text-gray-500">Plot No:</span> <span class="text-gray-700 font-medium">${f.plotNumber || '—'}</span></div>
                    <div><span class="text-gray-500">Size:</span> <span class="text-gray-700 font-medium">${f.size || '—'}</span></div>
                </div>
            </div>
            <div class="bg-gray-50 px-5 py-3 text-right border-t border-gray-200">
                <span class="text-blue-600 hover:text-blue-700 font-medium text-sm">View Details →</span>
            </div>
        </div>
    `).join('');
    lucide.createIcons();
}

function renderTable() {
    $id('cards-results').classList.add('hidden');
    $id('table-results').classList.remove('hidden');
    $id('table-body').innerHTML = searchResults.map((f, i) => `
        <tr class="hover:bg-gray-50/70 transition-colors">
            <td class="p-3 text-sm text-gray-700 whitespace-nowrap">${f.fileNumber || '—'}</td>
            <td class="p-3 text-sm text-gray-700 whitespace-nowrap">${f.kangisFileNo || '—'}</td>
            <td class="p-3 text-sm text-gray-700 whitespace-nowrap">${f.guarantor || '—'}</td>
            <td class="p-3 text-sm text-gray-700 whitespace-nowrap">${f.guarantee || '—'}</td>
            <td class="p-3 text-sm text-gray-700 whitespace-nowrap">${f.lga || '—'}</td>
            <td class="p-3 text-sm text-gray-700 whitespace-nowrap">${f.plotNumber || '—'}</td>
            <td class="p-3 text-sm font-medium whitespace-nowrap ${f.caveat === 'Yes' ? 'text-red-600' : 'text-green-600'}">${f.caveat}</td>
            <td class="p-3 whitespace-nowrap">
                <button onclick="selectFile(${i})" class="inline-flex items-center gap-1.5 px-2 h-8 rounded-md text-blue-600 hover:bg-blue-50 cursor-pointer border-0 bg-transparent text-sm font-medium">
                    <i data-lucide="file-search" class="h-4 w-4"></i> View
                </button>
            </td>
        </tr>
    `).join('');
    lucide.createIcons();
}

// ── File Details ──────────────────────────────────────────────────────────
function selectFile(i) {
    selectedFile = searchResults[i];

    // ── Gate: require authentication before showing full result ──
    if (!LS.isAuth) {
        // Show auth-required modal, then redirect to login
        Swal.fire({
            title: 'Authentication Required',
            html: 'Please <strong>sign in</strong> or <strong>create an account</strong> to view the full legal search report.',
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Sign In',
            cancelButtonText: 'Register',
            reverseButtons: false,
            allowOutsideClick: false,
        }).then((result) => {
            if (result.isConfirmed) {
                // Store pending file, then go to login
                pendAndRedirect('login');
            } else if (result.dismiss === Swal.DismissReason.cancel) {
                pendAndRedirect('register');
            }
        });
        return;
    }

    // Authenticated: show preview, then gate payment for full report
    openDetails();
}

/**
 * Store the pending file number in session, then redirect to login/register.
 */
function pendAndRedirect(target) {
    fetch(LS.pendUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': LS.csrf,
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            file_number: selectedFile?.fileNumber ?? '',
            query: $id('search-query').value.trim(),
        }),
    })
    .catch(() => {}) // even if this fails, still redirect
    .finally(() => {
        const url = target === 'login' ? LS.loginUrl : LS.registerUrl;
        window.location.href = url;
    });
}

/**
 * Render the file-details preview (shown to authenticated users before payment).
 */
function openDetails() {
    $id('file-title').textContent    = selectedFile.fileNumber || '—';
    $id('file-subtitle').textContent = `KANGIS: ${selectedFile.kangisFileNo}${selectedFile.newKangis ? ' | New KANGIS: '+selectedFile.newKangis : ''}`;
    const b = $id('file-caveat-badge');
    b.textContent = `Caveat: ${selectedFile.caveat}`;
    b.className   = `inline-flex items-center rounded-full text-xs font-medium px-3 py-1 ${badge(selectedFile.caveat)}`;
    $id('property-info').innerHTML = [
        ['Plot Number',selectedFile.plotNumber],['Plan Number',selectedFile.planNumber],
        ['Size',selectedFile.size],['LGA',selectedFile.lga],
        ['District',selectedFile.district],['Location',selectedFile.location],
    ].map(([l,v]) => `<div class="flex justify-between text-sm"><span class="text-gray-500">${l}:</span><span class="text-gray-800 font-medium text-right">${v||'—'}</span></div>`).join('');
    $id('ownership-info').innerHTML = [
        ['Guarantor',selectedFile.guarantor],['Guarantee',selectedFile.guarantee],
        ['Reg. Particulars',selectedFile.regParticulars],['Last Transaction',selectedFile.lastTx],
    ].map(([l,v]) => `<div class="flex justify-between text-sm"><span class="text-gray-500">${l}:</span><span class="text-gray-800 font-medium text-right">${v||'—'}</span></div>`).join('');

    // If already paid this session, reveal full report; otherwise show preview with pay CTA
    const btn = $id('request-report-btn');
    if (paid) {
        btn.innerHTML = '<i data-lucide="check-circle" class="h-5 w-5 mr-2"></i> Full Report Unlocked';
        btn.classList.remove('border-gray-300', 'text-gray-700');
        btn.classList.add('border-green-500', 'text-green-700', 'bg-green-50');
    } else {
        btn.innerHTML = '<i data-lucide="lock" class="h-5 w-5 mr-2"></i> Pay ₦1,000 to View Full Report';
        btn.classList.add('border-gray-300', 'text-gray-700');
        btn.classList.remove('border-green-500', 'text-green-700', 'bg-green-50');
    }
    lucide.createIcons();
    showDetails();
}

// ── Payment ───────────────────────────────────────────────────────────────
function requestPayment(onSuccess) {
    pendingCb = onSuccess;
    if (selectedFile) $id('report-file-number').textContent = 'File: ' + (selectedFile?.fileNumber || '');
    $id('report-modal').classList.remove('hidden');
}

function openPaystack() {
    $id('report-modal').classList.add('hidden');
    const ref = 'LSOP-' + Date.now() + '-' + Math.random().toString(36).substr(2,8).toUpperCase();

    PaystackPop.setup({
        key:      LS.pstackKey,
        amount:   LS.pstackAmt,
        currency: 'NGN',
        ref:      ref,
        metadata: { module: 'legal_search_online' },
        onSuccess: function(tx) {
            Swal.fire({ title: 'Verifying…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            fetch(LS.verifyUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': LS.csrf, 'Accept': 'application/json' },
                body: JSON.stringify({
                    reference:   tx.reference,
                    email:       tx.customer?.email ?? '',
                    file_number: selectedFile?.fileNumber ?? '',
                }),
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    paid = true;
                    Swal.fire({ title: 'Payment Successful', text: 'You can now view and print the search report.', icon: 'success', timer: 1800, showConfirmButton: false })
                    .then(() => { if (typeof pendingCb === 'function') pendingCb(); });
                } else {
                    Swal.fire('Verification Failed', res.message || 'Please contact support.', 'error');
                }
            })
            .catch(() => Swal.fire('Error', 'Could not verify payment. Please contact support.', 'error'));
        },
        onClose: function() {},
    }).openIframe();
}

// ── Events ────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();
    showHero();

    // Tabs
    $id('quick-tab').addEventListener('click', () => {
        $id('quick-tab').classList.add('bg-white','shadow-sm','text-gray-700');
        $id('quick-tab').classList.remove('bg-transparent','text-gray-500');
        $id('advanced-tab').classList.remove('bg-white','shadow-sm','text-gray-700');
        $id('advanced-tab').classList.add('bg-transparent','text-gray-500');
        $id('quick-search').classList.remove('hidden');
        $id('advanced-search').classList.add('hidden');
    });
    $id('advanced-tab').addEventListener('click', () => {
        $id('advanced-tab').classList.add('bg-white','shadow-sm','text-gray-700');
        $id('advanced-tab').classList.remove('bg-transparent','text-gray-500');
        $id('quick-tab').classList.remove('bg-white','shadow-sm','text-gray-700');
        $id('quick-tab').classList.add('bg-transparent','text-gray-500');
        $id('advanced-search').classList.remove('hidden');
        $id('quick-search').classList.add('hidden');
    });

    // Search
    $id('search-btn').addEventListener('click', quickSearch);
    $id('advanced-search-btn').addEventListener('click', advSearch);
    $id('search-query').addEventListener('keypress', e => { if (e.key === 'Enter') quickSearch(); });
    $id('search-query').addEventListener('input', function() {
        $id('reset-btn').classList.toggle('hidden', !this.value.trim());
    });

    // Reset
    $id('reset-btn').addEventListener('click', resetSearch);
    $id('advanced-reset-btn').addEventListener('click', resetSearch);
    $id('try-new-search').addEventListener('click', resetSearch);

    // View mode toggle
    $id('cards-view-btn').addEventListener('click', () => { viewMode = 'cards'; if (searchResults.length) renderCards(); });
    $id('table-view-btn').addEventListener('click', () => { viewMode = 'table'; if (searchResults.length) renderTable(); });

    // Back
    $id('back-to-results').addEventListener('click', () => { selectedFile = null; showResults(); });

    // Request report (from details page) — triggers Paystack payment
    $id('request-report-btn').addEventListener('click', () => {
        if (paid) {
            Swal.fire('Access Granted', 'Your report is unlocked for this session.', 'success');
            return;
        }
        // After successful payment, mark paid and re-render details
        requestPayment(() => {
            paid = true;
            openDetails();
            Swal.fire({
                title: 'Payment Successful',
                text: 'Your full legal search report is now unlocked.',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false,
            });
        });
    });

    // Payment modal
    $id('pay-now-btn').addEventListener('click', openPaystack);
    $id('cancel-report').addEventListener('click', () => $id('report-modal').classList.add('hidden'));
    $id('close-report-modal').addEventListener('click', () => $id('report-modal').classList.add('hidden'));

    // Pricing modal
    $id('view-pricing-btn').addEventListener('click', () => $id('pricing-modal').classList.remove('hidden'));
    $id('close-pricing-modal').addEventListener('click', () => $id('pricing-modal').classList.add('hidden'));

    // CTA
    $id('start-search-btn').addEventListener('click', () => { $id('search-query').focus(); $id('search-section').scrollIntoView({ behavior: 'smooth' }); });

    // Close modals on backdrop click
    [$id('pricing-modal'), $id('report-modal')].forEach(m => {
        m.addEventListener('click', e => { if (e.target === m) m.classList.add('hidden'); });
    });

    // ── Auto-reopen pending file after login/register redirect ──
    if (LS.pendingFile && LS.isAuth) {
        // Re-run the search for the pending file, then auto-open its details
        const query = LS.pendingQuery || LS.pendingFile;
        $id('search-query').value = query;
        doSearch({ query: query });
        // Hook into renderResults to auto-select the matching file once results arrive
        const origRender = renderResults;
        renderResults = function () {
            origRender();
            if (LS.pendingFile && searchResults.length) {
                const idx = searchResults.findIndex(f =>
                    (f.fileNumber || '').toUpperCase() === LS.pendingFile.toUpperCase()
                );
                if (idx >= 0) {
                    LS.pendingFile = null; // consume so it only fires once
                    setTimeout(() => selectFile(idx), 300);
                }
            }
        };
    }
});

window.selectFile = selectFile;
</script>

</body>
</html>
