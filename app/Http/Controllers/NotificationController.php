<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Yajra\DataTables\Facades\DataTables;

class NotificationController extends Controller
{
    public function __construct()
    {
        // $this->middleware('crud:notifications');
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $kind = $request->get('kind');     
        $status = $request->get('status');

        $q = $user->notifications()->latest();

        if ($kind) {
            $q->where('data->kind', $kind);
        }

        if ($status === 'unread') {
            $q->whereNull('read_at');
        } elseif ($status === 'read') {
            $q->whereNotNull('read_at');
        }

        $notifications = $q->paginate(20)->withQueryString();

        return view('notifications.index', compact('notifications'));
    }

    public function datatable(Request $request)
    {
        $user = $request->user();

        $query = $user->notifications()
            ->select('notifications.*');

        // Filters
        if ($request->type) {
            $query->where('data->type', $request->type);
        }

        if ($request->status === 'unread') {
            $query->whereNull('read_at');
        }

        if ($request->status === 'read') {
            $query->whereNotNull('read_at');
        }

        if ($request->from || $request->to) {
            $query->whereBetween('created_at', [
                $request->from ?? now()->subYears(10),
                $request->to ?? now(),
            ]);
        }

        return DataTables::eloquent($query)
            ->editColumn('message', fn ($n) => $n->data['message'] ?? '-')
            ->editColumn('type', fn ($n) => ucfirst(str_replace('_', ' ', $n->data['type'] ?? '-')))
            ->editColumn('status', fn ($n) => $n->read_at ? 'Read' : 'Unread')
            ->editColumn('created_at', fn ($n) => $n->created_at->format('d-M-Y H:i'))
            ->addColumn('actions', function ($n) {
                return '<a href="#" class="text-indigo-600 text-sm">View</a>';
            })
            ->rawColumns(['actions'])
            ->toJson();
    }

    public function markAsRead($id)
    {
        $notification = auth()->user()
            ->notifications()
            ->where('id', $id)
            ->firstOrFail();

        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    public function markUnread(Request $request, string $id)
    {
        $n = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $n->read_at = null;
        $n->save();

        return back()->with('success', 'Marked as unread');
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return back()->with('success', 'All notifications marked as read');
    }

    public function poll(Request $request)
    {
        $user = $request->user();

        $unreadCount = $user->unreadNotifications()->count();

        $top = $user->notifications()
            ->latest()
            ->limit(4)
            ->get()
            ->map(function ($n) {
                $data = $n->data ?? [];
                return [
                    'id' => $n->id,
                    'title' => $data['title'] ?? 'Notification',
                    'message' => $data['message'] ?? '',
                    'url' => $data['url'] ?? route('notifications.index'),
                    'is_unread' => is_null($n->read_at),
                    'created_at' => $n->created_at->format('d-M-Y h:i A'),
                ];
            });

        return response()->json([
            'unreadCount' => $unreadCount,
            'badge' => $unreadCount > 5 ? '5+' : (string)$unreadCount,
            'top' => $top,
        ]);
    }

}
