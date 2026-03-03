<?php

namespace App\Policies;

use App\Models\FieldSubmission;
use App\Models\User;
use App\Services\SubmissionAccessService;
use App\Support\RbacPermission;

class FieldSubmissionPolicy
{
    private static function hasFieldRole(User $user): bool
    {
        return $user->hasRole('field_agent')
            || $user->hasRole('field_operations_head')
            || $user->hasRole('field_executive')
            || $user->hasRole('field');
    }

    public static function isValidAssignee(User $assignee): bool
    {
        return self::hasFieldRole($assignee);
    }

    public function viewAny(User $user): bool
    {
        if (self::hasFieldRole($user)) {
            return true;
        }

        return RbacPermission::can($user, ['field-submissions', 'field_head'], 'read', [
            'field-submissions.list',
            'field-submissions.view',
            'field_head.list',
            'field_head.view',
        ]);
    }

    public function view(User $user, FieldSubmission $fieldSubmission): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        // Field roles can view all field submissions.
        if (self::hasFieldRole($user)) {
            return true;
        }

        return SubmissionAccessService::canAccessRecord($user, $fieldSubmission, ['field_executive_id']);
    }

    public function update(User $user, FieldSubmission $fieldSubmission): bool
    {
        return RbacPermission::can($user, ['field-submissions', 'field_head'], 'update', [
            'field-submissions.edit',
            'field_head.change_meeting_status',
            'field_head.upload_field_proof',
        ]) && $this->view($user, $fieldSubmission);
    }

    public function delete(User $user, FieldSubmission $fieldSubmission): bool
    {
        return RbacPermission::can($user, ['field-submissions', 'field_head'], 'delete', [
            'field-submissions.delete',
            'field_head.delete',
        ]) && $this->view($user, $fieldSubmission);
    }

    public function assignAny(User $user): bool
    {
        return self::hasFieldRole($user);
    }

    public function assign(User $user, FieldSubmission $fieldSubmission): bool
    {
        return $this->assignAny($user) && $this->view($user, $fieldSubmission);
    }
}
