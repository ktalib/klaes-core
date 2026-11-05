{{--
    The card on a row's or a file's Send / Receive button, Files and Mails tabs (user, 2026-11-04):
    Department, or In-Unit with a unit of the user's own department. The receiving officer is
    automatic: whoever receives the file.
--}}
<div x-show="movementAsk.open" x-transition.opacity class="fixed inset-0 z-50 bg-black bg-opacity-50 flex items-center justify-center p-4"
     @keydown.window.escape="movementAsk.open = false" x-cloak>
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg" role="dialog" aria-modal="true" aria-labelledby="movement-title">
        <div class="px-5 py-4 border-b border-gray-100 flex items-start justify-between gap-3">
            <div>
                <h3 id="movement-title" class="text-base font-bold text-gray-900" x-text="movementAsk.mode === 'in' ? 'Receive a file' : 'Send a file'"></h3>
                <p class="text-xs text-gray-500" x-text="(movementAsk.label ? movementAsk.label + ': ' : '') + (movementAsk.step === 'choose' ? 'Department or In-Unit?' : 'Choose the unit.')"></p>
            </div>
            <button type="button" class="p-1 text-gray-400 hover:text-gray-600" aria-label="Close" @click="movementAsk.open = false"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>

        <div x-show="movementAsk.step === 'choose'" class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-3">
            <button type="button" class="text-left rounded-xl border border-gray-300 hover:border-orange-400 hover:bg-orange-50 p-4" @click="chooseDepartment()">
                <span class="block text-sm font-bold text-gray-900">Department</span>
                <span class="block mt-1 text-xs text-gray-500">The usual movement, office to office.</span>
            </button>
            <button type="button" class="text-left rounded-xl border p-4 hover:bg-purple-50" style="border-color:#d8b4fe" @click="chooseInUnit()">
                <span class="block text-sm font-bold" style="color:#6b21a8">In-Unit</span>
                <span class="block mt-1 text-xs text-gray-500">A unit inside your department.</span>
            </button>
        </div>

        <div x-show="movementAsk.step === 'in_unit'" class="p-5 space-y-3">
            @include('secretariat_file_log.partials.unit_destination', ['state' => 'movementAsk', 'key' => 'ma', 'when' => "movementAsk.step === 'in_unit'"])
            <div class="flex items-center justify-end gap-2 pt-1">
                <button type="button" class="px-3 py-2 text-sm text-gray-600" @click="movementAsk.step = 'choose'">Back</button>
                <button type="button" class="px-4 py-2 text-white text-sm font-semibold rounded-lg disabled:opacity-50" style="background:#7e22ce"
                        :disabled="!movementValid()" @click="confirmInUnit()">Continue</button>
            </div>
        </div>
    </div>
</div>
