{{-- Property Record Transaction Modal for File Indexing --}}
<!-- Alpine.js is already loaded in the parent view -->
@php
    $transactionTypeOptions = [];
    try {
        $results = \Illuminate\Support\Facades\DB::connection('sqlsrv')->select("SELECT DISTINCT RTRIM(LTRIM([InstrumentName])) as InstrumentName FROM [klas].[dbo].[InstrumentTypes] WHERE [InstrumentName] IS NOT NULL ORDER BY InstrumentName");
        foreach ($results as $row) {
            $name = $row->InstrumentName;
            if (!empty($name)) {
                $transactionTypeOptions[] = $name;
            }
        }
    } catch (\Exception $e) {
        // Fallback in case of DB error
        $transactionTypeOptions = ['Other'];
    }

    if (!in_array('Other', $transactionTypeOptions)) {
        $transactionTypeOptions[] = 'Other';
    }
@endphp

{{-- Submission Summary + File Snapshot cards.

     Loaded from this partial rather than from each page, because every screen that
     can capture a transaction includes this modal but only some of them load
     create-indexing-dialog.js. The file is a self-contained IIFE that only assigns
     to window, so a page that also loads it directly (create_file_tracker_page)
     re-running it costs nothing. --}}
<script src="{{ asset('js/fileindexing/file-snapshot-card.js') }}?v={{ @filemtime(public_path('js/fileindexing/file-snapshot-card.js')) }}"></script>

<script>
    // Routing rule + history endpoint for the File History Summary card.
    // COFO_TRANSACTION_TYPES / OCCUPANCY_PERMIT_MARKER are read straight off
    // PropertyRecordController so the card's predicted destination and the table the server
    // actually writes to cannot drift apart.
    const FH_COFO_TRANSACTION_TYPES = @json(\App\Http\Controllers\PropertyRecordController::COFO_TRANSACTION_TYPES);
    const FH_OP_MARKER = @json(\App\Http\Controllers\PropertyRecordController::OCCUPANCY_PERMIT_MARKER);
    // The CORE Legal Search report engine - the same payload the LS timeline, the Property
    // Timeline, the PHS portal and the online report are all built from.
    const FH_HISTORY_URL = @json(route('legal_search.print.data'));
</script>
  

