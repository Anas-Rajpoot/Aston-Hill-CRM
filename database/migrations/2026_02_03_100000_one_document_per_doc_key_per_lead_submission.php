<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Enforce one document per doc_key per lead_submission (e.g. one Trade License per lead).
 * Removes duplicate rows (keeps latest by id), deletes their files, then adds unique index.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = 'lead_submission_documents';

        // Find duplicate (lead_submission_id, doc_key) and keep only the row with max(id)
        $duplicates = DB::table($table)
            ->select('lead_submission_id', 'doc_key', DB::raw('MAX(id) as keep_id'))
            ->groupBy('lead_submission_id', 'doc_key')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $row) {
            $toDelete = DB::table($table)
                ->where('lead_submission_id', $row->lead_submission_id)
                ->where('doc_key', $row->doc_key)
                ->where('id', '!=', $row->keep_id)
                ->get();

            foreach ($toDelete as $doc) {
                if (!empty($doc->file_path)) {
                    Storage::disk('public')->delete($doc->file_path);
                }
                DB::table($table)->where('id', $doc->id)->delete();
            }
        }

        // Re-add unique constraint so one document per doc_key per lead
        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->unique(['lead_submission_id', 'doc_key'], 'lead_submission_documents_lead_submission_id_doc_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('lead_submission_documents', function (Blueprint $blueprint) {
            $blueprint->dropUnique('lead_submission_documents_lead_submission_id_doc_key_unique');
        });
    }
};
