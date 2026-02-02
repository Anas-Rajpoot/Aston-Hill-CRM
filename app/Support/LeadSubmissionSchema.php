<?php

namespace App\Support;

use App\Models\ServiceType;

class LeadSubmissionSchema
{
    public static function fields(ServiceType $type): array
    {
        // Expect schema like: ['fields'=>[...], 'documents'=>[...]]
        return $type->schema['fields'] ?? [];
    }

    /** Default document types when schema is empty (matches design) */
    private static array $defaultDocuments = [
        ['key' => 'trade_license', 'label' => 'Trade License', 'required' => true],
        ['key' => 'establishment_card', 'label' => 'Establishment Card', 'required' => false],
        ['key' => 'owner_emirates_id', 'label' => 'Owner Emirates ID', 'required' => true],
        ['key' => 'loa_poa', 'label' => 'LOA / POA', 'required' => false],
        ['key' => 'ejari', 'label' => 'Ejari', 'required' => true],
        ['key' => 'proposal_form', 'label' => 'Proposal Form', 'required' => true],
        ['key' => 'main_application', 'label' => 'Main Application', 'required' => true],
        ['key' => 'customer_confirmation_email', 'label' => 'Customer Confirmation Email', 'required' => true],
        ['key' => 'as_person_eid', 'label' => 'AS Person EID', 'required' => false],
        ['key' => 'rfs_marketing_approvals', 'label' => 'RFS / Marketing / Migration Approvals', 'required' => false],
        ['key' => 'fnp_binder', 'label' => 'FNP Binder', 'required' => true],
        ['key' => 'etisatis_bill', 'label' => 'Etisatis Bill', 'required' => false],
    ];

    public static function documents(ServiceType $type): array
    {
        $docs = $type->schema['documents'] ?? [];
        return ! empty($docs) ? $docs : self::$defaultDocuments;
    }
}
