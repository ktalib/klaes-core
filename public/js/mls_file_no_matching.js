function mlsMatchingGenerator(config) {
    return {
        batchMode: false,
        editMode: false,
        viewMode: false,
        editingId: null,
        showGenerateModal: false,
        showEditModal: false,
        batchModalOpen: false,
        currentBatchNo: '',
        batchMembers: [],
        loading: false,
        currentDate: config.currentDate,
        currentTimeOnly: config.currentTimeOnly,
        formData: {
            full_file_number: '',
            file_title: '',
            plot_number: '',
            tp_no: '',
            district_id: '',
            district_other: '',
            lga_id: '',
            customer_type: '',
            tracking_id: '',
            quantity: 1
        },
        dataFetched: false,
        fileIndexed: null,
        locationEntries: [],
        currentEntryIndex: 0,
        applyLocationToAll: false,
        districts: config.districts,
        lgas: config.lgas,
        otherDistrictId: null,

        init() {
            this.otherDistrictId = this.detectOtherDistrictId();
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
            
            this.fetchWorldTime();
            setInterval(() => this.fetchWorldTime(), 1000);
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

        initializeLocationEntries() {
            const qty = parseInt(this.formData.quantity) || 1;
            const currentLen = this.locationEntries.length;

            if (qty > currentLen) {
                for (let i = currentLen; i < qty; i++) {
                    this.locationEntries.push({
                        plot_number: this.formData.plot_number || '',
                        tp_no: this.formData.tp_no || '',
                        district_id: this.formData.district_id || '',
                        district_other: this.formData.district_other || '',
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
                entry.district_other = current.district_other;
                entry.lga_id = current.lga_id;
            });

            this.formData.plot_number = current.plot_number;
            this.formData.tp_no = current.tp_no;
            this.formData.district_id = current.district_id;
            this.formData.district_other = current.district_other;
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
            if (!config.batchMembersUrl) return;
            this.currentBatchNo = batchNo;
            this.batchModalOpen = true;
            this.batchMembers = [];
            try {
                const response = await fetch(`${config.batchMembersUrl}/${batchNo}`);
                const data = await response.json();
                if (data.success) {
                    this.batchMembers = data.data;
                    setTimeout(() => lucide.createIcons(), 50);
                }
            } catch (e) {
                console.error('Failed to fetch batch members', e);
            }
        },

        async editRecord(id) {
            if (!config.editUrl) return;
            this.editMode = true;
            this.viewMode = false;
            this.editingId = id;
            this.loading = true;
            this.showEditModal = true;
            
            try {
                const response = await fetch(`${config.editUrl}/${id}/edit`);
                const data = await response.json();
                if (data.success) {
                    const rec = data.data;
                    this.formData.full_file_number = rec.full_file_number;
                    this.formData.file_title = rec.file_name;
                    this.formData.plot_number = rec.plot_no;
                    this.formData.tp_no = rec.tp_no;
                    this.formData.location = rec.location;
                    this.formData.customer_type = rec.customer_type || '';
                    this.formData.district_other = '';
                    
                    this.setDistrictFromLocation(rec.location, rec.plot_no, rec.district_id);
                    
                    if (rec.lga) {
                        const lga = this.lgas.find(l => rec.lga.toLowerCase().trim() === l.name.toLowerCase().trim());
                        if (lga) this.formData.lga_id = lga.id;
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
            if (!config.editUrl) return;
            this.viewMode = true;
            this.editMode = false;
            this.loading = true;
            this.showEditModal = true;
            
            try {
                const response = await fetch(`${config.editUrl}/${id}/edit`);
                const data = await response.json();
                if (data.success) {
                    const rec = data.data;
                    this.formData.full_file_number = rec.full_file_number;
                    this.formData.file_title = rec.file_name;
                    this.formData.plot_number = rec.plot_no;
                    this.formData.tp_no = rec.tp_no;
                    this.formData.location = rec.location;
                    this.formData.customer_type = rec.customer_type || '';
                    this.formData.district_other = '';
                    
                    this.setDistrictFromLocation(rec.location, rec.plot_no, rec.district_id);
                    
                    if (rec.lga) {
                        const lga = this.lgas.find(l => rec.lga.toLowerCase().trim() === l.name.toLowerCase().trim());
                        if (lga) this.formData.lga_id = lga.id;
                    }
                }
            } catch (e) {
                console.error('Failed to fetch record for view', e);
            } finally {
                this.loading = false;
            }
        },

        async updateRecord() {
            if (!config.updateUrl) return;
            this.loading = true;
            try {
                const response = await fetch(`${config.updateUrl}/${this.editingId}/update`, {
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
                        district_id: this.prepareDistrictValue(this.formData.district_id),
                        lga_id: this.formData.lga_id,
                        location: this.location
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
                if (this.isOtherDistrict(context.district_id) && context.district_other) {
                    parts.push(context.district_other);
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

        openFileSelector() {
            if (window.GlobalFileNoModal) {
                window.GlobalFileNoModal.open({
                    exclude_matched: config.excludeMatchedType || '',
                    callback: async (data) => {
                        this.formData.full_file_number = data.fileNumber;
                        this.fileIndexed = null;

                        const encoded = encodeURIComponent(data.fileNumber);

                        // Fire both checks in parallel — avoids sequential round-trips
                        const [checkResult, detailsResult] = await Promise.allSettled([
                            config.availableUrl
                                ? fetch(`${config.availableUrl}?file_number=${encoded}`).then(r => r.json())
                                : Promise.resolve(null),
                            config.detailsUrl
                                ? fetch(`${config.detailsUrl}?file_number=${encoded}`).then(r => r.json())
                                : Promise.resolve(null),
                        ]);

                        // Handle existence check
                        if (checkResult.status === 'fulfilled' && checkResult.value) {
                            this.fileIndexed = checkResult.value.exists === true;
                        }

                        // Handle file details
                        const detailsData = detailsResult.status === 'fulfilled' ? detailsResult.value : null;
                        if (detailsData && detailsData.success && detailsData.data) {
                            const details = detailsData.data;
                            this.formData.file_title    = details.title || '';
                            this.formData.plot_number   = details.plot_no || '';
                            this.formData.tp_no         = details.tp_no || '';
                            this.formData.lga_id        = details.lga_id || '';
                            this.formData.customer_type = details.customer_type || '';
                            this.formData.tracking_id   = details.tracking_id || '';
                            this.assignDistrictFromDetails(details);
                            Swal.fire({
                                toast: true, position: 'top-end', icon: 'success',
                                title: 'Details Populated', showConfirmButton: false, timer: 2000
                            });
                            this.dataFetched = true;
                        } else {
                            this.formData.tracking_id = '';
                            Swal.fire({
                                toast: true, position: 'top-end', icon: 'info',
                                title: 'No Location Details Found',
                                text: 'Please enter details manually',
                                showConfirmButton: false, timer: 3000
                            });
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

        async generateMls() {
            const requireLga = config.requireLga !== false;
            if (!this.formData.full_file_number || !this.formData.file_title || (requireLga && !this.formData.lga_id)) {
                const requiredFields = ['File Number', 'File Title'];
                if (requireLga) requiredFields.push('LGA');
                Swal.fire({
                    title: 'Missing Information',
                    text: `Please fill in all required fields (${requiredFields.join(', ')})`,
                    icon: 'warning',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }

            if (!this.validateDistrictEntries()) {
                Swal.fire({
                    title: 'Specify District',
                    text: 'Please provide the district name whenever "Other" is selected.',
                    icon: 'warning',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }

            const result = await Swal.fire({
                title: this.batchMode ? 'Match Batch?' : 'Match File Number?',
                text: `Are you sure you want to match ${this.batchMode ? this.formData.quantity : 'this'} record(s)?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#2563eb',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, Proceed',
                showLoaderOnConfirm: true,
                preConfirm: async () => {
                    try {
                        const requestFormData = {
                            ...this.formData,
                            district_id: this.prepareDistrictValue(this.formData.district_id)
                        };

                        const locationEntriesPayload = this.batchMode ? this.locationEntries.map(entry => ({
                            ...entry,
                            district_id: this.prepareDistrictValue(entry.district_id),
                            location: this.formatLocation(entry)
                        })) : null;

                        const response = await fetch(config.storeUrl, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': config.csrfToken,
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                ...requestFormData,
                                batch_mode: this.batchMode,
                                location: this.location,
                                location_entries: locationEntriesPayload
                            })
                        });
                        
                        const data = await response.json();
                        if (!response.ok || !data.success) {
                            throw new Error(data.message || 'Matching failed');
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
                    title: 'Matched!',
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
                file_title: '',
                plot_number: '',
                tp_no: '',
                district_id: '',
                district_other: '',
                lga_id: '',
                customer_type: '',
                tracking_id: '',
                quantity: 1
            };
            this.locationEntries = [];
            this.currentEntryIndex = 0;
            this.applyLocationToAll = false;
            this.dataFetched = false;
            this.fileIndexed = null;
        },

        handleDistrictSelectChange(entry = null) {
            const target = entry || (this.batchMode && !this.editMode ? this.current : this.formData);
            if (!target) return;
            if (!this.isOtherDistrict(target.district_id)) {
                target.district_other = '';
            }
            if (this.applyLocationToAll && this.batchMode && !this.editMode && target === this.current) {
                this.syncToAll('district_id');
            }
        },

        matchDistrictFromText(text) {
            if (!text) return null;
            const fragment = text.toLowerCase();
            return this.districts.find(d => fragment.includes(d.name.toLowerCase()));
        },

        extractCustomDistrict(location, plotNumber = '') {
            if (!location) return '';
            const segments = location.split(',').map(part => part.trim()).filter(Boolean);
            const plot = (plotNumber || '').toLowerCase();
            const lgaNames = this.lgas.map(l => l.name.toLowerCase());

            for (const segment of segments) {
                if (!segment) continue;
                const lower = segment.toLowerCase();
                if (plot && lower === plot) continue;
                if (lower === 'kano') continue;
                if (lgaNames.includes(lower)) continue;
                return segment;
            }
            return '';
        },

        setDistrictFromLocation(location, plotNumber = '', presetId = null) {
            this.formData.district_other = '';
            if (presetId) {
                const normalized = String(presetId).toLowerCase();
                if (normalized === 'other' && this.otherDistrictId) {
                    this.formData.district_id = this.otherDistrictId.toString();
                } else {
                    this.formData.district_id = presetId.toString();
                }
                return;
            }
            if (!location) {
                this.formData.district_id = '';
                return;
            }

            const match = this.matchDistrictFromText(location);
            if (match) {
                this.formData.district_id = match.id.toString();
                return;
            }

            const custom = this.extractCustomDistrict(location, plotNumber);
            if (custom) {
                this.formData.district_id = this.otherDistrictId ? this.otherDistrictId.toString() : '';
                this.formData.district_other = custom;
            } else {
                this.formData.district_id = '';
            }
        },

        assignDistrictFromDetails(details) {
            this.formData.district_other = '';
            if (!details) {
                this.formData.district_id = '';
                return;
            }
            if (details.district_id) {
                const normalized = String(details.district_id).toLowerCase();
                if (normalized === 'other' && this.otherDistrictId) {
                    this.formData.district_id = this.otherDistrictId.toString();
                } else {
                    this.formData.district_id = details.district_id.toString();
                }
                return;
            }
            if (details.district_name) {
                const match = this.matchDistrictFromText(details.district_name);
                if (match) {
                    this.formData.district_id = match.id.toString();
                } else {
                    this.formData.district_id = this.otherDistrictId ? this.otherDistrictId.toString() : '';
                    this.formData.district_other = details.district_name;
                }
                return;
            }
            this.formData.district_id = '';
        },

        isDistrictSelectionValid(context) {
            if (!context) return true;
            if (this.isOtherDistrict(context.district_id)) {
                return !!(context.district_other && context.district_other.trim().length);
            }
            return true;
        },

        validateDistrictEntries() {
            if (this.batchMode && !this.editMode) {
                return this.locationEntries.every(entry => this.isDistrictSelectionValid(entry));
            }
            return this.isDistrictSelectionValid(this.formData);
        },

        prepareDistrictValue(value) {
            if (value === '' || value === undefined || value === null) {
                return null;
            }
            return value;
        },

        detectOtherDistrictId() {
            if (!Array.isArray(this.districts)) return null;
            const match = this.districts.find(d => (d.name || '').trim().toLowerCase() === 'other');
            return match ? match.id : null;
        },

        isOtherDistrict(value) {
            if (value === undefined || value === null || value === '') return false;
            if (this.otherDistrictId === null || this.otherDistrictId === undefined) {
                return String(value).toLowerCase() === 'other';
            }
            return String(value) === String(this.otherDistrictId);
        }
    };
}

