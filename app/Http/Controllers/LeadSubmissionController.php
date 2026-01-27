<?php

namespace App\Http\Controllers;

use App\Models\LeadSubmission;
use App\Models\ServiceCategory;
use App\Models\ServiceType;
use App\Models\LeadSubmissionDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class LeadSubmissionController extends Controller
{
    public function __construct()
    {
        $this->middleware('crud:lead_submission');
    }

    /**
     * LIST PAGE
     */
    public function index(Request $request)
    {
        // Blade page only, datatable loads rows via ajax.
        return view('lead-submissions.index');
    }

    /**
     * DATATABLE
     */
    public function datatable(Request $request)
    {
        if (!$request->user()->can('lead_submission.list')) abort(403);

        $q = LeadSubmission::query()
            ->with(['creator:id,name,email', 'category:id,name', 'type:id,name'])
            ->select('lead_submissions.*');

        // Owner-only unless superadmin or permission lead-submission.view_all
        if (!$request->user()->hasRole('superadmin') && !$request->user()->can('lead_submission.view_all')) {
            $q->where('created_by', $request->user()->id);
        }

        // Filters (basic + all common)
        if ($request->filled('q')) {
            $term = $request->q;
            $q->where(function ($w) use ($term) {
                $w->where('company_name', 'like', "%{$term}%")
                  ->orWhere('account_number', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%")
                  ->orWhere('contact_number', 'like', "%{$term}%")
                  ->orWhere('status', 'like', "%{$term}%");
            });
        }

        if ($request->filled('service_category_id')) {
            $q->where('service_category_id', $request->service_category_id);
        }

        if ($request->filled('service_type_id')) {
            $q->where('service_type_id', $request->service_type_id);
        }

        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }

        if ($request->filled('created_by') && ($request->user()->can('lead_submission.view_all') || $request->user()->hasRole('superadmin'))) {
            $q->where('created_by', $request->created_by);
        }

        if ($request->filled('from')) {
            $q->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $q->whereDate('created_at', '<=', $request->to);
        }

        return DataTables::eloquent($q)
            ->addColumn('created_by_name', function (LeadSubmission $l) {
                return $l->creator ? ($l->creator->name.' ('.$l->creator->email.')') : '-';
            })
            ->addColumn('service_category_name', fn(LeadSubmission $l) => $l->category?->name ?? '-')
            ->addColumn('service_type_name', fn(LeadSubmission $l) => $l->type?->name ?? '-')
            ->editColumn('created_at', fn(LeadSubmission $l) => optional($l->created_at)->format('d-M-Y'))
            ->addColumn('actions', function (LeadSubmission $l) use ($request) {
                $u = $request->user();

                $btn = '<div class="flex gap-2 flex-wrap">';
                $btn .= '<a class="px-3 py-1 rounded bg-gray-800 text-white text-xs" href="'.route('lead-submissions.show', $l).'">View</a>';

                if ($u->can('lead_submission.edit')) {
                    $btn .= '<a class="px-3 py-1 rounded bg-indigo-600 text-white text-xs" href="'.route('lead-submissions.edit', $l).'">Edit</a>';
                }

                if ($u->can('lead_submission.delete')) {
                    $btn .= '<form method="POST" action="'.route('lead-submissions.destroy', $l).'" onsubmit="return confirm(\'Delete this lead Submission?\')" style="display:inline">';
                    $btn .= csrf_field().method_field('DELETE');
                    $btn .= '<button class="px-3 py-1 rounded bg-red-600 text-white text-xs">Delete</button>';
                    $btn .= '</form>';
                }

                $btn .= '</div>';
                return $btn;
            })
            ->rawColumns(['actions'])
            ->toJson();
    }

    /**
     * WIZARD - STEP 1 (Primary Info)
     */
    public function createStep1(Request $request)
    {
        $draft = $this->getDraft($request);
        return view('lead-submissions.create-step1', compact('draft'));
    }

    public function storeStep1(Request $request)
    {
        $data = $request->validate([
            'account_number' => ['required','string','max:100'],
            'company_name' => ['required','string','max:255'],

            'authorized_signatory_name' => ['nullable','string','max:255'],
            'contact_number' => ['required','string','max:50'],
            'alternate_contact_number' => ['nullable','string','max:50'],
            'email' => ['required','email','max:255'],

            'address' => ['required','string','max:500'],
            'emirates' => ['required','string','max:100'],
            'location_coordinates' => ['nullable','string','max:100'],

            'product' => ['required','string','max:255'],
            'offer' => ['nullable','string','max:255'],
            'mrc' => ['nullable','numeric','min:0'],
            'quantity' => ['nullable','integer','min:1'],
            'remarks' => ['nullable','string','max:2000'],
        ]);

        $this->putDraft($request, [
            'step1' => $data,
        ]);

        return redirect()->route('lead-submissions.create.step2');
    }

    /**
     * WIZARD - STEP 2 (Service Category)
     */
    public function createStep2(Request $request)
    {
        $draft = $this->getDraft($request);
        $categories = ServiceCategory::orderBy('sort_order')->orderBy('name')->get(['id','name','slug']);

        return view('lead-submissions.create-step2', compact('draft','categories'));
    }

    public function storeStep2(Request $request)
    {
        $data = $request->validate([
            'service_category_id' => ['required', Rule::exists('service_categories','id')],
        ]);

        $this->putDraft($request, [
            'step2' => $data,
            // reset dependent step when category changes
            'step3' => null,
            'step4' => null,
        ]);

        return redirect()->route('lead-submissions.create.step3');
    }

    /**
     * WIZARD - STEP 3 (Service Type + Dynamic Fields from schema)
     */
    public function createStep3(Request $request)
    {
        $draft = $this->getDraft($request);
        $categoryId = data_get($draft, 'step2.service_category_id');

        if (!$categoryId) {
            return redirect()->route('lead-submissions.create.step2')->with('error', 'Please select service category first.');
        }

        $types = ServiceType::where('service_category_id', $categoryId)
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id','name','schema','service_category_id']);

        // if type already selected, load schema
        $selectedTypeId = data_get($draft, 'step3.service_type_id');
        $selectedType = $selectedTypeId ? $types->firstWhere('id', (int)$selectedTypeId) : null;
        $schema = $selectedType?->schema ?? null;

        return view('lead-submissions.create-step3', compact('draft','types','selectedType','schema'));
    }

    public function storeStep3(Request $request)
    {
        $draft = $this->getDraft($request);
        $categoryId = data_get($draft, 'step2.service_category_id');

        if (!$categoryId) {
            return redirect()->route('lead-sumissions.create.step2')->with('error', 'Please select service category first.');
        }

        $base = $request->validate([
            'service_type_id' => ['required', Rule::exists('service_types','id')->where('service_category_id', $categoryId)],
        ]);

        $type = ServiceType::query()
            ->where('id', $base['service_type_id'])
            ->where('service_category_id', $categoryId)
            ->firstOrFail();

        // Dynamic fields validation from schema
        $schema = $type->schema ?? [];
        $dynamicRules = $this->rulesFromSchema($schema);

        $dynamicData = [];
        if (!empty($dynamicRules)) {
            $dynamicData = $request->validate($dynamicRules);
        }

        $this->putDraft($request, [
            'step3' => [
                'service_type_id' => (int)$base['service_type_id'],
                'meta' => $dynamicData, // saved in leads.meta json later
            ],
            'step4' => null, // reset docs when type changes
        ]);

        return redirect()->route('lead-submissions.create.step4');
    }

    /**
     * WIZARD - STEP 4 (Documents Upload based on schema)
     */
    public function createStep4(Request $request)
    {
        $draft = $this->getDraft($request);

        $categoryId = data_get($draft, 'step2.service_category_id');
        $typeId = data_get($draft, 'step3.service_type_id');

        if (!$categoryId) return redirect()->route('lead-submissions.create.step2')->with('error', 'Please select service category first.');
        if (!$typeId) return redirect()->route('lead-submissions.create.step3')->with('error', 'Please select service type first.');

        $type = ServiceType::where('id', $typeId)->firstOrFail();
        $schema = $type->schema ?? [];

        // expected documents list from schema
        $docs = data_get($schema, 'documents', []); // each: {key,label,required,accept, max_mb}

        return view('lead-submissions.create-step4', compact('draft','type','schema','docs'));
    }

    public function storeStep4(Request $request)
    {
        $draft = $this->getDraft($request);

        $categoryId = data_get($draft, 'step2.service_category_id');
        $typeId = data_get($draft, 'step3.service_type_id');

        if (!$categoryId) return redirect()->route('lead-submissions.create.step2')->with('error', 'Please select service category first.');
        if (!$typeId) return redirect()->route('lead-submissions.create.step3')->with('error', 'Please select service type first.');

        $type = ServiceType::where('id', $typeId)->firstOrFail();
        $schema = $type->schema ?? [];
        $docs = data_get($schema, 'documents', []);

        // Build file validation rules dynamically
        $fileRules = [];
        foreach ($docs as $doc) {
            $key = $doc['key'] ?? null;
            if (!$key) continue;

            $required = (bool)($doc['required'] ?? false);
            $maxMb = (int)($doc['max_mb'] ?? 10);

            // accept like "pdf,jpg,png" or mime list - keep simple:
            $rule = [$required ? 'required' : 'nullable', 'file', 'max:'.($maxMb * 1024)];
            $fileRules["docs.$key"] = $rule;
        }

        $validated = [];
        if (!empty($fileRules)) {
            $validated = $request->validate($fileRules);
        }

        // Save uploads temporarily in session (paths), then finalize on submit
        $stored = [];
        foreach ($docs as $doc) {
            $key = $doc['key'] ?? null;
            if (!$key) continue;

            if ($request->hasFile("docs.$key")) {
                $file = $request->file("docs.$key");
                $path = $file->store("lead-submissions/tmp/".($request->user()->id), 'public');

                $stored[$key] = [
                    'path' => $path,
                    'name' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ];
            }
        }

        $this->putDraft($request, [
            'step4' => [
                'docs' => $stored,
            ],
        ]);

        // FINAL SUBMIT: create lead + move docs to final folder
        return $this->finalizeLead($request);
    }

    /**
     * CRUD - SHOW
     */
    public function show(Request $request, LeadSubmission $leadSubmission)
    {
        if (!$this->canViewLead($request, $leadSubmission)) abort(403);

        $leadSubmission->load(['creator:id,name,email','category:id,name','type:id,name','documents']);

        return view('lead-submissions.show', compact('leadSubmission'));
    }

    /**
     * CRUD - EDIT
     * (You can decide: edit single page OR edit through wizard - here is single page entry point)
     */
    public function edit(Request $request, LeadSubmission $leadSubmission)
    {
        if (!$this->canEditLeadSubmission($request, $leadSubmission)) abort(403);

        $leadSubmission->load(['category:id,name','type:id,name','documents']);

        $categories = ServiceCategory::orderBy('sort_order')->orderBy('name')->get(['id','name']);
        $types = ServiceType::when($leadSubmission->service_category_id, fn($q)=>$q->where('service_category_id',$leadSubmission->service_category_id))
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id','name','schema','service_category_id']);

        $selectedType = $leadSubmission->type;
        $schema = $selectedType?->schema ?? [];

        return view('lead-submissions.edit', compact('leadSubmission','categories','types','schema'));
    }

    /**
     * CRUD - UPDATE
     */
    public function update(Request $request, LeadSubmission $leadSubmission)
    {
        if (!$this->canEditLeadSubmission($request, $leadSubmission)) abort(403);

        $data = $request->validate([
            'account_number' => ['required','string','max:100'],
            'company_name' => ['required','string','max:255'],

            'authorized_signatory_name' => ['nullable','string','max:255'],
            'contact_number' => ['required','string','max:50'],
            'alternate_contact_number' => ['nullable','string','max:50'],
            'email' => ['required','email','max:255'],

            'address' => ['required','string','max:500'],
            'emirates' => ['required','string','max:100'],
            'location_coordinates' => ['nullable','string','max:100'],

            'product' => ['required','string','max:255'],
            'offer' => ['nullable','string','max:255'],
            'mrc' => ['nullable','numeric','min:0'],
            'quantity' => ['nullable','integer','min:1'],
            'remarks' => ['nullable','string','max:2000'],

            'service_category_id' => ['required', Rule::exists('service_categories','id')],
            'service_type_id' => ['required', Rule::exists('service_types','id')->where('service_category_id', $request->service_category_id)],
        ]);

        $type = ServiceType::where('id', $data['service_type_id'])->firstOrFail();
        $schema = $type->schema ?? [];
        $dynamicRules = $this->rulesFromSchema($schema);
        $meta = [];

        if (!empty($dynamicRules)) {
            $meta = $request->validate($dynamicRules);
        }

        DB::transaction(function () use ($leadSubmission, $data, $meta) {
            $leadSubmission->fill([
                'account_number' => $data['account_number'],
                'company_name' => $data['company_name'],
                'authorized_signatory_name' => $data['authorized_signatory_name'] ?? null,
                'contact_number' => $data['contact_number'],
                'alternate_contact_number' => $data['alternate_contact_number'] ?? null,
                'email' => $data['email'],
                'address' => $data['address'],
                'emirates' => $data['emirates'],
                'location_coordinates' => $data['location_coordinates'] ?? null,
                'product' => $data['product'],
                'offer' => $data['offer'] ?? null,
                'mrc' => $data['mrc'] ?? null,
                'quantity' => $data['quantity'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'service_category_id' => (int)$data['service_category_id'],
                'service_type_id' => (int)$data['service_type_id'],
                'meta' => $meta,
            ]);

            $leadSubmission->save();
        });

        return redirect()->route('lead-submission.show', $leadSubmission)->with('success', 'Lead updated');
    }

    /**
     * CRUD - DELETE
     */
    public function destroy(Request $request, LeadSubmission $leadSubmission)
    {
        if (!$this->canDeleteLeadSubmission($request, $leadSubmission)) abort(403);

        DB::transaction(function () use ($leadSubmission) {
            // delete files
            foreach ($leadSubmission->documents as $doc) {
                if ($doc->path) Storage::disk('public')->delete($doc->path);
            }

            $leadSubmission->documents()->delete();
            $leadSubmission->delete();
        });

        return redirect()->route('lead-submission.index')->with('success', 'Lead Submission deleted');
    }

    /**
     * Download a lead document
     */
    public function downloadDocument(Request $request, Lead $leadSubmission, LeadSubmissionDocument $doc)
    {
        if (!$this->canViewLead($request, $lead)) abort(403);
        if ($doc->lead_id !== $lead->id) abort(404);

        return Storage::disk('public')->download($doc->path, $doc->original_name);
    }

    /**
     * =========================
     * Helpers
     * =========================
     */

    private function finalizeLead(Request $request)
    {
        $draft = $this->getDraft($request);

        $step1 = data_get($draft, 'step1');
        $step2 = data_get($draft, 'step2');
        $step3 = data_get($draft, 'step3');
        $step4 = data_get($draft, 'step4');

        if (!$step1 || !$step2 || !$step3) {
            return redirect()->route('leads.create.step1')->with('error', 'Wizard data incomplete.');
        }

        $lead = DB::transaction(function () use ($request, $step1, $step2, $step3, $step4) {

            $lead = LeadSubmission::create([
                'created_by' => $request->user()->id,

                // step 1
                'account_number' => $step1['account_number'],
                'company_name' => $step1['company_name'],
                'authorized_signatory_name' => $step1['authorized_signatory_name'] ?? null,
                'contact_number' => $step1['contact_number'],
                'alternate_contact_number' => $step1['alternate_contact_number'] ?? null,
                'email' => $step1['email'],
                'address' => $step1['address'],
                'emirates' => $step1['emirates'],
                'location_coordinates' => $step1['location_coordinates'] ?? null,
                'product' => $step1['product'],
                'offer' => $step1['offer'] ?? null,
                'mrc' => $step1['mrc'] ?? null,
                'quantity' => $step1['quantity'] ?? null,
                'remarks' => $step1['remarks'] ?? null,

                // step 2/3
                'service_category_id' => (int)$step2['service_category_id'],
                'service_type_id' => (int)$step3['service_type_id'],
                'meta' => $step3['meta'] ?? [],

                'status' => 'submitted',
                'submitted_at' => now(),
            ]);

            // move tmp docs to final folder
            $docs = data_get($step4, 'docs', []);
            foreach ($docs as $key => $info) {
                $tmpPath = $info['path'] ?? null;
                if (!$tmpPath) continue;

                $finalPath = "leads/{$lead->id}/{$key}-".basename($tmpPath);

                // move in same disk:
                Storage::disk('public')->move($tmpPath, $finalPath);

                LeadDocument::create([
                    'lead_id' => $lead->id,
                    'doc_key' => $key,
                    'path' => $finalPath,
                    'original_name' => $info['name'] ?? $key,
                    'mime' => $info['mime'] ?? null,
                    'size' => $info['size'] ?? null,
                ]);
            }

            return $lead;
        });

        $this->clearDraft($request);

        return redirect()->route('leads.show', $lead)->with('success', 'Lead submitted successfully.');
    }

    private function rulesFromSchema(array $schema): array
    {
        // Schema expected:
        // fields: [{ key, label, type, required, rules }]
        // Example rules: "string|max:255" or ["string","max:255"]
        $rules = [];
        $fields = $schema['fields'] ?? [];

        foreach ($fields as $f) {
            $key = $f['key'] ?? null;
            if (!$key) continue;

            $required = (bool)($f['required'] ?? false);

            $base = $required ? ['required'] : ['nullable'];

            // if schema has explicit rules:
            if (!empty($f['rules'])) {
                $extra = is_array($f['rules']) ? $f['rules'] : explode('|', (string)$f['rules']);
                $rules[$key] = array_merge($base, $extra);
                continue;
            }

            // fallback by type
            $type = $f['type'] ?? 'string';
            if ($type === 'number') $rules[$key] = array_merge($base, ['numeric']);
            elseif ($type === 'email') $rules[$key] = array_merge($base, ['email','max:255']);
            elseif ($type === 'date') $rules[$key] = array_merge($base, ['date']);
            else $rules[$key] = array_merge($base, ['string','max:255']);
        }

        return $rules;
    }

    private function canViewLead(Request $request, Lead $lead): bool
    {
        if ($request->user()->hasRole('superadmin') || $request->user()->can('leads.view_all')) {
            return $request->user()->can('leads.view');
        }

        return $request->user()->can('leads.view') && ((int)$lead->created_by === (int)$request->user()->id);
    }

    private function canEditLead(Request $request, Lead $lead): bool
    {
        if ($request->user()->hasRole('superadmin') || $request->user()->can('leads.view_all')) {
            return $request->user()->can('leads.edit');
        }

        return $request->user()->can('leads.edit') && ((int)$lead->created_by === (int)$request->user()->id);
    }

    private function canDeleteLead(Request $request, Lead $lead): bool
    {
        if ($request->user()->hasRole('superadmin') || $request->user()->can('leads.view_all')) {
            return $request->user()->can('leads.delete');
        }

        return $request->user()->can('leads.delete') && ((int)$lead->created_by === (int)$request->user()->id);
    }

    private function getDraft(Request $request): array
    {
        return (array) $request->session()->get('leads_wizard', []);
    }

    private function putDraft(Request $request, array $payload): void
    {
        $draft = $this->getDraft($request);

        foreach ($payload as $k => $v) {
            if ($v === null) {
                unset($draft[$k]);
            } else {
                $draft[$k] = $v;
            }
        }

        $request->session()->put('leads_wizard', $draft);
    }

    private function clearDraft(Request $request): void
    {
        $request->session()->forget('leads_wizard');
    }
}
