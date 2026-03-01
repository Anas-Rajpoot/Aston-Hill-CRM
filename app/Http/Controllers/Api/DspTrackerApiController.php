<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DspTrackerEntry;
use App\Models\SystemAuditLog;
use App\Models\Verifier;
use App\Support\RbacPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DspTrackerApiController extends Controller
{
    public const ALLOWED_COLUMNS = [
        'activity_number',
        'company_name',
        'account_number',
        'request_type',
        'appointment_date',
        'appointment_time',
        'product',
        'so_number',
        'request_status',
        'rejection_reason',
        'verifier_name',
        'verifier_number',
        'dsp_om_id',
        'uploaded_by',
        'uploaded_at',
    ];

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'read', ['dsp_tracker.list', 'dsp_tracker.search_dsp_status', 'dsp_tracker_status.list']);

        $validated = $request->validate([
            'sort' => ['sometimes', 'string', Rule::in(array_merge(self::ALLOWED_COLUMNS, ['id', 'created_at']))],
            'order' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'activity_number' => ['sometimes', 'nullable', 'string', 'max:200'],
            'company_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'account_number' => ['sometimes', 'nullable', 'string', 'max:200'],
            'request_type' => ['sometimes', 'nullable', 'string', 'max:200'],
            'request_status' => ['sometimes', 'nullable', 'string', 'max:200'],
            'product' => ['sometimes', 'nullable', 'string', 'max:200'],
            'so_number' => ['sometimes', 'nullable', 'string', 'max:200'],
            'rejection_reason' => ['sometimes', 'nullable', 'string', 'max:200'],
            'verifier_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'appointment_date_from' => ['sometimes', 'nullable', 'string', 'max:20'],
            'appointment_date_to' => ['sometimes', 'nullable', 'string', 'max:20'],
        ]);

        $query = DspTrackerEntry::query();

        foreach (['activity_number', 'company_name', 'account_number', 'request_type', 'request_status', 'product', 'so_number', 'rejection_reason', 'verifier_name'] as $key) {
            if (! empty($validated[$key] ?? null)) {
                $query->where($key, 'like', '%' . $validated[$key] . '%');
            }
        }
        if (! empty($validated['appointment_date_from'] ?? null)) {
            $query->where('appointment_date', '>=', $validated['appointment_date_from']);
        }
        if (! empty($validated['appointment_date_to'] ?? null)) {
            $query->where('appointment_date', '<=', $validated['appointment_date_to']);
        }

        $sort = $validated['sort'] ?? 'id';
        $order = $validated['order'] ?? 'asc';
        $query->orderBy($sort, $order);

        $rows = $query->get(array_merge(['id'], self::ALLOWED_COLUMNS));
        $verifierNumberByName = $this->buildVerifierNumberMap(
            $rows->pluck('verifier_name')->all()
        );
        $items = $rows->map(fn ($row) => $this->formatRow($row, $verifierNumberByName));

        $lastImportBatchId = DspTrackerEntry::query()
            ->where('user_id', $request->user()?->id)
            ->orderByDesc('created_at')
            ->value('import_batch_id');

        return response()->json([
            'data' => $items,
            'meta' => [
                'last_import_batch_id' => $lastImportBatchId,
            ],
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'create', ['dsp_tracker.upload_csv']);

        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*' => ['array'],
            'rows.*.activity_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'rows.*.company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'rows.*.account_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'rows.*.request_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'rows.*.appointment_date' => ['sometimes', 'nullable', 'string', 'max:50'],
            'rows.*.appointment_time' => ['sometimes', 'nullable', 'string', 'max:50'],
            'rows.*.product' => ['sometimes', 'nullable', 'string', 'max:255'],
            'rows.*.so_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'rows.*.request_status' => ['sometimes', 'nullable', 'string', 'max:100'],
            'rows.*.rejection_reason' => ['sometimes', 'nullable', 'string', 'max:500'],
            'rows.*.verifier_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'rows.*.verifier_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'rows.*.dsp_om_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'rows.*.uploaded_by' => ['sometimes', 'nullable', 'string', 'max:255'],
            'rows.*.uploaded_at' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $batchId = (string) Str::uuid();
        $userId = $request->user()?->id;

        $allowed = self::ALLOWED_COLUMNS;
        $verifierNumberByName = $this->buildVerifierNumberMap(
            array_map(fn ($r) => $r['verifier_name'] ?? null, $validated['rows'])
        );
        $toInsert = [];
        foreach ($validated['rows'] as $row) {
            $entry = [
                'import_batch_id' => $batchId,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            foreach ($allowed as $col) {
                $val = $row[$col] ?? null;
                $val = is_string($val) ? trim($val) : $val;
                $entry[$col] = ($val === '' || $val === null) ? null : (string) $val;
            }
            if (($entry['verifier_number'] ?? null) === null && !empty($entry['verifier_name'])) {
                $key = strtolower(trim((string) $entry['verifier_name']));
                if (isset($verifierNumberByName[$key]) && $verifierNumberByName[$key] !== '') {
                    $entry['verifier_number'] = $verifierNumberByName[$key];
                }
            }

            // Skip fully empty rows to avoid storing meaningless records.
            $hasContent = false;
            foreach ($allowed as $col) {
                if ($entry[$col] !== null) {
                    $hasContent = true;
                    break;
                }
            }
            if (! $hasContent) {
                continue;
            }
            $toInsert[] = $entry;
        }

        if (empty($toInsert)) {
            return response()->json(['message' => 'No valid rows found to import.'], 422);
        }

        DspTrackerEntry::query()->insert($toInsert);
        $count = count($toInsert);

        try {
            SystemAuditLog::record(
                'dsp_tracker.imported',
                null,
                ['count' => $count, 'batch_id' => $batchId],
                $request->user()->id,
                'dsp_tracker',
                null
            );
        } catch (\Exception $e) {
            // Ignore audit logging errors
        }

        return response()->json([
            'batch_id' => $batchId,
            'count' => $count,
        ], 201);
    }

    public function destroyBatch(Request $request, string $batchId): JsonResponse
    {
        $this->authorizeAction($request, 'delete', ['dsp_tracker.delete_existing_csv']);

        $count = DspTrackerEntry::query()
            ->where('import_batch_id', $batchId)
            ->count();

        $deleted = DspTrackerEntry::query()
            ->where('import_batch_id', $batchId)
            ->delete();

        try {
            SystemAuditLog::record(
                'dsp_tracker.batch_deleted',
                ['batch_id' => $batchId, 'count' => $count],
                null,
                $request->user()->id,
                'dsp_tracker',
                null
            );
        } catch (\Exception $e) {
            // Ignore audit logging errors
        }

        return response()->json([
            'deleted' => $deleted,
        ]);
    }

    private function buildVerifierNumberMap(array $names): array
    {
        $normalized = collect($names)
            ->map(fn ($n) => trim((string) $n))
            ->filter(fn ($n) => $n !== '')
            ->unique()
            ->values();

        if ($normalized->isEmpty()) {
            return [];
        }

        return Verifier::query()
            ->whereIn('verifier_name', $normalized->all())
            ->get(['verifier_name', 'verifier_number'])
            ->mapWithKeys(function ($v) {
                return [strtolower(trim((string) $v->verifier_name)) => trim((string) ($v->verifier_number ?? ''))];
            })
            ->all();
    }

    private function formatRow(DspTrackerEntry $row, array $verifierNumberByName = []): array
    {
        $resolvedVerifierNumber = $row->verifier_number;
        if ((is_null($resolvedVerifierNumber) || $resolvedVerifierNumber === '') && !empty($row->verifier_name)) {
            $key = strtolower(trim((string) $row->verifier_name));
            $resolvedVerifierNumber = $verifierNumberByName[$key] ?? null;
        }

        $out = [
            'id' => $row->id,
            'activity_number' => $row->activity_number,
            'company_name' => $row->company_name,
            'account_number' => $row->account_number,
            'request_type' => $row->request_type,
            'appointment_date' => $row->appointment_date,
            'appointment_time' => $row->appointment_time,
            'product' => $row->product,
            'so_number' => $row->so_number,
            'request_status' => $row->request_status,
            'rejection_reason' => $row->rejection_reason,
            'verifier_name' => $row->verifier_name,
            'verifier_number' => $resolvedVerifierNumber,
            'dsp_om_id' => $row->dsp_om_id,
            'uploaded_by' => $row->uploaded_by,
            'uploaded_at' => $row->uploaded_at,
        ];
        return $out;
    }

    private function authorizeAction(Request $request, string $action, array $legacyNames = []): void
    {
        $user = $request->user();
        if (! $user || ! RbacPermission::can($user, ['dsp_tracker', 'dsp_tracker_status'], $action, $legacyNames)) {
            abort(403, 'Unauthorized.');
        }
    }
}
