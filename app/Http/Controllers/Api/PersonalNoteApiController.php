<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PersonalNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonalNoteApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notes = PersonalNote::query()
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->get(['id', 'title', 'body', 'status', 'priority', 'due_date', 'created_at', 'updated_at']);

        return response()->json([
            'data' => $notes->map(fn (PersonalNote $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'body' => $n->body,
                'status' => $n->status,
                'priority' => $n->priority,
                'due_date' => $n->due_date?->format('Y-m-d'),
                'created_at' => $n->created_at?->toIso8601String(),
                'updated_at' => $n->updated_at?->toIso8601String(),
            ]),
        ]);
    }

    public function show(Request $request, PersonalNote $personal_note): JsonResponse
    {
        $this->authorizeCreatorOnly($personal_note);

        return response()->json([
            'id' => $personal_note->id,
            'title' => $personal_note->title,
            'body' => $personal_note->body,
            'status' => $personal_note->status,
            'priority' => $personal_note->priority,
            'due_date' => $personal_note->due_date?->format('Y-m-d'),
            'completed_at' => $personal_note->completed_at?->toIso8601String(),
            'created_at' => $personal_note->created_at?->toIso8601String(),
            'updated_at' => $personal_note->updated_at?->toIso8601String(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
        ]);

        $note = PersonalNote::create([
            'user_id' => $request->user()->id,
            'title' => (string) ($validated['title'] ?? ''),
            'body' => (string) ($validated['body'] ?? ''),
        ]);

        return response()->json([
            'id' => $note->id,
            'title' => $note->title,
            'body' => $note->body,
            'created_at' => $note->created_at?->toIso8601String(),
            'updated_at' => $note->updated_at?->toIso8601String(),
        ], 201);
    }

    public function update(Request $request, PersonalNote $personal_note): JsonResponse
    {
        $this->authorizeCreatorOnly($personal_note);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
        ]);

        $title = array_key_exists('title', $validated) ? (string) ($validated['title'] ?? '') : $personal_note->title;
        $body = array_key_exists('body', $validated) ? (string) ($validated['body'] ?? '') : $personal_note->body;

        $personal_note->update([
            'title' => $title,
            'body' => $body,
        ]);

        return response()->json([
            'id' => $personal_note->id,
            'title' => $personal_note->title,
            'body' => $personal_note->body,
            'updated_at' => $personal_note->updated_at?->toIso8601String(),
        ]);
    }

    public function destroy(Request $request, PersonalNote $personal_note): JsonResponse
    {
        $this->authorizeCreatorOnly($personal_note);

        $personal_note->delete();

        return response()->json(null, 204);
    }

    private function authorizeCreatorOnly(PersonalNote $note): void
    {
        if ($note->user_id !== request()->user()->id) {
            abort(403);
        }
    }
}
