<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DropdownOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DropdownSeederController extends Controller
{
    /**
     * GET /api/settings/dropdown-seeder
     * List all groups with their options.
     */
    public function index(Request $request): JsonResponse
    {
        $this->ensureSeededDefaults();

        $query = DropdownOption::query()->ordered();

        if ($request->filled('group')) {
            $query->forGroup($request->input('group'));
        }

        $options = $query->get();

        // Group them by group name
        $grouped = $options->groupBy('group')->map(function ($items, $group) {
            return [
                'group'   => $group,
                'options' => $items->map(fn ($o) => [
                    'id'         => $o->id,
                    'value'      => $o->value,
                    'label'      => $o->label,
                    'sort_order' => $o->sort_order,
                    'is_active'  => $o->is_active,
                ])->values()->toArray(),
            ];
        })->values()->toArray();

        return response()->json([
            'groups'     => DropdownOption::allGroups(),
            'data'       => $grouped,
        ]);
    }

    /**
     * POST /api/settings/dropdown-seeder
     * Create a new dropdown option.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group'      => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/'],
            'value'      => ['required', 'string', 'max:255'],
            'label'      => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active'  => ['sometimes', 'boolean'],
        ]);

        // Ensure unique within group
        $exists = DropdownOption::where('group', $validated['group'])
            ->where('value', $validated['value'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'This value already exists in the group.'], 422);
        }

        $option = DropdownOption::create($validated);
        $this->forgetDependentCaches($validated['group']);

        return response()->json($option, 201);
    }

    /**
     * PUT /api/settings/dropdown-seeder/{id}
     * Update value/label/sort/active. Cascade-renames if value changed.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $option = DropdownOption::findOrFail($id);
        $group = (string) $option->group;

        $validated = $request->validate([
            'value'      => ['sometimes', 'required', 'string', 'max:255'],
            'label'      => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active'  => ['sometimes', 'boolean'],
        ]);

        // Check unique if value is changing
        if (isset($validated['value']) && $validated['value'] !== $option->value) {
            $clash = DropdownOption::where('group', $option->group)
                ->where('value', $validated['value'])
                ->where('id', '!=', $option->id)
                ->exists();

            if ($clash) {
                return response()->json(['message' => 'This value already exists in the group.'], 422);
            }

            // Cascade rename across related tables
            $this->cascadeRename($option->group, $option->value, $validated['value']);
        }

        $option->update($validated);
        $this->forgetDependentCaches($group);

        return response()->json($option);
    }

    /**
     * DELETE /api/settings/dropdown-seeder/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $option = DropdownOption::findOrFail($id);
        $group = (string) $option->group;
        $option->delete();
        $this->forgetDependentCaches($group);

        return response()->json(['message' => 'Deleted.']);
    }

    /**
     * POST /api/settings/dropdown-seeder/bulk-sort
     * Reorder options within a group.
     */
    public function bulkSort(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items'            => ['required', 'array', 'min:1'],
            'items.*.id'       => ['required', 'integer', 'exists:dropdown_options,id'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['items'] as $item) {
                DropdownOption::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
            }
        });
        $groups = DropdownOption::query()
            ->whereIn('id', collect($validated['items'])->pluck('id')->all())
            ->distinct()
            ->pluck('group')
            ->all();
        foreach ($groups as $group) {
            $this->forgetDependentCaches((string) $group);
        }

        return response()->json(['message' => 'Sort order updated.']);
    }

    /* ───── Cascade Rename ───── */

    /**
     * When a dropdown value is renamed, update all records across the CRM
     * that reference the old value. Maps known group names to table.column pairs.
     */
    private function cascadeRename(string $group, string $oldValue, string $newValue): void
    {
        $mappings = $this->groupColumnMappings();

        if (! isset($mappings[$group])) {
            return;
        }

        DB::transaction(function () use ($mappings, $group, $oldValue, $newValue) {
            foreach ($mappings[$group] as [$table, $column]) {
                DB::table($table)
                    ->where($column, $oldValue)
                    ->update([$column => $newValue]);
            }
        });
    }

    /**
     * Return the mapping of dropdown group → [[table, column], ...].
     */
    private function groupColumnMappings(): array
    {
        return [
            'lead_statuses' => [
                ['lead_submissions', 'status'],
            ],
            'field_statuses' => [
                ['field_submissions', 'status'],
            ],
            'field_meeting_statuses' => [
                ['field_submissions', 'field_status'],
            ],
            'customer_support_statuses' => [
                ['customer_support_submissions', 'status'],
            ],
            'vas_statuses' => [
                ['vas_request_submissions', 'status'],
            ],
            'special_request_statuses' => [
                ['special_requests', 'status'],
            ],
            'client_statuses' => [
                ['clients', 'status'],
            ],
            'emirates' => [
                ['lead_submissions', 'emirates'],
                ['field_submissions', 'emirates'],
                ['customer_support_submissions', 'emirates'],
                ['clients', 'emirate'],
            ],
            'service_categories' => [
                ['lead_submissions', 'service_category'],
            ],
            'service_types' => [
                ['lead_submissions', 'service_type'],
            ],
            'product_types' => [
                ['lead_submissions', 'product_type'],
                ['clients', 'product_type'],
            ],
            'contract_types' => [
                ['lead_submissions', 'contract_type'],
            ],
            'company_categories' => [
                ['lead_submissions', 'company_category'],
                ['clients', 'company_category'],
            ],
            'expense_statuses' => [
                ['expenses', 'status'],
            ],
            'team_statuses' => [
                ['teams', 'status'],
            ],
        ];
    }

    /**
     * Ensure core dropdown groups have baseline values so admin can manage from UI immediately.
     */
    private function ensureSeededDefaults(): void
    {
        $defaults = [
            'lead_statuses' => ['unassigned', 'draft', 'submitted', 'approved', 'rejected'],
            'submission_types' => ['new', 'resubmission'],
            'service_categories' => ['Fixed', 'FMS', 'GSM', 'Other'],
            'service_types' => ['New Submission', 'Relocation', 'Update WO', 'Contract Renewal', 'Migration', 'Other'],
            'product_types' => ['Business Ultimate', 'Business Essential', 'Business Complete', 'Simplified', 'Q1', 'BIP'],
            'contract_types' => ['12 months', '24 months'],
            'work_order_statuses' => ['Under Process', 'Appointment Scheduled', 'Completed', 'To be Resubmit', 'Rejected', 'IT Issue'],
            'call_verification_statuses' => ['pending', 'verified', 'rejected'],
            'documents_verification_statuses' => ['pending', 'verified', 'rejected'],
            'du_statuses' => ['pending', 'approved', 'rejected'],

            'field_statuses' => ['unassigned', 'submitted', 'approved', 'rejected'],
            'field_meeting_statuses' => ['scheduled', 'completed', 'cancelled', 'rescheduled'],
            'field_appointment_statuses' => ['scheduled', 'completed', 'cancelled', 'no_show'],

            'customer_support_statuses' => ['open', 'in_progress', 'resolved', 'closed'],
            'ticket_priorities' => ['low', 'medium', 'high'],
            'ticket_categories' => ['billing', 'technical', 'service', 'other'],

            'vas_statuses' => ['unassigned', 'submitted', 'approved', 'rejected'],
            'special_request_statuses' => ['submitted', 'approved', 'rejected'],

            'client_statuses' => ['active', 'inactive', 'pending'],
            'client_categories' => ['new', 'existing', 'vip'],
            'company_categories' => ['SME', 'Enterprise', 'Government', 'Other'],
            'emirates' => ['Dubai', 'Abu Dhabi', 'Sharjah', 'Ajman', 'Fujairah', 'Ras Al Khaimah', 'Umm Al Quwain'],

            'dsp_statuses' => ['pending', 'in_progress', 'completed', 'rejected'],
            'order_statuses' => ['Under Process', 'Completed', 'To be Resubmit', 'Rejected'],
            'verification_statuses' => ['pending', 'verified', 'rejected'],

            'extension_statuses' => ['active', 'inactive', 'not_created'],
            'extension_gateways' => ['DU', 'ETISALAT', 'GSM'],

            'expense_statuses' => ['pending', 'approved', 'rejected'],
            'expense_categories' => ['Stationary', 'water', 'IT', 'telecom', 'nol/taxi', 'others'],

            'team_statuses' => ['active', 'inactive'],
            'user_statuses' => ['approved', 'pending', 'rejected', 'inactive'],
        ];

        foreach ($defaults as $group => $values) {
            $hasAny = DropdownOption::query()->where('group', $group)->exists();
            if ($hasAny) {
                continue;
            }
            foreach (array_values($values) as $idx => $value) {
                DropdownOption::query()->create([
                    'group' => $group,
                    'value' => (string) $value,
                    'label' => (string) $value,
                    'sort_order' => $idx,
                    'is_active' => true,
                ]);
            }
        }
    }

    /**
     * Drop module-level caches that depend on dropdown option groups.
     */
    private function forgetDependentCaches(string $group): void
    {
        if (in_array($group, ['extension_gateways', 'extension_statuses'], true)) {
            Cache::forget('cisco_extensions_filters');
        }
    }
}
