<div class="bg-gradient-to-br from-white via-gray-50/30 to-white rounded-2xl shadow-xl border border-gray-200/50 overflow-hidden backdrop-blur-sm mx-auto max-w-7xl">
    <div class="px-4 sm:px-6 py-5">
        <div class="border-b border-gray-200/60 pb-3">
            <nav class="flex flex-wrap items-center justify-center gap-1 sm:gap-2 md:gap-3 lg:gap-4">
                {{-- Overview --}}
                <a href="{{ route('sectionaltitling.index') }}" class="group relative flex flex-col items-center py-4 px-4 min-w-[80px] transition-all duration-300 {{ request()->routeIs('sectionaltitling.index') ? 'border-b-3 border-blue-500 bg-blue-50/50' : 'hover:bg-gradient-to-t hover:from-blue-50 hover:to-transparent rounded-lg' }}">
                    <div class="flex items-center justify-center w-10 h-10 {{ request()->routeIs('sectionaltitling.index') ? 'bg-blue-500 text-white shadow-lg' : 'bg-blue-100 text-blue-600 group-hover:bg-blue-500 group-hover:text-white group-hover:shadow-md' }} rounded-full mb-2 transition-all duration-300 transform group-hover:scale-110">
                        <i data-lucide="home" class="w-5 h-5"></i>
                    </div>
                    <span class="text-xs font-semibold {{ request()->routeIs('sectionaltitling.index') ? 'text-blue-700' : 'text-gray-600 group-hover:text-blue-700' }} text-center leading-tight transition-colors duration-300">Overview</span>
                </a>

                {{-- Customer Care --}}
                <a href="{{ route('customer-care.index') }}" class="group relative flex flex-col items-center py-4 px-4 min-w-[80px] transition-all duration-300 {{ request()->routeIs('customer-care.index') ? 'border-b-3 border-green-500 bg-green-50/50' : 'hover:bg-gradient-to-t hover:from-green-50 hover:to-transparent rounded-lg' }}">
                    <div class="flex items-center justify-center w-10 h-10 {{ request()->routeIs('customer-care.index') ? 'bg-green-500 text-white shadow-lg' : 'bg-green-100 text-green-600 group-hover:bg-green-500 group-hover:text-white group-hover:shadow-md' }} rounded-full mb-2 transition-all duration-300 transform group-hover:scale-110">
                        <i data-lucide="user" class="w-5 h-5"></i>
                    </div>
                    <span class="text-xs font-semibold {{ request()->routeIs('customer-care.index') ? 'text-green-700' : 'text-gray-600 group-hover:text-green-700' }} text-center leading-tight transition-colors duration-300">Customer<br>Care</span>
                </a>

                {{-- Entities --}}
                <a href="{{ route('programmes.entity') }}" class="group relative flex flex-col items-center py-4 px-4 min-w-[80px] transition-all duration-300 {{ request()->routeIs('programmes.entity') ? 'border-b-3 border-indigo-500 bg-indigo-50/50' : 'hover:bg-gradient-to-t hover:from-indigo-50 hover:to-transparent rounded-lg' }}">
                    <div class="flex items-center justify-center w-10 h-10 {{ request()->routeIs('programmes.entity') ? 'bg-indigo-500 text-white shadow-lg' : 'bg-indigo-100 text-indigo-600 group-hover:bg-indigo-500 group-hover:text-white group-hover:shadow-md' }} rounded-full mb-2 transition-all duration-300 transform group-hover:scale-110">
                        <i data-lucide="building" class="w-5 h-5"></i>
                    </div>
                    <span class="text-xs font-semibold {{ request()->routeIs('programmes.entity') ? 'text-indigo-700' : 'text-gray-600 group-hover:text-indigo-700' }} text-center leading-tight transition-colors duration-300">Entities</span>
                </a>

                {{-- Mother Applications --}}
                <a href="{{ route('sectionaltitling.mother') }}" class="group relative flex flex-col items-center py-4 px-4 min-w-[80px] transition-all duration-300 {{ request()->routeIs('sectionaltitling.mother') ? 'border-b-3 border-purple-500 bg-purple-50/50' : 'hover:bg-gradient-to-t hover:from-purple-50 hover:to-transparent rounded-lg' }}">
                    <div class="flex items-center justify-center w-10 h-10 {{ request()->routeIs('sectionaltitling.mother') ? 'bg-purple-500 text-white shadow-lg' : 'bg-purple-100 text-purple-600 group-hover:bg-purple-500 group-hover:text-white group-hover:shadow-md' }} rounded-full mb-2 transition-all duration-300 transform group-hover:scale-110">
                        <i data-lucide="file-text" class="w-5 h-5"></i>
                    </div>
                    <span class="text-xs font-semibold {{ request()->routeIs('sectionaltitling.mother') ? 'text-purple-700' : 'text-gray-600 group-hover:text-purple-700' }} text-center leading-tight transition-colors duration-300">Mother<br>Applications</span>
                </a>

                {{-- Secondary Applications --}}
            <a href="{{ route('sectionaltitling.secondary') }}" class="group relative flex flex-col items-center py-4 px-4 min-w-[80px] transition-all duration-300 {{ request()->routeIs('sectionaltitling.secondary') ? 'border-b-3 border-blue-500 bg-blue-50/50' : 'hover:bg-gradient-to-t hover:from-blue-50 hover:to-transparent rounded-lg' }}">
    <div class="flex items-center justify-center w-10 h-10 {{ request()->routeIs('sectionaltitling.secondary') ? 'bg-blue-500 text-white shadow-lg' : 'bg-blue-100 text-blue-600 group-hover:bg-blue-500 group-hover:text-white group-hover:shadow-md' }} rounded-full mb-2 transition-all duration-300 transform group-hover:scale-110">
        <i data-lucide="files" class="w-5 h-5"></i>
    </div>
    <span class="text-xs font-semibold {{ request()->routeIs('sectionaltitling.secondary') ? 'text-blue-700' : 'text-gray-600 group-hover:text-blue-700' }} text-center leading-tight transition-colors duration-300">Secondary<br>Applications</span>
