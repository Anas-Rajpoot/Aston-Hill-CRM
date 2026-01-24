<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PersonalNote;
use Yajra\DataTables\Facades\DataTables;

class PersonalNoteController extends Controller
{
    public function __construct()
    {
        // Use your CrudPermission middleware (module slug = personal_notes)
        // IMPORTANT: your alias is 'crud' in bootstrap/app.php
        $this->middleware('crud:personal_notes');

        // If you also want to block non-approved/2fa, you already have route group middleware.
    }

    public function index()
    {
        return view('personal-notes.index');
    }

    public function datatable(Request $request)
    {
        $user = $request->user();

        $q = PersonalNote::query()->select('personal_notes.*');

        // Owner-only (recommended)
        // If you want superadmin to see all, keep as is:
        if (!$user->hasRole('superadmin')) {
            $q->where('user_id', $user->id);
        }

        $q->filter([
            'status' => $request->status,
            'priority' => $request->priority,
            'from' => $request->from,
            'to' => $request->to,
            'q' => $request->q,
        ]);

        return DataTables::eloquent($q)
            ->editColumn('created_at', fn(PersonalNote $n) => $n->created_at?->format('d-M-Y'))
            ->editColumn('due_date', fn(PersonalNote $n) => $n->due_date?->format('d-M-Y') ?? '—')
            ->addColumn('status_badge', function (PersonalNote $n) {
                return $n->status === 'done' ? 'Done' : 'Pending';
            })
            ->addColumn('priority_badge', function (PersonalNote $n) {
                return ucfirst($n->priority);
            })
            ->addColumn('actions', function (PersonalNote $n) use ($request) {
                $show = route('personal-notes.show', $n);
                $edit = route('personal-notes.edit', $n);
                $del  = route('personal-notes.destroy', $n);
                $toggle = route('personal-notes.toggle', $n);

                $btn = '<div class="flex gap-2 flex-wrap">';
                $btn .= '<a class="px-3 py-1 rounded bg-gray-800 text-white text-xs" href="'.$show.'">View</a>';

                if ($request->user()->can('personal_notes.edit')) {
                    $btn .= '<a class="px-3 py-1 rounded bg-indigo-600 text-white text-xs" href="'.$edit.'">Edit</a>';
                }

                if ($request->user()->can('personal_notes.edit')) {
                    $btn .= '<form method="POST" action="'.$toggle.'" style="display:inline">';
                    $btn .= csrf_field().method_field('PUT');
                    $btn .= '<button class="px-3 py-1 rounded bg-emerald-600 text-white text-xs">'
                          . ($n->status === 'done' ? 'Mark Pending' : 'Mark Done') .
                          '</button>';
                    $btn .= '</form>';
                }

                if ($request->user()->can('personal_notes.delete')) {
                    $btn .= '<form method="POST" action="'.$del.'" onsubmit="return confirm(\'Delete this note?\')" style="display:inline">';
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
        return view('personal-notes.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required','string','max:190'],
            'body' => ['nullable','string'],
            'priority' => ['required','in:low,medium,high'],
            'due_date' => ['nullable','date'],
        ]);

        $data['user_id'] = $request->user()->id;
        $data['status'] = 'pending';

        PersonalNote::create($data);

        return redirect()->route('personal-notes.index')->with('success','Note added');
    }

    public function show(PersonalNote $personal_note)
    {
        $this->authorizeOwnerOrSuperadmin($personal_note);
        return view('personal-notes.show', ['note' => $personal_note]);
    }

    public function edit(PersonalNote $personal_note)
    {
        $this->authorizeOwnerOrSuperadmin($personal_note);
        return view('personal-notes.edit', ['note' => $personal_note]);
    }

    public function update(Request $request, PersonalNote $personal_note)
    {
        $this->authorizeOwnerOrSuperadmin($personal_note);

        $data = $request->validate([
            'title' => ['required','string','max:190'],
            'body' => ['nullable','string'],
            'priority' => ['required','in:low,medium,high'],
            'due_date' => ['nullable','date'],
            'status' => ['required','in:pending,done'],
        ]);

        // set completed_at automatically
        if ($data['status'] === 'done' && $personal_note->status !== 'done') {
            $data['completed_at'] = now();
        }
        if ($data['status'] === 'pending') {
            $data['completed_at'] = null;
        }

        $personal_note->update($data);

        return redirect()->route('personal-notes.show', $personal_note)->with('success','Note updated');
    }

    public function destroy(Request $request, PersonalNote $personal_note)
    {
        $this->authorizeOwnerOrSuperadmin($personal_note);
        $personal_note->delete();

        return redirect()->route('personal-notes.index')->with('success','Note deleted');
    }

    public function toggle(Request $request, PersonalNote $personal_note)
    {
        $this->authorizeOwnerOrSuperadmin($personal_note);

        if ($personal_note->status === 'done') {
            $personal_note->update(['status' => 'pending', 'completed_at' => null]);
        } else {
            $personal_note->update(['status' => 'done', 'completed_at' => now()]);
        }

        return back()->with('success','Status updated');
    }

    private function authorizeOwnerOrSuperadmin(PersonalNote $note): void
    {
        $user = request()->user();
        if ($user->hasRole('superadmin')) return;

        if ($note->user_id !== $user->id) {
            abort(403);
        }
    } 
}
