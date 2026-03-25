<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CiscoExtension;
use App\Models\CiscoExtensionAudit;
use App\Models\DropdownOption;
use App\Models\User;
use App\Models\UserColumnPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;

class ExtensionsApiController extends Controller
{
    use \App\Traits\ResolvesAuditDisplayValues;

    private const MODULE = 'cisco_extensions';

    private const ALLOWED_COLUMNS = [
        'id', 'extension', 'landline_number', 'gateway', 'username', 'password',
        'status', 'team_leader', 'manager', 'usage', 'assigned_to_name', 'comment', 'updated_at',
    ];

    public function __construct()
    {
        $this->middleware(['auth:sanctum']);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CiscoExtension::class);

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string', Rule::in(self::ALLOWED_COLUMNS)],
            'order' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'columns' => ['sometimes', 'array'],
            'columns.*' => ['string', Rule::in(self::ALLOWED_COLUMNS)],
            'extension' => ['sometimes', 'nullable', 'string', 'max:100'],
            'landline_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'gateway' => ['sometimes', 'nullable', 'string', 'max:100'],
            'username' => ['sometimes', 'nullable', 'string', 'max:100'],
            'assigned_to_q' => ['sometimes', 'nullable', 'string', 'max:200'],
            'manager_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'team_leader_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'manager_q' => ['sometimes', 'nullable', 'string', 'max:200'],
            'team_leader_q' => ['sometimes', 'nullable', 'string', 'max:200'],
            'status' => ['sometimes', 'nullable', 'array'],
            'status.*' => ['string', Rule::in(CiscoExtension::STATUSES)],
            'usage' => ['sometimes', 'nullable', 'array'],
            'usage.*' => ['string', Rule::in(['assigned', 'unassigned'])],
            'created_from' => ['sometimes', 'nullable', 'date'],
            'created_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:created_from'],
        ]);

        $user = $request->user();
        $columns = $this->resolveColumns($user, $validated['columns'] ?? null);
        $perPage = (int) ($validated['per_page'] ?? 15);
        $page = (int) ($validated['page'] ?? 1);
        $sort = $validated['sort'] ?? config('modules.cisco_extensions.default_sort.0', 'extension');
        $order = $validated['order'] ?? config('modules.cisco_extensions.default_sort.1', 'asc');

        $query = CiscoExtension::query()->with([
            'assignedToUser:id,name,team_leader_id,manager_id',
            'assignedToUser.teamLeader:id,name',
            'assignedToUser.manager:id,name',
            'assignedToUser.roles',
            'teamLeader:id,name',
            'manager:id,name',
        ]);
        $this->applyFilters($query, $validated);

        // Cache count for 30s
        $countCacheKey = 'extensions_count_' . md5(json_encode($validated));
        $total = Cache::remember($countCacheKey, 30, function () use ($query) {
            return (clone $query)->count();
        });

        $this->applySort($query, $sort, $order);
        $canViewPassword = $request->user() && ($request->user()->hasRole('superadmin') || $request->user()->can('extensions.edit'));
        $items = $query->skip(($page - 1) * $perPage)->take($perPage)->get()
            ->map(fn ($row) => $this->formatRow($row, $columns, (bool) $canViewPassword));

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
        if (! empty($requestColumns)) {
            return array_values(array_unique(array_intersect($requestColumns, self::ALLOWED_COLUMNS)));
        }
        $cacheKey = "col_pref_{$user->id}_" . self::MODULE;
        $preference = Cache::remember($cacheKey, 3600, function () use ($user) {
            return UserColumnPreference::where('user_id', $user->id)
                ->where('module', self::MODULE)
                ->first();
        });
        $cols = $preference?->visible_columns ?? config('modules.cisco_extensions.default_columns', []);
        $cols = is_array($cols) ? $cols : [];
        return array_values(array_intersect($cols, self::ALLOWED_COLUMNS));
    }

    private function applyFilters($query, array $validated): void
    {
        if (! empty($validated['extension'])) {
            $term = '%' . addcslashes($validated['extension'], '%_\\') . '%';
            $query->where('extension', 'like', $term);
        }
        if (! empty($validated['landline_number'])) {
            $term = '%' . addcslashes($validated['landline_number'], '%_\\') . '%';
            $query->where('landline_number', 'like', $term);
        }
        if (! empty($validated['gateway'])) {
            $query->where('gateway', $validated['gateway']);
        }
        if (! empty($validated['username'])) {
            $term = '%' . addcslashes($validated['username'], '%_\\') . '%';
            $query->where('username', 'like', $term);
        }
        if (! empty($validated['assigned_to_q'])) {
            $term = '%' . addcslashes($validated['assigned_to_q'], '%_\\') . '%';
            $query->whereHas('assignedToUser', fn ($q) => $q->where('name', 'like', $term));
        }
        if (! empty($validated['manager_id'])) {
            $query->whereHas('assignedToUser.manager', fn ($q) => $q->where('id', (int) $validated['manager_id']));
        }
        if (! empty($validated['team_leader_id'])) {
            $query->whereHas('assignedToUser.teamLeader', fn ($q) => $q->where('id', (int) $validated['team_leader_id']));
        }
        if (! empty($validated['manager_q'])) {
            $term = '%' . addcslashes($validated['manager_q'], '%_\\') . '%';
            $query->whereHas('assignedToUser.manager', fn ($q) => $q->where('name', 'like', $term));
        }
        if (! empty($validated['team_leader_q'])) {
            $term = '%' . addcslashes($validated['team_leader_q'], '%_\\') . '%';
            $query->whereHas('assignedToUser.teamLeader', fn ($q) => $q->where('name', 'like', $term));
        }
        if (! empty($validated['status']) && is_array($validated['status'])) {
            $query->whereIn('status', $validated['status']);
        }
        if (! empty($validated['usage']) && is_array($validated['usage'])) {
            if (in_array('assigned', $validated['usage'], true) && in_array('unassigned', $validated['usage'], true)) {
                // both – no filter
            } elseif (in_array('assigned', $validated['usage'], true)) {
                $query->whereNotNull('assigned_to');
            } elseif (in_array('unassigned', $validated['usage'], true)) {
                $query->whereNull('assigned_to');
            }
        }
        if (! empty($validated['created_from'])) {
            $query->whereDate('created_at', '>=', $validated['created_from']);
        }
        if (! empty($validated['created_to'])) {
            $query->whereDate('created_at', '<=', $validated['created_to']);
        }
    }

    private function applySort($query, string $sort, string $order): void
    {
        $direction = strtolower($order) === 'asc' ? 'asc' : 'desc';
        if (in_array($sort, ['team_leader', 'manager'], true)) {
            $assignedAlias = 'ext_assigned_u';
            $relAlias = $sort === 'manager' ? 'ext_mgr_u' : 'ext_tl_u';
            $query->leftJoin("users as {$assignedAlias}", 'cisco_extensions.assigned_to', '=', "{$assignedAlias}.id")
                ->leftJoin('users as ' . $relAlias, $assignedAlias . '.' . ($sort === 'manager' ? 'manager_id' : 'team_leader_id'), '=', $relAlias . '.id')
                ->orderBy($relAlias . '.name', $direction)
                ->select('cisco_extensions.*');
            return;
        }
        $query->orderBy('cisco_extensions.' . $sort, $direction);
    }

    private function formatRow(CiscoExtension $row, array $columns, bool $canViewPassword = false): array
    {
        $assigned = $row->assignedToUser;
        $teamLeaderName = $row->teamLeader?->name;
        $managerName = $row->manager?->name;
        if ($assigned) {
            if ($assigned->hasRole('manager')) {
                $teamLeaderName = null;
                $managerName = $assigned->name;
            } elseif ($assigned->hasRole('team_leader')) {
                $teamLeaderName = $assigned->name;
                $managerName = $assigned->manager?->name ?? $managerName;
            } else {
                $teamLeaderName = $assigned->teamLeader?->name ?? $teamLeaderName;
                $managerName = $assigned->manager?->name ?? $managerName;
            }
        }

        $rawPassword = (string) ($row->getRawOriginal('password') ?? '');

        $out = [
            'id' => $row->id,
            'extension' => $row->extension,
            'landline_number' => $row->landline_number,
            'gateway' => $row->gateway,
            'username' => $row->username,
            'password' => $row->password ? '********' : null,
            'status' => $row->status,
            'team_leader' => $teamLeaderName,
            'manager' => $managerName,
            'usage' => $row->assigned_to ? 'assigned' : 'unassigned',
            'assigned_to_name' => $assigned?->name ?? null,
            'assigned_to' => $row->assigned_to,
            'manager_id' => $assigned?->manager_id,
            'team_leader_id' => $assigned?->team_leader_id,
            'comment' => $row->comment,
            'updated_at' => $row->updated_at?->format('d-M-Y'),
            'can_view_password' => $canViewPassword,
            'password_view' => $this->resolvePasswordForView($rawPassword, $canViewPassword),
        ];
        $allowed = array_flip(array_merge($columns, ['id', 'assigned_to', 'manager_id', 'team_leader_id', 'can_view_password', 'password_view']));
        return array_intersect_key($out, $allowed);
    }

    /**
     * Summary counts for dashboard cards: total_extensions, assigned, unassigned, active_status.
     * Single query to avoid N+1 and reduce round-trips.
     */
    public function summary(): JsonResponse
    {
        $this->authorize('viewAny', CiscoExtension::class);

        $row = CiscoExtension::query()->selectRaw(
            'COUNT(*) as total, SUM(CASE WHEN assigned_to IS NOT NULL THEN 1 ELSE 0 END) as assigned, SUM(CASE WHEN assigned_to IS NULL THEN 1 ELSE 0 END) as unassigned, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active_status',
            [CiscoExtension::STATUS_ACTIVE]
        )->first();

        return response()->json([
            'total_extensions' => (int) ($row->total ?? 0),
            'assigned' => (int) ($row->assigned ?? 0),
            'unassigned' => (int) ($row->unassigned ?? 0),
            'active_status' => (int) ($row->active_status ?? 0),
        ]);
    }

    public function filters(): JsonResponse
    {
        $this->authorize('viewAny', CiscoExtension::class);

        $data = Cache::remember('cisco_extensions_filters', 600, function () {
            // Use Dropdown Seeder values as the source of truth for options.
            $gateways = DropdownOption::optionsForGroup('extension_gateways');
            $statuses = DropdownOption::optionsForGroup('extension_statuses');

            // Fallback for old databases that do not yet have seeded options.
            if (empty($gateways)) {
                $gateways = CiscoExtension::query()
                    ->whereNotNull('gateway')
                    ->where('gateway', '!=', '')
                    ->distinct()
                    ->pluck('gateway')
                    ->sort()
                    ->values()
                    ->map(fn ($g) => ['value' => $g, 'label' => $g])
                    ->all();
            }
            if (empty($statuses)) {
                $statuses = [
                    ['value' => 'active', 'label' => 'Active'],
                    ['value' => 'inactive', 'label' => 'InActive'],
                    ['value' => 'not_created', 'label' => 'Not Created'],
                ];
            }

            return [
                'gateways' => $gateways,
                'statuses' => $statuses,
                'usage_options' => [
                    ['value' => 'assigned', 'label' => 'Assigned'],
                    ['value' => 'unassigned', 'label' => 'UnAssigned'],
                ],
            ];
        });

        return response()->json($data);
    }

    public function columns(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CiscoExtension::class);

        $config = config('modules.cisco_extensions.columns', []);
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
        $visible = $pref?->visible_columns ?? config('modules.cisco_extensions.default_columns', []);

        return response()->json([
            'all_columns' => $allColumns,
            'visible_columns' => $visible,
        ]);
    }

    public function saveColumns(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CiscoExtension::class);

        $data = $request->validate([
            'visible_columns' => ['required', 'array', 'min:1'],
            'visible_columns.*' => ['string', Rule::in(self::ALLOWED_COLUMNS)],
        ]);

        UserColumnPreference::updateOrCreate(
            ['user_id' => $request->user()->id, 'module' => self::MODULE],
            ['visible_columns' => $data['visible_columns']]
        );
        Cache::forget("col_pref_{$request->user()->id}_" . self::MODULE);

        return response()->json(['success' => true]);
    }

    public function show(Request $request, CiscoExtension $ciscoExtension): JsonResponse
    {
        $this->authorize('view', $ciscoExtension);

        $ciscoExtension->load(['assignedToUser:id,name,email', 'teamLeader:id,name', 'manager:id,name']);
        $canViewPassword = $request->user() && ($request->user()->hasRole('superadmin') || $request->user()->can('extensions.edit'));
        $row = $this->formatRow($ciscoExtension, self::ALLOWED_COLUMNS, (bool) $canViewPassword);
        $row['password'] = $ciscoExtension->password ? '********' : null;
        $row['created_at'] = $ciscoExtension->created_at?->format('Y-m-d');
        $row['updated_at_raw'] = $ciscoExtension->updated_at?->format('Y-m-d');

        return response()->json(['data' => $row]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', CiscoExtension::class);

        $data = $request->validate([
            'extension' => ['required', 'string', 'max:50', 'unique:cisco_extensions,extension'],
            'landline_number' => ['required', 'regex:/^\d{11,12}$/'],
            'gateway' => ['nullable', 'string', 'max:100'],
            'username' => ['nullable', 'string', 'max:100'],
            'password' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(CiscoExtension::STATUSES)],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'team_leader_id' => ['nullable', 'integer', 'exists:users,id'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! empty($data['password'])) {
            $data['password'] = $this->encryptPasswordValue($data['password']);
        }
        $data['status'] = $data['status'] ?? CiscoExtension::STATUS_ACTIVE;

        $ext = CiscoExtension::create($data);
        $this->writeAuditLog($ext, 'created', null, $ext->getAttributes());

        return response()->json([
            'id' => $ext->id,
            'message' => 'Extension created.',
        ], 201);
    }

    public function bulkImport(Request $request): JsonResponse
    {
        $this->authorize('create', CiscoExtension::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();
        $rows = array_map('str_getcsv', file($path));
        if (count($rows) < 2) {
            return response()->json(['message' => 'CSV must have a header row and at least one data row.'], 422);
        }

        $header = array_map('trim', $rows[0]);
        $norm = static function (?string $v): string {
            return strtolower(preg_replace('/[^a-z0-9]+/', '_', trim((string) $v)) ?? '');
        };
        $normalizedHeader = array_map($norm, $header);
        $headerIndex = [];
        foreach ($normalizedHeader as $i => $h) {
            if ($h !== '' && ! isset($headerIndex[$h])) {
                $headerIndex[$h] = $i;
            }
        }
        $getCell = static function (array $row, array $indexMap, array $keys): ?string {
            foreach ($keys as $k) {
                if (isset($indexMap[$k])) {
                    $idx = $indexMap[$k];
                    $raw = $row[$idx] ?? null;
                    if ($raw !== null) {
                        return trim((string) $raw);
                    }
                }
            }
            return null;
        };
        $created = 0;
        $errors = [];

        foreach (array_slice($rows, 1) as $index => $row) {
            $line = $index + 2;
            $padded = array_pad($row, count($header), null);

            $extension = $getCell($padded, $headerIndex, ['extension']);
            $extension = $extension !== null ? trim($extension) : '';
            if (! $extension) {
                $errors[] = "Line {$line}: extension required.";
                continue;
            }

            if (CiscoExtension::where('extension', $extension)->exists()) {
                $errors[] = "Line {$line}: extension {$extension} already exists.";
                continue;
            }

            $landline_number = $getCell($padded, $headerIndex, ['landline_number', 'landline']) ?: null;
            $gateway = $getCell($padded, $headerIndex, ['gateway']) ?: null;
            $username = $getCell($padded, $headerIndex, ['username', 'user_name']) ?: null;
            $password = $getCell($padded, $headerIndex, ['password']) ?: '';
            $status = $getCell($padded, $headerIndex, ['status']) ?: 'active';
            if (! in_array($status, CiscoExtension::STATUSES, true)) {
                $status = CiscoExtension::STATUS_ACTIVE;
            }
            $comment = $getCell($padded, $headerIndex, ['comment', 'remarks']) ?: null;

            $assigned_to = null;
            $assignedRaw = $getCell($padded, $headerIndex, ['assigned_to', 'assigned_to_id', 'assigned_to_name', 'assigned_to_user']);
            if (($assignedRaw ?? '') !== '' && is_numeric($assignedRaw)) {
                $assigned_to = (int) $assignedRaw;
                if (! User::where('id', $assigned_to)->exists()) {
                    $assigned_to = null;
                }
            } elseif (($assignedRaw ?? '') !== '') {
                $u = User::where('name', $assignedRaw)->first();
                if ($u) {
                    $assigned_to = $u->id;
                }
            }

            $data = [
                'extension' => $extension,
                'landline_number' => $landline_number,
                'gateway' => $gateway,
                'username' => $username,
                'status' => $status,
                'assigned_to' => $assigned_to,
                'comment' => $comment,
            ];
            if ($password !== '') {
                $data['password'] = $this->encryptPasswordValue($password);
            }

            try {
                $ext = CiscoExtension::create($data);
                $this->writeAuditLog($ext, 'created', null, $ext->getAttributes());
                $created++;
            } catch (\Throwable $e) {
                $errors[] = "Line {$line}: " . $e->getMessage();
            }
        }

        return response()->json([
            'message' => "Import complete. Created: {$created}." . (count($errors) ? ' Some rows had errors.' : ''),
            'created' => $created,
            'errors' => $errors,
        ]);
    }

    public function update(Request $request, CiscoExtension $ciscoExtension): JsonResponse
    {
        $this->authorize('update', $ciscoExtension);

        $data = $request->validate([
            'extension' => ['sometimes', 'string', 'max:50', Rule::unique('cisco_extensions', 'extension')->ignore($ciscoExtension->id)],
            'landline_number' => ['required', 'regex:/^\d{11,12}$/'],
            'gateway' => ['nullable', 'string', 'max:100'],
            'username' => ['nullable', 'string', 'max:100'],
            'password' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(CiscoExtension::STATUSES)],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'team_leader_id' => ['nullable', 'integer', 'exists:users,id'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $old = $ciscoExtension->only(array_keys($data));
        if (! empty($data['password'])) {
            $data['password'] = $this->encryptPasswordValue($data['password']);
        } else {
            unset($data['password']);
        }

        $ciscoExtension->update($data);
        $this->writeAuditLog($ciscoExtension, 'updated', $old, $ciscoExtension->fresh()->only(array_keys($data)));
        $canViewPassword = $request->user() && ($request->user()->hasRole('superadmin') || $request->user()->can('extensions.edit'));
        return response()->json(['message' => 'Updated.', 'data' => $this->formatRow($ciscoExtension->fresh(['assignedToUser', 'teamLeader', 'manager']), self::ALLOWED_COLUMNS, (bool) $canViewPassword)]);
    }

    public function patch(Request $request, CiscoExtension $ciscoExtension): JsonResponse
    {
        $this->authorize('update', $ciscoExtension);

        $data = $request->validate([
            'extension' => ['sometimes', 'string', 'max:50', Rule::unique('cisco_extensions', 'extension')->ignore($ciscoExtension->id)],
            'landline_number' => ['sometimes', 'nullable', 'regex:/^\d{11,12}$/'],
            'gateway' => ['sometimes', 'nullable', 'string', 'max:100'],
            'username' => ['sometimes', 'nullable', 'string', 'max:100'],
            'password' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(CiscoExtension::STATUSES)],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'team_leader_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'manager_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'comment' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $relationsForFormat = ['assignedToUser.teamLeader', 'assignedToUser.manager', 'assignedToUser.roles', 'teamLeader', 'manager'];
        if (empty($data)) {
            return response()->json(['message' => 'No changes.', 'data' => $this->formatRow($ciscoExtension->fresh($relationsForFormat), self::ALLOWED_COLUMNS)]);
        }

        $old = $ciscoExtension->only(array_keys($data));
        if (! empty($data['password'])) {
            $data['password'] = $this->encryptPasswordValue($data['password']);
        } else {
            unset($data['password']);
        }

        $ciscoExtension->update($data);
        $this->writeAuditLog($ciscoExtension, 'updated', $old, $ciscoExtension->fresh()->only(array_keys($data)));
        $canViewPassword = $request->user() && ($request->user()->hasRole('superadmin') || $request->user()->can('extensions.edit'));
        return response()->json(['message' => 'Updated.', 'data' => $this->formatRow($ciscoExtension->fresh($relationsForFormat), self::ALLOWED_COLUMNS, (bool) $canViewPassword)]);
    }

    private function encryptPasswordValue(?string $plain): ?string
    {
        $value = trim((string) ($plain ?? ''));
        if ($value === '') return null;
        return Crypt::encryptString($value);
    }

    private function resolvePasswordForView(?string $storedValue, bool $canViewPassword): ?string
    {
        if (! $canViewPassword) return null;
        $value = trim((string) ($storedValue ?? ''));
        if ($value === '') return null;
        if ($this->isLegacyHashedPassword($value)) return null;
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            // Backward compatibility: historically plain text may exist.
            return $value;
        }
    }

    private function isLegacyHashedPassword(string $value): bool
    {
        return str_starts_with($value, '$2y$')
            || str_starts_with($value, '$2a$')
            || str_starts_with($value, '$2b$')
            || str_starts_with($value, '$argon2i$')
            || str_starts_with($value, '$argon2id$');
    }

    private function writeAuditLog(CiscoExtension $ext, string $action, ?array $oldValues, ?array $newValues): void
    {
        $mask = function (?array $arr) {
            if ($arr === null) return null;
            if (isset($arr['password'])) $arr['password'] = '********';
            return $arr;
        };
        CiscoExtensionAudit::create([
            'cisco_extension_id' => $ext->id,
            'user_id' => request()->user()?->id,
            'action' => $action,
            'old_values' => $mask($oldValues),
            'new_values' => $mask($newValues),
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
        ]);
    }

    public function destroy(CiscoExtension $ciscoExtension): JsonResponse
    {
        $this->authorize('delete', $ciscoExtension);

        $old = $ciscoExtension->toArray();
        unset($old['password']);
        $this->writeAuditLog($ciscoExtension, 'deleted', $old, null);
        $ciscoExtension->delete();

        return response()->json(['message' => 'Extension deleted.']);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:cisco_extensions,id'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $request->input('ids', []))));
        $extensions = CiscoExtension::query()->whereIn('id', $ids)->get();
        if ($extensions->isEmpty()) {
            return response()->json(['message' => 'No valid extensions selected.'], 422);
        }

        foreach ($extensions as $ext) {
            $this->authorize('delete', $ext);
        }

        $deleted = 0;
        foreach ($extensions as $ext) {
            $old = $ext->toArray();
            unset($old['password']);
            $this->writeAuditLog($ext, 'deleted', $old, null);
            $ext->delete();
            $deleted++;
        }

        return response()->json([
            'message' => "Deleted {$deleted} extension(s).",
            'deleted' => $deleted,
        ]);
    }

    public function bulkStatusUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:cisco_extensions,id'],
            'status' => ['required', 'string', Rule::in(CiscoExtension::STATUSES)],
        ]);

        $ids = array_values(array_unique(array_map('intval', $validated['ids'])));
        $targetStatus = $validated['status'];
        $extensions = CiscoExtension::query()->whereIn('id', $ids)->get();
        if ($extensions->isEmpty()) {
            return response()->json(['message' => 'No valid extensions selected.'], 422);
        }

        foreach ($extensions as $ext) {
            $this->authorize('update', $ext);
        }

        $updated = 0;
        foreach ($extensions as $ext) {
            if ($ext->status === $targetStatus) {
                continue;
            }
            $old = ['status' => $ext->status];
            $ext->update(['status' => $targetStatus]);
            $this->writeAuditLog($ext, 'updated', $old, ['status' => $targetStatus]);
            $updated++;
        }

        return response()->json([
            'message' => "Status updated for {$updated} extension(s).",
            'updated' => $updated,
        ]);
    }

    public function auditLog(CiscoExtension $ciscoExtension): JsonResponse
    {
        $this->authorize('view', $ciscoExtension);

        $audits = $ciscoExtension->audits()
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(function ($a) {
                return [
                    'id' => $a->id,
                    'action' => $a->action,
                    'old_values' => $a->old_values,
                    'new_values' => $a->new_values,
                    'user_name' => $a->user?->name ?? 'System',
                    'created_at' => $a->created_at?->toIso8601String(),
                ];
            });

        $audits = $this->resolveObjectBasedAuditValues($audits);

        return response()->json(['data' => $audits]);
    }

    public function assignableEmployees(): JsonResponse
    {
        $this->authorize('viewAny', CiscoExtension::class);

        $users = User::query()
            ->where('status', 'approved')
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'label' => $u->name]);

        $managerOptions = User::query()
            ->where('status', 'approved')
            ->whereHas('roles', fn ($q) => $q->where('name', 'manager'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'label' => $u->name]);

        $teamLeaderOptions = User::query()
            ->where('status', 'approved')
            ->whereHas('roles', fn ($q) => $q->where('name', 'team_leader'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'label' => $u->name]);

        return response()->json([
            'data' => $users,
            'manager_options' => $managerOptions,
            'team_leader_options' => $teamLeaderOptions,
        ]);
    }
}
