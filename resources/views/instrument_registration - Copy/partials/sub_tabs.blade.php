  <div id="pendingSubTabs" class="flex justify-center gap-2 py-3 transition-all duration-200" style="display: none;">
              <button class="pending-subtab-btn flex flex-col items-center focus:outline-none transition-all duration-150" id="subtab-other" onclick="switchPendingSubTab('other')">
                <span class="text-base font-semibold">Other Instruments</span>
              </button>
              <a href="{{route('st_transfer.index')}}" class="pending-subtab-btn flex flex-col items-center focus:outline-none transition-all duration-150" id="subtab-st" onclick="switchPendingSubTab('st')">
                <span class="text-base font-semibold">ST Assignment</span>
                <span class="text-xs font-normal text-gray-400">(Transfer of Title)</span>
              </a>
              <button class="pending-subtab-btn flex flex-col items-center focus:outline-none transition-all duration-150" id="subtab-regular" onclick="switchPendingSubTab('regular')">
                <span class="text-base font-semibold">Regular CofO</span>
              </button>
              <a href="{{route('st_registration.index')}}" class="pending-subtab-btn flex flex-col items-center focus:outline-none transition-all duration-150" id="subtab-sectional" onclick="switchPendingSubTab('sectional')">
                <span class="text-base font-semibold">Sectional Titling CofO</span>
              </a>
              <a  href="{{route('sltrdeedsreg.index')}}" class="pending-subtab-btn flex flex-col items-center focus:outline-none transition-all duration-150" id="subtab-sltr" onclick="switchPendingSubTab('sltr')">
                <span class="text-base font-semibold">SLTR CofO</span>
              </a>
            </div>
            <style>
              .pending-subtab-btn {
                @apply px-5 py-3 rounded-lg border border-gray-300 bg-white text-gray-700 font-medium shadow-sm transition-all duration-150 hover:bg-blue-50 hover:border-blue-500 hover:text-blue-700 focus:ring-2 focus:ring-blue-200;
                margin-right: 0.25rem;
                min-width: 160px;
                transition: all 0.2s ease;
                cursor: pointer;
              }
              .pending-subtab-btn.active {
                @apply bg-blue-600 text-white border-blue-600 shadow-md;
                transform: translateY(-2px);
              }
              .pending-subtab-btn:hover:not(.active) {
                @apply bg-blue-100 border-blue-400 shadow-md;
              }
              .pending-subtab-btn span:first-child {
                @apply mb-1;
              }
            </style>
            <style>
                .pending-subtab-btn {
                    @apply px-4 py-2 rounded-lg border border-gray-200 bg-gray-50 text-gray-700 font-medium shadow-sm transition-all duration-150;
                    margin-right: 0.25rem;
                }
                .pending-subtab-btn.active,
                .pending-subtab-btn:focus,
                .pending-subtab-btn:hover {
                    @apply bg-blue-600 text-white border-blue-600 shadow;
                }
            </style>
            <script>
                // Show/hide sub-tabs based on main tab selection
                function switchTab(tab, btn) {
                    // ...existing code for tab switching...
                    document.querySelectorAll('.tab-active').forEach(el => el.classList.remove('tab-active'));
                    btn.classList.add('tab-active');
                    // Show sub-tabs only for Pending
                    document.getElementById('pendingSubTabs').style.display = (tab === 'pending') ? 'flex' : 'none';
                    // Optionally, reset subtab highlight when switching main tab
                    if (tab === 'pending') {
                        switchPendingSubTab('other');
                    }
                }
                // Highlight sub-tabs (basic logic)
                function switchPendingSubTab(subtab) {
                    ['subtab-other', 'subtab-st', 'subtab-regular', 'subtab-sectional', 'subtab-sltr'].forEach(id => {
                        document.getElementById(id).classList.remove('active');
                    });
                    const active = {
                        'other': 'subtab-other',
                        'st': 'subtab-st',
                        'regular': 'subtab-regular',
                        'sectional': 'subtab-sectional',
                        'sltr': 'subtab-sltr'
                    }[subtab];
                    if (active) {
                        document.getElementById(active).classList.add('active');
                    }
                    // Add logic to filter table if needed
                }
                // On page load, show sub-tabs only if Pending is active
                document.addEventListener('DOMContentLoaded', function() {
                    // Detect which main tab is active
                    let activeTab = document.querySelector('.tab-active');
                    if (activeTab && activeTab.textContent.trim() === 'Pending') {
                        document.getElementById('pendingSubTabs').style.display = 'flex';
                        switchPendingSubTab('other');
                    } else {
                        document.getElementById('pendingSubTabs').style.display = 'none';
                    }
                });
            </script>