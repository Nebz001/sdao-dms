<?php

use App\Enums\OfficerChangeRequestStatus;
use App\Enums\OfficerPosition;
use App\Models\OfficerChangeRequest;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\OfficerChangeDeclinedNotification;
use App\Notifications\OfficerSeatEndedNotification;
use App\Organizations\Admin\DeclineOfficerChange;
use App\Organizations\BindOrganizationOfficer;
use App\Organizations\RequestOfficerChange;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipSeeder;
use Database\Seeders\WorkflowTemplateSeeder;
use Illuminate\Support\Facades\Notification;

/**
 * What the notices actually SAY, everywhere a recipient can read them — mail
 * subject, mail body (which is also the lock-screen preview), the in-app
 * title, and the in-app body shown in the bell.
 */
beforeEach(function () {
    $this->seed([IdentitySeeder::class, WorkflowTemplateSeeder::class, MembershipSeeder::class]);
    $this->org = Organization::where('name', 'Computing Society')->firstOrFail();
    $this->adviser = User::where('email', 'adviser-one@nu-lipa.edu.ph')->firstOrFail();
    $this->sdao = User::where('email', 'sdao-a@nu-lipa.edu.ph')->firstOrFail();
    $this->president = User::where('email', 'student-alpha@students.nu-lipa.edu.ph')->firstOrFail();
});

function everyVisibleString(object $notification, User $recipient): string
{
    $mail = $notification->toMail($recipient);

    return implode("\n", [
        $mail->envelope()->subject,                 // lock-screen / inbox subject
        strip_tags($mail->render()),                // body, i.e. the preview text clients derive from it
        implode("\n", array_map('strval', array_filter($notification->toArray($recipient), 'is_scalar'))), // in-app title + bell body + the rest
    ]);
}

// ── Seat ended: nothing about the replacement or a request ──────────────────

test('the seat-ended notice never reveals who replaced the officer — subject, preview, in-app title or bell body', function () {
    Notification::fake();
    $replacement = User::factory()->create(['name' => 'Zyxwv Replacementson', 'email' => 'zyxwv@example.test', 'account_status' => 'verified']);

    app(BindOrganizationOfficer::class)->execute($this->adviser, $this->org, $replacement, OfficerPosition::President);

    $captured = null;
    Notification::assertSentTo($this->president, OfficerSeatEndedNotification::class, function ($n) use (&$captured) {
        $captured = $n;

        return true;
    });

    $everything = everyVisibleString($captured, $this->president);

    expect($everything)
        ->not->toContain('Zyxwv')
        ->not->toContain('Replacementson')
        ->not->toContain('zyxwv@example.test')
        ->not->toContain('nominee')
        ->not->toContain('reason');
    // What it DOES say is only what the officer already knows.
    expect($captured->toMail($this->president)->envelope()->subject)->toBe('Your President seat has ended — Computing Society');
    expect($captured->toArray($this->president)['body'])->toBe('The organization\'s officers were changed.');
});

// ── Decline: the SDAO comment reaches the requester ─────────────────────────

function declinedWithComment(?string $comment): array
{
    $alpha = test()->president;
    $request = app(RequestOfficerChange::class)->execute($alpha, OfficerPosition::Secretary, User::factory()->create(['account_status' => 'verified']));
    app(DeclineOfficerChange::class)->execute(test()->sdao, $request, $comment);

    return [$request->fresh(), new OfficerChangeDeclinedNotification($request->fresh())];
}

test('the decline mail shows SDAO\'s full comment, line by line', function () {
    Notification::fake();
    [, $notification] = declinedWithComment("Not the right fit.\nTry again after the audit.");

    $html = $notification->toMail($this->president)->render();

    expect(strip_tags($html))->toContain('Reason given')->toContain('Not the right fit.')->toContain('Try again after the audit.');
    // Both lines are inside the quoted block, not just the first.
    expect(substr_count($html, '<blockquote'))->toBeGreaterThanOrEqual(1);
    expect(preg_match('/<blockquote.*Not the right fit.*Try again after the audit.*<\/blockquote>/s', $html))->toBe(1);
});

test('a comment with a blank line keeps EVERY paragraph inside the quoted reason', function () {
    Notification::fake();
    // Markdown only lazily continues a quote across a single newline; a blank
    // line ends it. Quoting each line explicitly keeps the second paragraph in
    // the quote instead of letting it read as if it were Laravel's own text.
    [, $notification] = declinedWithComment("First paragraph of the reason.\n\nSecond paragraph of the reason.");

    $html = $notification->toMail($this->president)->render();

    expect(substr_count($html, '<blockquote'))->toBe(1);
    expect(preg_match('/<blockquote.*?First paragraph of the reason\..*?Second paragraph of the reason\..*?<\/blockquote>/s', $html))->toBe(1);
});

test('the in-app decline notice shows the reason too, capped for the bell', function () {
    Notification::fake();
    [, $short] = declinedWithComment('Not the right fit.');
    expect($short->toArray($this->president)['body'])->toBe('Not approved this time. Reason: Not the right fit.');

    // A 2000-character comment (the allowed maximum) is capped in the bell, never dumped whole.
    [$long, $longNotification] = (function () {
        $request = OfficerChangeRequest::factory()->create([
            'organization_id' => $this->org->id,
            'requested_by' => $this->president->id,
            'position' => OfficerPosition::President,
            'nominee_id' => User::factory()->create(['account_status' => 'verified'])->id,
            'status' => OfficerChangeRequestStatus::Pending,
        ]);
        app(DeclineOfficerChange::class)->execute($this->sdao, $request, str_repeat('Long reason. ', 160));

        return [$request->fresh(), new OfficerChangeDeclinedNotification($request->fresh())];
    })();
    $body = $longNotification->toArray($this->president)['body'];
    expect(mb_strlen($body))->toBeLessThan(220)->and($body)->toEndWith('...')->and($body)->toStartWith('Not approved this time. Reason: Long reason.');
    // ...while the mail still carries it in full.
    expect(strip_tags($longNotification->toMail($this->president)->render()))->toContain(trim(str_repeat('Long reason. ', 160)));
});

test('with no comment the notice stays generic and never shows an empty "Reason"', function () {
    Notification::fake();
    [, $notification] = declinedWithComment(null);

    expect($notification->toArray($this->president)['body'])->toBe('Your officer change request was not approved this time.');
    expect(strip_tags($notification->toMail($this->president)->render()))->not->toContain('Reason given');
});

test('declining actually sends the comment-carrying notice to a requester who still holds a seat', function () {
    Notification::fake();
    declinedWithComment('Please wait for the next term.');

    Notification::assertSentTo($this->president, OfficerChangeDeclinedNotification::class, fn ($n) => $n->changeRequest->decision_comment === 'Please wait for the next term.');
});

test('declining a legacy request whose filer has left tells that person nothing — no nominee name, no reason', function () {
    Notification::fake();
    $orphan = OfficerChangeRequest::factory()->create([
        'organization_id' => $this->org->id,
        'requested_by' => User::factory()->create(['account_status' => 'verified'])->id,
        'position' => OfficerPosition::Secretary,
        'nominee_id' => User::factory()->create(['account_status' => 'verified'])->id,
        'status' => OfficerChangeRequestStatus::Pending,
    ]);

    app(DeclineOfficerChange::class)->execute($this->sdao, $orphan, 'Filer left.');

    expect($orphan->fresh()->status)->toBe(OfficerChangeRequestStatus::Declined);
    Notification::assertNothingSent();
});
