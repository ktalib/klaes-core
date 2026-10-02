@extends('layouts.app')

@section('page-title')
    {{ $pageTitle ?? 'Page Type Management' }}
@endsection
@section('content')
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <!-- Dashboard Content -->
        <div class="p-6">
          
          <div class="container mx-auto py-6 space-y-6">
            <div class="space-y-2">
                <h1 class="text-2xl font-semibold text-gray-900">Page Type &amp; Subtype Management</h1>
                <p class="text-sm text-gray-600">Maintain the taxonomy that powers Page Typing, QC, and downstream workflows.</p>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6 space-y-4">
                    <h2 class="text-lg font-medium text-gray-800">Add Page Type</h2>
                    <p class="text-sm text-gray-500">Page types represent high-level document categories such as File Cover or Application Letter.</p>
                    <form id="create-page-type-form" class="space-y-3">
                        <div>
                            <label for="page-type-name" class="block text-sm font-medium text-gray-700">Type name</label>
                            <input id="page-type-name" name="name" type="text" maxlength="120" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                   placeholder="e.g. Application Letter">
                        </div>
                        <button type="submit" class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Create Page Type
                        </button>
                    </form>
                </div>

                <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6 space-y-4">
                    <h2 class="text-lg font-medium text-gray-800">Add Page Subtype</h2>
                    <p class="text-sm text-gray-500">Subtypes group documents within a page type. Example: Under Application Letter, add Financial Application or Technical Application.</p>
                    <form id="create-page-subtype-form" class="space-y-3">
                        <div>
                            <label for="page-subtype-type" class="block text-sm font-medium text-gray-700">Parent page type</label>
                            <select id="page-subtype-type" name="page_type_id" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">Select page type</option>
                            </select>
                        </div>
                        <div>
                            <label for="page-subtype-name" class="block text-sm font-medium text-gray-700">Subtype name</label>
                            <input id="page-subtype-name" name="name" type="text" maxlength="120" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                   placeholder="e.g. Financial Application">
                        </div>
                        <button type="submit" class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Create Page Subtype
                        </button>
                    </form>
                </div>
            </div>

            <div class="bg-white shadow-sm rounded-lg border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-medium text-gray-800">Page Types</h2>
                        <p class="text-sm text-gray-500">Each row shows the generated code and number of associated subtypes.</p>
                    </div>
                    <button type="button" id="refresh-page-types"
                            class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Refresh
                    </button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200" id="page-types-table">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subtypes</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200"></tbody>
                    </table>
                </div>
                <div id="page-types-pagination" class="border-t border-gray-200 bg-gray-50 hidden"></div>
            </div>

            <div class="bg-white shadow-sm rounded-lg border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-medium text-gray-800">Page Subtypes</h2>
                        <p class="text-sm text-gray-500">Subtypes inherit their code prefix from the parent type.</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200" id="page-subtypes-table">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subtype</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Page Type</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200"></tbody>
                    </table>
                </div>
                <div id="page-subtypes-pagination" class="border-t border-gray-200 bg-gray-50 hidden"></div>
            </div>
        </div>
    </div>

    <div id="edit-entity-modal" class="fixed inset-0 z-40 hidden" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-900 bg-opacity-50" data-modal-dismiss="true"></div>
            <div class="relative w-full max-w-md bg-white rounded-lg shadow-xl">
                <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
                    <h3 id="edit-modal-title" class="text-lg font-medium text-gray-900">Edit</h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600" data-modal-dismiss="true" aria-label="Close">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 8.586l4.95-4.95a1 1 0 111.414 1.414L11.414 10l4.95 4.95a1 1 0 01-1.414 1.414L10 11.414l-4.95 4.95a1 1 0 01-1.414-1.414L8.586 10l-4.95-4.95A1 1 0 115.05 3.636L10 8.586z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
                <form id="edit-entity-form" class="px-5 py-4 space-y-4">
                    <div>
                        <label for="edit-modal-name" class="block text-sm font-medium text-gray-700">Name</label>
                        <input id="edit-modal-name" name="name" type="text" maxlength="120" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                               placeholder="Update name">
                    </div>
                    <div id="edit-modal-parent-group" class="space-y-1 hidden">
                        <label for="edit-modal-parent" class="block text-sm font-medium text-gray-700">Parent page type</label>
                        <select id="edit-modal-parent" name="page_type_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">Select page type</option>
                        </select>
                    </div>
                    <div class="flex items-center justify-end space-x-2 pt-2 border-t border-gray-200">
                        <button type="button" class="px-3 py-2 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-md" data-modal-dismiss="true">Cancel</button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-md">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

 
    <script>
        (function () {
            window.pageTypeManagerConfig = {
                routes: {
                    list: '{{ route('page-type-management.page-types.index') }}',
                    createType: '{{ route('page-type-management.page-types.store') }}',
                    updateType: '{{ route('page-type-management.page-types.update', ['pageType' => '__TYPE__']) }}',
                    deleteType: '{{ route('page-type-management.page-types.destroy', ['pageType' => '__TYPE__']) }}',
                    createSubtype: '{{ route('page-type-management.page-subtypes.store') }}',
                    updateSubtype: '{{ route('page-type-management.page-subtypes.update', ['pageSubType' => '__SUBTYPE__']) }}',
                    deleteSubtype: '{{ route('page-type-management.page-subtypes.destroy', ['pageSubType' => '__SUBTYPE__']) }}'
                },
                csrfToken: '{{ csrf_token() }}'
            };
        })();
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const config = window.pageTypeManagerConfig || {};
            const state = {
                pageTypes: [],
                flattenedSubtypes: []
            };
            const uiState = {
                pagination: {
                    types: { page: 1, perPage: 10 },
                    subtypes: { page: 1, perPage: 10 }
                },
                editModal: {
                    mode: null,
                    id: null,
                    parentId: null
                }
            };

            const pageTypesTableBody = document.querySelector('#page-types-table tbody');
            const pageSubtypesTableBody = document.querySelector('#page-subtypes-table tbody');
            const pageTypeSelect = document.getElementById('page-subtype-type');
            const createTypeForm = document.getElementById('create-page-type-form');
            const createSubtypeForm = document.getElementById('create-page-subtype-form');
            const refreshButton = document.getElementById('refresh-page-types');
            const pageTypesPagination = document.getElementById('page-types-pagination');
            const pageSubtypesPagination = document.getElementById('page-subtypes-pagination');
            const editModal = document.getElementById('edit-entity-modal');
            const editModalTitle = document.getElementById('edit-modal-title');
            const editModalForm = document.getElementById('edit-entity-form');
            const editModalName = document.getElementById('edit-modal-name');
            const editModalParentGroup = document.getElementById('edit-modal-parent-group');
            const editModalParentSelect = document.getElementById('edit-modal-parent');
            const editModalDismissElements = editModal ? editModal.querySelectorAll('[data-modal-dismiss]') : [];

            const defaultHeaders = {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': config.csrfToken
            };

            function notify(message, variant = 'success') {
                if (window.Swal && typeof window.Swal.fire === 'function') {
                    Swal.fire({
                        toast: true,
                        icon: variant === 'success' ? 'success' : (variant === 'warning' ? 'warning' : 'error'),
                        position: 'top-end',
                        title: message,
                        showConfirmButton: false,
                        timer: 2400
                    });
                } else {
                    alert(message);
                }
            }

            function formatErrors(errors) {
                if (!errors) {
                    return 'Validation failed.';
                }
                const messages = [];
                Object.keys(errors).forEach(function (field) {
                    const fieldErrors = errors[field];
                    if (Array.isArray(fieldErrors)) {
                        fieldErrors.forEach(function (line) {
                            messages.push(line);
                        });
                    }
                });
                return messages.join('\n') || 'Validation failed.';
            }

            function replaceRouteTemplate(template, id) {
                return template.replace('__TYPE__', id).replace('__SUBTYPE__', id);
            }

            function getPaginationMeta(items, key) {
                const pagination = uiState.pagination[key];
                const list = Array.isArray(items) ? items : [];
                const perPage = pagination.perPage;
                const totalItems = list.length;
                const totalPages = totalItems > 0 ? Math.ceil(totalItems / perPage) : 1;
                if (pagination.page > totalPages) {
                    pagination.page = totalPages;
                }
                if (pagination.page < 1) {
                    pagination.page = 1;
                }
                const currentPage = pagination.page;
                const startIndex = (currentPage - 1) * perPage;
                const pageItems = list.slice(startIndex, startIndex + perPage);
                return {
                    pageItems,
                    totalItems,
                    totalPages,
                    currentPage,
                    startIndex,
                    perPage
                };
            }

            function updatePaginationControls(container, key, meta) {
                if (!container || !meta) {
                    return;
                }
                const { totalItems, totalPages, currentPage, startIndex, perPage } = meta;
                if (!totalItems) {
                    container.classList.add('hidden');
                    container.innerHTML = '';
                    return;
                }
                container.classList.remove('hidden');
                const from = startIndex + 1;
                const to = Math.min(startIndex + perPage, totalItems);
                container.innerHTML = `
                    <div class="flex items-center justify-between px-4 py-3 text-sm text-gray-700">
                        <span>Showing ${from}-${to} of ${totalItems}</span>
                        <div class="inline-flex items-center space-x-2">
                            <button type="button" class="px-3 py-1.5 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed" data-page="${currentPage - 1}" ${currentPage === 1 ? 'disabled' : ''}>Previous</button>
                            <span class="font-medium">Page ${currentPage} of ${totalPages}</span>
                            <button type="button" class="px-3 py-1.5 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed" data-page="${currentPage + 1}" ${currentPage >= totalPages ? 'disabled' : ''}>Next</button>
                        </div>
                    </div>`;
            }

            function bindPagination(container, key, renderFn) {
                if (!container) {
                    return;
                }
                container.addEventListener('click', function (event) {
                    const button = event.target.closest('button[data-page]');
                    if (!button || button.disabled) {
                        return;
                    }
                    const requestedPage = parseInt(button.dataset.page, 10);
                    if (Number.isNaN(requestedPage)) {
                        return;
                    }
                    const pagination = uiState.pagination[key];
                    const items = key === 'types' ? state.pageTypes : state.flattenedSubtypes;
                    const totalItems = Array.isArray(items) ? items.length : 0;
                    const totalPages = totalItems > 0 ? Math.ceil(totalItems / pagination.perPage) : 1;
                    if (requestedPage < 1 || requestedPage > totalPages) {
                        return;
                    }
                    pagination.page = requestedPage;
                    renderFn();
                });
            }

            function populateParentSelect(selectedId) {
                if (!editModalParentSelect) {
                    return;
                }
                editModalParentSelect.innerHTML = '<option value="">Select page type</option>';
                state.pageTypes.forEach(function (type) {
                    const option = document.createElement('option');
                    option.value = type.id;
                    option.textContent = `${type.name} (${type.code || '—'})`;
                    if (selectedId && String(selectedId) === String(type.id)) {
                        option.selected = true;
                    }
                    editModalParentSelect.appendChild(option);
                });
            }

            function openEditModal(mode, entity) {
                if (!editModal || !editModalForm || !editModalName) {
                    return;
                }
                uiState.editModal.mode = mode;
                uiState.editModal.id = entity ? entity.id : null;
                uiState.editModal.parentId = entity ? (entity.page_type_id || (entity.parent ? entity.parent.id : null)) : null;

                editModalTitle.textContent = mode === 'subtype' ? 'Edit Page Subtype' : 'Edit Page Type';
                editModalName.value = entity && entity.name ? entity.name : '';
                editModalName.placeholder = mode === 'subtype' ? 'e.g. Financial Application' : 'e.g. Application Letter';

                if (mode === 'subtype') {
                    editModalParentGroup.classList.remove('hidden');
                    editModalParentSelect.setAttribute('required', 'required');
                    populateParentSelect(uiState.editModal.parentId);
                } else {
                    editModalParentGroup.classList.add('hidden');
                    editModalParentSelect.removeAttribute('required');
                    editModalParentSelect.value = '';
                }

                editModal.classList.remove('hidden');
                setTimeout(function () {
                    editModalName.focus();
                }, 50);
            }

            function closeEditModal() {
                if (!editModal || !editModalForm) {
                    return;
                }
                editModal.classList.add('hidden');
                editModalForm.reset();
                editModalParentGroup.classList.add('hidden');
                editModalParentSelect.removeAttribute('required');
                uiState.editModal = {
                    mode: null,
                    id: null,
                    parentId: null
                };
            }

            function renderPageTypes() {
                const meta = getPaginationMeta(state.pageTypes, 'types');
                const rows = meta.pageItems;
                pageTypesTableBody.innerHTML = '';
                if (!rows.length) {
                    const row = document.createElement('tr');
                    row.innerHTML = '<td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No page types configured yet.</td>';
                    pageTypesTableBody.appendChild(row);
                } else {
                    rows.forEach(function (type) {
                        const row = document.createElement('tr');
                        row.dataset.id = type.id;
                        row.innerHTML = `
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${type.name}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${type.code || '—'}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${type.subtype_count}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                <button type="button" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 mr-2" data-action="edit-type" data-id="${type.id}">Edit</button>
                                <button type="button" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md border border-red-200 text-red-700 bg-white hover:bg-red-50" data-action="delete-type" data-id="${type.id}">Delete</button>
                            </td>`;
                        pageTypesTableBody.appendChild(row);
                    });
                }

                pageTypeSelect.innerHTML = '<option value="">Select page type</option>';
                state.pageTypes.forEach(function (type) {
                    const option = document.createElement('option');
                    option.value = type.id;
                    option.textContent = `${type.name} (${type.code || '—'})`;
                    pageTypeSelect.appendChild(option);
                });

                updatePaginationControls(pageTypesPagination, 'types', meta);
            }

            function renderPageSubtypes() {
                pageSubtypesTableBody.innerHTML = '';
                const subtypes = [];
                state.pageTypes.forEach(function (type) {
                    (type.subtypes || []).forEach(function (subtype) {
                        subtypes.push({
                            id: subtype.id,
                            name: subtype.name,
                            code: subtype.code,
                            page_type_id: subtype.page_type_id,
                            page_type_name: subtype.page_type_name || type.name,
                            parent: type
                        });
                    });
                });

                state.flattenedSubtypes = subtypes;
                const meta = getPaginationMeta(subtypes, 'subtypes');
                const rows = meta.pageItems;

                if (!rows.length) {
                    const row = document.createElement('tr');
                    row.innerHTML = '<td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No page subtypes configured yet.</td>';
                    pageSubtypesTableBody.appendChild(row);
                    updatePaginationControls(pageSubtypesPagination, 'subtypes', meta);
                    return;
                }

                rows.forEach(function (subtype) {
                    const row = document.createElement('tr');
                    row.dataset.id = subtype.id;
                    row.innerHTML = `
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${subtype.name}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${subtype.code || '—'}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${subtype.page_type_name || (subtype.parent ? subtype.parent.name : '—')}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                            <button type="button" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 mr-2" data-action="edit-subtype" data-id="${subtype.id}">Edit</button>
                            <button type="button" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md border border-red-200 text-red-700 bg-white hover:bg-red-50" data-action="delete-subtype" data-id="${subtype.id}">Delete</button>
                        </td>`;
                    pageSubtypesTableBody.appendChild(row);
                });

                updatePaginationControls(pageSubtypesPagination, 'subtypes', meta);
            }

            async function loadData() {
                try {
                    const response = await fetch(config.routes.list, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const payload = await response.json();
                    if (!response.ok || !payload.success) {
                        throw new Error(payload.message || 'Failed to load page types.');
                    }
                    state.pageTypes = payload.data.page_types || [];
                    uiState.pagination.types.page = 1;
                    uiState.pagination.subtypes.page = 1;
                    renderPageTypes();
                    renderPageSubtypes();
                } catch (error) {
                    console.error('Failed to load page types', error);
                    notify(error.message || 'Failed to load page types.', 'error');
                }
            }

            async function createPageType(name) {
                const body = JSON.stringify({ name });
                const response = await fetch(config.routes.createType, {
                    method: 'POST',
                    headers: defaultHeaders,
                    body
                });
                const payload = await response.json();
                if (!response.ok || !payload.success) {
                    const message = payload.errors ? formatErrors(payload.errors) : (payload.message || 'Failed to create page type.');
                    throw new Error(message);
                }
                return payload;
            }

            async function updatePageType(id, name) {
                const endpoint = replaceRouteTemplate(config.routes.updateType, id);
                const response = await fetch(endpoint, {
                    method: 'PUT',
                    headers: defaultHeaders,
                    body: JSON.stringify({ name })
                });
                const payload = await response.json();
                if (!response.ok || !payload.success) {
                    const message = payload.errors ? formatErrors(payload.errors) : (payload.message || 'Failed to update page type.');
                    throw new Error(message);
                }
                return payload;
            }

            async function deletePageType(id) {
                const endpoint = replaceRouteTemplate(config.routes.deleteType, id);
                const response = await fetch(endpoint, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': config.csrfToken
                    }
                });
                const payload = await response.json();
                if (!response.ok || !payload.success) {
                    throw new Error(payload.message || 'Failed to delete page type.');
                }
                return payload;
            }

            async function createPageSubtype(pageTypeId, name) {
                const parentId = parseInt(pageTypeId, 10);
                if (Number.isNaN(parentId)) {
                    throw new Error('Parent page type must be a valid number.');
                }
                const response = await fetch(config.routes.createSubtype, {
                    method: 'POST',
                    headers: defaultHeaders,
                    body: JSON.stringify({
                        page_type_id: parentId,
                        name
                    })
                });
                const payload = await response.json();
                if (!response.ok || !payload.success) {
                    const message = payload.errors ? formatErrors(payload.errors) : (payload.message || 'Failed to create page subtype.');
                    throw new Error(message);
                }
                return payload;
            }

            async function updatePageSubtype(id, pageTypeId, name) {
                const parentId = parseInt(pageTypeId, 10);
                if (Number.isNaN(parentId)) {
                    throw new Error('Parent page type must be a valid number.');
                }
                const endpoint = replaceRouteTemplate(config.routes.updateSubtype, id);
                const response = await fetch(endpoint, {
                    method: 'PUT',
                    headers: defaultHeaders,
                    body: JSON.stringify({
                        page_type_id: parentId,
                        name
                    })
                });
                const payload = await response.json();
                if (!response.ok || !payload.success) {
                    const message = payload.errors ? formatErrors(payload.errors) : (payload.message || 'Failed to update page subtype.');
                    throw new Error(message);
                }
                return payload;
            }

            async function deletePageSubtype(id) {
                const endpoint = replaceRouteTemplate(config.routes.deleteSubtype, id);
                const response = await fetch(endpoint, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': config.csrfToken
                    }
                });
                const payload = await response.json();
                if (!response.ok || !payload.success) {
                    throw new Error(payload.message || 'Failed to delete page subtype.');
                }
                return payload;
            }

            bindPagination(pageTypesPagination, 'types', renderPageTypes);
            bindPagination(pageSubtypesPagination, 'subtypes', renderPageSubtypes);

            if (editModalDismissElements.length) {
                editModalDismissElements.forEach(function (element) {
                    element.addEventListener('click', closeEditModal);
                });
            }

            if (editModal) {
                editModal.addEventListener('click', function (event) {
                    if (event.target.dataset.modalDismiss) {
                        closeEditModal();
                    }
                });
            }

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && editModal && !editModal.classList.contains('hidden')) {
                    closeEditModal();
                }
            });

            if (editModalForm) {
                editModalForm.addEventListener('submit', async function (event) {
                    event.preventDefault();
                    const mode = uiState.editModal.mode;
                    const id = uiState.editModal.id;
                    const submitButton = editModalForm.querySelector('button[type="submit"]');
                    const name = editModalName.value.trim();
                    if (!mode || !id) {
                        notify('Record could not be identified for editing.', 'error');
                        return;
                    }
                    if (!name) {
                        notify('Provide a name before saving.', 'warning');
                        return;
                    }
                    submitButton.disabled = true;
                    try {
                        let payload;
                        if (mode === 'type') {
                            payload = await updatePageType(id, name);
                        } else {
                            const parentValue = editModalParentSelect.value;
                            if (!parentValue) {
                                throw new Error('Select a parent page type.');
                            }
                            const parentId = parseInt(parentValue, 10);
                            if (Number.isNaN(parentId)) {
                                throw new Error('Parent page type must be a valid number.');
                            }
                            payload = await updatePageSubtype(id, parentId, name);
                        }
                        notify(payload.message || 'Record updated successfully.');
                        closeEditModal();
                        await loadData();
                    } catch (error) {
                        notify(error.message || 'Failed to update record.', 'error');
                    } finally {
                        submitButton.disabled = false;
                    }
                });
            }

            createTypeForm.addEventListener('submit', async function (event) {
                event.preventDefault();
                const submitButton = createTypeForm.querySelector('button[type="submit"]');
                const nameField = document.getElementById('page-type-name');
                const name = nameField.value.trim();
                if (!name) {
                    notify('Please provide a page type name.', 'warning');
                    return;
                }
                submitButton.disabled = true;
                try {
                    const payload = await createPageType(name);
                    notify(payload.message || 'Page type created successfully.');
                    nameField.value = '';
                    await loadData();
                } catch (error) {
                    notify(error.message, 'error');
                } finally {
                    submitButton.disabled = false;
                }
            });

            createSubtypeForm.addEventListener('submit', async function (event) {
                event.preventDefault();
                const submitButton = createSubtypeForm.querySelector('button[type="submit"]');
                const typeField = document.getElementById('page-subtype-type');
                const nameField = document.getElementById('page-subtype-name');
                const pageTypeId = typeField.value;
                const name = nameField.value.trim();
                if (!pageTypeId) {
                    notify('Select a parent page type.', 'warning');
                    return;
                }
                if (!name) {
                    notify('Provide a subtype name.', 'warning');
                    return;
                }
                submitButton.disabled = true;
                try {
                    const payload = await createPageSubtype(pageTypeId, name);
                    notify(payload.message || 'Page subtype created successfully.');
                    nameField.value = '';
                    await loadData();
                } catch (error) {
                    notify(error.message, 'error');
                } finally {
                    submitButton.disabled = false;
                }
            });

            pageTypesTableBody.addEventListener('click', async function (event) {
                const action = event.target.dataset.action;
                if (!action) {
                    return;
                }
                const id = event.target.dataset.id;
                if (!id) {
                    return;
                }

                if (action === 'edit-type') {
                    const current = state.pageTypes.find(function (type) {
                        return String(type.id) === String(id);
                    });
                    if (!current) {
                        notify('Page type not found.', 'error');
                        return;
                    }
                    openEditModal('type', current);
                    return;
                }

                if (action === 'delete-type') {
                    const confirmed = confirm('Delete this page type? Subtypes will be removed if unused.');
                    if (!confirmed) {
                        return;
                    }
                    try {
                        const payload = await deletePageType(id);
                        notify(payload.message || 'Page type deleted successfully.');
                        await loadData();
                    } catch (error) {
                        notify(error.message, 'error');
                    }
                }
            });

            pageSubtypesTableBody.addEventListener('click', async function (event) {
                const action = event.target.dataset.action;
                if (!action) {
                    return;
                }
                const id = event.target.dataset.id;
                if (!id) {
                    return;
                }

                let subtype = null;
                state.pageTypes.forEach(function (type) {
                    if (subtype) {
                        return;
                    }
                    (type.subtypes || []).forEach(function (item) {
                        if (!subtype && String(item.id) === String(id)) {
                            subtype = {
                                id: item.id,
                                name: item.name,
                                code: item.code,
                                page_type_id: item.page_type_id,
                                page_type_name: item.page_type_name || type.name,
                                parent: type
                            };
                        }
                    });
                });

                if (action === 'edit-subtype') {
                    if (!subtype) {
                        notify('Page subtype not found.', 'error');
                        return;
                    }
                    openEditModal('subtype', subtype);
                    return;
                }

                if (action === 'delete-subtype') {
                    if (!subtype) {
                        notify('Page subtype not found.', 'error');
                        return;
                    }
                    const confirmed = confirm('Delete this page subtype?');
                    if (!confirmed) {
                        return;
                    }
                    try {
                        const payload = await deletePageSubtype(id);
                        notify(payload.message || 'Page subtype deleted successfully.');
                        await loadData();
                    } catch (error) {
                        notify(error.message, 'error');
                    }
                }
            });

            refreshButton.addEventListener('click', function () {
                loadData();
            });

            loadData();
        });
    </script>
@endsection
