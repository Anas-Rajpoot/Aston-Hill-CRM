<?php

namespace App\Services;

use App\Models\LeadSubmission;
use App\Models\ServiceType;
use App\Models\User;
use App\Repositories\Contracts\LeadSubmissionRepositoryInterface;
use App\Support\LeadSubmissionSchema;
use App\Traits\StoresLeadSubmissionDocuments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeadSubmissionService
{
    use StoresLeadSubmissionDocuments;

    public function __construct(private LeadSubmissionRepositoryInterface $repo) {}

    public function createDraftFromStep1(array $data, int $userId): LeadSubmission
    {
        return $this->repo->createDraft([
            ...$data,
            'created_by' => $userId,
            'status' => 'draft',
        ]);
    }

    public function saveStep2(LeadSubmission $leadSubmission, array $data): LeadSubmission
    {
        return $this->repo->updateLead($leadSubmission, [
            'service_category_id' => $data['service_category_id'],
            'service_type_id' => $data['service_type_id'],
        ]);
    }

    public function saveStep3(LeadSubmission $leadSubmission, array $data): LeadSubmission
    {
        return DB::transaction(function () use ($leadSubmission, $data) {
            return $this->repo->updateLead($leadSubmission, [
                'service_type_id' => $data['service_type_id'],
                'payload' => array_merge($leadSubmission->payload ?? [], $data['meta'] ?? []),
            ]);
        });
    }

    /**
     * Save documents for step 3. One document per doc_key per lead: replace existing before storing.
     */
    public function saveStep4Documents(Request $request, LeadSubmission $leadSubmission): void
    {
        DB::transaction(function () use ($request, $leadSubmission) {
            $type = ServiceType::findOrFail($leadSubmission->service_type_id);
            $docs = LeadSubmissionSchema::documents($type);

            foreach ($docs as $doc) {
                $key = $doc['key'] ?? null;
                if (! $key) continue;

                $files = $request->file("documents.{$key}");
                if (! $files) continue;

                $files = is_array($files) ? $files : [$files];
                $this->deleteDocumentsByKey($leadSubmission, $key);
                foreach ($files as $file) {
                    if ($file && $file->isValid()) {
                        $this->storeLeadSubmissionDocument($leadSubmission, $key, $file);
                    }
                }
            }

            $labels = $request->input('document_labels', []);
            $allDocInput = $request->file('documents', []) ?: [];
            foreach ($allDocInput as $key => $files) {
                if (str_starts_with((string) $key, 'additional_')) {
                    $this->deleteDocumentsByKey($leadSubmission, $key);
                    $title = is_array($labels) ? ($labels[$key] ?? null) : null;
                    if (is_string($title)) {
                        $title = trim($title) ?: null;
                    }
                    $files = is_array($files) ? $files : [$files];
                    foreach ($files as $file) {
                        if ($file && $file->isValid()) {
                            $this->storeLeadSubmissionDocument($leadSubmission, $key, $file, $title);
                        }
                    }
                }
            }
        });
    }

    /**
     * Save resubmission documents: for each key, replace existing with uploaded files.
     */
    public function saveResubmissionDocuments(Request $request, LeadSubmission $leadSubmission, array $docKeys): void
    {
        DB::transaction(function () use ($request, $leadSubmission, $docKeys) {
            foreach ($docKeys as $key) {
                $files = $request->file("documents.{$key}");
                if (!$files) {
                    continue;
                }
                $files = is_array($files) ? $files : [$files];
                $this->deleteDocumentsByKey($leadSubmission, $key);
                foreach ($files as $file) {
                    if ($file && $file->isValid()) {
                        $this->storeLeadSubmissionDocument($leadSubmission, $key, $file);
                    }
                }
            }
        });
    }

    /** Delete all documents for this lead and doc_key (used when replacing with new upload). */
    protected function deleteDocumentsByKey(LeadSubmission $leadSubmission, string $docKey): void
    {
        $existing = \App\Models\LeadSubmissionDocument::where('lead_submission_id', $leadSubmission->id)
            ->where('doc_key', $docKey)
            ->get();
        foreach ($existing as $doc) {
            $this->deleteLeadSubmissionDocument($doc);
        }
    }

    /**
     * Delete a single document by id. Document must belong to the lead.
     */
    public function deleteDocument(LeadSubmission $leadSubmission, int $documentId): void
    {
        $doc = \App\Models\LeadSubmissionDocument::where('lead_submission_id', $leadSubmission->id)
            ->where('id', $documentId)
            ->firstOrFail();
        $this->deleteLeadSubmissionDocument($doc);
    }

    /**
     * Add documents from request (e.g. edit page "add document"). Accepts documents[] and optional document_labels[].
     * Each file is stored with doc_key additional_{timestamp}_{index}.
     */
    public function addDocumentsFromRequest(Request $request, LeadSubmission $leadSubmission): void
    {
        $files = $request->file('documents');
        if (! is_array($files)) {
            $files = $files ? [$files] : [];
        }
        $labels = $request->input('document_labels', []);
        $ts = (string) time();
        foreach ($files as $i => $file) {
            if ($file && $file->isValid()) {
                $docKey = 'additional_' . $ts . '_' . $i;
                $label = is_array($labels) && isset($labels[$i]) ? $labels[$i] : null;
                if (is_string($label)) {
                    $label = trim($label) ?: null;
                }
                $this->storeLeadSubmissionDocument($leadSubmission, $docKey, $file, $label);
            }
        }
    }

    public function submit(LeadSubmission $leadSubmission): LeadSubmission
    {
        $fresh = $this->repo->submit($leadSubmission);

        NotificationService::dispatchOnce('new_submission_created', 'lead_submission:' . $fresh->id, [
            'module' => 'Lead Submissions',
            'title' => 'New Lead Submission',
            'message' => sprintf('Lead submission #%d was created for %s.', $fresh->id, $fresh->company_name ?: 'N/A'),
            'url' => '/lead-submissions',
            'users' => User::query()->get(['id', 'name', 'email']),
        ], 900);

        return $fresh;
    }
}
