<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables;
use App\Models\Announcement;

class AnnouncementController extends Controller
{
    public function __construct()
    {
        // Resource actions permission mapping (your CrudPermission middleware)
        $this->middleware('crud:announcements');

        // datatable/export are NOT resource actions => manual checks
    }

    public function index(Request $request)
    {
        // Everyone with announcements.list can access index.
        // Your crud middleware will check announcements.list automatically for index.
        return view('announcements.index');
    }

    public function datatable(Request $request)
    {
        if (!$request->user()->can('announcements.list')) abort(403);

        $query = Announcement::query()
            ->with('creator:id,name,email')
            ->select('announcements.*')
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at');

        // Filters
        $query->filter([
            'q' => $request->q,
            'active' => $request->active,
            'pinned' => $request->pinned,
            'has_attachment' => $request->has_attachment,
            'from' => $request->from,
            'to' => $request->to,
        ]);

        return DataTables::eloquent($query)
            ->addColumn('created_by_name', function (Announcement $a) {
                return $a->creator ? ($a->creator->name.' ('.$a->creator->email.')') : '-';
            })
            ->addColumn('type', function (Announcement $a) {
                if ($a->attachment_path && str_starts_with((string)$a->attachment_mime, 'image/')) return 'Image';
                if ($a->attachment_path) return 'File';
                return 'Text';
            })
            ->editColumn('created_at', fn(Announcement $a) => optional($a->created_at)->format('d-M-Y h:i A'))
            ->addColumn('status', fn(Announcement $a) => $a->is_active ? 'Active' : 'Inactive')
            ->addColumn('actions', function (Announcement $a) use ($request) {
                $user = $request->user();

                $show = route('announcements.show', $a);
                $edit = route('announcements.edit', $a);
                $del  = route('announcements.destroy', $a);

                $btn = '<div class="flex gap-2 flex-wrap">';
                $btn .= '<a class="px-3 py-1 rounded bg-gray-800 text-white text-xs" href="'.$show.'">View</a>';

                if ($user->can('announcements.edit')) {
                    $btn .= '<a class="px-3 py-1 rounded bg-indigo-600 text-white text-xs" href="'.$edit.'">Edit</a>';
                }

                if ($user->can('announcements.delete')) {
                    $btn .= '<form method="POST" action="'.$del.'" onsubmit="return confirm(\'Delete this announcement?\')" style="display:inline">';
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
        return view('announcements.create');
    }

    public function store(Request $request)
    {
        if (!$request->user()->can('announcements.create')) abort(403);

        $data = $request->validate([
            'title' => ['required','string','max:255'],
            'body' => ['nullable','string'],
            'attachment' => ['nullable','file','max:10240'], // 10MB
            'is_pinned' => ['nullable','boolean'],
            'is_active' => ['nullable','boolean'],
            'published_at' => ['nullable','date'],
        ]);

        $payload = [
            'created_by' => $request->user()->id,
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'is_pinned' => (bool)($request->boolean('is_pinned')),
            'is_active' => (bool)($request->boolean('is_active', true)),
            'published_at' => $data['published_at'] ?? null,
        ];

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');

            $path = $file->store('announcements', 'public');

            $payload['attachment_path'] = $path;
            $payload['attachment_name'] = $file->getClientOriginalName();
            $payload['attachment_mime'] = $file->getMimeType();
            $payload['attachment_size'] = $file->getSize();
        }

        Announcement::create($payload);

        return redirect()->route('announcements.index')->with('success', 'Announcement created');
    }

    public function show(Announcement $announcement)
    {
        // everyone can view if they have announcements.view
        if (!request()->user()->can('announcements.view')) abort(403);

        $announcement->load('creator:id,name,email');

        return view('announcements.show', ['row' => $announcement]);
    }

    public function edit(Announcement $announcement)
    {
        return view('announcements.edit', ['row' => $announcement]);
    }

    public function update(Request $request, Announcement $announcement)
    {
        if (!$request->user()->can('announcements.edit')) abort(403);

        $data = $request->validate([
            'title' => ['required','string','max:255'],
            'body' => ['nullable','string'],
            'attachment' => ['nullable','file','max:10240'],
            'remove_attachment' => ['nullable','boolean'],
            'is_pinned' => ['nullable','boolean'],
            'is_active' => ['nullable','boolean'],
            'published_at' => ['nullable','date'],
        ]);

        $announcement->title = $data['title'];
        $announcement->body  = $data['body'] ?? null;
        $announcement->is_pinned = (bool)($request->boolean('is_pinned'));
        $announcement->is_active = (bool)($request->boolean('is_active', true));
        $announcement->published_at = $data['published_at'] ?? null;

        if ($request->boolean('remove_attachment')) {
            if ($announcement->attachment_path) {
                Storage::disk('public')->delete($announcement->attachment_path);
            }
            $announcement->attachment_path = null;
            $announcement->attachment_name = null;
            $announcement->attachment_mime = null;
            $announcement->attachment_size = null;
        }

        if ($request->hasFile('attachment')) {
            // delete old file
            if ($announcement->attachment_path) {
                Storage::disk('public')->delete($announcement->attachment_path);
            }

            $file = $request->file('attachment');
            $path = $file->store('announcements', 'public');

            $announcement->attachment_path = $path;
            $announcement->attachment_name = $file->getClientOriginalName();
            $announcement->attachment_mime = $file->getMimeType();
            $announcement->attachment_size = $file->getSize();
        }

        $announcement->save();

        return redirect()->route('announcements.show', $announcement)->with('success', 'Announcement updated');
    }

    public function destroy(Request $request, Announcement $announcement)
    {
        if (!$request->user()->can('announcements.delete')) abort(403);

        if ($announcement->attachment_path) {
            Storage::disk('public')->delete($announcement->attachment_path);
        }

        $announcement->delete();

        return redirect()->route('announcements.index')->with('success', 'Announcement deleted');
    }
}
