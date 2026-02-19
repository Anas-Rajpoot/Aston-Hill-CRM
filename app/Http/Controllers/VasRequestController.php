<?php

namespace App\Http\Controllers;

use App\Models\VasRequestDocument;
use App\Models\VasRequestSubmission;
use App\Services\VasRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class VasRequestController extends Controller
{
    public function __construct(private VasRequestService $vasRequestService)
    {
    }

    public static function requestTypes(): array
    {
        return [
            'Establishment Card Update',
            'Trade License Update',
            'POC Details Update',
            'Benefit Activation',
            'CNAP Update',
            'Sim Contract Renewals',
            'Hard Cap',
            'IR Activation',
            'Rate Plan Change',
            'Vas Activation',
            'Migration - Pre to Post',
            'Migration - Post to Pre',
            'Upgrade Rate Plan Change Request',
            'Downgrade Rate Plan Change Request',
            'Flavour Change',
            'Sub Account To Main Account Transfer',
            'Company Name Change',
            'TRN Update',
            'Other Request',
        ];
    }

    /** Document keys for VAS submission. Only Trade License is required. */
    public static function documentSchema(): array
    {
        return [
            ['key' => 'trade_license', 'label' => 'Trade License', 'required' => true],
            ['key' => 'establishment_card', 'label' => 'Establishment Card', 'required' => false],
            ['key' => 'owner_emirates_id', 'label' => 'Owner Emirates ID', 'required' => false],
            ['key' => 'request_letter', 'label' => 'REQUEST LETTER', 'required' => false],
            ['key' => 'proposal_form', 'label' => 'Proposal Form', 'required' => false],
            ['key' => 'excel', 'label' => 'Excel', 'required' => false],
            ['key' => 'loa_poa', 'label' => 'LOA / POA', 'required' => false],
            ['key' => 'as_person_eid', 'label' => 'AS Person EID', 'required' => false],
            ['key' => 'customer_confirmation_email', 'label' => 'Customer Confirmation Email', 'required' => false],
            ['key' => 'fnp_binder', 'label' => 'FNP Binder', 'required' => false],
        ];
    }

    public function teamOptions(Request $request): JsonResponse
    {
        return app(FieldSubmissionController::class)->teamOptions($request);
    }

    public function documentSchemaResponse(): JsonResponse
    {
        return response()->json(['documents' => self::documentSchema()]);
    }

    /**
     * Store step 1: primary info + team info. Creates draft and returns id.
     */
    public function storeStep1(Request $request): JsonResponse
    {
        $types = self::requestTypes();
        $data = $request->validate([
            'request_type' => ['required', 'string', Rule::in($types)],
            'account_number' => ['required', 'string', 'max:100'],
            'contact_number' => ['required', 'string', 'max:50'],
            'company_name' => ['required', 'string', 'max:255'],
            'request_description' => ['required', 'string', 'max:5000'],
            'additional_notes' => ['nullable', 'string', 'max:2000'],
            'manager_id' => ['required', 'exists:users,id'],
            'team_leader_id' => ['required', 'exists:users,id'],
            'sales_agent_id' => ['required', 'exists:users,id'],
        ], [
            'request_type.required' => 'Please select a request type.',
            'request_type.in' => 'Invalid request type.',
            'account_number.required' => 'Account number is required.',
            'contact_number.required' => 'Contact number is required.',
            'company_name.required' => 'Company name is required.',
            'request_description.required' => 'Request description is required.',
            'manager_id.required' => 'Please select a manager.',
            'team_leader_id.required' => 'Please select a team leader.',
            'sales_agent_id.required' => 'Please select a sales agent.',
        ]);

        $payload = [
            'request_type' => $data['request_type'],
            'account_number' => $data['account_number'],
            'contact_number' => $data['contact_number'],
            'company_name' => $data['company_name'],
            'description' => $data['request_description'],
            'additional_notes' => $data['additional_notes'] ?? null,
            'manager_id' => (int) $data['manager_id'],
            'team_leader_id' => (int) $data['team_leader_id'],
            'sales_agent_id' => (int) $data['sales_agent_id'],
        ];

        $submission = $this->vasRequestService->create($payload, $request->user()->id);

        return response()->json([
            'id' => $submission->id,
            'message' => 'VAS request saved. Proceed to Step 2 to upload documents.',
        ], 201);
    }

    /** Max file size in KB (3 MB). */
    private const MAX_FILE_KB = 3072;

    /** Max total size of all uploaded files in bytes (10 MB). */
    private const MAX_TOTAL_BYTES = 10 * 1024 * 1024;

    /**
     * Store step 2: document uploads. Accepts multipart with document keys and optional additional_documents.
     * Each file max 3 MB; total of all files max 10 MB.
     */
    public function storeStep2(Request $request, VasRequestSubmission $vasRequest): JsonResponse
    {
        $schema = collect(self::documentSchema())->keyBy('key');
        $rules = [];
        $messages = [];
        foreach ($schema as $key => $doc) {
            $rules[$key] = ['nullable', 'file', 'max:' . self::MAX_FILE_KB];
            $messages[$key . '.max'] = 'The file must not be greater than 3 MB.';
        }
        $rules['additional_document_label'] = ['nullable', 'array'];
        $rules['additional_document_label.*'] = ['nullable', 'string', 'max:255'];
        $rules['additional_documents'] = ['nullable', 'array'];
        $rules['additional_documents.*'] = ['nullable', 'file', 'max:' . self::MAX_FILE_KB];
        $messages['additional_documents.*.max'] = 'Each file must not be greater than 3 MB.';

        $request->validate($rules, $messages);

        $totalBytes = 0;
        foreach ($schema as $key => $doc) {
            if ($request->hasFile($key)) {
                $file = $request->file($key);
                $totalBytes += $file->getSize();
            }
        }
        $additionalFiles = $request->file('additional_documents', []);
        foreach ($additionalFiles as $file) {
            if ($file && $file->isValid()) {
                $totalBytes += $file->getSize();
            }
        }
        if ($totalBytes > self::MAX_TOTAL_BYTES) {
            return response()->json([
                'message' => 'Total size of all files must not exceed 10 MB.',
                'errors' => [
                    'documents' => ['Total size of all files must not exceed 10 MB.'],
                ],
            ], 422);
        }

        foreach ($schema as $key => $doc) {
            if ($request->hasFile($key)) {
                $this->vasRequestService->storeDocument($vasRequest, $key, $request->file($key), null);
            }
        }

        $labels = $request->input('additional_document_label', []);
        foreach ($additionalFiles as $i => $file) {
            if ($file && $file->isValid()) {
                $label = $labels[$i] ?? 'Additional document ' . ($i + 1);
                $this->vasRequestService->storeDocument($vasRequest, 'additional_' . $i, $file, $label);
            }
        }

        return response()->json([
            'message' => 'Documents uploaded.',
        ], 200);
    }

    /**
     * Mark VAS request as submitted. Ensures all required documents are present.
     */
    public function submit(Request $request, VasRequestSubmission $vasRequest): JsonResponse
    {
        $vasRequest->load('documents');
        $schema = collect(self::documentSchema())->keyBy('key');
        $requiredKeys = $schema->where('required', true)->keys()->all();
        $existingKeys = $vasRequest->documents->pluck('doc_key')->unique()->all();

        $missing = array_diff($requiredKeys, $existingKeys);
        if (! empty($missing)) {
            $labels = $schema->mapWithKeys(fn ($d) => [$d['key'] => $d['label']])->all();
            $missingLabels = array_map(fn ($k) => $labels[$k] ?? $k, $missing);
            return response()->json([
                'message' => 'All required documents must be uploaded before submitting.',
                'errors' => [
                    'documents' => [implode(' ', array_map(fn ($l) => $l . ' is required.', $missingLabels))],
                ],
            ], 422);
        }

        $this->vasRequestService->submit($vasRequest);
        return response()->json([
            'message' => 'VAS request submitted successfully.',
        ], 200);
    }

    public function show(Request $request, VasRequestSubmission $vasRequest): JsonResponse
    {
        $this->authorize('view', $vasRequest);
        $vasRequest->load(['documents', 'creator.roles', 'manager:id,name', 'teamLeader:id,name', 'salesAgent:id,name', 'backOfficeExecutive:id,name']);
        $creator = $vasRequest->creator;
        $creatorName = $creator ? $creator->name : null;
        $creatorRole = $creator && $creator->roles->isNotEmpty()
            ? $creator->roles->first()->name
            : null;

        $documents = $vasRequest->documents->map(function ($doc) {
            $size = null;
            if ($doc->file_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($doc->file_path)) {
                $size = \Illuminate\Support\Facades\Storage::disk('public')->size($doc->file_path);
            }
            return [
                'id' => $doc->id,
                'doc_key' => $doc->doc_key,
                'file_path' => $doc->file_path,
                'file_name' => $doc->file_name,
                'label' => $doc->label,
                'size' => $size,
            ];
        });

        $data = $vasRequest->toArray();
        $data['creator_name'] = $creatorName;
        $data['creator_role'] = $creatorRole;
        $data['manager_name'] = $vasRequest->manager?->name;
        $data['team_leader_name'] = $vasRequest->teamLeader?->name;
        $data['sales_agent_name'] = $vasRequest->salesAgent?->name;
        $data['back_office_executive_name'] = $vasRequest->backOfficeExecutive?->name;
        $data['documents'] = $documents;

        return response()->json($data);
    }

    /**
     * Update VAS request (edit page). Authorized by vas.edit.
     */
    public function update(Request $request, VasRequestSubmission $vasRequest): JsonResponse
    {
        $this->authorize('update', $vasRequest);

        $types = self::requestTypes();
        $data = $request->validate([
            'request_type' => ['required', 'string', Rule::in($types)],
            'account_number' => ['nullable', 'string', 'max:100'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'company_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'request_description' => ['nullable', 'string', 'max:5000'],
            'additional_notes' => ['nullable', 'string', 'max:2000'],
            'manager_id' => ['required', 'exists:users,id'],
            'team_leader_id' => ['required', 'exists:users,id'],
            'sales_agent_id' => ['required', 'exists:users,id'],
            'back_office_executive_id' => ['nullable', 'exists:users,id'],
        ]);

        if (isset($data['request_description'])) {
            $data['description'] = $data['request_description'];
            unset($data['request_description']);
        }

        $vasRequest->update($data);
        return response()->json([
            'message' => 'VAS request updated.',
            'id' => $vasRequest->id,
        ]);
    }

    /**
     * POST /api/vas-requests/{vasRequest}/resubmit
     * Allows updating a rejected VAS request and re-submitting it.
     */
    public function resubmit(Request $request, VasRequestSubmission $vasRequest): JsonResponse
    {
        $this->authorize('update', $vasRequest);

        if ($vasRequest->status !== 'rejected') {
            return response()->json([
                'message' => 'Only rejected requests can be resubmitted.',
            ], 422);
        }

        $types = self::requestTypes();
        $data = $request->validate([
            'request_type' => ['required', 'string', Rule::in($types)],
            'account_number' => ['nullable', 'string', 'max:100'],
            'company_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'manager_id' => ['required', 'exists:users,id'],
            'team_leader_id' => ['required', 'exists:users,id'],
            'sales_agent_id' => ['required', 'exists:users,id'],
            'back_office_executive_id' => ['nullable', 'exists:users,id'],
        ]);

        $vasRequest->update(array_merge($data, [
            'status' => 'submitted',
            'submitted_at' => now(),
        ]));

        return response()->json([
            'message' => 'VAS request resubmitted successfully.',
            'id' => $vasRequest->id,
        ]);
    }

    /**
     * GET /api/vas-requests/{vasRequest}/documents/{document}/download
     */
    public function downloadDocument(Request $request, VasRequestSubmission $vasRequest, int $document): JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorize('view', $vasRequest);

        $doc = VasRequestDocument::where('vas_request_submission_id', $vasRequest->id)
            ->where('id', $document)
            ->first();
        if (! $doc || ! $doc->file_path) {
            return response()->json(['message' => 'Document not found.'], 404);
        }
        $fullPath = Storage::disk('public')->path($doc->file_path);
        if (! is_file($fullPath)) {
            return response()->json(['message' => 'File not found.'], 404);
        }
        $filename = $doc->file_name ?: basename($doc->file_path);

        return response()->file($fullPath, [
            'Content-Disposition' => 'attachment; filename="' . addslashes($filename) . '"',
        ]);
    }

    /**
     * DELETE /api/vas-requests/{vasRequest}/documents/{document}
     */
    public function deleteDocument(Request $request, VasRequestSubmission $vasRequest, int $document): JsonResponse
    {
        $this->authorize('update', $vasRequest);

        $this->vasRequestService->deleteDocument($vasRequest, $document);

        return response()->json(['message' => 'Document removed.']);
    }
}
