<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DspTrackerEntry;
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

        $items = $query->get()->map(fn ($row) => $this->formatRow($row));

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
        $toInsert = [];
        foreach ($validated['rows'] as $row) {
            $entry = [
                'import_batch_id' => $batchId,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            foreach ($allowed as $col) {
                $entry[$col] = isset($row[$col]) ? (string) $row[$col] : null;
            }
            $toInsert[] = $entry;
        }

        DspTrackerEntry::query()->insert($toInsert);

        return response()->json([
            'batch_id' => $batchId,
            'count' => count($toInsert),
        ], 201);
    }

    public function destroyBatch(Request $request, string $batchId): JsonResponse
    {
        $deleted = DspTrackerEntry::query()
            ->where('import_batch_id', $batchId)
            ->delete();

        return response()->json([
            'deleted' => $deleted,
        ]);
    }

    private function formatRow(DspTrackerEntry $row): array
    {
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
            'verifier_number' => $row->verifier_number,
            'dsp_om_id' => $row->dsp_om_id,
            'uploaded_by' => $row->uploaded_by,
            'uploaded_at' => $row->uploaded_at,
        ];
        return $out;
    }
}
