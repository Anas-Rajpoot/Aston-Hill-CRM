<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;

class SubmissionAccessService
{
    /**
     * Hierarchical access:
     * - created by current user
     * - assigned to current user
     * - related users (manager/team leader -> team members)
     * - same managed/led team
     *
     * @param  object  $record  Eloquent model instance
     * @param  array<int,string>  $extraColumns
     */
    public static function canAccessRecord(User $user, object $record, array $extraColumns = []): bool
    {
        if ($user->hasRole('superadmin')) {
            return true;
        }

        $uid = (int) $user->id;
        $visibleIds = TeamHierarchyService::getVisibleUserIds($user);
        if (! in_array($uid, $visibleIds, true)) {
            $visibleIds[] = $uid;
        }

        $columns = array_values(array_unique(array_merge([
            'created_by',
            'assigned_to',
            'sales_agent_id',
            'team_leader_id',
            'manager_id',
            'csr_id',
            'user_id',
            'executive_id',
            'field_executive_id',
            'back_office_executive_id',
        ], $extraColumns)));

        foreach ($columns as $column) {
            $value = (int) data_get($record, $column, 0);
            if ($value > 0 && in_array($value, $visibleIds, true)) {
                return true;
            }
        }

        $teamId = (int) data_get($record, 'team_id', 0);
        if ($teamId > 0) {
            $isRelatedTeam = Team::where('id', $teamId)
                ->where('status', 'active')
                ->where(function ($q) use ($uid) {
                    $q->where('manager_id', $uid)->orWhere('team_leader_id', $uid);
                })
                ->exists();

            if ($isRelatedTeam) {
                return true;
            }
        }

        return false;
    }
}

