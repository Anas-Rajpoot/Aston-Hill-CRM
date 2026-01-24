<?php

namespace App\Http\Controllers;

use App\Models\EmailFollowUp;
use App\Models\User;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use App\Http\Requests\EmailFollowUpStoreRequest;
use App\Http\Requests\EmailFollowUpUpdateRequest;

class EmailFollowUpController extends Controller
{
    public function __construct()
    {
        $this->middleware('crud:emails_followup');
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $creators = $user->hasRole('superadmin')
            ? User::select('id','name','email')->orderBy('name')->get()
            : collect();

        $categories = EmailFollowUp::query()
            ->when(!$user->hasRole('superadmin'), fn($q) => $q->where('created_by', $user->id))
            ->select('category')->distinct()->orderBy('category')->pluck('category');

        return view('email-followups.index', compact('creators','categories'));
    }

    public function datatable(Request $request)
    {
        $user = $request->user();

        // permission check for non-resource action
        if (!$user->can('emails_followup.list')) abort(403);

        $query = EmailFollowUp::query()
            ->with(['creator:id,name,email'])
            ->select('email_follow_ups.*');

        // owner-only unless superadmin
        if (!$user->hasRole('superadmin')) {
            $query->where('created_by', $user->id);
        }

        $query->filter([
            'created_by'   => $request->created_by,
            'category'     => $request->category,
            'subject'      => $request->subject,
            'request_from' => $request->request_from,
            'sent_to'      => $request->sent_to,
            'from'         => $request->from,
            'to'           => $request->to,
        ]);

        return DataTables::eloquent($query)
            ->addColumn('created_by_name', function (EmailFollowUp $e) {
                return $e->creator
                    ? $e->creator->name.' ('.$e->creator->email.')'
                    : '-';
            })
            ->editColumn('email_date', fn(EmailFollowUp $e) => optional($e->email_date)->format('d-M-Y'))
            ->addColumn('actions', function (EmailFollowUp $e) use ($request) {
                $user = $request->user();

                $show = route('email-followups.show', $e);
                $edit = route('email-followups.edit', $e);
                $del  = route('email-followups.destroy', $e);

                $btn = '<div class="flex gap-2 flex-wrap">';
                $btn .= '<a class="px-3 py-1 rounded bg-gray-800 text-white text-xs" href="'.$show.'">View</a>';

                if ($user->can('emails_followup.edit')) {
                    $btn .= '<a class="px-3 py-1 rounded bg-indigo-600 text-white text-xs" href="'.$edit.'">Edit</a>';
                }

                if ($user->can('emails_followup.delete')) {
                    $btn .= '<form method="POST" action="'.$del.'" onsubmit="return confirm(\'Delete this record?\')" style="display:inline">';
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

    public function create()
    {
        return view('email-followups.create');
    }

    public function store(EmailFollowUpStoreRequest $request)
    {
        if (!$request->user()->can('emails_followup.create')) abort(403);

        $data = $request->validated();
        $data['created_by'] = $request->user()->id; // ✅ auto add user

        EmailFollowUp::create($data);

        return redirect()->route('email-followups.index')->with('success', 'Email Follow Up added');
    }

    public function show(EmailFollowUp $email_followup)
    {
        $this->authorizeOwnerOrSuperadmin($email_followup);
        $email_followup->load('creator:id,name,email');

        return view('email-followups.show', ['row' => $email_followup]);
    }

    public function edit(EmailFollowUp $email_followup)
    {
        $this->authorizeOwnerOrSuperadmin($email_followup);
        return view('email-followups.edit', ['row' => $email_followup]);
    }

    public function update(EmailFollowUpUpdateRequest $request, EmailFollowUp $email_followup)
    {
        if (!$request->user()->can('emails_followup.edit')) abort(403);

        $this->authorizeOwnerOrSuperadmin($email_followup);

        $email_followup->update($request->validated());

        return redirect()->route('email-followups.show', $email_followup)->with('success', 'Updated');
    }

    public function destroy(Request $request, EmailFollowUp $email_followup)
    {
        if (!$request->user()->can('emails_followup.delete')) abort(403);

        $this->authorizeOwnerOrSuperadmin($email_followup);

        $email_followup->delete();

        return redirect()->route('email-followups.index')->with('success', 'Deleted');
    }

    public function exportCsv(Request $request)
    {
        $user = $request->user();
        if (!$user->can('emails_followup.list')) abort(403);

        // date range: custom + preset (month, quarter, half_year, year)
        [$from, $to] = $this->resolveDateRange($request);

        $query = EmailFollowUp::query()->with('creator:id,name,email');

        if (!$user->hasRole('superadmin')) {
            $query->where('created_by', $user->id);
        }

        $query->filter([
            'created_by'   => $request->created_by,
            'category'     => $request->category,
            'subject'      => $request->subject,
            'request_from' => $request->request_from,
            'sent_to'      => $request->sent_to,
            'from'         => $from,
            'to'           => $to,
        ])->orderBy('email_date','asc');

        $fileName = 'email_followups_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename={$fileName}",
        ];

        $columns = [
            'Email Date',
            'Subject',
            'Category',
            'Request From',
            'Sent To',
            'Comment',
            'Created By',
        ];

        $callback = function () use ($query, $columns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);

            $query->chunk(1000, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [
                        optional($r->email_date)->format('d-M-Y'),
                        $r->subject,
                        $r->category,
                        $r->request_from,
                        $r->sent_to,
                        $r->comment,
                        $r->creator ? ($r->creator->name.' ('.$r->creator->email.')') : '-',
                    ]);
                }
            });

            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function authorizeOwnerOrSuperadmin(EmailFollowUp $row): void
    {
        $user = request()->user();

        if ($user && method_exists($user, 'hasRole') && $user->hasRole('superadmin')) return;

        if (!$user || $row->created_by !== $user->id) abort(403);
    }

    private function resolveDateRange(Request $request): array
    {
        $preset = $request->input('preset'); // this_month, last_month, quarter, last_6_months, this_year, last_year
        $from = $request->input('from');
        $to   = $request->input('to');

        if ($from || $to) return [$from, $to];

        $now = now();

        return match ($preset) {
            'last_month' => [
                $now->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                $now->copy()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ],
            'quarter' => [
                $now->copy()->firstOfQuarter()->toDateString(),
                $now->copy()->lastOfQuarter()->toDateString(),
            ],
            'last_6_months' => [
                $now->copy()->subMonths(6)->toDateString(),
                $now->toDateString(),
            ],
            'last_year' => [
                $now->copy()->subYear()->startOfYear()->toDateString(),
                $now->copy()->subYear()->endOfYear()->toDateString(),
            ],
            'this_year' => [
                $now->copy()->startOfYear()->toDateString(),
                $now->copy()->endOfYear()->toDateString(),
            ],
            default => [
                $now->copy()->startOfMonth()->toDateString(),
                $now->copy()->endOfMonth()->toDateString(),
            ],
        };
    }
}
