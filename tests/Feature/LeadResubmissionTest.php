<?php

use App\Models\LeadSubmission;
use App\Models\LeadSubmissionDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

function leadResubmitUser(): User
{
    $role = Role::where('name', 'superadmin')->where('guard_name', 'web')->first()
        ?? Role::create(['name' => 'superadmin', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'name' => 'Lead Resubmit Tester',
        'email' => 'lead_resubmit_' . uniqid() . '@test.com',
        'status' => 'approved',
        'password' => bcrypt('Password1!'),
    ]);

    $user->assignRole($role);

    return $user;
}

describe('Lead resubmission creates new entry', function () {
    it('creates a new lead row and keeps original unchanged', function () {
        Storage::fake('public');

        $user = leadResubmitUser();

        $originalLead = LeadSubmission::create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'step' => 4,
            'status' => 'rejected',
            'account_number' => 'ACC-001',
            'company_name' => 'Original Company',
            'contact_number_gsm' => '0500000001',
            'payload' => ['source' => 'test'],
            'submission_type' => 'new',
        ]);

        LeadSubmissionDocument::create([
            'lead_submission_id' => $originalLead->id,
            'service_type_id' => null,
            'doc_key' => 'trade_license',
            'file_path' => 'leads/original/trade_license_old.pdf',
            'file_name' => 'trade_license_old.pdf',
            'mime' => 'application/pdf',
            'size' => 1024,
        ]);

        $response = $this->actingAs($user)->post('/api/lead-submissions/' . $originalLead->id . '/resubmit', [
            'action' => 'submit',
            'company_name' => 'Resubmitted Company',
            'contact_number_gsm' => '0500000002',
            'resubmission_reason' => 'Corrected details',
            'previous_activity' => 'Rejected by verifier',
            'documents' => [
                'trade_license' => [
                    UploadedFile::fake()->create('trade_license_new.pdf', 200, 'application/pdf'),
                ],
            ],
        ]);

        $response->assertStatus(200);

        $newLeadId = (int) $response->json('id');
        expect($newLeadId)->toBeGreaterThan(0)->not->toBe($originalLead->id);

        $originalLead->refresh();
        expect($originalLead->company_name)->toBe('Original Company')
            ->and($originalLead->status)->toBe('rejected')
            ->and($originalLead->submission_type)->toBe('new');

        $newLead = LeadSubmission::findOrFail($newLeadId);
        expect($newLead->company_name)->toBe('Resubmitted Company')
            ->and($newLead->contact_number_gsm)->toBe('0500000002')
            ->and($newLead->status)->toBe('unassigned')
            ->and($newLead->submission_type)->toBe('resubmission')
            ->and((int) ($newLead->payload['resubmission_of_id'] ?? 0))->toBe($originalLead->id)
            ->and((bool) ($newLead->payload['is_resubmission'] ?? false))->toBeTrue();

        expect(
            LeadSubmissionDocument::where('lead_submission_id', $originalLead->id)
                ->where('doc_key', 'trade_license')
                ->count()
        )->toBe(1);

        expect(
            LeadSubmissionDocument::where('lead_submission_id', $newLeadId)
                ->where('doc_key', 'trade_license')
                ->count()
        )->toBe(1);
    });
});
