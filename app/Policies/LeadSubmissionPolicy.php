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

    /** Requires read permission on lead module (back office always allowed). */
    public function viewAny(User $user): bool
    {
        if (self::hasBackOfficeRole($user)) {
            return true;
        }

        return RbacPermission::can($user, ['lead', 'lead-submissions'], 'read', [
            'lead.view',
            'lead.view.all',
            'lead-submissions.list',
            'lead-submissions.view',
        ]);
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
        if (! $this->viewAny($user)) {
            return false;
        }

        if ($user->hasRole('superadmin')) {
            return true;
        }

        if ($user->can('lead.view.all')) {
            return true;
        }

        if (self::hasBackOfficeRole($user)) {
            return true;
        }

        return SubmissionAccessService::canAccessRecord($user, $lead, ['executive_id']);
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

        // Super admin can always resubmit.
        if ($user->hasRole('superadmin')) {
            return true;
        }

        // Explicit permission gate (admin controls this in the role matrix).
        // Still keep business rule: only the creator can resubmit their own lead.
        $can = RbacPermission::can($user, ['lead', 'lead-submissions'], 'update', [
            'lead-submissions.resubmit_lead',
            'lead.resubmit_lead',
        ]);
        if (! $can) {
            return false;
        }

        return (int) $lead->created_by === (int) $user->id;
    }
}
