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

    /** Default document types when schema is empty – only Trade License is required */
    private static array $defaultDocuments = [
        ['key' => 'trade_license', 'label' => 'Trade License', 'required' => true],
        ['key' => 'establishment_card', 'label' => 'Establishment Card', 'required' => false],
        ['key' => 'owner_emirates_id', 'label' => 'Owner Emirates ID', 'required' => false],
        ['key' => 'loa_poa', 'label' => 'LOA / POA', 'required' => false],
        ['key' => 'ejari', 'label' => 'Ejari', 'required' => false],
        ['key' => 'proposal_form', 'label' => 'Proposal Form', 'required' => false],
        ['key' => 'main_application', 'label' => 'Main Application', 'required' => false],
        ['key' => 'customer_confirmation_email', 'label' => 'Customer Confirmation Email', 'required' => false],
        ['key' => 'as_person_eid', 'label' => 'AS Person EID', 'required' => false],
        ['key' => 'rfs_marketing_approvals', 'label' => 'RFS / Marketing / Migration Approvals', 'required' => false],
        ['key' => 'fnp_binder', 'label' => 'FNP Binder', 'required' => false],
        ['key' => 'etisatis_bill', 'label' => 'Etisatis Bill', 'required' => false],
    ];

    public static function documents(ServiceType $type): array
    {
        $docs = $type->schema['documents'] ?? [];
        $list = ! empty($docs) ? $docs : self::$defaultDocuments;
        // Only Trade License is required; normalize in case type schema had other required flags
        return array_map(fn ($d) => array_merge($d, ['required' => ($d['key'] ?? '') === 'trade_license']), $list);
    }

    /** Default document definitions when lead has no service type (e.g. step 3 documents before type set). Only Trade License required. */
    public static function defaultDocuments(): array
    {
        return self::$defaultDocuments;
    }
}
