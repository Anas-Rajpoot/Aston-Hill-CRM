<?php

namespace App\Services;

use App\Models\VasRequestDocument;
use App\Models\VasRequestSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VasRequestService
{
    public function create(array $data, int $userId): VasRequestSubmission
    {
        return DB::transaction(function () use ($data, $userId) {
            return VasRequestSubmission::create([
                ...$data,
                'created_by' => $userId,
                'status' => 'draft',
            ]);
        });
    }

    public function submit(VasRequestSubmission $submission): VasRequestSubmission
    {
        $submission->submit();
        return $submission->fresh();
    }

    public function storeDocument(VasRequestSubmission $submission, string $docKey, $file, ?string $label = null): void
    {
        $dir = 'vas-requests/' . $submission->id;
        $path = $file->store($dir, 'public');
        $submission->documents()->create([
            'doc_key' => $docKey,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'label' => $label,
        ]);
    }

    public function deleteDocument(VasRequestSubmission $submission, int $documentId): void
    {
        $doc = VasRequestDocument::where('vas_request_submission_id', $submission->id)
            ->where('id', $documentId)
            ->firstOrFail();
        if ($doc->file_path && Storage::disk('public')->exists($doc->file_path)) {
            Storage::disk('public')->delete($doc->file_path);
        }
        $doc->delete();
    }
}
