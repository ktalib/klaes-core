<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Models\Customer;
use App\Services\EntityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EntityCustomerController extends Controller
{
    /**
     * @var EntityService
     */
    protected $entityService;

    private const CORPORATE_KEYWORDS = [
        ' LTD',
        'LIMITED',
        'PLC',
        'INC',
        'CORP',
        'CORPORATION',
        'COMPANY',
        'GROUP',
        'HOLDINGS',
        'ENTERPRISE',
        'ENTERPRISES',
        'SERVICES',
        'VENTURES',
        'INDUSTRIES',
        'NIG.',
        'NIGERIA',
        'GLOBAL',
        'MINISTRY',
        'AGENCY',
        'BANK',
        'HOTELS',
        'INVESTMENT',
        'RESOURCES',
        'CONSULT',
    ];

    private const MULTIPLE_KEYWORDS = [
        ' AND ',
        ' & ',
        '&',
        ',',
        ';',
        ' ESTATE OF ',
        'FAMILY',
        ' HEIRS',
        ' JOINT ',
        ' COMMUNITY',
        ' COOPERATIVE',
    ];

    private const CUSTOMER_STATUS_BADGES = [
        'Active' => 'bg-green-100 text-green-800 border-green-200',
        'Pending' => 'bg-amber-100 text-amber-800 border-amber-200',
        'Suspended' => 'bg-rose-100 text-rose-800 border-rose-200',
        'Retired' => 'bg-gray-200 text-gray-700 border-gray-300',
    ];

    /**
     * Constructor
     */
    public function __construct(EntityService $entityService)
    {
        $this->entityService = $entityService;
        $this->middleware('auth');
    }

    // ============ ENTITY MANAGEMENT ============

    /**
     * Display a listing of all entities.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function indexEntities(Request $request)
    {
        // DataTables now handles pagination/filtering via AJAX to avoid loading every record.
        $entities = collect();
        $stats = $this->entityService->getEntityStatistics();
        $PageTitle = 'Entity Management';

        return view('entities.index', compact('entities', 'stats', 'PageTitle'));
    }

    /**
     * Server-side data endpoint for the entities DataTable.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function datatableEntities(Request $request)
    {
        $columnMap = [
            0 => 'file_number',
            1 => null,
            2 => 'entity_name',
            3 => 'entity_type',
            4 => 'customers_count',
            5 => 'created_at',
            6 => null,
        ];

        $recordsTotal = Entity::count();

        $query = Entity::query()
            ->select([
                'id',
                'entity_name',
                'entity_type',
                'file_number',
                'passport_photo',
                'company_logo',
                'created_at',
            ])
            ->withCount('customers');

        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->input('entity_type'));
        }

        $searchTerm = trim((string) $request->input('filter_search', ''));
        if ($searchTerm === '') {
            $searchTerm = trim((string) $request->input('search.value', ''));
        }

        if ($searchTerm !== '') {
            $query->where(function ($builder) use ($searchTerm) {
                $builder
                    ->where('entity_name', 'like', '%' . $searchTerm . '%')
                    ->orWhere('file_number', 'like', '%' . $searchTerm . '%');
            });
        }

        $filteredCount = (clone $query)->count();

        $orderColumnIndex = (int) $request->input('order.0.column', 2);
        $orderDirection = strtolower($request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $orderColumn = $columnMap[$orderColumnIndex] ?? 'entity_name';
        if ($orderColumn === null) {
            $orderColumn = 'entity_name';
        }

        $length = (int) $request->input('length', 25);
        if ($length < 1) {
            $length = 25;
        }
        $length = min($length, 100);

        $start = (int) $request->input('start', 0);
        if ($start < 0) {
            $start = 0;
        }

        $entities = (clone $query)
            ->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get();

        $data = $entities->map(fn(Entity $entity) => $this->transformEntityForDataTable($entity))->values();

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $filteredCount,
            'data' => $data,
        ]);
    }

    /**
     * Show entity details with all linked customers.
     *
     * @param Entity $entity
     * @return \Illuminate\View\View
     */
    public function showEntity(Entity $entity)
    {
        $entity->load('customers');
        $PageTitle = 'Entity: ' . $entity->entity_name;
        return view('entities.show', compact('entity', 'PageTitle'));
    }

    /**
     * Show customers for a specific entity.
     *
     * @param Entity $entity
     * @return \Illuminate\View\View
     */
    public function showEntityCustomers(Entity $entity)
    {
        $customers = $entity->customers()->orderBy('customer_name')->get();
        $entities = collect([$entity]); // For form compatibility

        $statusCounts = $customers->groupBy('status')->map(fn($group) => $group->count());
        $typeCounts = $customers->groupBy('customer_type')->map(fn($group) => $group->count());

        $metrics = [
            'total_customers' => $customers->count(),
            'active_customers' => $statusCounts['Active'] ?? 0,
            'pending_customers' => $statusCounts['Pending'] ?? 0,
            'suspended_customers' => $statusCounts['Suspended'] ?? 0,
            'retired_customers' => $statusCounts['Retired'] ?? 0,
            'with_retired_reason' => $customers->filter(fn($c) => !empty($c->reason_retired))->count(),
            'type_breakdown' => $typeCounts->toArray(),
            'last_updated_at' => $customers->max('updated_at'),
        ];

        $PageTitle = 'Entity Customers: ' . $entity->entity_name;

        return view('customers.entity', compact('customers', 'entity', 'entities', 'metrics', 'PageTitle'));
    }

    /**
     * Show form for creating a new entity.
     *
     * @return \Illuminate\View\View
     */
    public function createEntity()
    {
        $PageTitle = 'Create Entity';
        return view('entities.create', compact('PageTitle'));
    }

    /**
     * Store a newly created entity in the database.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function storeEntity(Request $request)
    {
        $validated = $request->validate([
            'entity_type' => 'required|in:Individual,Corporate,Multiple',
            'entity_name' => 'required|string|max:255',
            'passport_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'company_logo' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        // Check for similar existing entities
        $similar = $this->entityService->findSimilarEntities(
            $validated['entity_name'],
            $validated['entity_type'],
            0.7
        );

        if ($similar->count() > 0) {
            return redirect()->back()
                ->withInput()
                ->with('warning', 'Similar entities found. Please review before creating new entity.')
                ->with('similar_entities', $similar);
        }

        // Create entity
        $entity = Entity::create([
            'entity_type' => $validated['entity_type'],
            'entity_name' => trim($validated['entity_name']),
        ]);

        // Handle media upload
        if ($request->hasFile('passport_photo') && $validated['entity_type'] === 'Individual') {
            $path = $this->entityService->storePassportPhoto($request->file('passport_photo'), $entity->id);
            $entity->update(['passport_photo' => $path]);
        }

        if ($request->hasFile('company_logo') && $validated['entity_type'] === 'Corporate') {
            $path = $this->entityService->storeCompanyLogo($request->file('company_logo'), $entity->id);
            $entity->update(['company_logo' => $path]);
        }

        return redirect()->route('entities.show', $entity)
            ->with('success', 'Entity created successfully.');
    }

    /**
     * Show form for editing an entity.
     *
     * @param Entity $entity
     * @return \Illuminate\View\View
     */
    public function editEntity(Entity $entity)
    {
        $PageTitle = 'Edit Entity: ' . $entity->entity_name;
        return view('entities.edit', compact('entity', 'PageTitle'));
    }

    /**
     * Update the specified entity in storage.
     *
     * @param Request $request
     * @param Entity $entity
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateEntity(Request $request, Entity $entity)
    {
        $validated = $request->validate([
            'entity_name' => 'required|string|max:255',
            'passport_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
            'company_logo' => 'nullable|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        $updateData = ['entity_name' => trim($validated['entity_name'])];

        // Handle passport photo update
        if ($request->hasFile('passport_photo') && $entity->entity_type === 'Individual') {
            // Delete old photo
            if ($entity->passport_photo) {
                $this->entityService->deleteMedia($entity->passport_photo);
            }
            // Store new photo
            $path = $this->entityService->storePassportPhoto($request->file('passport_photo'), $entity->id);
            $updateData['passport_photo'] = $path;
        }

        // Handle company logo update
        if ($request->hasFile('company_logo') && $entity->entity_type === 'Corporate') {
            // Delete old logo
            if ($entity->company_logo) {
                $this->entityService->deleteMedia($entity->company_logo);
            }
            // Store new logo
            $path = $this->entityService->storeCompanyLogo($request->file('company_logo'), $entity->id);
            $updateData['company_logo'] = $path;
        }

        $entity->update($updateData);

        return redirect()->route('entities.show', $entity)
            ->with('success', 'Entity updated successfully.');
    }

    /**
     * Delete an entity (only if no linked customers exist).
     *
     * @param Entity $entity
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteEntity(Entity $entity)
    {
        if ($entity->customers()->count() > 0) {
            return redirect()->back()
                ->with('error', 'Cannot delete entity with linked customers.');
        }

        // Delete media files
        if ($entity->passport_photo) {
            $this->entityService->deleteMedia($entity->passport_photo);
        }
        if ($entity->company_logo) {
            $this->entityService->deleteMedia($entity->company_logo);
        }

        $entity->delete();

        return redirect()->route('entities.index')
            ->with('success', 'Entity deleted successfully.');
    }

    // ============ CUSTOMER MANAGEMENT ============

    /**
     * Display a listing of all customers with entity information.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function indexCustomers(Request $request)
    {
        // DataTables handles the heavy lifting on the frontend now.
        $customers = collect();
        $selectedEntityLabel = null;

        if ($request->filled('entity_id')) {
            $entity = Entity::select('id', 'entity_name')->find($request->entity_id);
            $selectedEntityLabel = $entity
                ? trim(($entity->entity_name ?: 'Entity') . ' (#' . $entity->id . ')')
                : 'ID #' . $request->entity_id;
        }

        $statusCounts = Customer::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $typeCounts = Customer::selectRaw('customer_type, COUNT(*) as count')
            ->groupBy('customer_type')
            ->pluck('count', 'customer_type');

        $metrics = [
            'total_customers' => $statusCounts->sum() ?: Customer::count(),
            'active_customers' => $statusCounts['Active'] ?? 0,
            'pending_customers' => $statusCounts['Pending'] ?? 0,
            'suspended_customers' => $statusCounts['Suspended'] ?? 0,
            'retired_customers' => $statusCounts['Retired'] ?? 0,
            'with_retired_reason' => Customer::whereNotNull('reason_retired')
                ->where('reason_retired', '!=', '')
                ->count(),
            'type_breakdown' => $typeCounts->toArray(),
            'last_updated_at' => Customer::max('updated_at'),
        ];

        $PageTitle = 'Customer  Management';

        return view('customers.index', compact('customers', 'metrics', 'PageTitle', 'selectedEntityLabel'));
    }

    /**
     * Server-side data source for the customers table.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function datatableCustomers(Request $request)
    {
        $columnMap = [
            0 => 'entity_id',
            1 => 'file_number',
            2 => 'account_no',
            3 => 'customer_code',
            4 => 'customer_name',
            5 => 'customer_type',
            6 => 'retired_by',
            7 => 'reason_retired',
            8 => 'email',
            9 => 'phone',
            10 => 'property_address',
            11 => null,
        ];

        $recordsTotal = Customer::count();

        $query = Customer::query()
            ->select([
                'id',
                'entity_id',
                'file_number',
                'account_no',
                'customer_code',
                'customer_name',
                'customer_type',
                'status',
                'retired_by',
                'reason_retired',
                'email',
                'phone',
                'property_address',
                'created_at',
            ]);

        if ($request->filled('customer_type')) {
            $query->where('customer_type', $request->input('customer_type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('entity_id')) {
            $query->where('entity_id', $request->input('entity_id'));
        }

        $searchTerm = trim((string) $request->input('filter_search', ''));
        if ($searchTerm === '') {
            $searchTerm = trim((string) $request->input('search.value', ''));
        }

        if ($searchTerm !== '') {
            $query->where(function ($builder) use ($searchTerm) {
                $builder
                    ->where('customer_name', 'like', '%' . $searchTerm . '%')
                    ->orWhere('email', 'like', '%' . $searchTerm . '%')
                    ->orWhere('phone', 'like', '%' . $searchTerm . '%')
                    ->orWhere('file_number', 'like', '%' . $searchTerm . '%')
                    ->orWhere('account_no', 'like', '%' . $searchTerm . '%')
                    ->orWhere('customer_code', 'like', '%' . $searchTerm . '%')
                    ->orWhere('property_address', 'like', '%' . $searchTerm . '%');
            });
        }

        $filteredCount = (clone $query)->count();

        $orderColumnIndex = (int) $request->input('order.0.column', 4);
        $orderDirection = strtolower($request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $orderColumn = $columnMap[$orderColumnIndex] ?? 'customer_name';
        if ($orderColumn === null) {
            $orderColumn = 'customer_name';
        }

        $length = (int) $request->input('length', 25);
        if ($length < 1) {
            $length = 25;
        }
        $length = min($length, 100);

        $start = (int) $request->input('start', 0);
        if ($start < 0) {
            $start = 0;
        }

        $customers = (clone $query)
            ->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get();

        $data = $customers->map(fn(Customer $customer) => $this->transformCustomerForDataTable($customer))->values();

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $filteredCount,
            'data' => $data,
        ]);
    }

    /**
     * Show customer details.
     *
     * @param Customer $customer
     * @return \Illuminate\View\View
     */
    public function showCustomer(Customer $customer)
    {
        $customer->load('entity');
        $PageTitle = 'Customer: ' . $customer->customer_name;
        return view('customers.show', compact('customer', 'PageTitle'));
    }

    /**
     * Show form for creating a new customer.
     *
     * @return \Illuminate\View\View
     */
    public function createCustomer()
    {
        $entities = Entity::orderBy('entity_name')->limit(20)->get();
        $PageTitle = 'Create Customer';
        return view('customers.create', compact('entities', 'PageTitle'));
    }

    /**
     * Store a newly created customer in the database.
     * Implements entity matching logic:
     * 1. Check if entity_name exists
     * 2. If found → reuse entity_id
     * 3. If not → create new entity
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function storeCustomer(Request $request)
    {
        $validated = $request->validate([
            'customer_type' => 'required|in:Individual,Corporate,Multiple',
            'customer_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:50',
            'reason_retired' => 'nullable|string|max:255',
            'property_address' => 'nullable|string|max:255',
            'physical_address' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'entity_id' => 'nullable|exists:entities,id',
            'create_new_entity' => 'nullable|boolean',
        ]);

        try {
            DB::connection('sqlsrv')->transaction(function () use ($validated) {
                // Determine entity
                if ($validated['create_new_entity'] ?? false) {
                    // Create new entity
                    $entity = $this->entityService->findOrCreateEntity(
                        $validated['customer_name'],
                        $validated['customer_type']
                    );
                    $validated['entity_id'] = $entity->id;
                } elseif ($validated['entity_id'] ?? null) {
                    // Use provided entity_id
                    // Already in validated data
                } else {
                    // Try to find/create entity based on customer name
                    $entity = $this->entityService->findOrCreateEntity(
                        $validated['customer_name'],
                        $validated['customer_type']
                    );
                    $validated['entity_id'] = $entity->id;
                }

                // Add audit trail
                $validated['created_by'] = Auth::id();
                $validated['status'] = $validated['status'] ?? 'Active';

                // Create customer
                Customer::create($validated);
            });

            return redirect()->route('customers.index')
                ->with('success', 'Customer created and linked to entity successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Error creating customer: ' . $e->getMessage());
        }
    }

    /**
     * Show form for editing a customer.
     *
     * @param Customer $customer
     * @return \Illuminate\View\View
     */
    public function editCustomer(Customer $customer)
    {
        $entities = Entity::orderBy('entity_name')->limit(20)->get();
        $customer->load('entity');
        $PageTitle = 'Edit Customer: ' . $customer->customer_name;
        return view('customers.edit', compact('customer', 'entities', 'PageTitle'));
    }

    /**
     * Update the specified customer in storage.
     *
     * @param Request $request
     * @param Customer $customer
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateCustomer(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:50',
            'property_address' => 'nullable|string|max:255',
            'physical_address' => 'nullable|string|max:255',
            'status' => 'required|string|max:50',
            'reason_retired' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'entity_id' => 'nullable|exists:entities,id',
        ]);

        try {
            DB::connection('sqlsrv')->transaction(function () use ($validated, $customer) {
                $validated['updated_by'] = Auth::id();
                $customer->update($validated);
            });

            return redirect()->route('customers.show', $customer)
                ->with('success', 'Customer updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Error updating customer: ' . $e->getMessage());
        }
    }

    /**
     * Delete a customer (soft delete).
     *
     * @param Customer $customer
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteCustomer(Customer $customer)
    {
        $customer->delete();

        return redirect()->route('customers.index')
            ->with('success', 'Customer deleted successfully.');
    }

    // ============ ENTITY LINKING & MATCHING ============

    /**
     * Find similar entities (AJAX endpoint).
     * Used during customer creation to suggest existing entities.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function findSimilarEntities(Request $request)
    {
        $request->validate([
            'entity_name' => 'required|string|max:255',
            'entity_type' => 'nullable|in:Individual,Corporate,Multiple',
        ]);

        $similar = $this->entityService->findSimilarEntities(
            $request->entity_name,
            $request->entity_type,
            0.65
        );

        return response()->json([
            'success' => true,
            'data' => $similar->map(function ($entity) {
                return [
                    'id' => $entity->id,
                    'name' => $entity->entity_name,
                    'type' => $entity->entity_type,
                    'customer_count' => $entity->customers()->count(),
                    'media_url' => $entity->media_url,
                ];
            }),
        ]);
    }

    /**
     * Link multiple customers to a single entity.
     * Use case: Consolidating duplicate records.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function linkCustomersToEntity(Request $request)
    {
        $request->validate([
            'entity_id' => 'required|exists:entities,id',
            'customer_ids' => 'required|array|min:1',
            'customer_ids.*' => 'exists:customers,id',
        ]);

        try {
            $count = $this->entityService->linkCustomersToEntity(
                $request->entity_id,
                $request->customer_ids
            );

            return response()->json([
                'success' => true,
                'message' => "Successfully linked {$count} customer(s) to entity.",
                'data' => ['linked_count' => $count],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error linking customers: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Merge duplicate entities.
     * Transfers all customers from source to target entity.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function mergeEntities(Request $request)
    {
        $request->validate([
            'source_entity_id' => 'required|exists:entities,id',
            'target_entity_id' => 'required|exists:entities,id',
        ]);

        if ($request->source_entity_id === $request->target_entity_id) {
            return response()->json([
                'success' => false,
                'message' => 'Source and target entities must be different.',
            ], 422);
        }

        try {
            $this->entityService->mergeEntities(
                $request->source_entity_id,
                $request->target_entity_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Entities merged successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error merging entities: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get entity statistics (AJAX endpoint).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatistics()
    {
        $stats = $this->entityService->getEntityStatistics();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Search entities (AJAX endpoint).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchEntities(Request $request)
    {
        $request->validate([
            'q' => 'required|string|max:255',
            'type' => 'nullable|in:Individual,Corporate,Multiple',
        ]);

        $entities = $this->entityService->searchEntities(
            $request->q,
            $request->type,
            50
        );

        return response()->json([
            'success' => true,
            'data' => $entities->map(function ($entity) {
                return [
                    'id' => $entity->id,
                    'name' => $entity->entity_name,
                    'type' => $entity->entity_type,
                    'customer_count' => $entity->customers()->count(),
                ];
            }),
        ]);
    }

    /**
     * Get customers for an entity (AJAX endpoint).
     *
     * @param Entity $entity
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEntityCustomers(Entity $entity)
    {
        $customers = $entity->customers()
            ->select(['id', 'customer_name', 'email', 'phone', 'status'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $customers,
        ]);
    }

    /**
     * Locate entity and customer staging records for a given file number.
     *
     * @param Request $request
     * @param string $fileNumber
     * @return \Illuminate\Http\JsonResponse
     */
    public function lookupByFileNumber(Request $request, string $fileNumber)
    {
        $normalized = $this->normalizeFileNumberInput($fileNumber);

        if ($normalized === '') {
            return response()->json([
                'success' => false,
                'message' => 'A valid file number is required to perform the lookup.',
            ], 422);
        }

        $collapsed = $this->collapseFileNumber($normalized);
        $expression = $this->buildFileNumberCollapseExpression();

        $customerQuery = Customer::query()
            ->with('entity')
            ->whereNotNull('file_number')
            ->where(function ($query) use ($normalized, $collapsed, $expression) {
                $query->whereRaw('UPPER(file_number) = ?', [$normalized])
                    ->orWhereRaw("{$expression} = ?", [$collapsed]);
            })
            ->whereNull('deleted_at');

        $customer = (clone $customerQuery)
            ->whereNull('reason_retired')
            ->orderByDesc('id')
            ->orderByDesc('updated_at')
            ->first();

        if (!$customer) {
            $customer = (clone $customerQuery)
                ->orderByRaw("CASE WHEN reason_retired IS NULL THEN 0 ELSE 1 END")
                ->orderByRaw("CASE WHEN status = 'Active' THEN 0 ELSE 1 END")
                ->orderByDesc('id')
                ->orderByDesc('updated_at')
                ->first();
        }

        $entity = $customer?->entity;

        if (!$entity) {
            $entity = Entity::query()
                ->withCount('customers')
                ->whereNotNull('file_number')
                ->where(function ($query) use ($normalized, $collapsed, $expression) {
                    $query->whereRaw('UPPER(file_number) = ?', [$normalized])
                        ->orWhereRaw("{$expression} = ?", [$collapsed]);
                })
                ->orderByDesc('updated_at')
                ->first();
        }

        if (!$customer && $entity && ($entity->customers_count ?? 0) > 0) {
            $customer = $entity->customers()
                ->whereNull('reason_retired')
                ->orderByDesc('id')
                ->orderByDesc('updated_at')
                ->first();

            if (!$customer) {
                $customer = $entity->customers()
                    ->orderByRaw("CASE WHEN reason_retired IS NULL THEN 0 ELSE 1 END")
                    ->orderByRaw("CASE WHEN status = 'Active' THEN 0 ELSE 1 END")
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id')
                    ->first();
            }
        }

        if (!$customer && !$entity) {
            return response()->json([
                'success' => false,
                'message' => "No matching entity or customer records were found for {$normalized}.",
            ]);
        }

        $entityPayload = $entity ? $this->transformEntity($entity) : null;
        $customerPayload = $customer ? $this->transformCustomer($customer) : null;

        $detectedEntityType = $entityPayload['entity_type']
            ?? ($customerPayload['customer_type'] ?? $this->detectOwnerType($entityPayload['entity_name'] ?? $customerPayload['customer_name'] ?? ''));

        $detectedCustomerType = $customerPayload['customer_type']
            ?? $this->detectOwnerType($customerPayload['customer_name'] ?? $entityPayload['entity_name'] ?? '');

        $message = $this->buildLookupMessage($normalized, $entityPayload, $customerPayload);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'file_number' => $normalized,
                'entity' => $entityPayload,
                'customer' => $customerPayload,
                'detected_entity_type' => $detectedEntityType,
                'detected_customer_type' => $detectedCustomerType,
            ],
        ]);
    }

    /**
     * Build a REPLACE expression for collapsing file numbers in SQL Server.
     *
     * @param string $column
     * @return string
     */
    protected function buildFileNumberCollapseExpression(string $column = 'file_number'): string
    {
        return "REPLACE(REPLACE(REPLACE(REPLACE(UPPER({$column}), '-', ''), '/', ''), ' ', ''), '_', '')";
    }

    /**
     * Normalize file number input for comparisons.
     *
     * @param string|null $value
     * @return string
     */
    protected function normalizeFileNumberInput(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return strtoupper(trim($value));
    }

    /**
     * Collapse a file number to alphanumeric characters only.
     *
     * @param string $value
     * @return string
     */
    protected function collapseFileNumber(string $value): string
    {
        return preg_replace('/[^A-Z0-9]/', '', $value) ?? '';
    }

    /**
     * Detect probable owner type from name heuristics.
     *
     * @param string|null $reference
     * @return string
     */
    protected function detectOwnerType(?string $reference): string
    {
        $text = $reference ? trim($reference) : '';
        if ($text === '') {
            return 'Individual';
        }

        $upper = Str::upper($text);

        foreach (self::CORPORATE_KEYWORDS as $keyword) {
            if (Str::contains($upper, $keyword)) {
                return 'Corporate';
            }
        }

        foreach (self::MULTIPLE_KEYWORDS as $keyword) {
            if (Str::contains($upper, $keyword)) {
                return 'Multiple';
            }
        }

        return 'Individual';
    }

    /**
     * Transform entity for DataTables consumption.
     *
     * @param Entity $entity
     * @return array<string, string>
     */
    protected function transformEntityForDataTable(Entity $entity): array
    {
        return [
            'file_number' => $this->renderFileNumberCell($entity),
            'media' => $this->renderMediaCell($entity),
            'entity' => $this->renderEntityCell($entity),
            'type' => $this->renderTypeCell($entity),
            'linked_customers' => $this->renderLinkedCustomersCell($entity),
            'date_captured' => $this->renderDateCapturedCell($entity),
            'actions' => view('entities.partials.action-menu', ['entity' => $entity])->render(),
        ];
    }

    /**
     * Render the file number column HTML.
     *
     * @param Entity $entity
     * @return string
     */
    protected function renderFileNumberCell(Entity $entity): string
    {
        if ($entity->file_number) {
            $number = e($entity->file_number);
            return sprintf(
                '<span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-800"><i data-lucide="file-text" class="h-3 w-3"></i><span>%s</span></span>',
                $number
            );
        }

        return '<span class="text-xs text-gray-400">&mdash;</span>';
    }

    /**
     * Render the media column HTML.
     *
     * @param Entity $entity
     * @return string
     */
    protected function renderMediaCell(Entity $entity): string
    {
        $mediaSources = $this->buildMediaSources($entity);
        if (count($mediaSources)) {
            $items = array_map(function (array $media) {
                $url = e($media['url']);
                $alt = e($media['alt']);
                $label = e($media['label']);
                $shape = e($media['shape']);
                $objectClass = e($media['objectClass']);

                return <<<HTML
<div class="flex flex-col items-center gap-1">
    <img src="{$url}" alt="{$alt}" class="h-12 w-12 {$objectClass} ring-1 ring-gray-200 {$shape}" loading="lazy">
    <span class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">{$label}</span>
</div>
HTML;
            }, $mediaSources);

            return '<div class="flex items-center gap-3">' . implode('', $items) . '</div>';
        }

        $fallbackLabel = match ($entity->entity_type) {
            'Individual' => 'No Passport',
            'Corporate' => 'No Logo',
            default => 'No Logo or Passport',
        };

        return sprintf(
            '<div class="flex h-12 w-12 items-center justify-center rounded-md bg-gray-100 text-[10px] font-semibold uppercase tracking-wide text-gray-500">%s</div>',
            e($fallbackLabel)
        );
    }

    /**
     * Render the entity column HTML.
     *
     * @param Entity $entity
     * @return string
     */
    protected function renderEntityCell(Entity $entity): string
    {
        $icon = match ($entity->entity_type) {
            'Individual' => 'user',
            'Corporate' => 'building-2',
            default => 'users',
        };

        $name = e($entity->entity_name ?? 'Unnamed');

        return <<<HTML
<div class="flex items-center gap-3">
    <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-500">
        <i data-lucide="{$icon}" class="h-4 w-4"></i>
    </div>
    <div class="font-semibold text-gray-900">{$name}</div>
</div>
HTML;
    }

    /**
     * Render the type column HTML.
     *
     * @param Entity $entity
     * @return string
     */
    protected function renderTypeCell(Entity $entity): string
    {
        $type = $entity->entity_type ?? 'Unknown';
        $pType = $type === 'Multiple' ? 'Multiple Owners' : $type;
        $typeSafe = e($pType);

        return <<<HTML
<span class="inline-flex items-center gap-1 rounded-full bg-purple-100 px-2 py-1 text-xs font-semibold text-purple-800">
    {$typeSafe}
</span>
HTML;
    }

    /**
     * Render the linked customers column HTML.
     *
     * @param Entity $entity
     * @return string
     */
    protected function renderLinkedCustomersCell(Entity $entity): string
    {
        $count = (int) ($entity->customers_count ?? 0);

        return <<<HTML
<span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">
    <i data-lucide="link-2" class="h-3 w-3"></i>
    {$count} linked
</span>
HTML;
    }

    /**
     * Render the created date column HTML.
     *
     * @param Entity $entity
     * @return string
     */
    protected function renderDateCapturedCell(Entity $entity): string
    {
        $date = optional($entity->created_at)->format('d M Y');

        if ($date === null) {
            return '<span class="text-xs text-gray-400">&mdash;</span>';
        }

        return sprintf(
            '<span class="inline-flex items-center gap-1"><i data-lucide="calendar" class="h-4 w-4 text-gray-400"></i>%s</span>',
            e($date)
        );
    }

    /**
     * Build media metadata for a given entity.
     *
     * @param Entity $entity
     * @return array<int, array<string, string>>
     */
    protected function buildMediaSources(Entity $entity): array
    {
        $mediaSources = [];
        $showPassport = $entity->entity_type !== 'Corporate';

        if ($showPassport && $entity->passport_photo) {
            $url = $this->resolveMediaUrl($entity->passport_photo);
            if ($url) {
                $mediaSources[] = [
                    'url' => $url,
                    'alt' => trim(($entity->entity_name ?? 'Entity') . ' passport photo'),
                    'label' => 'Passport',
                    'shape' => 'rounded-full',
                    'objectClass' => 'object-cover',
                ];
            }
        }

        if ($entity->company_logo) {
            $url = $this->resolveMediaUrl($entity->company_logo);
            if ($url) {
                $mediaSources[] = [
                    'url' => $url,
                    'alt' => trim(($entity->entity_name ?? 'Entity') . ' company logo'),
                    'label' => 'Logo',
                    'shape' => 'rounded-md',
                    'objectClass' => 'object-contain',
                ];
            }
        }

        return $mediaSources;
    }

    /**
     * Resolve a stored media path to a full URL.
     *
     * @param string|null $path
     * @return string|null
     */
    protected function resolveMediaUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $trimmed = ltrim($path, '/');

        if (filter_var($trimmed, FILTER_VALIDATE_URL)) {
            return $trimmed;
        }

        return asset('storage/' . $trimmed);
    }

    /**
     * Transform customer model for DataTables display.
     *
     * @param Customer $customer
     * @return array<string, string>
     */
    protected function transformCustomerForDataTable(Customer $customer): array
    {
        return [
            'entity_id' => $this->renderCustomerEntityIdCell($customer),
            'file_number' => $this->renderCustomerFileNumberCell($customer),
            'account_no' => $this->renderCustomerAccountCell($customer),
            'customer_code' => $this->renderCustomerCodeCell($customer),
            'customer_name' => $this->renderCustomerNameCell($customer),
            'customer_type' => $this->renderCustomerTypeCell($customer),
            'retired_by' => $this->renderCustomerRetiredByCell($customer),
            'reason_retired' => $this->renderCustomerReasonCell($customer),
            'email' => $this->renderCustomerEmailCell($customer),
            'phone' => $this->renderCustomerPhoneCell($customer),
            'property_address' => $this->renderCustomerAddressCell($customer),
            'actions' => view('customers.partials.action-menu', ['customer' => $customer])->render(),
        ];
    }

    /**
     * Render entity ID badge.
     *
     * @param Customer $customer
     * @return string
     */
    protected function renderCustomerEntityIdCell(Customer $customer): string
    {
        if ($customer->entity_id) {
            $id = e($customer->entity_id);
            return <<<HTML
<span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700">
    {$id}
</span>
HTML;
        }

        return '<span class="text-xs text-gray-400">&mdash;</span>';
    }

    /**
     * Render file number badge.
     *
     * @param Customer $customer
     * @return string
     */
    protected function renderCustomerFileNumberCell(Customer $customer): string
    {
        if ($customer->file_number) {
            $file = e($customer->file_number);
            return <<<HTML
<span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-800">
    <i data-lucide="file-text" class="h-3 w-3"></i>
    {$file}
</span>
HTML;
        }

        return '<span class="text-xs text-gray-400">&mdash;</span>';
    }

    /**
     * Render account number badge.
     *
     * @param Customer $customer
     * @return string
     */
    protected function renderCustomerAccountCell(Customer $customer): string
    {
        if ($customer->account_no) {
            $account = e($customer->account_no);
            return <<<HTML
<span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800">
    <i data-lucide="hash" class="h-3 w-3"></i>
    {$account}
</span>
HTML;
        }

        return '<span class="text-xs text-gray-400">&mdash;</span>';
    }

    /**
     * Render customer code column.
     *
     * @param Customer $customer
     * @return string
     */
    protected function renderCustomerCodeCell(Customer $customer): string
    {
        return sprintf('<span class="font-mono text-sm text-gray-900">%s</span>', e($customer->customer_code ?? '—'));
    }

    /**
     * Render customer name with status.
     *
     * @param Customer $customer
     * @return string
     */
    protected function renderCustomerNameCell(Customer $customer): string
    {
        $name = e($customer->customer_name ?? 'Unnamed');

        return <<<HTML
<span class="font-semibold text-gray-900">{$name}</span>
HTML;
    }

    /**
     * Render customer type badge.
     *
     * @param Customer $customer
     * @return string
     */
    protected function renderCustomerTypeCell(Customer $customer): string
    {
        $type = $customer->customer_type ?? 'Unknown';

        $config = match ($type) {
            'Individual' => [
                'icon' => 'user',
                'color' => 'bg-blue-100 text-blue-700 border-blue-200'
            ],
            'Corporate' => [
                'icon' => 'building-2',
                'color' => 'bg-emerald-100 text-emerald-700 border-emerald-200'
            ],
            'Multiple' => [
                'icon' => 'users',
                'color' => 'bg-purple-100 text-purple-700 border-purple-200'
            ],
            default => [
                'icon' => 'help-circle',
                'color' => 'bg-gray-100 text-gray-600 border-gray-200'
            ],
        };

        $displayType = $type === 'Multiple' ? 'Multiple Owners' : $type;
        $typeSafe = e($displayType);
        $icon = e($config['icon']);
        $colorClass = e($config['color']);

        return <<<HTML
<span class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-xs font-medium {$colorClass}">
    <i data-lucide="{$icon}" class="h-3.5 w-3.5"></i>
    {$typeSafe}
</span>
HTML;
    }

    /**
     * Render retired-by text.
     *
     * @param Customer $customer
     * @return string
     */
    protected function renderCustomerRetiredByCell(Customer $customer): string
    {
        return sprintf('<span class="text-sm text-gray-700">%s</span>', e($customer->retired_by ?? '—'));
    }

    /**
     * Render reason retired text.
     *
     * @param Customer $customer
     * @return string
     */
    protected function renderCustomerReasonCell(Customer $customer): string
    {
        $reason = e($customer->reason_retired ?? '—');
        return <<<HTML
<span class="block max-w-xs whitespace-normal text-sm text-gray-600" title="{$reason}">
    {$reason}
</span>
HTML;
    }

    /**
     * Render email cell.
     *
     * @param Customer $customer
     * @return string
     */
    protected function renderCustomerEmailCell(Customer $customer): string
    {
        if ($customer->email) {
            $email = e($customer->email);
            return <<<HTML
<a href="mailto:{$email}" class="text-sm text-blue-600 hover:underline">
    {$email}
</a>
HTML;
        }

        return '<span class="text-sm text-gray-400">&mdash;</span>';
    }

    /**
     * Render phone cell.
     *
     * @param Customer $customer
     * @return string
     */
    protected function renderCustomerPhoneCell(Customer $customer): string
    {
        $primary = $customer->phone ?? $customer->mobile;
        if ($primary) {
            $phone = e($primary);
            $secondary = $customer->mobile && $customer->mobile !== $customer->phone
                ? '<span class="text-xs text-gray-500">' . e($customer->mobile) . '</span>'
                : '';

            return <<<HTML
<div class="flex flex-col">
    <span class="text-sm text-gray-700">{$phone}</span>
    {$secondary}
</div>
HTML;
        }

        return '<span class="text-sm text-gray-400">&mdash;</span>';
    }

    /**
     * Render address cell.
     *
     * @param Customer $customer
     * @return string
     */
    protected function renderCustomerAddressCell(Customer $customer): string
    {
        if ($customer->property_address) {
            $address = e($customer->property_address);
            return <<<HTML
<span class="block max-w-xs truncate text-sm text-gray-700" title="{$address}">
    {$address}
</span>
HTML;
        }

        return '<span class="text-sm text-gray-400">&mdash;</span>';
    }

    /**
     * Transform entity model into response payload.
     *
     * @param Entity $entity
     * @return array<string, mixed>
     */
    protected function transformEntity(Entity $entity): array
    {
        return [
            'id' => $entity->id,
            'entity_name' => $entity->entity_name,
            'entity_type' => $entity->entity_type,
            'file_number' => $entity->file_number,
            'property_address' => $entity->property_address ?? null,
            'physical_address' => $entity->physical_address ?? $entity->mailing_address ?? null,
            'mailing_address' => $entity->mailing_address ?? null,
            'customer_count' => $entity->customers_count ?? $entity->customers()->count(),
            'updated_at' => optional($entity->updated_at)->toDateTimeString(),
        ];
    }

    /**
     * Transform customer model into response payload.
     *
     * @param Customer $customer
     * @return array<string, mixed>
     */
    protected function transformCustomer(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'customer_name' => $customer->customer_name,
            'customer_type' => $customer->customer_type,
            'status' => $customer->status ?: 'Active',
            'email' => $customer->email,
            'phone' => $customer->phone,
            'mobile' => $customer->mobile,
            'account_no' => $customer->account_no,
            'customer_code' => $customer->customer_code,
            'notes' => $customer->notes,
            'reason_retired' => $customer->reason_retired,
            'retired_by' => $customer->retired_by,
            'file_number' => $customer->file_number,
            'entity_id' => $customer->entity_id,
            'property_address' => $customer->property_address,
            'physical_address' => $customer->physical_address ?? $customer->residential_address,
            'updated_at' => optional($customer->updated_at)->toDateTimeString(),
        ];
    }

    /**
     * Build a human-readable lookup message.
     *
     * @param string $fileNumber
     * @param array<string, mixed>|null $entity
     * @param array<string, mixed>|null $customer
     * @return string
     */
    protected function buildLookupMessage(string $fileNumber, ?array $entity, ?array $customer): string
    {
        if ($entity && $customer) {
            return "Matched entity and customer records for {$fileNumber}.";
        }

        if ($entity) {
            return "Matched entity record for {$fileNumber}. No customer record was linked.";
        }

        if ($customer) {
            return "Matched customer record for {$fileNumber}.";
        }

        return "No matching entity or customer records were found for {$fileNumber}.";
    }
}
