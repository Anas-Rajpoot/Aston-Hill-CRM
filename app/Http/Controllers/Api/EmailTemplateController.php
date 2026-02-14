<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Models\NotificationTrigger;
use App\Models\SystemAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailTemplateController extends Controller
{
    private function canManage($user): bool
    {
        return $user && (
            $user->hasRole('superadmin')
            || $user->can('notification_rules.manage_templates')
            || $user->can('manage-notification-rules')
        );
    }

    private function canDelete($user): bool
    {
        return $user && (
            $user->hasRole('superadmin')
            || $user->can('notification_rules.delete')
            || $user->can('manage-notification-rules')
        );
    }

    /**
     * GET /api/email-templates
     *
     * Return all templates. Also ensure every notification trigger has a template.
     */
    public function index(Request $request): JsonResponse
    {
        // Auto-create templates for any trigger that doesn't have one yet
        $this->ensureTemplatesForAllTriggers();

        $templates = EmailTemplate::orderBy('id')->get([
            'id', 'trigger_key', 'name', 'subject', 'body', 'available_variables', 'updated_at',
        ]);

        // Also return available triggers for the "Add Template" form
        $triggers = NotificationTrigger::orderBy('id')
            ->get(['id', 'key', 'name'])
            ->map(fn ($t) => ['key' => $t->key, 'name' => $t->name]);

        return response()->json([
            'data' => $templates,
            'meta' => [
                'can_update' => $this->canManage($request->user()),
                'triggers'   => $triggers,
            ],
        ]);
    }

    /**
     * GET /api/email-templates/{emailTemplate}
     */
    public function show(EmailTemplate $emailTemplate): JsonResponse
    {
        return response()->json(['data' => $emailTemplate]);
    }

    /**
     * POST /api/email-templates — create a new template
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->canManage($user)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'trigger_key'         => 'required|string|max:80',
            'name'                => 'required|string|max:200',
            'subject'             => 'required|string|max:255',
            'body'                => 'required|string|min:10',
            'available_variables' => 'nullable|array',
        ]);

        $validated['updated_by'] = $user->id;

        // Default available variables if not provided
        if (empty($validated['available_variables'])) {
            $validated['available_variables'] = ['CompanyName', 'SubmissionRef', 'CreatedAt', 'AssignedTo', 'Status'];
        }

        $template = EmailTemplate::create($validated);

        SystemAuditLog::record(
            'email_template.created',
            [],
            $template->toArray(),
            $user->id,
            'email_template',
            $template->id,
        );

        return response()->json([
            'message' => 'Email template created successfully.',
            'data'    => $template,
        ], 201);
    }

    /**
     * PUT /api/email-templates/{emailTemplate} — update
     */
    public function update(Request $request, EmailTemplate $emailTemplate): JsonResponse
    {
        $user = $request->user();
        if (! $this->canManage($user)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'name'    => 'sometimes|string|max:200',
            'subject' => 'sometimes|string|max:255',
            'body'    => 'sometimes|string|min:10',
        ]);

        $old = $emailTemplate->only(array_keys($validated));

        $emailTemplate->fill($validated);
        $emailTemplate->updated_by = $user->id;
        $emailTemplate->save();

        // Audit
        $changed = array_filter($old, fn ($v, $k) => $v !== ($validated[$k] ?? null), ARRAY_FILTER_USE_BOTH);
        if (! empty($changed)) {
            SystemAuditLog::record(
                'email_template.updated',
                $changed,
                array_intersect_key($validated, $changed),
                $user->id,
                'email_template',
                $emailTemplate->id,
            );
        }

        return response()->json(['message' => 'Template updated.', 'data' => $emailTemplate->fresh()]);
    }

    /**
     * DELETE /api/email-templates/{emailTemplate}
     */
    public function destroy(Request $request, EmailTemplate $emailTemplate): JsonResponse
    {
        $user = $request->user();
        if (! $this->canDelete($user)) {
            return response()->json(['message' => 'Unauthorized. You need "Delete Templates / Levels" permission.'], 403);
        }

        $old = $emailTemplate->toArray();
        $emailTemplate->delete();

        SystemAuditLog::record(
            'email_template.deleted',
            $old,
            [],
            $user->id,
            'email_template',
            $old['id'],
        );

        return response()->json(['message' => 'Email template deleted.']);
    }

    /**
     * Ensure every notification trigger has at least one email template.
     */
    private function ensureTemplatesForAllTriggers(): void
    {
        $triggers = NotificationTrigger::all(['key', 'name']);
        $existingKeys = EmailTemplate::pluck('trigger_key')->toArray();
        $defaultVars = ['CompanyName', 'SubmissionRef', 'CreatedAt', 'AssignedTo', 'Status'];

        foreach ($triggers as $trigger) {
            if (! in_array($trigger->key, $existingKeys)) {
                EmailTemplate::create([
                    'trigger_key'         => $trigger->key,
                    'name'                => $trigger->name . ' Email',
                    'subject'             => $trigger->name . ' - {{CompanyName}}',
                    'body'                => "Dear Team,\n\nThis is a notification regarding: {$trigger->name}.\n\nSubmission: {{SubmissionRef}}\nCompany: {{CompanyName}}\nStatus: {{Status}}\nAssigned To: {{AssignedTo}}\nDate: {{CreatedAt}}\n\nPlease review and take the necessary action.\n\nRegards,\n" . config('app.name', 'Aston Hill CRM'),
                    'available_variables' => $defaultVars,
                ]);
            }
        }
    }
}
