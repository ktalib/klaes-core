<div id="detterment-tab" class="tab-content {{ request()->has('deeds') ? 'active' : '' }}">
    <form id="deeds-form" method="POST">
        @csrf
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
            <div class="p-4 border-b">
                <h3 class="text-sm font-medium">Deeds</h3>
                <p class="text-xs text-gray-500"></p>
            </div>
            <input type="hidden" name="application_id" value="{{ $application->id }}">
            <input type="hidden" name="fileno" value="{{ $application->fileno }}">
            <div class="p-4 space-y-4">
                <div class="grid grid-cols-3 gap-4">
                    <div class="space-y-2">
                        <label for="serial-no" class="text-xs font-medium block">
                            Serial No
                        </label>
                        <input
                            id="serial-no"
                            name="serial_no"
                            type="text"
                            value="{{ $deeds->serial_no ?? '' }}"
                            class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100 text-gray-500 cursor-not-allowed"
                            disabled
                        >
                    </div>
                    <div class="space-y-2">
                        <label for="page-no" class="text-xs font-medium block">
                            Page No
                        </label>
                        <input
                            id="page-no"
                            name="page_no"
                            type="text"
                            value="{{ $deeds->page_no ?? '' }}"
                            class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100 text-gray-500 cursor-not-allowed"
                            disabled
                        >
                    </div>
                    <div class="space-y-2">
                        <label for="volume-no" class="text-xs font-medium block">
                            Volume No
                        </label>
                        <input
                            id="volume-no"
                            name="volume_no"
                            type="text"
                            value="{{ $deeds->volume_no ?? '' }}"
                            class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100 text-gray-500 cursor-not-allowed"
                            disabled
                        >
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label for="deeds-time" class="text-xs font-medium block">
                            Deeds Time
                        </label>
                        <input
                            id="deeds-time"
                            name="deeds_time"
                            type="text"
                            value="{{ $deeds->deeds_time ?? '' }}"
                            class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100 text-gray-500 cursor-not-allowed"
                            disabled
                        >
                    </div>
                    <div class="space-y-2">
                        <label for="deeds-date" class="text-xs font-medium block">
                            Deeds Date
                        </label>
                        <input
                            id="deeds-date"
                            name="deeds_date"
                            type="text"
                            value="{{ $deeds->deeds_date ?? '' }}"
                            class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100 text-gray-500 cursor-not-allowed"
                            disabled
                        >
                    </div>
                </div>
                <hr class="my-4">

                <div class="flex justify-between items-center">
                    <div class="flex gap-2">
                        <a
                            href="{{ route('sectionaltitling.primary') }}"
                            class="flex items-center px-3 py-1 text-xs bg-white text-black p-2 border border-gray-500 rounded-md hover:bg-gray-800"
                        >
                            <i data-lucide="undo-2" class="w-3.5 h-3.5 mr-1.5"></i>
                            Back
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>