<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Allowed School Email Domains
    |--------------------------------------------------------------------------
    |
    | Every account in the system must hold an NU Lipa school email address.
    | Students and staff are issued addresses on different domains, so the
    | allow-list is split by audience. Comma-separated in the env vars.
    |
    */

    'email_domains' => [
        'student' => array_filter(explode(',', (string) env('SCHOOL_STUDENT_EMAIL_DOMAINS', 'students.nu-lipa.edu.ph'))),
        'staff' => array_filter(explode(',', (string) env('SCHOOL_STAFF_EMAIL_DOMAINS', 'nu-lipa.edu.ph'))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email Verification Codes
    |--------------------------------------------------------------------------
    |
    | Governs the 6-digit codes sent to confirm ownership of a school email
    | address, on both registration and profile email changes.
    |
    */

    'verification_code' => [
        'ttl_minutes' => 15,
        'max_attempts' => 5,
        'lockout_minutes' => 15,
        'resend_cooldown_seconds' => 60,
    ],

];
