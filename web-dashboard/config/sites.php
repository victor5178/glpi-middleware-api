<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Site aliases (GLPI location <-> scanned site)
    |--------------------------------------------------------------------------
    |
    | Used by Discrepancy Review to compare locations precisely. GLPI location
    | names and the scanned site (company) names are often different labels for
    | the same physical place — list every such label under a shared canonical
    | key so they are treated as equal. Matching is case-insensitive.
    |
    | Any name NOT listed here falls back to a tolerant text compare (either name
    | contains the other), so partial mapping is fine.
    |
    | Use the "Detected site names" panel on the Discrepancy Review page to copy
    | the exact strings to map. Example:
    |
    |   'aliases' => [
    |       'Tawau'       => ['Dai Lieng , Tawau', 'Sabah > Tawau'],
    |       'MASB E-Gate' => ['MASB E-Gate', 'Sabah > Kota Kinabalu > E-Gate'],
    |       'Sandakan'    => ['Dai Lieng , Sandakan', 'Sabah > Sandakan'],
    |   ],
    */

    'aliases' => [
        // 'Canonical site' => ['GLPI location name', 'Scanned site name', ...],
    ],

];