<div id="property-transaction-dialog" class="property-transaction-overlay hidden"
    style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 10000; display: none; align-items: center; justify-content: center; background-color: rgba(0, 0, 0, 0.5);">
    <div class="dialog-content property-form-content" style="max-width: 900px; max-height: 90vh; overflow-y: auto;">
        <div class="flex justify-between items-center mb-4">
            <h2 id="transaction-form-title" class="text-xl font-bold">Add  Property Transaction Details</h2>
            <button id="close-property-transaction-form" class="text-gray-500 hover:text-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <div x-data="{
            isUpdateMode: false,
            // Transaction fields
            transactions: [{
                id: 1,
                recordId: null,
                source: '',
                transactionType: '',
                // Filled in only when Transaction Type is 'Other': the instrument as it
                // reads on the paper. It is what gets SAVED as the transaction type --
                // the word 'Other' names no instrument and would be dead on a timeline.
                otherTransactionType: '',
                status: 'Normal',
                transactionDate: '',
                opType: '',
                // Old OP / New OP. Asked only for Resettlement and Direct Allocation,
                // and blank means the question was never put — see showOpCategory().
                // The one visible Type answer. Copied into opType / cofoType by
                // handleTypeChange(), which is where each instrument's own column is fed.
                typeValue: '',
                // The one visible Category answer, whatever the instrument.
                instrumentCategory: '',
                opSerialNumber: '',
                // Deed of Purchase: what was paid, and the receipt behind it.
                considerationAmount: '',
                receiptNo: '',
                cofoType: '',
                serialNo: '',
                pageNo: '',
                volumeNo: '',
                regDate: new Date().toISOString().split('T')[0],
                regTime: '09:00',
                landUse: '',
                period: '',
                periodUnit: 'Years',
                comments: '',
                firstParty: '',
                secondParty: '',
                coFirstParty: '',
                thirdParty: '',
                includeCoSurrenderor: false,
                includeThirdParty: false,
                includeCoMortgagor: false,
                includeMortgagor3: false,
                mortgagor3: '',
                includeFourthParty: false,
                fourthParty: '',
                fifthParty: '',
                // Set when the server holds this row back as a possible duplicate.
                duplicateInfo: null,
                forceSave: false,
                // Declared on EVERY transaction shape, not just the one addTransaction()
                // builds: x-bind turns an undefined dotted expression into an empty string,
                // and an empty string on a boolean attribute means present -- so leaving this
                // out disabled the :disabled it drives. Only the gap check ever sets it true.
                totRequired: false,
                totLegLabel: ''
            }],

            // File indexing data (will be populated from outside)
            fileIndexingData: {
                file_number: '',
                lga: '',
                district: '',
                state: 'KANO',
                plot_no: '',
                tp_no: '',
                property_description: ''
            },

            lgas: [],
            districts: [],

            // ---- File History Summary card state -------------------------------------
            // See fileindexing/partial/file_history_summary_card.blade.php for the design.
            fhSummaryOpen: true,
            fhSummaryLoading: false,
            fhSummaryError: false,
            // Rows from LegalSearchService::buildPrintReport for the selected file.
            fhOnFileRows: [],
            // Which file number fhOnFileRows was fetched for, so a re-render never shows
            // one file's history against another file's captured transactions.
            fhLoadedFileNo: '',
            // Post-save outcome keyed by transaction block id -> SAVED | UPDATED | HELD_BACK.
            fhSaveOutcome: {},

            // District Selection Logic
            districtSelection: '',
            customDistrict: '',

            syncDistrictSelection() {
                if (!this.fileIndexingData.district) {
                    this.districtSelection = '';
                    this.customDistrict = '';
                    return;
                }
                
                // If districts are loaded, check if value exists
                if (this.districts.length > 0) {
                    if (this.districts.includes(this.fileIndexingData.district)) {
                        this.districtSelection = this.fileIndexingData.district;
                        this.customDistrict = '';
                    } else {
                        this.districtSelection = 'Other';
                        this.customDistrict = this.fileIndexingData.district;
                    }
                } else {
                    // Fallback if not loaded yet - assume it might be existing
                    this.districtSelection = this.fileIndexingData.district; 
                }
            },

            handleDistrictChange() {
                if (this.districtSelection === 'Other') {
                    this.fileIndexingData.district = this.customDistrict;
                } else {
                    this.fileIndexingData.district = this.districtSelection;
                }
                this.updatePropertyDescription();
            },

            updateFromCustom() {
                if (this.districtSelection === 'Other') {
                    this.fileIndexingData.district = this.customDistrict;
                    this.updatePropertyDescription();
                }
            },

            // Party labels for different transaction types
            partyLabels: {
                'Power of Attorney': { first: 'Grantor', second: 'Grantee' },
                'Deed of Assignment': { first: 'Assignor', second: 'Assignee' },
                'ST Assignment': { first: 'Assignor', second: 'Assignee' },
                'Deed of Mortgage': { first: 'Mortgagor', second: 'Mortgagee' },
                'Tripartite Mortgage': { first: 'Mortgagor', second: 'Mortgagee' },
                'Certificate of Occupancy': { first: 'Grantor', second: 'Grantee' },
                'ST Certificate of Occupancy': { first: 'Grantor', second: 'Grantee' },
                'SLTR Certificate of Occupancy': { first: 'Grantor', second: 'Grantee' },
                'Customary Right of Occupancy': { first: 'Grantor', second: 'Grantee' },
                'Deed of Transfer': { first: 'Transferor', second: 'Transferee' },
                'Deed of Gift': { first: 'Donor', second: 'Donee' },
                'Deed of Lease': { first: 'Lessor', second: 'Lessee' },
                'Deed of Sub Lease': { first: 'Lessor', second: 'Lessee' },
                'Deed of Sub Under Lease': { first: 'Lessor', second: 'Lessee' },
                'Indenture of Lease': { first: 'Lessor', second: 'Lessee' },
                'Quarry Lease': { first: 'Lessor', second: 'Lessee' },
                'Private Lease': { first: 'Lessor', second: 'Lessee' },
                'Building Lease': { first: 'Lessor', second: 'Lessee' },
                'Tenancy Agreement': { first: 'Landlord', second: 'Tenant' },
                'Deed of Release': { first: 'Releasor', second: 'Releasee' },
                'Deed of Surrender': { first: 'Surrenderor', second: 'Surrenderee' },
                'Deed of Surrender and Release': { first: 'Surrenderor', second: 'Surrenderee' },
                'Letter of Administration': { first: 'Administrator', second: 'Beneficiary' },
                'Certificate of Purchase': { first: 'Vendor', second: 'Purchaser' },
                'Occupancy Permit (OP)': { first: 'Grantor', second: 'Grantee' },
                'OCCUPANCY PERMIT (OP)': { first: 'Grantor', second: 'Grantee' },
                'OCCUPANCY PERMIT': { first: 'Grantor', second: 'Grantee' }
            },

            // The Type and Category each instrument can carry, straight from
            // App\Services\InstrumentTypeCatalog -- the one copy of that table.
            // Echoed through Blade's JSON directive, which escapes for an HTML
            // attribute -- and this x-data is one.
            instrumentCatalog: @js(\App\Services\InstrumentTypeCatalog::forJs()),

            transactionTypeOptions: @js($transactionTypeOptions),
            additionalTransactionTypes: [],

            registerTransactionType(type) {
                if (!type) {
                    return;
                }
                const normalized = normalizePropertyTransactionType(type);
                if (!normalized) {
                    return;
                }
                if (!this.transactionTypeOptions.includes(normalized) && !this.additionalTransactionTypes.includes(normalized)) {
                    this.additionalTransactionTypes = [...this.additionalTransactionTypes, normalized];
                }
            },

            ensureTransactionTypes(types) {
                if (!Array.isArray(types)) {
                    return;
                }
                types.forEach(type => this.registerTransactionType(type));
            },

            // Add new transaction
            addTransaction() {
                this.transactions.push({
                    id: this.transactions.length + 1,
                    recordId: null,
                    source: '',
                    transactionType: '',
                    otherTransactionType: '',
                    status: 'Normal',
                    transactionDate: '',
                    typeValue: '',
                    instrumentCategory: '',
                    opSerialNumber: '',
                    // Deed of Purchase: what was paid, and the receipt behind it.
                    considerationAmount: '',
                    receiptNo: '',
                    cofoType: '',
                    serialNo: '',
                    pageNo: '',
                    volumeNo: '',
                    regDate: new Date().toISOString().split('T')[0],
                    regTime: '09:00',
                    landUse: '',
                    period: '',
                    periodUnit: 'Years',
                    firstParty: '',
                    secondParty: '',
                    coFirstParty: '',
                    thirdParty: '',
                    includeCoSurrenderor: false,
                    includeThirdParty: false,
                    includeCoMortgagor: false,
                    includeFourthParty: false,
                    fourthParty: '',
                    duplicateInfo: null,
                    forceSave: false,
                    // Declared up front so Alpine tracks them from the first render — a
                    // block only becomes 'required' when the ownership-gap check adds it.
                    totRequired: false,
                    totLegLabel: ''
                });
            },

            // Remove transaction
            removeTransaction(index) {
                if (this.transactions.length > 1) {
                    this.transactions.splice(index, 1);
                }
            },

            // Get party labels for a transaction
            getPartyLabels(transactionType) {
                const labels = this.partyLabels[transactionType] || { first: 'Grantor', second: 'Grantee' };
                return {
                    first: 'Party 1 / ' + labels.first,
                    second: 'Party 2 / ' + labels.second
                };
            },

            // Human-readable label + badge color for the staging table a transaction was
            // backfilled from, so a mixed-source timeline (file_history_staging, CofO_staging,
            // pra, deed_registrations) is visually distinguishable in the edit form.
            sourceLabels: {
                fh: { label: 'File History', color: 'bg-slate-100 text-slate-700 border-slate-300' },
                file_history: { label: 'File History', color: 'bg-slate-100 text-slate-700 border-slate-300' },
                cofo: { label: 'CofO', color: 'bg-emerald-100 text-emerald-700 border-emerald-300' },
                pra: { label: 'PRA', color: 'bg-amber-100 text-amber-700 border-amber-300' },
                deeds: { label: 'Deed Registration', color: 'bg-purple-100 text-purple-700 border-purple-300' }
            },

            getSourceInfo(source) {
                if (!source) {
                    return { label: 'New', color: 'bg-blue-100 text-blue-700 border-blue-300' };
                }
                return this.sourceLabels[source] || { label: source, color: 'bg-gray-100 text-gray-700 border-gray-300' };
            },

            // Check if transaction type is government
            isGovernmentTransaction(transactionType) {
                if (!transactionType) return false;
                
                // Standardize the check: uppercase, trim, and remove extra internal spaces
                const normalized = String(transactionType).toUpperCase().trim().replace(/\s+/g, ' ');
                
                const govKeys = [
                    'CERTIFICATE OF OCCUPANCY', 
                    'ST CERTIFICATE OF OCCUPANCY', 
                    'SLTR CERTIFICATE OF OCCUPANCY', 
                    'CUSTOMARY RIGHT OF OCCUPANCY', 
                    'OCCUPATION PERMIT', 
                    'OCCUPANCY PERMIT', 
                    'OCCUPANCY PERMIT (OP)',
                    'OP'
                ];
                
                const result = govKeys.includes(normalized);
                if (result) return true;
                
                // Partial match fallback for safety
                if (normalized.includes('OCCUPANCY PERMIT') || normalized.includes('CERTIFICATE OF OCCUPANCY') || normalized === 'OP') {
                    return true;
                }
                
                return false;
            },

            isOPTransaction(transactionType) {
                return normalizePropertyTransactionType(transactionType) === 'Occupancy Permit (OP)';
            },

            // A Deed of Purchase records what was paid. Matched on the phrase
            // rather than an exact name so both spellings in the lookup table --
            // Deed of Purchase, and the older Deed of Purchase Registration --
            // reveal the fields.
            //
            // NOTE: this whole component is the value of one x-data HTML
            // attribute. A double quote anywhere inside it, comments included,
            // closes that attribute early and dumps the rest of the script onto
            // the page as visible text. Use single quotes here, never double.
            isPurchaseTransaction(transactionType) {
                return String(transactionType || '').toLowerCase().includes('deed of purchase');
            },

            // 'Other' is a prompt, not an instrument: it reveals the field where the
            // officer names the instrument the catalogue does not list.
            isOtherTransaction(transactionType) {
                return normalizePropertyTransactionType(transactionType) === 'Other';
            },

            // The 44 Kano Local Governments, in the exact wording saved as party_1.
            // Rendered from App\Services\KanoLgaDirectory so this list, the PRA form and the
            // create-indexing OP section can never drift apart.
            // Echoed through Blade's ESCAPING echo, not its raw JSON directive: this sits
            // inside the x-data attribute, so the quotes in the JSON must arrive as HTML
            // entities and be decoded by the browser before Alpine evaluates the object.
            // Raw quotes close the attribute at the first array element and spill the rest
            // of this object onto the page as visible text.
            kanoLgaAuthorities: {{ json_encode(app(\App\Services\KanoLgaDirectory::class)->fullNames()) }},

            // ANY instrument whose chosen Type is LGA was issued by a Local Government, so
            // party 1 is which Local Government -- a Plot Allocation Letter, a Certificate
            // of Occupancy and a Right of Occupancy just as much as an Occupancy Permit.
            //
            // Recognised by the catalogue LABEL, not the stored value: the four instruments
            // spell it differently ('LGA', 'LGA CofO'), and a new LGA option added to the
            // catalogue tomorrow is picked up here without a second edit.
            isLgaType(transaction) {
                const value = String(transaction && transaction.typeValue || '');
                if (!value) return false;

                return this.typeOptionsFor(transaction).some(
                    (o) => o.value === value && String(o.label).trim().toUpperCase() === 'LGA'
                );
            },

            // The narrower case: an Occupancy Permit granted by a Local Government. An LGA
            // registers nothing in the State deeds registry, so THIS one also has no serial,
            // page, volume, date or time to enter -- rules that belong to the permit, not to
            // every LGA-issued instrument, which is why they stay keyed to this and not to
            // isLgaType().
            isLgaOpType(transaction) {
                if (!transaction || !this.isOPTransaction(transaction.transactionType)) return false;
                return this.isLgaType(transaction);
            },

            // The catalogue entry for whatever instrument this block holds, matched the
            // way the PHP side matches: normalised, and the KEY as a substring -- which is
            // how 'ST Certificate of Occupancy' resolves to the CofO entry, and
            // 'OCCUPANCY PERMIT' to the permit one.
            catalogEntryFor(transaction) {
                const needle = String(transaction && transaction.transactionType || '')
                    .trim().replace(/\s+/g, ' ').toLowerCase();
                if (!needle) return null;
                return (this.instrumentCatalog || []).find((e) => needle.indexOf(e.key) !== -1) || null;
            },

            typeOptionsFor(transaction) {
                return (this.catalogEntryFor(transaction) || {}).types || [];
            },

            categoryOptionsFor(transaction) {
                return (this.catalogEntryFor(transaction) || {}).categories || [];
            },

            // False for a stored value the catalogue no longer lists, so the select can go
            // on offering it as '(legacy)' rather than silently blanking an old record.
            isCatalogType(transaction) {
                const value = String(transaction && transaction.typeValue || '');
                return this.typeOptionsFor(transaction).some((o) => o.value === value);
            },

            // Category 'Old' on an Occupancy Permit: the paper predates the practice that
            // produced a serial, page and volume, so its particulars are optional.
            isOldCategory(transaction) {
                if (!transaction || !this.isOPTransaction(transaction.transactionType)) return false;
                return String(transaction.instrumentCategory || '').trim().toUpperCase() === 'OLD';
            },

            // Which column the one visible Type field is stored in. Each instrument keeps
            // the column it has always used, so no existing value has to move.
            typeColumnFor(transaction) {
                if (!transaction) return 'instrument_subtype';
                if (this.isOPTransaction(transaction.transactionType)) return 'op_type';
                if (this.isCofOTransaction(transaction.transactionType)) return 'cofo_type';
                return 'instrument_subtype';
            },

            // Copy the answer into that column, then let the OP rules run off opType
            // exactly as they did when OP Type was its own control.
            handleTypeChange(transaction) {
                const column = this.typeColumnFor(transaction);

                transaction.opType = column === 'op_type' ? transaction.typeValue : '';
                transaction.cofoType = column === 'cofo_type' ? transaction.typeValue : '';

                if (column === 'op_type') {
                    // handleOpTypeChange() does the party-1 rules AND the permit's own
                    // 0/0/0 registration stamp, which no other instrument gets.
                    this.handleOpTypeChange(transaction);
                    return;
                }

                this.applyLgaGrantorRules(transaction);
            },

            // Party 1 follows the LGA picker on every instrument that shows one. Kept
            // apart from handleOpTypeChange() because the 0/0/0 stamp beside it there is
            // the permit's rule, not a rule about being LGA-issued.
            applyLgaGrantorRules(transaction) {
                const current = String(transaction.firstParty || '').trim();

                if (this.isLgaType(transaction)) {
                    // The State is not one of the 44 -- make the officer name the council.
                    if (!this.kanoLgaAuthorities.includes(current)) {
                        transaction.firstParty = '';
                    }
                    return;
                }

                // No longer LGA-issued: hand party 1 back to whatever the instrument says.
                if (this.kanoLgaAuthorities.includes(current)) {
                    transaction.firstParty = this.isGovernmentTransaction(transaction.transactionType)
                        ? 'KANO STATE GOVERNMENT'
                        : '';
                }
            },


            // Serial No. and Volume No. are required on an Occupancy Permit -- EXCEPT on
            // the two kinds that never reached the State deeds registry. An LGA permit
            // never went in, and an 'Old' one predates the practice that produced a
            // serial, page and volume. A BLANK Category still counts as required, so the
            // permits captured before the field existed keep the rule they were saved
            // under.
            opRegNoRequired(transaction) {
                if (!transaction || !this.isOPTransaction(transaction.transactionType)) return false;
                return !this.isLgaOpType(transaction) && !this.isOldCategory(transaction);
            },

            // Registration particulars are fixed and uneditable for Right of Occupancy and
            // for an LGA-issued OP alike. Both stamp 0/0/0 and leave date/time blank.
            regParticularsLocked(transaction) {
                if (!transaction) return false;
                return this.isROFOTransaction(transaction.transactionType) || this.isLgaOpType(transaction);
            },

            // Applies (or undoes) the LGA rules when the OP Type changes.
            handleOpTypeChange(transaction) {
                if (this.isLgaOpType(transaction)) {
                    // Registration number 0/0/0, no date, no time — the state registry never saw it.
                    transaction.serialNo = '0';
                    transaction.pageNo = '0';
                    transaction.volumeNo = '0';
                    transaction.regDate = '';
                    transaction.regTime = '';
                    // The grantor is no longer the State; make the officer pick which LGA.
                    if (!this.kanoLgaAuthorities.includes(transaction.firstParty)) {
                        transaction.firstParty = '';
                    }
                    return;
                }

                // Switched away from LGA: clear the 0/0/0 placeholder so the real particulars
                // can be typed, and hand party 1 back to whatever the transaction type dictates.
                if (transaction.serialNo === '0' && transaction.pageNo === '0' && transaction.volumeNo === '0') {
                    transaction.serialNo = '';
                    transaction.pageNo = '';
                    transaction.volumeNo = '';
                }
                if (this.kanoLgaAuthorities.includes(transaction.firstParty)) {
                    transaction.firstParty = this.isGovernmentTransaction(transaction.transactionType)
                        ? 'KANO STATE GOVERNMENT'
                        : '';
                }
            },

            // Detects Certificate of Occupancy transactions (and its ST/SLTR variants),
            // which require a CofO Type (Land / KANGIS Old & New / SLTR / ST).
            isCofOTransaction(transactionType) {
                if (!transactionType) return false;
                return /CERTIFICATE OF OCCUPANCY/i.test(String(transactionType));
            },

            // Mirrors the canonical list in resources/views/partials/cofo_type_options.blade.php.
            // Used only to decide whether a stored value needs a legacy <option>.
            isKnownCofoType(value) {
                return ['Land CofO', 'KANGIS CofO - Old', 'KANGIS CofO - New', 'SLTR CofO', 'ST CofO']
                    .includes(String(value || ''));
            },

            // Detects Right of Occupancy / Customary Right of Occupancy transactions.
            // For these, Registration Number defaults to 0/0/0 and Reg Date/Time are not applicable.
            isROFOTransaction(transactionType) {
                if (!transactionType) return false;
                const normalized = String(transactionType).toUpperCase().trim().replace(/\s+/g, ' ');
                return normalized === 'RIGHT OF OCCUPANCY'
                    || normalized === 'CUSTOMARY RIGHT OF OCCUPANCY'
                    || normalized === 'STATUTORY RIGHT OF OCCUPANCY';
            },

            // Get registration number preview
            getRegNumberPreview(transaction) {
                const parts = [transaction.serialNo, transaction.pageNo, transaction.volumeNo].filter(Boolean);
                return parts.length > 0 ? parts.join('/') : '';
            },

            // Auto-sync page number with serial number
            syncPageNo(transaction) {
                transaction.pageNo = transaction.serialNo;
            },

            // Get property description handling both indexing and edit page formats
            getPropertyDescription(data) {
                // If it's already built, return it (but check for N/A)
                if (data.property_description && data.property_description !== 'N/A') {
                    return data.property_description;
                }
                
                // Indexing page format: uses location field
                if (data.location && data.location !== 'N/A') {
                    return data.location;
                }
                
                // Fallback: construct from district and lga if available
                const district = data.district || '';
                const lga = data.lga || '';
                const state = data.state || 'KANO';
                
                if (district || lga) {
                    return [district, lga, state].filter(Boolean).join(', ').toUpperCase();
                }
                
                return 'N/A';
            },

            // Update property description from components
            updatePropertyDescription() {
                const parts = [];
                if (this.fileIndexingData.district) parts.push(this.fileIndexingData.district);
                if (this.fileIndexingData.lga) parts.push(this.fileIndexingData.lga);
                if (this.fileIndexingData.state || 'KANO') parts.push(this.fileIndexingData.state || 'KANO');

                this.fileIndexingData.property_description = parts.join(', ').toUpperCase();
            },

            // Open the global File Number selector so a file number can be chosen
            // directly inside this modal (used when the modal is opened standalone
            // without a file number pre-selected on the indexing form).
            selectFileNumber() {
                const applyResult = (result) => {
                    const fn = (result && (result.fileNumber || result.file_number))
                        ? String(result.fileNumber || result.file_number).trim()
                        : '';
                    if (!fn) return;

                    this.fileIndexingData.file_number = fn;

                    const rec = (result && result.record) ? result.record : {};
                    const clean = (v) => (!v || v === 'N/A') ? '' : v;

                    if (result && result.file_title && !clean(this.fileIndexingData.file_title)) {
                        this.fileIndexingData.file_title = result.file_title;
                    }
                    if (!clean(this.fileIndexingData.lga)) this.fileIndexingData.lga = rec.lga || rec.lgsaOrCity || '';
                    if (!clean(this.fileIndexingData.district)) this.fileIndexingData.district = rec.district || rec.districtName || '';
                    if (!clean(this.fileIndexingData.plot_no)) this.fileIndexingData.plot_no = rec.plot_number || rec.plot_no || '';
                    if (!clean(this.fileIndexingData.tp_no)) this.fileIndexingData.tp_no = rec.tp_no || '';

                    this.updatePropertyDescription();
                    if (typeof this.syncDistrictSelection === 'function') {
                        this.syncDistrictSelection();
                    }

                    // A new file means a new history - clear the previous outcome badges and
                    // kick off the (slow, abortable) fetch. Not awaited: the card already
                    // renders the captured rows, so nothing here blocks the operator.
                    this.fhSaveOutcome = {};
                    this.fhLoadFileHistory(false);
                };

                if (typeof window.GlobalFileNoModal !== 'undefined'
                    && typeof window.GlobalFileNoModal.open === 'function') {
                    window.GlobalFileNoModal.open({ callback: applyResult });
                    return;
                }

                // Fallback when the global selector isn't present on the page.
                const manual = window.prompt('Enter File Number');
                if (manual && manual.trim()) {
                    applyResult({ fileNumber: manual.trim() });
                }
            },

            // ================= File History Summary card ==============================

            /**
             * Which table a captured transaction will be written to.
             *
             * Mirrors PropertyRecordController's routing decision exactly, reading its
             * constants via a Blade json handoff rather than restating them, so the card
             * cannot predict a destination the server would not actually use.
             *
             *   _source === 'deeds' (on an existing row) -> deed_registrations
             *   CofO type, source unset or 'cofo'        -> CofO_staging
             *   OP type,   source unset or 'pra'         -> pra
             *   EXISTING row, routed by its own _source   -> pra / CofO_staging / deed_registrations
             *   otherwise                                -> file_history_staging
             *
             * That fourth rule is easy to miss and was wrong here at first: an EXISTING row
             * whose type is neither a CofO nor an OP still updates in the table it came from,
             * not in file_history_staging. A Right of Occupancy loaded from pra updates in
             * pra. Without this the card promised 'File History' while the server wrote to
             * 'pra' - see the matching guard in PropertyRecordController's update branch.
             */
            fhResolveDestination(transaction) {
                const type = String(transaction.transactionType || transaction.instrumentType || '').trim();
                const source = String(transaction.source || '').trim().toLowerCase() || null;
                const hasRecord = !!transaction.recordId;

                const isCofO = FH_COFO_TRANSACTION_TYPES.includes(type);
                const isOP = type.toLowerCase().includes(FH_OP_MARKER.toLowerCase());

                if (hasRecord && source === 'deeds') return 'deed_registrations';
                if (isCofO && (source === null || source === 'cofo')) return 'CofO_staging';
                if (isOP && (source === null || source === 'pra')) return 'pra';

                // Existing row with no type-specific handler: it stays where it came from.
                if (hasRecord) {
                    if (source === 'pra') return 'pra';
                    if (source === 'cofo') return 'CofO_staging';
                }

                return 'file_history_staging';
            },

            fhDestinationLabel(key) {
                return ({
                    file_history_staging: 'File History',
                    CofO_staging: 'CofO',
                    pra: 'PRA',
                    deed_registrations: 'Deed Reg.',
                })[key] || key;
            },

            fhDestinationColor(key) {
                return ({
                    file_history_staging: '#3b82f6',
                    CofO_staging: '#10b981',
                    pra: '#f59e0b',
                    deed_registrations: '#8b5cf6',
                })[key] || '#94a3b8';
            },

            /**
             * A loose identity for a transaction, used only to tell whether a row already on
             * the file is the same dealing as one in the form - so an edited row is listed
             * ONCE (as EDITING) instead of twice.
             *
             * Deliberately fuzzy: the report's rows carry no source ids to join on. Parties
             * and instrument are normalised hard (case, punctuation and honorifics vary between
             * capture sources: 'Alh. Tijjani' vs 'ALH TIJJANI'). A false miss is safe - the row
             * simply shows twice; a false match would HIDE a genuinely new transaction, so the
             * registration number is folded in whenever both sides carry one.
             */
            fhSignature(instrument, p1, p2, regNo) {
                const norm = (v) => String(v || '')
                    .toUpperCase()
                    .replace(/\b(ALH|ALHAJI|ALHAJA|HAJIYA|HAJIA|MAL|MALLAM|MR|MRS|MISS|DR|ENGR)\b\.?/g, '')
                    .replace(/[^A-Z0-9]+/g, '');
                const reg = String(regNo || '').replace(/[^0-9]/g, '');
                return [norm(instrument), norm(p1), norm(p2), (reg && reg !== '000') ? reg : ''].join('|');
            },

            /** Transaction blocks that carry enough detail to be worth listing. */
            fhCapturedRows() {
                const rows = [];
                (this.transactions || []).forEach((t, index) => {
                    const instrument = String(t.transactionType || t.instrumentType || '').trim();
                    const p1 = String(t.firstParty || '').trim();
                    const p2 = String(t.secondParty || '').trim();
                    // An untouched blank block is not a transaction yet.
                    if (!instrument && !p1 && !p2) return;

                    const destinationKey = this.fhResolveDestination(t);
                    const regNo = [t.serialNo, t.pageNo, t.volumeNo].filter(Boolean).join('/');
                    const outcome = this.fhSaveOutcome[t.id] || null;

                    let status = t.recordId ? 'EDITING' : 'NEW';
                    if (outcome) status = outcome;

                    rows.push({
                        key: 'form-' + (t.id != null ? t.id : index),
                        derived: false,
                        destinationKey: destinationKey,
                        destinationLabel: this.fhDestinationLabel(destinationKey),
                        color: this.fhDestinationColor(destinationKey),
                        status: status,
                        // 'Existing' rather than 'Updated': the operator is being told what
                        // was already on the file versus what this save added, not which SQL
                        // verb ran. A re-saved row is still an existing one.
                        statusLabel: ({
                            NEW: 'New',
                            EDITING: 'Existing',
                            SAVED: 'Saved',
                            UPDATED: 'Existing',
                            HELD_BACK: 'Held back',
                        })[status] || status,
                        instrument: instrument || 'Instrument',
                        parties: [p1, p2, t.thirdParty].filter(Boolean).join(' \u2192 ') || '\u2014',
                        // Kept unjoined as well: the ownership-gap check walks party_1 and
                        // party_2 individually, and cannot un-join the display string.
                        p1: p1,
                        p2: p2,
                        regNo: (regNo && regNo !== '//') ? regNo : '',
                        date: t.transactionDate || t.regDate || '',
                        sortTs: this.fhSortTimestamp(t.transactionDate || t.regDate || ''),
                        signature: this.fhSignature(instrument, p1, p2, regNo),
                    });
                });
                return rows;
            },

            /**
             * The card's rows: everything already on the file, plus everything being captured,
             * with rows that are the same dealing collapsed into one.
             */
            fhSummaryRows() {
                const captured = this.fhCapturedRows();
                const capturedSignatures = new Set(
                    captured.map((r) => r.signature).filter((sig) => sig.replace(/\|/g, '') !== '')
                );

                const clean = (v) => (!v || v === '-') ? '' : v;
                const onFile = [];

                (this.fhOnFileRows || []).forEach((row, index) => {
                    const instrument = clean(String(row.instrument_type || '').trim());
                    const p1 = clean(String(row.grantor || '').trim());
                    const p2 = clean(String(row.grantee || '').trim());

                    // Synthetic report rows are context, not capturable records.
                    const derived = ['File Commissioning', 'Temporary File'].includes(row.source_table)
                        || String(row.instrument_type || '').toLowerCase().indexOf('commissioning') !== -1;

                    const signature = this.fhSignature(instrument, p1, p2, clean(row.reg_no));
                    // Already represented by a form block - the form block wins, because it is
                    // the editable one and shows the real destination.
                    if (!derived && capturedSignatures.has(signature)) return;

                    const known = ['file_history_staging', 'CofO_staging', 'pra', 'deed_registrations'];
                    const destinationKey = derived
                        ? null
                        : (known.indexOf(row.source_table) !== -1 ? row.source_table : null);

                    onFile.push({
                        key: 'ls-' + index,
                        derived: derived,
                        destinationKey: destinationKey,
                        destinationLabel: destinationKey ? this.fhDestinationLabel(destinationKey) : '',
                        color: derived ? '#94a3b8' : this.fhDestinationColor(destinationKey),
                        status: 'ON_FILE',
                        statusLabel: derived ? 'Derived' : 'On file',
                        instrument: instrument || 'Instrument',
                        parties: [p1, p2, clean(row.party_3)].filter(Boolean).join(' \u2192 ') || '\u2014',
                        p1: p1,
                        p2: p2,
                        regNo: (clean(row.reg_no) && row.reg_no !== '0/0/0') ? row.reg_no : '',
                        date: clean(row.transaction_date) || clean(row.reg_date) || '',
                        sortTs: this.fhSortTimestamp(clean(row.transaction_date) || clean(row.reg_date) || ''),
                        signature: signature,
                    });
                });

                // One chronological list. Derived rows (File Commissioning) stay pinned at the
                // top regardless of date: they mark the opening of the file, and the LS timeline
                // orders them the same way - a 1995 commissioning still precedes a 1994 CofO
                // there. Rows with no usable date sort to the end rather than to 1970.
                const all = onFile.concat(captured);
                const derived = all.filter((r) => r.derived);
                const dated = all.filter((r) => !r.derived);

                dated.sort((a, b) => {
                    const at = a.sortTs;
                    const bt = b.sortTs;
                    if (at === null && bt === null) return 0;
                    if (at === null) return 1;
                    if (bt === null) return -1;
                    return at - bt;
                });

                return derived.concat(dated);
            },

            /**
             * A sortable timestamp for a transaction date, or null when there is nothing usable.
             *
             * These dates arrive in several shapes: an ISO date from a form input
             * (2009-02-12), a formatted string from the report (Dec 29, 1994), a SQL Server
             * datetime string (May 11 1995 12:00AM), or a bare year the report falls back to
             * for legacy files (1995). Date.parse handles the first three; the bare year is
             * matched explicitly, because Date.parse reads a 4-digit string as a year in some
             * engines and as nonsense in others.
             */
            fhSortTimestamp(value) {
                const raw = String(value || '').trim();
                if (raw === '' || raw === '-') return null;

                // ISO first, built explicitly in UTC. Date.parse treats 'YYYY-MM-DD' as UTC but
                // 'May 11 1995' as LOCAL, so letting it handle both puts the two formats a
                // timezone apart and can flip the order of same-day rows.
                const iso = raw.match(/^(\d{4})-(\d{2})-(\d{2})/);
                if (iso) return Date.UTC(Number(iso[1]), Number(iso[2]) - 1, Number(iso[3]));

                const bareYear = raw.match(/^(\d{4})$/);
                if (bareYear) return Date.UTC(Number(bareYear[1]), 0, 1);

                // 'May 11 1995 12:00AM' - SQL Server's string form. Date.parse rejects it
                // outright without a space before AM/PM, which silently demoted these rows to
                // the bare-year fallback and lost their month and day.
                const spaced = raw.replace(/(\d)(AM|PM)\b/i, '$1 $2');
                const named = spaced.match(/^([A-Za-z]{3,9})\s+(\d{1,2}),?\s+(\d{4})/);
                if (named) {
                    const months = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'];
                    const m = months.indexOf(named[1].slice(0, 3).toLowerCase());
                    if (m !== -1) return Date.UTC(Number(named[3]), m, Number(named[2]));
                }

                const parsed = Date.parse(spaced);
                if (!isNaN(parsed)) return parsed;

                // Last resort: a year embedded in a messier string.
                const anyYear = raw.match(/(1[89]\d{2}|20\d{2})/);
                return anyYear ? Date.UTC(Number(anyYear[1]), 0, 1) : null;
            },

            fhDestinationTally() {
                const counts = {};
                this.fhCapturedRows().forEach((r) => {
                    if (!r.destinationKey) return;
                    counts[r.destinationKey] = (counts[r.destinationKey] || 0) + 1;
                });
                return Object.keys(counts).map((key) => ({
                    key: key,
                    count: counts[key],
                    label: this.fhDestinationLabel(key),
                }));
            },

            fhSummaryHeadline() {
                const captured = this.fhCapturedRows();
                const onFile = this.fhSummaryRows().filter((r) => r.status === 'ON_FILE').length;
                const fresh = captured.filter((r) => r.status === 'NEW' || r.status === 'SAVED').length;
                const editing = captured.filter((r) => r.status === 'EDITING' || r.status === 'UPDATED').length;

                const parts = [];
                if (onFile) parts.push(onFile + ' on file');
                if (editing) parts.push(editing + ' being edited');
                if (fresh) parts.push(fresh + ' new');
                return parts.length ? '\u00b7 ' + parts.join(' \u00b7 ') : '';
            },

            /**
             * Load the file's real history from the core Legal Search report engine.
             *
             * That call costs ~3-5s, so it is deliberately kept off the typing path: it runs
             * once per file number, is cached for the life of the page, aborts a superseded
             * request when the user switches file, and never blocks Save. The card is already
             * showing the captured rows by the time this resolves, so the latency is only ever
             * a context section filling in late - never an empty card.
             */
            async fhLoadFileHistory(force) {
                const fileNo = String(
                    this.fileIndexingData.file_number || this.fileIndexingData.temp_file_no || ''
                ).trim();

                if (!fileNo) {
                    this.fhOnFileRows = [];
                    this.fhLoadedFileNo = '';
                    this.fhSummaryError = false;
                    return;
                }
                if (!force && this.fhLoadedFileNo === fileNo) return;

                window._fhHistoryCache = window._fhHistoryCache || {};
                if (!force && window._fhHistoryCache[fileNo]) {
                    this.fhOnFileRows = window._fhHistoryCache[fileNo];
                    this.fhLoadedFileNo = fileNo;
                    this.fhSummaryError = false;
                    return;
                }

                // Supersede any in-flight request - the user has moved to another file.
                if (window._fhHistoryAbort) {
                    try { window._fhHistoryAbort.abort(); } catch (e) { /* already settled */ }
                }
                const controller = new AbortController();
                window._fhHistoryAbort = controller;

                this.fhSummaryLoading = true;
                this.fhSummaryError = false;

                try {
                    const url = FH_HISTORY_URL + '?file_number=' + encodeURIComponent(fileNo);
                    // The endpoint is behind `auth`, so the session cookie has to go with it.
                    const res = await fetch(url, {
                        signal: controller.signal,
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json' },
                    });
                    if (!res.ok) throw new Error('HTTP ' + res.status);

                    // An expired session redirects to the login page, which is a 200 of HTML.
                    // Detect that by content type rather than letting json() throw a parse
                    // error that reads like a bug in the endpoint.
                    const contentType = res.headers.get('content-type') || '';
                    if (contentType.indexOf('application/json') === -1) {
                        throw new Error('Expected JSON, got ' + (contentType || 'no content-type'));
                    }

                    const body = await res.json();
                    const rows = (body && body.data && body.data.rows) ? body.data.rows : [];

                    window._fhHistoryCache[fileNo] = rows;
                    this.fhOnFileRows = rows;
                    this.fhLoadedFileNo = fileNo;
                } catch (err) {
                    // An abort is a supersede, not a failure - leave the previous state alone.
                    if (err && err.name === 'AbortError') return;
                    console.warn('File History Summary: could not load history', err);
                    this.fhOnFileRows = [];
                    this.fhLoadedFileNo = fileNo;
                    this.fhSummaryError = true;
                } finally {
                    if (window._fhHistoryAbort === controller) {
                        window._fhHistoryAbort = null;
                        this.fhSummaryLoading = false;
                    }
                }
            },

            /**
             * The File History Summary rendered as a self-contained HTML block, for the
             * confirmation dialog (which lives in SweetAlert, outside Alpine's reach).
             *
             * The rendering itself lives in fhRenderSummaryHtml() in the script block below,
             * NOT here: this method body sits inside the x-data attribute, which is delimited
             * by a double quote, so a single double quote anywhere in it ends the attribute
             * early and dumps the rest of the component onto the page as visible text.
             * Generating HTML needs quotes, so it belongs in a script block instead.
             */
            /**
             * Scroll a transaction block into view and flash it.
             *
             * The form lists transactions in the file's CHRONOLOGICAL order, not the order
             * they were added, so a newly captured record usually lands mid-list rather than
             * at the bottom. Scrolling to the end then shows a different instrument, which
             * reads as the record having gone missing. Clicking its row in the summary takes
             * you straight to it.
             */
            fhJumpToTransaction(rowKey) {
                const id = String(rowKey || '').replace(/^form-/, '');
                if (!id) return;
                const el = document.getElementById('fh-txn-' + id);
                if (!el) return;

                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                el.classList.remove('fh-txn-flash');
                // Reflow so the animation restarts when the same row is clicked twice.
                void el.offsetWidth;
                el.classList.add('fh-txn-flash');
            },

            fhSummaryHtml() {
                return (typeof window.fhRenderSummaryHtml === 'function')
                    ? window.fhRenderSummaryHtml(this.fhSummaryRows(), this.fhDestinationTally())
                    : '';
            },

            /**
             * Apply a save response to the card: mark what persisted, then refetch the history
             * so the ON FILE layer reflects the rows that were just written.
             */
            fhApplySaveOutcome(response) {
                const data = (response && response.data) || {};
                const created = new Set((data.created_ids || []).map(String));
                const updated = new Set((data.updated_ids || []).map(String));

                // A held-back duplicate identifies itself by its POSITION in the submitted
                // array (splitDuplicateTransactions returns `index`), not by an id - the same
                // key handleDeferredDuplicates() uses. The submit payload is a straight 1:1
                // map of this.transactions, so the index still lines up here.
                const heldIndexes = new Set(
                    ((response && response.duplicates) || [])
                        .map((d) => (d && d.index != null) ? Number(d.index) : null)
                        .filter((i) => i !== null)
                );

                const outcome = {};
                (this.transactions || []).forEach((t, index) => {
                    if (heldIndexes.has(index)) { outcome[t.id] = 'HELD_BACK'; return; }
                    const recordId = (t.recordId != null) ? String(t.recordId) : null;
                    if (recordId && updated.has(recordId)) { outcome[t.id] = 'UPDATED'; return; }
                    if (recordId && created.has(recordId)) { outcome[t.id] = 'SAVED'; return; }
                    // Nothing to match on: fall back to what the block was going to do.
                    outcome[t.id] = t.recordId ? 'UPDATED' : 'SAVED';
                });

                this.fhSaveOutcome = outcome;
                this.fhSummaryOpen = true;

                // The history is now stale - drop the cache entry and reload it.
                const fileNo = String(
                    this.fileIndexingData.file_number || this.fileIndexingData.temp_file_no || ''
                ).trim();
                if (fileNo && window._fhHistoryCache) delete window._fhHistoryCache[fileNo];
                this.fhLoadFileHistory(true);
            },

            clearFileNumber() {
                this.fileIndexingData.file_number = '';
                this.fileIndexingData.temp_file_no = '';
                this.fhOnFileRows = [];
                this.fhLoadedFileNo = '';
                this.fhSaveOutcome = {};
                this.fhSummaryError = false;
            },

            // Auto-fill first party for government transactions
            handleTransactionTypeChange(transaction) {
                // First see if it's already a valid option to avoid breaking selection
                const originalValue = transaction.transactionType;
                const normalized = normalizePropertyTransactionType(originalValue);
                
                // If normalized is different but both are valid, stick to what's in the list
                if (normalized !== originalValue) {
                    if (this.transactionTypeOptions.includes(normalized) || this.additionalTransactionTypes.includes(normalized)) {
                        transaction.transactionType = normalized;
                    } else if (!this.transactionTypeOptions.includes(originalValue) && !this.additionalTransactionTypes.includes(originalValue)) {
                         transaction.transactionType = normalized;
                    }
                }
                
                this.registerTransactionType(transaction.transactionType);

                // The typed name belongs to 'Other' alone. Left behind on a row that has
                // since become a real instrument, it would be submitted in its place.
                if (!this.isOtherTransaction(transaction.transactionType)) {
                    transaction.otherTransactionType = '';
                }

                const isGov = this.isGovernmentTransaction(transaction.transactionType);
                console.log('Transaction Type changed:', {
                    value: transaction.transactionType,
                    isGov: isGov
                });

                // An LGA-issued OP is a government transaction whose grantor is NOT the State,
                // so it keeps whichever Local Government was picked instead of being reset.
                if (isGov && !this.isLgaType(transaction)) {
                    transaction.firstParty = 'KANO STATE GOVERNMENT';
                } else if (!isGov) {
                    if (transaction.firstParty === 'KANO STATE GOVERNMENT') {
                        transaction.firstParty = '';
                    }
                }

                // Reset extra fields when type changes
                transaction.includeCoSurrenderor = false;
                transaction.includeThirdParty = false;
                transaction.includeCoMortgagor = false;
                transaction.includeFourthParty = false;
                transaction.coFirstParty = '';
                transaction.thirdParty = '';
                transaction.fourthParty = '';

                // Not a plot allocation letter any more: drop the issuing body, and the
                // party 1 it wrote, so a deed does not inherit a grantor from a letter.
                // A Type or Category the new instrument does not offer is not an answer to
                // ITS question, so it goes rather than riding along invisibly.
                if (!this.typeOptionsFor(transaction).some((o) => o.value === transaction.typeValue)) {
                    transaction.typeValue = '';
                }
                if (!this.categoryOptionsFor(transaction).some((o) => o.value === transaction.instrumentCategory)) {
                    transaction.instrumentCategory = '';
                }

                if (!this.isOPTransaction(transaction.transactionType)) {
                    transaction.opSerialNumber = '';

                    // opType is cleared by the caller; undo the LGA registration stamp here so a
                    // deed does not inherit 0/0/0 from a permit the row used to be.
                    if (transaction.serialNo === '0' && transaction.pageNo === '0' && transaction.volumeNo === '0') {
                        transaction.serialNo = '';
                        transaction.pageNo = '';
                        transaction.volumeNo = '';
                    }
                }

                if (!this.isCofOTransaction(transaction.transactionType)) {
                    transaction.cofoType = '';
                }

                // Party 1 last, once typeValue has settled: a Local Government stays on the
                // row only while the new instrument's Type is still LGA.
                this.applyLgaGrantorRules(transaction);

                // Right of Occupancy: Registration Number is fixed at 0/0/0 and Reg Date/Time are not applicable.
                if (this.isROFOTransaction(transaction.transactionType)) {
                    transaction.serialNo = '0';
                    transaction.pageNo = '0';
                    transaction.volumeNo = '0';
                    transaction.regDate = '';
                    transaction.regTime = '';
                }
            },

            // Submit form
            /* ═══════════════════════════════════════════════════════════════════════
             * Missing Transfer of Title detection  (advisory, conversion files only)
             *
             * The chain says the title moved from one person to another, but no Transfer
             * of Title records the move. Every LINK is examined, not just the last owner
             * against the applicant — a break at the top of a chain
             * (Owner 1 → ??? → Owner 2 → Owner 3 → Owner 4) is invisible to a check that
             * only looks at the end of it, and those are the ones that survive for years.
             *
             * ADVISORY BY DESIGN. It reveals the gaps, pre-fills the transfers, and then
             * lets the officer save anyway. On legacy conversion files the chain is
             * incomplete because the PAPER is incomplete, and some gaps cannot be closed
             * from the file at all; blocking would stop indexing on data nobody present
             * can fix. See docs/plans/2026-09-01-transaction-history-tot-gap.md.
             * ═══════════════════════════════════════════════════════════════════════ */

            /** Set once the sections have been revealed, so a second Submit saves. */
            totGapAcknowledged: false,
            totGapLegs: [],

            /** Conversion files only — the one gate this whole feature sits behind. */
            totIsConversionFile() {
                const fileNo = String(
                    this.fileIndexingData.file_number || this.fileIndexingData.temp_file_no || ''
                ).trim();
                return /^\s*CON-/i.test(fileNo);
            },

            /**
             * Does this instrument move ownership?
             *
             * Mirrors TitleHolderResolver::movesOwnership() — the rule Legal Search, the
             * holder lines and Match OP all use. A mortgage, caveat, lease or
             * recertification does NOT move a title, so it opens no leg.
             */
            totMovesOwnership(instrument) {
                const t = String(instrument || '').toLowerCase();
                if (!t) return false;

                const never = ['mortgage', 'surrender', 'release', 'caveat', 'search',
                    'recertification', 'change of purpose', 'sub-lease', 'sublease',
                    'power of attorney', 'lease'];
                if (never.some((n) => t.indexOf(n) !== -1)) return false;

                const moves = ['assignment', 'transfer of title', 'conveyance', 'gift',
                    'vesting', 'sale'];
                return moves.some((m) => t.indexOf(m) !== -1);
            },

            /** A grant opens a chain; it never continues one. */
            totIsGrant(instrument) {
                const t = String(instrument || '').toLowerCase();
                return t.indexOf('occupancy permit') !== -1
                    || t.indexOf('right of occupancy') !== -1
                    || t.indexOf('certificate of occupancy') !== -1
                    || t.indexOf('statutory') !== -1;
            },

            /**
             * An issuing authority, never a private party a title is transferred TO.
             *
             * KANGIS is here by name because LegalSearchService STAMPS every non-Ministry
             * recertification with party_1 = Kano Geographic Information Service. That
             * string contains none of government / ministry / governor / kano state, so
             * without this line every recertified conversion file asked the officer to
             * record a Transfer of Title into KANGIS.
             */
            totIsGovernment(name) {
                const n = String(name || '').toLowerCase();
                return n.indexOf('government') !== -1
                    || n.indexOf('ministry') !== -1
                    || n.indexOf('governor') !== -1
                    || n.indexOf('kano state') !== -1
                    || n.indexOf('geographic information') !== -1
                    || n.indexOf('kangis') !== -1
                    || n.indexOf('physical planning') !== -1;
            },

            /** Honorifics off, words sorted — so 'ALH MUSA IDRIS' keys as 'IDRISMUSA'. */
            totPersonKey(value) {
                return String(value || '')
                    .toUpperCase()
                    .replace(/\b(ALH|ALHAJI|ALHAJA|HAJIYA|HAJIA|MAL|MALAM|MALLAM|MR|MRS|MISS|MS|DR|ENGR|ARC|BARR|PROF|CHIEF|SIR|LATE|HON|MOHD|MUHD)\b\.?/g, ' ')
                    .replace(/[^A-Z0-9\s]+/g, ' ')
                    .trim()
                    .split(/\s+/)
                    .sort()
                    .join('');
            },

            /**
             * The same person spelt two ways? (MOHD/MUHD, OZATAMGBO/OZOTAMGBO.)
             *
             * 36 files estate-wide are in that state. Treating them as a leg would ask the
             * officer to record a transfer from a man to himself, so any close match
             * withholds the warning — that is a name correction, not a dealing.
             */
            totSamePerson(a, b) {
                const x = this.totPersonKey(a);
                const y = this.totPersonKey(b);
                if (!x || !y) return false;
                if (x === y) return true;

                // Digits are identifiers, not spellings. 'PLOT 1 LTD' and 'PLOT 2 LTD' are
                // one character apart and score 0.9 on the check below, which would silently
                // swallow the leg between two genuinely different parties. A difference in
                // the digits settles it before any fuzzy comparison runs.
                const digits = (v) => (String(v).match(/\d+/g) || []).join('.');
                if (digits(x) !== digits(y)) return false;

                const long = x.length >= y.length ? x : y;
                const short = x.length >= y.length ? y : x;
                if (!long.length) return false;

                // Levenshtein
                let prev = Array.from({ length: short.length + 1 }, (_, i) => i);
                for (let i = 1; i <= long.length; i++) {
                    const row = [i];
                    for (let j = 1; j <= short.length; j++) {
                        row[j] = Math.min(
                            prev[j] + 1,
                            row[j - 1] + 1,
                            prev[j - 1] + (long[i - 1] === short[j - 1] ? 0 : 1)
                        );
                    }
                    prev = row;
                }

                return 1 - (prev[short.length] / long.length) >= 0.80;
            },

            /**
             * Ownership legs with no Transfer of Title behind them.
             *
             * Walks the card's own chronological, de-duplicated row list — on-file history
             * AND everything typed into the card — and compares where each dealing LEFT the
             * title with where the next one PICKED IT UP.
             */
            totMissingLegs() {
                if (!this.totIsConversionFile()) return [];

                const all = this.fhSummaryRows().filter((r) => {
                    // Synthetic context (File Commissioning, Temporary File) is not a dealing.
                    if (r.derived) return false;
                    return String(r.p1 || '').trim() !== '' || String(r.p2 || '').trim() !== '';
                });

                // ONLY rows that put the title in somebody's hands belong to the chain: a
                // GRANT, which opens it, and a TRANSFER, which moves it.
                //
                // A mortgage, lease, power of attorney, search, caveat, surrender or
                // recertification leaves ownership exactly where it was, so it may act as
                // NEITHER end of a leg. totMovesOwnership() has always known that, but it
                // was only ever consulted when listing transfers already recorded -- the
                // walk itself read every row. That is what asked officers to record a
                // Transfer of Title into a bank on 1,126 conversion files, and into KANGIS
                // on every recertified one.
                //
                // An instrument that is neither is left out rather than guessed at: this
                // check is advisory, so a missed leg costs less than a false one.
                const rows = all.filter((r) =>
                    this.totIsGrant(r.instrument) || this.totMovesOwnership(r.instrument)
                );

                if (rows.length < 2) return [];

                // Every transfer already on the chain. Compared BY PERSON, not by string:
                // totSamePerson() calls 'Ahmed Shitu Abubakar' and 'Ahmad Shitu Abubakar'
                // one man, so the exact-key set this used to build disagreed with the very
                // guard beside it and re-asked for a transfer the file already had.
                const transfers = rows
                    .filter((r) => this.totMovesOwnership(r.instrument))
                    .map((r) => ({
                        from: String(r.p1 || '').trim(),
                        to: String(r.p2 || '').trim(),
                    }));

                const alreadyRecorded = (from, to) => transfers.some(
                    (t) => this.totSamePerson(t.from, from) && this.totSamePerson(t.to, to)
                );

                const legs = [];

                for (let i = 0; i < rows.length - 1; i++) {
                    const holder = String(rows[i].p2 || '').trim();     // where it was left
                    const next = rows[i + 1];
                    const taker = String(next.p1 || '').trim();          // where it resumes

                    if (!holder || !taker) continue;

                    // A grant starts a chain: nobody transferred TO the government.
                    if (this.totIsGrant(next.instrument)) continue;
                    if (this.totIsGovernment(taker)) continue;

                    // Same party either side - the chain is continuous here.
                    if (this.totSamePerson(holder, taker)) continue;

                    // The next row IS this leg backwards: it hands the title from `taker`
                    // to the man already holding it. That is one dealing on the file twice
                    // -- usually one name spelt two ways, which fhSignature() dedupes by
                    // string and therefore misses -- not a transfer nobody recorded.
                    if (this.totSamePerson(holder, next.p2)) continue;

                    if (alreadyRecorded(holder, taker)) continue;
                    if (legs.some((l) => this.totSamePerson(l.from, holder)
                                      && this.totSamePerson(l.to, taker))) continue;

                    legs.push({
                        from: holder,
                        to: taker,
                        after: rows[i].instrument,
                        before: next.instrument,
                    });
                }

                return legs;
            },

            /**
             * The instrument these sections are captured as: 'Transfer of Title', plain.
             *
             * NOT 'Transfer Of Title (OP)'. That name means the transfer off an Occupancy
             * Permit, and this check does not read an OP at all — it reads the ownership
             * chain, which is the whole reason it works on conversion files. Labelling a
             * conversion's transfer as an OP transfer would misfile it against an
             * instrument the file does not have.
             *
             * InstrumentTypes only holds the (OP) spelling, so the plain name is added to
             * the dropdown through the component's own registerTransactionType() — a value
             * that matches no option renders the select blank, on a section whose entire
             * purpose is to be pre-filled.
             */
            totTransferOption() {
                const options = (this.transactionTypeOptions || []).concat(this.additionalTransactionTypes || []);

                // An existing non-OP spelling wins, so this follows the list if one is added.
                const plain = options.find((o) => {
                    const t = String(o).toLowerCase();
                    return t.indexOf('transfer of title') !== -1 && t.indexOf('(op)') === -1;
                });

                return plain || 'Transfer of Title';
            },

            /** One red, pre-filled Transfer of Title block per missing leg. */
            totAddSections(legs) {
                legs.forEach((leg) => {
                    this.addTransaction();
                    const block = this.transactions[this.transactions.length - 1];

                    block.transactionType = this.totTransferOption();
                    block.firstParty = leg.from;
                    block.secondParty = leg.to;
                    // What the card renders red against, and what clears when filled in.
                    block.totRequired = true;
                    block.totLegLabel = leg.from + ' → ' + leg.to;

                    this.registerTransactionType(block.transactionType);
                });
            },

            /** Still outstanding? Used to clear the banner as the officer fills them in. */
            totOutstandingCount() {
                return (this.transactions || []).filter((t) =>
                    t.totRequired && !String(t.transactionDate || '').trim()
                ).length;
            },

            submitTransactions() {
                // Advisory gap check. The FIRST Submit on a conversion file with unrecorded
                // ownership legs reveals them and does not save; a second Submit saves
                // whatever is in the card. So the finding is unmissable and never a dead end.
                if (!this.totGapAcknowledged && this.totIsConversionFile()) {
                    const legs = this.totMissingLegs();

                    if (legs.length) {
                        this.totGapLegs = legs;
                        this.totGapAcknowledged = true;
                        this.totAddSections(legs);

                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'warning',
                                title: legs.length === 1
                                    ? 'One ToT is not recorded'
                                    : legs.length + ' ToTs are not recorded',
                                {{-- Single-quoted HTML attributes throughout: this whole
                                     component lives inside an x-data=&quot;...&quot; attribute,
                                     so one double quote here ends the attribute and dumps the
                                     rest of the component into the page as text. --}}
                                html: `<p style='font-size:13px;color:#475569;'>The history shows the title moving, `
                                    + `but no Transfer of Title records the move:</p>`
                                    + `<div style='margin-top:8px;text-align:left;font-size:12px;border:1px solid #fecaca;`
                                    + `background:#fef2f2;border-radius:8px;padding:8px;'>`
                                    + legs.map((l) => `<div style='padding:2px 0;'><b>`
                                        + (l.from || '?') + `</b> &rarr; <b>` + (l.to || '?') + `</b></div>`).join('')
                                    + `</div>`
                                    + `<p style='font-size:12px;color:#64748b;margin-top:10px;'>`
                                    + `A Transfer of Title section has been added for each, in red, with the names filled in. `
                                    + `Complete them, or press Submit again to save without them.</p>`,
                                confirmButtonText: 'Capture Conversion ToT',
                                confirmButtonColor: '#dc2626',
                            });
                        }

                        return;
                    }
                }

                console.log('Submitting transactions:', this.transactions);
                console.log('File indexing data:', this.fileIndexingData);

                // Validate that at least one transaction has required fields
                const hasValidTransaction = this.transactions.some(t => {
                    return t.transactionType;
                });

                if (!hasValidTransaction) {
                    const errorText = 'Please fill in at least one transaction with a Transaction Type.';

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: errorText,
                            confirmButtonText: 'OK'
                        });
                    } else {
                        alert(errorText);
                    }
                    return;
                }

                const missingOtherName = this.transactions.some(t =>
                    this.isOtherTransaction(t.transactionType) && !String(t.otherTransactionType || '').trim()
                );

                if (missingOtherName) {
                    const msg = 'Please specify the instrument type for the transaction whose type is Other.';
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Validation Error', text: msg, confirmButtonText: 'OK' });
                    } else {
                        alert(msg);
                    }
                    return;
                }

                const missingOpSerial = this.transactions.some(t =>
                    this.isOPTransaction(t.transactionType) && !/^[1-9][0-9]*$/.test(String(t.opSerialNumber || '').trim())
                );

                if (missingOpSerial) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: 'OP Serial Number is required for Occupancy Permit (OP) transactions.',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        alert('OP Serial Number is required for Occupancy Permit (OP) transactions.');
                    }
                    return;

                }

                const missingOpType = this.transactions.some(t =>
                    this.isOPTransaction(t.transactionType) && !String(t.opType || '').trim()
                );

                if (missingOpType) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: 'Type is required for an Occupancy Permit. Please select Resettlement, Direct Allocation or LGA.',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        alert('Type is required for an Occupancy Permit.');
                    }
                    return;
                }

                // An LGA-issued OP is exempt: the Local Government registers nothing in the
                // state deeds registry, so it has no serial or volume number to supply.
                const missingLgaGrantor = this.transactions.some(t =>
                    this.isLgaType(t) && !String(t.firstParty || '').trim()
                );

                if (missingLgaGrantor) {
                    const msg = 'Select the Local Government that issued the Occupancy Permit.';
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Validation Error', text: msg, confirmButtonText: 'OK' });
                    } else {
                        alert(msg);
                    }
                    return;
                }

                // opRegNoRequired() exempts the two kinds that never reached the State
                // deeds registry: an LGA permit and an Old OP.
                const missingOpRegNo = this.transactions.some(t =>
                    this.opRegNoRequired(t)
                    && (!String(t.serialNo || '').trim() || !String(t.volumeNo || '').trim())
                );

                if (missingOpRegNo) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: 'Registration Number (Serial No. and Volume No.) is required for Occupancy Permit (OP) transactions.',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        alert('Registration Number (Serial No. and Volume No.) is required for Occupancy Permit (OP) transactions.');
                    }
                    return;
                }

                // Call the global submission handler
                if (typeof submitPropertyTransactions === 'function') {
                    submitPropertyTransactions(this.transactions, this.fileIndexingData);
                } else {
                    console.error('submitPropertyTransactions function not found');
                }
            }
        }" x-init="
            // Watch for changes and sync page numbers
            $watch('transactions', (value) => {
                value.forEach(t => {
                    if (t.serialNo && !t.pageNo) {
                        t.pageNo = t.serialNo;
                    }
                });
            }, { deep: true });
        ">
            <form id="property-transaction-form" @submit.prevent="submitTransactions">
                @csrf

                <div class="space-y-4 py-2 flex-1">
                    <!-- File Number selector: choose a file number directly in this modal -->
                    <div class="bg-white border border-gray-200 rounded-lg p-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            File Number <span class="text-red-500">*</span>
                        </label>
                        <div class="flex gap-2">
                            <input type="text" readonly id="ptm-file-number"
                                :value="fileIndexingData.file_number || fileIndexingData.temp_file_no || ''"
                                placeholder="No file number selected — click Select"
                                class="flex-grow px-3 py-2 text-sm border border-gray-300 rounded-md bg-gray-50 text-gray-700 font-mono">
                            <button type="button" @click="selectFileNumber()"
                                class="inline-flex items-center gap-1 px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 whitespace-nowrap">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                                </svg>
                                Select
                            </button>
                            <button type="button" @click="clearFileNumber()"
                                x-show="fileIndexingData.file_number || fileIndexingData.temp_file_no"
                                class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-300 whitespace-nowrap">
                                Clear
                            </button>
                        </div>
                    </div>

                    <!-- Info Box showing file indexing details -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                        <div class="flex items-start gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500 mt-0.5"
                                viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                    clip-rule="evenodd" />
                            </svg>
                            <div class="flex-1">
                                <h4 class="text-sm font-semibold text-blue-800 mb-2">File Indexing Information</h4>
                                <div class="grid grid-cols-2 gap-2 text-sm text-blue-700">
                                    <div><strong>File Number:</strong> <span
                                            x-text="fileIndexingData.file_number || fileIndexingData.temp_file_no || 'N/A'"></span></div>
                                    <div><strong>LGA:</strong> <span x-text="fileIndexingData.lga || 'N/A'"></span>
                                    </div>
                                    <div><strong>Plot No:</strong> <span
                                            x-text="fileIndexingData.plot_no || fileIndexingData.plot_number || 'N/A'"></span>
                                    </div>
                                    <div><strong>TP No:</strong> <span x-text="fileIndexingData.tp_no || 'N/A'"></span>
                                    </div>
                                    <div class="col-span-2"><strong>Property Description:</strong> <span
                                            x-text="getPropertyDescription(fileIndexingData)"></span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @include('fileindexing.partial.file_history_summary_card')

                    <!-- Property Details Builder Section -->
                    <div class="border border-blue-100 rounded-lg p-4 mb-4 bg-blue-50/30">
                        <h4 class="text-sm font-semibold text-blue-800 mb-3 flex items-center gap-2">
                             <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Property Registration Details
                        </h4>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">District</label>
                                <select x-model="districtSelection" @change="handleDistrictChange()"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors bg-white appearance-none">
                                    <option value="">Select District</option>
                                    <template x-for="name in districts" :key="name">
                                        <option :value="name" x-text="name" :selected="districtSelection === name"></option>
                                    </template>
                                    <option value="Other">Other</option>
                                </select>
                                <div x-show="districtSelection === 'Other'" class="mt-2" x-transition>
                                    <input type="text" x-model="customDistrict" @input="updateFromCustom()"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors"
                                        placeholder="Specify District Name">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">LGA</label>
                                <select x-model="fileIndexingData.lga" @change="updatePropertyDescription()"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors bg-white appearance-none">
                                    <option value="">Select LGA</option>
                                    <template x-for="name in lgas" :key="name">
                                        <option :value="name" x-text="name" :selected="fileIndexingData.lga === name"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">State</label>
                                <input type="text" x-model="fileIndexingData.state" readonly
                                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-md bg-gray-50 text-gray-500 cursor-not-allowed">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Plot No.</label>
                                <input type="text" x-model="fileIndexingData.plot_no"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors"
                                    placeholder="Enter Plot No">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">TP No.</label>
                                <input type="text" x-model="fileIndexingData.tp_no"
                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors"
                                    placeholder="Enter TP No"
                                    @input="fileIndexingData.tp_no = fileIndexingData.tp_no.replace(/-/g, '/')">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Built Property Description</label>
                            <textarea x-model="fileIndexingData.property_description" readonly
                                class="w-full px-3 py-2 text-sm border border-blue-200 rounded-md bg-blue-50/50 text-blue-800 font-semibold uppercase"
                                rows="2" placeholder="Description will be built from LGA and District..."></textarea>
                        </div>
                    </div>

                    {{-- CofO duplicate pre-check card (populated by window.CofoDuplicateGuard) --}}
                    <div id="ptm-cofo-dup-card" class="hidden mb-4"></div>

                    <!-- Transactions Container -->
                    {{-- Ownership-gap summary. Advisory: it says what was found and that the
                         save is still available, so nobody reads it as a wall. --}}
                    <template x-if="totGapLegs.length">
                        <div class="mb-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3">
                            <div class="flex items-start gap-2.5">
                                <svg class="w-4 h-4 mt-0.5 shrink-0 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 9v2m0 4h.01M4.93 19h14.14a2 2 0 001.73-3L13.73 4a2 2 0 00-3.46 0L3.2 16a2 2 0 001.73 3z" />
                                </svg>
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-red-900">
                                        <span x-text="totGapLegs.length"></span>
                                        <span x-text="totGapLegs.length === 1 ? 'ToT is' : 'ToTs are'"></span>
                                        not recorded on this file
                                    </p>
                                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                                        <template x-for="(leg, li) in totGapLegs" :key="li">
                                            <span class="inline-flex items-center rounded border border-red-200 bg-white px-2 py-0.5 text-[10px] font-semibold text-red-800">
                                                <span x-text="leg.from"></span>
                                                <span class="mx-1 text-red-300">&rarr;</span>
                                                <span x-text="leg.to"></span>
                                            </span>
                                        </template>
                                    </div>
                                    <p class="mt-2 text-[11px] text-red-800">
                                        A Transfer of Title section has been added for each, below, with the names filled in.
                                        Complete them — or press <b>Submit</b> again to save without them.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template x-for="(transaction, index) in transactions" :key="transaction.id">
                        <div class="rounded-lg p-4 mb-4 shadow-sm fh-txn-block"
                             :class="transaction.totRequired
                                ? 'border-2 border-red-400 border-l-4 border-l-red-600 bg-red-50/60'
                                : 'border border-gray-300 bg-white'"
                             :id="'fh-txn-' + transaction.id">

                            {{-- Raised by the ownership-gap check: the chain moved the title
                                 between these two people and nothing recorded the move. Advisory
                                 — the officer may complete it or submit without it. --}}
                            <template x-if="transaction.totRequired">
                                <div class="mb-3 flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center gap-1.5 rounded px-2 py-1 text-[10px] font-bold uppercase tracking-wide bg-red-600 text-white">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 9v2m0 4h.01M4.93 19h14.14a2 2 0 001.73-3L13.73 4a2 2 0 00-3.46 0L3.2 16a2 2 0 001.73 3z" />
                                        </svg>
                                        Transfer of Title missing
                                    </span>
                                    <span class="text-[11px] font-semibold text-red-800" x-text="transaction.totLegLabel"></span>
                                </div>
                            </template>

                            <div class="flex justify-between items-center mb-3">
                                <h3 class="text-lg font-semibold flex items-center gap-2"
                                    :class="transaction.totRequired ? 'text-red-900' : 'text-gray-700'">
                                    Transaction <span x-text="index + 1"></span>
                                    <span x-show="transaction.recordId"
                                        class="text-xs font-medium px-2 py-0.5 rounded-full border"
                                        :class="getSourceInfo(transaction.source).color"
                                        :title="transaction.recordId ? 'Backfilled from ' + getSourceInfo(transaction.source).label + ' (record #' + transaction.recordId + ')' : ''"
                                        x-text="getSourceInfo(transaction.source).label"></span>
                                </h3>
                                <button type="button" @click="removeTransaction(index)" x-show="transactions.length > 1"
                                    class="text-red-500 hover:text-red-700 text-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>

                            {{-- Held back by the server as a possible duplicate. The row was NOT
                                 saved; the user either removes it or confirms it is genuine and
                                 saves again with force_save set. --}}
                            <template x-if="transaction.duplicateInfo">
                                <div class="mb-3 rounded-lg border border-amber-300 bg-amber-50 p-3">
                                    <div class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-tight text-amber-800">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 9v2m0 4h.01M4.93 19h14.14a2 2 0 001.73-3L13.73 4a2 2 0 00-3.46 0L3.2 16a2 2 0 001.73 3z" />
                                        </svg>
                                        Not saved — possible duplicate
                                    </div>
                                    <div class="mt-1 text-[11px] text-amber-900" x-text="transaction.duplicateInfo.message"></div>

                                    <div class="mt-2 space-y-1.5">
                                        <template x-for="(m, mi) in (transaction.duplicateInfo.matches || [])" :key="mi">
                                            <div class="rounded border border-amber-200 bg-white p-2 text-[10px] leading-tight text-gray-700">
                                                <div class="font-bold text-gray-800"
                                                    x-text="(m.transaction_type || 'Certificate of Occupancy') + ' — ' + (m.regNo || '—')"></div>
                                                <div>
                                                    <span class="font-semibold text-gray-700">Date:</span>
                                                    <span x-text="(m.transaction_date || '—').toString().substring(0, 10)"></span>
                                                    <span class="mx-1 text-gray-300">|</span>
                                                    <span class="font-semibold text-gray-700">Party 2:</span>
                                                    <span x-text="m.party_2 || m.Grantee || m.Assignee || m.Lessee || '—'"></span>
                                                </div>
                                                <div>
                                                    <span class="font-semibold text-gray-700">Captured by:</span>
                                                    <span x-text="m.captured_by_name || m.captured_by || m.created_by || '—'"></span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>

                                    <label class="mt-2 flex items-start gap-2 text-[11px] font-semibold text-amber-900">
                                        <input type="checkbox" x-model="transaction.forceSave"
                                            class="mt-0.5 rounded border-amber-400 text-amber-600 focus:ring-amber-500">
                                        <span>I have checked — this is not a duplicate. Save it anyway.</span>
                                    </label>
                                </div>
                            </template>

                            <div class="space-y-3">
                                <!-- Transaction Type and Date - 2x2 Grid -->
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Transaction Type
                                            <span class="text-red-500">*</span></label>
                                        {{-- Locked ONLY on a section the gap check added: there the
                                             instrument is the finding, not a choice. Changing it would
                                             leave the red label and the pre-filled parties describing a
                                             transfer while the row records a different dealing entirely.
                                             The block can still be deleted. The submitted payload is
                                             built from `transactions`, not from the DOM, so a disabled
                                             select still saves.

                                             `=== true`, not the bare property: x-bind coerces an
                                             UNDEFINED dotted expression to '', and '' on a boolean
                                             attribute means "present" — so a block whose object never
                                             declared totRequired (an ordinary Transaction 1, and every
                                             row loaded in update mode) came out disabled. --}}
                                        <select x-model="transaction.transactionType"
                                            :disabled="transaction.totRequired === true"
                                            @change="handleTransactionTypeChange(transaction); if (!isOPTransaction(transaction.transactionType)) transaction.opType = ''; if (!isCofOTransaction(transaction.transactionType)) transaction.cofoType = ''"
                                            :name="'transactions[' + index + '][transaction_type]'"
                                            class="w-full px-3 py-2 text-sm border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                            :class="transaction.totRequired
                                                ? 'border-red-300 bg-red-50 text-red-900 font-semibold cursor-not-allowed'
                                                : 'border-gray-300 bg-white hover:border-gray-400'">
                                            <option value="">Select type</option>
                                            <template x-for="option in transactionTypeOptions"
                                                :key="'default-' + option">
                                                <option :value="option" x-text="option"></option>
                                            </template>
                                            <template x-for="option in additionalTransactionTypes"
                                                :key="'custom-' + option">
                                                <option :value="option" x-text="option"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div>
                                        <label
                                            class="block text-sm font-medium text-gray-700 mb-1">Transaction/Commencement
                                            Date</label>
                                        <input type="date" x-model="transaction.transactionDate"
                                            :name="'transactions[' + index + '][transaction_date]'"
                                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors">
                                    </div>
                                </div>

                                <!-- Other: the instrument still has to be named -->
                                {{-- 'Other' is picked when the paper is an instrument the
                                     catalogue does not list. Saving the word itself would
                                     record nothing, so what is typed here is submitted as
                                     the transaction type in its place. --}}
                                <div x-show="isOtherTransaction(transaction.transactionType)" x-cloak x-transition>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Specify Instrument Type <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" x-model="transaction.otherTransactionType"
                                        :name="'transactions[' + index + '][other_transaction_type]'"
                                        maxlength="255"
                                        placeholder="Name the instrument as it reads on the document"
                                        :class="!String(transaction.otherTransactionType || '').trim()
                                            ? 'border-red-400 ring-1 ring-red-300'
                                            : 'border-gray-300'"
                                        class="w-full px-3 py-2 text-sm border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors">
                                    <p class="mt-1 text-[11px] text-gray-500">
                                        Saved as the transaction type in place of &quot;Other&quot;.
                                    </p>
                                </div>

                                <!-- Status / Type / Category, on one row -->
                                {{-- flex rather than a fixed grid: Type and Category are hidden
                                     for an instrument that has neither, and a hidden flex child
                                     collapses instead of leaving an empty column. So the row
                                     reads Status alone, Status + one, or all three, and each
                                     takes an equal share of whatever is showing. min-w keeps
                                     them from being squeezed unreadable before they wrap. --}}
                                <div class="flex flex-wrap gap-3">
                                    <div class="flex-1 min-w-[180px]">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                        <select x-model="transaction.status"
                                            :name="'transactions[' + index + '][status]'"
                                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors bg-white">
                                            <option value="Normal">Normal</option>
                                            <option value="Normal Cancellation">Normal Cancellation</option>
                                            <option value="Total Cancellation">Total Cancellation</option>
                                        </select>
                                    </div>

                                    <!-- Type (options come from the instrument) -->
                                    {{-- One field, whatever the instrument. Its options are
                                         InstrumentTypeCatalog's, so the three capture screens
                                         cannot drift apart, and an instrument with no Type
                                         (either allocation letter) simply hides it.

                                         The VALUE keeps the spelling its instrument has always
                                         stored — 'OP Resettlement', 'Land CofO' — because ~34k
                                         pra and ~15.5k CofO_staging rows carry those and have to
                                         re-select on load. Only the labels read as the table
                                         does. handleTypeChange() copies the answer into opType /
                                         cofoType so the OP and CofO rules below keep working
                                         off the field they have always read. --}}
                                    <div class="flex-1 min-w-[180px]" x-show="typeOptionsFor(transaction).length > 0" x-cloak>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Type <span x-show="isOPTransaction(transaction.transactionType)" class="text-red-500">*</span>
                                        </label>
                                        <select x-model="transaction.typeValue"
                                            @change="handleTypeChange(transaction)"
                                            :name="'transactions[' + index + '][type_value]'"
                                            :class="isOPTransaction(transaction.transactionType) && !transaction.typeValue ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-300'"
                                            class="w-full px-3 py-2 text-sm border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors bg-white">
                                            <option value="">Select type</option>
                                            <template x-for="option in typeOptionsFor(transaction)" :key="option.value">
                                                <option :value="option.value" x-text="option.label"></option>
                                            </template>
                                            {{-- A stored value the catalogue no longer lists (a legacy
                                                 cofo_type such as 'Legacy CofO', which 14,818 rows carry)
                                                 stays selectable, so loading an old record cannot blank it. --}}
                                            <template x-if="transaction.typeValue && !isCatalogType(transaction)">
                                                <option :value="transaction.typeValue" x-text="transaction.typeValue + ' (legacy)'"></option>
                                            </template>
                                        </select>
                                        <p x-show="isLgaOpType(transaction)" x-cloak class="mt-1 text-[11px] text-amber-700">
                                            Issued by a Local Government: registration number is fixed at 0/0/0 and
                                            the registration date and time are left blank.
                                        </p>
                                    </div>

                                    <!-- Category (options come from the instrument) -->
                                    {{-- Which generation the paper is: Old / New on an Occupancy
                                         Permit, a Certificate of Occupancy and a Right of Occupancy
                                         alike, and nothing at all on a Plot Allocation Letter, which
                                         has no generation. Blank is allowed and means the question
                                         was never put, which is what every row captured before this
                                         field is. --}}
                                    <div class="flex-1 min-w-[180px]" x-show="categoryOptionsFor(transaction).length > 0" x-cloak>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                                        <select x-model="transaction.instrumentCategory"
                                            :name="'transactions[' + index + '][instrument_category]'"
                                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors bg-white">
                                            <option value="">Select category</option>
                                            <template x-for="option in categoryOptionsFor(transaction)" :key="option.value">
                                                <option :value="option.value" x-text="option.label"></option>
                                            </template>
                                        </select>
                                        <p x-show="isOldCategory(transaction)" x-cloak class="mt-1 text-[11px] text-amber-700">
                                            Old: the registration particulars (Serial No., Page No. and Vol No.)
                                            are optional.
                                        </p>
                                    </div>
                                </div>

                                <!-- Registration Number -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Registration
                                        Number
                                        <span x-show="opRegNoRequired(transaction)" x-cloak class="text-red-500 text-xs font-normal ml-1">(Serial No. &amp; Volume No. required for OP)</span>
                                        <span x-show="isLgaOpType(transaction)" x-cloak class="text-amber-700 text-xs font-normal ml-1">(not applicable for an LGA-issued OP)</span>
                                    </label>
                                    <div class="grid grid-cols-5 gap-2">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Serial
                                                No.<span x-show="opRegNoRequired(transaction)" x-cloak class="text-red-500"> *</span></label>
                                            <input type="text" x-model="transaction.serialNo"
                                                @input="transaction.serialNo = transaction.serialNo.replace(/[^0-9]/g, '').replace(/^0+(\d)/, '$1'); syncPageNo(transaction)"
                                                :name="'transactions[' + index + '][serial_no]'"
                                                :class="opRegNoRequired(transaction) && !transaction.serialNo ? 'border-red-400 ring-1 ring-red-300' : (regParticularsLocked(transaction) ? 'border-gray-200 bg-gray-50 text-gray-500 cursor-not-allowed' : 'border-gray-300')"
                                                :readonly="regParticularsLocked(transaction)"
                                                class="w-full px-2 py-1.5 text-xs border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors"
                                                placeholder="e.g. 1">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 mb-1">Page No.
                                                (Auto-filled)</label>
                                            <input type="text" x-model="transaction.pageNo"
                                                :name="'transactions[' + index + '][page_no]'" readonly
                                                class="w-full px-2 py-1.5 text-xs border border-gray-200 rounded-md bg-gray-50 text-gray-500 cursor-not-allowed"
                                                placeholder="Same as Serial">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Volume
                                                No.<span x-show="opRegNoRequired(transaction)" x-cloak class="text-red-500"> *</span></label>
                                            <input type="text" x-model="transaction.volumeNo"
                                                @input="transaction.volumeNo = transaction.volumeNo.replace(/[^0-9]/g, '').replace(/^0+(\d)/, '$1')"
                                                :name="'transactions[' + index + '][volume_no]'"
                                                :class="opRegNoRequired(transaction) && !transaction.volumeNo ? 'border-red-400 ring-1 ring-red-300' : (regParticularsLocked(transaction) ? 'border-gray-200 bg-gray-50 text-gray-500 cursor-not-allowed' : 'border-gray-300')"
                                                :readonly="regParticularsLocked(transaction)"
                                                class="w-full px-2 py-1.5 text-xs border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors"
                                                placeholder="e.g. 2">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Reg Time</label>
                                            <input type="time" x-model="transaction.regTime"
                                                :name="'transactions[' + index + '][reg_time]'"
                                                :disabled="regParticularsLocked(transaction)"
                                                :class="regParticularsLocked(transaction) ? 'bg-gray-50 text-gray-500 cursor-not-allowed' : ''"
                                                class="w-full px-2 py-1.5 text-xs border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Reg Date</label>
                                            <input type="date" x-model="transaction.regDate"
                                                :name="'transactions[' + index + '][reg_date]'"
                                                :disabled="regParticularsLocked(transaction)"
                                                :class="regParticularsLocked(transaction) ? 'bg-gray-50 text-gray-500 cursor-not-allowed' : ''"
                                                class="w-full px-2 py-1.5 text-xs border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors">
                                        </div>
                                    </div>

                                    <!-- Registration Number Preview -->
                                    <div x-show="getRegNumberPreview(transaction)" x-transition
                                        class="mt-2 p-2 bg-blue-50 border border-blue-200 rounded">
                                        <span class="text-sm font-semibold text-blue-700">Registration Number:</span>
                                        <span class="text-sm font-bold text-blue-800 ml-2"
                                            x-text="getRegNumberPreview(transaction)"></span>
                                    </div>

                                    <div x-show="isOPTransaction(transaction.transactionType)" x-transition class="mt-3">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            OP Serial Number <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" x-model="transaction.opSerialNumber" :required="isOPTransaction(transaction.transactionType)" pattern="[1-9][0-9]*" maxlength="100"
                                            :name="'transactions[' + index + '][op_serial_number]'"
                                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors"
                                            placeholder="Enter OP serial number"
                                            @input="
                                                let v = $event.target.value.replace(/[^0-9]/g, '');
                                                v = v.replace(/^0+(\d)/, '$1');
                                                transaction.opSerialNumber = v;
                                                $event.target.value = v;
                                            ">
                                    </div>

                                    {{-- Deed of Purchase only: what was paid and the
                                         receipt behind it. Same pattern as the OP
                                         serial above — revealed by transaction type,
                                         one pair per transaction row. --}}
                                    <div x-show="isPurchaseTransaction(transaction.transactionType)" x-transition
                                        class="mt-3 grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Amount</label>
                                            <input type="text" inputmode="decimal" x-model="transaction.considerationAmount"
                                                :name="'transactions[' + index + '][consideration_amount]'"
                                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors"
                                                placeholder="Purchase consideration">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Receipt No</label>
                                            <input type="text" x-model="transaction.receiptNo"
                                                :name="'transactions[' + index + '][receipt_no]'"
                                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors"
                                                placeholder="Payment receipt number">
                                        </div>
                                    </div>
                                </div>

                                <!-- Land Use and Period -->
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Land Use</label>
                                        <select x-model="transaction.landUse"
                                            :name="'transactions[' + index + '][land_use]'"
                                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors bg-white">
                                            <option value="">Select land use</option>
                                            <option value="RESIDENTIAL">RESIDENTIAL</option>
                                            <option value="AGRICULTURAL">AGRICULTURAL</option>
                                            <option value="COMMERCIAL">COMMERCIAL</option>
                                            <option value="INDUSTRIAL">INDUSTRIAL</option>
                                            <option value="RESIDENTIAL/COMMERCIAL">RESIDENTIAL/COMMERCIAL</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label
                                            class="block text-sm font-medium text-gray-700 mb-1">Period/Tenancy</label>
                                        <div class="flex space-x-2">
                                            <input type="number" x-model="transaction.period"
                                                :name="'transactions[' + index + '][period]'"
                                                class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors"
                                                placeholder="Period">
                                            <select x-model="transaction.periodUnit"
                                                :name="'transactions[' + index + '][period_unit]'"
                                                class="w-24 px-2 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors bg-white">
                                                <option value="Days">Days</option>
                                                <option value="Months">Months</option>
                                                <option value="Years">Years</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Comments/Remarks -->
                                <div class='hidden'>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Transaction Comments (e.g. Temp File Number)</label>
                                    <textarea x-model="transaction.comments"
                                        :name="'transactions[' + index + '][comments]'"
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors"
                                        rows="2" placeholder="Add any comments or remarks here..."></textarea>
                                </div>

                                <!-- Transaction Parties -->
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1"
                                            x-text="getPartyLabels(transaction.transactionType).first"></label>
                                        {{-- Party 1 is normally free text, and locked to KANO STATE
                                             GOVERNMENT on a government grant. Anything issued by a
                                             Local Government is the third case: the grantor is one of
                                             the 44, so the field becomes a picker and the chosen full
                                             name is what gets saved as party_1. That is every
                                             instrument whose Type is LGA, not only a permit. --}}
                                        <template x-if="isLgaType(transaction)">
                                            <select x-model="transaction.firstParty"
                                                :name="'transactions[' + index + '][first_party]'"
                                                class="w-full px-3 py-2 text-sm border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors bg-white"
                                                :class="!transaction.firstParty ? 'border-red-400 ring-1 ring-red-300' : 'border-gray-300'">
                                                <option value="">Select Local Government</option>
                                                <template x-for="name in kanoLgaAuthorities" :key="name">
                                                    <option :value="name" x-text="name"></option>
                                                </template>
                                                {{-- Keeps a hand-typed grantor from an older row selectable. --}}
                                                <template x-if="transaction.firstParty && !kanoLgaAuthorities.includes(transaction.firstParty)">
                                                    <option :value="transaction.firstParty" x-text="transaction.firstParty"></option>
                                                </template>
                                            </select>
                                        </template>
                                        <template x-if="!isLgaType(transaction)">
                                            <input type="text" x-model="transaction.firstParty"
                                                :name="'transactions[' + index + '][first_party]'"
                                                :class="isGovernmentTransaction(transaction.transactionType) ? 'w-full px-3 py-2 text-sm border border-gray-200 rounded-md shadow-sm bg-gray-50 text-gray-600 cursor-not-allowed' : 'w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors'"
                                                :readonly="isGovernmentTransaction(transaction.transactionType)"
                                                :placeholder="isGovernmentTransaction(transaction.transactionType) ? 'KANO STATE GOVERNMENT' : 'Enter name'">
                                        </template>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1"
                                            x-text="getPartyLabels(transaction.transactionType).second"></label>
                                        <input type="text" x-model="transaction.secondParty"
                                            :name="'transactions[' + index + '][second_party]'"
                                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors"
                                            placeholder="Enter name">
                                    </div>
                                </div>
                                <!-- Extended Party Fields (Co-Surrenderor / Co-Mortgagor / Third Party) -->
                                <div class="col-span-2 mt-2"
                                    x-show="normalizePropertyTransactionType(transaction.transactionType) === 'Deed of Surrender' || 
                                            normalizePropertyTransactionType(transaction.transactionType) === 'Tripartite Mortgage' || 
                                            normalizePropertyTransactionType(transaction.transactionType) === 'Deed of Mortgage' ||
                                            normalizePropertyTransactionType(transaction.transactionType) === 'Deed of Surrender and Release'">

                                    <!-- Deed of Surrender specific -->
                                    <div
                                        x-show="normalizePropertyTransactionType(transaction.transactionType) === 'Deed of Surrender' || normalizePropertyTransactionType(transaction.transactionType) === 'Deed of Surrender and Release'">
                                        <div class="flex items-center mb-2">
                                            <input type="checkbox" x-model="transaction.includeCoSurrenderor"
                                                :id="'include-co-surrenderor-' + index"
                                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                            <label :for="'include-co-surrenderor-' + index"
                                                class="ml-2 block text-sm text-gray-900">
                                                Include Co-Surrenderor
                                            </label>
                                        </div>
                                        <div x-show="transaction.includeCoSurrenderor" x-transition>
                                            <label
                                                class="block text-sm font-medium text-gray-700 mb-1">Co-Surrenderor</label>
                                            <input type="text" x-model="transaction.coFirstParty"
                                                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors"
                                                placeholder="Enter Co-Surrenderor name">
                                        </div>
                                    </div>

                                    <!-- Tripartite Mortgage specific -->
                                    <div
                                        x-show="normalizePropertyTransactionType(transaction.transactionType) === 'Tripartite Mortgage'">
                                        <!-- Co-Mortgagor (Mortgagor 2) -->
                                        <div class="space-y-3 pl-6 border-l-2 border-blue-100">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Co-Mortgagor (Mortgagor 2)</label>
                                                <input type="text" x-model="transaction.coFirstParty"
                                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors"
                                                    placeholder="Enter Co-Mortgagor name">
                                            </div>

                                            <div class="flex items-center">
                                                <input type="checkbox" x-model="transaction.includeMortgagor3"
                                                    :id="'include-mortgagor3-trip-' + index"
                                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                <label :for="'include-mortgagor3-trip-' + index"
                                                    class="ml-2 block text-sm text-gray-900">
                                                    Include Mortgagor 3?
                                                </label>
                                            </div>

                                            <div x-show="transaction.includeMortgagor3" x-transition>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Mortgagor 3 (party_4)</label>
                                                <input type="text" x-model="transaction.mortgagor3"
                                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors"
                                                    placeholder="Enter Mortgagor 3 name">
                                            </div>

                                            <div class="flex items-center">
                                                <input type="checkbox" x-model="transaction.includeFourthParty"
                                                    :id="'include-fourth-party-trip-' + index"
                                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                <label :for="'include-fourth-party-trip-' + index"
                                                    class="ml-2 block text-sm text-gray-900">
                                                    Include Fourth Party Agreement?
                                                </label>
                                            </div>

                                            <div x-show="transaction.includeFourthParty" x-transition>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Fourth Party (party_5)</label>
                                                <input type="text" x-model="transaction.fourthParty"
                                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors"
                                                    placeholder="Enter Fourth Party name">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Deed of Mortgage specific: Co-Mortgagor and Fourth Party -->
                                    <div x-show="normalizePropertyTransactionType(transaction.transactionType) === 'Deed of Mortgage'">
                                        <div class="flex items-center mb-2">
                                            <input type="checkbox" x-model="transaction.includeCoMortgagor"
                                                :id="'include-co-mortgagor-' + index"
                                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                            <label :for="'include-co-mortgagor-' + index"
                                                class="ml-2 block text-sm text-gray-900">
                                                Include Co-Mortgagor (three-party agreement)
                                            </label>
                                        </div>

                                        <div x-show="transaction.includeCoMortgagor" x-transition class="space-y-3 pl-6 border-l-2 border-blue-100">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Co-Mortgagor</label>
                                                <input type="text" x-model="transaction.coFirstParty"
                                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors"
                                                    placeholder="Enter Co-Mortgagor name">
                                            </div>

                                            <div class="flex items-center">
                                                <input type="checkbox" x-model="transaction.includeFourthParty"
                                                    :id="'include-fourth-party-' + index"
                                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                <label :for="'include-fourth-party-' + index"
                                                    class="ml-2 block text-sm text-gray-900 ">
                                                    Include Fourth Party?
                                                </label>
                                            </div> 

                                            <div x-show="transaction.includeFourthParty" x-transition>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Fourth Party</label>
                                                <input type="text" x-model="transaction.fourthParty"
                                                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 hover:border-gray-400 transition-colors"
                                                    placeholder="Enter Fourth Party name">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Add Transaction Button -->
                    <div class="flex justify-center mt-4">
                        <button type="button" @click="addTransaction()"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-blue-600 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100 hover:border-blue-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                                    clip-rule="evenodd" />
                            </svg>
                            Add Another Transaction
                        </button>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200 mt-6">
                        <button type="button" id="cancel-property-transaction"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                            Cancel
                        </button>
                       
                        {{-- isUpdateMode is set by openPropertyTransactionModal() when the
                             modal was opened with existing_records, i.e. the file already has
                             stored transactions. --}}
                        <button type="submit" id="save-transaction-btn"
                            class="px-6 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
                            x-text="isUpdateMode ? 'Update Transaction Details' : 'Save Transaction Details'">
                            Save Transaction Details
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    /* Property Transaction Modal overlay styles - Use unique class name to avoid conflicts */
    .property-transaction-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
    }

    .property-transaction-overlay.hidden {
        display: none !important;
    }

    .property-transaction-overlay .dialog-content {
        background: white;
        border-radius: 0.5rem;
        padding: 1.5rem;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        position: relative;
        z-index: 10001;
    }

    .property-form-content {
        width: 100%;
        max-width: 900px;
    }

    /* Ensure SweetAlert appears above this modal */
    .swal2-container {
        z-index: 20000 !important;
    }
