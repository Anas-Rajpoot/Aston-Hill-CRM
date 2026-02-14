<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FormDraft;
use App\Models\SystemPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormDraftController extends Controller
{
    /**
     * GET /api/form-drafts/{module}/{recordRef}
     * Fetch a saved draft. Supports ETag / 304.
     */
    public function show(Request $request, string $module, string $recordRef): JsonResponse
    {
        if (! $this->isEnabled()) {
            return response()->json(['data' => null, 'enabled' => false]);
        }

        $draft = FormDraft::findDraft($request->user()->id, $module, $recordRef);

        if (! $draft) {
            return response()->json(['data' => null, 'enabled' => true]);
        }

        $etag = '"' . md5(json_encode($draft->data) . $draft->updated_at) . '"';

        if ($request->header('If-None-Match') === $etag) {
            return response()->json(null, 304)->header('ETag', $etag);
        }

        return response()->json([
            'data'       => $draft->data,
            'updated_at' => $draft->updated_at?->toIso8601String(),
            'enabled'    => true,
        ])->header('ETag', $etag);
    }

    /**
     * POST /api/form-drafts/{module}/{recordRef}
     * Save (upsert) a draft.
     */
    public function store(Request $request, string $module, string $recordRef): JsonResponse
    {
        if (! $this->isEnabled()) {
            return response()->json(['message' => 'Auto-save disabled.', 'enabled' => false], 200);
        }

        $request->validate([
            'data' => 'required|array',
        ]);

        $draft = FormDraft::saveDraft(
            $request->user()->id,
            $module,
            $recordRef,
            $request->input('data')
        );

        return response()->json([
            'message'    => 'Draft saved.',
            'updated_at' => $draft->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * DELETE /api/form-drafts/{module}/{recordRef}
     * Clear a draft (e.g., after successful submit).
     */
    public function destroy(Request $request, string $module, string $recordRef): JsonResponse
    {
        FormDraft::clearDraft($request->user()->id, $module, $recordRef);

        return response()->json(['message' => 'Draft cleared.']);
    }

    /**
     * Check if auto-save is globally enabled.
     */
    private function isEnabled(): bool
    {
        return (bool) SystemPreference::singleton()->auto_save_draft_forms;
    }
}
