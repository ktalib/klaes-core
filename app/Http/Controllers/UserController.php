<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\LoggedHistory;
use App\Models\Notification;
use App\Models\PackageTransaction;
use App\Models\Subscription;
use App\Models\User;
use App\Support\Permissions\ModulePermissions;
use App\Support\Permissions\ModulePermissionSync;
use App\Rules\NigerianPhone;
use App\Models\UserRole;
use App\Models\UserType;
use App\Models\UserLevel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage; // added for profile image storage
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Services\ProfilePhotoService;
use App\Services\UserNotificationService;
use App\Services\Payroll\WorkstationPaymentStructureService;
use App\Support\Concerns\ResolvesWorkStations;

class UserController extends Controller
{
    use \App\Http\Controllers\Concerns\AuthorizesModules;

    /** The module this controller administers. */
    private const MODULE = 'User Account';

    use ResolvesWorkStations;
    public function __construct(
        protected UserNotificationService $notificationService,
        protected WorkstationPaymentStructureService $structureService
    ) {
    }

    /**
     * Staff-type buckets behind the MDC / MLPP tabs.
     *
     * staff_type_category is free text (values seen: MDC, MDCM, MLPP, NULL), so both
     * predicates normalise before comparing. A blank category counts as MLPP, which is
     * what the blade did when it filtered the collection in PHP.
     */
    private const TAB_PREDICATES = [
        'mdc'  => "UPPER(LTRIM(RTRIM(ISNULL([staff_type_category], '')))) IN ('MDC', 'MDCM')",
        'mlpp' => "UPPER(LTRIM(RTRIM(ISNULL([staff_type_category], '')))) IN ('MLPP', '')",
    ];

    /**
     * The whole-system grant, spelled exactly as the guards across the app expect.
     *
     * Dozens of call sites compare `assign_role` to this literal (`!== 'Supper Admin'`),
     * so it is stored ALONE — never merged into the Step 4 role list — and the misspelling
     * is deliberate: it is the value already in production.
     */
    public const SUPPER_ADMIN_ROLE = 'Supper Admin';

    /** Sort key => the columns it orders by, in order. */
    private const SORTABLE_COLUMNS = [
        'name'       => ['first_name', 'last_name'],
        'username'   => ['username'],
        'email'      => ['email'],
        'user_level' => ['user_level'],
        'staff_type' => ['staff_type_category'],
        'created_at' => ['created_at'],
    ];

    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    public function index(Request $request)
    {
        $this->authorizeModule(self::MODULE, 'view');

        $PageTitle = __('User');
        $PageDescription = __('User List');

        $parentId = parentId();
        $currentUser = Auth::user();
        // Some long-standing administrator accounts (including System Admin #2)
        // have type "Management" but carry the actual whole-system grant in
        // assign_role. Looking only at type made the User List show that account
        // only its parent's users. Honour either representation of the admin role.
        $assignedRoles = collect(explode(',', (string) ($currentUser->assign_role ?? '')))
            ->map(fn ($role) => trim($role))
            ->filter();
        $hasSystemUserAccess = $currentUser && (
            in_array(strtolower(trim((string) $currentUser->type)), ['super admin', 'owner'], true)
            || $assignedRoles->contains(fn ($role) => strcasecmp($role, self::SUPPER_ADMIN_ROLE) === 0)
        );
        $shouldFilterByParent = !empty($parentId) && $currentUser && !$hasSystemUserAccess;

        $scope = function () use ($shouldFilterByParent, $parentId) {
            return User::query()->when($shouldFilterByParent, function ($query) use ($parentId) {
                $query->where('parent_id', $parentId);
            });
        };

        $activeTab = $request->query('tab') === 'mlpp' ? 'mlpp' : 'mdc';

        $search = trim((string) $request->query('search', ''));

        $sort = $request->query('sort', 'name');
        if (!array_key_exists($sort, self::SORTABLE_COLUMNS)) {
            $sort = 'name';
        }
        $direction = strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $perPage = (int) $request->query('per_page', 25);
        if (!in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 25;
        }

        // One page of rows, not the whole table: each rendered row costs ~12KB of HTML,
        // so the old ->get() shipped ~18MB and left DataTables to paginate it in the browser.
        $users = $scope()
            ->select([
                'id',
                'first_name',
                'last_name',
                'username',
                'email',
                'phone_number',
                'department_id',
                'assign_role',
                'user_actions',
                'profile',
                'passport_photo_path',
                'user_level',
                'type',
                'work_days_per_week',
                'man_hours_per_day',
                'staff_type_category',
                'created_at',
                'parent_id',
                'is_active',
            ])
            ->with(['department:id,name'])
            ->whereRaw(self::TAB_PREDICATES[$activeTab])
            ->when($search !== '', function ($query) use ($search) {
                $like = '%' . $search . '%';
                $query->where(function ($match) use ($like) {
                    $match->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhereRaw("CONCAT([first_name], ' ', [last_name]) LIKE ?", [$like])
                        ->orWhere('username', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone_number', 'like', $like)
                        ->orWhere('assign_role', 'like', $like)
                        ->orWhere('user_level', 'like', $like);
                });
            })
            ->tap(function ($query) use ($sort, $direction) {
                foreach (self::SORTABLE_COLUMNS[$sort] as $column) {
                    $query->orderBy($column, $direction);
                }
                // Deterministic tie-break: without it SQL Server may hand back the same
                // row on two different pages.
                $query->orderBy('id', 'desc');
            })
            ->paginate($perPage)
            ->withQueryString();

        // Counted in SQL: filtering these off the loaded collection re-parsed
        // every created_at into a Carbon instance and cost ~8s on 500+ users.
        $userStats = (array) $scope()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN [type] COLLATE Latin1_General_BIN2 = 'admin' THEN 1 ELSE 0 END) as admins")
            ->selectRaw("SUM(CASE WHEN [type] COLLATE Latin1_General_BIN2 = 'user' THEN 1 ELSE 0 END) as regular")
            ->selectRaw('SUM(CASE WHEN [created_at] >= ? THEN 1 ELSE 0 END) as new_this_month', [
                now()->startOfMonth(),
            ])
            ->first()
            ->getAttributes();

        $tabCounts = (array) $scope()
            ->selectRaw('SUM(CASE WHEN ' . self::TAB_PREDICATES['mdc'] . ' THEN 1 ELSE 0 END) as mdc')
            ->selectRaw('SUM(CASE WHEN ' . self::TAB_PREDICATES['mlpp'] . ' THEN 1 ELSE 0 END) as mlpp')
            ->first()
            ->getAttributes();

        return view('user.index', compact(
            'users',
            'userStats',
            'tabCounts',
            'activeTab',
            'search',
            'sort',
            'direction',
            'perPage',
            'PageTitle',
            'PageDescription'
        ) + ['perPageOptions' => self::PER_PAGE_OPTIONS]);
    }



