<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemAuditLog;
use App\Models\User;
use App\Models\UserColumnPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class EmployeeApiController extends Controller
{
    private const MODULE = 'employees';

    private const ALLOWED_COLUMNS = [
        'id', 'employee_number', 'name', 'roles', 'team_leader', 'manager', 'department',
        'email', 'phone', 'cnic_number', 'extension', 'status', 'joining_date', 'terminate_date',
    ];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string', Rule::in(array_merge(self::ALLOWED_COLUMNS, ['team_leader_id', 'manager_id']))],
            'order' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'columns' => ['sometimes', 'array'],
            'columns.*' => ['string', Rule::in(self::ALLOWED_COLUMNS)],
            'q' => ['sometimes', 'nullable', 'string', 'max:200'],
            'name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'email' => ['sometimes', 'nullable', 'string', 'max:200'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(['pending', 'approved', 'rejected'])],
            'department' => ['sometimes', 'nullable', 'string', 'max:100'],
            'role' => ['sometimes', 'nullable', 'string', 'max:100'],
            'manager_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'team_leader_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'joining_from' => ['sometimes', 'nullable', 'date'],
            'joining_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:joining_from'],
            'terminate_from' => ['sometimes', 'nullable', 'date'],
            'terminate_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:terminate_from'],
        ]);

        $user = $request->user();
        $columns = $this->resolveColumns($user, $validated['columns'] ?? null);
        $perPage = (int) ($validated['per_page'] ?? 15);
        $page = (int) ($validated['page'] ?? 1);
        $sort = $validated['sort'] ?? 'name';
        $order = $validated['order'] ?? 'asc';

        $query = User::query()
            ->with(['roles:id,name', 'manager:id,name', 'teamLeader:id,name'])
            ->when(! $user->hasRole('superadmin'), fn ($q) => $q->whereDoesntHave('roles', fn ($r) => $r->where('name', 'superadmin')));

        $this->applyFilters($query, $validated);
        $total = $query->count();

        $this->applySort($query, $sort, $order);
        $items = $query->skip(($page - 1) * $perPage)->take($perPage)->get()
            ->map(fn ($row) => $this->formatRow($row, $columns));

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
        $cols = $preference?->visible_columns ?? config('modules.employees.default_columns', []);
        $cols = is_array($cols) ? $cols : [];
        return array_values(array_intersect($cols, self::ALLOWED_COLUMNS));
    }

    private function applyFilters($query, array $validated): void
    {
        if (! empty($validated['q'])) {
            $term = '%' . addcslashes($validated['q'], '%_\\') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('employee_number', 'like', $term)
                    ->orWhere('department', 'like', $term);
            });
        }
        if (! empty($validated['name'])) {
            $term = '%' . addcslashes($validated['name'], '%_\\') . '%';
            $query->where('name', 'like', $term);
        }
        if (! empty($validated['email'])) {
            $term = '%' . addcslashes($validated['email'], '%_\\') . '%';
            $query->where('email', 'like', $term);
        }
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['department'])) {
            $query->where('department', $validated['department']);
        }
        if (! empty($validated['role'])) {
            $query->whereHas('roles', fn ($r) => $r->where('name', $validated['role']));
        }
        if (! empty($validated['manager_id'])) {
            $query->where('manager_id', $validated['manager_id']);
        }
        if (! empty($validated['team_leader_id'])) {
            $query->where('team_leader_id', $validated['team_leader_id']);
        }
        if (! empty($validated['joining_from'])) {
            $query->whereDate('joining_date', '>=', $validated['joining_from']);
        }
        if (! empty($validated['joining_to'])) {
            $query->whereDate('joining_date', '<=', $validated['joining_to']);
        }
        if (! empty($validated['terminate_from'])) {
            $query->whereDate('terminate_date', '>=', $validated['terminate_from']);
        }
        if (! empty($validated['terminate_to'])) {
            $query->whereDate('terminate_date', '<=', $validated['terminate_to']);
        }
    }

    private function applySort($query, string $sort, string $order): void
    {
        $direction = strtolower($order) === 'asc' ? 'asc' : 'desc';
        if (in_array($sort, ['manager', 'team_leader'], true)) {
            $col = $sort === 'manager' ? 'manager_id' : 'team_leader_id';
            $alias = $sort . '_u';
            $query->leftJoin("users as {$alias}", "users.{$col}", '=', "{$alias}.id")
                ->orderBy("{$alias}.name", $direction)
                ->select('users.*');
            return;
        }
        $query->orderBy('users.' . $sort, $direction);
    }

    private function formatRow(User $row, array $columns): array
    {
        $out = [
            'id' => $row->id,
            'employee_number' => $row->employee_number,
            'name' => $row->name,
            'email' => $row->email,
            'phone' => $row->phone,
            'cnic_number' => $row->cnic_number,
            'department' => $row->department,
            'extension' => $row->extension,
            'status' => $row->status,
            'joining_date' => $row->joining_date?->format('Y-m-d'),
            'terminate_date' => $row->terminate_date?->format('Y-m-d'),
            'manager_id' => $row->manager_id,
            'manager' => $row->manager?->name ?? null,
            'team_leader_id' => $row->team_leader_id,
            'team_leader' => $row->teamLeader?->name ?? null,
            'roles' => $row->roles->pluck('name')->values()->all(),
        ];
        return array_intersect_key($out, array_fill_keys(array_merge(['id'], $columns), true));
    }

    public function filters(Request $request): JsonResponse
    {
        $user = $request->user();
        $cacheKey = 'employee_filters_' . $user->id;
        $data = Cache::remember($cacheKey, 600, function () use ($user) {
            $baseQuery = User::query()
                ->when(! $user->hasRole('superadmin'), fn ($q) => $q->whereDoesntHave('roles', fn ($r) => $r->where('name', 'superadmin')));

            $departments = (clone $baseQuery)->whereNotNull('department')->distinct()->pluck('department')->map(fn ($d) => ['value' => $d, 'label' => $d])->values()->all();
            $roles = Role::where('guard_name', 'web')->orderBy('name')->get(['id', 'name'])->unique('name')->values()->map(fn ($r) => [
                'value' => $r->name,
                'label' => self::formatRoleNameForDisplay($r->name),
            ])->all();

            $allManagers = User::query()
                ->where('status', 'approved')
                ->when(! $user->hasRole('superadmin'), fn ($q) => $q->whereDoesntHave('roles', fn ($r) => $r->where('name', 'superadmin')))
                ->orderBy('name')
                ->get(['id', 'name']);

            return [
                'statuses' => [
                    ['value' => 'approved', 'label' => 'Active'],
                    ['value' => 'rejected', 'label' => 'Inactive'],
                    ['value' => 'pending', 'label' => 'Pending Approval'],
                ],
                'departments' => $departments,
                'roles' => $roles,
                'managers' => $allManagers->map(fn ($m) => ['id' => $m->id, 'name' => $m->name])->all(),
                'team_leaders' => $allManagers->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->all(),
            ];
        });

        return response()->json($data);
    }

    public function columns(Request $request): JsonResponse
    {
        $config = config('modules.employees.columns', []);
        $allColumns = [];
        foreach ($config as $key => $def) {
            $allColumns[] = ['key' => $key, 'label' => $def['label'] ?? $key];
        }
        $pref = UserColumnPreference::where('user_id', $request->user()->id)
            ->where('module', self::MODULE)
            ->first();
        $visible = $pref?->visible_columns ?? config('modules.employees.default_columns', []);

        return response()->json([
            'all_columns' => $allColumns,
            'visible_columns' => $visible,
        ]);
    }

    public function saveColumns(Request $request): JsonResponse
    {
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

    public function bulkImport(Request $request): JsonResponse
    {
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
        $created = 0;
        $updated = 0;
        $errors = [];
        foreach (array_slice($rows, 1) as $index => $row) {
            $line = $index + 2;
            $assoc = array_combine($header, array_pad($row, count($header), null));
            if ($assoc === false) {
                $errors[] = "Line {$line}: column count mismatch.";
                continue;
            }
            $email = trim($assoc['email'] ?? $assoc['Email'] ?? '');
            if (! $email) {
                $errors[] = "Line {$line}: email required.";
                continue;
            }
            $name = trim($assoc['name'] ?? $assoc['Name'] ?? '');
            $user = User::where('email', $email)->first();
            $data = [
                'name' => $name ?: ($user?->name ?? ''),
                'email' => $email,
                'employee_number' => trim($assoc['employee_number'] ?? $assoc['Employee ID'] ?? $user?->employee_number ?? ''),
                'department' => trim($assoc['department'] ?? $assoc['Department'] ?? $user?->department ?? '') ?: null,
                'extension' => trim($assoc['extension'] ?? $assoc['Extension'] ?? $user?->extension ?? '') ?: null,
                'joining_date' => $this->parseDate(trim($assoc['joining_date'] ?? $assoc['Joining Date'] ?? '') ?: null),
                'terminate_date' => $this->parseDate(trim($assoc['terminate_date'] ?? $assoc['Terminate Date'] ?? '') ?: null),
            ];
            if ($user) {
                $user->fill($data);
                $user->save();
                $updated++;
            } else {
                $data['password'] = Hash::make(str()->random(12));
                $data['status'] = 'pending';
                User::create($data);
                $created++;
            }
        }

        try {
            SystemAuditLog::record('employee.bulk_imported', null, ['count' => $created], $request->user()->id, 'employee');
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'message' => "Import complete. Created: {$created}, Updated: {$updated}.",
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
        ]);
    }

    private function parseDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        try {
            $d = \Carbon\Carbon::parse($value);
            return $d->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Format role name for display: replace _ with space, capitalize first letter of each word.
     * Same rules as the frontend datatable (formatRoleName).
     */
    private static function formatRoleNameForDisplay(?string $role): string
    {
        if (! $role || ! is_string($role)) {
            return '';
        }
        $words = array_filter(explode('_', $role), fn ($w) => $w !== '');
        $formatted = array_map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)) . mb_strtolower(mb_substr($w, 1)), $words);

        return implode(' ', $formatted);
    }
}
