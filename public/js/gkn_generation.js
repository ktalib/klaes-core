function gknGeneratorFixed(config) {
    return {
        batchMode: false,
        editMode: false,
        viewMode: false,
        editingId: null,
        showGenerateModal: false,
        showEditModal: false,
        batchModalOpen: false,
        serialStatuses: config.serialStatuses || {},
        currentBatchNo: '',
        batchMembers: [],
        loading: false,
        refreshing: false,
        partialUrl: config.partialUrl,
        currentDate: config.currentDate,
        currentTimeOnly: config.currentTimeOnly,
        formData: {
            full_file_number: '',
            customer_type: '',
            other_customer_type: '',
            type: 'GKN',
            prefix: 'GKN-',
            serial_number: '',
            file_title: '',
            land_use_id: '',
            purpose_id: '',
            plot_number: '',
            tp_no: '',
            district_id: '',
            lga_id: '',
            quantity: 1
        },
        locationEntries: [],
        currentEntryIndex: 0,
        applyLocationToAll: false,
        districts: config.districts,
        lgas: config.lgas,
        landUses: config.landUses,
        purposes: config.purposes,
        customerTypes: config.customerTypes,
        loadingTrackingId: false,

        get filteredPurposes() {
            if (!this.formData.land_use_id) return [];
            return this.purposes.filter(p => p.landuseid == this.formData.land_use_id);
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

        init() {
            // Expose Alpine handlers so onclick="" in partial HTML can reach this component
            window.__gknEdit = (id) => this.editRecord(id);
            window.__gknView = (id) => this.viewRecord(id);
            window.__gknOpenBatch = (batchNo) => this.openBatchModal(batchNo);

            console.log('GKN Generator Initialized', this);
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

            this.$watch('formData.type', (val) => {
                const prefixes = {
                    'GKN': 'GKN-',
                    'LPKN': 'LPKN-',
                    'MISCS': 'MISC KN '
                };
                this.formData.prefix = prefixes[val] || 'GKN-';
                this.fetchNextGkn();
            });

            this.$watch('batchModalOpen', (val) => {
                if (val) setTimeout(() => lucide.createIcons(), 50);
            });
            
            this.fetchNextGkn();
            this.fetchWorldTime();
            
            if (this.customerTypes && this.customerTypes.length > 0) {
                this.formData.customer_type = this.customerTypes[0].customer_type_name;
            }

            setInterval(() => this.fetchWorldTime(), 1000);
        },

        async refreshTable() {
            this.refreshing = true;
            try {
                const params = new URLSearchParams(window.location.search);
                const search = params.get('search') || '';
                const query = new URLSearchParams();
                if (search) query.set('search', search);
                const url = this.partialUrl + (query.toString() ? `?${query}` : '');
                const response = await fetch(url, {
                    headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const html = await response.text();
                const container = document.getElementById('gkn-records-container');
                container.innerHTML = html;
                if (window.lucide) lucide.createIcons();
            } catch (e) {
                console.error('AJAX refresh failed, falling back to reload', e);
                window.location.reload();
            } finally {
                this.refreshing = false;
            }
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
                entry.lga_id = current.lga_id;
            });

            this.formData.plot_number = current.plot_number;
            this.formData.tp_no = current.tp_no;
            this.formData.district_id = current.district_id;
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
                const response = await fetch(`/gkn/generation/batch-members/${batchNo}`);
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
            this.editMode = true;
            this.viewMode = false;
            this.editingId = id;
            this.loading = true;
            this.showEditModal = true;
            
            try {
                const response = await fetch(`/gkn/generation/${id}/edit`);
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
                    this.formData.customer_type = rec.customer_type || '';
                    
                    const isPredefined = this.customerTypes.some(ct => ct.customer_type_name === rec.customer_type);
                    if (rec.customer_type && !isPredefined) {
                        this.formData.customer_type = 'Other';
                        this.formData.other_customer_type = rec.customer_type;
                    } else {
                        this.formData.other_customer_type = '';
                    }

                    this.formData.type = rec.type || 'GKN';
                    const prefixes = { 'GKN': 'GKN-', 'LPKN': 'LPKN-', 'MISCS': 'MISC KN ' };
                    this.formData.prefix = prefixes[this.formData.type] || 'GKN-';
                    
                    if (rec.location) {
                        const district = this.districts.find(d => rec.location.toLowerCase().includes(d.name.toLowerCase()));
                        if (district) this.formData.district_id = district.id;
                    }
                    
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
            this.viewMode = true;
            this.editMode = false;
            this.loading = true;
            this.showEditModal = true;
            
            try {
                const response = await fetch(`/gkn/generation/${id}/edit`);
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
                    this.formData.customer_type = rec.customer_type || '';

                    const isPredefined = this.customerTypes.some(ct => ct.customer_type_name === rec.customer_type);
                    if (rec.customer_type && !isPredefined) {
                        this.formData.customer_type = 'Other';
                        this.formData.other_customer_type = rec.customer_type;
                    } else {
                        this.formData.other_customer_type = '';
                    }

                    this.formData.type = rec.type || 'GKN';
                    const prefixes = { 'GKN': 'GKN-', 'LPKN': 'LPKN-', 'MISCS': 'MISC KN ' };
                    this.formData.prefix = prefixes[this.formData.type] || 'GKN-';
                    
                    if (rec.location) {
                       const district = this.districts.find(d => rec.location.toLowerCase().includes(d.name.toLowerCase()));
                       if (district) this.formData.district_id = district.id;
                    }
                    
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
            this.loading = true;
            try {
                const response = await fetch(`/gkn/generation/${this.editingId}/update`, {
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
                        district_id: this.formData.district_id,
                        lga_id: this.formData.lga_id,
                        land_use_id: this.formData.land_use_id,
                        purpose_id: this.formData.purpose_id,
                        customer_type: this.formData.customer_type === 'Other' ? this.formData.other_customer_type : this.formData.customer_type,
                        location: this.location,
                        type: this.formData.type
                    })
                });

                const data = await response.json();
                if (data.success) {
                    this.showEditModal = false;
                    Swal.fire({
                        icon: 'success',
                        title: 'Updated',
                        text: data.message,
                        timer: 1500,
                        showConfirmButton: false
                    });
                    await this.refreshTable();
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
                const d = this.districts.find(i => i.id == context.district_id);
                if (d) parts.push(d.name);
            }

            if (context.lga_id) {
                const l = this.lgas.find(i => i.id == context.lga_id);
                if (l) parts.push(l.name);
            }

            parts.push('Kano');
            return parts.join(', ');
        },

        async fetchNextGkn() {
            if (!this.formData.type) {
                this.nextGkn = null;
                this.formData.serial_number = 'N/A';
                this.loadingTrackingId = false;
                return;
            }
            this.loadingTrackingId = true;
            this.nextGkn = null;
            try {
                const response = await fetch(`${config.availableUrl}?limit=1&type=${this.formData.type}`);
                const result = await response.json();
                if (result.success && result.data.length > 0) {
                    this.nextGkn = result.data[0];
                    this.formData.serial_number = result.next_serial;
                } else if (result.success && result.is_initialized === false) {
                    // Not initialized — the counter has no starting serial yet.
                    this.nextGkn = null;
                    this.formData.serial_number = 'NOT SET';
                    
                    // The counter is set once, under System Admin → Configurable Entries →
                    // Serial Initialization, and locked there. Nothing on this page can set it.
                    if (this.showGenerateModal) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Serial not initialized',
                            html: 'No starting serial has been set for <strong>' + this.formData.type + '</strong>.<br><br>'
                                + 'Set it under <strong>System Admin → Configurable Entries → Serial Initialization (Land &amp; Deeds)</strong>, '
                                + 'then reopen this form.'
                        });
                    }
                } else {
                    console.warn('No available GKN records found', result);
                    this.nextGkn = { tracking_id: 'Not Found', gkn_awaiting_fileno: 'No Records Found' };
                    this.formData.serial_number = result.next_serial || 'N/A';
                }
            } catch (e) {
                console.error('Failed to fetch next GKN', e);
                this.nextGkn = { tracking_id: 'Error', gkn_awaiting_fileno: 'Error Loading' };
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

        async generateGkn() {
            if (!this.formData.file_title || !this.formData.lga_id || !this.formData.land_use_id || !this.formData.purpose_id) {
                Swal.fire({
                    title: 'Missing Information',
                    text: 'Please fill in all required fields (File Title, Land Use, Purpose, LGA)',
                    icon: 'warning',
                    confirmButtonColor: '#3b82f6'
                });
                return;
            }

            const result = await Swal.fire({
                title: this.batchMode ? 'Generate Batch?' : 'Generate File Number?',
                text: `Are you sure you want to generate ${this.batchMode ? this.formData.quantity : 'this'} GKN record(s)?`,
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
                                customer_type: this.formData.customer_type === 'Other' ? this.formData.other_customer_type : this.formData.customer_type,
                                batch_mode: this.batchMode,
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
                this.showGenerateModal = false;
                Swal.fire({
                    title: 'Generated!',
                    text: result.value.message,
                    icon: 'success',
                    timer: 1800,
                    showConfirmButton: false,
                    confirmButtonColor: '#2563eb'
                });
                await this.refreshTable();
                this.fetchNextGkn();
            }
        },

        resetForm() {
            this.editMode = false;
            this.viewMode = false;
            this.editingId = null;
            this.formData = {
                full_file_number: '',
                customer_type: '',
                other_customer_type: '',
                type: 'GKN',
                prefix: 'GKN-',
                serial_number: '',
                file_title: '',
                land_use_id: '',
                purpose_id: '',
                plot_number: '',
                tp_no: '',
                district_id: '',
                lga_id: '',
                quantity: 1
            };
            this.locationEntries = [];
            this.currentEntryIndex = 0;
            this.applyLocationToAll = false;
            this.fetchNextGkn();

            if (this.customerTypes && this.customerTypes.length > 0) {
                this.formData.customer_type = this.customerTypes[0].customer_type_name;
            }
        }
    };
}