    public function create()
    {
        $this->authorizeModule(self::MODULE, 'create');

        try {
            // Get departments using the default connection
            $departments = Department::where('is_active', 1)->pluck('name', 'id');

            // Get user types using sqlsrv connection
            $userTypes = UserType::on('sqlsrv')
                ->active()
                ->orderByPriority()
                ->get(['id', 'name', 'code', 'description']);

            // Get user roles using sqlsrv connection
            $userRoles = UserRole::on('sqlsrv')
                ->where('is_active', 1)
                ->get(['id', 'name', 'department_id', 'level', 'user_type']);

            $workStations = $this->workStationOptions();

            $attendanceShifts = config('attendance.shifts', []);

            $staffTypeOptions = DB::connection('sqlsrv')
                ->table('payroll_staff_types')
                ->orderBy('code')
                ->get(['code', 'name'])
                ->mapWithKeys(fn ($type) => [$type->code => sprintf('%s - %s', $type->code, $type->name)])
                ->toArray();

            $paymentStructures = $this->structureService->dropdownOptions();

            // Officer ranks (seniority) for file-request prioritisation.
            $ranks = \App\Models\OfficerRank::options();

            $deputyOptions = $this->deputyOptions();

            // Only a Super Admin may hand out delete; see ModulePermissionSync::canGrantDelete().
            $canGrantDelete = ModulePermissionSync::canGrantDelete(Auth::user());

            return view('user.create', compact(
                'departments',
                'userTypes',
                'userRoles',
                'workStations',
                'attendanceShifts',
                'staffTypeOptions',
                'paymentStructures',
                'ranks',
                'deputyOptions',
                'canGrantDelete'
            ));
        } catch (\Exception $e) {
            \Log::error('Error in UserController@create', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->back()->with('error', 'Error loading user creation form: ' . $e->getMessage());
        }
    }

    /**
     * Get user levels for a specific user type
     */
    public function getUserLevels($userTypeId)
    {
        try {
            $userLevels = UserLevel::on('sqlsrv')
                                  ->forUserType($userTypeId)
                                  ->active()
                                  ->get(['id', 'name', 'code', 'description']);
            
            \Log::info('User levels fetched', [
                'user_type_id' => $userTypeId,
                'levels_count' => $userLevels->count(),
                'levels' => $userLevels->toArray()
            ]);
            
            return response()->json($userLevels);
        } catch (\Exception $e) {
            \Log::error('Error fetching user levels', [
                'user_type_id' => $userTypeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Failed to load user levels: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Store an uploaded passport photo against the user, replacing any previous file.
     * Returns true when a new photo was saved; false when the request carried none
     * (in which case the existing photo is left untouched).
     */
    private function applyProfileUpload(Request $request, User $user): bool
    {
        if (!$request->hasFile('profile')) {
            return false;
        }

        app(ProfilePhotoService::class)->store($request->file('profile'), $user);

        return true;
    }

    /**
     * Honour the edit form's "Remove Profile Picture" control.
     * Returns true when a photo was cleared. Ignored when the same submit also carries
     * a new upload — replacing a photo is not removing it.
     */
    private function applyProfileRemoval(Request $request, User $user): bool
    {
        if (!$request->boolean('remove_profile') || $request->hasFile('profile')) {
            return false;
        }

        return app(ProfilePhotoService::class)->remove($user);
    }

    private function profileUploadRules(bool $required = false): array
    {
        return ProfilePhotoService::rules($required);
    }

    /**
     * Minimal public-facing profile used by the clickable "Created by / Indexed by"
     * cells: full name, username, phone and photo, nothing else.
     *
     * Accepts either an id or a display name, because the two tables that use it store
     * the creator differently — land recommendations/RofOs keep a user id, while
     * file_indexings.created_by holds the indexer's NAME.
     */
    public function profileCard(Request $request)
    {
        $id = trim((string) $request->query('id', ''));
        $name = trim((string) $request->query('name', ''));

        $user = null;

        $columns = ['id', 'first_name', 'last_name', 'username', 'phone_number', 'profile', 'passport_photo_path'];

        // Only ask for the face-check columns once they exist: code reaches production
        // ahead of the SQL script, and naming a missing column fails the whole query.
        if (ProfilePhotoService::faceColumnsExist()) {
            $columns[] = 'photo_face_status';
            $columns[] = 'photo_face_reason';
            $columns[] = 'photo_face_path';
        }

        if ($id !== '' && ctype_digit($id)) {
            $user = User::find((int) $id, $columns);
        }

        // file_indexings.created_by holds an id on most rows and a typed name on the rest,
        // so a value arriving as `name` may still be an id.
        if (!$user && $name !== '' && ctype_digit($name)) {
            $user = User::find((int) $name, $columns);
        }

        if (!$user && $name !== '') {
            $user = User::query()
                ->where(function ($query) use ($name) {
                    $query->whereRaw("LTRIM(RTRIM(COALESCE(first_name, '') + ' ' + COALESCE(last_name, ''))) = ?", [$name])
                        ->orWhere('username', $name);
                })
                ->first($columns);
        }

        if (!$user) {
            return response()->json([
                'found' => false,
                'message' => __('No matching user record was found.'),
                'full_name' => $name !== '' ? $name : null,
            ], 404);
        }

        return response()->json([
            'found' => true,
            'id' => $user->id,
            'full_name' => trim($user->first_name . ' ' . $user->last_name) ?: $user->username,
            'username' => $user->username,
            'phone_number' => $user->phone_number,
            'profile_url' => $user->profile_url,
            // Drives the verified tick / missing-photo mark on the card.
            'has_photo' => $user->has_profile_photo,
            // The recorded face-check verdict, so the card states what the system
            // actually believes about this picture rather than re-deciding it on every
            // open: 'pass' | 'fail' | 'override', or null when nobody has checked it.
            'face_status' => $user->photo_face_verdict,
            'face_reason' => $user->photo_face_reason,
            // A Super Admin opening an unchecked picture is a chance to judge it now,
            // rather than waiting for that person to sign in. Nobody else may report a
            // verdict on somebody else's account — it locks them out of the system.
            'can_report_face' => $user->photo_face_verdict === null
                && $user->has_profile_photo
                && optional($request->user())->isSuperAdmin() === true,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeModule(self::MODULE, 'create');

        // create.blade.php posts `phone`; edit.blade.php posts `phone_number`.
        // Settle on one key so validation and the assignment below agree, and
        // fold +234/spacing into the canonical 0XXXXXXXXXX the gateways expect.
        $request->merge([
            'phone_number' => NigerianPhone::normalize(
                $request->input('phone_number', $request->input('phone'))
            ),
        ]);

        /*
         | Drop the permission grid's blank user_role[] sentinel before anything validates.
         |
         | The grid posts one empty entry so that "every module revoked" is distinguishable
         | from "the field was not submitted" -- but left in place it also satisfies
         | required_unless, which would let a user be saved with no module at all while the
         | rule still claims to require one.
         */
        $request->merge(['user_role' => $this->postedModules($request)]);

        if (\Auth::user()->type == 'super admin') {
                $validator = \Validator::make(
                    $request->all(),
                    [
                        'name' => 'required',
                        'username' => 'required|unique:users', // add username validation
                        'password' => 'required|min:6',
                        'work_station' => $this->workStationValidationRule(false),
                        'phone_number' => ['required', 'string', new NigerianPhone()],
                        'profile' => $this->profileUploadRules(),
                    ]
                );
                if ($validator->fails()) {
                    $messages = $validator->getMessageBag();

                    return redirect()->back()->with('error', $messages->first());
                }

                $user = new User();
                $user->name = $request->name;
                $user->username = $request->username; // save username
                $user->email = $request->filled('email') ? $request->email : uniqid('temp_') . '@klaes.com.ng';
                $user->assign_role = isset($request->user_role) ? implode(',', $request->user_role) : null;
                $user->password = \Hash::make($request->password);
                // allow either phone or phone_number
                $user->phone_number = $request->input('phone_number', $request->input('phone'));
                $user->department_id = $request->department_id; // Save department_id
                // Seniority for file-request priority; "Other (specify)" adds to the lookup table.
                $user->rank = \App\Models\OfficerRank::resolveSubmittedRank($request->input('rank'), $request->input('rank_other'));
                $user->work_station = $request->work_station;
                $user->user_level = $request->user_level; // Save user_level
                $user->type = 'owner';
                $user->lang = 'english';
                $user->subscription = 1;
                $user->parent_id = parentId();
                $user->email_verified_at = now();

                $defaultShiftCode = $this->defaultShiftCode();
                $requestedShiftCode = $request->shift_code ?? $defaultShiftCode;
                $resolvedShiftCode = $this->resolveShiftCode($requestedShiftCode);
                $user->shift_code = $resolvedShiftCode ?? $defaultShiftCode;
                $user->auto_deactivate = true;
                
                // Handle optional profile image upload
                if (!$this->applyProfileUpload($request, $user)) {
                    $user->profile = 'avatar.png';
                }

                $user->save();
                defaultTemplate($user->id);

                $module = 'user_create';
                $notification = Notification::where('parent_id', parentId())->where('module', $module)->first();
                $setting = settings();
                $errorMessage = '';
                if (!empty($notification) && $notification->enabled_email == 1) {
                    $notification_responce = MessageReplace($notification, $user->id);
                    $data['subject'] = $notification_responce['subject'];
                    $data['message'] = $notification_responce['message'];
                    $data['module'] = $module;
                    $data['password'] = $request->password;
                    $data['logo'] = $setting['company_logo'];
                    $to = $user->email;

                    $response = commonEmailSend($to, $data);
                    if ($response['status'] == 'error') {
                        $errorMessage = '<br><span class="text-danger">' . $response['message'] . '</span>';
                    }
                }
                return redirect()->route('users.index')->with('success', __('User successfully created.') . $errorMessage);
            } else {

                $shiftOptions = array_keys(config('attendance.shifts', []));
                $shiftRule = ['nullable', 'string'];
                if (!empty($shiftOptions)) {
                    $shiftRule[] = Rule::in($shiftOptions);
                }

                $validator = \Validator::make(
                    $request->all(),
                    [
                        'first_name' => 'required',
                        'last_name' => 'required',
                        'phone_number' => ['required', 'string', new NigerianPhone()],
                        'password' => 'required|min:6',
                        // A Supper Admin is not scoped to a department and carries no role
                        // grid, so Step 1 and Step 4 are optional for that user type only.
                        'department_id' => 'required_unless:user_type,' . self::SUPPER_ADMIN_ROLE,
                        'user_type' => 'required|string|in:Management,Operations,ALL,User,System,' . self::SUPPER_ADMIN_ROLE,
                        'user_level' => 'required|string|in:Administrative,Technical,Finance,Lowest,High,Highest',
                        'user_role' => ['required_unless:user_type,' . self::SUPPER_ADMIN_ROLE, 'array'],
                        'rank' => 'nullable|string|max:255',
                        'rank_other' => 'nullable|string|max:255|required_if:rank,' . \App\Models\OfficerRank::OTHER_VALUE,
                        'work_station' => $this->workStationValidationRule(false),
                        'work_days_per_week' => 'nullable|integer|in:2,5,7',
                        'man_hours_per_day' => 'nullable|integer|in:4,8',
                        'staff_type_category' => 'required|string|in:MDC,MLPP,MDCM',
                        'shift_code' => $shiftRule,
                        'auto_deactivate' => 'nullable|boolean',
                        'payment_structure_id' => 'nullable|integer|exists:sqlsrv.workstation_payment_structures,id',
                        'base_salary_override' => 'nullable|numeric|min:0',
                        'is_on_leave' => 'nullable|boolean',
                        'leave_start_date' => 'nullable|date',
                        'leave_end_date' => 'nullable|date|after_or_equal:leave_start_date',
                        'leave_reason' => 'nullable|string|max:255',
                        'deputy_user_id' => 'nullable|integer|exists:sqlsrv.users,id',
                        'out_of_office_from' => 'nullable|date',
                        'out_of_office_to' => 'nullable|date|after_or_equal:out_of_office_from',
                        'profile' => $this->profileUploadRules(),
                    ]
                );
                if ($validator->fails()) {
                    $messages = $validator->getMessageBag();

                    return redirect()->back()->with('error', $messages->first());
                }

                $pricing_feature_settings = getSettingsValByIdName(1, 'pricing_feature');
                if ($pricing_feature_settings == 'on') {
                    $ids = parentId();
                    $authUser = \App\Models\User::find($ids);
                    if ($authUser) {
                        $totalUser = $authUser->totalUser();
                        $subscription = Subscription::find($authUser->subscription);
                        if ($totalUser >= $subscription->user_limit && $subscription->user_limit != 0) {
                            return redirect()->back()->with('error', __('Your user limit is over, please upgrade your subscription.'));
                        }
                    }
                }
                
                $isSupperAdmin = trim((string) $request->user_type) === self::SUPPER_ADMIN_ROLE;

                $user = new User();
                $user->first_name = $request->first_name;
                $user->last_name = $request->last_name;
                $user->username = $request->username; // save username
                $user->email = $request->filled('email') ? $request->email : uniqid('temp') . '@klaes.com.ng';
                // allow either phone or phone_number
                $user->phone_number = $request->input('phone_number', $request->input('phone'));
                $user->password = \Hash::make($request->password);
                // Blank rather than absent when Step 1 was skipped for a Supper Admin.
                $user->department_id = $request->filled('department_id') ? $request->department_id : null;
                $user->user_level = $request->user_level;
                $user->work_station = $request->input('work_station');
                $user->type = $request->user_type; // Use the selected user type
                $user->user_type = $request->user_type; // keep canonical column in sync for edit prefill
                $user->work_days_per_week = $request->filled('work_days_per_week')
                    ? (int) $request->work_days_per_week
                    : null;
                $user->man_hours_per_day = $request->filled('man_hours_per_day')
                    ? (int) $request->man_hours_per_day
                    : null;
                $user->staff_type_category = $request->staff_type_category;
                $defaultShiftCode = $this->defaultShiftCode();
                $requestedShiftCode = $request->input('shift_code', $defaultShiftCode);
                $resolvedShiftCode = $this->resolveShiftCode($requestedShiftCode);
                $user->shift_code = $resolvedShiftCode ?? $defaultShiftCode;
                $normalizedStaffType = strtoupper(trim($request->staff_type_category ?? ''));
                $shouldForceAccessEnabled = in_array($normalizedStaffType, ['MLPP', 'MDCM'], true);
                $user->auto_deactivate = $shouldForceAccessEnabled
                    ? true
                    : $request->boolean('auto_deactivate', true);
                $user->is_pc_access = $shouldForceAccessEnabled
                    ? true
                    : $request->boolean('is_pc_access');
                $structureId = $request->filled('payment_structure_id')
                    ? (int) $request->payment_structure_id
                    : null;
                $overrideValue = $request->filled('base_salary_override')
                    ? (float) $request->base_salary_override
                    : null;

                $user->workstation_payment_structure_id = $structureId;
                $user->base_salary_override = $overrideValue;
                $user->base_salary_source = $overrideValue !== null
                    ? 'override'
                    : ($structureId ? 'structure' : null);
                $user->email_verified_at = now();
                
                // Handle required profile image upload
                if (!$this->applyProfileUpload($request, $user)) {
                    // Fallback, though validation requires it
                    $user->profile = 'avatar.png';
                    $user->passport_photo_path = null;
                }
                
                $user->lang = 'english';
                $user->parent_id = parentId();
                // Seniority for file-request priority; "Other (specify)" adds to the lookup table.
                $user->rank = \App\Models\OfficerRank::resolveSubmittedRank($request->input('rank'), $request->input('rank_other'));
                $user->is_on_leave = $request->boolean('is_on_leave');
                $user->leave_start_date = $request->filled('leave_start_date') ? $request->leave_start_date : null;
                $user->leave_end_date = $request->filled('leave_end_date') ? $request->leave_end_date : null;
                $user->leave_reason = $request->filled('leave_reason') ? $request->leave_reason : null;
                $user->deputy_user_id = $request->filled('deputy_user_id') ? (int) $request->deputy_user_id : null;
                $user->out_of_office_from = $request->filled('out_of_office_from') ? $request->out_of_office_from : null;
                $user->out_of_office_to = $request->filled('out_of_office_to') ? $request->out_of_office_to : null;
                // Supper Admin is a whole-system grant, never one entry among others: the
                // guards compare assign_role to the bare string, so anything ticked in
                // Step 4 is discarded rather than appended.
                $grantedModules = $this->postedModules($request);

                $user->assign_role = $isSupperAdmin
                    ? self::SUPPER_ADMIN_ROLE
                    : ($grantedModules ? implode(',', $grantedModules) : null);
                /*
                 | user_actions is deliberately no longer written. It held four system-wide
                 | flags (create/view/update/delete) that nothing in the app ever read, and
                 | which the module x action grid now answers per module. The column is left
                 | in place rather than dropped -- this is a live registry with no backups,
                 | and an unused column costs nothing next to an irreversible schema change.
                 */
                $user->dfr_permissions = isset($request->dfr_permissions) ? implode(',', $request->dfr_permissions) : null;
                $user->fr_permissions = $request->input('fr_permissions') === 'SCB' ? 'SCB' : null;
                $user->save();

                $this->syncModulePermissions($request, $user, $isSupperAdmin, $grantedModules);

                $module = 'user_create';
                $notification = Notification::where('parent_id', parentId())->where('module', $module)->first();
                if (!empty($notification)) {
                    $notification->password=$request->password;
                }
                $setting = settings();
                $errorMessage = '';
                if (!empty($notification) && $notification->enabled_email == 1) {
                    $notification_responce = MessageReplace($notification, $user->id);
                    $data['subject'] = $notification_responce['subject'];
                    $data['message'] = $notification_responce['message'];
                    $data['module'] = $module;
                    $data['password'] = $request->password;
                    $data['logo'] = $setting['company_logo'];
                    $to = $user->email;

                    $response = commonEmailSend($to, $data);
                    if ($response['status'] == 'error') {
                        $errorMessage=$response['message'];
                    }
                }

                return redirect()->route('users.index')->with('success', __('User successfully created.'));
            }
    }


    public function show(User $user)
    {
        $settings = settings();
        $transactions = PackageTransaction::where('user_id', $user->id)->orderBy('created_at', 'DESC')->get();
        // Remove subscriptions variable
        return view('user.show', compact('user', 'transactions', 'settings'));
    }


    public function edit($id)
    {
        $this->authorizeModule(self::MODULE, 'edit');

        $user = User::findOrFail($id);
        $departments = Department::where('is_active', 1)->pluck('name', 'id');
        
        // Get user roles with user_type and level columns (same as create method)
        $userRoles = UserRole::on('sqlsrv')
                           ->where('is_active', 1)
                           ->get(['id', 'name', 'department_id', 'level', 'user_type']);
        
        // Get user's assigned roles as an array
        $userAssignedRoles = !empty($user->assign_role) ? explode(',', $user->assign_role) : [];
        
        $workStations = $this->workStationOptions();

        $staffTypeOptions = DB::connection('sqlsrv')
            ->table('payroll_staff_types')
            ->orderBy('code')
            ->get(['code', 'name'])
            ->mapWithKeys(fn ($type) => [$type->code => sprintf('%s - %s', $type->code, $type->name)])
            ->toArray();

        $paymentStructures = $this->structureService->dropdownOptions();
        $attendanceShifts = config('attendance.shifts', []);

        // Officer ranks (seniority) for file-request prioritisation.
        $ranks = config('file_request_priority.options', []);

        $deputyOptions = $this->deputyOptions($user->id);

        // Stored action grants, so the grid opens showing what this user actually holds
        // rather than re-deriving it from assign_role.
        $moduleGrants = ModulePermissionSync::gridFor($user);
        $canGrantDelete = ModulePermissionSync::canGrantDelete(Auth::user());

        return view('user.edit', compact(
            'user',
            'departments',
            'userRoles',
            'userAssignedRoles',
            'workStations',
            'attendanceShifts',
            'staffTypeOptions',
            'paymentStructures',
            'ranks',
            'deputyOptions',
            'moduleGrants',
            'canGrantDelete'
        ));
    }


    public function update(Request $request, $id)
    {
        $this->authorizeModule(self::MODULE, 'edit');

        // create.blade.php posts `phone`; edit.blade.php posts `phone_number`.
        // Settle on one key so validation and the assignment below agree, and
        // fold +234/spacing into the canonical 0XXXXXXXXXX the gateways expect.
        $request->merge([
            'phone_number' => NigerianPhone::normalize(
                $request->input('phone_number', $request->input('phone'))
            ),
        ]);

        /*
         | Drop the permission grid's blank user_role[] sentinel before anything validates.
         |
         | The grid posts one empty entry so that "every module revoked" is distinguishable
         | from "the field was not submitted" -- but left in place it also satisfies
         | required_unless, which would let a user be saved with no module at all while the
         | rule still claims to require one.
         */
        $request->merge(['user_role' => $this->postedModules($request)]);

        if (\Auth::user()->type == 'super admin') {
                $user = User::findOrFail($id);

                $validator = \Validator::make(
                    $request->all(),
                    [
                        'name' => 'required',
                        'username' => 'required|unique:users,username,' . $id, // add username validation
                        'phone_number' => ['required', 'string', new NigerianPhone()],
                        'work_station' => $this->workStationValidationRule(false),
                        'password' => 'nullable|min:6',
                        'profile' => $this->profileUploadRules(),
                    ]
                );
                if ($validator->fails()) {
                    $messages = $validator->getMessageBag();

                    return redirect()->back()->with('error', $messages->first());
                }

                $userData = $request->all();
                // The uploaded file must never reach the fillable "profile" column directly.
                unset($userData['profile']);
                $user->fill($userData);
                $this->applyProfileUpload($request, $user);
                $this->applyProfileRemoval($request, $user);
                $user->username = $request->username; // update username
                $user->email = $request->filled('email') ? $request->email : uniqid('temp') . '@klaes.com.ng';
                if ($request->filled('password')) {
                    $user->password = Hash::make($request->password);
                }
                $user->save();
                $this->notifyAccountUpdate($user);

                return redirect()->route('users.index')->with('success', 'User successfully updated.');
            } else {
                $shiftOptions = array_keys(config('attendance.shifts', []));
                $shiftRule = ['nullable', 'string'];
                if (!empty($shiftOptions)) {
                    $shiftRule[] = Rule::in($shiftOptions);
                }

                $validator = \Validator::make(
                    $request->all(),
                    [
                        'first_name' => 'required',
                        'last_name' => 'required',
                        'phone_number' => ['required', 'string', new NigerianPhone()],
                        // A Supper Admin is not scoped to a department and carries no role
                        // grid, so Step 1 and Step 4 are optional for that user type only.
                        'department_id' => 'required_unless:user_type,' . self::SUPPER_ADMIN_ROLE,
                        'user_type' => 'required|string|in:Management,Operations,ALL,User,System,' . self::SUPPER_ADMIN_ROLE,
                        'user_level' => 'required|string|in:Administrative,Technical,Finance,Lowest,Highest,High',
                        'user_role' => ['required_unless:user_type,' . self::SUPPER_ADMIN_ROLE, 'array'],
                        'rank' => 'nullable|string|max:255',
                        'rank_other' => 'nullable|string|max:255|required_if:rank,' . \App\Models\OfficerRank::OTHER_VALUE,
                        'work_station' => $this->workStationValidationRule(false),
                        'work_days_per_week' => 'nullable|integer|in:2,5,7',
                        'man_hours_per_day' => 'nullable|integer|in:4,8',
                        'staff_type_category' => 'required|string|in:MDC,MLPP,MDCM',
                        'shift_code' => $shiftRule,
                        'auto_deactivate' => 'nullable|boolean',
                        'payment_structure_id' => 'nullable|integer|exists:sqlsrv.workstation_payment_structures,id',
                        'base_salary_override' => 'nullable|numeric|min:0',
                        'is_on_leave' => 'nullable|boolean',
                        'leave_start_date' => 'nullable|date',
                        'leave_end_date' => 'nullable|date|after_or_equal:leave_start_date',
                        'leave_reason' => 'nullable|string|max:255',
                        'deputy_user_id' => ['nullable', 'integer', 'exists:sqlsrv.users,id', Rule::notIn([$id])],
                        'out_of_office_from' => 'nullable|date',
                        'out_of_office_to' => 'nullable|date|after_or_equal:out_of_office_from',
                        'password' => 'nullable|min:6',
                        'profile' => $this->profileUploadRules(),
                    ]
                );
                if ($validator->fails()) {
                    $messages = $validator->getMessageBag();

                    return redirect()->back()->with('error', $messages->first());
                }

                // A Supper Admin skips Step 1 and Step 4, so user_role can legitimately be
                // absent here — nothing below may assume it holds at least one entry.
                $isSupperAdmin = trim((string) $request->user_type) === self::SUPPER_ADMIN_ROLE;

                $user = User::findOrFail($id);
                $user->first_name = $request->first_name;
                $user->last_name = $request->last_name;
                $user->username = $request->username; // update username
                $user->email = $request->filled('email') ? $request->email : uniqid('temp') . '@klaes.com.ng';
                $user->phone_number = $request->phone_number;
                // Blank rather than absent when Step 1 was skipped for a Supper Admin.
                $user->department_id = $request->filled('department_id') ? $request->department_id : null;
                // Seniority for file-request priority; "Other (specify)" adds to the lookup table.
                $user->rank = \App\Models\OfficerRank::resolveSubmittedRank($request->input('rank'), $request->input('rank_other'));
                $user->user_level = $request->user_level;
                if ($request->has('work_station')) {
                    $user->work_station = $request->input('work_station');
                }
                if ($request->has('work_days_per_week')) {
                    $user->work_days_per_week = $request->filled('work_days_per_week')
                        ? (int) $request->work_days_per_week
                        : null;
                }
                if ($request->has('man_hours_per_day')) {
                    $user->man_hours_per_day = $request->filled('man_hours_per_day')
                        ? (int) $request->man_hours_per_day
                        : null;
                }
                $user->staff_type_category = $request->staff_type_category;
                if ($request->has('is_on_leave')) {
                    $user->is_on_leave = $request->boolean('is_on_leave');
                }
                if ($request->has('leave_start_date')) {
                    $user->leave_start_date = $request->filled('leave_start_date') ? $request->leave_start_date : null;
                }
                if ($request->has('leave_end_date')) {
                    $user->leave_end_date = $request->filled('leave_end_date') ? $request->leave_end_date : null;
                }
                if ($request->has('leave_reason')) {
                    $user->leave_reason = $request->filled('leave_reason') ? $request->leave_reason : null;
                }
                if ($request->has('deputy_user_id')) {
                    $user->deputy_user_id = $request->filled('deputy_user_id') ? (int) $request->deputy_user_id : null;
                }
                if ($request->has('out_of_office_from')) {
                    $user->out_of_office_from = $request->filled('out_of_office_from') ? $request->out_of_office_from : null;
                }
                if ($request->has('out_of_office_to')) {
                    $user->out_of_office_to = $request->filled('out_of_office_to') ? $request->out_of_office_to : null;
                }
                $normalizedStaffType = strtoupper(trim($request->staff_type_category ?? ''));
                $shouldForceAccessEnabled = in_array($normalizedStaffType, ['MLPP', 'MDCM'], true);
                if ($request->has('shift_code')) {
                    $user->shift_code = $this->resolveShiftCode($request->input('shift_code'));
                }
                if ($shouldForceAccessEnabled) {
                    $user->auto_deactivate = true;
                    $user->is_pc_access = true;
                } else {
                    if ($request->has('auto_deactivate')) {
                        $user->auto_deactivate = $request->boolean('auto_deactivate', (bool) ($user->auto_deactivate ?? true));
                    }
                    if ($request->has('is_pc_access')) {
                        $user->is_pc_access = $request->boolean('is_pc_access', (bool) ($user->is_pc_access ?? false));
                    }
                }
                $structureId = $request->filled('payment_structure_id')
                    ? (int) $request->payment_structure_id
                    : null;
                $overrideValue = $request->filled('base_salary_override')
                    ? (float) $request->base_salary_override
                    : null;

                $user->workstation_payment_structure_id = $structureId;
                $user->base_salary_override = $overrideValue;
                $user->base_salary_source = $overrideValue !== null
                    ? 'override'
                    : ($structureId ? 'structure' : null);
                $user->type = $request->user_type;
                $user->user_type = $request->user_type; // keep canonical column in sync for edit prefill
                // Supper Admin is a whole-system grant, never one entry among others: the
                // guards compare assign_role to the bare string, so anything ticked in
                // Step 4 is discarded rather than appended.
                $grantedModules = $this->postedModules($request);

                $user->assign_role = $isSupperAdmin
                    ? self::SUPPER_ADMIN_ROLE
                    : ($grantedModules ? implode(',', $grantedModules) : null);
                // See store(): user_actions is dead and is no longer written. Leaving the
                // stored value alone means editing a user does not quietly erase it either.
                $user->dfr_permissions = isset($request->dfr_permissions) ? implode(',', $request->dfr_permissions) : null;
                $user->fr_permissions = $request->input('fr_permissions') === 'SCB' ? 'SCB' : null;
                if ($request->filled('password')) {
                    $user->password = Hash::make($request->password);
                }
                $this->applyProfileUpload($request, $user);
                $this->applyProfileRemoval($request, $user);
                $user->save();

                $this->syncModulePermissions($request, $user, $isSupperAdmin, $grantedModules);

                $this->notifyAccountUpdate($user);

                return redirect()->route('users.index')->with('success', 'User successfully updated.');
            }
    }


    /**
     * The module names the permission grid granted, cleaned up.
     *
     * The grid always posts one blank user_role[] entry so that clearing every module is
     * distinguishable from not submitting the field at all -- without it the browser omits the
     * key entirely and "revoke everything" would read as "leave unchanged". That blank, and any
     * stray whitespace, is stripped here before the CSV is built, so assign_role never gains a
     * leading or doubled comma.
     *
     * @return array<int, string>
     */
    private function postedModules(Request $request): array
    {
        return collect($request->input('user_role', []))
            ->map(fn ($name) => trim((string) $name))
            ->filter(fn ($name) => $name !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Persist the module x action grid alongside assign_role.
     *
     * A Supper Admin is skipped: ModulePermissions short-circuits on that account, so rows for
     * them would be dead weight that a later reader could only be misled by.
     *
     * @param  array<int, string>  $grantedModules
     */
    private function syncModulePermissions(Request $request, User $user, bool $isSupperAdmin, array $grantedModules): void
    {
        if ($isSupperAdmin) {
            DB::connection('sqlsrv')->table('module_permissions')->where('user_id', $user->id)->delete();
            ModulePermissions::forget($user->id);

            return;
        }

        ModulePermissionSync::sync(
            $user,
            (array) $request->input('module_perm', []),
            $grantedModules,
            ModulePermissionSync::canGrantDelete(Auth::user())
        );
    }

    private function authorizeProfilePhoto(User $user): void
    {
        $actor = Auth::user();
        abort_unless($actor && ($actor->isSuperAdmin() || $actor->can('edit user')), 403);
        $parentId = parentId();
        if (!$actor->isSuperAdmin() && $actor->type !== 'owner' && !empty($parentId)) {
            abort_unless((string) $user->parent_id === (string) $parentId, 403);
        }
    }

    public function editProfilePhoto(User $user)
    {
        $this->authorizeProfilePhoto($user);

        return view('user.profile-photo', compact('user'));
    }

    public function updateProfilePhoto(Request $request, User $user)
    {
        $this->authorizeProfilePhoto($user);
        $request->validate([
            'action' => ['required', Rule::in(['update', 'remove'])],
            'profile' => $this->profileUploadRules($request->input('action') === 'update'),
        ]);

        $oldValues = $user->only(['profile', 'passport_photo_path']);
        $photos = app(ProfilePhotoService::class);
        if ($request->input('action') === 'remove') {
            $photos->remove($user);
            $message = __('Profile picture removed. This user will be asked to upload a new photo before using the system.');
        } else {
            $photos->store($request->file('profile'), $user);
            $message = __('Profile picture updated successfully.');
        }
        $user->save();
        app(\App\Services\AuditService::class)->logAction(
            'UPDATE', 'users', $user->id, $oldValues,
            $user->only(['profile', 'passport_photo_path']), $message
        );

        return response()->json(['success' => true, 'message' => $message, 'photoUrl' => $user->profile_url]);
    }

    /**
     * Clear a user's passport photo from the row action menu.
     *
     * Separate from update() so the list screen can do it without opening the edit form.
     */
    public function removeProfilePhoto(User $user)
    {
        if (!app(ProfilePhotoService::class)->remove($user)) {
            return redirect()->back()->with('success', __('That user has no profile picture to remove.'));
        }

        $user->save();

        return redirect()->back()->with(
            'success',
            __('Profile picture removed for :name. They will be asked to upload a new one at their next sign-in.', [
                'name' => $user->name,
            ])
        );
    }

    public function suspend(User $user)
    {
        // Suspending withdraws access but keeps the account, so it is an edit, not a delete.
        $this->authorizeModule(self::MODULE, 'edit');

        if ((int) $user->id === (int) Auth::id()) {
            return redirect()->route('users.index')->with('error', __('You cannot suspend your own account.'));
        }

        if ((int) $user->is_active === 0) {
            return redirect()->route('users.index')->with('success', __('User already suspended.'));
        }

        $user->is_active = 0;
        $user->save();

        return redirect()->route('users.index')->with('success', __('User successfully suspended.'));
    }

    public function unsuspend(User $user)
    {
        $this->authorizeModule(self::MODULE, 'edit');

        if ((int) $user->is_active === 1) {
            return redirect()->route('users.index')->with('success', __('User already active.'));
        }

        $user->is_active = 1;
        $user->save();

        return redirect()->route('users.index')->with('success', __('User successfully reactivated.'));
    }

    public function destroy($id)
    {
        /*
         | This had no authorization whatsoever: any signed-in account could delete any user
         | by posting to the route, because nothing in the app checked and the only barrier
         | was the sidebar not drawing the button.
         |
         | It also called delete() on the result of find(), which is null for an id that is
         | gone -- a fatal error rather than a message, and a second click on a stale page
         | was enough to trigger it.
         */
        $this->authorizeModule(self::MODULE, 'delete');

        $user = User::find($id);

        if (!$user) {
            return redirect()->route('users.index')->with('error', __('That user no longer exists.'));
        }

        if ((int) $user->id === (int) Auth::id()) {
            return redirect()->route('users.index')->with('error', __('You cannot delete your own account.'));
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', __('User successfully deleted.'));
    }

    /**
     * Users eligible to be selected as a deputy (redirect target) while an
     * MLPP staff member is on leave/holiday. Excludes the given user id.
     */
    protected function deputyOptions(?int $excludeUserId = null): array
    {
        return User::deputyOptions($excludeUserId);
    }

    protected function defaultShiftCode(): ?string
    {
        $shifts = array_keys(config('attendance.shifts', []));

        return $shifts[0] ?? null;
    }

    protected function resolveShiftCode(?string $code): ?string
    {
        $shifts = array_keys(config('attendance.shifts', []));
        if (empty($shifts)) {
            return null;
        }

        if ($code === null || $code === '') {
            return null;
        }

        return in_array($code, $shifts, true)
            ? $code
            : null;
    }

    protected function notifyAccountUpdate(User $user): void
    {
        try {
            $actor = Auth::user();
            $actorName = $actor?->name
                ?? trim(($actor->first_name ?? '') . ' ' . ($actor->last_name ?? ''))
                ?: ($actor?->email ?? 'System');

            $accountName = $user->name
                ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))
                ?: ($user->username ?? 'Account');

            $this->notificationService->create(
                (int) $user->id,
                'file_tracking.account.updated',
                'Account details updated',
                sprintf('%s updated the profile for %s.', $actorName, $accountName),
                [
                    'updated_by' => $actor?->id,
                    'updated_by_name' => $actorName,
                    'account_name' => $accountName,
                ],
                [
                    'module' => 'file_tracking',
                    'subject' => 'Account details updated',
                    'message' => sprintf('%s updated the profile for %s.', $actorName, $accountName),
                ]
            );
        } catch (\Throwable $exception) {
            \Log::warning('Unable to send account update notification', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function loggedHistory()
    {
        $histories = LoggedHistory::where('parent_id', parentId())->get();
        return view('logged_history.index', compact('histories'));
    }

    public function loggedHistoryShow($id)
    {
        $histories = LoggedHistory::find($id);
        return view('logged_history.show', compact('histories'));
    }

    public function loggedHistoryDestroy($id)
    {
        $histories = LoggedHistory::find($id);
        $histories->delete();
        return redirect()->back()->with('success', 'Logged history succefully deleted.');
    }

}
