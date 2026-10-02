<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
    <!-- Today -->
    <div class="card">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm text-gray-500 mb-1">Today</p>
                <h2 class="text-3xl font-bold">{{ $todayCount ?? 0 }}</h2>
                <p class="text-sm text-gray-500 mt-1">Captured Today</p>
            </div>
            <div class="h-10 w-10 bg-purple-100 rounded-full flex items-center justify-center text-purple-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
            </div>
        </div>
    </div>

    <!-- Total Instruments -->
    <div class="card">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm text-gray-500 mb-1">Total Instruments</p>
                <h2 class="text-3xl font-bold">{{ $totalCount ?? 0 }}</h2>
                <p class="text-sm text-green-600 flex items-center mt-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M5 10l7-7m0 0l7 7m-7-7v18" />
                    </svg>
                    All Time
                </p>
            </div>
            <div class="h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center text-blue-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
        </div>
    </div>

    <!-- Verified -->
    <div class="card">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm text-gray-500 mb-1">Verified</p>
                <h2 class="text-3xl font-bold">{{ $verifiedCount ?? 0 }}</h2>
                <p class="text-sm text-gray-500 mt-1">Deeds Registered</p>
            </div>
            <div class="h-10 w-10 bg-green-100 rounded-full flex items-center justify-center text-green-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
        </div>
    </div>

    <!-- Pending -->
    <div class="card">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-sm text-gray-500 mb-1">Pending</p>
                <h2 class="text-3xl font-bold">{{ $pendingCount ?? 0 }}</h2>
                <p class="text-sm text-gray-500 mt-1">Awaiting Deeds Registration</p>
            </div>
            <div class="h-10 w-10 bg-yellow-100 rounded-full flex items-center justify-center text-yellow-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </div>
    </div>

</div>