<?php

namespace App\Repositories\Eloquent;

use App\Models\LeadSubmission;
use App\Repositories\Contracts\LeadSubmissionRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class LeadSubmissionRepository implements LeadSubmissionRepositoryInterface
{
    public function queryForListing(): Builder
    {
        return LeadSubmission::query()->with(['creator:id,name,email','category:id,name','type:id,name']);
    }

    public function createDraft(array $data): LeadSubmission
    {
        $data['status'] = $data['status'] ?? 'draft';
        return LeadSubmission::create($data);
    }

    public function updateLead(LeadSubmission $leadSubmission, array $data): LeadSubmission
    {
        $leadSubmission->update($data);
        return $leadSubmission->refresh();
    }

    public function submit(LeadSubmission $leadSubmission): LeadSubmission
    {
        $leadSubmission->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        return $leadSubmission->refresh();
    }
}
