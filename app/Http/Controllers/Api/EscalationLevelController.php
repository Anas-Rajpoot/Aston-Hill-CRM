<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EscalationLevel;
use App\Models\SystemAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * CRUD + reorder for SLA escalation levels.
 *
 * Gate: manage-escalation-levels (super_admin or explicit permission).
 * All authenticated users can read (GET).
 *
 * Recipient Type now stores a Spatie role slug.
 * Custom Email is the email address column (editable by double-click).
 */
class EscalationLevelController extends Controller
{
    private function canManage($user): bool
    {
        return $user && ($user->hasRole('superadmin') || $user->can('manage-escalation-levels'));
    }

    // ──────────────────────────────────────────────────────────
    //  GET /api/escalation-levels
    // ──────────────────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $levels = EscalationLevel::cached();

        $data = $levels->map(fn ($lvl) => $this->formatLevel($lvl));

        // Fetch all system roles for the Recipient Type dropdown
        $roles = Role::orderBy('name')->get()->map(fn ($r) => [
            'value' => $r->name,
            'label' => ucwords(str_replace('_', ' ', $r->name)),
        ]);

        return response()->json([
            'data' => $data,
            'meta' => [
                'can_update' => $this->canManage($request->user()),
                'roles'      => $roles,
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────────
    //  POST /api/escalation-levels — create a new level
    // ──────────────────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        if (! $this->canManage($request->user())) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate($this->rules());

        // Auto-assign next level number
        if (empty($validated['level'])) {
            $validated['level'] = (EscalationLevel::max('level') ?? 0) + 1;
        }

        $level = EscalationLevel::create($validated);
        EscalationLevel::clearCache();

        SystemAuditLog::record(
            'escalation_level.created',
            [],
            $level->toArray(),
            $request->user()->id,
            'escalation_level',
            $level->id,
        );

        return response()->json([
            'message' => 'Escalation level created.',
            'data'    => $this->formatLevel($level->fresh()),
        ], 201);
    }

    // ──────────────────────────────────────────────────────────
    //  PUT /api/escalation-levels/{escalationLevel} — update
    // ──────────────────────────────────────────────────────────
    public function update(Request $request, EscalationLevel $escalationLevel): JsonResponse
    {
        if (! $this->canManage($request->user())) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate($this->rules($escalationLevel->id));

        $old = $escalationLevel->toArray();
        $escalationLevel->update($validated);
        EscalationLevel::clearCache();

        $changed = array_filter($old, fn ($v, $k) =>
            array_key_exists($k, $validated) && json_encode($v) !== json_encode($validated[$k]),
            ARRAY_FILTER_USE_BOTH
        );

        if (! empty($changed)) {
            SystemAuditLog::record(
                'escalation_level.updated',
                $changed,
                array_intersect_key($validated, $changed),
                $request->user()->id,
                'escalation_level',
                $escalationLevel->id,
            );
        }

        return response()->json([
            'message' => 'Escalation level updated.',
            'data'    => $this->formatLevel($escalationLevel->fresh()),
        ]);
    }

    // ──────────────────────────────────────────────────────────
    //  DELETE /api/escalation-levels/{escalationLevel}
    // ──────────────────────────────────────────────────────────
    public function destroy(Request $request, EscalationLevel $escalationLevel): JsonResponse
    {
        if (! $this->canManage($request->user())) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $old = $escalationLevel->toArray();
        $escalationLevel->delete();
        EscalationLevel::clearCache();

        // Recalculate level numbers to stay sequential
        $this->resequenceLevels();

        SystemAuditLog::record(
            'escalation_level.deleted',
            $old,
            [],
            $request->user()->id,
            'escalation_level',
            $old['id'],
        );

        return response()->json(['message' => 'Escalation level removed.']);
    }

    // ──────────────────────────────────────────────────────────
    //  PUT /api/escalation-levels/reorder — reorder levels
    // ──────────────────────────────────────────────────────────
    public function reorder(Request $request): JsonResponse
    {
        if (! $this->canManage($request->user())) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'order'   => 'required|array|min:1',
            'order.*' => 'required|integer|exists:escalation_levels,id',
        ]);

        $old = EscalationLevel::orderBy('level')->pluck('id')->toArray();

        DB::transaction(function () use ($validated) {
            EscalationLevel::query()->update(['level' => DB::raw('level + 1000')]);

            foreach ($validated['order'] as $idx => $id) {
                EscalationLevel::where('id', $id)->update(['level' => $idx + 1]);
            }
        });

        EscalationLevel::clearCache();

        SystemAuditLog::record(
            'escalation_levels.reordered',
            ['order' => $old],
            ['order' => $validated['order']],
            $request->user()->id,
            'escalation_level',
            null,
        );

        return response()->json([
            'message' => 'Escalation levels reordered.',
            'data'    => EscalationLevel::orderBy('level')
                ->get()
                ->map(fn ($l) => $this->formatLevel($l)),
        ]);
    }

    // ──────────────────────────────────────────────────────────
    //  POST /api/escalation-levels/reset — reset to defaults
    // ──────────────────────────────────────────────────────────
    public function reset(Request $request): JsonResponse
    {
        if (! $this->canManage($request->user())) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $old = EscalationLevel::orderBy('level')->get()->toArray();

        DB::transaction(function () {
            EscalationLevel::query()->delete();

            foreach (EscalationLevel::defaults() as $row) {
                EscalationLevel::create($row);
            }
        });

        EscalationLevel::clearCache();

        SystemAuditLog::record(
            'escalation_levels.reset',
            ['levels' => $old],
            ['levels' => EscalationLevel::defaults()],
            $request->user()->id,
            'escalation_level',
            null,
        );

        return response()->json([
            'message' => 'Escalation levels reset to defaults.',
            'data'    => EscalationLevel::orderBy('level')
                ->get()
                ->map(fn ($l) => $this->formatLevel($l)),
        ]);
    }

    // ──────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────

    private function rules(?int $ignoreId = null): array
    {
        // recipient_type must be a valid role slug from the roles table
        $roleNames = Role::pluck('name')->toArray();

        return [
            'level'          => ['sometimes', 'integer', 'min:1', Rule::unique('escalation_levels', 'level')->ignore($ignoreId)],
            'recipient_type' => ['required', 'string', Rule::in($roleNames)],
            'custom_email'   => ['nullable', 'email', 'max:255'],
            'is_active'      => ['sometimes', 'boolean'],
        ];
    }

    private function formatLevel(EscalationLevel $level): array
    {
        return [
            'id'              => $level->id,
            'level'           => $level->level,
            'recipient_type'  => $level->recipient_type,
            'custom_email'    => $level->custom_email,
            'is_active'       => $level->is_active,
            'recipient_label' => $level->recipient_label,
            'created_at'      => $level->created_at,
            'updated_at'      => $level->updated_at,
        ];
    }

    private function resequenceLevels(): void
    {
        $levels = EscalationLevel::orderBy('level')->get();
        DB::transaction(function () use ($levels) {
            EscalationLevel::query()->update(['level' => DB::raw('level + 1000')]);
            $seq = 1;
            foreach ($levels as $level) {
                $level->update(['level' => $seq++]);
            }
        });
        EscalationLevel::clearCache();
    }
}
