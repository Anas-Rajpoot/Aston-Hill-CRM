<?php

namespace Database\Seeders;

use App\Models\LibraryCategory;
use App\Models\LibraryDocument;
use Illuminate\Database\Seeder;

class LibrarySeeder extends Seeder
{
    public function run(): void
    {
        // Categories
        $cats = [];
        foreach ([
            ['name' => 'Application Forms', 'slug' => 'application-forms'],
            ['name' => 'NOC Formats',       'slug' => 'noc-formats'],
            ['name' => 'Internal Templates', 'slug' => 'internal-templates'],
            ['name' => 'Support Forms',      'slug' => 'support-forms'],
            ['name' => 'Compliance',         'slug' => 'compliance'],
            ['name' => 'Financial',          'slug' => 'financial'],
            ['name' => 'Field Operations',   'slug' => 'field-operations'],
            ['name' => 'General',            'slug' => 'general'],
        ] as $c) {
            $cats[$c['slug']] = LibraryCategory::firstOrCreate(['slug' => $c['slug']], $c)->id;
        }

        $userId = \App\Models\User::first()?->id ?? 1;

        // Demo docs matching screenshot
        $docs = [
            ['Lead Application Form - Standard',    'application-forms', 'Application Form', ['Lead Submissions'],     'pdf',  4718592,  '15-Jun-2025', 'active'],
            ['NOC Format - Residential',             'noc-formats',       'NOC Format',       ['Field Submissions'],     'docx', 2097152,  '15-Jun-2025', 'active'],
            ['VAS Request Template',                 'internal-templates','Internal Template', ['VAS Requests'],          'xlsx', 1048576,  '14-Jun-2025', 'active'],
            ['Customer Support Ticket Form',         'support-forms',     'Support Form',      ['Customer Support'],      'pdf',  3145728,  '14-Jun-2025', 'active'],
            ['Government Compliance Form - Type A',  'compliance',        'Compliance',        ['Government', 'Finance'], 'pdf',  5242880,  '13-Jun-2025', 'active'],
            ['Client Proposal Template - Premium',   'financial',         'Financial',         ['Clients', 'Sales'],      'pptx', 8388608,  '12-Jun-2025', 'active'],
            ['Field Visit Report Format',            'field-operations',  'Field Operations',  ['Field Submissions'],     'docx', 1572864,  '30-Jun-2024', 'active'],
            ['Old Application Form v1.0',            'general',           'General',           ['Lead Submissions'],      'pdf',  2621440,  '15-Nov-2024', 'inactive'],
        ];

        $i = 1;
        foreach ($docs as $d) {
            LibraryDocument::firstOrCreate(['name' => $d[0]], [
                'document_code' => 'LIB-2026-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'name'          => $d[0],
                'description'   => 'Standard ' . strtolower($d[2]) . ' for ' . strtolower(implode(', ', $d[3])),
                'category_id'   => $cats[$d[1]] ?? null,
                'module_keys'   => $d[3],
                'tags'          => [strtolower($d[2]), 'template'],
                'visibility'    => 'internal',
                'file_type'     => $d[4],
                'mime_type'     => match ($d[4]) { 'pdf' => 'application/pdf', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation', default => 'application/octet-stream' },
                'storage_disk'  => 'public',
                'storage_path'  => 'library/demo-' . $i . '.' . $d[4],
                'size_bytes'    => $d[5],
                'status'        => $d[7],
                'uploaded_by'   => $userId,
                'created_at'    => \Carbon\Carbon::parse($d[6]),
            ]);
            $i++;
        }
    }
}
