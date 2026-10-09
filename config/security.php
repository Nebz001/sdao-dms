<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Content-Security-Policy report-only mode
    |--------------------------------------------------------------------------
    |
    | When true, the policy is sent as Content-Security-Policy-Report-Only:
    | the browser logs violations to its console but blocks nothing. Flip it
    | on in the environment to test safely after a deploy.
    |
    */

    'csp_report_only' => (bool) env('SECURITY_CSP_REPORT_ONLY', false),

];
