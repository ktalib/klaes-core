<div x-data="instrumentTypesManager()" @open-instrument-types-modal.window="openModal()" class="relative z-50"
    aria-labelledby="modal-title" role="dialog" aria-modal="true" x-show="isOpen" x-cloak style="display: none;">

    <!-- Backdrop -->
    <div x-show="isOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>

    <!-- Modal Panel -->
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div x-show="isOpen" x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-4xl">

                <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">

                    <!-- Modal Header -->
                    <div class="flex justify-between items-center mb-6 pb-4 border-b">
                        <h3 class="text-lg font-semibold leading-6 text-gray-900" id="modal-title">
                            <span
                                x-text="viewMode === 'list' ? 'Manage Instrument Types' : (formMode === 'add' ? 'Add Instrument Type' : 'Edit Instrument Type')"></span>
                        </h3>
                        <button @click="closeModal()" class="text-gray-400 hover:text-gray-500 focus:outline-none">
                            <span class="sr-only">Close</span>
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>

                    <!-- VIEW: List of Types -->
                    <div x-show="viewMode === 'list'" class="space-y-4">
                        <div class="flex justify-between items-center">
                            <p class="text-sm text-gray-500">Manage the types of legal instruments available for
                                registration.</p>
                            <button @click="openAddForm()"
                                class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 text-sm flex items-center gap-2 transition-colors">
                                <i class="fas fa-plus"></i> Add New Type
                            </button>
                        </div>

                        {{-- overflow-x-auto, not overflow-hidden: a long name or
                             description used to widen the table past the modal and
                             the Vault and Actions columns were simply clipped off
                             the right edge with no way to reach them. --}}
                        <div class="border rounded-md overflow-x-auto shadow-sm">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            S/N</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Name</th>
                                        <th
                                            class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Vault (Serial/Page/Volume)</th>
                                        <th
                                            class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <template x-for="(type, index) in types" :key="type.id">
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 align-top"
                                                x-text="index + 1"></td>
                                            {{-- The name stays on one line; the
                                                 description wraps inside a fixed
                                                 width. Previously the whole cell
                                                 was nowrap, so a long description
                                                 stretched the row and pushed the
                                                 columns after it out of view. --}}
                                            <td class="px-6 py-4 text-sm font-medium text-gray-900 align-top">
                                                <div class="flex flex-col max-w-xs">
                                                    <span class="whitespace-nowrap" x-text="type.name"></span>
                                                    <span class="text-xs text-gray-400 break-words"
                                                        x-text="type.description"></span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right align-top">
                                                <div class="flex flex-col items-end">
                                                    <span
                                                        x-text="`${type.last_serial || '-'}/${type.last_page || '-'}/${type.last_volume || '-'}`"></span>
                                                    <template
                                                        x-if="type.vault_source && type.vault_source !== type.name">
                                                        <span
                                                            class="text-xs text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full mt-1"
                                                            x-text="'Using ' + type.vault_source"></span>
                                                    </template>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium align-top">
                                                <button @click="openEditForm(type)"
                                                    class="text-indigo-600 hover:text-indigo-900 mr-3 font-semibold">Edit</button>
                                                <button @click="deleteType(type.id)"
                                                    class="text-red-600 hover:text-red-900 font-semibold">Delete</button>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr x-show="types.length === 0">
                                        <td colspan="4" class="px-6 py-8 text-center text-gray-500 text-sm">
                                            No instrument types found. Click "Add New Type" to create one.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- VIEW: Add/Edit Form -->
                    <div x-show="viewMode === 'form'" class="space-y-6">
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div class="col-span-2 sm:col-span-1">
                                <label for="typeName" class="block text-sm font-medium text-gray-700">Name <span
                                        class="text-red-500">*</span></label>
                                <input type="text" id="typeName" x-model="formData.name"
                                    placeholder="e.g. Power of Attorney"
                                    :disabled="formMode === 'edit' && ['Power of Attorney', 'Deed of Surrender and Release', 'Deed of Assignment', 'Deed of Gift'].includes(formData.name)"
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm disabled:bg-gray-100 disabled:text-gray-500" />
                                <template
                                    x-if="formMode === 'edit' && ['Power of Attorney', 'Deed of Surrender and Release', 'Deed of Assignment', 'Deed of Gift'].includes(formData.name)">
                                    <p class="mt-1 text-xs text-amber-600">
                                        <i class="fas fa-lock mr-1"></i> System Protected Type (Cannot be renamed)
                                    </p>
                                </template>
                            </div>
                            <div class="col-span-2 sm:col-span-1">
                                <label for="typeDescription"
                                    class="block text-sm font-medium text-gray-700">Description</label>
                                <input type="text" id="typeDescription" x-model="formData.description"
                                    placeholder="Optional description"
                                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
                            </div>
                        </div>

                        <!-- Vault Fields -->
                        <div class="border-t pt-4">
                            <div class="flex items-center gap-2 mb-2">
                                <label class="block text-sm font-medium text-gray-700">Registration Vault
                                    Configuration</label>
                                <span class="text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full">Automated
                                    Numbering</span>
                            </div>

                            <!-- Caution Alert for Edit Mode -->
                            <div x-show="formMode === 'edit'"
                                class="bg-yellow-50 border-l-4 border-yellow-400 p-3 mb-4 rounded-r-md">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-exclamation-triangle text-yellow-500"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-yellow-700">
                                            <strong>Caution:</strong> You are modifying the <strong>Last Used
                                                Confirmation</strong>.
                                            The system will increment from these values for the <em>next</em>
                                            registration.
                                            Only change this if you need to recalibrate the numbering sequence.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div x-show="formMode === 'add'"
                                class="bg-gray-50 p-3 mb-4 rounded-md border border-gray-200">
                                <p class="text-xs text-gray-600">
                                    Optionally initialize the starting point for this instrument type.
                                    If left blank, it will start from Volume 1, Page 1, Serial 1.
                                </p>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label for="lastSerial"
                                        class="block text-xs font-medium text-gray-500 uppercase tracking-wide">Last
                                        Serial</label>
                                    <input type="number" id="lastSerial" x-model="formData.last_serial" placeholder="0"
                                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                                </div>
                                <div>
                                    <label for="lastPage"
                                        class="block text-xs font-medium text-gray-500 uppercase tracking-wide">Last
                                        Page</label>
                                    <input type="number" id="lastPage" x-model="formData.last_page" placeholder="1"
                                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                                </div>
                                <div>
                                    <label for="lastVolume"
                                        class="block text-xs font-medium text-gray-500 uppercase tracking-wide">Last
                                        Volume</label>
                                    <input type="number" id="lastVolume" x-model="formData.last_volume" placeholder="1"
                                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                                </div>
                            </div>
                        </div>

                        {{--
                            Where this register began in KLAES.

                            Not a starting point for issuing — nothing is ever numbered from
                            these two boxes, and the vault above is untouched by them. They are
                            the FLOOR of the "Fill a missing registration number" list on the
                            capture screen, which runs from the first number this office issued
                            in the book up to the number the vault stands on now.

                            Left blank, that floor is guessed from the lowest registration still
                            in the system — and a guess cannot tell "never issued" from "issued
                            and then deleted", so a hole at the very start of a register stays
                            invisible. Deed of Mortgage is that shape today: four registrations
                            running 89/89/22 to 92/92/22 with nothing between them, so nothing
                            is offered.

                            Fill these from the paper book: the first entry KLAES itself wrote,
                            not the first entry in the book.
                        --}}
                        <div class="border-t pt-4">
                            <div class="flex items-center gap-2 mb-2">
                                <label class="block text-sm font-medium text-gray-700">Register Begins At</label>
                                <span class="text-xs bg-gray-100 text-gray-700 px-2 py-0.5 rounded-full">Optional</span>
                            </div>

                            <div class="bg-gray-50 p-3 mb-4 rounded-md border border-gray-200">
                                <p class="text-xs text-gray-600">
                                    The first number this office issued in this register — the point the
                                    manual workflow handed over. It is used only to work out which numbers
                                    are <strong>missing</strong> from the register; it never issues a number
                                    and never moves the vault above.
                                    <span class="block mt-1">
                                        Leave blank and the system infers it from the earliest registration it
                                        still holds, which cannot reveal a number deleted from the very start
                                        of the book.
                                    </span>
                                </p>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label for="startSerial"
                                        class="block text-xs font-medium text-gray-500 uppercase tracking-wide">First
                                        Serial</label>
                                    <input type="number" min="1" id="startSerial" x-model="formData.start_serial"
                                        placeholder="e.g. 89"
                                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                                </div>
                                <div>
                                    <label for="startVolume"
                                        class="block text-xs font-medium text-gray-500 uppercase tracking-wide">First
                                        Volume</label>
                                    <input type="number" min="1" id="startVolume" x-model="formData.start_volume"
                                        placeholder="e.g. 22"
                                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-indigo-500 focus:border-indigo-500" />
                                </div>
                                <div class="flex items-end">
                                    <p class="text-xs text-gray-500 pb-2"
                                        x-text="formData.start_serial && formData.start_volume
                                            ? `Missing numbers will be listed from ${formData.start_serial}/${formData.start_serial}/${formData.start_volume} onwards.`
                                            : 'Not recorded — the starting point will be inferred.'"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="border-t pt-4 flex justify-end gap-3">
                            <button @click="cancelForm()"
                                class="bg-white text-gray-700 px-4 py-2 rounded-md hover:bg-gray-50 text-sm border border-gray-300 font-medium shadow-sm transition-colors">
                                Cancel
                            </button>
                            <button @click="submitForm()"
                                class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 text-sm font-medium shadow-sm transition-colors flex items-center gap-2">
                                <span x-text="formMode === 'add' ? 'Create Type' : 'Save Changes'"></span>
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>