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
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables;
use App\Http\Resources\LeadSubmissionResource;

class LeadSubmissionController extends Controller
{
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
        $q = LeadSubmission::query()->with(['creator:id,name,email','category:id,name','type:id,name']);

        // Your restriction logic belongs here:
        if (!$request->user()->hasRole('superadmin') && !$request->user()->can('lead-submissions.view_all')) {
            $q->where('created_by', $request->user()->id);
        }

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
        $data = $request->validate([
            'company_name' => ['required','string','max:255'],
            'account_number' => ['nullable','string','max:100'],
            'authorized_signatory_name' => ['nullable','string','max:255'],
            'contact_number_gsm' => ['nullable','string','max:50'],
            'alternate_contact_number' => ['nullable','string','max:50'],
            'email' => ['nullable','email','max:255'],
            'address' => ['nullable','string','max:500'],
            'emirates' => ['nullable','string','max:150'],
            'location_coordinates' => ['nullable','string','max:100'],
            'product' => ['nullable','string','max:150'],
            'offer' => ['nullable','string','max:150'],
            'mrc_aed' => ['nullable','string','max:50'],
            'quantity' => ['nullable','integer','min:0'],
            'remarks' => ['nullable','string','max:1000'],

            // If your figma has Request Type on step1:
            'request_type' => ['nullable','string','max:120'],
        ]);

        $leadSubmission = $this->leadSubmissionService->createDraftFromStep1($data, $request->user()->id);

        return redirect()->route('lead-submissions.wizard.step2', $leadSubmission)->with('success', 'Step 1 saved.');
    }

    /** STEP 2 */
    public function createStep2(LeadSubmission $leadSubmission, Request $request)
    {
        $this->authorizeLeadSubmissionAccess($request, $leadSubmission);

        $categories = ServiceCategory::orderBy('name')->get(['id','name']);
        return view('lead-submission.wizard.step2', compact('leadSubmission','categories'));
    }

    public function storeStep2(Request $request, LeadSubmission $leadSubmission)
    {
        $this->authorizeLeadSubmissionAccess($request, $leadSubmission);

        $data = $request->validate([
            'service_category_id' => ['required','exists:service_categories,id'],
        ]);

        $this->leadSubmissionService->saveStep2($leadSubmission, $data);

        return redirect()->route('lead-submissions.wizard.step3', $leadSubmission)->with('success', 'Step 2 saved.');
    }

    /** STEP 3 (dynamic fields from service_types.schema) */
    public function createStep3(LeadSubmission $leadSubmission, Request $request)
    {
        $this->authorizeLeadSubmissionAccess($request, $leadSubmission);

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
        $this->authorizeLeadSubmissionAccess($request, $leadSubmission);

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
        $this->authorizeLeadSubmissionAccess($request, $leadSubmission);

        $docDefs = [];
        if ($leadSubmission->service_type_id) {
            $type = ServiceType::find($leadSubmission->service_type_id);
            $docDefs = $type ? LeadSubmissionSchema::documents($type) : [];
        }

        $existingDocs = LeadSubmissionDocument::where('lead_submission_id', $leadSubmission->id)->get();

        return view('lead-submission.wizard.step4', compact('leadSubmission','docDefs','existingDocs'));
    }

    public function storeStep4(Request $request, LeadSubmission $leadSubmission)
    {
        $this->authorizeLeadSubmissionAccess($request, $leadSubmission);

        $type = ServiceType::findOrFail($leadSubmission->service_type_id);
        $docDefs = LeadSchema::documents($type);

        // Validate uploads (optional per doc) + max size
        $rules = [];
        foreach ($docDefs as $doc) {
            $key = $doc['key'] ?? null;
            if (!$key) continue;

            $required = (bool)($doc['required'] ?? false);

            // file validation
            $rules["documents.$key"] = [
                $required ? 'required' : 'nullable',
                'file',
                'max:10240', // 10MB
            ];
        }
        $request->validate($rules);

        // Save documents in public/leads/{leadId}/...
        $this->leadSubmissionService->saveStep4Documents($request, $leadSubmission);

        // If submit action: enforce required docs exist (in DB or in request)
        if ($request->input('action') === 'submit') {
            $missing = $this->missingRequiredDocs($leadSubmission, $docDefs);
            if (!empty($missing)) {
                return back()->withErrors([
                    'documents' => 'Missing required documents: ' . implode(', ', $missing),
                ]);
            }

            $this->leadSubmissionService->submit($leadSubmission);
            return redirect()->route('lead-submissions.show', $leadSubmission)->with('success', 'Lead Submission submitted successfully.');
        }

        return back()->with('success', 'Documents saved.');
    }

    /** SHOW */
    public function show(Request $request, LeadSubmission $leadSubmission)
    {
        $this->authorizeLeadSubmissionAccess($request, $leadSubmission);

        $leadSubmission->load(['creator:id,name,email','category:id,name','type:id,name']);
        $docs = LeadSubmissionDocument::where('lead_submission_id', $leadSubmission->id)->get();

        $type = $leadSubmission->type;
        $fields = $type ? LeadSubmissionSchema::fields($type) : [];

        return view('lead-submission.show', compact('leadSubmission','docs','fields'));
    }

    /** EDIT (single page edit for primary + category + type + meta + upload new docs) */
    public function edit(Request $request, LeadSubmission $leadSubmission)
    {
        $this->authorizeLeadSubmissionAccess($request, $leadSubmission);

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
        $this->authorizeLeadSubmissionAccess($request, $leadSubmission);

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
        $this->authorizeLeadSubmissionAccess($request, $leadSubmission);

        // delete documents files
        $docs = LeadSubmissionDocument::where('lead_submission_id', $leadSubmission->id)->get();
        foreach ($docs as $d) {
            if ($d->path) Storage::disk('public')->delete($d->path);
            $d->delete();
        }

        // delete folder (optional)
        Storage::disk('public')->deleteDirectory("lead-submissions/{$leadSubmission->id}");

        $leadSubmission->delete();

        return redirect()->route('lead-submissions.index')->with('success', 'Lead Submission deleted.');
    }

    /** AJAX: types by category */
    public function serviceTypesByCategory(Request $request)
    {
        $request->validate([
            'service_category_id' => ['required','exists:service_categories,id'],
        ]);

        $types = ServiceType::where('service_category_id', $request->service_category_id)
            ->orderBy('name')
            ->get(['id','name']);

        return response()->json($types);
    }

    /** AJAX: schema by type */
    public function typeSchema(ServiceType $type)
    {
        return response()->json([
            'fields' => LeadSubmissionSchema::fields($type),
            'documents' => LeadSubmissionSchema::documents($type),
        ]);
    }

    /** ACCESS CONTROL: owner-only unless superadmin or leads.view_all */
    private function authorizeLeadSubmissionAccess(Request $request, LeadSubmission $leadSubmission): void
    {
        $user = $request->user();

        if ($user->hasRole('superadmin') || $user->can('lead_submissions.view_all')) {
            return;
        }

        if ((int)$leadSubmission->created_by !== (int)$user->id) {
            abort(403);
        }
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

    public function submit(LeadSubmission $leadSubmission)
    {
        $this->authorize('submit', $leadSubmission);

        $leadSubmission->submit();

        // Notification
        Notification::send(
            User::role('superadmin')->get(),
            new LeadSubmittedNotification($leadSubmission)
        );

        return redirect()->route('lead-submissions.index')
            ->with('success','Lead submission submitted successfully');
    }

}
