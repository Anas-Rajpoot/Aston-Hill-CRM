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

    public function createDraftFromStep1(array $data, int $userId): Lead
    {
        return $this->repo->createDraft([
            ...$data,
            'created_by' => $userId,
            'status' => 'draft',
        ]);
    }

    public function saveStep2(LeadSubmission $leadSubmission, array $data): LeadSubmission
    {
        return $this->repo->updateLeadSubmission($leadSubmission, [
            'service_category_id' => $data['service_category_id'],
        ]);
    }

    public function saveStep3(LeadSubmission $leadSubmission, array $data): LeadSubmission
    {
        // data: service_type_id + dynamic meta[]
        return DB::transaction(function () use ($leadSubmission, $data) {
            $leadSubmission = $this->repo->updateLeadSubmission($leadSubmission, [
                'service_type_id' => $data['service_type_id'],
                'meta' => $data['meta'] ?? [],
            ]);

            return $leadSubmission;
        });
    }

    public function saveStep4Documents(Request $request, LeadSubmission $leadSubmission): void
    {
        $type = ServiceType::findOrFail($lead->service_type_id);
        $docs = LeadSubmissionSchema::documents($type);

        foreach ($docs as $doc) {
            $key = $doc['key'] ?? null;
            if (!$key) continue;

            if ($request->hasFile("documents.$key")) {
                $file = $request->file("documents.$key");
                $this->storeLeadSubmissionDocument($lead, $key, $file);
            }
        }
    }

    public function submit(LeadSubmission $leadSubmission): LeadSubmission
    {
        return $this->repo->submit($leadSubmission);
    }
}
