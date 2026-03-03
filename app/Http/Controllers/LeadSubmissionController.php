<?php

namespace App\Http\Controllers;

use App\Models\LeadSubmission;
use App\Models\ServiceCategory;
use App\Models\ServiceType;
use App\Models\UserColumnPreference;
use App\Models\LeadSubmissionDocument;
use App\Services\LeadSubmissionService;
use App\Support\LeadSubmissionSchema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Facades\DataTables;
use App\Http\Resources\LeadSubmissionResource;
use App\Http\Resources\LeadSubmissionShowResource;
use App\Rules\AeDomainRule;
use App\Rules\AllowedDocumentFile;

class LeadSubmissionController extends Controller
{
    private const CANONICAL_SERVICE_TYPES_BY_CATEGORY = [
        'fixed' => ['New Submission', 'Relocation', 'Update WO', 'Contract Renewal', 'Migration', 'Other'],
        'fms' => ['New Submission', 'Relocation', 'Update WO', 'Contract Renewal', 'Migration', 'Other'],
        'gsm' => ['New Sim Card', 'C2B Migration', 'B2B Migration', 'MNP', 'MNMI / EC Renewal', 'AS Update', 'MPR', 'Sim Replacement', 'Multi Sim'],
        'other' => ['Office 365', 'Domain Activation', 'Number Swapping', 'Device Request', 'Other Request'],
    ];

    public function __construct(private LeadSubmissionService $leadSubmissionService)
    {
        // Your CrudPermission middleware (module: leads)
        // Must exist in Kernel alias as 'crud' OR your custom name.
        $this->middleware('crud:lead-submissions');
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', LeadSubmission::class);

        $preference = UserColumnPreference::where('user_id', $request->user()->id)
            ->where('module', 'lead_submissions.index')
            ->first();

        $defaultCols = [
            'created_at','lead_submission_id','company_name','account_number','request_type',
            'category','type','status','created_by','email','phone'
        ];

        $visibleCols = $preference?->columns ?: $defaultCols;

        $categories = ServiceCategory::orderBy('name')->get(['id','name']);

        return LeadSubmissionResource::collection(
            LeadSubmission::with(['creator','category','type'])
                ->filter($request->all())
                ->paginate(10)
        );

        // return view('lead-submission.index', compact('visibleCols','defaultCols','categories'));
    }

    /** DATATABLE ENDPOINT */
    public function datatable(Request $request)
    {
        $q = LeadSubmission::query()
            ->visibleTo($request->user())
            ->with(['creator:id,name,email','category:id,name','type:id,name']);

        // Filters
        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }

        if ($request->filled('service_category_id')) {
            $q->where('service_category_id', $request->service_category_id);
        }

        if ($request->filled('service_type_id')) {
            $q->where('service_type_id', $request->service_type_id);
        }

        if ($request->filled('from')) {
            $q->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $q->whereDate('created_at', '<=', $request->to);
        }

        if ($request->filled('q')) {
            $term = $request->q;
            $q->where(function ($w) use ($term) {
                $w->where('company_name', 'like', "%{$term}%")
                  ->orWhere('account_number', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%")
                  ->orWhere('phone', 'like', "%{$term}%")
                  ->orWhere('request_type', 'like', "%{$term}%");
            });
        }

        return DataTables::eloquent($q)
            ->addColumn('lead_submission_id', fn(LeadSubmission $l) => $l->id)
            ->addColumn('category', fn(LeadSubmission $l) => $l->category?->name ?? '-')
            ->addColumn('type', fn(LeadSubmission $l) => $l->type?->name ?? '-')
            ->addColumn('created_by', fn(LeadSubmission $l) => $l->creator ? ($l->creator->name.' ('.$l->creator->email.')') : '-')
            ->editColumn('created_at', fn(LeadSubmission $l) => optional($l->created_at)->format('d-M-Y h:i A'))
            // ->addColumn('actions', function (LeadSubmission $l) use ($request) {
            //     $user = $request->user();
            //     $show = route('lead-submissions.show', $l);
            //     $edit = route('lead-submissions.edit', $l);
            //     $del  = route('lead-submissions.destroy', $l);

            //     $btn = '<div class="flex gap-2 flex-wrap">';
            //     $btn .= '<a class="px-3 py-1 rounded bg-gray-900 text-white text-xs" href="'.$show.'">View</a>';

            //     if ($user->can('lead-submissions.edit')) {
            //         $btn .= '<a class="px-3 py-1 rounded bg-indigo-600 text-white text-xs" href="'.$edit.'">Edit</a>';
            //     }

            //     if ($user->can('lead-submissions.delete')) {
            //         $btn .= '<form method="POST" action="'.$del.'" onsubmit="return confirm(\'Delete this lead submission?\')" style="display:inline">';
            //         $btn .= csrf_field().method_field('DELETE');
            //         $btn .= '<button class="px-3 py-1 rounded bg-red-600 text-white text-xs">Delete</button>';
            //         $btn .= '</form>';
            //     }

            //     $btn .= '</div>';
            //     return $btn;
            // })
            // ->rawColumns(['actions'])
            ->toJson();
    }

    /** SAVE COLUMN PREFS */
    public function saveColumnPrefs(Request $request)
    {
        $data = $request->validate([
            'visible_columns' => ['required','array','min:1'],
            'visible_columns.*' => ['string','max:80'],
        ]);

        UserColumnPreference::updateOrCreate(
            ['user_id' => $request->user()->id, 'module' => 'lead_submissions.index'],
            ['visible_columns' => $data['visible_columns']]
        );

        return response()->json(['ok' => true]);
    }

