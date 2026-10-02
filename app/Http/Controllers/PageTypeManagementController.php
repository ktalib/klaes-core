<?php

namespace App\Http\Controllers;

use App\Models\PageSubType;
use App\Models\PageType;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PageTypeManagementController extends Controller
{
    /**
     * Render the management dashboard for page types and subtypes.
     */
    public function index()
    {
        $pageTitle = 'Page Type Management';

        return view('pagetyping.manage', compact('pageTitle'));
    }

    /**
     * Return all page types with their subtypes for the SPA interface.
     */
    public function listPageTypes(): JsonResponse
    {
        $pageTypes = PageType::with(['pageSubTypes' => function ($query) {
            $query->orderBy('PageSubType');
        }])->orderBy('PageType')->get();

        $records = $pageTypes->map(function (PageType $pageType) {
            return $this->transformPageType($pageType);
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'Page types loaded successfully.',
            'data' => [
                'page_types' => $records,
            ],
        ]);
    }

    /**
     * Create a new page type record.
     */
    public function storePageType(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('sqlsrv.PageType', 'PageType'),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payload = $validator->validated();

        try {
            $pageType = null;
            DB::connection('sqlsrv')->transaction(function () use (&$pageType, $payload) {
                $name = trim($payload['name']);
                $pageType = PageType::create([
                    'PageType' => $name,
                ]);
            });

            $pageType->load('pageSubTypes');

            return response()->json([
                'success' => true,
                'message' => 'Page type created successfully.',
                'data' => [
                    'page_type' => $this->transformPageType($pageType),
                ],
            ]);
        } catch (Exception $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create page type.',
            ], 500);
        }
    }

    /**
     * Update an existing page type.
     */
    public function updatePageType(Request $request, PageType $pageType): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('sqlsrv.PageType', 'PageType')->ignore($pageType->id, 'id'),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payload = $validator->validated();

        try {
            DB::connection('sqlsrv')->transaction(function () use (&$pageType, $payload) {
                $pageType->PageType = trim($payload['name']);
                $pageType->save();
            });

            $pageType->load('pageSubTypes');

            return response()->json([
                'success' => true,
                'message' => 'Page type updated successfully.',
                'data' => [
                    'page_type' => $this->transformPageType($pageType),
                ],
            ]);
        } catch (Exception $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update page type.',
            ], 500);
        }
    }

    /**
     * Delete a page type once it has no dependent page typings.
     */
    public function deletePageType(PageType $pageType): JsonResponse
    {
        if ($pageType->pageTypings()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Page type is in use and cannot be deleted.',
            ], 409);
        }

        try {
            DB::connection('sqlsrv')->transaction(function () use (&$pageType) {
                $pageType->pageSubTypes()->delete();
                $pageType->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Page type deleted successfully.',
            ]);
        } catch (Exception $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete page type.',
            ], 500);
        }
    }

    /**
     * Store a new page subtype.
     */
    public function storePageSubType(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'page_type_id' => ['required', 'integer', 'exists:sqlsrv.PageType,id'],
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('sqlsrv.PageSubType', 'PageSubType')->where(function ($query) use ($request) {
                    $pageTypeId = (int) $request->input('page_type_id');
                    return $query->where('PageTypeId', $pageTypeId);
                }),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payload = $validator->validated();

        try {
            $pageSubType = null;
            DB::connection('sqlsrv')->transaction(function () use (&$pageSubType, $payload) {
                $pageSubType = PageSubType::create([
                    'PageTypeId' => (int) $payload['page_type_id'],
                    'PageSubType' => trim($payload['name']),
                ]);
            });

            $pageSubType->load('pageType');

            return response()->json([
                'success' => true,
                'message' => 'Page subtype created successfully.',
                'data' => [
                    'page_subtype' => $this->transformPageSubType($pageSubType),
                ],
            ]);
        } catch (Exception $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create page subtype.',
            ], 500);
        }
    }

    /**
     * Update an existing page subtype.
     */
    public function updatePageSubType(Request $request, PageSubType $pageSubType): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'page_type_id' => ['required', 'integer', 'exists:sqlsrv.PageType,id'],
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('sqlsrv.PageSubType', 'PageSubType')
                    ->ignore($pageSubType->id, 'id')
                    ->where(function ($query) use ($request) {
                        $pageTypeId = (int) $request->input('page_type_id');
                        return $query->where('PageTypeId', $pageTypeId);
                    }),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payload = $validator->validated();

        try {
            DB::connection('sqlsrv')->transaction(function () use (&$pageSubType, $payload) {
                $pageSubType->PageTypeId = (int) $payload['page_type_id'];
                $pageSubType->PageSubType = trim($payload['name']);
                $pageSubType->save();
            });

            $pageSubType->load('pageType');

            return response()->json([
                'success' => true,
                'message' => 'Page subtype updated successfully.',
                'data' => [
                    'page_subtype' => $this->transformPageSubType($pageSubType),
                ],
            ]);
        } catch (Exception $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update page subtype.',
            ], 500);
        }
    }

    /**
     * Delete a page subtype if it is unused.
     */
    public function deletePageSubType(PageSubType $pageSubType): JsonResponse
    {
        if ($pageSubType->pageTypings()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Page subtype is in use and cannot be deleted.',
            ], 409);
        }

        try {
            DB::connection('sqlsrv')->transaction(function () use (&$pageSubType) {
                $pageSubType->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Page subtype deleted successfully.',
            ]);
        } catch (Exception $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete page subtype.',
            ], 500);
        }
    }

    /**
     * Transform a page type model to the structure expected by the UI.
     */
    protected function transformPageType(PageType $pageType): array
    {
        $pageType->loadMissing('pageSubTypes');

        $subtypes = $pageType->pageSubTypes
            ->sortBy('PageSubType')
            ->values()
            ->map(function (PageSubType $subType) {
                return $this->transformPageSubType($subType);
            })
            ->all();

        return [
            'id' => $pageType->id,
            'name' => $pageType->PageType,
            'code' => $pageType->code,
            'subtypes' => $subtypes,
            'subtype_count' => count($subtypes),
        ];
    }

    /**
     * Transform a page subtype model for JSON output.
     */
    protected function transformPageSubType(PageSubType $pageSubType): array
    {
        $pageSubType->loadMissing('pageType');

        return [
            'id' => $pageSubType->id,
            'name' => $pageSubType->PageSubType,
            'code' => $pageSubType->code,
            'page_type_id' => $pageSubType->PageTypeId,
            'page_type_name' => optional($pageSubType->pageType)->PageType,
        ];
    }
}