</style>

<script>
    const PROPERTY_TRANSACTION_TYPE_MAP = {
        'DEED OF TRANSFER': 'Deed of Transfer',
        'DEED OF ASSIGNMENT': 'Deed of Assignment',
        'ST ASSIGNMENT': 'ST Assignment',
        'CERTIFICATE OF OCCUPANCY': 'Certificate of Occupancy',
        'C OF O': 'Certificate of Occupancy',
        'ST CERTIFICATE OF OCCUPANCY': 'ST Certificate of Occupancy',
        'SLTR CERTIFICATE OF OCCUPANCY': 'SLTR Certificate of Occupancy',
        'IRREVOCABLE POWER OF ATTORNEY': 'Irrevocable Power of Attorney',
        'POWER OF ATTORNEY': 'Power of Attorney',
        'DEED OF RELEASE': 'Deed of Release',
        'DEED OF MORTGAGE': 'Deed of Mortgage',
        'TRIPARTITE MORTGAGE': 'Tripartite Mortgage',
        'DEED OF SUB LEASE': 'Deed of Sub Lease',
        'DEED OF SUBLEASE': 'Deed of Sub Lease',
        'DEED OF SUB UNDER LEASE': 'Deed of Sub Under Lease',
        'DEED OF LEASE': 'Deed of Lease',
        'INDENTURE OF LEASE': 'Indenture of Lease',
        'DEED OF VARIATION': 'Deed of Variation',
        'DEED OF SURRENDER': 'Deed of Surrender',
        'DEED OF SURRENDER AND RELEASE': 'Deed of Surrender and Release',
        'DEED OF GIFT': 'Deed of Gift',
        'LETTER OF ADMINISTRATION': 'Letter of Administration',
        'CERTIFICATE OF PURCHASE': 'Certificate of Purchase',
        'TENANCY AGREEMENT': 'Tenancy Agreement',
        'CUSTOMARY RIGHT OF OCCUPANCY': 'Customary Right of Occupancy',
        'OCCUPATION PERMIT': 'Occupation Permit',
        'OCCUPANCY PERMIT': 'Occupancy Permit (OP)',
        'OCCUPANCY PERMIT (OP)': 'Occupancy Permit (OP)',
        'OCCUPANCY PERMIT(OP)': 'Occupancy Permit (OP)',
        'OP': 'Occupancy Permit (OP)',
        'OTHER': 'Other',
    };

    function formatDateForInput(value) {
        if (!value) {
            return '';
        }

        const stringValue = String(value).trim();
        if (stringValue === '') {
            return '';
        }

        const isoMatch = stringValue.match(/^(\d{4}-\d{2}-\d{2})/);
        if (isoMatch) {
            return isoMatch[1];
        }

        const parsed = new Date(stringValue);
        if (!isNaN(parsed.getTime())) {
            return parsed.toISOString().split('T')[0];
        }

        return stringValue;
    }

    function formatTimeForInput(value) {
        if (!value) {
            return '';
        }

        const stringValue = String(value).trim();
        if (stringValue === '') {
            return '';
        }

        const timeMatch = stringValue.match(/^(\d{2}:\d{2})/);
        if (timeMatch) {
            return timeMatch[1];
        }

        const parsed = new Date(`1970-01-01T${stringValue}`);
        if (!isNaN(parsed.getTime())) {
            return parsed.toISOString().substring(11, 16);
        }

        return stringValue.substring(0, 5);
    }

    function normalizePropertyTransactionType(value) {
        if (value === null || typeof value === 'undefined') {
            return '';
        }

        const textValue = String(value).trim();
        if (textValue === '') {
            return '';
        }

        const lookupKey = textValue.toUpperCase();
        return PROPERTY_TRANSACTION_TYPE_MAP[lookupKey] || textValue;
    }

    /**
     * Build a standardized property description from various data sources
     */
    function getUnifiedPropertyDescription(data) {
        if (!data) return 'N/A';
        
        // Use property_description if it exists and is valid
        if (data.property_description && data.property_description !== 'N/A' && data.property_description.trim() !== '') {
            return data.property_description;
        }
        
        // Fallback to location
        if (data.location && data.location !== 'N/A' && data.location.trim() !== '') {
            return data.location;
        }
        
        // Re-build from components as last resort
        const parts = [];
        if (data.district) parts.push(data.district);
        if (data.lga) parts.push(data.lga);
        if (data.state || 'KANO') parts.push(data.state || 'KANO');
        
        return parts.length > 0 ? parts.join(', ').toUpperCase() : 'N/A';
    }

    // Module-level store: keeps the last fileIndexingData passed to the modal
    // so submitPropertyTransactions can fall back to it even if Alpine state is stale
    let _lastFileIndexingData = null;

    // Resolve file_number from temp_file_no when file_number is absent
    function resolveFileNumber(data) {
        if (!data.file_number && data.temp_file_no) {
            data.file_number = data.temp_file_no;
        }
        return data;
    }

    // Global function to open the property transaction modal
    // IMPORTANT: Defined outside DOMContentLoaded to be globally accessible immediately
    function openPropertyTransactionModal(fileIndexingData) {
        resolveFileNumber(fileIndexingData);
        _lastFileIndexingData = fileIndexingData;
        console.log('Opening property transaction modal with data:', fileIndexingData);

        const modal = document.getElementById('property-transaction-dialog');
        if (!modal) {
            console.error('Property transaction modal not found!');
            return;
        }

        console.log('Modal element found:', modal);

        // Try to get Alpine component and set file indexing data
        try {
            const alpineElement = modal.querySelector('[x-data]');
            console.log('Alpine element:', alpineElement);

            if (alpineElement && typeof Alpine !== 'undefined') {
                // Wait for Alpine to be ready
                setTimeout(() => {
                    try {
                        const alpineComponent = Alpine.$data(alpineElement);
                        if (alpineComponent) {
                            console.log('Alpine component found, setting data...');
                            
                            // Load reference data if missing
                            if (alpineComponent.lgas.length === 0) {
                                fetch('/api/reference/lgas')
                                    .then(res => res.json())
                                    .then(data => { 
                                        if (data.success && data.data) {
                                            alpineComponent.lgas = data.data.map(item => item.name);
                                        }
                                    });
                            }
                            if (alpineComponent.districts.length === 0) {
                                fetch('/api/reference/districts')
                                    .then(res => res.json())
                                    .then(data => { 
                                        if (data.success && data.data) {
                                            alpineComponent.districts = data.data.map(item => item.name);
                                            // Sync selection after loading districts
                                            if (typeof alpineComponent.syncDistrictSelection === 'function') {
                                                alpineComponent.syncDistrictSelection();
                                            }
                                        }
                                    });
                            } else {
                                // Sync immediately if already loaded
                                if (typeof alpineComponent.syncDistrictSelection === 'function') {
                                    alpineComponent.syncDistrictSelection();
                                }
                            }

                            // Correct and enrich fileIndexingData from existing records if available
                            if (fileIndexingData.existing_records && fileIndexingData.existing_records.length > 0) {
                                const rec = fileIndexingData.existing_records[0];
                                console.log('Enriching header info from first record:', rec);
                                
                                // Map database fields correctly
                                const cleanValue = (val) => (!val || val === 'N/A') ? '' : val;
                                
                                if (!cleanValue(fileIndexingData.lga)) fileIndexingData.lga = rec.lgsaOrCity || rec.lga || '';
                                if (!cleanValue(fileIndexingData.district)) fileIndexingData.district = rec.districtName || rec.district || '';
                                if (!cleanValue(fileIndexingData.plot_no)) {
                                    fileIndexingData.plot_no = rec.plot_no || rec.plot_number || '';
                                }
                                if (!cleanValue(fileIndexingData.tp_no)) fileIndexingData.tp_no = rec.tp_no || '';
                                if (!cleanValue(fileIndexingData.property_description)) {
                                    fileIndexingData.property_description = rec.property_description || rec.location || '';
                                }
                                if (!fileIndexingData.state || fileIndexingData.state === 'N/A') fileIndexingData.state = 'KANO';
                            }
                            
                            
                            alpineComponent.fileIndexingData = fileIndexingData;
                            
                            // Re-sync after setting data
                            if (typeof alpineComponent.syncDistrictSelection === 'function') {
                                alpineComponent.syncDistrictSelection();
                            }

                            // Check if existing records exist and populate transactions
                            if (fileIndexingData.existing_records && fileIndexingData.existing_records.length > 0) {
                                console.log('Found existing records, populating transactions:', fileIndexingData.existing_records.length);
                                console.log('Sample existing record structure:', fileIndexingData.existing_records[0]);

                                // Helper function to extract party names from database record
                                function extractPartyNames(record) {
                                    // Check for specific party fields based on transaction type
                                    const partyFields = [
                                        ['party_1', 'party_2'], // Generic party columns
                                        ['Grantor', 'Grantee'],
                                        ['Assignor', 'Assignee'],
                                        ['Mortgagor', 'Mortgagee'],
                                        ['Surrenderor', 'Surrenderee'],
                                        ['Lessor', 'Lessee'],
                                        ['Landlord', 'Tenant'],
                                        ['Releasor', 'Releasee'],
                                        ['Transferor', 'Transferee'],
                                        ['Donor', 'Donee'],
                                        ['Administrator', 'Beneficiary'],
                                        ['Vendor', 'Purchaser']
                                    ];

                                    let firstParty = '';
                                    let secondParty = '';

                                    // Check each possible party field combination
                                    for (let [first, second] of partyFields) {
                                        if (record[first] || record[second]) {
                                            firstParty = record[first] || '';
                                            secondParty = record[second] || '';
                                            break;
                                        }
                                    }

                                    // Fallback to generic fields if available
                                    if (!firstParty && !secondParty) {
                                        firstParty = record.first_party || record.firstParty || '';
                                        secondParty = record.second_party || record.secondParty || '';
                                    }

                                    return { firstParty, secondParty };
                                }

                                // Convert existing records to transaction format
                                const mappedTransactions = fileIndexingData.existing_records.map((record, index) => {
                                    const parties = extractPartyNames(record);
                                    const formattedTransactionDate = formatDateForInput(record.transaction_date || record.transactionDate || null);
                                    const formattedRegDate = formatDateForInput(record.reg_date || record.regDate || null);
                                    const formattedRegTime = formatTimeForInput(record.reg_time || record.regTime || null);
                                    const normalizedType = normalizePropertyTransactionType(
                                        record.transaction_type ||
                                        record.transactionType ||
                                        record.instrument_type ||
                                        record.instrumentType ||
                                        ''
                                    );

                                    // Matches isLgaOpType(), but on the raw record: the payload below
                                    // is what isLgaOpType() will later read, so it cannot ask itself.
                                    // Both LGA kinds count — keep this list in step with LGA_OP_TYPES.
                                    const isStoredLgaOpType = ['LGA', 'LGA ALLOCATION LETTER'].includes(
                                        String(record.op_type || record.opType || '')
                                            .trim().toUpperCase().replace(/^OP\s+/, '')
                                    );

                                    const transactionPayload = {
                                        id: index + 1,
                                        recordId: record.id || record.record_id || null,
                                        // Which staging table this record was loaded from (fh/cofo/pra/deeds),
                                        // set server-side by PropertyRecordController::checkExistingRecords().
                                        // Sent back on submit so an update lands in the SAME table the row
                                        // came from, instead of defaulting to file_history_staging.
                                        source: record._source || record.source || '',
                                        transactionType: normalizedType,
                                        instrumentType: normalizedType,
                                        // A stored row already carries the instrument's real name
                                        // (registered through ensureTransactionTypes below), so it
                                        // never comes back as 'Other'.
                                        otherTransactionType: '',
                                        status: record.status || 'Normal',
                                        transactionDate: formattedTransactionDate || '',
                                        opType: record.op_type || record.opType || '',
                                        // The visible Type comes from whichever column this
                                        // instrument stores it in; the catalogue decides which.
                                        typeValue: record.op_type || record.opType
                                            || record.cofo_type || record.cofoType
                                            || record.instrument_subtype || record.instrumentSubtype || '',
                                        instrumentCategory: record.instrument_category || record.instrumentCategory || '',
                                        opSerialNumber: record.op_serial_number || record.opSerialNumber || '',
                                        cofoType: record.cofo_type || record.cofoType || '',
                                        serialNo: record.serialNo || record.serial_no || '',
                                        pageNo: record.pageNo || record.page_no || '',
                                        volumeNo: record.volumeNo || record.volume_no || '',
                                        // Today's date / 09:00 are conveniences for a normal instrument.
                                        // An LGA-issued OP is deliberately dateless, so a blank stays blank
                                        // rather than being silently stamped with today on reopen.
                                        regDate: formattedRegDate || (isStoredLgaOpType ? '' : new Date().toISOString().split('T')[0]),
                                        regTime: formattedRegTime || (isStoredLgaOpType ? '' : '09:00'),
                                        landUse: record.landUse || record.land_use || fileIndexingData.land_use_type || '',
                                        period: record.period || '',
                                        periodUnit: record.periodUnit || record.period_unit || 'Years',
                                        comments: record.comments || record.comment || record.remarks || '',
                                        firstParty: parties.firstParty || '',
                                        secondParty: parties.secondParty || '',
                                        // A stored row is never a gap finding — see addTransaction().
                                        totRequired: false,
                                        totLegLabel: ''
                                    };
                                    return transactionPayload;
                                });

                                if (typeof alpineComponent.ensureTransactionTypes === 'function') {
                                    alpineComponent.ensureTransactionTypes(mappedTransactions.map(t => t.transactionType));
                                }

                                alpineComponent.transactions = mappedTransactions;
                                alpineComponent.isUpdateMode = true;

                                console.log('Populated transactions from existing records:', alpineComponent.transactions);

                                // Update modal title to "Update"
                                const titleElement = document.getElementById('transaction-form-title');
                                if (titleElement) {
                                    titleElement.textContent = 'Update Property Transaction Details';
                                }
                            } else {
                                console.log('No existing records, creating empty transaction');
                                alpineComponent.isUpdateMode = false;

                                // Update modal title to "Add"
                                const titleElement = document.getElementById('transaction-form-title');
                                if (titleElement) {
                                    titleElement.textContent = 'Add Property Transaction Details';
                                }

                                // Create single empty transaction
                                const blankTransaction = {
                                    id: 1,
                                    recordId: null,
                                    transactionType: '',
                                    otherTransactionType: '',
                                    status: 'Normal',
                                    transactionDate: '',
                                    typeValue: '',
                                    instrumentCategory: '',
                                    opSerialNumber: '',
                                    // Deed of Purchase: what was paid, and the receipt behind it.
                                    considerationAmount: '',
                                    receiptNo: '',
                                    cofoType: '',
                                    serialNo: '',
                                    pageNo: '',
                                    volumeNo: '',
                                    regDate: new Date().toISOString().split('T')[0],
                                    regTime: '09:00',
                                    landUse: fileIndexingData.land_use_type || '',
                                    period: '',
                                    periodUnit: 'Years',
                                    comments: fileIndexingData.temp_file_no ? 'Temp File: ' + fileIndexingData.temp_file_no : '',
                                    firstParty: '',
                                    secondParty: '',
                                    // A blank row is never a gap finding — see addTransaction().
                                    totRequired: false,
                                    totLegLabel: ''
                                };

                                alpineComponent.ensureTransactionTypes([blankTransaction.transactionType]);
                                alpineComponent.transactions = [blankTransaction];
                            }
                            // File History Summary: the modal usually opens with a file
                            // number already set, which never goes through selectFileNumber(),
                            // so the history load has to be kicked off here as well. Not
                            // awaited - the card already shows the captured rows.
                            try {
                                alpineComponent.fhSaveOutcome = {};
                                if (typeof alpineComponent.fhLoadFileHistory === 'function') {
                                    alpineComponent.fhLoadFileHistory(false);
                                }
                            } catch (e) {
                                console.warn('File History Summary: could not start history load', e);
                            }

                            console.log('Data set successfully');
                        } else {
                            console.warn('Alpine component not found, modal will still open');
                        }
                    } catch (e) {
                        console.error('Error setting Alpine data:', e);
                    }
                }, 100);
            } else {
                console.warn('Alpine not ready or element not found, modal will still open');
            }
        } catch (e) {
            console.error('Error accessing Alpine:', e);
        }

        // Show the modal regardless
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
        console.log('Modal should now be visible');
    }

    /**
     * Render the File History Summary as a standalone HTML block for the SweetAlert
     * confirmation dialog, which is drawn outside this component's DOM and stylesheet.
     *
     * Lives here rather than in the Alpine x-data attribute on purpose: building HTML needs
     * double quotes, and a double quote inside that attribute truncates it and dumps the
     * whole component onto the page as text.
     *
     * Fed by the same fhSummaryRows() / fhDestinationTally() the inline card uses, so the two
     * always agree about what a save did. All styling is inlined, since the dialog does not
     * inherit the card's stylesheet.
     */
    window.fhRenderSummaryHtml = function (rows, tally) {
        rows = rows || [];
        if (!rows.length) return '';

        const esc = (v) => String(v == null ? '' : v)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');

        const chipBase = 'display:inline-block;padding:1px 6px;border-radius:9999px;'
            + 'font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;'
            + 'white-space:nowrap;border:1px solid transparent;';

        const destStyle = {
            file_history_staging: 'background:#dbeafe;color:#1e40af;border-color:#bfdbfe;',
            CofO_staging:         'background:#d1fae5;color:#065f46;border-color:#a7f3d0;',
            pra:                  'background:#fef3c7;color:#92400e;border-color:#fde68a;',
            deed_registrations:   'background:#ede9fe;color:#5b21b6;border-color:#ddd6fe;',
        };
        const statusStyle = {
            NEW:       'background:#059669;color:#fff;',
            EDITING:   'background:#2563eb;color:#fff;',
            ON_FILE:   'background:#f1f5f9;color:#475569;border-color:#e2e8f0;',
            SAVED:     'background:#047857;color:#fff;',
            UPDATED:   'background:#1d4ed8;color:#fff;',
            HELD_BACK: 'background:#b45309;color:#fff;',
        };

        const items = rows.map((r) => {
            const chips = [];
            if (r.destinationKey) {
                chips.push('<span style="' + chipBase + (destStyle[r.destinationKey] || '') + '">'
                    + esc(r.destinationLabel) + '</span>');
            }
            chips.push('<span style="' + chipBase + (statusStyle[r.status] || '') + '">'
                + esc(r.statusLabel) + '</span>');

            return '<div style="padding:6px 0;border-bottom:1px solid #f1f5f9;'
                + (r.derived ? 'opacity:.6;' : '') + '">'
                +   '<div style="display:flex;flex-wrap:wrap;align-items:center;gap:5px;margin-bottom:2px;">'
                +     chips.join('')
                +     '<span style="font-size:12px;font-weight:600;color:#1e293b;">' + esc(r.instrument) + '</span>'
                +     (r.regNo ? '<span style="font-size:10px;color:#94a3b8;">Reg: ' + esc(r.regNo) + '</span>' : '')
                +   '</div>'
                +   '<div style="display:flex;justify-content:space-between;gap:10px;">'
                +     '<span style="font-size:11px;color:#475569;">' + esc(r.parties) + '</span>'
                +     '<span style="font-size:10px;color:#94a3b8;white-space:nowrap;">' + esc(r.date) + '</span>'
                +   '</div>'
                + '</div>';
        }).join('');

        const tallyText = (tally || []).map((d) => esc(d.label) + ' ' + d.count).join(' \u00b7 ');

        return '<div style="margin-top:14px;text-align:left;">'
            +    '<div style="font-size:11px;font-weight:700;color:#6d28d9;text-transform:uppercase;'
            +    'letter-spacing:.05em;margin-bottom:2px;">File History Summary</div>'
            +    (tallyText
                    ? '<div style="font-size:10px;color:#64748b;margin-bottom:6px;">Written to: ' + tallyText + '</div>'
                    : '')
            +    items
            + '</div>';
    };

    // Global function to close the property transaction modal
    function closePropertyTransactionModal() {
        const modal = document.getElementById('property-transaction-dialog');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    // Make functions globally accessible
    window.openPropertyTransactionModal = openPropertyTransactionModal;
    window.closePropertyTransactionModal = closePropertyTransactionModal;

    // Unified property description function for form submission
    function getUnifiedPropertyDescription(data) {
        // Edit page format: uses property_description (district + lga)
        if (data.property_description) {
            return data.property_description;
        }

        // Indexing page format: uses location field
        if (data.location) {
            return data.location;
        }

        // Fallback: construct from district and lga if available
        const district = data.district || '';
        const lga = data.lga || '';
        if (district || lga) {
            return [district, lga].filter(Boolean).join(', ');
        }

        return '';
    }

    /**
     * Handle a partial save: the server stored every non-duplicate transaction and
     * returned the ones that look like duplicates.
     *
     * The saved rows are dropped from the form (so a re-submit cannot insert them a
     * second time) and only the held-back rows are left behind, each flagged with the
     * existing records it matched and a "save it anyway" checkbox. Ticking that box and
     * saving again re-submits the row with force_save, which skips the check.
     *
     * The modal deliberately stays open, and existing records are NOT re-read — that
     * would repopulate the form from the database and discard what the user typed.
     */
    function handleDeferredDuplicates(response, deferred, transactions, submitBtn, originalBtnText) {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerText = originalBtnText;
        }

        const data = response.data || {};
        const savedCount = (data.file_history_count || 0) + (data.cofo_count || 0) + (data.pra_count || 0);

        const heldRows = [];
        deferred.forEach(d => {
            const row = transactions[d.index];
            if (!row) return;
            row.duplicateInfo = {
                message: d.message || 'A matching record already exists on this file number.',
                matches: d.matches || [],
                strongMatch: !!d.strong_match
            };
            row.forceSave = false;
            heldRows.push(row);
        });

        if (heldRows.length > 0) {
            try {
                const alpineElement = document.querySelector('#property-transaction-dialog [x-data]');
                if (alpineElement && typeof Alpine !== 'undefined') {
                    const alpineComponent = Alpine.$data(alpineElement);
                    if (alpineComponent) {
                        alpineComponent.transactions = heldRows;
                    }
                }
            } catch (e) {
                console.warn('Could not prune already-saved transactions from the form:', e);
            }
        }

        const heldLabel = deferred.length + ' transaction' + (deferred.length > 1 ? 's' : '');
        let html = '';
        if (savedCount > 0) {
            html += '<div style="text-align:left; margin-bottom:8px;"><b>' + savedCount + ' transaction'
                + (savedCount > 1 ? 's' : '') + '</b> saved successfully.</div>';
        }
        html += '<div style="text-align:left;">' + heldLabel + ' matched a record already on this file and '
            + (deferred.length > 1 ? 'were' : 'was') + ' <b>not saved</b>. '
            + 'Review ' + (deferred.length > 1 ? 'them' : 'it') + ' below — remove '
            + (deferred.length > 1 ? 'any that are' : 'it if it is') + ' a duplicate, or tick '
            + '"this is not a duplicate" and save again.</div>';

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'Confirm possible duplicate' + (deferred.length > 1 ? 's' : ''),
                html: html,
                confirmButtonText: 'Review'
            });
        } else {
            alert(html.replace(/<[^>]+>/g, ''));
        }
    }

    // Global function to submit property transactions
    function submitPropertyTransactions(transactions, fileIndexingData) {
        console.log('=== SUBMITTING PROPERTY TRANSACTIONS ===');
        console.log('1. File Indexing Data received:', fileIndexingData);
        console.log('2. File Number:', fileIndexingData?.file_number);
        console.log('3. Original transactions:', transactions);

        // Validate file indexing data — fall back to the stored data from openPropertyTransactionModal
        if (!fileIndexingData) {
            fileIndexingData = {};
        }
        resolveFileNumber(fileIndexingData);
        if (!fileIndexingData.file_number) {
            // Try the module-level store set when the modal was last opened
            const stored = _lastFileIndexingData;
            if (stored && stored.file_number) {
                console.log('file_number missing from Alpine data, using stored fallback:', stored.file_number);
                fileIndexingData.file_number = stored.file_number;
            } else {
                // Last resort: check a DOM input named file_number
                const domFileNo = document.getElementById('file_number')?.value?.trim();
                if (domFileNo) {
                    console.log('file_number missing from Alpine data, using DOM fallback:', domFileNo);
                    fileIndexingData.file_number = domFileNo;
                }
            }
        }
        if (!fileIndexingData.file_number) {
            console.error('ERROR: File indexing data or file number is missing!');
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'File number is missing. Please close the modal and try again.',
                    confirmButtonText: 'OK'
                });
            } else {
                alert('File number is missing. Please close the modal and try again.');
            }
            return;
        }

        // Convert camelCase field names to snake_case for backend
        const convertedTransactions = transactions.map(t => ({
            record_id: t.recordId || null,
            _source: t.source || null,
            // Set by the user on a row the server held back as a possible duplicate:
            // "I have checked — this is a separate instrument, save it."
            force_save: t.forceSave ? 1 : 0,
            // 'Other' is replaced by the instrument the officer named, so every
            // destination table stores a real instrument name. The typed name rides
            // along under its own key for the record.
            transaction_type: (normalizePropertyTransactionType(t.transactionType) === 'Other'
                && String(t.otherTransactionType || '').trim())
                ? String(t.otherTransactionType).trim()
                : (t.transactionType || ''),
            other_transaction_type: String(t.otherTransactionType || '').trim(),
            instrument_type: t.instrumentType || '',
            status: t.status || 'Normal',
            op_type: t.opType || '',
            // The visible Type, sent under every column name it could belong to. Each
            // destination table is filtered to its own columns, so only the right one
            // lands -- op_type on pra, cofo_type on CofO_staging, instrument_subtype on
            // file_history_staging.
            type_value: t.typeValue || '',
            instrument_subtype: t.typeValue || '',
            instrument_category: t.instrumentCategory || '',
            cofo_type: t.cofoType || '',
            transaction_date: t.transactionDate || '',
            op_serial_number: String(t.opSerialNumber || '').trim().replace(/^0+(\d)/, '$1'),
            // Deed of Purchase. This literal IS the payload — a field the form
            // shows but that is missing here posts nothing and is lost silently.
            consideration_amount: String(t.considerationAmount || '').trim(),
            receipt_no: String(t.receiptNo || '').trim(),
            serial_no: String(t.serialNo || '').trim().replace(/^0+(\d)/, '$1'),
            page_no: String(t.pageNo || '').trim().replace(/^0+(\d)/, '$1'),
            volume_no: String(t.volumeNo || '').trim().replace(/^0+(\d)/, '$1'),
            reg_date: t.regDate || '',
            reg_time: t.regTime || '',
            land_use: t.landUse || '',
            period: t.period || '',
            period_unit: t.periodUnit || 'Years',
            comments: t.comments || '',
            first_party: t.firstParty || '',
            second_party: t.secondParty || '',
            co_first_party: t.coFirstParty || '',
            third_party: t.thirdParty || '',
            mortgagor_3: t.mortgagor3 || '',
            party_4: t.party4 || t.mortgagor3 || '',
            party_5: t.party5 || t.fourthParty || t.fifthParty || ''
        }));

        console.log('4. Converted transactions:', convertedTransactions);

        // Prepare data for submission with unified field handling
        const formData = {
            file_number: fileIndexingData.file_number,
            temp_fileno: fileIndexingData.temp_file_no || null,
            file_title: fileIndexingData.file_title,
            plot_no: fileIndexingData.plot_no || fileIndexingData.plot_number, // Handle both formats
            tp_no: fileIndexingData.tp_no,
            lpkn_no: fileIndexingData.lpkn_no,
            lga: fileIndexingData.lga,
            district: fileIndexingData.district,
            property_description: getUnifiedPropertyDescription(fileIndexingData),
            test_control: fileIndexingData.test_control || null,
            transactions: convertedTransactions
        };

        console.log('5. Final form data to submit:', formData);
        console.log('6. Form data as JSON:', JSON.stringify(formData, null, 2));

        // Disable submit button/show loading state
        const submitBtn = document.getElementById('save-transaction-btn');
        let originalBtnText = 'Save Transaction Details';
        if (submitBtn) {
            originalBtnText = submitBtn.innerText;
            submitBtn.disabled = true;
            submitBtn.innerText = 'Saving...';
        }

        // Submit to server
        $.ajax({
            url: '{{ route("property-records.storeFromIndexing") }}',
            method: 'POST',
            data: JSON.stringify(formData),
            contentType: 'application/json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function (response) {
                console.log('Success:', response);

                // File History Summary: badge each block with what actually happened
                // (SAVED / UPDATED / HELD BACK) and reload the file's history so the ON FILE
                // layer reflects the rows just written. Best-effort - a card that cannot be
                // updated must never turn a successful save into an error.
                let fhSummaryHtml = '';
                try {
                    const fhEl = document.querySelector('#property-transaction-dialog [x-data]');
                    if (fhEl && typeof Alpine !== 'undefined') {
                        const fhCmp = Alpine.$data(fhEl);
                        if (fhCmp && typeof fhCmp.fhApplySaveOutcome === 'function') {
                            fhCmp.fhApplySaveOutcome(response);
                        }
                        // Snapshot AFTER applying the outcome, so the dialog carries the
                        // SAVED / UPDATED / HELD BACK badges rather than the pre-save state.
                        if (fhCmp && typeof fhCmp.fhSummaryHtml === 'function') {
                            fhSummaryHtml = fhCmp.fhSummaryHtml();
                        }
                    }
                } catch (e) {
                    console.warn('File History Summary: could not apply save outcome', e);
                }

                // Partial save: everything that was not a duplicate is already stored.
                // The rows the server held back come back here for the user to confirm.
                const deferred = response.duplicates || [];
                if (deferred.length > 0) {
                    handleDeferredDuplicates(response, deferred, transactions, submitBtn, originalBtnText);
                    return;
                }

                // Build detailed toast message showing where records went
                let toastParts = [];
                const data = response.data || {};
                const fileHistoryCount = data.file_history_count || 0;
                const cofoCount = data.cofo_count || 0;
                const praCount = data.pra_count || 0;

                if (fileHistoryCount > 0) {
                    toastParts.push('<div class="flex items-center gap-2 mb-1"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg> <span>' + fileHistoryCount + ' transaction' + (fileHistoryCount > 1 ? 's' : '') + ' &rarr; <b>File History</b></span></div>');
                }
                if (cofoCount > 0) {
                    toastParts.push('<div class="flex items-center gap-2 mb-1"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> <span>' + cofoCount + ' CofO transaction' + (cofoCount > 1 ? 's' : '') + ' &rarr; <b>CofO</b></span></div>');
                }
                if (praCount > 0) {
                    toastParts.push('<div class="flex items-center gap-2 mb-1"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg> <span>' + praCount + ' OP transaction' + (praCount > 1 ? 's' : '') + ' &rarr; <b>PRA</b></span></div>');
                }

                const detailHtml = toastParts.length > 0
                    ? '<div style="text-align:left; margin-top:8px;">' + toastParts.join('') + '</div>'
                    : '';

                // When the indexing form was opened from another page (e.g. the
                // New KANGIS tracker) via ?return_to=..., redirect back after a
                // successful transaction save with the (possibly new) file
                // number / tracking id so the originating screen can resume.
                const buildReturnUrl = () => {
                    if (!window.returnToUrl) return null;
                    const params = new URLSearchParams();
                    if (fileIndexingData.file_number) params.set('file_number', fileIndexingData.file_number);
                    if (fileIndexingData.tracking_id) params.set('tracking_id', fileIndexingData.tracking_id);
                    if (fileIndexingData.file_title)  params.set('file_title',  fileIndexingData.file_title);
                    const separator = window.returnToUrl.includes('?') ? '&' : '?';
                    return window.returnToUrl + separator + params.toString();
                };

                // Full summary card — the same one shown after indexing, listing the
                // instruments captured and the file's whole record footprint. Falls
                // back to the inline counts toast when the shared renderer is not on
                // the page (it lives in create-indexing-dialog.js) or the server
                // could not build a summary.
                const canShowCard = typeof window.showIndexingSavedCard === 'function'
                    && (response.storage_summary || (response.instruments || []).length || fhSummaryHtml);

                if (canShowCard) {
                    window.showIndexingSavedCard(response, {
                        isUpdate: true,
                        title: 'Transactions captured',
                        // The same File History Summary the inline card shows.
                        extraHtml: fhSummaryHtml,
                        // Just the instruments — the file's full record footprint was
                        // already shown on the indexing card a moment earlier.
                        instrumentsOnly: true,
                    }).then(() => {
                        // File Snapshot for the version this capture just created.
                        // No Submission Summary here: capturing a transaction files
                        // no new file, so there are no destinations to review.
                        if (typeof window.showFileSnapshotCard === 'function' && response.snapshot) {
                            return window.showFileSnapshotCard(response.snapshot);
                        }
                    }).then(() => {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerText = originalBtnText;
                        }
                        // Modal deliberately left open: the File History Summary card above
                        // now shows what was written and where. The operator closes it when
                        // they have read it.
                        if (typeof checkExistingPropertyRecords === 'function') {
                            checkExistingPropertyRecords();
                        }
                        const returnUrl = buildReturnUrl();
                        if (returnUrl) {
                            window.location.href = returnUrl;
                        }
                    });
                } else if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Saved Successfully!',
                        html: (response.message || 'Property transaction details saved successfully!') + detailHtml,
                        confirmButtonText: 'OK'
                    }).then(() => {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerText = originalBtnText;
                        }
                        // Modal deliberately left open: the File History Summary card above
                        // now shows what was written and where. The operator closes it when
                        // they have read it.
                        if (typeof checkExistingPropertyRecords === 'function') {
                            checkExistingPropertyRecords();
                        }
                        const returnUrl = buildReturnUrl();
                        if (returnUrl) {
                            window.location.href = returnUrl;
                        }
                    });
                } else {
                    alert(response.message || 'Property transaction details saved successfully!');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerText = originalBtnText;
                    }
                    // Modal deliberately left open - see the note in the branches above.
                    if (typeof checkExistingPropertyRecords === 'function') {
                        checkExistingPropertyRecords();
                    }
                    const returnUrl = buildReturnUrl();
                    if (returnUrl) {
                        window.location.href = returnUrl;
                    }
                }
            },
            error: function (xhr, status, error) {
                console.error('Error:', error);
                console.error('Response:', xhr.responseText);
                console.error('Full XHR:', xhr);

                // Re-enable button on error
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerText = originalBtnText;
                }

                let errorMessage = 'An error occurred while saving transaction details.';
                let errorDetails = '';

                if (xhr.responseJSON) {
                    console.error('Response JSON:', xhr.responseJSON);

                    if (xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }

                    // Show validation errors if present
                    if (xhr.responseJSON.errors) {
                        errorDetails = '<ul style="text-align: left; margin-top: 10px;">';
                        Object.keys(xhr.responseJSON.errors).forEach(field => {
                            const messages = xhr.responseJSON.errors[field];
                            messages.forEach(msg => {
                                errorDetails += `<li>${msg}</li>`;
                            });
                        });
                        errorDetails += '</ul>';
                    }
                }

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        html: errorMessage + errorDetails,
                        confirmButtonText: 'OK',
                        width: '600px'
                    });
                } else {
                    alert(errorMessage + (errorDetails ? '\n\nValidation Errors:\n' + errorDetails.replace(/<[^>]*>/g, '') : ''));
                }
            }
        });
    }



    // Initialize close button handlers when DOM is ready
    document.addEventListener('DOMContentLoaded', function () {
        const closePropertyTransactionBtn = document.getElementById('close-property-transaction-form');
        const cancelPropertyTransactionBtn = document.getElementById('cancel-property-transaction');

        // Close modal handlers
        if (closePropertyTransactionBtn) {
            closePropertyTransactionBtn.addEventListener('click', function () {
                closePropertyTransactionModal();
            });
        }

        if (cancelPropertyTransactionBtn) {
            cancelPropertyTransactionBtn.addEventListener('click', function () {
                closePropertyTransactionModal();
            });
        }

        // Overlay click deliberately does NOT close this dialog.
        //
        // The card holds every transaction being captured for the file — often several,
        // each with parties, dates and registration particulars typed by hand, plus any
        // Transfer of Title sections the ownership-gap check has added. Closing discards
        // all of it with no confirmation and no undo, and the backdrop is a large target
        // sitting directly behind a tall scrolling form: a mis-aimed click, or a click
        // landing through a SweetAlert as it dismisses, wipes the lot.
        //
        // It closes on the X and on Cancel — both deliberate acts on a named control.



    });
