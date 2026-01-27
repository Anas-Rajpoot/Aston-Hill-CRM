<?php

namespace App\Repositories\Contracts;

use App\Models\LeadSubmission;
use Illuminate\Database\Eloquent\Builder;

interface LeadSubmissionRepositoryInterface
{
    public function queryForListing(): Builder;

    public function createDraft(array $data): LeadSubmission;
    public function updateLead(LeadSubmission $leadSubmission, array $data): LeadSubmission;

    public function submit(LeadSubmission $leadSubmission): LeadSubmission;
}
