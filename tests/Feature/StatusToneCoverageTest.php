<?php

use App\Enums\AccountStatus;
use App\Enums\DocumentStatus;
use App\Enums\JoinRequestStatus;
use App\Enums\OfficerChangeRequestStatus;
use App\Enums\OrganizationStatus;
use App\Enums\RenewalEligibility;
use App\Enums\TransitionAction;

/**
 * resources/js/lib/status-tones.ts is the one table that gives every status a
 * badge tone. When a PHP enum gains a case, the badge for it would silently
 * fall back to a neutral chip, so this test fails until the case is added to
 * its domain in that file (one `value: { tone, label }` entry per line).
 */
function toneTableDomain(string $domain): string
{
    $source = file_get_contents(base_path('resources/js/lib/status-tones.ts'));

    expect(preg_match('/\n    '.preg_quote($domain, '/').': \{(.*?)\n    \},/s', $source, $matches))->toBe(1);

    return $matches[1];
}

/**
 * @param  array<int, string>  $values
 */
function expectEveryValueToned(string $domain, array $values): void
{
    $block = toneTableDomain($domain);

    foreach ($values as $value) {
        // The needle names the missing status, so a failure points at the exact entry to add.
        expect($block)->toContain("\n        {$value}: { tone: '");
    }
}

test('every document status has a badge tone', function () {
    expectEveryValueToned('document', array_map(fn (DocumentStatus $c) => $c->value, DocumentStatus::cases()));
});

test('every transition action has a badge tone', function () {
    expectEveryValueToned('action', array_map(fn (TransitionAction $c) => $c->value, TransitionAction::cases()));
});

test('every organization status has a badge tone', function () {
    expectEveryValueToned('organization', array_map(fn (OrganizationStatus $c) => $c->value, OrganizationStatus::cases()));
});

test('every account status has a badge tone', function () {
    expectEveryValueToned('account', array_map(fn (AccountStatus $c) => $c->value, AccountStatus::cases()));
});

test('every officer change request and join request status has a badge tone', function () {
    expectEveryValueToned('request', array_map(fn (OfficerChangeRequestStatus $c) => $c->value, OfficerChangeRequestStatus::cases()));
    expectEveryValueToned('request', array_map(fn (JoinRequestStatus $c) => $c->value, JoinRequestStatus::cases()));
});

test('every renewal eligibility state has a badge tone, plus the renewal due flag', function () {
    expectEveryValueToned('renewal', [...array_map(fn (RenewalEligibility $c) => $c->value, RenewalEligibility::cases()), 'due']);
});

test('the tone table only uses the five tones', function () {
    $source = file_get_contents(base_path('resources/js/lib/status-tones.ts'));

    preg_match_all("/tone: '([a-z]+)'/", $source, $matches);

    expect(array_unique($matches[1]))->each->toBeIn(['success', 'info', 'warning', 'destructive', 'neutral']);
});
