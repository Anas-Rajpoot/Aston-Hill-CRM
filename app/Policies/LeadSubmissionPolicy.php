<?php

namespace App\Policies;

use App\Models\LeadSubmission;
use App\Models\User;
use App\Services\SubmissionAccessService;
use App\Support\RbacPermission;

class LeadSubmissionPolicy
{
    /** Requires read permission on lead module. */
    public function viewAny(User $user): bool
    {
        return RbacPermission::can($user, ['lead', 'lead-submissions'], 'read', [
            'lead.view',
            'lead.view.all',
            'lead.view.assigned',
            'lead.view.created',
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

        if ($user->can('lead.view.all')) {
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
