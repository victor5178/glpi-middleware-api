<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Form templates
    |--------------------------------------------------------------------------
    |
    | Values shown in the "Form type" dropdown. The stored string must contain a
    | keyword the middleware's FORM_TEMPLATES matcher recognises so the signature
    | pipeline picks the right anchors — e.g. "Form 1" (or "New Computer and
    | Network User") maps to the form1 template (ITS Staff signature detection).
    | "Other" lets the user type a free-text type for anything not listed.
    */
    'templates' => [
        'Form 1 — New Computer & Network User Application',
        'Purchase Requisition',
        'Leave Application',
        'Service Request',
        'Asset Movement / Transfer',
    ],

    /*
    |--------------------------------------------------------------------------
    | Company suggestions (datalist for the "Company" field)
    |--------------------------------------------------------------------------
    |
    | Free-typing is still allowed — these are just autocomplete suggestions.
    | Add the entities your forms come from.
    */
    'companies' => [
        'DAI LIENG MACHINERY SDN BHD',
        'DAI LIENG BERHAD',
        'PIASAU ENGINEERING SDN BHD',
    ],

];
