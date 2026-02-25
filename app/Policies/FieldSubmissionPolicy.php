<?php

namespace App\Policies;

use App\Models\FieldSubmission;
use App\Models\User;
use App\Services\SubmissionAccessService;
use App\Support\RbacPermission;

class FieldSubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return RbacPermission::can($user, ['field-submissions', 'field_head'], 'read', [
            'field-submissions.list',
            'field-submissions.view',
            'field_head.list',
            'field_head.view',
        ]);
    }

    public function view(User $user, FieldSubmission $fieldSubmission): bool
    {
        return $this->viewAny($user)
            && SubmissionAccessService::canAccessRecord($user, $fieldSubmission, ['field_executive_id']);
    }

    public function update(User $user, FieldSubmission $fieldSubmission): bool
    {
        return RbacPermission::can($user, ['field-submissions', 'field_head'], 'update', [
            'field-submissions.edit',
            'field_head.change_meeting_status',
            'field_head.upload_field_proof',
        ]) && $this->view($user, $fieldSubmission);
    }
}