</a>

                {{-- Planning --}}
                <a href="{{route('programmes.approvals.planning_recomm')}}?url=view" class="group relative flex flex-col items-center py-4 px-4 min-w-[80px] transition-all duration-300 hover:bg-gradient-to-t hover:from-red-50 hover:to-transparent rounded-lg">
                    <div class="flex items-center justify-center w-10 h-10 bg-red-100 text-red-600 group-hover:bg-red-500 group-hover:text-white group-hover:shadow-md rounded-full mb-2 transition-all duration-300 transform group-hover:scale-110">
                        <i data-lucide="calendar" class="w-5 h-5"></i>
                    </div>
                    <span class="text-xs font-semibold text-gray-600 group-hover:text-red-700 text-center leading-tight transition-colors duration-300">Planning</span>
                </a>

                {{-- Survey --}}
                <a href="{{ route('attribution.index') }}" class="group relative flex flex-col items-center py-4 px-4 min-w-[80px] transition-all duration-300 {{ request()->routeIs('attribution.index') ? 'border-b-3 border-orange-500 bg-orange-50/50' : 'hover:bg-gradient-to-t hover:from-orange-50 hover:to-transparent rounded-lg' }}">
                    <div class="flex items-center justify-center w-10 h-10 {{ request()->routeIs('attribution.index') ? 'bg-orange-500 text-white shadow-lg' : 'bg-orange-100 text-orange-600 group-hover:bg-orange-500 group-hover:text-white group-hover:shadow-md' }} rounded-full mb-2 transition-all duration-300 transform group-hover:scale-110">
                        <i data-lucide="ruler" class="w-5 h-5"></i>
                    </div>
                    <span class="text-xs font-semibold {{ request()->routeIs('attribution.index') ? 'text-orange-700' : 'text-gray-600 group-hover:text-orange-700' }} text-center leading-tight transition-colors duration-300">Survey</span>
                </a>

                {{-- Map --}}
                <a href="{{ route('map.index') }}" class="group relative flex flex-col items-center py-4 px-4 min-w-[80px] transition-all duration-300 {{ request()->routeIs('map.index') ? 'border-b-3 border-teal-500 bg-teal-50/50' : 'hover:bg-gradient-to-t hover:from-teal-50 hover:to-transparent rounded-lg' }}">
                    <div class="flex items-center justify-center w-10 h-10 {{ request()->routeIs('map.index') ? 'bg-teal-500 text-white shadow-lg' : 'bg-teal-100 text-teal-600 group-hover:bg-teal-500 group-hover:text-white group-hover:shadow-md' }} rounded-full mb-2 transition-all duration-300 transform group-hover:scale-110">
                        <i data-lucide="map" class="w-5 h-5"></i>
                    </div>
                    <span class="text-xs font-semibold {{ request()->routeIs('map.index') ? 'text-teal-700' : 'text-gray-600 group-hover:text-teal-700' }} text-center leading-tight transition-colors duration-300">Map</span>
                </a>
            </nav>
        </div>
    </div>
</div>

<br>
<br>