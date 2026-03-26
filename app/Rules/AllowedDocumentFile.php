<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Validates document uploads by file extension so .docx etc. are accepted
 * even when the server reports their MIME as application/zip.
 */
class AllowedDocumentFile implements ValidationRule
{
    /** @var list<string> extensions without leading dot */
    public const EXTENSIONS = [
        'pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx', 'csv', 'eml', 'msg',
        'png', 'jpg', 'jpeg', 'gpg', 'gif', 'webp',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('The :attribute must be a file.');
            return;
        }

        $ext = strtolower($value->getClientOriginalExtension());
        if (! in_array($ext, self::EXTENSIONS, true)) {
            $fail('The :attribute field must be a file of type: '.implode(', ', self::EXTENSIONS).'.');
        }
    }
}
