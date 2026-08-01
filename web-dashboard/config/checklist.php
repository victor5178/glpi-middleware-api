<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Category-based audit checklists (STRICT)
    |--------------------------------------------------------------------------
    |
    | The audit form renders ONLY these items per asset category. Any legacy
    | item not listed here (e.g. UPS working, Monitor working under Computer)
    | is dropped. Keys are the audit_results boolean columns.
    |
    | "Asset found on site" (asset_found) is handled separately as a status —
    | it is not a checklist criteria.
    */

    'by_category' => [
        'computer' => [
            'is_physical_good'   => 'Physical Condition',
            'is_patch_latest'    => 'OS latest patches',
            'is_endpoint_latest' => 'Endpoint latest patches',
        ],
        'desktop' => [
            'is_physical_good'   => 'Physical Condition',
            'is_patch_latest'    => 'OS latest patches',
            'is_endpoint_latest' => 'Endpoint latest patches',
        ],
        'laptop' => [
            'is_physical_good'   => 'Physical Condition',
            'is_patch_latest'    => 'OS latest patches',
            'is_endpoint_latest' => 'Endpoint latest patches',
        ],
        'monitor' => [
            'is_physical_good'   => 'Physical Condition',
            'is_monitor_working' => 'Monitor Working',
        ],
        'network' => [
            'is_physical_good' => 'Physical Condition',
            'led_normal'       => 'Normal LEDs light',
        ],
        'networkequipment' => [
            'is_physical_good' => 'Physical Condition',
            'led_normal'       => 'Normal LEDs light',
        ],
        'printer' => [
            'is_physical_good' => 'Physical Condition',
            'led_normal'       => 'Normal LEDs light',
            'no_fault'         => 'Working in good condition',
        ],
    ],

    // Used when the category is unknown / not one of the above.
    'default' => [
        'is_physical_good' => 'Physical Condition',
    ],

    /*
    | Every boolean column a form may submit. Controllers flag all of these
    | (0 when a field isn't rendered for the chosen category) so legacy columns
    | are explicitly cleared and the payload is complete.
    */
    'all_flags' => [
        'asset_found', 'is_physical_good', 'is_patch_latest', 'is_endpoint_latest',
        'is_monitor_working', 'is_ups_working', 'led_normal', 'no_fault',
    ],

];