</script>

{{-- CofO duplicate check (inline card + save lock) --}}
@include('fileindexing.partials.cofo_duplicate_check')
<script>
(function () {
    function initPtmCofoDupGuard() {
        const form = document.getElementById('property-transaction-form');
        const card = document.getElementById('ptm-cofo-dup-card');
        const saveBtn = document.getElementById('save-transaction-btn');
        if (!form || !card || !window.CofoDuplicateGuard) return;
        if (form.dataset.cofoDupBound === '1') return;
        form.dataset.cofoDupBound = '1';

        // Return the fields for the first CofO-type transaction row that has a
        // file number, or null if none. (File-indexing CofO captures are usually
        // single-transaction; the first matching row drives the pre-check.)
        function getFirstCofoTxn() {
            const fileNumber = (document.getElementById('ptm-file-number')?.value || '').trim();
            if (!fileNumber) return null;

            const typeEls = form.querySelectorAll('[name^="transactions["][name$="[transaction_type]"]');
            for (const typeEl of typeEls) {
                const type = (typeEl.value || '').trim();
                if (!window.CofoDuplicateGuard.isCofoType(type)) continue;
                const m = typeEl.name.match(/transactions\[(\d+)\]/);
                if (!m) continue;
                const i = m[1];
                const g = (suffix) => {
                    const el = form.querySelector(`[name="transactions[${i}][${suffix}]"]`);
                    return el && el.value ? String(el.value).trim() : '';
                };
                return {
                    file_number: fileNumber,
                    transaction_type: type,
                    cofo_type: g('cofo_type'),
                    party_2: g('second_party'),
                    transaction_date: g('transaction_date'),
                    vol: g('volume_no'),
                    page: g('page_no'),
                    serial: g('serial_no'),
                };
            }
            return null;
        }

        // Advisory only. Saving is never blocked here: the form submits, the server
        // saves everything that is not a duplicate, and returns the duplicates for the
        // user to confirm (see handleDeferredDuplicates below).
        const guard = window.CofoDuplicateGuard.create({
            card: card,
            lockOnAnyMatch: true,
            warnOnly: true,
            getFields: getFirstCofoTxn,
            setLocked: function () {},
        });

        // Debounced runner shared across the various triggers.
        let t = null;
        const schedule = (opts) => {
            clearTimeout(t);
            t = setTimeout(() => guard.run(opts), 250);
        };

        // Delegated field changes inside the (Alpine-rendered) transactions.
        form.addEventListener('input', () => schedule());
        form.addEventListener('change', () => schedule());

        // The file number is set programmatically by the global file selector
        // (no input/change event fires). Re-check shortly after any click in the
        // dialog; the guard dedupes by field values, so unchanged clicks are no-ops.
        const dialog = document.getElementById('property-transaction-dialog');
        if (dialog) {
            dialog.addEventListener('click', () => schedule());
        }

        // Clear any lock left on the form by an earlier build of this guard.
        delete form.dataset.cofoDupLocked;
        delete form.dataset.cofoDupMessage;
        if (saveBtn) { saveBtn.disabled = false; saveBtn.classList.remove('opacity-50', 'cursor-not-allowed'); }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPtmCofoDupGuard);
    } else {
        initPtmCofoDupGuard();
    }
})();
</script>