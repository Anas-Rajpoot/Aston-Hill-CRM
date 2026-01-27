<?php

namespace App\Services;

class LeadSubmissionService
{
    public function createStep1(array $data, User $user): Lead
    {
        return Lead::create(array_merge($data, [
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'step' => 1,
            'status' => 'draft',
        ]));
    }

    public function updateStep2(Lead $lead, array $data, User $user): Lead
    {
        $lead->update([
            'service_category_id' => $data['service_category_id'],
            'updated_by' => $user->id,
            'step' => max($lead->step, 2),
        ]);
        return $lead;
    }

    public function updateStep3(Lead $lead, array $data, User $user): Lead
    {
        $payload = $lead->payload ?? [];
        $payload = array_merge($payload, $data['payload'] ?? []);

        $lead->update([
            'service_type_id' => $data['service_type_id'],
            'payload' => $payload,
            'updated_by' => $user->id,
            'step' => max($lead->step, 3),
        ]);
        return $lead;
    }

    public function submit(Lead $lead, User $user): Lead
    {
        $lead->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'updated_by' => $user->id,
            'step' => 4,
        ]);
        return $lead;
    }
}
