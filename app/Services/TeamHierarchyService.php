<?php

namespace App\Services;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves team hierarchy for RBAC scoping on submissions.
 *
 * Access Control Matrix:
 *  ┌────────────────────────┬──────────────────────────────────────────────────────┐
 *  │ Role                   │ Sees                                                 │
 *  ├────────────────────────┼──────────────────────────────────────────────────────┤
 *  │ Super Admin            │ ALL submissions of ALL types                         │
 *  │ Manager                │ Own + all team members' submissions                  │
 *  │                        │ (teams where teams.manager_id = user.id)             │
 *  │ Team Leader            │ Own + direct team members' submissions               │
 *  │                        │ (teams where teams.team_leader_id = user.id)         │
 *  │ Backoffice/CSR/Agent   │ ONLY assigned to them OR created by them             │
 *  └────────────────────────┴──────────────────────────────────────────────────────┘
 *
 * The same rules apply to both Listing Pages AND Reports.
 */
class TeamHierarchyService
{
    /**
     * Get all user IDs visible to the given user based on team hierarchy.
     *
     * Returns empty array for super admin (caller must skip filtering).
     * Returns [$userId] for regular agents (no team hierarchy).
     * Returns [$userId, ...memberIds] for managers/leaders.
     *
     * @return int[]
     */
    public static function getVisibleUserIds(User $user): array
    {
        if ($user->hasRole('superadmin')) {
            return []; // empty = all records (caller MUST skip filtering)
        }

        $cacheKey = "team_hierarchy_user_{$user->id}";

        return Cache::remember($cacheKey, 300, function () use ($user) {
            $ids = [$user->id];

            // ── Manager role: sees all members of teams they manage ──
            $managedTeamIds = Team::where('manager_id', $user->id)
                ->where('status', 'active')
                ->pluck('id');

            if ($managedTeamIds->isNotEmpty()) {
                // All users assigned to these teams
                $memberIds = User::whereIn('team_id', $managedTeamIds)
                    ->pluck('id')
                    ->toArray();
                $ids = array_merge($ids, $memberIds);

                // Team leaders of those teams (they may not have team_id set)
                $leaderIds = Team::whereIn('id', $managedTeamIds)
                    ->whereNotNull('team_leader_id')
                    ->pluck('team_leader_id')
                    ->toArray();
                $ids = array_merge($ids, $leaderIds);
            }

            // ── Team Leader role: sees all members of teams they lead ──
            $ledTeamIds = Team::where('team_leader_id', $user->id)
                ->where('status', 'active')
                ->pluck('id');

            if ($ledTeamIds->isNotEmpty()) {
                $memberIds = User::whereIn('team_id', $ledTeamIds)
                    ->pluck('id')
                    ->toArray();
                $ids = array_merge($ids, $memberIds);
            }

            return array_values(array_unique($ids));
        });
    }

    /**
     * Check if the user is a super admin.
     */
    public static function isSuperAdmin(User $user): bool
    {
        return $user->hasRole('superadmin');
    }

    /**
     * Apply team-based visibility scope to a submission query.
     *
     * This is the single, authoritative RBAC method for all 4 submission types.
     * It implements the access control matrix documented above.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  User  $user
     * @param  array  $extraAssignmentColumns  Module-specific assignment columns
     *         e.g. ['executive_id'] for leads, ['field_executive_id'] for field,
     *              ['back_office_executive_id'] for VAS
     */
    public static function scopeSubmissionsForUser($query, User $user, array $extraAssignmentColumns = []): void
    {
        // Super Admin → sees everything
        if (self::isSuperAdmin($user)) {
            return;
        }

        $uid = $user->id;
        $visibleUserIds = self::getVisibleUserIds($user);

        $query->where(function ($q) use ($uid, $visibleUserIds, $extraAssignmentColumns) {
            // ── 1. Records created by this user ──
            $q->where('created_by', $uid);

            // ── 2. Records assigned to this user (via standard hierarchy columns) ──
            $q->orWhere('sales_agent_id', $uid)
              ->orWhere('team_leader_id', $uid)
              ->orWhere('manager_id', $uid);

            // ── 3. Records assigned via module-specific columns ──
            foreach ($extraAssignmentColumns as $col) {
                $q->orWhere($col, $uid);
            }

            // ── 4. Team hierarchy: records from team members (Manager/Leader only) ──
            // For regular agents, $visibleUserIds is just [$uid], so this clause
            // is redundant but harmless. For managers/leaders it adds their members.
            if (count($visibleUserIds) > 1) {
                $q->orWhereIn('created_by', $visibleUserIds)
                  ->orWhereIn('sales_agent_id', $visibleUserIds);
            }

            // ── 5. Records belonging to teams this user manages/leads ──
            $teamIds = Team::where('manager_id', $uid)
                ->orWhere('team_leader_id', $uid)
                ->pluck('id');

            if ($teamIds->isNotEmpty()) {
                $q->orWhereIn('team_id', $teamIds);
            }
        });
    }

    /**
     * Flush the hierarchy cache for a specific user.
     */
    public static function flushCache(?int $userId = null): void
    {
        if ($userId) {
            Cache::forget("team_hierarchy_user_{$userId}");
        }
    }

    /**
     * Flush all hierarchy caches (e.g., after team membership changes).
     */
    public static function flushAllCaches(): void
    {
        $userIds = User::whereNotNull('team_id')->pluck('id');
        $managerIds = Team::whereNotNull('manager_id')->pluck('manager_id');
        $leaderIds = Team::whereNotNull('team_leader_id')->pluck('team_leader_id');

        $allIds = $userIds->merge($managerIds)->merge($leaderIds)->unique();

        foreach ($allIds as $id) {
            Cache::forget("team_hierarchy_user_{$id}");
        }
    }
}
