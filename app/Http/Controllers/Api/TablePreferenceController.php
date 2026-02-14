<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserTablePreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TablePreferenceController extends Controller
{
    /**
     * GET /api/table-preferences/{module}
     * Returns the resolved per_page for the authenticated user + module.
     */
    public function show(Request $request, string $module): JsonResponse
    {
        $perPage = UserTablePreference::resolve($request->user()->id, $module);

        return response()->json([
            'module'   => $module,
            'per_page' => $perPage,
            'options'  => UserTablePreference::ALLOWED,
        ]);
    }

    /**
     * POST /api/table-preferences/{module}
     * Set (or update) the user's per-page preference for a module.
     */
    public function store(Request $request, string $module): JsonResponse
    {
        $request->validate([
            'per_page' => ['required', 'integer', Rule::in(UserTablePreference::ALLOWED)],
        ]);

        $pref = UserTablePreference::setPreference(
            $request->user()->id,
            $module,
            (int) $request->input('per_page')
        );

        return response()->json([
            'message'  => 'Preference saved.',
            'module'   => $module,
            'per_page' => $pref->per_page,
        ]);
    }
}
