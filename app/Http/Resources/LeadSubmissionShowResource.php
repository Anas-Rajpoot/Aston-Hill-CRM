<?php

namespace App\Http\Resources;

use App\Models\ServiceCategory;
use App\Models\ServiceType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Single API response for lead submission (wizard + review).
 * Used by GET /api/lead-submissions/{id} and current-draft.
 * Ensures: step, primary info, service category/type, team names, documents.
 */
class LeadSubmissionShowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $step = (int) ($this->step ?? 1);
        $step = $step >= 1 && $step <= 4 ? $step : 1;

        $documents = $this->whenLoaded('documents', function () {
            return $this->documents->map(fn ($doc) => [
                'id' => $doc->id,
                'doc_key' => $doc->doc_key,
                'label' => $doc->label ?? $doc->doc_key,
                'path' => $doc->file_path,
                'original_name' => $doc->file_name,
                'mime' => $doc->mime,
                'size' => $doc->size,
            ])->values()->all();
        }, []);

        return [
            'id' => $this->id,
            'status' => $this->status,
            'step' => $step,
            // Primary info (from DB columns)
            'account_number' => $this->account_number,
            'company_name' => $this->company_name,
            'authorized_signatory_name' => $this->authorized_signatory_name,
            'contact_number_gsm' => $this->contact_number_gsm,
            'alternate_contact_number' => $this->alternate_contact_number,
            'email' => $this->email,
            'address' => $this->address,
            'emirate' => $this->emirate,
            'location_coordinates' => $this->location_coordinates,
            'product' => $this->product,
            'offer' => $this->offer,
            'mrc_aed' => $this->mrc_aed,
            'quantity' => $this->quantity,
            'ae_domain' => $this->ae_domain,
            'gaid' => $this->gaid,
            'remarks' => $this->remarks,
            'request_type' => $this->request_type,
            // Team IDs
            'manager_id' => $this->manager_id,
            'team_leader_id' => $this->team_leader_id,
            'sales_agent_id' => $this->sales_agent_id,
            // Service (Step 2)
            'service_category_id' => $this->service_category_id ? (int) $this->service_category_id : null,
            'service_type_id' => $this->service_type_id ? (int) $this->service_type_id : null,
            // Relation names (for review page)
            'category' => $this->whenLoaded('category', fn () => $this->category ? $this->category->only(['id', 'name']) : null),
            'type' => $this->whenLoaded('type', fn () => $this->type ? $this->type->only(['id', 'name', 'schema']) : null),
            // Always resolve names from service_categories / service_types by ID (used by detail + edit submission)
            'category_name' => $this->service_category_id ? (ServiceCategory::where('id', $this->service_category_id)->value('name') ?? $this->category?->name) : ($this->category?->name ?? null),
            'type_name' => $this->service_type_id ? (ServiceType::where('id', $this->service_type_id)->value('name') ?? $this->type?->name) : ($this->type?->name ?? null),
            'manager_name' => $this->manager?->name,
            'team_leader_name' => $this->teamLeader?->name,
            'sales_agent_name' => $this->salesAgent?->name,
            'creator_name' => $this->creator?->name,
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? $this->creator->only(['id', 'name']) : null),
            'manager' => $this->whenLoaded('manager', fn () => $this->manager ? $this->manager->only(['id', 'name']) : null),
            'team_leader' => $this->whenLoaded('teamLeader', fn () => $this->teamLeader ? $this->teamLeader->only(['id', 'name']) : null),
            'sales_agent' => $this->whenLoaded('salesAgent', fn () => $this->salesAgent ? $this->salesAgent->only(['id', 'name']) : null),
            'documents' => $documents,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'status_changed_at' => $this->status_changed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            // Back office (edit submission form)
            'executive_id' => $this->executive_id,
            'executive_name' => $this->executive?->name,
            'executive' => $this->whenLoaded('executive', fn () => $this->executive ? $this->executive->only(['id', 'name']) : null),
            'call_verification' => $this->call_verification,
            'pending_from_sales' => $this->pending_from_sales,
            'documents_verification' => $this->documents_verification,
            'submission_date_from' => $this->submission_date_from?->format('Y-m-d'),
            'back_office_notes' => $this->back_office_notes,
            'activity' => $this->activity,
            'back_office_account' => $this->back_office_account,
            'work_order' => $this->work_order,
            'du_status' => $this->du_status,
            'completion_date' => $this->completion_date?->format('Y-m-d'),
            'du_remarks' => $this->du_remarks,
            'additional_note' => $this->additional_note,
        ];
    }
}
