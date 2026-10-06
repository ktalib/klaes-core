function dcivGenerator(config) {
    const defaultCommissioningForm = () => ({
        file_number: '',
        file_name: '',
        name_or_allottee: '',
        plot_number: '', // used for Reason on the commissioning sheet
        tp_number: '',
        location: '',
        lga: '',
        date_created: '',
        time_created: '',
        created_by: config.userName || '',
        tracking_id: ''
    });

    return {
        batchMode: false,
        editMode: false,
        viewMode: false,
        editingId: null,
        showGenerateModal: false,
        showEditModal: false,
        batchModalOpen: false,
        showCommissioningModal: false,
        serialStatuses: config.serialStatuses || {},
        currentBatchNo: '',
        batchMembers: [],
        relatedFilesModalOpen: false,
        relatedFilesData: [],
        relatedFileNumber: '',
        relatedFilesLoading: false,
        relatedFilesSource: 'dciv',
        enableRelatedFiles: false,
        isSupperAdmin: config.isSupperAdmin || false,
        loading: false,
        // Commissioning Sheet State
        commissioningForm: defaultCommissioningForm(),
        commissioningLoading: false,
        selectedRecord: null,

        openCommissioningSheetModal(record) {
            if (!record || !record.full_file_number || record.full_file_number === 'N/A') {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid File Number',
                    text: 'Please ensure the file has a valid File Number before generating a commissioning sheet.'
                });
                return;
            }

            this.selectedRecord = record;
            const cleanPlot = this.sanitizeValue(record.dciv_reason) || this.sanitizeValue(record.plot_no);
            const cleanTp = this.sanitizeValue(record.tp_no);
            const cleanLocation = this.sanitizeValue(record.location);
            const cleanLga = this.sanitizeValue(record.lga) || '';
            const cleanName = record.user && record.user.name ? record.user.name : (config.userName || 'System');

            this.commissioningForm = {
                file_number: record.full_file_number || '',
                file_name: record.file_name || '',
                name_or_allottee: record.file_name || '',
                plot_number: cleanPlot,
                tp_number: cleanTp,
                location: cleanLocation,
                lga: cleanLga,
                date_created: this.formatDateForInput(record.commissioning_date || record.created_at),
                time_created: this.formatTimeForInput(record.commissioning_time || record.formatted_time),
                created_by: cleanName,
                tracking_id: record.tracking_id || ''
            };

            if (!this.commissioningForm.date_created) {
                this.commissioningForm.date_created = this.getCurrentDate();
            }

            if (!this.commissioningForm.time_created) {
                this.commissioningForm.time_created = this.getCurrentTimeForInput();
            }

            this.showCommissioningModal = true;
            setTimeout(() => lucide.createIcons(), 50);
        },
        closeCommissioningModal() {
            this.showCommissioningModal = false;
            this.resetCommissioningForm();
        },

        resetCommissioningForm() {
            this.commissioningForm = defaultCommissioningForm();
            this.selectedRecord = null;
        },

        buildCommissioningPayloadFromRecord(record) {
            const cleanPlot = this.sanitizeValue(record?.dciv_reason) || this.sanitizeValue(record?.plot_no);
            const cleanTp = this.sanitizeValue(record?.tp_no);
            const cleanLocation = this.sanitizeValue(record?.location);
            const cleanLga = this.sanitizeValue(record?.lga) || '';
            const cleanName = record && record.user && record.user.name ? record.user.name : (config.userName || 'System');

            return {
                file_number: record?.full_file_number || '',
                file_name: record?.file_name || '',
                name_or_allottee: record?.file_name || '',
                plot_number: cleanPlot,
                tp_number: cleanTp,
                location: cleanLocation,
                lga: cleanLga,
                date_created: this.formatDateForInput(record?.commissioning_date || record?.created_at) || this.getCurrentDate(),
                time_created: this.formatTimeForInput(record?.commissioning_time || record?.formatted_time) || this.getCurrentTimeForInput(),
                created_by: cleanName,
                tracking_id: record?.tracking_id || ''
            };
        },

        async openPrinterManagerFromMenu(record) {
            if (!record || !record.full_file_number || record.full_file_number === 'N/A') {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid File Number',
                    text: 'Please ensure the file has a valid File Number before opening the print manager.'
                });
                return;
            }

            const payload = this.buildCommissioningPayloadFromRecord(record);

            this.commissioningLoading = true;
            try {
                const saveUrl = config.commissioningSheetGeneratePrintUrl || config.commissioningSheetStoreUrl;
                const response = await fetch(saveUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': config.csrfToken,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Failed to prepare commissioning sheet');
                }

                const printUrl = `/commissioning-sheet/print/${result.data.id}`;
                if (window.SmartPrintManager && typeof window.SmartPrintManager.open === 'function') {
                    window.SmartPrintManager.open(payload.file_number, 'Commissioning Sheet', printUrl);
                } else {
                    window.dispatchEvent(new CustomEvent('open-print-manager', {
                        detail: { ref: payload.file_number, type: 'Commissioning Sheet', url: printUrl }
                    }));
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Unable to Open Print Manager',
                    text: error.message || 'An unexpected error occurred'
                });
            } finally {
                this.commissioningLoading = false;
            }
        },

        async generateCommissioningSheet() {
            if (!this.commissioningForm.file_number) {
                Swal.fire({
                    icon: 'warning',
                    title: 'File Number Required',
                    text: 'Please confirm the file number before proceeding.'
                });
                return;
            }

            this.commissioningLoading = true;

            const payload = {
                ...this.commissioningForm,
                date_created: this.commissioningForm.date_created || this.getCurrentDate(),
                created_by: this.commissioningForm.created_by || config.userName || 'System'
            };

            try {
                const response = await fetch(config.commissioningSheetStoreUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': config.csrfToken,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();

                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Failed to save commissioning sheet');
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Commissioning Sheet Ready',
                    text: 'Opening print manager...',
                    timer: 1600,
                    showConfirmButton: false
                });

                const printUrl = `/commissioning-sheet/print/${result.data.id}`;
                this.closeCommissioningModal();

                if (window.SmartPrintManager && typeof window.SmartPrintManager.open === 'function') {
                    window.SmartPrintManager.open(payload.file_number, 'Commissioning Sheet', printUrl);
                } else {
                    window.dispatchEvent(new CustomEvent('open-print-manager', {
                        detail: {
                            ref: payload.file_number,
                            type: 'Commissioning Sheet',
                            url: printUrl
                        }
                    }));
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Unable to Generate Sheet',
                    text: error.message || 'An unexpected error occurred'
                });
            } finally {
                this.commissioningLoading = false;
            }
        },

        getCurrentDate() {
            if (this.currentDate) {
                return this.currentDate;
            }
            return new Date().toISOString().split('T')[0];
        },

        getCurrentTimeForInput() {
            const now = new Date();
            return now.toISOString().substring(11, 16);
        },

        formatDateForInput(value) {
            if (!value) return '';
            if (typeof value === 'string') {
                const normalized = value.split(' ')[0];
                if (/^\d{4}-\d{2}-\d{2}$/.test(normalized)) {
                    return normalized;
                }
            }
            const parsed = new Date(value);
            if (!isNaN(parsed.getTime())) {
                return parsed.toISOString().split('T')[0];
            }
            return '';
        },

        formatTimeForInput(value) {
            if (!value) return '';
            const trimmed = value.toString().trim();
            if (/^\d{2}:\d{2}$/.test(trimmed)) {
                return trimmed;
            }
            if (/^\d{2}:\d{2}:\d{2}$/.test(trimmed)) {
                return trimmed.substring(0, 5);
            }
            const amPmMatch = trimmed.match(/^(\d{1,2}):(\d{2})\s?(AM|PM)$/i);
            if (amPmMatch) {
                let hours = parseInt(amPmMatch[1], 10);
                const minutes = amPmMatch[2];
                const modifier = amPmMatch[3].toUpperCase();
                if (modifier === 'PM' && hours < 12) {
                    hours += 12;
                }
                if (modifier === 'AM' && hours === 12) {
                    hours = 0;
                }
                return `${hours.toString().padStart(2, '0')}:${minutes}`;
            }
            const parsed = new Date(`1970-01-01T${trimmed}`);
            if (!isNaN(parsed.getTime())) {
                return parsed.toISOString().substring(11, 16);
            }
            return '';
        },

        sanitizeValue(value) {
            if (!value || value === 'N/A' || value === '\u2014' || value === '—') {
                return '';
            }
            return value;
        },

        refreshing: false,
        currentTimeOnly: config.currentTimeOnly,
        formData: {
            full_file_number: '',
            prefix: 'DCIV',
            year: config.currentYear,
            serial_number: '',
            file_title: '',
            plot_number: '',
            tp_no: '',
            tracking_id: '',
            date_created: '',
            time_created: '',
            created_by: '',
            status: '',
            district_id: '',
            custom_district: '',
            lga_id: '',
            quantity: 1,
            land_use_id: '',
            purpose_id: '',
            reason: '',
            relatedFiles: [{ file_number: '', secondary_title: '', is_found: false }]
        },
        locationEntries: [],
        currentEntryIndex: 0,
        applyLocationToAll: false,
        districts: config.districts,
        lgas: config.lgas,
        landUses: config.landUses,
        purposes: config.purposes,
        loadingTrackingId: false,
        // 'manual' (default) lets the operator type the serial - and pick the year
        // it belongs to; 'auto' takes whatever dciv_serial_control says is next in
        // the current year. Manual still opens pre-filled with that same next
        // serial, so doing nothing is safe.
        serialMode: 'manual',
     
        init() {
            this.$watch('batchMode', (val) => {
                setTimeout(() => lucide.createIcons(), 50);
                if (val) this.initializeLocationEntries();
            });
            
            this.$watch('currentEntryIndex', () => {
                setTimeout(() => lucide.createIcons(), 50);
            });

            this.$watch('formData.quantity', (val) => {
                if (this.batchMode && !this.editMode) this.initializeLocationEntries();
            });

            this.$watch('showGenerateModal', (val) => {
                if (!val) {
                    this.resetForm();
                } else {
                    // resetForm() clears the serial, so a reopened form would show
                    // an empty Manual field. Pull the next serial back in.
                    if (!this.editMode && !this.formData.serial_number) this.fetchNextDciv();
                    setTimeout(() => lucide.createIcons(), 50);
                }
            });

            this.$watch('showEditModal', (val) => {
                if (!val) {
                    this.resetForm();
                } else {
                    setTimeout(() => lucide.createIcons(), 50);
                }
            });

            this.$watch('batchModalOpen', (val) => {
                if (val) setTimeout(() => lucide.createIcons(), 50);
            });
            
            this.$watch('showCommissioningModal', (val) => {
                if (val) setTimeout(() => lucide.createIcons(), 50);
            });
            
            this.$watch('formData.prefix', () => {
                // Each prefix has its own initialized years. If the one on screen is not
                // among the new prefix's, drop back to the current year rather than
                // leaving the select showing a year it does not offer.
                if (!this.availableYears.some(y => Number(y.year) === Number(this.formData.year))) {
                    this.formData.year = config.currentYear;
                }
                this.fetchNextDciv();
            });
            
            this.fetchNextDciv();
            this.fetchWorldTime();
            setInterval(() => this.fetchWorldTime(), 1000);
        },

        /**
         * The years a manual serial may be issued in for the selected prefix, each
         * as { year, initialized }.
         *
         * Built server-side from the reservoir - the years that still hold an
         * unclaimed number - with `initialized` saying whether that year's counter
         * has been set up. A year without one is still listed, and picking it says
         * so plainly rather than the year simply not being there.
         */
        get availableYears() {
            const byPrefix = (config.serialYears || {})[this.formData.prefix] || [];
            const years = byPrefix.length
                ? byPrefix.slice()
                : [{ year: Number(config.currentYear), initialized: false }];

            if (!years.some(y => Number(y.year) === Number(config.currentYear))) {
                years.push({ year: Number(config.currentYear), initialized: false });
            }

            return years.sort((a, b) => Number(b.year) - Number(a.year));
        },

        get current() {
            if (this.batchMode && !this.editMode) {
                if (!this.locationEntries[this.currentEntryIndex]) {
                    this.initializeLocationEntries();
                }
                return this.locationEntries[this.currentEntryIndex];
            }
            return this.formData;
        },

        get filteredPurposes() {
            if (!this.formData.land_use_id) return [];
            return this.purposes.filter(p => p.landuseid == this.formData.land_use_id);
        },

        initializeLocationEntries() {
            const qty = parseInt(this.formData.quantity) || 1;
            const currentLen = this.locationEntries.length;

            if (qty > currentLen) {
                for (let i = currentLen; i < qty; i++) {
                    this.locationEntries.push({
                        plot_number: this.formData.plot_number || '',
                        tp_no: this.formData.tp_no || '',
                        district_id: this.formData.district_id || '',
                        custom_district: this.formData.custom_district || '',
                        lga_id: this.formData.lga_id || '',
                        location: '' 
                    });
                }
            } else if (qty < currentLen) {
                this.locationEntries = this.locationEntries.slice(0, qty);
                if (this.currentEntryIndex >= qty) this.currentEntryIndex = qty - 1;
            }
        },

        syncToAll(field) {
            if (!this.batchMode || !this.applyLocationToAll) return;
            
            const val = this.current[field];
            this.locationEntries.forEach(entry => {
                entry[field] = val;
            });
            this.formData[field] = val;
        },

        applyLocationToBatch() {
            if (!this.batchMode) return;
            
            const current = this.current;
            this.locationEntries.forEach(entry => {
                entry.plot_number = current.plot_number;
                entry.tp_no = current.tp_no;
                entry.district_id = current.district_id;
                entry.custom_district = current.custom_district;
                entry.lga_id = current.lga_id;
            });

            this.formData.plot_number = current.plot_number;
            this.formData.tp_no = current.tp_no;
            this.formData.district_id = current.district_id;
            this.formData.custom_district = current.custom_district;
            this.formData.lga_id = current.lga_id;

            Swal.fire({
                icon: 'success',
                title: 'Locations Synced',
                text: 'Current location applied to all files in batch',
                timer: 1500,
                showConfirmButton: false
            });
        },

        nextEntry() {
            if (this.currentEntryIndex < this.locationEntries.length - 1) {
                this.currentEntryIndex++;
            }
        },

        previousEntry() {
            if (this.currentEntryIndex > 0) {
                this.currentEntryIndex--;
            }
        },

        async openBatchModal(batchNo) {
            this.currentBatchNo = batchNo;
            this.batchModalOpen = true;
            this.batchMembers = [];
            try {
                const response = await fetch(`/dciv/generation/batch-members/${batchNo}`);
                const data = await response.json();
                if (data.success) {
                    this.batchMembers = data.data;
                    setTimeout(() => lucide.createIcons(), 50);
                }
            } catch (e) {
                console.error('Failed to fetch batch members', e);
            }
        },

        async openRelatedFilesModal(detail) {
            // detail can be a string (legacy) or object {fileNumber, source, id}
            let fileNumber, source, id;
            if (typeof detail === 'object' && detail !== null) {
                fileNumber = detail.fileNumber;
                source = this.normalizeRelatedSource(detail.source);
                id = detail.id || null;
            } else {
                fileNumber = detail;
                source = 'dciv';
                id = null;
            }
            this.relatedFileNumber = fileNumber;
            this.relatedFilesSource = source;
            this.relatedFilesModalOpen = true;
            this.relatedFilesData = [];
            this.relatedFilesLoading = true;
            try {
                let url = `/dciv/generation/related-files/${encodeURIComponent(fileNumber)}?source=${source}`;
                if (id) url += `&id=${id}`;
                const response = await fetch(url);
                const data = await response.json();
                if (data.success) {
                    this.relatedFilesData = data.data;
                    this.relatedFilesSource = this.normalizeRelatedSource(data.source || source);
                }
            } catch (e) {
                console.error('Failed to fetch related files', e);
            } finally {
                this.relatedFilesLoading = false;
                setTimeout(() => lucide.createIcons(), 50);
            }
        },

        normalizeRelatedSource(source) {
            const normalized = String(source || '').toLowerCase().trim();
            if (normalized === 'fi' || normalized.indexOf('file_index') !== -1) {
                return 'fi';
            }
            return 'dciv';
        },

        async editRecord(id) {
            this.editMode = true;
            this.viewMode = false;
            this.editingId = id;
            this.loading = true;
            this.showEditModal = true;
            
            try {
                const response = await fetch(`/dciv/generation/${id}/edit`);
                const data = await response.json();
                if (data.success) {
                    const rec = data.data;
                    this.formData.full_file_number = rec.full_file_number;
                    this.formData.file_title = rec.file_name;
                    this.formData.plot_number = rec.plot_no;
                    this.formData.tp_no = rec.tp_no;
                    this.formData.location = rec.location;
                    this.formData.land_use_id = rec.land_use_id;
                    this.formData.purpose_id = rec.purpose_id;
                    this.formData.reason = rec.dciv_reason || '';
                    this.formData.tracking_id = rec.tracking_id || '';
                    this.formData.date_created = this.formatDateForInput(rec.commissioning_date || rec.created_at);
                    this.formData.time_created = this.formatTimeForInput(rec.commissioning_time || rec.formatted_time);
                    this.formData.created_by = rec.created_by_name || (rec.user && rec.user.first_name ? `${rec.user.first_name} ${rec.user.last_name || ''}`.trim() : (rec.created_by || ''));
                    this.formData.status = rec.status || '';
                    
                    if (rec.location) {
                        const district = this.districts.find(d => rec.location.toLowerCase().includes(d.name.toLowerCase()));
                        if (district) {
                            this.formData.district_id = district.id;
                            this.formData.custom_district = '';
                        } else {
                            // Try to extract district from location string if not found in list
                            const parts = rec.location.split(', ');
                            if (parts.length >= 2) {
                                // Assuming format: Plot No, District, LGA, State
                                // If district not in list, it might be the 2nd part
                                const potentialDistrict = parts[1];
                                if (potentialDistrict && potentialDistrict !== rec.lga) {
                                    this.formData.district_id = 'Other';
                                    this.formData.custom_district = potentialDistrict;
                                }
                            }
                        }
                    }
                    
                    if (rec.lga) {
                        const lga = this.lgas.find(l => rec.lga.toLowerCase().trim() === l.name.toLowerCase().trim());
                        if (lga) this.formData.lga_id = lga.id;
                    }

                    if (data.related_files && data.related_files.length > 0) {
                        this.formData.relatedFiles = data.related_files.map(rf => ({
                            ...rf,
                            is_found: true
                        }));
                    } else {
                        this.formData.relatedFiles = [{ file_number: '', secondary_title: '', is_found: false }];
                    }
                }
            } catch (e) {
                console.error('Failed to fetch record for edit', e);
            } finally {
                this.loading = false;
            }
        },

        editRecordFromBatch(id) {
            this.batchModalOpen = false;
            this.editRecord(id);
        },

        async viewRecord(id) {
            this.viewMode = true;
            this.editMode = false;
            this.loading = true;
            this.showEditModal = true;
            
            try {
                const response = await fetch(`/dciv/generation/${id}/edit`);
                const data = await response.json();
                if (data.success) {
                    const rec = data.data;
                    this.formData.full_file_number = rec.full_file_number;
                    this.formData.file_title = rec.file_name;
                    this.formData.plot_number = rec.plot_no;
                    this.formData.tp_no = rec.tp_no;
                    this.formData.location = rec.location;
                    this.formData.land_use_id = rec.land_use_id;
                    this.formData.purpose_id = rec.purpose_id;
                    this.formData.reason = rec.dciv_reason || '';
                    this.formData.tracking_id = rec.tracking_id || '';
                    this.formData.date_created = this.formatDateForInput(rec.commissioning_date || rec.created_at);
                    this.formData.time_created = this.formatTimeForInput(rec.commissioning_time || rec.formatted_time);
                    this.formData.created_by = rec.created_by_name || (rec.user && rec.user.first_name ? `${rec.user.first_name} ${rec.user.last_name || ''}`.trim() : (rec.created_by || ''));
                    this.formData.status = rec.status || '';
                    
                    if (rec.location) {
                        const district = this.districts.find(d => rec.location.toLowerCase().includes(d.name.toLowerCase()));
                        if (district) {
                            this.formData.district_id = district.id;
                            this.formData.custom_district = '';
                        } else {
                            const parts = rec.location.split(', ');
                            if (parts.length >= 2) {
                                const potentialDistrict = parts[1];
                                if (potentialDistrict && potentialDistrict !== rec.lga) {
                                    this.formData.district_id = 'Other';
                                    this.formData.custom_district = potentialDistrict;
                                }
                            }
                        }
                    }
                    
                    if (rec.lga) {
                        const lga = this.lgas.find(l => rec.lga.toLowerCase().trim() === l.name.toLowerCase().trim());
                        if (lga) this.formData.lga_id = lga.id;
                    }

                    if (data.related_files && data.related_files.length > 0) {
                        this.formData.relatedFiles = data.related_files.map(rf => ({
                            ...rf,
                            is_found: true
                        }));
                    } else {
                        this.formData.relatedFiles = [{ file_number: '', secondary_title: '', is_found: false }];
                    }
                }
            } catch (e) {
                console.error('Failed to fetch record for view', e);
            } finally {
                this.loading = false;
            }
        },

        async updateRecord() {
            this.loading = true;
            try {
                const response = await fetch(`/dciv/generation/${this.editingId}/update`, {
                    method: 'PUT',
                    headers: {
                        'X-CSRF-TOKEN': config.csrfToken,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        file_name: this.formData.file_title,
                        plot_no: this.formData.plot_number,
                        tp_no: this.formData.tp_no,
                        land_use_id: this.formData.land_use_id,
                        purpose_id: this.formData.purpose_id,
                        district_id: this.formData.district_id,
                        custom_district: this.formData.custom_district,
                        lga_id: this.formData.lga_id,
                        location: this.location,
                        reason: this.formData.reason
                    })
                });

                const data = await response.json();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Updated',
                        text: data.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => window.location.reload());
                } else {
                    Swal.fire('Error', data.message || 'Update failed', 'error');
                }
            } catch (e) {
                Swal.fire('Error', 'An unexpected error occurred', 'error');
            } finally {
                this.loading = false;
            }
        },

        get location() {
            let context = this.formData;
            if (this.batchMode && this.locationEntries[this.currentEntryIndex]) {
                context = this.locationEntries[this.currentEntryIndex];
            }
            return this.formatLocation(context);
        },

        formatLocation(context) {
            if (!context) return '';
            let parts = [];
            if (context.plot_number) parts.push(context.plot_number);
            
            if (context.district_id) {
                if (context.district_id === 'Other') {
                    if (context.custom_district) parts.push(context.custom_district);
                } else {
                    const d = this.districts.find(i => i.id == context.district_id);
                    if (d) parts.push(d.name);
                }
            }

            if (context.lga_id) {
                const l = this.lgas.find(i => i.id == context.lga_id);
                if (l) parts.push(l.name);
            }

            parts.push('Kano');
            return parts.join(', ');
        },

        // Switch between typing the serial and letting the counter pick it.
        // Leaving manual always re-reads the counter, so Auto can never show a
        // number the operator typed.
        setSerialMode(mode) {
            if (this.serialMode === mode) return;
            this.serialMode = mode;
            if (mode === 'auto') {
                this.formData.serial_number = '';
                this.formData.year = config.currentYear;
            }
            this.fetchNextDciv();
        },

        onYearPicked() {
            if (this.serialMode !== 'manual' || this.editMode) return;
            // The serial belongs to the year that was just left, so it is re-read for
            // the new one rather than carried across into a different series.
            this.formData.serial_number = '';
            this.fetchNextDciv();
        },

        onSerialTyped() {
            if (this.serialMode !== 'manual' || this.editMode) return;
            const serial = parseInt(this.formData.serial_number);
            if (!Number.isInteger(serial) || serial < 1) return;
            this.fetchNextDciv();
        },

        async fetchNextDciv() {
            // A manual serial is only sent once the operator has actually typed
            // one; until then the lookup behaves like auto and pre-fills the field.
            const serial = parseInt(this.formData.serial_number);
            const useManualSerial = this.serialMode === 'manual'
                && !this.editMode
                && Number.isInteger(serial) && serial > 0;

            this.loadingTrackingId = true;
            this.nextDciv = null; 
            try {
                const manualYear = this.serialMode === 'manual' && !this.editMode
                    ? Number(this.formData.year)
                    : null;

                const url = `${config.availableUrl}?limit=1&prefix=${this.formData.prefix}`
                    + (useManualSerial ? `&serial=${serial}` : '')
                    + (manualYear ? `&year=${manualYear}` : '');
                const response = await fetch(url);
                const result = await response.json();
                if (result.success && Array.isArray(result.taken) && result.taken.length > 0) {
                    // The serial exists but has already been issued.
                    this.nextDciv = { tracking_id: 'Already Used', dciv_awaiting_fileno: result.taken[0] };
                    this.formData.year = result.year || config.currentYear;
                } else if (result.success && result.data.length > 0) {
                    this.nextDciv = result.data[0];
                    if (!useManualSerial) this.formData.serial_number = result.next_serial;
                    if (!manualYear) this.formData.year = result.year;
                } else if (result.success && result.is_initialized === false && this.serialMode === 'manual' && !this.editMode) {
                    // Manual does not use the counter: the operator types the serial
                    // and the lookup only fetches its tracking ID. Until a serial is
                    // typed there is nothing to look up, so just leave the field open.
                    this.nextDciv = useManualSerial
                        ? { tracking_id: 'Not Found', dciv_awaiting_fileno: 'No Records Found' }
                        : null;
                } else if (result.success && result.is_initialized === false) {
                    // Not initialized — the counter has no starting serial yet.
                    this.nextDciv = null;
                    if (!useManualSerial) this.formData.serial_number = 'NOT SET';
                    if (!manualYear) this.formData.year = result.year || config.currentYear;

                    // The counter is set once, under System Admin → Configurable Entries →
                    // Serial Initialization, and locked there. Nothing on this page can set it.
                    // Only Auto depends on it, so only Auto is told.
                    if (this.showGenerateModal) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Serial not initialized',
                            html: 'No starting serial has been set for <strong>' + this.formData.prefix
                                + '</strong> in <strong>' + (result.year || this.formData.year) + '</strong>.<br><br>'
                                + 'Set it under <strong>System Admin → Configurable Entries → Serial Initialization (Land &amp; Deeds)</strong>, '
                                + 'then reopen this form.'
                        });
                    }
                } else {
                    console.warn('No available DCIV group records found', result);
                    this.nextDciv = { tracking_id: 'Not Found', dciv_awaiting_fileno: 'No Records Found' };
                    if (!useManualSerial) this.formData.serial_number = result.next_serial || 'N/A';
                    if (!manualYear) this.formData.year = result.year || config.currentYear;
                }
            } catch (e) {
                console.error('Failed to fetch next DCIV', e);
                this.nextDciv = { tracking_id: 'Error', dciv_awaiting_fileno: 'Error Loading' };
            } finally {
                this.loadingTrackingId = false;
            }
        },

        async fetchWorldTime() {
            try {
                const response = await fetch(config.worldTimeUrl);
                const data = await response.json();
                if (data.datetime) {
                    const dt = new Date(data.datetime);
                    this.currentDate = dt.toISOString().split('T')[0];
                    this.currentTimeOnly = dt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
                }
            } catch (e) {
                const now = new Date();
                this.currentDate = now.toISOString().split('T')[0];
                this.currentTimeOnly = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
            }
        },

        addRelatedFile() {
            this.formData.relatedFiles.push({ file_number: '', secondary_title: '', is_found: false });
            setTimeout(() => lucide.createIcons(), 50);
        },

        removeRelatedFile(index) {
            this.formData.relatedFiles.splice(index, 1);
        },

        async generateDciv() {
            if (this.serialMode === 'manual') {
                const serial = parseInt(this.formData.serial_number);
                if (!Number.isInteger(serial) || serial < 1) {
                    Swal.fire({
                        title: 'Serial Number Required',
                        text: 'Serial numbering is set to Manual. Enter a serial number, or switch to Auto.',
                        icon: 'warning',
                        confirmButtonColor: '#3b82f6'
                    });
                    return;
                }
            }

            if (!this.formData.file_title || !this.formData.lga_id) {
                Swal.fire({
                    title: 'Missing Information',
                    text: 'Please fill in all required fields (File Title, LGA)',
                    icon: 'warning',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }

            // Validate related files if enabled
            if (this.enableRelatedFiles) {
                const hasIncomplete = this.formData.relatedFiles.some(rf => !rf.file_number || !rf.secondary_title);
                if (this.formData.relatedFiles.length === 0 || hasIncomplete) {
                    Swal.fire({
                        title: 'Missing Related Files',
                        text: 'Related Files is enabled. Please fill in all Related File Number and Related File Title fields.',
                        icon: 'warning',
                        confirmButtonColor: '#3b82f6'
                    });
                    return;
                }
            }

            const result = await Swal.fire({

                title: this.batchMode ? 'Generate Batch?' : 'Generate File Number?',
                text: `Are you sure you want to generate ${this.batchMode ? this.formData.quantity : 'this'} record(s)?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#2563eb',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, Proceed',
                showLoaderOnConfirm: true,
                preConfirm: async () => {
                    try {
                        const response = await fetch(config.storeUrl, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': config.csrfToken,
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                ...this.formData,
                                batch_mode: this.batchMode,
                                serial_mode: this.serialMode,
                                location: this.location,
                                location_entries: this.batchMode ? this.locationEntries.map(entry => ({
                                    ...entry,
                                    location: this.formatLocation(entry)
                                })) : null
                            })
                        });
                        
                        const data = await response.json();
                        if (!response.ok || !data.success) {
                            throw new Error(data.message || 'Generation failed');
                        }
                        return data;
                    } catch (error) {
                        Swal.showValidationMessage(`Request failed: ${error.message}`);
                    }
                },
                allowOutsideClick: () => !Swal.isLoading()
            });

            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Generated!',
                    text: result.value.message,
                    icon: 'success',
                    confirmButtonColor: '#2563eb'
                }).then(() => {
                    window.location.reload();
                });
            }
        },

        resetForm() {
            this.editMode = false;
            this.viewMode = false;
            this.editingId = null;
            this.formData = {
                full_file_number: '',
                prefix: 'DCIV',
                year: config.currentYear,
                serial_number: '',
                file_title: '',
                plot_number: '',
                tp_no: '',
                tracking_id: '',
                date_created: '',
                time_created: '',
                created_by: '',
                status: '',
                district_id: '',
                custom_district: '',
                lga_id: '',
                quantity: 1,
                land_use_id: '',
                purpose_id: '',
                reason: '',
                relatedFiles: [{ file_number: '', secondary_title: '', is_found: false }]
            };
            this.serialMode = 'manual';
            this.locationEntries = [];
            this.currentEntryIndex = 0;
            this.applyLocationToAll = false;
            this.enableRelatedFiles = false;
            this.fetchNextDciv();
        },

        openRelatedFileSelector(index) {
            if (window.GlobalFileNoModal) {
                window.GlobalFileNoModal.open({
                    callback: async (data) => {
                        this.formData.relatedFiles[index].file_number = data.fileNumber;
                        
                        // Auto-fetch file title
                        try {
                            const response = await fetch(`/dciv/generation/lookup-title?file_number=${encodeURIComponent(data.fileNumber)}`);
                            const result = await response.json();
                            if (result.success && result.title) {
                                this.formData.relatedFiles[index].secondary_title = result.title;
                                this.formData.relatedFiles[index].is_found = true;
                            } else {
                                this.formData.relatedFiles[index].is_found = false;
                            }
                        } catch (err) {
                            console.error('Failed to auto-fetch file title', err);
                            this.formData.relatedFiles[index].is_found = false;
                        }
                    }
                });
            } else {
                console.error('GlobalFileNoModal not initialized');
                Swal.fire({
                    icon: 'error',
                    title: 'Initialization Error',
                    text: 'File number selector is not ready. Please refresh the page.'
                });
            }
        },

        hideGlobalLoading() {
            Swal.close();
        }
    };
}
