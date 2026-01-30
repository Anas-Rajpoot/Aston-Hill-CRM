<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Field Submission – Role keys for team dropdowns
    |--------------------------------------------------------------------------
    | Super admin can add/edit/delete roles in the system. These keys map
    | the field submission form dropdowns to Spatie role names. Users with
    | the given role will appear in the corresponding dropdown.
    | Leave null or use a role name that doesn't exist to show no users.
    */

    'team_roles' => [
        'manager' => 'manager',           // role name for "Manager Name" dropdown
        'team_leader' => 'team_leader',    // role name for "Team Leader Name" dropdown
        'sales_agent' => 'sales_agent',    // role name for "Sales Agent Name" dropdown
    ],

];
