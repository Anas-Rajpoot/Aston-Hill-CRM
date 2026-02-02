<?php

namespace App\Traits;

use App\Models\LeadSubmission;
use App\Models\LeadSubmissionDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait StoresLeadSubmissionDocuments
{
    protected function storeLeadSubmissionDocument(LeadSubmission $leadSubmission, string $docKey, UploadedFile $file): LeadSubmissionDocument
    {
        $dir = "lead-submissions/{$leadSubmission->id}/{$docKey}";
        $name = time() . '_' . preg_replace('/[^a-zA-Z0-9\.\-_]/', '_', $file->getClientOriginalName());

        $path = $file->storeAs($dir, $name, 'public');

        return LeadSubmissionDocument::create([
            'lead_submission_id' => $leadSubmission->id,
            'doc_key' => $docKey,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    protected function deleteLeadSubmissionDocument(LeadSubmissionDocument $doc): void
    {
        if ($doc->path) {
            Storage::disk('public')->delete($doc->path);
        }
        $doc->delete();
    }
}
