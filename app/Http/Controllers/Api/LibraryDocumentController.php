<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLibraryDocumentRequest;
use App\Http\Resources\LibraryDocumentResource;
use App\Models\LibraryCategory;
use App\Models\LibraryDocument;
use App\Models\LibraryDocumentVersion;
use App\Models\SystemAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LibraryDocumentController extends Controller
{
    private function canManage($user): bool
    {
        return $user && ($user->hasRole('superadmin') || $user->can('manage-library'));
    }

    private function canView($user): bool
    {
        return $user && ($user->hasRole('superadmin') || $user->can('manage-library') || $user->can('view-library'));
    }

    private function canDownload($user): bool
    {
        return $user && ($user->hasRole('superadmin') || $user->can('manage-library') || $user->can('download-library'));
    }

    /* ── GET /api/library/documents ── */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $this->canView($user)) {
            return response()->json(['message' => 'You do not have permission to view the library.'], 403);
        }

        $query = LibraryDocument::with(['category', 'uploader'])
            ->filter($request->only(['q', 'category_id', 'file_type', 'status', 'visibility', 'uploaded_by', 'date_from', 'date_to']));

        // Non-managers only see documents visible to their role(s) or public documents
        if (! $this->canManage($user)) {
            $userRoles = $user->getRoleNames()->toArray();
            $query->where(function ($q) use ($userRoles) {
                $q->where('visibility', 'public');
                foreach ($userRoles as $role) {
                    $q->orWhereJsonContains('allowed_roles', $role);
                }
            });
            // Non-managers should only see active documents
            $query->where('status', 'active');
        }

        // Sort
        $sortField = 'created_at';
        $sortDir   = 'desc';
        if ($sort = $request->input('sort')) {
            $parts   = explode(':', $sort);
            $allowed = ['name', 'created_at', 'size_bytes', 'status', 'file_type', 'visibility', 'updated_at'];
            $sortField = in_array($parts[0], $allowed) ? $parts[0] : 'created_at';
            $sortDir   = ($parts[1] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        }
        $query->orderBy($sortField, $sortDir);

        $perPage   = min((int) $request->input('per_page', 10), 100);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => LibraryDocumentResource::collection($paginated->items()),
            'meta' => [
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'from'         => $paginated->firstItem(),
                'to'           => $paginated->lastItem(),
                'can_manage'   => $this->canManage($user),
                'can_download' => $this->canDownload($user),
            ],
        ]);
    }

    /* ── GET /api/library/documents/meta ── */
    public function meta(Request $request): JsonResponse
    {
        $data = Cache::remember('library_meta', 600, function () {
            return [
                'categories' => LibraryCategory::orderBy('name')->get(['id', 'name', 'slug']),
                'modules'    => LibraryDocument::distinct()->whereNotNull('module_keys')->pluck('module_keys')->flatten()->unique()->sort()->values(),
                'file_types' => LibraryDocument::FILE_TYPES,
                'roles'      => \Spatie\Permission\Models\Role::where('guard_name', 'web')
                                    ->orderBy('name')
                                    ->pluck('name')
                                    ->unique()
                                    ->values(),
            ];
        });
        return response()->json(['data' => $data]);
    }

    /* ── GET /api/library/documents/{id} ── */
    public function show(Request $request, LibraryDocument $document): JsonResponse
    {
        $user = $request->user();

        if (! $this->canView($user)) {
            return response()->json(['message' => 'You do not have permission to view this document.'], 403);
        }

        // Non-managers can only see documents visible to their role
        if (! $this->canManage($user)) {
            $userRoles = $user->getRoleNames()->toArray();
            $isAllowed = $document->visibility === 'public'
                || ! empty(array_intersect($userRoles, $document->allowed_roles ?? []));
            if (! $isAllowed) {
                return response()->json(['message' => 'You do not have access to this document.'], 403);
            }
        }

        $document->load(['category', 'uploader']);
        return response()->json(['data' => new LibraryDocumentResource($document)]);
    }

    /* ── POST /api/library/documents ── */
    public function store(StoreLibraryDocumentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $file = $request->file('file');

        $mime     = $file->getMimeType();
        $fileType = LibraryDocument::inferFileType($mime);
        $path     = $file->store('library', 'public');
        $size     = $file->getSize();
        $checksum = hash_file('sha256', $file->getRealPath());

        $doc = LibraryDocument::create([
            'document_code' => LibraryDocument::nextCode(),
            'name'          => $data['name'],
            'description'   => $data['description'] ?? null,
            'category_id'   => $data['category_id'] ?? null,
            'module_keys'   => $data['module_keys'] ?? [],
            'tags'          => $data['tags'] ?? [],
            'visibility'    => $data['visibility'],
            'allowed_roles' => $data['allowed_roles'] ?? [],
            'allowed_departments' => $data['allowed_departments'] ?? [],
            'file_type'     => $fileType,
            'mime_type'     => $mime,
            'storage_disk'  => 'public',
            'storage_path'  => $path,
            'size_bytes'    => $size,
            'checksum_sha256' => $checksum,
            'status'        => $data['status'],
            'uploaded_by'   => $request->user()->id,
        ]);

        // Create first version
        $ver = LibraryDocumentVersion::create([
            'document_id'     => $doc->id,
            'version'         => 1,
            'change_note'     => 'Initial upload',
            'storage_disk'    => 'public',
            'storage_path'    => $path,
            'mime_type'       => $mime,
            'size_bytes'      => $size,
            'checksum_sha256' => $checksum,
            'uploaded_by'     => $request->user()->id,
            'created_at'      => now(),
        ]);

        $doc->update(['last_version_id' => $ver->id]);
        LibraryDocument::clearCache();
        Cache::forget('library_meta');

        SystemAuditLog::record('library_document.created', null, ['name' => $doc->name, 'id' => $doc->id], $request->user()->id, 'library_document', $doc->id);

        return response()->json([
            'message' => 'Document uploaded.',
            'data'    => new LibraryDocumentResource($doc->load(['category', 'uploader'])),
        ], 201);
    }

    /* ── PUT /api/library/documents/{id} ── */
    public function update(StoreLibraryDocumentRequest $request, LibraryDocument $document): JsonResponse
    {
        $data = $request->validated();
        $old  = $document->only(['name', 'description', 'category_id', 'status', 'visibility']);

        $document->update([
            'name'          => $data['name'],
            'description'   => $data['description'] ?? $document->description,
            'category_id'   => $data['category_id'] ?? $document->category_id,
            'module_keys'   => $data['module_keys'] ?? $document->module_keys,
            'tags'          => $data['tags'] ?? $document->tags,
            'visibility'    => $data['visibility'],
            'allowed_roles' => $data['allowed_roles'] ?? $document->allowed_roles,
            'allowed_departments' => $data['allowed_departments'] ?? $document->allowed_departments,
            'status'        => $data['status'],
            'updated_by'    => $request->user()->id,
        ]);

        // If new file uploaded, create new version
        if ($request->hasFile('file')) {
            $file     = $request->file('file');
            $mime     = $file->getMimeType();
            $fileType = LibraryDocument::inferFileType($mime);
            $path     = $file->store('library', 'public');
            $size     = $file->getSize();
            $checksum = hash_file('sha256', $file->getRealPath());
            $newVer   = $document->current_version + 1;

            $ver = LibraryDocumentVersion::create([
                'document_id'     => $document->id,
                'version'         => $newVer,
                'change_note'     => $data['change_note'] ?? 'Updated file',
                'storage_disk'    => 'public',
                'storage_path'    => $path,
                'mime_type'       => $mime,
                'size_bytes'      => $size,
                'checksum_sha256' => $checksum,
                'uploaded_by'     => $request->user()->id,
                'created_at'      => now(),
            ]);

            $document->update([
                'file_type'       => $fileType,
                'mime_type'       => $mime,
                'storage_disk'    => 'public',
                'storage_path'    => $path,
                'size_bytes'      => $size,
                'checksum_sha256' => $checksum,
                'current_version' => $newVer,
                'last_version_id' => $ver->id,
            ]);
        }

        LibraryDocument::clearCache();
        Cache::forget('library_meta');

        SystemAuditLog::record('library_document.updated', $old, $data, $request->user()->id, 'library_document', $document->id);

        return response()->json([
            'message' => 'Document updated.',
            'data'    => new LibraryDocumentResource($document->fresh()->load(['category', 'uploader'])),
        ]);
    }

    /* ── PATCH /api/library/documents/{id}/toggle ── */
    public function toggle(Request $request, LibraryDocument $document): JsonResponse
    {
        if (! $this->canManage($request->user())) return response()->json(['message' => 'Unauthorized.'], 403);

        $old = $document->status;
        $new = $old === 'active' ? 'inactive' : 'active';
        $document->update(['status' => $new, 'updated_by' => $request->user()->id]);
        LibraryDocument::clearCache();

        SystemAuditLog::record('library_document.toggled', ['status' => $old], ['status' => $new], $request->user()->id, 'library_document', $document->id);

        return response()->json(['message' => "Status changed to {$new}.", 'data' => new LibraryDocumentResource($document->fresh()->load(['category', 'uploader']))]);
    }

    /* ── DELETE /api/library/documents/{id} ── */
    public function destroy(Request $request, LibraryDocument $document): JsonResponse
    {
        if (! $this->canManage($request->user())) return response()->json(['message' => 'Unauthorized.'], 403);

        $docName = $document->name;
        $docId   = $document->id;

        // Delete file from storage
        $disk = Storage::disk($document->storage_disk);
        if ($disk->exists($document->storage_path)) {
            $disk->delete($document->storage_path);
        }

        // Delete version files
        foreach ($document->versions as $ver) {
            if ($disk->exists($ver->storage_path)) {
                $disk->delete($ver->storage_path);
            }
        }

        $document->versions()->delete();
        $document->delete();
        LibraryDocument::clearCache();

        SystemAuditLog::record('library_document.deleted', null, ['name' => $docName], $request->user()->id, 'library_document', $docId);

        return response()->json(['message' => 'Document deleted.']);
    }

    /* ── GET /api/library/documents/{id}/download ── */
    public function download(Request $request, LibraryDocument $document)
    {
        $user = $request->user();

        if (! $this->canDownload($user)) {
            return response()->json(['message' => 'You do not have permission to download documents.'], 403);
        }

        // Non-managers can only download documents visible to their role
        if (! $this->canManage($user)) {
            $userRoles = $user->getRoleNames()->toArray();
            $isAllowed = $document->visibility === 'public'
                || ! empty(array_intersect($userRoles, $document->allowed_roles ?? []));
            if (! $isAllowed) {
                return response()->json(['message' => 'You do not have access to this document.'], 403);
            }
        }

        $disk = Storage::disk($document->storage_disk);
        if (! $disk->exists($document->storage_path)) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        $ext = pathinfo($document->storage_path, PATHINFO_EXTENSION);
        $filename = \Illuminate\Support\Str::slug($document->name) . '.' . $ext;

        return $disk->download($document->storage_path, $filename);
    }

    /* ── GET /api/library/documents/{id}/versions ── */
    public function versions(LibraryDocument $document): JsonResponse
    {
        $versions = $document->versions()
            ->with('uploader')
            ->orderByDesc('version')
            ->get()
            ->map(fn ($v) => [
                'id'          => $v->id,
                'version'     => $v->version,
                'change_note' => $v->change_note,
                'size_human'  => $v->size_human,
                'uploaded_by' => $v->uploader?->name ?? '—',
                'created_at'  => $v->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $versions]);
    }

    /* ── POST /api/library/documents/bulk-upload ── */
    public function bulkUpload(Request $request): JsonResponse
    {
        if (! $this->canManage($request->user())) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'files'   => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ['required', 'file', 'max:20480'],
        ]);

        $uploaded = [];
        $errors   = [];

        foreach ($request->file('files') as $i => $file) {
            try {
                $mime     = $file->getMimeType();
                $fileType = LibraryDocument::inferFileType($mime);
                $path     = $file->store('library', 'public');
                $size     = $file->getSize();
                $checksum = hash_file('sha256', $file->getRealPath());
                $name     = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

                $doc = LibraryDocument::create([
                    'document_code' => LibraryDocument::nextCode(),
                    'name'          => $name,
                    'category_id'   => $request->input('category_id') ?: null,
                    'module_keys'   => [],
                    'tags'          => [],
                    'visibility'    => 'internal',
                    'file_type'     => $fileType,
                    'mime_type'     => $mime,
                    'storage_disk'  => 'public',
                    'storage_path'  => $path,
                    'size_bytes'    => $size,
                    'checksum_sha256' => $checksum,
                    'status'        => 'active',
                    'uploaded_by'   => $request->user()->id,
                ]);

                $ver = LibraryDocumentVersion::create([
                    'document_id'     => $doc->id,
                    'version'         => 1,
                    'change_note'     => 'Initial upload (bulk)',
                    'storage_disk'    => 'public',
                    'storage_path'    => $path,
                    'mime_type'       => $mime,
                    'size_bytes'      => $size,
                    'checksum_sha256' => $checksum,
                    'uploaded_by'     => $request->user()->id,
                    'created_at'      => now(),
                ]);

                $doc->update(['last_version_id' => $ver->id]);
                $uploaded[] = $doc->name;
            } catch (\Throwable $e) {
                $errors[] = $file->getClientOriginalName() . ': ' . $e->getMessage();
            }
        }

        LibraryDocument::clearCache();
        Cache::forget('library_meta');

        SystemAuditLog::record('library_document.bulk_uploaded', null, [
            'count' => count($uploaded), 'names' => $uploaded,
        ], $request->user()->id, 'library_document');

        return response()->json([
            'message'  => count($uploaded) . ' document(s) uploaded successfully.',
            'uploaded' => $uploaded,
            'errors'   => $errors,
        ], count($uploaded) > 0 ? 201 : 422);
    }

    /* ── GET /api/library/export ── */
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        if (! $user || ! ($user->hasRole('superadmin') || $user->can('export-library'))) {
            abort(403, 'No permission to export.');
        }

        $query = LibraryDocument::with(['category', 'uploader'])
            ->filter($request->only(['q', 'category_id', 'file_type', 'status', 'visibility']))
            ->orderByDesc('created_at')
            ->limit(50000);

        $filename = 'library_export_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Document Name', 'Category', 'Related Module', 'File Type', 'Uploaded By', 'Uploaded On', 'Size', 'Status', 'Document Code', 'Version', 'Tags']);

            $query->cursor()->each(function ($doc) use ($out) {
                fputcsv($out, [
                    $doc->name,
                    $doc->category?->name,
                    implode(', ', $doc->module_keys ?? []),
                    strtoupper($doc->file_type),
                    $doc->uploader?->name,
                    $doc->created_at?->format('Y-m-d'),
                    $doc->size_human,
                    ucfirst($doc->status),
                    $doc->document_code,
                    'v' . $doc->current_version,
                    implode(', ', $doc->tags ?? []),
                ]);
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
