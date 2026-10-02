(() => {
	const apiEndpoint = '/api/file-tracker-dashboard/overview';
	const ACTIVE_STATUSES = new Set(['ACTIVE', 'PENDING', 'PENDING_ACCEPTANCE', 'IN_PROGRESS']);

	let dashboardState = {
		stats: {
			totalFiles: 0,
			activeFiles: 0,
			highPriorityFiles: 0,
			uniqueOffices: 0,
		},
		priorityBreakdown: { HIGH: 0, MEDIUM: 0, LOW: 0, OTHER: 0 },
		officeDistribution: [],
		trackers: [],
	};

	let officeLookup = {};
	let filteredTrackers = [];
	let priorityChart = null;
	let officeChart = null;
	let chartsVisible = false;
	let isLoading = false;

	const elementCache = {
		totals: () => document.getElementById('total-files'),
		active: () => document.getElementById('active-files'),
		highPriority: () => document.getElementById('high-priority'),
		officesInvolved: () => document.getElementById('offices-involved'),
		tableBody: () => document.getElementById('files-table-body'),
		noResults: () => document.getElementById('no-results'),
		searchInput: () => document.getElementById('search-input'),
		priorityFilter: () => document.getElementById('priority-filter'),
		officeFilter: () => document.getElementById('office-filter'),
		refreshBtn: () => document.getElementById('refreshBtn'),
		toggleChartsBtn: () => document.getElementById('toggleChartsBtn'),
		chartsSection: () => document.getElementById('charts-section'),
		detailsDialog: () => document.getElementById('details-dialog'),
		detailsContent: () => document.getElementById('details-content'),
		closeDetails: () => document.getElementById('close-details'),
		closeDetailsBtn: () => document.getElementById('close-details-btn'),
		printDetailsBtn: () => document.getElementById('print-details-btn'),
		clearFiltersBtn: () => document.getElementById('clear-filters'),
		createFileBtn: () => document.getElementById('createFileBtn'),
	};

	function normalizePriority(priority) {
		return String(priority || '').toUpperCase();
	}

	function hasActiveEntry(tracker) {
		if (!tracker || !Array.isArray(tracker.logEntries)) {
			return false;
		}
		return tracker.logEntries.some(entry => {
			const status = normalizePriority(entry?.status);
			return ACTIVE_STATUSES.has(status);
		});
	}

	function findActiveEntry(tracker) {
		if (!tracker || !Array.isArray(tracker.logEntries)) {
			return null;
		}
		for (let index = tracker.logEntries.length - 1; index >= 0; index -= 1) {
			const entry = tracker.logEntries[index];
			const status = normalizePriority(entry?.status);
			if (ACTIVE_STATUSES.has(status)) {
				return entry;
			}
		}
		return null;
	}

	function buildOfficeLookup() {
		officeLookup = {};

		const registerOffice = (code, name, department) => {
			if (!code) {
				return;
			}
			const normalizedCode = String(code).toUpperCase();
			if (!officeLookup[normalizedCode]) {
				officeLookup[normalizedCode] = {
					name: name || null,
					department: department || null,
				};
			} else {
				if (!officeLookup[normalizedCode].name && name) {
					officeLookup[normalizedCode].name = name;
				}
				if (!officeLookup[normalizedCode].department && department) {
					officeLookup[normalizedCode].department = department;
				}
			}
		};

		dashboardState.trackers.forEach(tracker => {
			registerOffice(tracker.currentOfficeId, tracker.currentOffice, tracker.currentOfficeDepartment);
			if (Array.isArray(tracker.logEntries)) {
				tracker.logEntries.forEach(entry => {
					registerOffice(entry?.officeId, entry?.officeName, entry?.department);
				});
			}
		});

		(dashboardState.officeDistribution || []).forEach(entry => {
			registerOffice(entry?.officeCode, entry?.officeName, entry?.department);
		});
	}

	function getOfficeInfo(code) {
		if (!code) {
			return { name: null, department: null };
		}
		const normalizedCode = String(code).toUpperCase();
		const info = officeLookup[normalizedCode];
		return info ? { name: info.name || normalizedCode, department: info.department } : { name: normalizedCode, department: null };
	}

	function getOfficeColor(officeCode) {
		if (!officeCode) {
			return { bg: 'bg-gray-100', text: 'text-gray-700', border: 'border-gray-200' };
		}
		const normalizedCode = String(officeCode).toUpperCase();
		const colorMap = {
			'CCU': { bg: 'bg-blue-100', text: 'text-blue-700', border: 'border-blue-200' },
			'RCP': { bg: 'bg-green-100', text: 'text-green-700', border: 'border-green-200' },
			'DVF': { bg: 'bg-purple-100', text: 'text-purple-700', border: 'border-purple-200' },
			'SUR': { bg: 'bg-orange-100', text: 'text-orange-700', border: 'border-orange-200' },
			'LEG': { bg: 'bg-red-100', text: 'text-red-700', border: 'border-red-200' },
			'PLN': { bg: 'bg-yellow-100', text: 'text-yellow-700', border: 'border-yellow-200' },
			'DIR': { bg: 'bg-indigo-100', text: 'text-indigo-700', border: 'border-indigo-200' },
			'CRT': { bg: 'bg-pink-100', text: 'text-pink-700', border: 'border-pink-200' },
			'ARC': { bg: 'bg-teal-100', text: 'text-teal-700', border: 'border-teal-200' },
			'FIN': { bg: 'bg-emerald-100', text: 'text-emerald-700', border: 'border-emerald-200' },
			'ITD': { bg: 'bg-cyan-100', text: 'text-cyan-700', border: 'border-cyan-200' },
			'REG': { bg: 'bg-lime-100', text: 'text-lime-700', border: 'border-lime-200' },
			'DEEDS': { bg: 'bg-rose-100', text: 'text-rose-700', border: 'border-rose-200' },
			'CAD': { bg: 'bg-violet-100', text: 'text-violet-700', border: 'border-violet-200' },
			'HC': { bg: 'bg-amber-100', text: 'text-amber-700', border: 'border-amber-200' },
			'DCIV': { bg: 'bg-slate-100', text: 'text-slate-700', border: 'border-slate-200' },
			'DL': { bg: 'bg-stone-100', text: 'text-stone-700', border: 'border-stone-200' },
			'DRER': { bg: 'bg-zinc-100', text: 'text-zinc-700', border: 'border-zinc-200' }
		};
		return colorMap[normalizedCode] || { bg: 'bg-gray-100', text: 'text-gray-700', border: 'border-gray-200' };
	}

	function getFileNumberBadgeColor(fileNumber) {
		if (!fileNumber) {
			return { bg: 'bg-gray-100', text: 'text-gray-700', border: 'border-gray-200' };
		}
		const fileStr = String(fileNumber).toUpperCase();
		if (fileStr.includes('RES')) {
			return { bg: 'bg-green-50', text: 'text-green-600', border: 'border-green-200' };
		}
		if (fileStr.includes('COM')) {
			return { bg: 'bg-blue-50', text: 'text-blue-600', border: 'border-blue-200' };
		}
		if (fileStr.includes('IND')) {
			return { bg: 'bg-orange-50', text: 'text-orange-600', border: 'border-orange-200' };
		}
		if (fileStr.includes('AG')) {
			return { bg: 'bg-yellow-50', text: 'text-yellow-600', border: 'border-yellow-200' };
		}
		if (fileStr.includes('ST-')) {
			return { bg: 'bg-purple-50', text: 'text-purple-600', border: 'border-purple-200' };
		}
		if (fileStr.includes('CON')) {
			return { bg: 'bg-cyan-50', text: 'text-cyan-600', border: 'border-cyan-200' };
		}
		return { bg: 'bg-gray-50', text: 'text-gray-600', border: 'border-gray-200' };
	}

	function getStatusLabel(status) {
		const normalized = normalizePriority(status);
		if (normalized === 'ACTIVE' || normalized === 'IN_PROGRESS') {
			return 'In Progress';
		}
		if (normalized === 'PENDING' || normalized === 'PENDING_ACCEPTANCE') {
			return 'Pending';
		}
		if (normalized === 'COMPLETED' || normalized === 'COMPLETE') {
			return 'Completed';
		}
		if (normalized === 'ON_HOLD' || normalized === 'HOLD') {
			return 'On Hold';
		}
		if (normalized === 'REVIEW' || normalized === 'UNDER_REVIEW') {
			return 'Under Review';
		}
		if (normalized === 'APPROVED') {
			return 'Approved';
		}
		if (normalized === 'REJECTED') {
			return 'Rejected';
		}
		if (!normalized) {
			return 'Unknown';
		}
		return normalized
			.toLowerCase()
			.split('_')
			.map(part => part.charAt(0).toUpperCase() + part.slice(1))
			.join(' ');
	}

	function getStatusColor(status) {
		const normalized = normalizePriority(status);
		switch (normalized) {
			case 'ACTIVE':
				return 'status-active';
			case 'IN_PROGRESS':
				return 'status-in-progress';
			case 'PENDING':
			case 'PENDING_ACCEPTANCE':
				return 'status-pending';
			case 'COMPLETED':
			case 'COMPLETE':
				return 'status-completed';
			case 'ON_HOLD':
			case 'HOLD':
				return 'status-on-hold';
			case 'REVIEW':
			case 'UNDER_REVIEW':
				return 'status-review';
			case 'APPROVED':
				return 'status-approved';
			case 'REJECTED':
				return 'status-rejected';
			default:
				return 'status-unknown';
		}
	}

	function formatTimeDifference(dateString) {
		if (!dateString) {
			return 'N/A';
		}
		const parsedDate = new Date(dateString);
		if (Number.isNaN(parsedDate.getTime())) {
			return 'N/A';
		}

		const now = new Date();
		const diffMs = now - parsedDate;
		const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
		const diffHours = Math.floor((diffMs % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
		const diffMinutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));

		if (diffDays > 0) {
			return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
		}
		if (diffHours > 0) {
			return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
		}
		if (diffMinutes > 0) {
			return `${diffMinutes} minute${diffMinutes > 1 ? 's' : ''} ago`;
		}
		return 'Just now';
	}

	function updateDashboardStats() {
		const totalElement = elementCache.totals();
		const activeElement = elementCache.active();
		const highPriorityElement = elementCache.highPriority();
		const officesElement = elementCache.officesInvolved();
		if (!totalElement || !activeElement || !highPriorityElement || !officesElement) {
			return;
		}

		const stats = dashboardState.stats || {};
		const totalFiles = typeof stats.totalFiles === 'number' ? stats.totalFiles : dashboardState.trackers.length;
		const activeFiles = typeof stats.activeFiles === 'number' ? stats.activeFiles : dashboardState.trackers.filter(hasActiveEntry).length;
		const highPriorityFiles = typeof stats.highPriorityFiles === 'number'
			? stats.highPriorityFiles
			: dashboardState.trackers.filter(tracker => normalizePriority(tracker.priority) === 'HIGH').length;

		let uniqueOffices = stats.uniqueOffices;
		if (typeof uniqueOffices !== 'number') {
			const officeIds = new Set();
			dashboardState.trackers.forEach(tracker => {
				if (tracker.currentOfficeId) {
					officeIds.add(String(tracker.currentOfficeId).toUpperCase());
				}
				if (Array.isArray(tracker.logEntries)) {
					tracker.logEntries.forEach(entry => {
						if (entry?.officeId) {
							officeIds.add(String(entry.officeId).toUpperCase());
						}
					});
				}
			});
			uniqueOffices = officeIds.size;
		}

		totalElement.textContent = totalFiles;
		activeElement.textContent = activeFiles;
		highPriorityElement.textContent = highPriorityFiles;
		officesElement.textContent = uniqueOffices;
	}

	function updateFilesTable() {
		const tableBody = elementCache.tableBody();
		const noResults = elementCache.noResults();
		if (!tableBody || !noResults) {
			return;
		}

		if (!filteredTrackers.length) {
			tableBody.innerHTML = '';
			noResults.classList.remove('hidden');
			if (window.lucide && typeof window.lucide.createIcons === 'function') {
				window.lucide.createIcons();
			}
			return;
		}

		noResults.classList.add('hidden');

		const rows = filteredTrackers.map(tracker => {
			const activeEntry = findActiveEntry(tracker);
			if (!activeEntry) {
				return '';
			}

			const priority = normalizePriority(tracker.priority);
			const priorityClass = `priority-${priority || 'UNKNOWN'}`;
			const priorityText = priority ? priority.charAt(0) + priority.slice(1).toLowerCase() : 'Unknown';
			const officeInfo = getOfficeInfo(activeEntry.officeId || tracker.currentOfficeId);
			const officeName = officeInfo.name || tracker.currentOffice || '—';
			const officeDepartment = officeInfo.department || tracker.currentOfficeDepartment || 'N/A';
			const officeCode = activeEntry.officeId || tracker.currentOfficeId || '';
			const officeColors = getOfficeColor(officeCode);
			const statusLabel = getStatusLabel(activeEntry.status);
			const statusColorClass = getStatusColor(activeEntry.status);
			const timeInOffice = formatTimeDifference(activeEntry.createdAt || activeEntry.logInDate);
			const fileNumberColors = getFileNumberBadgeColor(tracker.fileNo);

			return `
				<tr class="file-row fade-in">
					<td class="px-6 py-4 whitespace-nowrap">
						<span class="inline-flex items-center px-2.5 py-1 rounded-md text-sm font-medium border ${fileNumberColors.bg} ${fileNumberColors.text} ${fileNumberColors.border}">
							${tracker.fileNo || 'No File Number'}
						</span>
					</td>
					<td class="px-6 py-4 whitespace-nowrap">
						<div class="flex items-center">
							<div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-lg flex items-center justify-center">
								<i data-lucide="file-text" class="h-5 w-5 text-blue-600"></i>
							</div>
							<div class="ml-4">
								<div class="text-sm font-medium text-gray-900">${tracker.fileName || 'Unnamed File'}</div>
							</div>
						</div>
					</td>
					<td class="px-6 py-4 whitespace-nowrap">
						<div class="flex items-center">
							<span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium border ${officeColors.bg} ${officeColors.text} ${officeColors.border}">
								<i data-lucide="building" class="h-3 w-3 mr-1"></i>
								${officeCode || 'N/A'}
							</span>
							<div class="ml-3">
								<div class="text-sm font-medium text-gray-900">${officeName}</div>
								<div class="text-xs text-gray-500">${officeDepartment}</div>
							</div>
						</div>
					</td>
					<td class="px-6 py-4 whitespace-nowrap">
						<div class="text-sm text-gray-900">${timeInOffice}</div>
						<div class="text-xs text-gray-500">Since ${activeEntry.logInDate || '-'}</div>
					</td>
					<td class="px-6 py-4 whitespace-nowrap">
						<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${priorityClass}">${priorityText}</span>
					</td>
					<td class="px-6 py-4 whitespace-nowrap">
						<div class="flex items-center">
							<div class="h-2.5 w-2.5 rounded-full ${statusColorClass} mr-2 animate-pulse-slow"></div>
							<span class="text-sm text-gray-900">${statusLabel}</span>
						</div>
					</td>
					<td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
						<button class="view-details-btn inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50" data-tracking-id="${tracker.trackingId}">
							<i data-lucide="eye" class="h-4 w-4 mr-1"></i>
							View Details
						</button>
					</td>
				</tr>
			`;
		}).join('');

		tableBody.innerHTML = rows;
		setupViewDetailsButtons();
		if (window.lucide && typeof window.lucide.createIcons === 'function') {
			window.lucide.createIcons();
		}
	}

	function setupViewDetailsButtons() {
		document.querySelectorAll('.view-details-btn').forEach(button => {
			button.addEventListener('click', function handleViewDetails() {
				const trackingId = this.dataset.trackingId;
				showTrackerDetails(trackingId);
			});
		});
	}

	function showTrackerDetails(trackingId) {
		if (!trackingId) {
			return;
		}
		const tracker = dashboardState.trackers.find(item => item.trackingId === trackingId);
		if (!tracker) {
			return;
		}

		const priority = normalizePriority(tracker.priority);
		const priorityClass = `priority-${priority || 'UNKNOWN'}`;
		const priorityText = priority ? priority.charAt(0) + priority.slice(1).toLowerCase() : 'Unknown';
		const activeEntry = findActiveEntry(tracker);
		const officeInfo = getOfficeInfo(tracker.currentOfficeId);
		const officeName = officeInfo.name || tracker.currentOffice || tracker.currentOfficeId || '—';
		const officeDepartment = officeInfo.department || tracker.currentOfficeDepartment || 'N/A';

		const detailsContent = elementCache.detailsContent();
		if (!detailsContent) {
			return;
		}

		const movementHistory = Array.isArray(tracker.logEntries) ? tracker.logEntries.map(entry => {
			const normalizedStatus = normalizePriority(entry?.status);
			const activeMatch = activeEntry && entry?.logId && entry.logId === activeEntry.logId;
			const chipClass = activeMatch ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800';
			const statusLabel = activeMatch ? 'Active' : getStatusLabel(normalizedStatus);
			const officeDetails = getOfficeInfo(entry?.officeId);

			return `
				<div class="${activeMatch ? 'log-entry log-entry-active' : 'log-entry log-entry-completed'} p-3 rounded">
					<div class="flex items-center justify-between mb-1">
						<span class="text-sm font-medium">${officeDetails.name || entry?.officeName || 'Unknown Office'}</span>
						<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${chipClass}">
							${statusLabel}
						</span>
					</div>
					<div class="text-xs text-gray-600 space-y-1">
						<div>Log ID: <span class="font-mono">${entry?.logId || '—'}</span></div>
						<div>Logged In: ${(entry?.logInDate || '-')} at ${(entry?.logInTime || '-')}</div>
						${entry?.logOutDate ? `<div>Logged Out: ${entry.logOutDate} at ${entry.logOutTime || '-'}</div>` : ''}
						${entry?.notes ? `<div class="mt-1"><strong>Notes:</strong> ${entry.notes}</div>` : ''}
					</div>
				</div>
			`;
		}).join('') : '';

		detailsContent.innerHTML = `
			<div class="py-4 space-y-6">
				<div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
					<div>
						<h3 class="text-xl font-semibold">${tracker.fileName || 'Unnamed File'}</h3>
						<p class="text-sm text-gray-600">File No: ${tracker.fileNo || '—'} | Tracking ID: ${tracker.trackingId || '—'}</p>
					</div>
					<div class="flex items-center gap-2">
						<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${priorityClass}">${priorityText}</span>
						<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">${tracker.currentOfficeId || 'N/A'}</span>
					</div>
				</div>

				<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
					<div class="space-y-4">
						<div>
							<h4 class="text-sm font-medium text-gray-700 mb-2">File Information</h4>
							<div class="space-y-2 text-sm">
								<div class="flex justify-between">
									<span class="text-gray-600">File Name:</span>
									<span class="font-medium">${tracker.fileName || '—'}</span>
								</div>
								<div class="flex justify-between">
									<span class="text-gray-600">File No:</span>
									<span class="font-medium">${tracker.fileNo || '—'}</span>
								</div>
								<div class="flex justify-between">
									<span class="text-gray-600">Tracking ID:</span>
									<span class="font-mono">${tracker.trackingId || '—'}</span>
								</div>
								<div class="flex justify-between">
									<span class="text-gray-600">Priority:</span>
									<span class="font-medium ${priorityClass} px-2 py-0.5 rounded text-xs">${priorityText}</span>
								</div>
								<div class="flex justify-between">
									<span class="text-gray-600">Created:</span>
									<span>${tracker.createdAt ? new Date(tracker.createdAt).toLocaleString() : '—'}</span>
								</div>
							</div>
						</div>

						<div>
							<h4 class="text-sm font-medium text-gray-700 mb-2">Current Location</h4>
							<div class="space-y-2 text-sm">
								<div class="flex justify-between">
									<span class="text-gray-600">Office:</span>
									<span>${officeName}</span>
								</div>
								<div class="flex justify-between">
									<span class="text-gray-600">Office ID:</span>
									<span class="font-mono">${tracker.currentOfficeId || '—'}</span>
								</div>
								<div class="flex justify-between">
									<span class="text-gray-600">Department:</span>
									<span>${officeDepartment}</span>
								</div>
								<div class="flex justify-between">
									<span class="text-gray-600">Time in Office:</span>
									<span>${activeEntry ? formatTimeDifference(activeEntry.createdAt || activeEntry.logInDate) : 'N/A'}</span>
								</div>
								${activeEntry?.notes ? `
								<div class="mt-2 p-2 bg-blue-50 rounded border border-blue-100">
									<div class="text-xs font-medium text-blue-800 mb-1">Current Notes:</div>
									<div class="text-xs text-blue-700">${activeEntry.notes}</div>
								</div>
								` : ''}
							</div>
						</div>
					</div>

					<div>
						<h4 class="text-sm font-medium text-gray-700 mb-2">Movement History</h4>
						<div class="space-y-3 max-h-80 overflow-y-auto">
							${movementHistory || '<div class="text-sm text-gray-500">No movement history available.</div>'}
						</div>
					</div>
				</div>
			</div>
		`;

		const dialog = elementCache.detailsDialog();
		if (dialog) {
			dialog.classList.add('show');
		}
		if (window.lucide && typeof window.lucide.createIcons === 'function') {
			window.lucide.createIcons();
		}
	}

	function applyFilters() {
					const searchInput = elementCache.searchInput();
					const priorityFilter = elementCache.priorityFilter();
					const officeFilter = elementCache.officeFilter();

					const searchTerm = (searchInput?.value || '').trim().toLowerCase();
					const selectedPriority = normalizePriority(priorityFilter?.value || 'ALL');
					const selectedOffice = String(officeFilter?.value || 'ALL').toUpperCase();

					filteredTrackers = dashboardState.trackers.filter(tracker => {
						if (!hasActiveEntry(tracker)) {
							return false;
						}

						const fileName = String(tracker.fileName || '').toLowerCase();
						const fileNo = String(tracker.fileNo || '').toLowerCase();
						const trackingId = String(tracker.trackingId || '').toLowerCase();
						const matchesSearch = !searchTerm
							|| fileName.includes(searchTerm)
							|| fileNo.includes(searchTerm)
							|| trackingId.includes(searchTerm);

						const priority = normalizePriority(tracker.priority);
						const matchesPriority = selectedPriority === 'ALL' || priority === selectedPriority;

						const activeEntry = findActiveEntry(tracker);
						const activeOfficeId = activeEntry?.officeId || tracker.currentOfficeId;
						const matchesOffice = selectedOffice === 'ALL'
							|| (activeOfficeId && String(activeOfficeId).toUpperCase() === selectedOffice);

						return matchesSearch && matchesPriority && matchesOffice;
					});
				}

				function filterFiles({ skipRecalculate = false } = {}) {
					if (!skipRecalculate) {
						applyFilters();
					}
					updateFilesTable();
					if (chartsVisible) {
						updateCharts();
					}
				}

				function updateCharts() {
					updatePriorityChart();
					updateOfficeChart();
				}

				function updatePriorityChart() {
					const canvas = document.getElementById('priority-chart');
					if (!canvas) {
						return;
					}
					const ctx = canvas.getContext('2d');

					const high = filteredTrackers.filter(tracker => normalizePriority(tracker.priority) === 'HIGH').length;
					const medium = filteredTrackers.filter(tracker => normalizePriority(tracker.priority) === 'MEDIUM').length;
					const low = filteredTrackers.filter(tracker => normalizePriority(tracker.priority) === 'LOW').length;

					if (priorityChart) {
						priorityChart.destroy();
					}

					priorityChart = new Chart(ctx, {
						type: 'doughnut',
						data: {
							labels: ['High Priority', 'Medium Priority', 'Low Priority'],
							datasets: [
								{
									data: [high, medium, low],
									backgroundColor: ['#EF4444', '#F59E0B', '#10B981'],
									borderWidth: 2,
									borderColor: '#fff',
									hoverOffset: 15,
								},
							],
						},
						options: {
							responsive: true,
							maintainAspectRatio: false,
							plugins: {
								legend: {
									position: 'bottom',
									labels: {
										padding: 20,
										usePointStyle: true,
										pointStyle: 'circle',
									},
								},
								tooltip: {
									callbacks: {
										label(context) {
											const label = context.label || '';
											const value = context.raw || 0;
											const total = context.dataset.data.reduce((sum, item) => sum + item, 0) || 1;
											const percentage = Math.round((value / total) * 100);
											return `${label}: ${value} (${percentage}%)`;
										},
									},
								},
							},
							cutout: '70%',
							animation: {
								animateScale: true,
								animateRotate: true,
							},
						},
					});
				}

				function updateOfficeChart() {
					const canvas = document.getElementById('office-chart');
					if (!canvas) {
						return;
					}
					const ctx = canvas.getContext('2d');

					const officeCounts = {};
					filteredTrackers.forEach(tracker => {
						const activeEntry = findActiveEntry(tracker);
						const officeId = activeEntry?.officeId || tracker.currentOfficeId;
						if (!officeId) {
							return;
						}
						const normalizedCode = String(officeId).toUpperCase();
						officeCounts[normalizedCode] = (officeCounts[normalizedCode] || 0) + 1;
					});

					const sortedOffices = Object.entries(officeCounts)
						.sort((a, b) => b[1] - a[1])
						.slice(0, 8);

					const labels = sortedOffices.map(([officeId]) => {
						const info = getOfficeInfo(officeId);
						return info.name || officeId;
					});
					const data = sortedOffices.map(([, count]) => count);

					if (officeChart) {
						officeChart.destroy();
					}

					officeChart = new Chart(ctx, {
						type: 'bar',
						data: {
							labels,
							datasets: [
								{
									label: 'Files in Office',
									data,
									backgroundColor: 'rgba(59, 130, 246, 0.7)',
									borderColor: 'rgb(59, 130, 246)',
									borderWidth: 1,
									borderRadius: 4,
									hoverBackgroundColor: 'rgba(59, 130, 246, 1)',
								},
							],
						},
						options: {
							responsive: true,
							maintainAspectRatio: false,
							plugins: {
								legend: { display: false },
								tooltip: {
									callbacks: {
										label(context) {
											return `Files: ${context.raw}`;
										},
									},
								},
							},
							scales: {
								y: {
									beginAtZero: true,
									ticks: { precision: 0 },
									grid: { drawBorder: false },
								},
								x: {
									grid: { display: false },
								},
							},
							animation: {
								duration: 1000,
								easing: 'easeOutQuart',
							},
						},
					});
				}

				function toggleCharts() {
					const chartsSection = elementCache.chartsSection();
					const toggleBtn = elementCache.toggleChartsBtn();
					if (!chartsSection || !toggleBtn) {
						return;
					}

					chartsVisible = !chartsVisible;

					if (chartsVisible) {
						chartsSection.classList.add('show');
						toggleBtn.innerHTML = '<i data-lucide="x" class="h-4 w-4 mr-1"></i><span>Hide Charts</span>';
						toggleBtn.classList.remove('bg-purple-50');
						toggleBtn.classList.add('bg-purple-100');
						updateCharts();
					} else {
						chartsSection.classList.remove('show');
						toggleBtn.innerHTML = '<i data-lucide="bar-chart-3" class="h-4 w-4 mr-1"></i><span>Show Charts</span>';
						toggleBtn.classList.remove('bg-purple-100');
						toggleBtn.classList.add('bg-purple-50');
					}

					if (window.lucide && typeof window.lucide.createIcons === 'function') {
						window.lucide.createIcons();
					}
				}

				function setRefreshButtonLoading(state) {
					const refreshBtn = elementCache.refreshBtn();
					if (!refreshBtn) {
						return;
					}
					const icon = refreshBtn.querySelector('i');
					if (!icon) {
						return;
					}

					if (state) {
						icon.classList.add('animate-spin');
					} else {
						icon.classList.remove('animate-spin');
					}
				}

				async function loadDashboardData({ showAlertOnError = true } = {}) {
					if (isLoading) {
						return;
					}
					isLoading = true;
					setRefreshButtonLoading(true);

					try {
						const response = await fetch(apiEndpoint, {
							headers: { Accept: 'application/json' },
							cache: 'no-store',
						});

						if (!response.ok) {
							throw new Error(`Request failed with status ${response.status}`);
						}

						const payload = await response.json();
						if (!payload || payload.success === false) {
							throw new Error(payload?.message || 'Unexpected API response');
						}

						const data = payload.data || {};
						dashboardState = {
							stats: data.stats || dashboardState.stats,
							priorityBreakdown: data.priorityBreakdown || dashboardState.priorityBreakdown,
							officeDistribution: Array.isArray(data.officeDistribution) ? data.officeDistribution : [],
							trackers: Array.isArray(data.trackers) ? data.trackers : [],
						};

						buildOfficeLookup();
						applyFilters();
						updateDashboardStats();
						updateFilesTable();
						if (chartsVisible) {
							updateCharts();
						}
					} catch (error) {
						console.error('Failed to load dashboard data:', error);
						if (showAlertOnError) {
							alert('Unable to load dashboard data. Please try again.');
						}
					} finally {
						setRefreshButtonLoading(false);
						isLoading = false;
						if (window.lucide && typeof window.lucide.createIcons === 'function') {
							window.lucide.createIcons();
						}
					}
				}

				function initializeDashboard() {
					const searchInput = elementCache.searchInput();
					const priorityFilter = elementCache.priorityFilter();
					const officeFilter = elementCache.officeFilter();
					const clearFiltersBtn = elementCache.clearFiltersBtn();
					const refreshBtn = elementCache.refreshBtn();
					const toggleBtn = elementCache.toggleChartsBtn();
					const closeDetails = elementCache.closeDetails();
					const closeDetailsBtn = elementCache.closeDetailsBtn();
					const printDetailsBtn = elementCache.printDetailsBtn();
					const createFileBtn = elementCache.createFileBtn();

					if (searchInput) {
						searchInput.addEventListener('input', () => filterFiles());
					}
					if (priorityFilter) {
						priorityFilter.addEventListener('change', () => filterFiles());
					}
					if (officeFilter) {
						officeFilter.addEventListener('change', () => filterFiles());
					}
					if (clearFiltersBtn) {
						clearFiltersBtn.addEventListener('click', () => {
							if (searchInput) searchInput.value = '';
							if (priorityFilter) priorityFilter.value = 'ALL';
							if (officeFilter) officeFilter.value = 'ALL';
							document.querySelectorAll('.priority-filter-btn').forEach(button => {
								button.classList.remove('bg-blue-100', 'text-blue-700');
								button.classList.add('text-gray-500', 'hover:bg-gray-100');
							});
							document.querySelectorAll('.office-filter-btn').forEach(button => {
								button.classList.remove('bg-blue-100', 'text-blue-700');
								button.classList.add('text-gray-500', 'hover:bg-gray-100');
							});
							filterFiles();
						});
					}

					document.querySelectorAll('.priority-filter-btn').forEach(button => {
						button.addEventListener('click', function handlePriorityButton() {
							document.querySelectorAll('.priority-filter-btn').forEach(btn => {
								btn.classList.remove('bg-blue-100', 'text-blue-700');
								btn.classList.add('text-gray-500', 'hover:bg-gray-100');
							});
							this.classList.remove('text-gray-500', 'hover:bg-gray-100');
							this.classList.add('bg-blue-100', 'text-blue-700');
							if (priorityFilter) {
								priorityFilter.value = this.dataset.priority || 'ALL';
							}
							filterFiles();
						});
					});

					document.querySelectorAll('.office-filter-btn').forEach(button => {
						button.addEventListener('click', function handleOfficeButton() {
							document.querySelectorAll('.office-filter-btn').forEach(btn => {
								btn.classList.remove('bg-blue-100', 'text-blue-700');
								btn.classList.add('text-gray-500', 'hover:bg-gray-100');
							});
							this.classList.remove('text-gray-500', 'hover:bg-gray-100');
							this.classList.add('bg-blue-100', 'text-blue-700');
							if (officeFilter) {
								officeFilter.value = this.dataset.office || 'ALL';
							}
							filterFiles();
						});
					});

					if (refreshBtn) {
						refreshBtn.addEventListener('click', () => {
							loadDashboardData();
						});
					}

					if (createFileBtn) {
						createFileBtn.addEventListener('click', () => {
							alert('Redirecting to Create File Tracker page...');
						});
					}

					if (toggleBtn) {
						toggleBtn.addEventListener('click', toggleCharts);
					}
					if (closeDetails) {
						closeDetails.addEventListener('click', () => {
							const dialog = elementCache.detailsDialog();
							if (dialog) {
								dialog.classList.remove('show');
							}
						});
					}
					if (closeDetailsBtn) {
						closeDetailsBtn.addEventListener('click', () => {
							const dialog = elementCache.detailsDialog();
							if (dialog) {
								dialog.classList.remove('show');
							}
						});
					}
					if (printDetailsBtn) {
						printDetailsBtn.addEventListener('click', () => {
							alert('Print functionality would be implemented here');
						});
					}

					updateFilesTable();
					updateDashboardStats();
					loadDashboardData();
				}

				document.addEventListener('DOMContentLoaded', () => {
					initializeDashboard();
					if (window.lucide && typeof window.lucide.createIcons === 'function') {
						window.lucide.createIcons();
					}
				});
			})();



