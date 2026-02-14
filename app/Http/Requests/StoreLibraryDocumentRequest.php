<?php

namespace App\Http\Requests;

use App\Models\LibraryDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLibraryDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user();
        return $u && ($u->hasRole('superadmin') || $u->can('manage-library'));
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'name'                => 'required|string|max:200',
            'description'         => 'nullable|string|max:2000',
            'category_id'         => 'nullable|exists:library_categories,id',
            'module_keys'         => 'nullable|array',
            'module_keys.*'       => 'string|max:50',
            'tags'                => 'nullable|array',
            'tags.*'              => 'string|max:30',
            'visibility'          => ['required', Rule::in(LibraryDocument::VISIBILITIES)],
            'allowed_roles'       => 'nullable|array',
            'allowed_departments' => 'nullable|array',
            'status'              => ['required', Rule::in(['active', 'inactive'])],
            'file'                => $isUpdate ? 'nullable|file|max:51200' : 'required|file|max:51200',
            'change_note'         => 'nullable|string|max:255',
        ];
    }
}