    public function storeStep1(Request $request)
    {
        $draft = $request->boolean('draft');
        $rules = [
            'company_name' => ['required', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'authorized_signatory_name' => ['nullable', 'string', 'max:255'],
            'contact_number_gsm' => [$draft ? 'nullable' : 'required', 'string', 'max:50'],
            'alternate_contact_number' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => [$draft ? 'nullable' : 'required', 'string', 'max:500'],
            'emirates' => [$draft ? 'nullable' : 'required', 'string', 'max:150'],
            'emirate' => ['nullable', 'string', 'max:150'],
            'location_coordinates' => ['nullable', 'string', 'max:100'],
            'product' => [$draft ? 'nullable' : 'required', 'string', 'max:150'],
            'offer' => ['nullable', 'string', 'max:500'],
            'mrc_aed' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['nullable', 'integer', 'min:0'],
            'ae_domain' => [$draft ? 'nullable' : 'required', 'string', 'max:255', new AeDomainRule()],
            'gaid' => ['nullable', 'string', 'max:255'],
            'manager_id' => [$draft ? 'nullable' : 'required', 'integer', 'exists:users,id'],
            'team_leader_id' => ['nullable', 'integer', 'exists:users,id'],
            'sales_agent_id' => ['nullable', 'integer', 'exists:users,id'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'request_type' => ['nullable', 'string', 'max:120'],
        ];
        $messages = [
            'company_name.required' => 'Company name is required.',
            'contact_number_gsm.required' => 'Contact number (GSM) is required.',
            'address.required' => 'Complete address is required.',
            'emirates.required' => 'Emirates is required.',
            'product.required' => 'Product is required.',
            'ae_domain.required' => 'Enter Domain Name',
            'manager_id.required' => 'Please select a manager.',
            'email.email' => 'Please enter a valid email address.',
        ];
        $data = $request->validate($rules, $messages);

        $data['emirate'] = $data['emirate'] ?? $data['emirates'] ?? null;
        unset($data['emirates']);
        foreach (['manager_id', 'team_leader_id', 'sales_agent_id'] as $key) {
            if (isset($data[$key]) && $data[$key] === '') {
                $data[$key] = null;
            }
        }
        $payload = array_intersect_key($data, array_flip([
            'account_number', 'company_name', 'authorized_signatory_name', 'contact_number_gsm',
            'alternate_contact_number', 'email', 'address', 'emirate', 'location_coordinates',
            'product', 'offer', 'mrc_aed', 'quantity', 'ae_domain', 'gaid',
            'manager_id', 'team_leader_id', 'sales_agent_id', 'remarks', 'request_type',
        ]));
        $data['payload'] = $payload;
        $data['step'] = 1;

        $leadSubmission = $this->leadSubmissionService->createDraftFromStep1($data, $request->user()->id);
        self::forgetCurrentDraftCache((int) $request->user()->id);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'id' => $leadSubmission->id,
                'message' => 'Step 1 saved.',
            ], 201);
        }

    }

    private const CURRENT_DRAFT_CACHE_TTL = 120; // 2 min

    private static function currentDraftCacheKey(int $userId): string
    {
        return 'lead_submissions_current_draft_'.$userId;
    }

    /**
     * Get the current user's latest draft (for auto-resume on wizard load).
     * Cached per user 2 min to avoid repeated heavy queries; invalidated on create/update/discard.
     */
    public function currentDraft(Request $request)
    {
        $userId = (int) $request->user()->id;
        $cacheKey = self::currentDraftCacheKey($userId);

        $payload = Cache::remember($cacheKey, self::CURRENT_DRAFT_CACHE_TTL, function () use ($userId) {
            $draft = LeadSubmission::where('created_by', $userId)
                ->where('status', 'draft')
                ->with([
                    'category:id,name',
                    'type:id,name,schema',
                    'documents',
                    'creator:id,name',
                    'manager:id,name',
                    'teamLeader:id,name',
                    'salesAgent:id,name',
                ])
                ->orderBy('updated_at', 'desc')
                ->first();

            if (! $draft) {
                return ['draft' => null];
            }

            return ['draft' => (new LeadSubmissionShowResource($draft))->resolve()];
        });

        return response()->json($payload)->header('Cache-Control', 'private, max-age=120');
    }

    /** Invalidate current-draft cache for user (call after create/update/discard). */
    public static function forgetCurrentDraftCache(int $userId): void
    {
        Cache::forget(self::currentDraftCacheKey($userId));
    }

    /**
     * Update an existing draft (Step 1).
     */
    public function updateStep1(Request $request, LeadSubmission $lead)
    {
        // Only the creator can update their draft
        if ($lead->created_by !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }

        // Only drafts can be updated via this endpoint
        if ($lead->status !== 'draft') {
            abort(422, 'Cannot update a submitted lead submission.');
        }

        $draft = $request->boolean('draft');
        $rules = [
            'company_name' => ['required', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'authorized_signatory_name' => ['nullable', 'string', 'max:255'],
            'contact_number_gsm' => [$draft ? 'nullable' : 'required', 'string', 'max:50'],
            'alternate_contact_number' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => [$draft ? 'nullable' : 'required', 'string', 'max:500'],
            'emirates' => [$draft ? 'nullable' : 'required', 'string', 'max:150'],
            'emirate' => ['nullable', 'string', 'max:150'],
            'location_coordinates' => ['nullable', 'string', 'max:100'],
            'product' => [$draft ? 'nullable' : 'required', 'string', 'max:150'],
            'offer' => ['nullable', 'string', 'max:500'],
            'mrc_aed' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['nullable', 'integer', 'min:0'],
            'ae_domain' => [$draft ? 'nullable' : 'required', 'string', 'max:255', new AeDomainRule()],
            'gaid' => ['nullable', 'string', 'max:255'],
            'manager_id' => [$draft ? 'nullable' : 'required', 'integer', 'exists:users,id'],
            'team_leader_id' => ['nullable', 'integer', 'exists:users,id'],
            'sales_agent_id' => ['nullable', 'integer', 'exists:users,id'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'request_type' => ['nullable', 'string', 'max:120'],
        ];
        $messages = [
            'company_name.required' => 'Company name is required.',
            'contact_number_gsm.required' => 'Contact number (GSM) is required.',
            'address.required' => 'Complete address is required.',
            'emirates.required' => 'Emirates is required.',
            'product.required' => 'Product is required.',
            'ae_domain.required' => 'Enter Domain Name',
            'manager_id.required' => 'Please select a manager.',
            'email.email' => 'Please enter a valid email address.',
        ];
        $data = $request->validate($rules, $messages);

        $data['emirate'] = $data['emirate'] ?? $data['emirates'] ?? null;
        unset($data['emirates']);
        foreach (['manager_id', 'team_leader_id', 'sales_agent_id'] as $key) {
            if (isset($data[$key]) && $data[$key] === '') {
                $data[$key] = null;
            }
        }
        $data['updated_by'] = $request->user()->id;
        $payloadKeys = [
            'account_number', 'company_name', 'authorized_signatory_name', 'contact_number_gsm',
            'alternate_contact_number', 'email', 'address', 'emirate', 'location_coordinates',
            'product', 'offer', 'mrc_aed', 'quantity', 'ae_domain', 'gaid',
            'manager_id', 'team_leader_id', 'sales_agent_id', 'remarks', 'request_type',
        ];
        $payload = array_merge($lead->payload ?? [], array_intersect_key($data, array_flip($payloadKeys)));
        $data['payload'] = $payload;
        $data['step'] = 1;

        $lead->update($data);
        self::forgetCurrentDraftCache((int) $request->user()->id);

        return response()->json([
            'id' => $lead->id,
            'message' => 'Draft updated.',
        ]);
    }

    /**
     * Discard (delete) a draft so user can start fresh.
     */
    public function discardDraft(Request $request, LeadSubmission $lead)
    {
        if ($lead->created_by !== $request->user()->id) {
            abort(403, 'Unauthorized');
        }

        if ($lead->status !== 'draft') {
            abort(422, 'Cannot discard a submitted lead submission.');
        }

        $lead->delete();
        self::forgetCurrentDraftCache((int) $request->user()->id);

        return response()->json(['message' => 'Draft discarded.']);
    }

    /** STEP 2 */
    public function createStep2(LeadSubmission $leadSubmission, Request $request)
    {
        $this->authorizeLeadSubmissionAccess($request, $leadSubmission, 'view');

        $categories = ServiceCategory::orderBy('name')->get(['id','name']);
        return view('lead-submission.wizard.step2', compact('leadSubmission','categories'));
    }

    public function storeStep2(Request $request, LeadSubmission $lead)
    {
        $this->authorizeLeadSubmissionAccess($request, $lead, 'update');

        $data = $request->validate([
            'service_category_id' => ['required', 'integer', 'exists:service_categories,id'],
            'service_type_id' => ['required', 'integer', 'exists:service_types,id'],
        ], [
            'service_category_id.required' => 'Please select a service category.',
            'service_type_id.required' => 'Please select a service type.',
        ]);

        $categoryId = (int) $data['service_category_id'];
        $typeId = (int) $data['service_type_id'];

        // Ensure type belongs to the selected category
        $type = ServiceType::findOrFail($typeId);
        if ((int) $type->service_category_id !== $categoryId) {
            return response()->json(['message' => 'Service type does not belong to the selected category.'], 422);
        }

        $category = ServiceCategory::find($categoryId);
        $lead->service_category_id = $categoryId;
        $lead->service_type_id = $typeId;
        $payload = array_merge($lead->payload ?? [], [
            'category_name' => $category?->name,
            'type_name' => $type->name,
        ]);
        $lead->payload = $payload;
        $lead->step = 2;
        $lead->save();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['message' => 'Step 2 saved.']);
        }

        return redirect()->route('lead-submissions.wizard.step3', $lead)->with('success', 'Step 2 saved.');
    }

    /** STEP 3 (dynamic fields from service_types.schema) */
    public function createStep3(LeadSubmission $leadSubmission, Request $request)
    {
        $this->authorizeLeadSubmissionAccess($request, $leadSubmission, 'view');

        // types dropdown depends on selected category
        $types = collect();
        if ($leadSubmission->service_category_id) {
            $types = ServiceType::where('service_category_id', $leadSubmission->service_category_id)
                ->orderBy('name')
                ->get(['id','name','schema']);
        }

        $selectedTypeId = (int) ($request->get('service_type_id') ?: $leadSubmission->service_type_id ?: 0);
        $selectedType = $selectedTypeId ? $types->firstWhere('id', $selectedTypeId) : null;

        $fields = $selectedType ? LeadSubmissionSchema::fields($selectedType) : [];

        return view('lead-submission.wizard.step3', compact('leadSubmission','types','selectedType','fields'));
    }

    public function storeStep3(Request $request, LeadSubmission $leadSubmission)
    {
        $this->authorizeLeadSubmissionAccess($request, $leadSubmission, 'update');

        $base = $request->validate([
            'service_type_id' => ['required','exists:service_types,id'],
        ]);

        $type = ServiceType::findOrFail($base['service_type_id']);
        $fields = LeadSubmissionSchema::fields($type);

        // Dynamic validation rules
        $rules = $this->buildDynamicRules($fields);
        $validatedDynamic = $request->validate($rules);

        // Extract meta only for schema keys
        $meta = [];
        foreach ($fields as $f) {
            $key = $f['key'] ?? null;
            if (!$key) continue;

            // checkbox can be absent => store 0
            if (($f['type'] ?? '') === 'checkbox') {
                $meta[$key] = $request->boolean("meta.$key");
            } else {
                $meta[$key] = data_get($validatedDynamic, "meta.$key");
            }
        }

        $this->leadSubmissionService->saveStep3($leadSubmission, [
            'service_type_id' => $type->id,
            'meta' => $meta,
        ]);

        return redirect()->route('lead-submissions.wizard.step4', $leadSubmission)->with('success', 'Step 3 saved.');
    }

    /** STEP 4 (documents from schema) */
    public function createStep4(LeadSubmission $leadSubmission, Request $request)
    {
        $this->authorizeLeadSubmissionAccess($request, $leadSubmission, 'view');

        $docDefs = [];
        if ($leadSubmission->service_type_id) {
            $type = ServiceType::find($leadSubmission->service_type_id);
            $docDefs = $type ? LeadSubmissionSchema::documents($type) : [];
        }

        $existingDocs = LeadSubmissionDocument::where('lead_submission_id', $leadSubmission->id)->get();

        return view('lead-submission.wizard.step4', compact('leadSubmission','docDefs','existingDocs'));
    }

    public function storeStep4(Request $request, LeadSubmission $lead)
    {
        $this->authorizeLeadSubmissionAccess($request, $lead, 'update');

        $docDefs = $lead->service_type_id
            ? LeadSubmissionSchema::documents(ServiceType::findOrFail($lead->service_type_id))
            : LeadSubmissionSchema::defaultDocuments();

        // Validate: PDF, DOC, DOCX, EML only; 3MB per file (frontend sends array)
        $rules = [];
        $docKeys = array_filter(array_map(fn ($d) => $d['key'] ?? null, $docDefs));
        foreach ($docKeys as $key) {
            $rules["documents.$key"] = ['nullable', 'array'];
            $rules["documents.$key.*"] = ['file', new AllowedDocumentFile(), 'max:3072'];
        }
        // Allow additional custom document keys
        $allDocKeys = array_keys($request->file('documents', []) ?: []);
        foreach ($allDocKeys as $key) {
            if (!in_array($key, $docKeys, true)) {
                $rules["documents.$key"] = ['nullable', 'array'];
                $rules["documents.$key.*"] = ['file', new AllowedDocumentFile(), 'max:3072'];
            }
        }
        $request->validate($rules, [
            'documents.*.*' => 'Each file must be PDF, DOC, DOCX, or EML.',
            'documents.*.*.max' => 'Each file must not exceed 3MB.',
        ]);

        // Total size check (10MB)
        $totalSize = 0;
        $allKeys = array_unique(array_merge($docKeys, array_keys($request->file('documents', []) ?: [])));
        foreach ($allKeys as $key) {
            $files = $request->file("documents.$key");
            if (!$files) continue;
            $files = is_array($files) ? $files : [$files];
            foreach ($files as $f) {
                if ($f && $f->isValid()) {
                    $totalSize += $f->getSize();
                }
            }
        }
        $existingSize = LeadSubmissionDocument::where('lead_submission_id', $lead->id)->sum('size');
        if (($totalSize + $existingSize) > 10 * 1024 * 1024) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => 'Total upload size must not exceed 10MB.',
                    'errors' => ['documents' => ['Total upload size must not exceed 10MB.']],
                ], 422);
            }
            return back()->withErrors(['documents' => 'Total upload size must not exceed 10MB.']);
        }

        // Save documents in public/leads/{leadId}/... (replace-by-doc_key: one doc per type per lead)
        $this->leadSubmissionService->saveStep4Documents($request, $lead);

        // Persist wizard step: step_after=4 when user clicked Next (to review), 3 when Save as Draft
        $lead->step = (int) $request->input('step_after', $request->input('action') === 'submit' ? 4 : 3);
        if ($lead->step < 1 || $lead->step > 4) {
            $lead->step = $request->input('action') === 'submit' ? 4 : 3;
        }
        $lead->save();

        // If submit action: enforce required docs exist (in DB or in request)
        if ($request->input('action') === 'submit') {
            $missing = $this->missingRequiredDocs($lead, $docDefs);
            if (!empty($missing)) {
if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'message' => 'Please upload all required documents.',
                        'errors' => ['documents' => ['Missing required documents: ' . implode(', ', $missing)]],
                    ], 422);
                }
                return back()->withErrors([
                    'documents' => 'Missing required documents: ' . implode(', ', $missing),
                ]);
            }

            $this->leadSubmissionService->submit($lead);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['message' => 'Lead Submission submitted successfully.']);
            }
            return redirect()->route('lead-submissions.show', $lead)->with('success', 'Lead Submission submitted successfully.');
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['message' => 'Documents saved.']);
        }
        return back()->with('success', 'Documents saved.');
    }

    /**
     * SHOW (JSON for SPA).
     * Fetch lead by ID from database (query by id, no route model binding for JSON).
     * Returns lead + step + service + documents for wizard and review.
     */
    public function show(Request $request, $lead)
    {
        $id = (int) $lead;
        if ($id < 1) {
            return response()->json(['message' => 'Lead submission not found.'], 404);
        }

        $leadSubmission = LeadSubmission::with([
            'category:id,name',
            'type:id,name,schema',
            'documents',
            'creator:id,name',
            'manager:id,name',
            'teamLeader:id,name',
            'salesAgent:id,name',
            'executive:id,name',
        ])->find($id);

        if (! $leadSubmission) {
            return response()->json(['message' => 'Lead submission not found.'], 404);
        }

        $this->authorizeLeadSubmissionAccess($request, $leadSubmission, 'view');

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json((new LeadSubmissionShowResource($leadSubmission))->resolve());
        }

        $leadSubmission->load(['creator:id,name,email','category:id,name','type:id,name']);
        $docs = LeadSubmissionDocument::where('lead_submission_id', $leadSubmission->id)->get();
        $type = $leadSubmission->type;
        $fields = $type ? LeadSubmissionSchema::fields($type) : [];

        return view('lead-submission.show', compact('leadSubmission','docs','fields'));
    }

    /** EDIT (single page edit for primary + category + type + meta + upload new docs) */
    public function edit(Request $request, LeadSubmission $leadSubmission)
    {
        $this->authorizeLeadSubmissionAccess($request, $leadSubmission, 'view');

        $categories = ServiceCategory::orderBy('name')->get(['id','name']);
        $types = $leadSubmission->service_category_id
            ? ServiceType::where('service_category_id', $leadSubmission->service_category_id)->orderBy('name')->get(['id','name','schema'])
            : collect();

        $selectedType = $leadSubmission->type;
        $fields = $selectedType ? LeadSubmissionSchema::fields($selectedType) : [];
        $docDefs = $selectedType ? LeadSubmissionSchema::documents($selectedType) : [];
        $existingDocs = LeadSubmissionDocument::where('lead_submission_id', $leadSubmission->id)->get();

        return view('lead-submission.edit', compact('leadSubmission','categories','types','selectedType','fields','docDefs','existingDocs'));
    }

    public function update(Request $request, LeadSubmission $leadSubmission)
    {
        $this->authorizeLeadSubmissionAccess($request, $leadSubmission, 'update');

        $base = $request->validate([
            'company_name' => ['required','string','max:255'],
            'account_number' => ['nullable','string','max:100'],
            'email' => ['nullable','email','max:255'],
            'contact_number_gsm' => ['nullable','string','max:50'],
            'request_type' => ['nullable','string','max:120'],

            'service_category_id' => ['required','exists:service_categories,id'],
            'service_type_id' => ['required','exists:service_types,id'],
        ]);

        $type = ServiceType::findOrFail($base['service_type_id']);
        $fields = LeadSubmissionSchema::fields($type);
        $rules = $this->buildDynamicRules($fields);
        $validatedDynamic = $request->validate($rules);

        $meta = [];
        foreach ($fields as $f) {
            $key = $f['key'] ?? null;
            if (!$key) continue;

            if (($f['type'] ?? '') === 'checkbox') {
                $meta[$key] = $request->boolean("meta.$key");
            } else {
                $meta[$key] = data_get($validatedDynamic, "meta.$key");
            }
        }

        $lead->update([
            ...$base,
            'meta' => $meta,
        ]);

        // Optional: upload extra docs from edit
        $docDefs = LeadSubmissionSchema::documents($type);
        if (!empty($docDefs)) {
            // Only validate provided files
            $fileRules = [];
            foreach ($docDefs as $doc) {
                $key = $doc['key'] ?? null;
                if (!$key) continue;
                $fileRules["documents.$key"] = ['nullable','file','max:10240'];
            }
            $request->validate($fileRules);

            $this->leadSubmissionService->saveStep4Documents($request, $leadSubmission);
        }

        return redirect()->route('lead-submissions.show', $leadSubmission)->with('success', 'Lead Submission updated successfully.');
    }

    /** DELETE */
    public function destroy(Request $request, LeadSubmission $leadSubmission)
    {
        $this->authorizeLeadSubmissionAccess($request, $leadSubmission, 'delete');

        // delete documents files
        $docs = LeadSubmissionDocument::where('lead_submission_id', $leadSubmission->id)->get();
        foreach ($docs as $d) {
            if ($d->file_path) Storage::disk('public')->delete($d->file_path);
            $d->delete();
        }

        // delete folder (optional)
        Storage::disk('public')->deleteDirectory("lead-submissions/{$leadSubmission->id}");

        $leadSubmission->delete();

        return redirect()->route('lead-submissions.index')->with('success', 'Lead Submission deleted.');
    }

    /** AJAX: all categories */
    public function categories()
    {
        return response()->json(
            ServiceCategory::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
        );
    }

    /** AJAX: types by category (service-types?service_category_id=X) */
    public function serviceTypes(Request $request)
    {
        $request->validate([
            'service_category_id' => ['required', 'exists:service_categories,id'],
        ]);

        $category = ServiceCategory::query()
            ->where('id', (int) $request->service_category_id)
            ->first(['id', 'name']);

        $types = ServiceType::where('service_category_id', $request->service_category_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $categoryKey = strtolower(trim((string) ($category?->slug ?? $category?->name ?? '')));
        $canonicalNames = self::CANONICAL_SERVICE_TYPES_BY_CATEGORY[$categoryKey] ?? null;
        if (! is_array($canonicalNames)) {
            return response()->json($types);
        }
        $ordered = collect();
        foreach ($canonicalNames as $index => $typeName) {
            $slug = str($typeName)->slug()->toString() . '-' . ($category?->slug ?? 'service');
            $type = ServiceType::updateOrCreate(
                ['slug' => $slug],
                [
                    'service_category_id' => (int) $request->service_category_id,
                    'name' => $typeName,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                    'schema' => ['fields' => [], 'documents' => []],
                ]
            );
            $ordered->push($type->only(['id', 'name', 'slug']));
        }

        return response()->json($ordered->values()->all());
    }

    /** @deprecated use serviceTypes */
    public function serviceTypesByCategory(Request $request)
    {
        return $this->serviceTypes($request);
    }

    /** AJAX: schema by type */
    public function typeSchema(ServiceType $type)
    {
        return response()->json([
            'fields' => LeadSubmissionSchema::fields($type),
            'documents' => LeadSubmissionSchema::documents($type),
        ]);
    }

    /**
     * Download a single document. Authorized same as show.
     */
    public function downloadDocument(Request $request, $lead, $document)
    {
        $leadSubmission = LeadSubmission::find((int) $lead);
        if (! $leadSubmission) {
            return response()->json(['message' => 'Lead submission not found.'], 404);
        }
        $this->authorizeLeadSubmissionAccess($request, $leadSubmission, 'view');

        $doc = LeadSubmissionDocument::where('lead_submission_id', $leadSubmission->id)
            ->where('id', (int) $document)
            ->first();
        if (! $doc || ! $doc->file_path) {
            return response()->json(['message' => 'Document not found.'], 404);
        }
        $fullPath = Storage::disk('public')->path($doc->file_path);
        if (! is_file($fullPath)) {
            return response()->json(['message' => 'File not found.'], 404);
        }
        $filename = $doc->file_name ?: basename($doc->file_path);

        return response()->file($fullPath, [
            'Content-Disposition' => 'attachment; filename="'.addslashes($filename).'"',
        ]);
    }

    /**
     * Bulk download all documents as a zip. Authorized same as show.
     */
    public function bulkDownloadDocuments(Request $request, $lead): StreamedResponse
    {
        $leadSubmission = LeadSubmission::with('documents')->find((int) $lead);
        if (! $leadSubmission) {
            abort(404, 'Lead submission not found.');
        }
        $this->authorizeLeadSubmissionAccess($request, $leadSubmission, 'view');

        $documents = $leadSubmission->documents->filter(fn ($d) => $d->file_path && is_file(Storage::disk('public')->path($d->file_path)));
        if ($documents->isEmpty()) {
            abort(404, 'No documents to download.');
        }

        $zip = new \ZipArchive;
        $tempPath = storage_path('app/temp/lead-'.$leadSubmission->id.'-'.uniqid().'.zip');
        if (! is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }
        if ($zip->open($tempPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Could not create archive.');
        }
        foreach ($documents as $index => $doc) {
            $fullPath = Storage::disk('public')->path($doc->file_path);
            $baseName = $doc->file_name ?: basename($doc->file_path);
            $zip->addFile($fullPath, (string) ($index + 1).'-'.$baseName);
        }
        $zip->close();

        return response()->streamDownload(function () use ($tempPath) {
            echo file_get_contents($tempPath);
            @unlink($tempPath);
        }, 'lead-submission-'.$leadSubmission->id.'-documents.zip', [
            'Content-Type' => 'application/zip',
        ]);
    }

    /** Access control delegates to LeadSubmissionPolicy. */
    private function authorizeLeadSubmissionAccess(Request $request, LeadSubmission $leadSubmission, string $ability = 'view'): void
    {
        $this->authorize($ability, $leadSubmission);
    }

    /** dynamic rules builder */
    private function buildDynamicRules(array $fields): array
    {
        $rules = [];

        foreach ($fields as $f) {
            $key = $f['key'] ?? null;
            if (!$key) continue;

            $type = $f['type'] ?? 'text';
            $required = (bool)($f['required'] ?? false);

            $r = [];
            $r[] = $required ? 'required' : 'nullable';

            // Basic type mapping
            switch ($type) {
                case 'number':
                    $r[] = 'numeric';
                    break;
                case 'email':
                    $r[] = 'email';
                    break;
                case 'date':
                    $r[] = 'date';
                    break;
                case 'select':
                    $r[] = 'string';
                    break;
                case 'textarea':
                case 'text':
                default:
                    $r[] = 'string';
                    break;
                case 'checkbox':
                    $r[] = 'boolean';
                    break;
            }

            if (!empty($f['max'])) {
                $r[] = 'max:' . (int)$f['max'];
            }

            // If select has options, restrict values (optional)
            if ($type === 'select' && !empty($f['options']) && is_array($f['options'])) {
                $allowed = array_map('strval', $f['options']);
                $r[] = 'in:' . implode(',', array_map(fn($v) => str_replace(',', '\,', $v), $allowed));
            }

            $rules["meta.$key"] = $r;
        }

        return $rules;
    }

    private function missingRequiredDocs(LeadSubmission $leadSubmission, array $docDefs): array
    {
        $missing = [];
        $existingKeys = LeadSubmissionDocument::where('lead_submission_id', $leadSubmission->id)->pluck('doc_key')->toArray();

        foreach ($docDefs as $doc) {
            $key = $doc['key'] ?? null;
            if (!$key) continue;

            $required = (bool)($doc['required'] ?? false);
            if ($required && !in_array($key, $existingKeys, true)) {
                $missing[] = $doc['label'] ?? $key;
            }
        }

        return $missing;
    }

    public function submit($lead)
    {
        $leadSubmission = LeadSubmission::findOrFail($lead);
        $this->authorize('submit', $leadSubmission);

        // UPDATE existing row only (avoid INSERT / created_by error)
        $leadSubmission->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'status_changed_at' => now(),
            'submission_type' => $leadSubmission->submission_type ?? 'new',
        ]);

        if (request()->expectsJson() || request()->ajax()) {
            return response()->json([
                'message' => 'Lead submission submitted successfully.',
            ], 200);
        }

        return redirect()->route('lead-submissions.index')
            ->with('success', 'Lead submission submitted successfully');
    }

    /**
     * GET resubmission form data: lead + categories + document definitions.
     * Only for rejected leads; only super admin or creator.
     */
    public function resubmissionData(Request $request, $lead)
    {
        $leadSubmission = LeadSubmission::with([
            'category:id,name',
            'type:id,name,schema',
            'documents',
            'creator:id,name',
            'manager:id,name',
            'teamLeader:id,name',
            'salesAgent:id,name',
        ])->findOrFail((int) $lead);

        $this->authorize('resubmit', $leadSubmission);

        if (! in_array($leadSubmission->status, ['rejected', 'submitted'])) {
            return response()->json(['message' => 'Only rejected or submitted submissions can be resubmitted.'], 422);
        }

        $categories = ServiceCategory::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug']);
        $resubmissionDocDefs = [
            ['key' => 'trade_license', 'label' => 'Trade License', 'required' => true],
            ['key' => 'establishment_card', 'label' => 'Establishment Card', 'required' => false],
            ['key' => 'owner_emirates_id', 'label' => 'Owner Emirates ID', 'required' => true],
            ['key' => 'vat_certificate', 'label' => 'VAT Certificate', 'required' => false],
        ];

        $leadArr = (new LeadSubmissionShowResource($leadSubmission))->resolve();
        $payload = $leadSubmission->payload ?? [];
        $leadArr['resubmission_reason'] = $payload['resubmission_reason'] ?? null;
        $leadArr['previous_activity'] = $payload['previous_activity'] ?? null;

        return response()->json([
            'lead' => $leadArr,
            'categories' => $categories,
            'resubmission_documents' => $resubmissionDocDefs,
        ]);
    }

    /**
     * POST resubmit: create a new lead entry from the source lead + form data,
     * then optionally submit it. Original lead remains unchanged.
     * Only super admin or creator.
     */
    public function resubmit(Request $request, $lead)
    {
        $leadSubmission = LeadSubmission::findOrFail((int) $lead);
        $this->authorize('resubmit', $leadSubmission);

        if (! in_array($leadSubmission->status, ['rejected', 'submitted'])) {
            return response()->json(['message' => 'Only rejected or submitted submissions can be resubmitted.'], 422);
        }

        $isDraft = $request->input('action') === 'draft';

        $rules = [
            'account_number' => ['nullable', 'string', 'max:100'],
            'company_name' => [$isDraft ? 'nullable' : 'required', 'string', 'max:255'],
            'authorized_signatory_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact_number_gsm' => [$isDraft ? 'nullable' : 'required', 'string', 'max:50'],
            'alternate_contact_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'emirate' => ['nullable', 'string', 'max:100'],
            'location_coordinates' => ['nullable', 'string', 'max:100'],
            'previous_activity' => [$isDraft ? 'nullable' : 'required', 'string', 'max:2000'],
            'resubmission_reason' => [$isDraft ? 'nullable' : 'required', 'string', 'max:2000'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'service_category_id' => ['nullable', 'integer', 'exists:service_categories,id'],
            'service_type_id' => ['nullable', 'integer', 'exists:service_types,id'],
            'product' => ['nullable', 'string', 'max:255'],
            'offer' => ['nullable', 'string', 'max:255'],
            'mrc_aed' => ['nullable', 'string', 'max:50'],
            'quantity' => ['nullable', 'string', 'max:50'],
            'ae_domain' => ['nullable', 'string', 'max:255'],
            'gaid' => ['nullable', 'string', 'max:100'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'team_leader_id' => ['nullable', 'integer', 'exists:users,id'],
            'sales_agent_id' => ['nullable', 'integer', 'exists:users,id'],
        ];

        $data = $request->validate($rules);

        $payload = array_merge($leadSubmission->payload ?? [], [
            'resubmission_reason' => $data['resubmission_reason'] ?? null,
            'previous_activity' => $data['previous_activity'] ?? null,
            'is_resubmission' => true,
            'resubmission_of_id' => $leadSubmission->id,
        ]);

        $create = [
            'created_by' => $request->user()->id,
            'account_number' => $data['account_number'] ?? $leadSubmission->account_number,
            'company_name' => $data['company_name'] ?? $leadSubmission->company_name,
            'authorized_signatory_name' => $data['authorized_signatory_name'] ?? $leadSubmission->authorized_signatory_name,
            'email' => $data['email'] ?? $leadSubmission->email,
            'contact_number_gsm' => $data['contact_number_gsm'] ?? $leadSubmission->contact_number_gsm,
            'alternate_contact_number' => $data['alternate_contact_number'] ?? $leadSubmission->alternate_contact_number,
            'address' => $data['address'] ?? $leadSubmission->address,
            'emirate' => $data['emirate'] ?? $leadSubmission->emirate,
            'location_coordinates' => $data['location_coordinates'] ?? $leadSubmission->location_coordinates,
            'product' => $data['product'] ?? $leadSubmission->product,
            'offer' => $data['offer'] ?? $leadSubmission->offer,
            'mrc_aed' => $data['mrc_aed'] ?? $leadSubmission->mrc_aed,
            'quantity' => $data['quantity'] ?? $leadSubmission->quantity,
            'ae_domain' => $data['ae_domain'] ?? $leadSubmission->ae_domain,
            'gaid' => $data['gaid'] ?? $leadSubmission->gaid,
            'manager_id' => $data['manager_id'] ?? $leadSubmission->manager_id,
            'team_leader_id' => $data['team_leader_id'] ?? $leadSubmission->team_leader_id,
            'sales_agent_id' => $data['sales_agent_id'] ?? $leadSubmission->sales_agent_id,
            'remarks' => $data['remarks'] ?? $leadSubmission->remarks,
            'payload' => $payload,
            'submission_type' => 'resubmission',
            'updated_by' => $request->user()->id,
            'step' => 4,
            'status' => $isDraft ? 'draft' : 'submitted',
            'submitted_at' => $isDraft ? null : now(),
            'status_changed_at' => now(),
            'rejected_at' => null,
            'rejected_by' => null,
            'approved_at' => null,
            'approved_by' => null,
        ];

        if (!empty($data['service_category_id'])) {
            $create['service_category_id'] = $data['service_category_id'];
        }
        if (!empty($data['service_type_id'])) {
            $create['service_type_id'] = $data['service_type_id'];
        }

        $newLeadSubmission = DB::transaction(function () use ($leadSubmission, $create) {
            $newLeadSubmission = LeadSubmission::create($create);

            foreach ($leadSubmission->documents as $doc) {
                $newLeadSubmission->documents()->create([
                    'service_type_id' => $doc->service_type_id,
                    'doc_key' => $doc->doc_key,
                    'label' => $doc->label,
                    'file_path' => $doc->file_path,
                    'file_name' => $doc->file_name,
                    'mime' => $doc->mime,
                    'size' => $doc->size,
                ]);
            }

            return $newLeadSubmission;
        });

        // Document uploads (resubmission doc keys)
        $docKeys = array_values(array_filter(array_map(
            fn ($doc) => $doc['key'] ?? null,
            LeadSubmissionSchema::defaultDocuments()
        )));
        $fileRules = [];
        foreach ($docKeys as $key) {
            $fileRules["documents.{$key}"] = ['nullable', 'array'];
            $fileRules["documents.{$key}.*"] = ['file', new AllowedDocumentFile(), 'max:3072'];
        }
        $fileRules['documents.additional'] = ['nullable', 'array'];
        $fileRules['documents.additional.*'] = ['file', new AllowedDocumentFile(), 'max:3072'];
        $request->validate($fileRules, [
            'documents.*.*' => 'Each file must be PDF, DOC, DOCX, or EML.',
            'documents.*.*.max' => 'Each file must not exceed 3MB.',
        ]);
        $this->leadSubmissionService->saveResubmissionDocuments($request, $newLeadSubmission, $docKeys);

        // Handle additional documents (append, don't replace)
        $additionalFiles = $request->file('documents.additional');
        if ($additionalFiles) {
            $additionalFiles = is_array($additionalFiles) ? $additionalFiles : [$additionalFiles];
            $ts = (string) time();
            foreach ($additionalFiles as $i => $file) {
                if ($file && $file->isValid()) {
                    $docKey = 'additional_' . $ts . '_' . $i;
                    $path = $file->store("leads/{$newLeadSubmission->id}", 'public');
                    $newLeadSubmission->documents()->create([
                        'doc_key' => $docKey,
                        'original_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'mime_type' => $file->getClientMimeType(),
                        'size' => $file->getSize(),
                    ]);
                }
            }
        }

        if (!$isDraft) {
            // Require Trade License on submit (must exist after saving uploads)
            if (!$newLeadSubmission->documents()->where('doc_key', 'trade_license')->exists()) {
                return response()->json(['message' => 'Trade License is required.'], 422);
            }
        }

        return response()->json([
            'id' => $newLeadSubmission->id,
            'message' => $isDraft ? 'Resubmission draft saved.' : 'Lead resubmitted successfully.',
            'status' => $newLeadSubmission->fresh()->status,
        ], $isDraft ? 200 : 200);
    }

}
