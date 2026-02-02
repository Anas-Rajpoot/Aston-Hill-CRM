<?php

namespace App\Services;

use App\Models\LeadSubmission;
use App\Models\ServiceType;
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

    public function saveStep4Documents(Request $request, LeadSubmission $leadSubmission): void
    {
        $type = ServiceType::findOrFail($leadSubmission->service_type_id);
        $docs = LeadSubmissionSchema::documents($type);

        foreach ($docs as $doc) {
            $key = $doc['key'] ?? null;
            if (!$key) continue;

            $files = $request->file("documents.$key");
            if (!$files) continue;

            $files = is_array($files) ? $files : [$files];
            foreach ($files as $file) {
                if ($file && $file->isValid()) {
                    $this->storeLeadSubmissionDocument($leadSubmission, $key, $file);
                }
            }
        }

        // Save additional custom documents (keys like additional_*)
        $labels = $request->input('document_labels', []);
        $allDocInput = $request->file('documents', []) ?: [];
        foreach ($allDocInput as $key => $files) {
            if (str_starts_with((string) $key, 'additional_')) {
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
    }

    public function submit(LeadSubmission $leadSubmission): LeadSubmission
    {
        return $this->repo->submit($leadSubmission);
    }
}
