<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemAuditLog;
use App\Models\Verifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VerifierApiController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum']);
    }

    /** Check if user can add verifiers (super admin or verifiers.add permission). */
    private function canAdd(Request $request): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }
        if ($user->hasRole('superadmin')) {
            return true;
        }

        return $user->can('verifiers.add') || $user->can('verifiers.create');
    }

    /** Check if user can delete verifiers. */
    private function canDelete(Request $request): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }
        if ($user->hasRole('superadmin')) {
            return true;
        }

        return $user->can('verifiers.delete');
    }

    /**
     * GET /api/verifiers – list with search, sort, pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string', Rule::in(['id', 'verifier_name', 'verifier_number', 'remarks', 'created_at'])],
            'order' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'q' => ['sometimes', 'nullable', 'string', 'max:200'],
        ]);

        $query = Verifier::query();

        if (! empty($validated['q'])) {
            $term = '%' . addcslashes($validated['q'], '%_\\') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('verifier_name', 'like', $term)
                    ->orWhere('verifier_number', 'like', $term)
                    ->orWhere('remarks', 'like', $term)
                    ->orWhere('id', 'like', $term);
            });
        }

        $sort = $validated['sort'] ?? 'id';
        $order = $validated['order'] ?? 'desc';
        $query->orderBy($sort, $order);

        $perPage = (int) ($validated['per_page'] ?? 10);
        $page = (int) ($validated['page'] ?? 1);
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $items = $paginator->getCollection()->map(fn ($row) => [
            'id' => $row->id,
            'verifier_name' => $row->verifier_name,
            'verifier_number' => $row->verifier_number,
            'remarks' => $row->remarks,
            'created_at' => $row->created_at?->toIso8601String(),
            'updated_at' => $row->updated_at?->toIso8601String(),
        ]);

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * POST /api/verifiers – create one verifier.
     */
    public function store(Request $request): JsonResponse
    {
        if (! $this->canAdd($request)) {
            return response()->json(['message' => 'Unauthorized to add verifiers.'], 403);
        }

        $validated = $request->validate([
            'verifier_name' => ['required', 'string', 'max:255'],
            'verifier_number' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string'],
        ]);

        $verifier = Verifier::create($validated);

        try {
            SystemAuditLog::record(
                'verifier.created',
                null,
                $verifier->toArray(),
                $request->user()->id,
                'verifier',
                $verifier->id
            );
        } catch (\Exception $e) {
            // Ignore audit logging errors
        }

        return response()->json([
            'message' => 'Verifier created.',
            'data' => [
                'id' => $verifier->id,
                'verifier_name' => $verifier->verifier_name,
                'verifier_number' => $verifier->verifier_number,
                'remarks' => $verifier->remarks,
            ],
        ], 201);
    }

    /**
     * PUT /api/verifiers/{verifier} – update (for double-click edit).
     */
    public function update(Request $request, Verifier $verifier): JsonResponse
    {
        if (! $this->canAdd($request)) {
            return response()->json(['message' => 'Unauthorized to edit verifiers.'], 403);
        }

        $validated = $request->validate([
            'verifier_name' => ['sometimes', 'string', 'max:255'],
            'verifier_number' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string'],
        ]);

        $old = $verifier->getOriginal();
        $verifier->update($validated);
        $changed = $verifier->getChanges();

        try {
            SystemAuditLog::record(
                'verifier.updated',
                array_intersect_key($old, $changed),
                $changed,
                $request->user()->id,
                'verifier',
                $verifier->id
            );
        } catch (\Exception $e) {
            // Ignore audit logging errors
        }

        return response()->json([
            'message' => 'Verifier updated.',
            'data' => [
                'id' => $verifier->id,
                'verifier_name' => $verifier->verifier_name,
                'verifier_number' => $verifier->verifier_number,
                'remarks' => $verifier->remarks,
            ],
        ]);
    }

    /**
     * DELETE /api/verifiers/{verifier}.
     */
    public function destroy(Request $request, Verifier $verifier): JsonResponse
    {
        if (! $this->canDelete($request)) {
            return response()->json(['message' => 'Unauthorized to delete verifiers.'], 403);
        }

        try {
            SystemAuditLog::record(
                'verifier.deleted',
                $verifier->toArray(),
                null,
                $request->user()->id,
                'verifier',
                $verifier->id
            );
        } catch (\Exception $e) {
            // Ignore audit logging errors
        }

        $verifier->delete();

        return response()->json(['message' => 'Verifier deleted.']);
    }

    /**
     * POST /api/verifiers/import-csv – bulk import from CSV rows.
     */
    public function importCsv(Request $request): JsonResponse
    {
        if (! $this->canAdd($request)) {
            return response()->json(['message' => 'Unauthorized to import verifiers.'], 403);
        }

        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*' => ['array'],
            'rows.*.verifier_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'rows.*.verifier_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'rows.*.remarks' => ['sometimes', 'nullable', 'string'],
        ]);

        $rows = $validated['rows'];
        $now = now();
        $toInsert = [];
        foreach ($rows as $row) {
            $name = trim($row['verifier_name'] ?? '');
            if ($name === '') {
                continue;
            }
            $toInsert[] = [
                'verifier_name' => $name,
                'verifier_number' => trim($row['verifier_number'] ?? '') ?: null,
                'remarks' => trim($row['remarks'] ?? '') ?: null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $count = 0;
        if (! empty($toInsert)) {
            Verifier::query()->insert($toInsert);
            $count = count($toInsert);
        }

        try {
            SystemAuditLog::record(
                'verifier.bulk_imported',
                null,
                ['count' => $count],
                $request->user()->id,
                'verifier',
                null
            );
        } catch (\Exception $e) {
            // Ignore audit logging errors
        }

        return response()->json([
            'message' => $count . ' verifier(s) imported.',
            'imported' => $count,
        ]);
    }

    /**
     * GET /api/verifiers/export-csv – stream CSV download.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:200'],
        ]);

        $query = Verifier::query()->orderBy('id');
        if (! empty($validated['q'])) {
            $term = '%' . addcslashes($validated['q'], '%_\\') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('verifier_name', 'like', $term)
                    ->orWhere('verifier_number', 'like', $term)
                    ->orWhere('remarks', 'like', $term);
            });
        }

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="verifiers-' . date('Y-m-d-His') . '.csv"',
        ];

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['no.', 'Verifier Name', 'Verifier Number', 'Remarks']);
            $num = 0;
            $query->chunk(100, function ($chunk) use ($handle, &$num) {
                foreach ($chunk as $row) {
                    $num++;
                    fputcsv($handle, [
                        $num,
                        $row->verifier_name ?? '',
                        $row->verifier_number ?? '',
                        $row->remarks ?? '',
                    ]);
                }
            });
            fclose($handle);
        }, 'verifiers-' . date('Y-m-d-His') . '.csv', $headers);
    }
}
