<?php

namespace App\Observers;

use App\Models\VasRequestSubmission;
use App\Models\VasRequestAudit;
use Illuminate\Support\Facades\Auth;

class VasRequestObserver
{
    public function updated(VasRequestSubmission $vas)
    {
        foreach ($vas->getChanges() as $field => $new) {
            if ($field === 'updated_at') continue;

            VasRequestAudit::create([
                'vas_request_submission_id' => $vas->id,
                'field_name' => $field,
                'old_value' => $vas->getOriginal($field),
                'new_value' => $new,
                'changed_at' => now(),
                'changed_by' => Auth::id(),
            ]);
        }
    }
}
