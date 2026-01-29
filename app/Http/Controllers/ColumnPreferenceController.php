<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ColumnPreferenceController extends Controller
{
    public function show(Request $request, string $module)
    {
        $user = $request->user();

        $cacheKey = "col_pref_{$user->id}_{$module}";

        $preference = Cache::remember($cacheKey, 3600, function () use ($user, $module) {
            return UserColumnPreference::where('user_id', $user->id)
                ->where('module', $module)
                ->first();
        });

        return response()->json([
            'all_columns' => ModuleColumnService::columns($module),
            'visible_columns' => $preference?->columns
                ?? ModuleColumnService::defaultColumns($module),
        ]);
    }

    public function store(SaveColumnPreferenceRequest $request, string $module)
    {
        $data = $request->validate([
            'visible_columns'=>'required|array|min:1'
        ]);
        
        UserColumnPreference::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'module' => $module,
            ],
            [
                'columns' => $request->columns,
            ]
        );

        Cache::forget("col_pref_{$request->user()->id}_{$module}");

        return response()->json(['success' => true]);
    }
}

