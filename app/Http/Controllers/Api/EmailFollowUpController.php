<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailFollowUp;
use App\Models\SystemAuditLog;
use App\Models\UserColumnPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class EmailFollowUpController extends Controller
{
    private const MODULE = 'email_follow_ups';

    private const ALLOWED_COLUMNS = [
        'id', 'created_by', 'email_date', 'subject', 'category', 'request_from',
        'sent_to', 'comment', 'status', 'status_date',
    ];

    private const BASE_COLUMNS = ['id', 'status'];

    public function __construct()
    {
        $this->middleware(['auth:sanctum']);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EmailFollowUp::class);

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string', Rule::in(array_merge(self::ALLOWED_COLUMNS, ['creator']))],
            'order' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'columns' => ['sometimes', 'array'],
            'columns.*' => ['string', Rule::in(array_merge(self::ALLOWED_COLUMNS, ['creator']))],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(EmailFollowUp::STATUSES)],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'q' => ['sometimes', 'nullable', 'string', 'max:200'],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
        ]);

        $user = $request->user();
        $columns = $this->resolveColumns($user, $validated['columns'] ?? null);
        $perPage = (int) ($validated['per_page'] ?? 15);
        $page = (int) ($validated['page'] ?? 1);
        $sort = $validated['sort'] ?? 'email_date';
        $order = $validated['order'] ?? 'desc';

        $baseQuery = EmailFollowUp::query()->visibleTo($user);
        $this->applyFilters($baseQuery, $validated);
        $total = $baseQuery->count();

        $dataQuery = EmailFollowUp::query()->visibleTo($user);
        $this->applyFilters($dataQuery, $validated);
        $this->applySort($dataQuery, $sort, $order);

        $selectColumns = $this->buildSelectColumns($columns);
        $dataQuery->select($selectColumns);

        $eagerLoad = [];
        if (in_array('creator', $columns, true)) {
            $eagerLoad['creator'] = fn ($q) => $q->select('id', 'name', 'email')->with('roles:id,name');
        }
        if (! empty($eagerLoad)) {
            $dataQuery->with($eagerLoad);
        }

        $offset = ($page - 1) * $perPage;
        $items = $dataQuery->skip($offset)->take($perPage)->get()->map(function ($row) use ($columns) {
            return $this->formatRow($row, $columns);
        });

        $lastPage = $total > 0 ? (int) ceil($total / $perPage) : 1;

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ]);
    }

    private function resolveColumns($user, ?array $requestColumns): array
    {
        $allAllowed = array_merge(self::ALLOWED_COLUMNS, ['creator']);
        if (! empty($requestColumns)) {
            $allowed = array_intersect($requestColumns, $allAllowed);
            return array_values(array_unique(array_merge(self::BASE_COLUMNS, $allowed)));
        }

        $cacheKey = "col_pref_{$user->id}_" . self::MODULE;
        $preference = Cache::remember($cacheKey, 3600, function () use ($user) {
            return UserColumnPreference::where('user_id', $user->id)
                ->where('module', self::MODULE)
                ->first();
        });

        $cols = $preference?->visible_columns ?? config('modules.email_follow_ups.default_columns', []);
        $cols = is_array($cols) ? $cols : [];
        $allowed = array_intersect($cols, $allAllowed);
        return array_values(array_unique(array_merge(self::BASE_COLUMNS, $allowed)));
    }

    private function applyFilters($query, array $validated): void
    {
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['category'])) {
            $query->where('category', $validated['category']);
        }
        if (! empty($validated['from'])) {
            $query->where('email_date', '>=', $validated['from']);
        }
        if (! empty($validated['to'])) {
            $query->where('email_date', '<=', $validated['to']);
        }
        if (! empty($validated['q'])) {
            $term = '%' . addcslashes($validated['q'], '%_\\') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('subject', 'like', $term)
                    ->orWhere('request_from', 'like', $term)
                    ->orWhere('sent_to', 'like', $term)
                    ->orWhere('category', 'like', $term);
            });
        }
    }

    private function applySort($query, string $sort, string $order): void
    {
        $direction = strtolower($order) === 'asc' ? 'asc' : 'desc';
        if ($sort === 'creator') {
            $query->leftJoin('users as creator_users', 'email_follow_ups.created_by', '=', 'creator_users.id')
                ->orderBy('creator_users.name', $direction);
            return;
        }
        if ($sort === 'status_date') {
            $query->orderBy('email_follow_ups.updated_at', $direction);
            return;
        }
        $query->orderBy('email_follow_ups.' . $sort, $direction);
    }

    private function buildSelectColumns(array $columns): array
    {
        $t = 'email_follow_ups';
        $base = ["{$t}.id", "{$t}.status"];
        $map = [
            'created_by' => "{$t}.created_by",
            'creator' => "{$t}.created_by",
            'email_date' => "{$t}.email_date",
            'subject' => "{$t}.subject",
            'category' => "{$t}.category",
            'request_from' => "{$t}.request_from",
            'sent_to' => "{$t}.sent_to",
            'comment' => "{$t}.comment",
            'status_date' => "{$t}.updated_at",
        ];
        foreach ($columns as $col) {
            if ($col === 'id' || $col === 'status') {
                continue;
            }
            if (isset($map[$col])) {
                $base[] = $map[$col];
            }
        }
        $qualified = array_unique($base);
        $aliased = [];
        foreach ($qualified as $q) {
            $aliased[] = strpos($q, '.') !== false ? $q . ' as ' . substr($q, strrpos($q, '.') + 1) : $q;
        }
        return $aliased;
    }

    private function formatRow(EmailFollowUp $row, array $columns): array
    {
        $out = [];
        foreach ($columns as $col) {
            if ($col === 'creator') {
                $out['creator'] = $row->creator?->name ?? null;
                $firstRole = $row->creator?->roles?->first();
                $out['creator_role'] = $firstRole ? str_replace('_', ' ', ucwords($firstRole->name, '_')) : null;
                continue;
            }
            if ($col === 'email_date') {
                $out[$col] = $row->email_date ? $row->email_date->format('d-M-Y') : null;
                continue;
            }
            if ($col === 'status_date') {
                $out[$col] = $row->updated_at ? $row->updated_at->format('d-M-Y') : null;
                continue;
            }
            $out[$col] = $row->$col ?? null;
        }
        return $out;
    }

    public function filters(): JsonResponse
    {
        $this->authorize('viewAny', EmailFollowUp::class);

        $categories = EmailFollowUp::query()
            ->visibleTo(request()->user())
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category')
            ->filter()
            ->sort()
            ->values()
            ->take(50)
            ->all();

        return response()->json([
            'statuses' => array_map(fn ($s) => ['value' => $s, 'label' => ucfirst($s)], EmailFollowUp::STATUSES),
            'categories' => array_values($categories),
        ]);
    }

    public function columns(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EmailFollowUp::class);

        $config = config('modules.email_follow_ups.columns', []);
        $allColumns = [];
        foreach ($config as $key => $def) {
            $allColumns[] = [
                'key' => $key,
                'label' => $def['label'] ?? $key,
            ];
        }

        $pref = UserColumnPreference::where('user_id', $request->user()->id)
            ->where('module', self::MODULE)
            ->first();

        $visible = $pref?->visible_columns ?? config('modules.email_follow_ups.default_columns', []);

        return response()->json([
            'all_columns' => $allColumns,
            'visible_columns' => $visible,
        ]);
    }

    public function saveColumns(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EmailFollowUp::class);

        $allAllowed = array_merge(self::ALLOWED_COLUMNS, ['creator']);
        $data = $request->validate([
            'visible_columns' => ['required', 'array', 'min:1'],
            'visible_columns.*' => ['string', Rule::in($allAllowed)],
        ]);

        UserColumnPreference::updateOrCreate(
            ['user_id' => $request->user()->id, 'module' => self::MODULE],
            ['visible_columns' => $data['visible_columns']]
        );

        Cache::forget("col_pref_{$request->user()->id}_" . self::MODULE);

        return response()->json(['success' => true]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', EmailFollowUp::class);

        $data = $request->validate([
            'email_date' => ['required', 'date'],
            'status' => ['sometimes', 'string', Rule::in(EmailFollowUp::STATUSES)],
            'category' => ['required', 'string', 'max:100'],
            'subject' => ['nullable', 'string', 'max:255'],
            'request_from' => ['nullable', 'string', 'max:190'],
            'sent_to' => ['nullable', 'string', 'max:190'],
            'comment' => ['nullable', 'string'],
        ]);

        $data['created_by'] = $request->user()->id;
        $data['status'] = $data['status'] ?? 'pending';

        $entry = EmailFollowUp::create($data);

        try {
            SystemAuditLog::record(
                'email_follow_up.created',
                null,
                $entry->toArray(),
                $request->user()->id,
                'email_follow_up',
                $entry->id
            );
        } catch (\Exception $e) {
            // Ignore audit logging errors
        }

        return response()->json([
            'id' => $entry->id,
            'message' => 'Email follow-up entry added.',
        ], 201);
    }

    public function patch(Request $request, EmailFollowUp $emailFollowUp): JsonResponse
    {
        $this->authorize('update', $emailFollowUp);

        $data = $request->validate([
            'email_date' => ['sometimes', 'date'],
            'category' => ['sometimes', 'string', 'max:100'],
            'subject' => ['sometimes', 'nullable', 'string', 'max:255'],
            'request_from' => ['sometimes', 'nullable', 'string', 'max:190'],
            'sent_to' => ['sometimes', 'nullable', 'string', 'max:190'],
            'comment' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'string', Rule::in(EmailFollowUp::STATUSES)],
        ]);

        if (! empty($data)) {
            $old = $emailFollowUp->getOriginal();
            $emailFollowUp->update($data);
            $changed = $emailFollowUp->getChanges();

            try {
                SystemAuditLog::record(
                    'email_follow_up.updated',
                    array_intersect_key($old, $changed),
                    $changed,
                    $request->user()->id,
                    'email_follow_up',
                    $emailFollowUp->id
                );
            } catch (\Exception $e) {
                // Ignore audit logging errors
            }
        }

        $emailFollowUp->load('creator:id,name,email');
        $columns = array_merge(
            ['id', 'email_date', 'subject', 'category', 'request_from', 'sent_to', 'creator', 'status'],
            array_keys($data)
        );
        $columns = array_unique($columns);
        $row = $this->formatRow($emailFollowUp, $columns);

        return response()->json([
            'id' => $emailFollowUp->id,
            'message' => 'Updated.',
            'row' => $row,
        ]);
    }

    public function updateStatus(Request $request, EmailFollowUp $emailFollowUp): JsonResponse
    {
        $this->authorize('update', $emailFollowUp);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(EmailFollowUp::STATUSES)],
        ]);

        $old = $emailFollowUp->getOriginal();
        $emailFollowUp->update(['status' => $data['status']]);
        $changed = $emailFollowUp->getChanges();

        try {
            SystemAuditLog::record(
                'email_follow_up.status_updated',
                array_intersect_key($old, $changed),
                $changed,
                $request->user()->id,
                'email_follow_up',
                $emailFollowUp->id
            );
        } catch (\Exception $e) {
            // Ignore audit logging errors
        }

        return response()->json([
            'id' => $emailFollowUp->id,
            'status' => $emailFollowUp->status,
        ]);
    }
}
