<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSlaRuleRequest;
use App\Http\Resources\SlaRuleResource;
use App\Models\SlaRule;
use App\Models\SystemAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SlaRuleController extends Controller
{
    /** Editable fields for audit diffing. */
    private const AUDIT_FIELDS = [
        'sla_duration_minutes',
        'warning_threshold_minutes',
        'notification_email',
        'is_active',
    ];

    /**
     * GET /api/sla-rules – list all rules (cached).
     */
    public function index(Request $request): JsonResponse
    {
        $rules = SlaRule::cached();
        $user  = $request->user();
        $canUpdate = $user && ($user->hasRole('superadmin') || $user->can('manage-sla'));

        return response()->json([
            'data' => SlaRuleResource::collection($rules),
            'meta' => [
                'can_update'     => $canUpdate,
                'total'          => $rules->count(),
                'active_count'   => $rules->where('is_active', true)->count(),
                'updated_at_max' => $rules->max('updated_at')?->toIso8601String(),
            ],
        ]);
    }

    /**
     * PATCH /api/sla-rules/{slaRule} – update a single rule.
     */
    public function update(UpdateSlaRuleRequest $request, SlaRule $slaRule): JsonResponse
    {
        $oldValues = collect(self::AUDIT_FIELDS)
            ->mapWithKeys(fn ($f) => [$f => $slaRule->getOriginal($f) ?? $slaRule->{$f}])
            ->toArray();

        $slaRule->fill($request->only(self::AUDIT_FIELDS));
        $slaRule->updated_by = $request->user()->id;
        $slaRule->save();

        SlaRule::clearCache();

        $newValues = collect(self::AUDIT_FIELDS)
            ->mapWithKeys(fn ($f) => [$f => $slaRule->{$f}])
            ->toArray();

        // Log only changed fields
        $changed = array_filter(
            $oldValues,
            fn ($v, $k) => $v != ($newValues[$k] ?? null),
            ARRAY_FILTER_USE_BOTH
        );

        if (! empty($changed)) {
            SystemAuditLog::record(
                'sla_rule.updated',
                $changed,
                array_intersect_key($newValues, $changed),
                $request->user()->id,
                'sla_rule',
                $slaRule->id,
            );
        }

        return response()->json([
            'message' => 'SLA rule updated.',
            'data'    => new SlaRuleResource($slaRule->fresh()),
        ]);
    }

    /**
     * PATCH /api/sla-rules/{slaRule}/toggle – quick active toggle.
     */
    public function toggle(Request $request, SlaRule $slaRule): JsonResponse
    {
        $user = $request->user();
        if (! $user || (! $user->hasRole('superadmin') && ! $user->can('manage-sla'))) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $request->validate(['is_active' => ['required', 'boolean']]);

        $old = $slaRule->is_active;
        $slaRule->is_active  = $request->boolean('is_active');
        $slaRule->updated_by = $user->id;
        $slaRule->save();

        SlaRule::clearCache();

        if ($old !== $slaRule->is_active) {
            SystemAuditLog::record(
                'sla_rule.toggled',
                ['is_active' => $old],
                ['is_active' => $slaRule->is_active],
                $user->id,
                'sla_rule',
                $slaRule->id,
            );
        }

        return response()->json([
            'message' => $slaRule->is_active ? 'SLA rule activated.' : 'SLA rule deactivated.',
            'data'    => new SlaRuleResource($slaRule->fresh()),
        ]);
    }
}
