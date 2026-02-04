<?php

namespace App\Traits;

use App\Models\FieldSubmission;
use App\Models\FieldSubmissionDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait StoresFieldSubmissionDocuments
{
    protected function storeFieldSubmissionDocument(FieldSubmission $fieldSubmission, string $docKey, UploadedFile $file, ?string $label = null): FieldSubmissionDocument
    {
        $dir = "field-submissions/{$fieldSubmission->id}/{$docKey}";
        $name = time() . '_' . preg_replace('/[^a-zA-Z0-9\.\-_]/', '_', $file->getClientOriginalName());

        $filePath = $file->storeAs($dir, $name, 'public');

        $data = [
            'field_submission_id' => $fieldSubmission->id,
            'doc_key' => $docKey,
            'file_path' => $filePath,
            'file_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ];
        if ($label !== null && $label !== '') {
            $data['label'] = $label;
        }
        return FieldSubmissionDocument::create($data);
    }

    protected function deleteFieldSubmissionDocument(FieldSubmissionDocument $doc): void
    {
        if ($doc->file_path) {
            Storage::disk('public')->delete($doc->file_path);
        }
        $doc->delete();
    }
}
