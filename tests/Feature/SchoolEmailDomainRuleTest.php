<?php

use App\Rules\SchoolEmailDomain;

function schoolEmailFails(?string $audience, string $email): bool
{
    $rule = new SchoolEmailDomain($audience);
    $failed = false;

    $rule->validate('email', $email, function () use (&$failed) {
        $failed = true;
    });

    return $failed;
}

test('accepts the configured student domain', function () {
    expect(schoolEmailFails('student', 'juan.delacruz@students.nu-lipa.edu.ph'))->toBeFalse();
});

test('accepts the configured staff domain', function () {
    expect(schoolEmailFails('staff', 'jane.doe@nu-lipa.edu.ph'))->toBeFalse();
});

test('rejects a personal address regardless of audience', function () {
    expect(schoolEmailFails(null, 'someone@gmail.com'))->toBeTrue();
    expect(schoolEmailFails('student', 'someone@gmail.com'))->toBeTrue();
    expect(schoolEmailFails('staff', 'someone@gmail.com'))->toBeTrue();
});

test('a student address fails the staff audience and vice versa', function () {
    expect(schoolEmailFails('staff', 'juan.delacruz@students.nu-lipa.edu.ph'))->toBeTrue();
    expect(schoolEmailFails('student', 'jane.doe@nu-lipa.edu.ph'))->toBeTrue();
});

test('null audience accepts either domain', function () {
    expect(schoolEmailFails(null, 'juan.delacruz@students.nu-lipa.edu.ph'))->toBeFalse();
    expect(schoolEmailFails(null, 'jane.doe@nu-lipa.edu.ph'))->toBeFalse();
});

test('is case-insensitive on the domain', function () {
    expect(schoolEmailFails('staff', 'Jane.Doe@NU-LIPA.EDU.PH'))->toBeFalse();
});

test('rejects a domain that merely contains the school domain as a suffix trick', function () {
    expect(schoolEmailFails(null, 'attacker@nu-lipa.edu.ph.evil.com'))->toBeTrue();
    expect(schoolEmailFails(null, 'attacker@evil-nu-lipa.edu.ph'))->toBeTrue();
});

test('rejects a value with no @ at all', function () {
    expect(schoolEmailFails(null, 'not-an-email'))->toBeTrue();
});

test('the allowed domains are driven by config, not hardcoded', function () {
    config(['school.email_domains.staff' => ['example-staff.test']]);

    expect(schoolEmailFails('staff', 'someone@example-staff.test'))->toBeFalse();
    expect(schoolEmailFails('staff', 'someone@nu-lipa.edu.ph'))->toBeTrue();
});
