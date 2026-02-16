<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAnnouncementRequest;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use App\Models\AnnouncementAcknowledgement;
use App\Models\SystemAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    private function canManage($user): bool
    {
        return $user && ($user->hasRole('superadmin') || $user->can('manage-announcements'));
    }

    /* ── GET /api/announcements ──────────────────────── */
    public function index(Request $request): JsonResponse
    {
        // Show ALL announcements by default (including disabled/archived)
        $query = Announcement::with('creator');

        if ($q = $request->input('q')) {
            $query->where(fn ($w) => $w->where('title', 'like', "%{$q}%")->orWhere('body', 'like', "%{$q}%"));
        }
        if ($status = $request->input('status')) {
            match ($status) {
                'active'    => $query->active(),
                'scheduled' => $query->scheduled(),
                'expired'   => $query->expired(),
                'disabled'  => $query->archived(),
                'archived'  => $query->archived(),
                default     => null,
            };
        }
        if ($priority = $request->input('priority')) {
            $query->where('priority', $priority);
        }
        if ($request->input('visibility') === 'all_users') {
            $query->where('all_users', true);
        }
        if ($from = $request->input('date_from')) {
            $query->whereDate('published_at', '>=', $from);
        }
        if ($to = $request->input('date_to')) {
            $query->whereDate('published_at', '<=', $to);
        }

        // Sort
        $sortField = 'published_at';
        $sortDir   = 'desc';
        if ($sort = $request->input('sort')) {
            $parts     = explode(':', $sort);
            $allowed   = ['title', 'type', 'published_at', 'expire_at', 'priority', 'status', 'created_at'];
            $sortField = in_array($parts[0], $allowed) ? $parts[0] : 'published_at';
            $sortDir   = ($parts[1] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        }
        $query->orderBy($sortField, $sortDir);

        $perPage   = min((int) $request->input('per_page', 10), 100);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => AnnouncementResource::collection($paginated->items()),
            'meta' => [
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'from'         => $paginated->firstItem(),
                'to'           => $paginated->lastItem(),
                'counters'     => Announcement::counters(),
                'can_update'   => $this->canManage($request->user()),
            ],
        ]);
    }

    /* ── GET /api/announcements/{announcement} ───────── */
    public function show(Announcement $announcement): JsonResponse
    {
        $announcement->load(['creator', 'acknowledgements.user']);
        $announcement->loadCount('acknowledgements');

        $totalUsers = \App\Models\User::count();

        $resource = (new AnnouncementResource($announcement))->toArray(request());
        $resource['total_users']     = $totalUsers;
        $resource['acknowledged_by'] = $announcement->acknowledgements
            ->map(fn ($ack) => [
                'user_id'   => $ack->user_id,
                'name'      => $ack->user?->name ?? 'Unknown',
                'acked_at'  => $ack->acknowledged_at?->toIso8601String(),
            ])
            ->values()
            ->toArray();

        return response()->json(['data' => $resource]);
    }

    /* ── POST /api/announcements ─────────────────────── */
    public function store(StoreAnnouncementRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $data['channels']   = $data['channels'] ?? ['web'];

        $ann = Announcement::create($data);
        Announcement::clearCache();

        SystemAuditLog::record('announcement.created', null, $data, $request->user()->id, 'announcement', $ann->id);

        return response()->json([
            'message' => 'Announcement created.',
            'data'    => new AnnouncementResource($ann->load('creator')),
        ], 201);
    }

    /* ── PUT /api/announcements/{announcement} ───────── */
    public function update(StoreAnnouncementRequest $request, Announcement $announcement): JsonResponse
    {
        $data = $request->validated();
        $old  = $announcement->only(array_keys($data));
        $data['updated_by'] = $request->user()->id;

        $announcement->update($data);
        Announcement::clearCache();

        $changed = array_filter($old, fn ($v, $k) => json_encode($v) !== json_encode($data[$k] ?? null), ARRAY_FILTER_USE_BOTH);
        if (! empty($changed)) {
            SystemAuditLog::record('announcement.updated', $changed, array_intersect_key($data, $changed), $request->user()->id, 'announcement', $announcement->id);
        }

        return response()->json([
            'message' => 'Announcement updated.',
            'data'    => new AnnouncementResource($announcement->fresh()->load('creator')),
        ]);
    }

    /* ── PATCH /api/announcements/{announcement} ─ inline field update ── */
    public function patchField(Request $request, Announcement $announcement): JsonResponse
    {
        if (! $this->canManage($request->user())) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $allowed = ['title', 'type', 'priority', 'published_at', 'expire_at'];
        $validated = $request->validate(
            collect($allowed)
                ->filter(fn ($f) => $request->has($f))
                ->mapWithKeys(fn ($f) => [$f => match ($f) {
                    'title'        => 'required|string|max:255',
                    'type'         => 'required|string|in:' . implode(',', Announcement::TYPES),
                    'priority'     => 'required|string|in:' . implode(',', Announcement::PRIORITIES),
                    'published_at' => 'required|date',
                    'expire_at'    => 'nullable|date',
                    default        => 'nullable|string|max:255',
                }])
                ->toArray()
        );

        if (empty($validated)) {
            return response()->json(['message' => 'No valid fields to update.'], 422);
        }

        $old = $announcement->only(array_keys($validated));
        $announcement->update(array_merge($validated, ['updated_by' => $request->user()->id]));
        Announcement::clearCache();

        $changed = array_filter($old, fn ($v, $k) => json_encode($v) !== json_encode($validated[$k] ?? null), ARRAY_FILTER_USE_BOTH);
        if (! empty($changed)) {
            SystemAuditLog::record('announcement.field_updated', $changed, array_intersect_key($validated, $changed), $request->user()->id, 'announcement', $announcement->id);
        }

        return response()->json([
            'message' => 'Announcement updated.',
            'data'    => new AnnouncementResource($announcement->fresh()->load('creator')),
        ]);
    }

    /* ── PATCH /api/announcements/{id}/publish-now ──── */
    public function publishNow(Request $request, Announcement $announcement): JsonResponse
    {
        if (! $this->canManage($request->user())) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $announcement->update(['published_at' => now(), 'updated_by' => $request->user()->id]);
        Announcement::clearCache();

        SystemAuditLog::record('announcement.published', ['published_at' => $announcement->getOriginal('published_at')], ['published_at' => now()->toIso8601String()], $request->user()->id, 'announcement', $announcement->id);

        return response()->json(['message' => 'Announcement published.', 'data' => new AnnouncementResource($announcement->fresh()->load('creator'))]);
    }

    /* ── POST /api/announcements/{id}/duplicate ──────── */
    public function duplicate(Request $request, Announcement $announcement): JsonResponse
    {
        if (! $this->canManage($request->user())) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $clone = $announcement->replicate(['archived_at']);
        $clone->title        = $announcement->title . ' (Copy)';
        $clone->published_at = now()->addDay();
        $clone->expire_at    = null;
        $clone->archived_at  = null;
        $clone->created_by   = $request->user()->id;
        $clone->updated_by   = null;
        $clone->save();

        Announcement::clearCache();
        SystemAuditLog::record('announcement.duplicated', null, ['source_id' => $announcement->id, 'new_id' => $clone->id], $request->user()->id, 'announcement', $clone->id);

        return response()->json(['message' => 'Announcement duplicated.', 'data' => new AnnouncementResource($clone->load('creator'))], 201);
    }

    /* ── DELETE /api/announcements/{id} (soft archive) ── */
    public function destroy(Request $request, Announcement $announcement): JsonResponse
    {
        if (! $this->canManage($request->user())) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $announcement->update(['archived_at' => now(), 'updated_by' => $request->user()->id]);
        Announcement::clearCache();

        SystemAuditLog::record('announcement.archived', null, ['archived_at' => now()->toIso8601String()], $request->user()->id, 'announcement', $announcement->id);

        return response()->json(['message' => 'Announcement archived.']);
    }

    /* ── DELETE /api/announcements/{id}/permanent ───── */
    public function forceDelete(Request $request, Announcement $announcement): JsonResponse
    {
        if (! $this->canManage($request->user())) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $title = $announcement->title;
        SystemAuditLog::record('announcement.deleted', ['title' => $title, 'id' => $announcement->id], null, $request->user()->id, 'announcement', $announcement->id);

        $announcement->acknowledgements()->delete();
        $announcement->delete();
        Announcement::clearCache();

        return response()->json(['message' => 'Announcement permanently deleted.']);
    }

    /* ── POST /api/announcements/{id}/acknowledge ──── */
    public function acknowledge(Request $request, Announcement $announcement): JsonResponse
    {
        if (! $announcement->require_ack) {
            return response()->json(['message' => 'Acknowledgement not required.'], 422);
        }

        AnnouncementAcknowledgement::firstOrCreate(
            ['announcement_id' => $announcement->id, 'user_id' => $request->user()->id],
            ['acknowledged_at' => now()]
        );

        return response()->json(['message' => 'Acknowledged.']);
    }
}
