<?php

namespace Database\Seeders;

use App\Models\CustomerSupportSubmission;
use App\Models\FieldSubmission;
use App\Models\LeadSubmission;
use App\Models\User;
use App\Models\VasRequestSubmission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class TinkerDemoHierarchySeeder extends Seeder
{
    public function run(): void
    {
        $requiredRoles = [
            'manager',
            'team_leader',
            'sales_agent',
            'back_office',
            'customer_support_representative',
        ];

        $missingRoles = collect($requiredRoles)
            ->reject(fn (string $name): bool => Role::query()->where('name', $name)->exists())
            ->values();

        if ($missingRoles->isNotEmpty()) {
            throw new \RuntimeException('Missing roles: ' . $missingRoles->implode(', ') . '. Please run role/permission seeders first.');
        }

        DB::transaction(function (): void {
            $managers = $this->createManagers(2);
            $teamLeaders = $this->createTeamLeaders(4, $managers);
            $salesAgentsPrimary = $this->createSalesAgents(8, $teamLeaders, 'sa');
            $salesAgentsExtra = $this->createSalesAgents(3, $teamLeaders, 'sax');
            $salesAgents = $salesAgentsPrimary->concat($salesAgentsExtra)->values();

            $this->createBackOfficeUsers(3);
            $this->createCsrUsers(3);

            // Keep reruns idempotent by clearing previous demo records only.
            $this->clearExistingDemoSubmissions();

            foreach ($teamLeaders as $teamLeader) {
                $this->createBundleForActor($teamLeader, 2, $salesAgents);
            }

            foreach ($salesAgents as $salesAgent) {
                $this->createBundleForActor($salesAgent, 3, $salesAgents);
            }
        });

        $this->command?->info('TinkerDemoHierarchySeeder completed successfully.');
        $this->command?->line('- Managers: 2');
        $this->command?->line('- Team Leaders: 4');
        $this->command?->line('- Sales Agents: 11 (8 + 3)');
        $this->command?->line('- Back Office: 3');
        $this->command?->line('- Customer Support Representatives: 3');
    }

    private function createManagers(int $count): Collection
    {
        $users = collect();
        for ($i = 1; $i <= $count; $i++) {
            $users->push($this->upsertUser(
                email: "tk.manager{$i}@example.com",
                name: "TK Manager {$i}",
                roleName: 'manager'
            ));
        }

        return $users;
    }

    private function createTeamLeaders(int $count, Collection $managers): Collection
    {
        $users = collect();
        for ($i = 1; $i <= $count; $i++) {
            $manager = $managers->get(($i - 1) % max($managers->count(), 1));
            $users->push($this->upsertUser(
                email: "tk.teamleader{$i}@example.com",
                name: "TK Team Leader {$i}",
                roleName: 'team_leader',
                managerId: $manager?->id
            ));
        }

        return $users;
    }

    private function createSalesAgents(int $count, Collection $teamLeaders, string $emailPrefix): Collection
    {
        $users = collect();
        for ($i = 1; $i <= $count; $i++) {
            $teamLeader = $teamLeaders->get(($i - 1) % max($teamLeaders->count(), 1));
            $users->push($this->upsertUser(
                email: "tk.{$emailPrefix}{$i}@example.com",
                name: "TK Sales Agent " . strtoupper($emailPrefix) . " {$i}",
                roleName: 'sales_agent',
                managerId: $teamLeader?->manager_id,
                teamLeaderId: $teamLeader?->id
            ));
        }

        return $users;
    }

    private function createBackOfficeUsers(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $this->upsertUser(
                email: "tk.backoffice{$i}@example.com",
                name: "TK Back Office {$i}",
                roleName: 'back_office'
            );
        }
    }

    private function createCsrUsers(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $this->upsertUser(
                email: "tk.csr{$i}@example.com",
                name: "TK CSR {$i}",
                roleName: 'customer_support_representative'
            );
        }
    }

    private function upsertUser(
        string $email,
        string $name,
        string $roleName,
        ?int $managerId = null,
        ?int $teamLeaderId = null
    ): User {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->password = Hash::make('Password@123');
        $user->phone = $user->phone ?: '971500000000';
        $user->country = $user->country ?: 'AE';
        $user->timezone = $user->timezone ?: 'Asia/Dubai';
        $user->status = 'approved';
        $user->email_verified_at = $user->email_verified_at ?: now();
        $user->manager_id = $managerId;
        $user->team_leader_id = $teamLeaderId;
        $user->save();
        $user->syncRoles([$roleName]);

        return $user->fresh();
    }

    private function clearExistingDemoSubmissions(): void
    {
        LeadSubmission::query()->where('company_name', 'like', 'TK Demo %')->delete();
        FieldSubmission::query()->where('company_name', 'like', 'TK Demo %')->delete();
        VasRequestSubmission::query()->where('company_name', 'like', 'TK Demo %')->delete();
        CustomerSupportSubmission::query()->where('company_name', 'like', 'TK Demo %')->delete();
    }

    private function createBundleForActor(User $actor, int $countPerModule, Collection $salesAgents): void
    {
        for ($i = 1; $i <= $countPerModule; $i++) {
            $teamLeaderId = $actor->hasRole('team_leader') ? $actor->id : $actor->team_leader_id;
            $managerId = $actor->manager_id;
            $salesAgentId = $actor->hasRole('sales_agent')
                ? $actor->id
                : $this->pickSalesAgentIdForTeamLeader($teamLeaderId, $salesAgents);

            if (! $salesAgentId) {
                $salesAgentId = $salesAgents->first()?->id;
            }

            if (! $teamLeaderId) {
                $teamLeaderId = $salesAgents->firstWhere('id', $salesAgentId)?->team_leader_id;
            }

            if (! $managerId) {
                $managerId = $salesAgents->firstWhere('id', $salesAgentId)?->manager_id;
            }

            $submittedAt = now()->subMinutes(random_int(30, 5000));
            $accountNumber = 'TK-' . $actor->id . '-' . $i . '-' . random_int(1000, 9999);
            $companyName = "TK Demo {$actor->id}-{$i}";

            LeadSubmission::query()->create([
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
                'step' => 4,
                'status' => 'submitted',
                'account_number' => $accountNumber,
                'company_name' => $companyName,
                'authorized_signatory_name' => $actor->name,
                'contact_number_gsm' => '971500000000',
                'email' => $actor->email,
                'address' => 'Dubai, UAE',
                'emirate' => 'Dubai',
                'product' => 'Mobile Plan',
                'offer' => 'Standard Offer',
                'mrc_aed' => 250,
                'quantity' => 1,
                'sales_agent_id' => $salesAgentId,
                'team_leader_id' => $teamLeaderId,
                'manager_id' => $managerId,
                'submission_type' => 'new',
                'payload' => ['seed' => 'tinker_demo_hierarchy'],
                'submitted_at' => $submittedAt,
                'remarks' => '[TinkerSeedDemo] Lead created by seeder',
            ]);

            FieldSubmission::query()->create([
                'created_by' => $actor->id,
                'company_name' => $companyName,
                'contact_number' => '971500000000',
                'product' => 'Router Installation',
                'emirates' => 'Dubai',
                'complete_address' => 'Business Bay, Dubai, UAE',
                'additional_notes' => '[TinkerSeedDemo] Field submission created by seeder',
                'manager_id' => $managerId,
                'team_leader_id' => $teamLeaderId,
                'sales_agent_id' => $salesAgentId,
                'status' => 'submitted',
                'submitted_at' => $submittedAt,
            ]);

            VasRequestSubmission::query()->create([
                'created_by' => $actor->id,
                'account_number' => $accountNumber,
                'contact_number' => '971500000000',
                'company_name' => $companyName,
                'request_type' => 'Migration',
                'description' => 'Generated by Tinker demo seeder.',
                'additional_notes' => '[TinkerSeedDemo] VAS request created by seeder',
                'status' => 'submitted',
                'sales_agent_id' => $salesAgentId,
                'team_leader_id' => $teamLeaderId,
                'manager_id' => $managerId,
                'submitted_at' => $submittedAt,
            ]);

            CustomerSupportSubmission::query()->create([
                'created_by' => $actor->id,
                'issue_category' => 'General Inquiry',
                'company_name' => $companyName,
                'account_number' => $accountNumber,
                'contact_number' => '971500000000',
                'issue_description' => 'Generated by Tinker demo seeder.',
                'manager_id' => $managerId,
                'team_leader_id' => $teamLeaderId,
                'sales_agent_id' => $salesAgentId,
                'status' => 'submitted',
                'submitted_at' => $submittedAt,
                'internal_remarks' => '[TinkerSeedDemo] Customer support request created by seeder',
            ]);
        }
    }

    private function pickSalesAgentIdForTeamLeader(?int $teamLeaderId, Collection $salesAgents): ?int
    {
        if (! $teamLeaderId) {
            return $salesAgents->first()?->id;
        }

        return $salesAgents
            ->where('team_leader_id', $teamLeaderId)
            ->shuffle()
            ->first()?->id;
    }
}

