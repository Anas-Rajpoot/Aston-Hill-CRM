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

            $uid = (int) $user->id;

            // ── Manager: sees whole team work ──
            $managedTeamIds = Team::query()
                ->where('status', 'active')
                ->where('manager_id', $uid)
                ->pluck('id');

            if ($managedTeamIds->isNotEmpty()) {
                $memberIds = User::whereIn('team_id', $managedTeamIds)
                    ->pluck('id')
                    ->toArray();
                $ids = array_merge($ids, $memberIds);

                return array_values(array_unique(array_map('intval', $ids)));
            }

            // ── Team Leader: sees ONLY direct team members (exclude the manager's own work) ──
            $ledTeamIds = Team::query()
                ->where('status', 'active')
                ->where('team_leader_id', $uid)
                ->pluck('id');

            if ($ledTeamIds->isNotEmpty()) {
                $teamManagerIds = Team::query()
                    ->where('status', 'active')
                    ->whereIn('id', $ledTeamIds)
                    ->pluck('manager_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->toArray();

                $memberIds = User::whereIn('team_id', $ledTeamIds)
                    ->pluck('id')
                    ->toArray();

                // Remove manager IDs so TL can't see manager-created work.
                $filteredMemberIds = array_values(array_filter(
                    $memberIds,
                    fn ($id) => ! in_array((int) $id, $teamManagerIds, true)
                ));

                $ids = array_merge($ids, $filteredMemberIds);
                return array_values(array_unique(array_map('intval', $ids)));
            }

            // ── Sales agent / normal user: sees only own records ──
            return [$uid];
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
        $isManagerRank = Team::query()
            ->where('status', 'active')
            ->where('manager_id', $uid)
            ->exists();

        $query->where(function ($q) use ($uid, $visibleUserIds, $extraAssignmentColumns, $isManagerRank) {
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

            // ── 5. Broad team_id visibility only for Managers ──
            // Team Leaders must NOT see the manager's work, so we do not OR by team_id for TL/agents.
            if ($isManagerRank) {
                $teamIds = Team::query()
                    ->where('status', 'active')
                    ->where('manager_id', $uid)
                    ->pluck('id');

                if ($teamIds->isNotEmpty()) {
                    $q->orWhereIn('team_id', $teamIds);
                }
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
