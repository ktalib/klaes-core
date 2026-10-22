<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>KLAES-CORE - Login</title>
  <link rel="icon" type="image/png" href="{{ asset('assets/logo/klas_core_logo.png') }}">
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    /* Hide scrollbar for Chrome, Safari and Opera */
    .hide-scrollbar::-webkit-scrollbar {
      display: none;
    }
    
    /* Hide scrollbar for IE, Edge and Firefox */
    .hide-scrollbar {
      -ms-overflow-style: none;  /* IE and Edge */
      scrollbar-width: none;  /* Firefox */
    }
  </style>
</head>
<body>
  <div class="h-screen flex bg-gradient-to-br from-gray-50 via-gray-100 to-gray-200 overflow-hidden">
    <!-- Left side - Login form (static) -->
    <div class="w-full md:w-1/2 flex flex-col items-center justify-center p-8 h-screen">
      <div class="w-full max-w-md p-8 space-y-8 bg-white rounded-xl shadow-lg">
        <div class="flex flex-col items-center justify-center">
          <div class="w-48 h-32 relative mb-4">
            <img
              src="{{ asset('assets/logo/klas_core_logo.png') }}"
              alt="KLAES-CORE Logo"
              class="rounded-lg w-full h-full object-contain"
            />
          </div>
          <h2 class="text-2xl font-bold text-gray-900">Welcome to KLAES-CORE</h2>
          <p class="text-sm text-gray-600 mt-1">Part of the KLAES 2.0 Suite</p>
        </div>

        <form action="{{ route('login') }}" method="post" id="loginForm" class="login-form">
            @csrf
     
        <div class="space-y-4">
            <div class="space-y-2">
              <label for="email" class="block text-sm font-medium text-gray-700">Username</label>
              <div class="relative">
                <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-3 top-3 h-4 w-4 text-gray-400" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect width="20" height="16" x="2" y="4" rx="2"></rect>
                  <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
                </svg>
                <input
                  id="username"
                  type="text"
                  name="username"
                  placeholder="Enter your username"
                  class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md text-sm"
                  required
                />
              </div>
            </div>

            <div class="space-y-2">
              <div class="flex items-center justify-between hidden">
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <a href="{{ route('password.request') }}" class="text-xs  hover:underline">
                  Forgot password?
                </a>
              </div>
              <div class="relative">
                <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-3 top-3 h-4 w-4 text-gray-400" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect>
                  <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                <input
                  id="password"
                  type="password"
                  name="password"
                  placeholder="Enter your password"
                  class="w-full pl-10 pr-10 py-2 border border-gray-300 rounded-md text-sm"
                  required
                />
                <button
                  type="button"
                  id="togglePassword"
                  class="absolute right-3 top-3 text-gray-400 hover:text-gray-600"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" id="eyeIcon" class="h-4 w-4" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                  <svg xmlns="http://www.w3.org/2000/svg" id="eyeOffIcon" class="h-4 w-4 hidden" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"></path>
                    <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"></path>
                    <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"></path>
                    <line x1="2" x2="22" y1="2" y2="22"></line>
                  </svg>
                </button>
              </div>
            </div>

            <div class="flex items-center justify-between">
              <div class="flex items-center space-x-2">
                <input
                  type="checkbox"
                  id="remember-me"
                  class="h-4 w-4  focus:ring-blue-500 border-gray-300 rounded"
                />
                <label for="remember-me" class="text-sm text-gray-700">
                  Remember me
                </label>
              </div>
            </div>
          </div>

          <button 
            type="submit" 
            id="loginButton"
            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
          >
            Sign in
          </button>
        </form>

        {{-- SweetAlert for error/success messages --}}
        @if (session('error'))
            <script>
            window.addEventListener('load', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: @json(session('error')),
                    confirmButtonColor: '#4B5563'
                });
            });
            </script>
        @endif
        @if (session('success'))
            <script>
            window.addEventListener('load', function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: @json(session('success')),
                    confirmButtonColor: '#4B5563'
                });
            });
            </script>
        @endif

        <div class="mt-6 text-center">
          <p class="text-sm text-gray-600">
            Don't have an account?
            <a href="#" class=" hover:underline">
              Contact administrator
            </a>
          </p>
        </div>
      </div>
    </div>

    <!-- Right side - KLAES information (scrollable with hidden scrollbar) -->
    <div
      class="hidden md:block md:w-1/2 bg-gray-100 h-screen overflow-y-auto hide-scrollbar"
    >
      <div class="w-full max-w-3xl px-12 py-8 mx-auto">
        <div class="text-center mb-8">
          <h1 class="text-4xl font-bold text-gray-900 mb-2">KLAES 2.0</h1>
          <h2 class="text-2xl font-semibold text-gray-800 mb-3">Kano State Land Administration Enterprise System</h2>
          <p class="text-lg text-gray-700 font-medium italic">
            Powering a Smart, Secure &amp; Integrated Future for Land, Urban Planning, and Revenue Governance in Kano
            State, Leveraging Blockchain Technology and AI.
          </p>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
          <p class="text-gray-700 leading-relaxed">
            The Kano State Land Administration Enterprise System (KLAES 2.0) is a next-generation, enterprise-grade
            digital ecosystem designed to revolutionize how land, urban planning, and state revenues are managed across
            Kano State.
          </p>
        </div>

        <div class="bg-blue-50 p-6 rounded-lg shadow-sm mb-6 border border-blue-100">
          <p class="text-gray-700 leading-relaxed">
            Built on a modern, modular architecture, KLAES 2.0 serves as the unified digital backbone for the Ministry
            of Land and Physical Planning, KAMMA (Kano State Metropolitan Area Management Agency), KADCA (Kano State
            Development Control Agency), and KANGIS (Kano State Geographic Information System). By bringing together
            land administration, geospatial intelligence, development control, and revenue collection, KLAES 2.0 is not
            just a software—it is a statewide digital governance ecosystem enabling smarter workflows, greater
            transparency, and seamless service delivery.
          </p>
          <p class="text-gray-700 leading-relaxed mt-3">
            Whether it's registering a title, approving a building plan, performing a legal search, managing land
            disputes, or collecting state revenues, KLAES 2.0 ensures every department works in lockstep with
            synchronized data and automated processes.
          </p>
        </div>

        @php
          $klaesSuites = [
            [
              'title' => '1. KLAES-CORE (The Foundational Land & Registry Engine)',
              'intro' => 'The centralized hub for the Ministry of Land and Physical Planning, managing the end-to-end lifecycle of land files and deeds.',
              'items' => [
                'e-Registry' => 'A digitally centralized, secure repository of all title records enabling instant retrieval, search, and registration with strict data integrity and audit trails.',
                'Sectional Titling (ST) Module' => 'Allows original owners to apply for property fragmentation, supporting full workflows including betterment billing, planning approvals, and final conveyancing via a nested application system.',
                'Systematic Land Titling & Registration (SLTR)' => 'Empowers the mass titling of informal settlements by integrating community-based land identification with formal parcel-based mapping.',
                'Electronic Document Management System (EDMS)' => 'Manages scanned documents, file archiving, and indexing, integrated with RFID tracking for physical files and bulk document workflows.',
                'Customer Relationship Management (CRM)' => 'Streamlines interactions between the Ministry and stakeholders, tracking requests, complaints, service timelines, and feedback.',
              ],
            ],
            [
              'title' => '2. KLAES-GIS (The Geospatial & Title Foundation – KANGIS)',
              'intro' => 'The spatial intelligence engine powering accurate mapping and land information.',
              'items' => [
                'Enterprise GIS Integration' => 'Provides multi-user access to live spatial databases of parcels, layouts, infrastructure, and encumbrances.',
                'Advanced Spatial Tools' => 'Supports spatial analysis, parcel querying, site planning, and seamless survey integration.',
                'KANGIS Synchronization' => 'Fully integrated with the Parcel Fabric, COGO tools, and the KANGIS Land Information System (LIS) for real-time, bi-directional data syncing.',
              ],
            ],
            [
              'title' => '3. KLAES-METRO (The Urban & Field Operations Engine – KAMMA)',
              'intro' => 'The urban planning engine automating building permits, zoning, and field operations.',
              'items' => [
                'Automated Planning Approvals' => 'Streamlines the submission, review, and approval of building plans and zoning permits.',
                'Field Operations & Inspections' => 'Empowers field agents with mobile tools for joint site inspections, cadastral mapping, and special assignments.',
                'Spatial Buffer Routing' => 'Automatically routes applications to KAMMA or Physical Planning based on geospatial city-radius parameters.',
              ],
            ],
            [
              'title' => '4. KLAES-CONTROL (The Regulatory & Security Shield – KADCA)',
              'intro' => 'The centralized governance, compliance, and security hub for development control.',
              'items' => [
                'Development Control & Enforcement' => 'Provides a digital framework for site inspections, compliance monitoring, and enforcement of approved building plans.',
                'Advanced Security Protocols' => 'Enforces system-wide compliance through 2-Factor Authentication (2FA), biometric face identification, and Role-Based Access Control (RBAC).',
                'Immutable Audit Trails' => 'Tracks every user action, file movement, and system modification to ensure absolute accountability.',
              ],
            ],
            [
              'title' => '5. KLAES-Rev M (The Unified Billing & Revenue Engine)',
              'intro' => 'The financial engine ensuring zero revenue leakages and seamless state treasury integration.',
              'items' => [
                'Automated Billing Workflow' => 'Generates dynamic, automated invoices based on parcel size, land use, and instrument type.',
                'Real-Time Payment Integration' => 'Direct API-based integration with INTERSWITCH and KIRMAS (Kano State Internal Revenue Service) ensures all payments are tracked, validated, and reconciled in real-time.',
                'Customer Portal & Digital Invoicing' => "Applicants can view/download invoices, make payments via multiple channels (Bank, POS, USSD, Mobile), and monitor balances. Receipts are auto-generated and digitally attached to the customer's profile.",
              ],
            ],
          ];

          $klaesTech = [
            'Blockchain Technology' => 'Used in our Digital Legal Search (available both On-Premise for official use and Online for commercial use) to guarantee record immutability. It generates complete land histories from ownership to encumbrances, making titles fraud-proof.',
            'Artificial Intelligence (AI)' => 'Integrated into the core engine to automatically detect anomalies, flag duplicate land transactions, and predict processing bottlenecks before they occur.',
            'Multi-Tiered Approvals & Alerts' => 'Automated routing of files through various approval stages with real-time SMS and email alerts to applicants and officers, drastically reducing approval times.',
          ];
        @endphp

        <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
          <h3 class="font-semibold text-xl text-gray-900 mb-2">The KLAES 2.0 Suite Ecosystem</h3>
          <p class="text-gray-700 leading-relaxed mb-5">
            KLAES 2.0 is powered by five specialized, interoperable suites designed to handle the specific mandates of
            Kano State's land and urban governance agencies:
          </p>

          <div class="space-y-6">
            @foreach ($klaesSuites as $suite)
              <div class="feature-item">
                <h4 class="flex items-center text-lg font-medium mb-1">
                  <div class="h-6 w-6 rounded-full bg-blue-100 flex items-center justify-center mr-2 flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                      <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                  </div>
                  {{ $suite['title'] }}
                </h4>
                <p class="pl-8 text-sm text-gray-600 italic mb-2">{{ $suite['intro'] }}</p>
                <ul class="pl-8 space-y-1.5">
                  @foreach ($suite['items'] as $label => $text)
                    <li class="flex items-start">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mt-1 mr-2 flex-shrink-0" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                      </svg>
                      <span class="text-gray-700"><span class="font-semibold">{{ $label }}:</span> {{ $text }}</span>
                    </li>
                  @endforeach
                </ul>
              </div>
            @endforeach
          </div>
        </div>

        <div class="bg-blue-50 p-6 rounded-lg shadow-sm mb-6 border border-blue-100">
          <h3 class="font-semibold text-xl text-gray-900 mb-2">Powered by Next-Gen Technologies</h3>
          <p class="text-gray-700 leading-relaxed mb-4">
            KLAES 2.0 leverages cutting-edge technology to guarantee trust, speed, and accuracy:
          </p>
          <ul class="space-y-2">
            @foreach ($klaesTech as $label => $text)
              <li class="flex items-start">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mt-1 mr-2 flex-shrink-0" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                  <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <span class="text-gray-700"><span class="font-semibold">{{ $label }}:</span> {{ $text }}</span>
              </li>
            @endforeach
          </ul>
        </div>

        <div class="text-center mb-8">
          <p class="text-xl font-semibold italic">
            "KLAES 2.0: Interoperable Land, Urban, and Revenue Governance for a Smarter Kano."
          </p>
        </div>

        <div class="mt-4 mb-8 text-sm text-gray-600 text-center">
          © 2026 LAAD-Sys - Land Admin System. All rights reserved.
        </div>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const loginForm = document.getElementById('loginForm');
      const togglePassword = document.getElementById('togglePassword');
      const passwordInput = document.getElementById('password');
      const eyeIcon = document.getElementById('eyeIcon');
      const eyeOffIcon = document.getElementById('eyeOffIcon');
      
      // Toggle password visibility
      togglePassword.addEventListener('click', function() {
        if (passwordInput.type === 'password') {
          passwordInput.type = 'text';
          eyeIcon.classList.add('hidden');
          eyeOffIcon.classList.remove('hidden');
        } else {
          passwordInput.type = 'password';
          eyeIcon.classList.remove('hidden');
          eyeOffIcon.classList.add('hidden');
        }
      });
      
      // Auto-scrolling functionality for the right side content
      const rightSideContent = document.querySelector('.md\\:w-1\\/2.bg-gray-100.h-screen.overflow-y-auto');
      if (rightSideContent) {
        let scrollSpeed = 0.1; // pixels per millisecond
        let lastTimestamp = null;
        let scrollPaused = false;

        function autoScroll(timestamp) {
          if (scrollPaused) return;

          if (!lastTimestamp) {
            lastTimestamp = timestamp;
          }

          const elapsed = timestamp - lastTimestamp;
          lastTimestamp = timestamp;

          // Calculate how much to scroll based on elapsed time
          const scrollAmount = scrollSpeed * elapsed;

          // Scroll content
          rightSideContent.scrollTop += scrollAmount;

          // Check if we've reached the bottom
          const isAtBottom = rightSideContent.scrollHeight - rightSideContent.scrollTop - rightSideContent.clientHeight < 1;

          if (isAtBottom) {
            // Pause at the bottom before resetting
            scrollPaused = true;
            setTimeout(() => {
              rightSideContent.scrollTop = 0;
              lastTimestamp = null;
              scrollPaused = false;
              requestAnimationFrame(autoScroll);
            }, 2000); // Pause for 2 seconds
            return;
          }

          requestAnimationFrame(autoScroll);
        }

        // Start the auto-scrolling animation
        requestAnimationFrame(autoScroll);
      }
    });
  </script>
</body>
</html>
