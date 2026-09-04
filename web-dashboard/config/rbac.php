<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Super administrators (fail-safe against lockout)
    |--------------------------------------------------------------------------
    |
    | GLPI usernames listed here are ALWAYS treated as full Administrators,
    | regardless of what the database says. Set this to your own GLPI username
    | before rolling out RBAC so you can always reach the Access page and can
    | never be locked out. Comma-separated in the env var.
    */
    'super_admins' => array_filter(array_map('trim', explode(',', (string) env('RBAC_SUPER_ADMINS', '')))),

    /*
    |--------------------------------------------------------------------------
    | Default role
    |--------------------------------------------------------------------------
    |
    | Role name applied to any logged-in user who has NOT been assigned a role.
    | Keeps existing users working (read-only audit access) until an admin
    | assigns them something. Set to an empty string to deny-by-default instead.
    */
    'default_role' => env('RBAC_DEFAULT_ROLE', 'Viewer'),

    /*
    |--------------------------------------------------------------------------
    | Modules & actions (the fixed permission surface)
    |--------------------------------------------------------------------------
    */
    'modules' => [
        'audit_records' => 'Audit asset records',
        'forms'         => 'Forms Tracking (OCR)',
    ],

    'actions' => ['view', 'execute', 'edit', 'delete'],

];
