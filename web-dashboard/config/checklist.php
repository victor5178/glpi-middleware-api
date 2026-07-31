<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Category-based audit checklists
    |--------------------------------------------------------------------------
    |
    | The audit form renders these checklist items based on the asset's GLPI
    | category. "always" items show for every category; "by_category" adds the
    | category-specific items; "default" is used when the category is unknown.
    | Keys are the audit_results boolean columns.
    */

    'always' => [
        'asset_found' => 'Asset physically found',
    ],

    'by_category' => [
        'computer'         => ['is_physical_good' => 'Physical Condition'],
        'desktop'          => ['is_physical_good' => 'Physical Condition'],
        'laptop'           => ['is_physical_good' => 'Physical Condition'],
        'monitor'          => ['is_physical_good' => 'Physical Condition', 'is_monitor_working' => 'Monitor Working'],
        'network'          => ['is_physical_good' => 'Physical Condition', 'led_normal' => 'Normal LED Light', 'no_fault' => 'No Faulty'],
        'networkequipment' => ['is_physical_good' => 'Physical Condition', 'led_normal' => 'Normal LED Light', 'no_fault' => 'No Faulty'],
    ],

    'default' => [
        'is_physical_good'   => 'Physical condition good',
        'is_patch_latest'    => 'OS patches up to date',
        'is_endpoint_latest' => 'Endpoint protection up to date',
        'is_monitor_working' => 'Monitor working',
        'is_ups_working'     => 'UPS working',
    ],

    /*
    | Every boolean checklist column. Controllers submit all of these (0 when a
    | field isn't rendered for the chosen category) so the payload is complete.
    */
    'all_flags' => [
        'asset_found', 'is_physical_good', 'is_patch_latest', 'is_endpoint_latest',
        'is_monitor_working', 'is_ups_working', 'led_normal', 'no_fault',
    ],

];
