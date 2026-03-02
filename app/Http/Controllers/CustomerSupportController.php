<?php

namespace App\Http\Controllers;

use App\Models\CustomerSupportSubmission;
use App\Services\CustomerSupportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CustomerSupportController extends Controller
{
    public function __construct(private CustomerSupportService $customerSupportService)
    {
    }

    public static function issueCategories(): array
    {
        return [
            'Internet / Landline Issues',
            'SIM Cards Not Working',
            'Billing Issues',
            'Plan / Benefits Issue',
            'Documents / Contract Renewal',
            'Upgrade / Downgrade / Cancellation',
            'Hard Cap / Roaming Activation',
            'B2B Portal Issue',
            'Other Request',
        ];
    }

    /**
     * Store a new customer support submission (with optional attachments).
     */
    public function store(Request $request): JsonResponse
    {
        $categories = self::issueCategories();
        $rules = [
            'issue_category' => ['required', 'string', Rule::in($categories)],
            'company_name' => ['required', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'contact_number' => ['required', 'string', 'max:50'],
            'issue_description' => ['required', 'string', 'max:5000'],
            'manager_id' => ['required', 'integer', 'min:1', 'exists:users,id'],
            'team_leader_id' => ['nullable', 'integer', 'exists:users,id'],
            'sales_agent_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
        $messages = [
            'issue_category.required' => 'Please select an issue category.',
            'issue_category.in' => 'Invalid issue category.',
            'company_name.required' => 'Company name is required.',
            'contact_number.required' => 'Contact number is required.',
            'issue_description.required' => 'Issue description is required.',
            'manager_id.required' => 'Please select a manager.',
            'manager_id.integer' => 'Please select a valid manager.',
            'manager_id.min' => 'Please select a manager.',
            'manager_id.exists' => 'Please select a valid manager.',
            'team_leader_id.integer' => 'Please select a valid team leader.',
            'team_leader_id.exists' => 'Please select a valid team leader.',
            'sales_agent_id.integer' => 'Please select a valid sales agent.',
            'sales_agent_id.exists' => 'Please select a valid sales agent.',
        ];
        foreach (['attachment_1', 'attachment_2'] as $key) {
            $rules[$key] = ['nullable', 'file', 'max:10240']; // 10MB
        }

        $data = $request->validate($rules, $messages);

        $payload = [
            'issue_category' => $data['issue_category'],
            'company_name' => $data['company_name'],
            'account_number' => $data['account_number'] ?? '',
            'contact_number' => $data['contact_number'],
            'issue_description' => $data['issue_description'],
            'attachments' => [],
            'manager_id' => (int) $data['manager_id'],
            'team_leader_id' => isset($data['team_leader_id']) ? (int) $data['team_leader_id'] : null,
            'sales_agent_id' => isset($data['sales_agent_id']) ? (int) $data['sales_agent_id'] : null,
        ];

        $submission = $this->customerSupportService->create($payload, $request->user()->id);
        $dir = 'customer-support/' . $submission->id;
        $attachments = [];

        foreach (['attachment_1', 'attachment_2'] as $key) {
            if ($request->hasFile($key)) {
                $file = $request->file($key);
                $path = $file->store($dir, 'public');
                $attachments[] = [
                    'path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                ];
            }
        }

        if (count($attachments) > 0) {
            $submission->update(['attachments' => $attachments]);
        }

        if ($request->boolean('submit')) {
            $this->customerSupportService->submit($submission);
        }

        return response()->json([
            'id' => $submission->id,
            'message' => $request->boolean('submit')
                ? 'Request submitted successfully.'
                : 'Customer support request saved.',
        ], 201);
    }

    /**
     * Team options (Manager, Team Leader, Sales Agent) – reuse field submissions logic.
     */
    public function teamOptions(Request $request): JsonResponse
    {
        return app(FieldSubmissionController::class)->teamOptions($request);
    }
}
