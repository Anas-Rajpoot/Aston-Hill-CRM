<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\VasRequestSubmission;
use Illuminate\Http\Request;

class VasRequestController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', VasRequestSubmission::class);

        $q = VasRequestSubmission::query()
            ->visibleTo($request->user())
            ->with(['creator','salesAgent','teamLeader','manager','backOfficeExecutive']);

        if ($request->filled('q')) {
            $q->where(function ($x) use ($request) {
                $x->where('account_number', 'like', "%{$request->q}%")
                  ->orWhere('company_name', 'like', "%{$request->q}%");
            });
        }

        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }

        if ($request->filled('request_type')) {
            $q->where('request_type', $request->request_type);
        }

        if ($request->filled('from')) {
            $q->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $q->whereDate('created_at', '<=', $request->to);
        }

        return $q->orderByDesc('id')->paginate(15);
    }

    public function submit(VasRequestSubmission $vas)
    {
        $this->authorize('submit', $vas);

        if ($vas->status !== 'draft') {
            return response()->json(['message' => 'Only draft can be submitted'], 422);
        }

        $vas->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        return response()->json(['message' => 'VAS request submitted successfully']);
    }

    public function approve(VasRequestSubmission $vas)
    {
        $this->authorize('approve', $vas);

        if ($vas->status !== 'submitted') {
            return response()->json(['message' => 'Only submitted requests can be approved'], 422);
        }

        $vas->update([
            'status' => 'approved',
        ]);

        return response()->json(['message' => 'VAS request approved successfully']);
    }

    public function reject(VasRequestSubmission $vas, Request $request)
    {
        $this->authorize('reject', $vas);

        if ($vas->status !== 'submitted') {
            return response()->json(['message' => 'Only submitted requests can be rejected'], 422);
        }

        $vas->update([
            'status' => 'rejected',
        ]);

        return response()->json(['message' => 'VAS request rejected successfully']);
    }
}
