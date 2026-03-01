<?php

namespace App\Policies;

use App\Models\LeadSubmission;
use App\Models\User;
use App\Services\SubmissionAccessService;
use App\Support\RbacPermission;

class LeadSubmissionPolicy
{
    private static function hasBackOfficeRole(User $user): bool
    {
        return $user->hasRole('back_office')
            || $user->hasRole('backoffice')
            || $user->hasRole('back_office_executive');
    }

    public static function isValidAssignee(User $assignee): bool
    {
        return self::hasBackOfficeRole($assignee);
    }

    /** Requires read permission on lead module. */
    public function viewAny(User $user): bool
    {
        // Business requirement: all authenticated roles can view lead submissions listing.
        return true;
    }

    public function create(User $user): bool
    {
        return RbacPermission::can($user, ['lead', 'lead-submissions'], 'create', [
            'lead.create',
            'lead-submissions.create',
        ]);
    }

    public function view(User $user, LeadSubmission $lead): bool
    {
        // Business requirement: all authenticated roles can view lead details/rows.
        return true;
    }

    public function update(User $user, LeadSubmission $lead): bool
    {
        return RbacPermission::can($user, ['lead', 'lead-submissions'], 'update', [
            'lead.edit',
            'lead-submissions.edit',
        ]) && $this->view($user, $lead);
    }

    public function delete(User $user, LeadSubmission $lead): bool
    {
        return RbacPermission::can($user, ['lead', 'lead-submissions'], 'delete', [
            'lead.delete',
            'lead-submissions.delete',
        ]) && $this->view($user, $lead);
    }

    public function assignAny(User $user): bool
    {
        if (! self::hasBackOfficeRole($user)) {
            return false;
        }

        return RbacPermission::can($user, ['back_office', 'lead', 'lead-submissions'], 'update', [
            'back_office.assign_bo_executive',
            'lead.assign_bo_executive',
            'lead-submissions.assign_bo_executive',
        ]);
    }

    public function assign(User $user, LeadSubmission $lead): bool
    {
        return $this->assignAny($user) && $this->view($user, $lead);
    }

    public function submit(User $user, LeadSubmission $lead): bool
    {
        return $lead->status === 'draft' && (int) $lead->created_by === (int) $user->id;
    }

    /** Resubmit: rejected or submitted leads; only super admin or the original submitter (created_by). */
    public function resubmit(User $user, LeadSubmission $lead): bool
    {
        if (! in_array($lead->status, ['rejected', 'submitted'])) {
            return false;
        }
        if ($user->hasRole('superadmin')) {
            return true;
        }
        return (int) $lead->created_by === (int) $user->id;
    }
}
