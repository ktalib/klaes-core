<!-- Instrument Type Section -->
<div class="form-section" data-section="instrument-details">
    <div class="space-y-3">
        <div class="space-y-1">
            <div class="flex items-center justify-between">
                <label class="text-sm">Registration Number</label>
                <button type="button" id="add-reg-row-btn"
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add More
                </button>
            </div>

            {{-- Row 1 — primary (bound to form-controller state, names unchanged) --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3" data-group="registration-number">
                <div>
                    <label for="serialNo" class="text-xs">Serial No.</label>
                    <input id="serialNo" name="serialNo" class="form-input text-xs py-1" placeholder="e.g. 1"
                        data-model="serialNo" value="{{ old('serialNo') }}" type="number" min="1">
                </div>
                <div>
                    <label for="pageNo" class="text-xs">Page No.</label>
                    <input id="pageNo" name="pageNo" class="form-input text-xs py-1 bg-gray-100 cursor-not-allowed"
                        placeholder="e.g. 1" data-model="pageNo" value="{{ old('pageNo') }}" type="number" min="1">
                </div>
                <div>
                    <label for="volumeNo" class="text-xs">Volume No.</label>
                    <input id="volumeNo" name="volumeNo" class="form-input text-xs py-1" placeholder="e.g. 2"
                        data-model="volumeNo" value="{{ old('volumeNo') }}" type="number" min="1">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-3">
                <div>
                    <label for="transactionDate" class="text-xs">Transaction/Commencement Date</label>
                    <input type="date" id="transactionDate" name="transactionDate" class="form-input text-xs py-1"
                        data-model="transactionDate" value="{{ old('transactionDate') }}">
                </div>
                <div>
                    <label for="deeds_date" class="text-xs">Deeds Date
                        <span class="text-gray-400 hidden" data-visible-when="property">(optional)</span>
                    </label>
                    <input id="deeds_date" name="deeds_date" type="date" class="form-input text-xs py-1"
                        data-model="deedsDate" value="{{ old('deeds_date') }}"
                        data-index-class="border-blue-300 focus:border-blue-500">
                </div>
                <div>
                    <label for="deeds_time" class="text-xs">Deeds Time
                        <span class="text-gray-400 hidden" data-visible-when="property">(optional)</span>
                    </label>
                    <input id="deeds_time" name="deeds_time" type="time" class="form-input text-xs py-1"
                        data-model="deedsTime" value="{{ old('deeds_time') }}"
                        data-index-class="border-blue-300 focus:border-blue-500">
                </div>
            </div>

            {{-- Type and Category: one field each, whatever the instrument. Their options
                 come from App\Services\InstrumentTypeCatalog and are swapped client-side by
                 applyInstrumentCatalogRules() in public/js/pra/form-controller.js, which
                 also hides a field the instrument has no answers for. The catalogue is
                 emitted once, as data, so this markup holds no second copy of the table. --}}
            <script type="application/json" data-role="instrument-catalog">@json(\App\Services\InstrumentTypeCatalog::forJs())</script>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                <div class="hidden" data-role="instrument-type-group">
                    <label for="instrument_type_value" class="text-xs">Type</label>
                    <select id="instrument_type_value" name="type_value"
                        class="form-select text-xs py-1" data-model="typeValue">
                        <option value="">Select type</option>
                    </select>
                </div>
                <div class="hidden" data-role="instrument-category-group">
                    <label for="instrument_category" class="text-xs">Category</label>
                    <select id="instrument_category" name="instrument_category"
                        class="form-select text-xs py-1" data-model="instrumentCategory">
                        <option value="">Select category</option>
                    </select>
                    <p class="mt-1 text-[11px] text-amber-700 hidden" data-role="instrument-old-note">
                        Old: the registration particulars (Serial No., Page No. and Vol No.) are optional.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3 hidden" data-role="op-fields-wrapper">
                <div>
                    {{-- The permit's Type is the shared Type field above; this hidden input is
                         what actually posts op_type, kept in step by the controller. Picking
                         LGA there turns the Grantor field into a 44-LGA picker and fixes the
                         registration particulars at 0/0/0 with no date or time — see
                         applyLgaOpTypeRules() in public/js/pra/form-controller.js. --}}
                    <input type="hidden" id="op_type" name="op_type" data-model="opType" value="">
                    <p class="mt-1 text-[11px] text-amber-700 hidden" data-role="op-lga-note">
                        Issued by a Local Government: registration number is fixed at 0/0/0 and the
                        deeds date and time are left blank.
                    </p>
                </div>
                <div>
                    <label for="op_serial_number" class="text-xs">OP Serial Number</label>
                    <input id="op_serial_number" name="op_serial_number" class="form-input text-xs py-1"
                        placeholder="Enter OP serial number" data-model="opSerialNumber"
                        oninput="this.value = this.value.replace(/[^0-9]/g, '').replace(/^0+/, '')"
                        value="{{ old('op_serial_number') }}" type="text" autocomplete="off">
                </div>
            </div>

            {{-- Deed of Purchase only: what was paid and the receipt behind it.
                 Shown by the same mechanism as the OP block above — the controller
                 toggles this wrapper off the selected transaction type. These two
                 fields also exist on the Land Registration capture form; a
                 historical purchase is backfilled here instead. --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3 hidden" data-role="purchase-fields-wrapper">
                <div>
                    <label for="consideration_amount" class="text-xs">Payment Amount</label>
                    <input id="consideration_amount" name="consideration_amount" class="form-input text-xs py-1"
                        placeholder="Purchase consideration" data-model="considerationAmount"
                        inputmode="decimal" autocomplete="off" type="text"
                        value="{{ old('consideration_amount') }}">
                </div>
                <div>
                    <label for="receipt_no" class="text-xs">Receipt No</label>
                    <input id="receipt_no" name="receipt_no" class="form-input text-xs py-1"
                        placeholder="Payment receipt number" data-model="receiptNo"
                        autocomplete="off" type="text"
                        value="{{ old('receipt_no') }}">
                </div>
            </div>

            {{-- A Certificate of Occupancy's Type is the shared Type field above, whose
                 options for this instrument are Land / Old KANGIS / New KANGIS / LGA.
                 This hidden input is only what posts cofo_type, kept in step by
                 applyInstrumentCatalogRules(). --}}
            <input type="hidden" id="cofo_type" name="cofo_type" data-model="cofoType" value="{{ old('cofo_type') }}">
            <input type="hidden" id="instrument_subtype" name="instrument_subtype" data-model="instrumentSubtype" value="">

            <div class="mt-2 p-3 bg-blue-50 border-2 border-blue-200 rounded-lg shadow-sm hidden"
                data-role="reg-preview">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500" viewBox="0 0 20 20"
                            fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                clip-rule="evenodd" />
                        </svg>
                        <span class="text-sm font-semibold text-blue-700">Registration Number:</span>
                    </div>
                    <span class="text-lg font-bold text-blue-800 tracking-wider" data-role="reg-preview-value"></span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
                <label for="landUse" class="text-sm">Land Use</label>
                <select id="landUse" name="land_use" class="form-select text-sm" data-model="landUse" required>
                    <option value="">Select land use</option>
                    <option value="RESIDENTIAL">RESIDENTIAL</option>
                    <option value="AGRICULTURAL">AGRICULTURAL</option>
                    <option value="COMMERCIAL">COMMERCIAL</option>
                    <option value="COMMERCIAL ( WARE HOUSE)">COMMERCIAL ( WARE HOUSE)</option>
                    <option value="COMMERCIAL (OFFICES)">COMMERCIAL (OFFICES)</option>
                    <option value="COMMERCIAL (PETROL FILLING STATION)">COMMERCIAL (PETROL FILLING STATION)</option>
                    <option value="COMMERCIAL (RICE PROCESSING)">COMMERCIAL (RICE PROCESSING)</option>
                    <option value="COMMERCIAL (SCHOOL)">COMMERCIAL (SCHOOL)</option>
                    <option value="COMMERCIAL (SHOPS & PUBLIC CONVINIENCE)">COMMERCIAL (SHOPS & PUBLIC CONVINIENCE)
                    </option>
                    <option value="COMMERCIAL (SHOPS AND OFFICES)">COMMERCIAL (SHOPS AND OFFICES)</option>
                    <option value="COMMERCIAL (SHOPS)">COMMERCIAL (SHOPS)</option>
                    <option value="COMMERCIAL (WAREHOUSE)">COMMERCIAL (WAREHOUSE)</option>
                    <option value="COMMERCIAL (WORKSHOP AND OFFICES)">COMMERCIAL (WORKSHOP AND OFFICES)</option>
                    <option value="COMMERCIAL AND RESIDENTIAL">COMMERCIAL AND RESIDENTIAL</option>
                    <option value="INDUSTRIAL">INDUSTRIAL</option>
                    <option value="INDUSTRIAL (SMALL SCALE)">INDUSTRIAL (SMALL SCALE)</option>
                    <option value="RESIDENTIAL AND COMMERCIAL">RESIDENTIAL AND COMMERCIAL</option>
                    <option value="RESIDENTIAL/COMMERCIAL">RESIDENTIAL/COMMERCIAL</option>
                    <option value="RESIDENTIAL/COMMERCIAL LAYOUT">RESIDENTIAL/COMMERCIAL LAYOUT</option>
                </select>
            </div>

            <div class="space-y-1">
                <label for="period" class="text-sm">Period/Tenancy</label>
                <div class="flex space-x-1">
                    <input id="period" name="period" type="number" class="form-input text-sm" placeholder="Period"
                        data-model="period" value="{{ old('period') }}">
                    <select id="periodUnit" name="periodUnit" class="form-select text-sm w-[90px]"
                        data-model="periodUnit">
                        <option value="Days" {{ old('periodUnit') === 'Days' ? 'selected' : '' }}>Days</option>
                        <option value="Months" {{ old('periodUnit') === 'Months' ? 'selected' : '' }}>Months</option>
                        <option value="Years" {{ old('periodUnit', 'Years') === 'Years' ? 'selected' : '' }}>Years
                        </option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Extra registration number rows (appended by JS) --}}
        <div id="extra-reg-rows-container" class="space-y-3 mt-1"></div>
    </div>
</div>

<script>
    // Sync Serial No to Page No and handle leading zeros
    document.addEventListener('DOMContentLoaded', function () {
        const serialNoInput = document.getElementById('serialNo');
        const pageNoInput = document.getElementById('pageNo');
        const volumeNoInput = document.getElementById('volumeNo');

        function stripLeadingZeros(value) {
            if (!value) return '';
            // Remove non-digits and strip leading zeros
            const clean = value.toString().replace(/\D/g, '');
            if (!clean) return '';
            return parseInt(clean, 10).toString();
        }

        function handleSyncAndSanitize(input, syncTo = null) {
            if (!input) return;

            // Handle typing - allow numbers
            input.addEventListener('input', function () {
                let value = this.value;
                // If it starts with '0' and has more than 1 char, or is just '0', sanitize it
                if (value.startsWith('0')) {
                    this.value = stripLeadingZeros(value);
                }
                
                if (syncTo) {
                    syncTo.value = this.value;
                    syncTo.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });

            // Handle validation on blur
            input.addEventListener('blur', function () {
                const sanitized = stripLeadingZeros(this.value);
                this.value = sanitized;
                if (syncTo) {
                    syncTo.value = sanitized;
                    syncTo.dispatchEvent(new Event('input', { bubbles: true }));
                }
                this.dispatchEvent(new Event('input', { bubbles: true }));
            });
        }

        handleSyncAndSanitize(serialNoInput, pageNoInput);
        
        if (volumeNoInput) {
            volumeNoInput.addEventListener('input', function() {
                if (this.value.startsWith('0')) {
                    this.value = stripLeadingZeros(this.value);
                }
            });
            volumeNoInput.addEventListener('blur', function() {
                this.value = stripLeadingZeros(this.value);
                this.dispatchEvent(new Event('input', { bubbles: true }));
            });
        }

        // Initial check for existing values
        if (serialNoInput && serialNoInput.value.startsWith('0')) {
            serialNoInput.value = stripLeadingZeros(serialNoInput.value);
            if (pageNoInput) {
                pageNoInput.value = serialNoInput.value;
            }
        }
        if (volumeNoInput && volumeNoInput.value.startsWith('0')) {
            volumeNoInput.value = stripLeadingZeros(volumeNoInput.value);
        }

        // ── Add More Registration Number Rows ──────────────────────────
        const addRegRowBtn = document.getElementById('add-reg-row-btn');
        const extraRegContainer = document.getElementById('extra-reg-rows-container');
        let extraRegCount = 0;

        // ── Type / Category / OP Serial Number on every extra row ──────
        // An extra registration number is a second instrument on the same file, so it
        // needs its own answers to the same three questions the primary row asks — a
        // second Occupancy Permit is routinely a different Type with its own OP serial.
        // Options come from the catalogue <script> the primary fields already read, so
        // this holds no second copy of that table.
        const instrumentCatalog = (function () {
            const node = document.querySelector('[data-role="instrument-catalog"]');
            try {
                return node ? JSON.parse(node.textContent || '[]') : [];
            } catch (err) {
                console.warn('Instrument catalogue did not parse', err);
                return [];
            }
        })();

        function normalizeInstrumentText(value) {
            return String(value == null ? '' : value).replace(/\s+/g, ' ').trim().toLowerCase();
        }

        function escapeHtml(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        /** What the currently selected instrument asks for: matched as the PHP side matches. */
        function instrumentContext() {
            const select = document.getElementById('transactionType-record');
            const needle = normalizeInstrumentText(select ? select.value : '');
            const entry = needle
                ? instrumentCatalog.find(function (e) { return needle.indexOf(e.key) !== -1; })
                : null;

            return {
                types: (entry && entry.types) || [],
                categories: (entry && entry.categories) || [],
                // Matched the way updateTransactionContext() matches, so the OP Serial
                // Number appears on an extra row exactly when it appears on the primary.
                isOp: needle.indexOf('occupancy permit') !== -1
                    || needle.indexOf('occupation permit') !== -1
                    || needle === 'op',
            };
        }

        function catalogOptionsHtml(options, placeholder, current) {
            let html = '<option value="">' + escapeHtml(placeholder) + '</option>';
            let matched = false;

            options.forEach(function (o) {
                const isCurrent = o.value === current;
                if (isCurrent) matched = true;
                html += '<option value="' + escapeHtml(o.value) + '"' + (isCurrent ? ' selected' : '') + '>' +
                    escapeHtml(o.label) + '</option>';
            });

            // A value the newly selected instrument does not offer stays selectable
            // rather than being silently dropped — the same courtesy the primary
            // field's fillCatalogSelect() extends.
            if (current && !matched) {
                html += '<option value="' + escapeHtml(current) + '" selected>' +
                    escapeHtml(current) + ' (legacy)</option>';
            }

            return html;
        }

        /** Draw (or redraw) one row's Type, Category and OP Serial Number. */
        function renderExtraRegInstrumentFields(row) {
            const holder = row.querySelector('[data-role="extra-reg-instrument"]');
            if (!holder) return;

            const idx = row.dataset.extraRegIdx;
            const ctx = instrumentContext();

            // Whatever the user already picked survives a redraw when the new
            // instrument still offers it.
            const previous = function (field) {
                const el = holder.querySelector('[data-field="' + field + '"]');
                return el ? el.value : '';
            };
            const prevType = previous('type_value');
            const prevCategory = previous('instrument_category');
            const prevSerial = previous('op_serial_number');

            let html = '';

            if (ctx.types.length || ctx.categories.length) {
                html += '<div class="grid grid-cols-1 md:grid-cols-2 gap-3">';
                if (ctx.types.length) {
                    html += '<div>' +
                        '<label class="text-xs text-gray-600">Type</label>' +
                        '<select data-field="type_value" name="extra_regs[' + idx + '][type_value]" ' +
                            'class="form-select text-xs py-1">' +
                            catalogOptionsHtml(ctx.types, 'Select type', prevType) +
                        '</select>' +
                    '</div>';
                }
                if (ctx.categories.length) {
                    html += '<div>' +
                        '<label class="text-xs text-gray-600">Category</label>' +
                        '<select data-field="instrument_category" name="extra_regs[' + idx + '][instrument_category]" ' +
                            'class="form-select text-xs py-1">' +
                            catalogOptionsHtml(ctx.categories, 'Select category', prevCategory) +
                        '</select>' +
                    '</div>';
                }
                html += '</div>';
            }

            if (ctx.isOp) {
                // Second column only, so it lands under Category exactly as it does on
                // the primary row (whose left cell holds the hidden op_type input).
                html += '<div class="grid grid-cols-1 md:grid-cols-2 gap-3">' +
                    '<div></div>' +
                    '<div>' +
                        '<label class="text-xs text-gray-600">OP Serial Number</label>' +
                        '<input data-field="op_serial_number" name="extra_regs[' + idx + '][op_serial_number]" ' +
                            'class="form-input text-xs py-1" placeholder="Enter OP serial number" type="text" ' +
                            'autocomplete="off" value="' + escapeHtml(prevSerial) + '" ' +
                            'oninput="this.value = this.value.replace(/[^0-9]/g, &quot;&quot;).replace(/^0+/, &quot;&quot;)">' +
                    '</div>' +
                '</div>';
            }

            holder.innerHTML = html;
            // An empty holder is still a child of a space-y-3 row, so it would leave a
            // phantom gap for an instrument that asks none of these three questions.
            holder.classList.toggle('hidden', html === '');
        }

        function refreshExtraRegInstrumentFields() {
            if (!extraRegContainer) return;
            extraRegContainer.querySelectorAll('[data-extra-reg-idx]').forEach(renderExtraRegInstrumentFields);
        }

        // Both routes are needed: the event covers an instrument the controller selects
        // itself (which fires no 'change'), the listener covers a plain user pick.
        document.addEventListener('pra:instrument-fields-changed', refreshExtraRegInstrumentFields);
        const instrumentTypeSelect = document.getElementById('transactionType-record');
        if (instrumentTypeSelect) {
            instrumentTypeSelect.addEventListener('change', refreshExtraRegInstrumentFields);
        }

        if (addRegRowBtn && extraRegContainer) {
            addRegRowBtn.addEventListener('click', function () {
                const idx = extraRegCount++;

                // Clone land use options from the main select so they stay in sync
                const sourceLandUse = document.getElementById('landUse');
                const landUseOptionsHtml = sourceLandUse ? sourceLandUse.innerHTML : '<option value="">Select land use</option>';

                const row = document.createElement('div');
                row.className = 'relative border border-gray-200 rounded-md p-3 bg-gray-50 space-y-3';
                row.dataset.extraRegIdx = idx;
                row.innerHTML =
                    // Remove button
                    '<button type="button" title="Remove this row" ' +
                        'class="absolute top-2 right-2 text-gray-400 hover:text-red-500" ' +
                        'onclick="this.closest(\'[data-extra-reg-idx]\').remove()">' +
                        '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">' +
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>' +
                        '</svg>' +
                    '</button>' +
                    // Row 1: Serial / Page / Volume
                    '<div class="grid grid-cols-1 md:grid-cols-3 gap-3">' +
                        '<div>' +
                            '<label class="text-xs text-gray-600">Serial No.</label>' +
                            '<input name="extra_regs[' + idx + '][serial_no]" class="form-input text-xs py-1" placeholder="e.g. 1" type="number" min="1">' +
                        '</div>' +
                        '<div>' +
                            '<label class="text-xs text-gray-600">Page No.</label>' +
                            '<input name="extra_regs[' + idx + '][page_no]" class="form-input text-xs py-1" placeholder="e.g. 1" type="number" min="1">' +
                        '</div>' +
                        '<div>' +
                            '<label class="text-xs text-gray-600">Volume No.</label>' +
                            '<input name="extra_regs[' + idx + '][volume_no]" class="form-input text-xs py-1" placeholder="e.g. 2" type="number" min="1">' +
                        '</div>' +
                    '</div>' +
                    // Row 2: Transaction/Commencement Date / Deeds Date / Deeds Time
                    '<div class="grid grid-cols-1 md:grid-cols-3 gap-3">' +
                        '<div>' +
                            '<label class="text-xs text-gray-600">Transaction/Commencement Date</label>' +
                            '<input name="extra_regs[' + idx + '][transaction_date]" type="date" class="form-input text-xs py-1">' +
                        '</div>' +
                        '<div>' +
                            '<label class="text-xs text-gray-600">Deeds Date <span class="text-gray-400">(optional)</span></label>' +
                            '<input name="extra_regs[' + idx + '][deeds_date]" type="date" class="form-input text-xs py-1">' +
                        '</div>' +
                        '<div>' +
                            '<label class="text-xs text-gray-600">Deeds Time <span class="text-gray-400">(optional)</span></label>' +
                            '<input name="extra_regs[' + idx + '][deeds_time]" type="time" class="form-input text-xs py-1">' +
                        '</div>' +
                    '</div>' +
                    // Row 3: Type / Category / OP Serial Number — filled by
                    // renderExtraRegInstrumentFields() from the shared catalogue, and
                    // re-filled whenever the instrument type changes under the row.
                    '<div data-role="extra-reg-instrument" class="space-y-3"></div>' +
                    // Row 4: Land Use / Period+Unit
                    '<div class="grid grid-cols-2 gap-3">' +
                        '<div>' +
                            '<label class="text-xs text-gray-600">Land Use</label>' +
                            '<select name="extra_regs[' + idx + '][land_use]" class="form-select text-xs py-1">' +
                                landUseOptionsHtml +
                            '</select>' +
                        '</div>' +
                        '<div>' +
                            '<label class="text-xs text-gray-600">Period/Tenancy</label>' +
                            '<div class="flex space-x-1">' +
                                '<input name="extra_regs[' + idx + '][period]" type="number" class="form-input text-xs" placeholder="Period">' +
                                '<select name="extra_regs[' + idx + '][period_unit]" class="form-select text-xs w-[90px]">' +
                                    '<option value="Days">Days</option>' +
                                    '<option value="Months">Months</option>' +
                                    '<option value="Years" selected>Years</option>' +
                                '</select>' +
                            '</div>' +
                        '</div>' +
                    '</div>';
                extraRegContainer.appendChild(row);
                renderExtraRegInstrumentFields(row);
            });
        }
    });
</script>