<?php

namespace App\Traits;

use App\Models\LeadSubmission;
use App\Models\LeadSubmissionDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait StoresLeadSubmissionDocuments
{
    protected function storeLeadSubmissionDocument(LeadSubmission $leadSubmission, string $docKey, UploadedFile $file, ?string $label = null): LeadSubmissionDocument
    {
        $dir = "lead-submissions/{$leadSubmission->id}/{$docKey}";
        $name = time() . '_' . preg_replace('/[^a-zA-Z0-9\.\-_]/', '_', $file->getClientOriginalName());

        $filePath = $file->storeAs($dir, $name, 'public');

        $data = [
            'lead_submission_id' => $leadSubmission->id,
            'doc_key' => $docKey,
            'file_path' => $filePath,
            'file_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ];
        if ($leadSubmission->service_type_id) {
            $data['service_type_id'] = $leadSubmission->service_type_id;
        }
        if ($label !== null && $label !== '') {
            $data['label'] = $label;
        }
        return LeadSubmissionDocument::create($data);
    }

    protected function deleteLeadSubmissionDocument(LeadSubmissionDocument $doc): void
    {
        if ($doc->file_path) {
            Storage::disk('public')->delete($doc->file_path);
        }
        $doc->delete();
    }
}
