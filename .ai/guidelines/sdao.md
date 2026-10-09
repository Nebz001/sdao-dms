# SDAO Paperless Documentation System — Project Guidelines

## What this project is

A web-based system that takes NU Lipa SDAO's student-organization paperwork
fully online. Students submit forms digitally; each form routes automatically
through the correct chain of approvers; every status change is reflected live;
a shared calendar prevents double-booking of venues. Goal: no lost documents,
no manual follow-ups, no scheduling conflicts.

Five form types: organization registration, organization renewal,
activity calendar (term plan), activity proposal, after-activity report.
Each activity has a venue, a date, and a start/end time.

## Standing rules (apply to ALL work)

Every frontend change, regardless of size, must consult the `frontend-design`
skill and use the project's existing shadcn/ui component library correctly and
consistently — no ad-hoc styling, no unaligned/unspaced layouts, no empty
dashboard-style placeholder blocks left over from scaffolding. Match the visual
density and spacing already established in working screens (reference: the
populated sidebar with grouped nav sections). Every new screen must include real
user feedback: loading states, success/error confirmation, and confirmation
modals before destructive or hard-to-reverse actions (approve, reject,
deactivate an officer, submit for review). This is a permanent standard, not a
one-time cleanup.

Every save/update/change action in the app must show clear success feedback
when it completes. Use the app's existing flash-toast convention:
`redirect(...)->with('flash', ['message' => '...'])` (read server-side from the
session `flash` key by `HandleInertiaRequests::share()`, and consumed
client-side via `useFlashToast()` / `usePage().props.flash.toast`) — the same
pattern used by every approve/reject/return-for-revision action. Never call
Inertia's native `Inertia::flash(...)`/`->flash(...)` helper for this: it
writes to a different, unread response key (`page.flash`, a sibling of
`page.props`) and produces a silently-successful action with no confirmation.
Fortify-driven flows (passkeys) may instead use their own inline/modal
`onSuccess` feedback, which is equally acceptable. This is a permanent
standard, not a one-time cleanup — apply it to every new save/update/change
action going forward.

Every UI/UX-related task — a bug fix, a new feature, or a polish pass alike —
must explicitly consult the `frontend-design` skill (and any other installed,
relevant UI/UX skill, e.g. `web-design-guidelines`) before implementation, not
only when the user asks for it. This is a permanent standard, not a one-time
cleanup.

Dev login is removed entirely once real authentication is in active use — not
just production-gated. Delete the dev login routes, controller, and pages. All
testing from this point forward uses real registration and login exclusively.

## DOMAIN INVARIANTS — never violate these

These are product rules, not suggestions. Do not "simplify" them away.

1. **Approval chains are configuration, not code.** Steps, order, and the
   role bound to each step live in data (a workflow template per form type /
   variant). Changing personnel or process must NOT require code changes.
   Never hardcode a specific person or a fixed sequence into the engine.

2. **Three approver actions, with exact semantics:**
   - *Approve* → document advances to the next step.
   - *Reject* → document stops permanently. The student cannot revive it;
     they must file a brand-new document.
   - *Return for revision* → document goes back to the student to edit, then
     returns DIRECTLY to the approver who requested the change. Everyone
     ranked BELOW the requester is NOT re-consulted — their approvals persist.
     The document resumes at the requester and continues upward. This is
     deliberate; a large edit is still NOT re-reviewed by lower approvers.

3. **SDAO is two people; their step requires BOTH to approve.** A split
   decision (one approves, one does not) is treated as not-approved and the
   document goes back. Model as a step with two required approvers, not one.

4. **Approvers resolve by role, scoped by program / school.** Four schools
   exist (including Senior High School). A regular school has multiple
   *programs*, each with its own *program chair*, and exactly one *dean*. An
   organization belongs to one program within a school. The chain template
   references the *role*; at routing time the **program chair** resolves from
   the org's program and the **dean** resolves from the org's school. Senior
   High School is structured differently (no programs, no chairs, no dean): it
   has a single **principal** who replaces both the program-chair and dean
   steps — see #8.

5. **Real-time everywhere.** Any approve/reject/return must reflect across all
   clients without a manual refresh.

6. **Calendar: tentative warns, confirmed blocks — on time RANGES.** A
   submission under review shows tentatively and only warns. An approved
   activity HARD-BLOCKS its venue for its date and time range. Two activities
   conflict only if they share a venue and date AND their time ranges OVERLAP
   (A.start < B.end AND B.start < A.end). Same time at a different venue never
   conflicts. **Venue is a free-text value typed by the user; there is no
   canonical or managed venue list.** Conflict detection matches on the entered
   string exactly — the same physical place must be typed consistently for a
   clash to be detected.

7. **Full revision history is kept** for every document across all transitions.

8. **Activity proposal chains vary by school structure ONLY — calendar
   status (on/off) has NO effect on the approval chain.** SDAO appears
   exactly ONCE in every variant (both members required, per #3), in the
   SAME position regardless of on/off-calendar. On/off-calendar is still
   tracked (it drives venue-conflict handling — tentative warning vs. hard
   block — and which step-1 fields are required), it just never changes who
   approves or in what order. Each school-structure/calendar-status
   combination is its own workflow template (chains are configuration), not
   a special-case branch in code — including the on/off-calendar pair, which
   are separate template rows with identical step lists.

   Regular school (program chair from the org's program, dean from its school):
   - *On-calendar and off-calendar (identical):* adviser → program chair →
     dean → SDAO → asst. director of academic services → academic director
     → executive director

   Senior High School (single principal replaces both chair and dean steps):
   - *On-calendar and off-calendar (identical):* adviser → principal → SDAO
     → asst. director of academic services → academic director → executive
     director

   Extra-Curricular org (no college — Phase 2 remediation item 3; skips
   program chair AND dean outright rather than substituting a role):
   - *On-calendar and off-calendar (identical):* adviser → SDAO → asst.
     director of academic services → academic director → executive director

9. **Approver notification on every hand-off.** When the engine advances a
   document to the next approver, it fires a notification to that approver
   immediately — build this as part of the engine now, not later. Only the
   delivery channel (personal email via a transactional email provider) is
   deferred to the auth slice; the notification trigger itself is not deferred.
   This is how "no follow-ups" is enforced. The renewal-season broadcast
   (below) is the second notification trigger in the system, alongside this
   one — both use the same mail+database channel pattern.

## Short chains

Registration, renewal, and activity calendar use: SDAO → final status.

**After-activity report:** adviser → SDAO → final status (client direction; was
SDAO only). SDAO keeps its rules exactly (both members required, split decision
returns), just one step later. The report is routed by the generic engine like
any other form: the organization's adviser resolves by role, and an adviser's
return resumes at the adviser, SDAO's at SDAO. There is no report-specific
branch in code.

**A step with nobody in post is refused before anything is saved — every form.**
`App\Approval\StepApproverGuard` is the one check that the step a document is
about to enter has an active holder (or enough of them: SDAO needs
`required_approvals`). It runs on submit and resubmit (the step that returned it)
and on an approval that would complete its step; the engine calls it before any
write and the five submit and five resubmit actions call it up front, before any
upload, so a refused action leaves no document, file, transition, approval or
notification. It throws `NoApproverForStepException`, which `bootstrap/app.php`
renders as an error toast back on the page (web) or a 422 (mobile api) naming the
missing role — "Your organization has no active adviser. Please contact SDAO."
for an officer, "…SDAO needs to assign one before it can move on." for an
approver. Never a 404 or 500, and never a per-form copy of this check.

**Changing a chain once documents exist — retire, never edit.** A document reads
its steps live from its own template by step position, and transitions,
approvals and wait stats all key off those positions. Renumbering steps of a
template that documents already use would relabel their history. So a chain
change that inserts or reorders steps retires the old template
(`workflow_templates.retired_at`; kept, never picked for a new submission, see
`WorkflowTemplate::active()`) and adds a new one. The after-activity report
change is `2026_10_09_100001_add_adviser_step_to_after_activity_report_workflow`:
reports in review that no approver has acted on, and whose organization has an
active adviser, move to the new template at the adviser step; everything else
(finished, returned, SDAO-started, no adviser) stays on the old one. It never
edits or deletes a transition row (invariant #7). `check:workflow-templates`
and `WorkflowTemplateSeeder` only look at active templates.

**Registration & renewal — digital scope.** The physical NU Lipa
registration/renewal form includes adviser, dean, and CRSO endorsement/receipt
steps and a probation outcome. These are intentionally NOT part of the digital
approval chain (client direction). The digital system reviews organization
registration and renewal via **SDAO only**, with exactly two terminal outcomes
(Approved, Rejected). Do not model CRSO, adviser/dean approval steps, or
probation for these forms. (Probation remains unmodeled system-wide — see the
Key model facts note.)

## Key model facts

- Four schools exist: three regular schools plus the Senior High School department.
- A regular school has multiple programs; each program has one program chair;
  each school has exactly one dean. Senior High School has no programs and no
  dean — it has a single principal.
- An organization belongs to one program within a regular school, OR directly
  to Senior High School (which has no programs).
- Organization renewal happens at most once per academic year, and only during
  the **3rd-term renewal season**. Renewal is not submittable in 1st or 2nd
  term. A renewal filed in 3rd term of academic year X covers X+1. A
  registration approved during 3rd term of X covers BOTH X and X+1 (grace) —
  this is what stops a newly founded org from being asked to renew in the
  season it was just approved in. Renewal does NOT start from scratch — it
  carries forward the organization's previous data. Every prior record is
  preserved (one record per covered year), never deleted or overwritten.
- **Organization activity status**, derived (not stored as a single column):
  **Active** (covered for the current academic year, has an active
  president/secretary), **Needs Renewal** (coverage has lapsed), **Pending
  Review** (not covered, but has a registration/renewal in flight), or
  **Inactive** (never approved with nothing in flight, or covered but with no
  active officers). Orthogonally, `renewalDue` is true during 3rd term for an
  org that hasn't filed for next year yet — a `renewalDue` org is normally
  ALSO Active, since that is the entire point of renewal season; the two are
  not mutually exclusive. `renewalDue` for an org must always agree with
  whether that org can currently submit a renewal — the same predicate, never
  two competing ones.
- **Stamp-timing asymmetry (deliberate, do not unify):** a registration's
  covered-year is stamped at APPROVE time (it records when the org became
  active); a renewal's covered-year is stamped at SUBMIT time (it is the
  uniqueness key preventing a duplicate filing, and must exist while the
  renewal is still in review).
- Activity proposal is a SINGLE submission in TWO steps: (1) request form,
  (2) proposal narrative + attachments. Routing begins after step 2.
  Step 1: student picks an approved-calendar activity (on-calendar) OR creates
  one outside it (off-calendar). The proposal is persisted as a draft from
  step 1 — an abandoned half-finished proposal auto-saves and can be resumed;
  it does not enter the approval chain until step 2 is submitted.
- The after-activity report is hard-linked to the specific **approved**
  activity it reports on. A report cannot exist without a corresponding
  approved activity.
- Attachments are stored in separate locations per document type.
- Probation status is intentionally NOT modeled. Do not add it.
- Org membership is its own entity linking a student to an org with a role
  (president or secretary) and an active status, scoped to an academic year.
  Do NOT model this as president_id/secretary_id columns on the org.
- Both president and secretary can submit documents and receive returned
  documents for their org — they are equal partners, not a hierarchy.
- At most one active president and one active secretary per org at a time
  (a validation rule, not a table constraint).
- On officer turnover, the adviser invites the new officers; old memberships
  are deactivated, never hard-deleted — retained for document history
  (consistent with the renewal "preserve per covered year" rule).

## Document status model

Every document moves through these statuses. The model must be explicit; do
not invent additional statuses or collapse these.

- **Draft** — created but not yet submitted (includes the two-step proposal
  while step 2 is incomplete).
- **In Review** — submitted and currently moving through the approval chain.
- **Returned** — sent back to the student for revision (mid-flow, NOT
  terminal; the student edits and resubmits, then flow resumes at the
  returning approver per invariant #2).
- **Approved** — all required approvals received. **Terminal.**
- **Rejected** — permanently stopped by an approver. **Terminal.** The
  student must file a brand-new document; the rejected document is not
  revived.

Returned is in-progress, not final. There are exactly two terminal statuses:
Approved and Rejected.

## Identity & accounts (foundational)

- **Identity model is settled.** Seeded fake accounts (with assigned roles and
  a dev login to act as any of them) are used for ALL development and testing.
  Real email/password auth (Laravel Fortify) is applied last, in Slice 6 only.
- **Real auth is deferred.** The stub sits behind an auth interface/boundary;
  build every feature against that boundary, NOT against the real auth
  implementation. Swapping the stub for real auth must be a localized change.
- Authentication uses personal email + password via Laravel Fortify. Email
  verification is REQUIRED — it confirms an address is real before an account
  exists.
- Account creation is split by role:
  - Approvers (adviser, program chair, dean, principal, SDAO members, and the
    three directors) are created or invited by SDAO admin. They never
    self-register.
  - Student officers (president, secretary) self-register. Self-registration
    grants only a bare, unaffiliated, email-verified student account — no org,
    no officer role, no ability to submit anything.
- Org affiliation is adviser-initiated: the org's adviser binds a student to
  the org as president or secretary. A student CANNOT submit for an org until
  that binding exists. Trust flows from the trusted adviser account, not from
  the student's claim.
- Authentication ≠ authorization. A valid login grants no power until a role
  is assigned.
- Staff/approver roles are assigned by SDAO/admin and are never self-claimed.
- **Student account verification gate.** Self-registered student accounts are
  created in an "Unverified" state — able to log in, but unable to submit or act
  on anything. SDAO reviews new self-registered accounts via a "Pending
  Accounts" queue and manually marks each Verified or Rejected. Only Verified
  accounts can submit registrations or be adviser-bound as officers.

## Architecture ownership — do not blur these

- **Laravel** owns ALL business logic and is the ONLY write path. The approval
  engine, routing, and calendar-conflict checks are server-authoritative in
  Laravel — never enforced in client code or split into DB policies.
- **Supabase** provides Postgres, Realtime, Storage, and (later) Auth.
- **Clients** (React + React Native) SUBSCRIBE to Supabase Realtime for live
  updates but WRITE through Laravel's API. The Supabase client is
  read/subscribe-only on the frontend.
- Laravel writes to Postgres → Supabase Realtime propagates to clients.
  Add relevant tables to Supabase's realtime publication.
- Attachment uploads are the only direct client→Supabase action, gated by
  signed URLs issued by Laravel.

## Remediation-phase rules & corrections (post-manual-testing)

Discovered during manual testing after Slice 6. Additive to the invariants
above; nothing here relaxes an existing rule.

### One organization per student
A student cannot be an active officer (president/secretary) of more than one
organization at a time, and cannot have more than one organization registration
in Draft/In Review/Returned simultaneously. They may attempt a new registration
only after a prior one is Rejected (reject frees them to try again) or has no
active officer binding yet. Once a registration is Approved, the founding
student is automatically bound as President and this rule locks them to that org.

### Adviser selection, exclusivity, and binding timing
The registration form's adviser field is a typeahead search against existing
admin-provisioned adviser accounts (never free text, never a new adviser account
created by the student). While selecting, the UI shows a live warning if the
chosen adviser is already assigned to another organization. An adviser may be
assigned to exactly ONE organization at a time — enforced with a hard re-check
at SDAO approval time (race-condition guard, same defensive pattern as
VenueConflictChecker's approve-time re-check from Slice 3), since two students
could pick the same unbound adviser while both applications are pending. The
adviser is only actually bound to the organization at the moment the
registration is Approved — not at submission. If the chosen adviser is the
specific problem, SDAO uses return-for-revision (not reject) so the student can
pick a different one; reject remains available separately for other reasons.

### Changing an organization's adviser

SDAO changes an organization's adviser two ways — assign an existing unassigned
(pool) adviser from the organization page, or create a new adviser account for
the organization — and BOTH end in one action,
`App\Organizations\Admin\AssignOrganizationAdviser`. Never change who is bound
anywhere else.

- **One locked transaction**, same idea as `runSeatChange()`: lock the
  organization row, close the outgoing adviser's `adviser_terms` row, unbind
  their role row (back to the pool — never deleted), bind the incoming adviser
  and open their term. A collision gets a plain validation message, not a 500.
- **History is a table, not a column:** `adviser_terms` (user, organization,
  `started_at`, `ended_at`, who started and who ended it, and how it ended).
  Rows are closed, never deleted. `role_assignments` stays the live source of
  "who is the adviser now"; RoleDirectory is unchanged.
- **Database rules:** partial unique indexes allow one bound adviser per
  organization and one bound organization per adviser, and one open term per
  organization and per user. Unbound pool advisers are unconstrained.
- **SDAO chooses the outgoing adviser's fate:** back to the pool, or
  deactivated in the same transaction. The screens send the adviser the page
  showed; the action refuses if it changed meanwhile (so a confirmation about
  one adviser can never deactivate another).
- **Documents move on their own:** nothing is stored per document, so whatever
  waits at the adviser step belongs to the new adviser the moment the swap
  commits. Because the engine only notifies on a transition, the swap sends the
  new adviser ONE notice (never one per document) listing the documents waiting
  at their step and pending join requests.
- **Notices, after commit and best-effort:** new adviser (what is waiting), old
  adviser (their role ended — NEVER who replaced them; mail only if
  deactivated), the organization's active officers (who the new adviser is).
- **Printed forms keep the adviser who actually signed** (read from the step
  approval); only an unsigned form previews whoever holds the seat now.
- An adviser with no organization is not a reviewer: the proposal review
  entries stay hidden for them, and an organization whose only adviser is
  deactivated counts as having no adviser.

### Section-based revision flagging — universal (all form types, all approvers)
Every return-for-revision action allows the approver to flag one or more specific
sections of the document as needing revision (not just a free-text comment), and
the UI highlights exactly those sections for the student on resubmission. An
approver may flag multiple sections in a single return, freely, with no
restriction. Store this as structured data on the transition (a list of section
keys), not just a comment string. Section definitions per form type:

- **Registration/Renewal:** Contact Information, Organization Details, Adviser
  Selection, Attachments, General
- **Activity Request Form (proposal step 1):** RSO Info, Activity Details
  (Nature/Type), Partner Orgs & SDG, Budget, Schedule & Venue, Request Letter,
  Resume of Resource Person(s), Sample Post-Survey Form, General
- **Proposal narrative (step 2):** Objectives (a single field — was briefly
  split into Overall Goal/Specific Objectives, collapsed back), Activity
  Description (a real, intentional field again — restored under this name,
  not the old `narrative` column — with Criteria/Mechanics and Program Flow
  as its subordinate detail), Budget (Proposed Budget, Budget Source,
  Expenses — no separate Source of Funding; it duplicated Budget Source and
  now just echoes it read-only), Responsible Persons, General
- **Activity Calendar:** each activity row is its own flaggable unit (no shared
  sections)
- **After-Activity Report:** Event Details, Summary/Program, Evaluation,
  Attachments, General

### Approver remarks — approve-time remarks and after-the-fact remarks

Two separate things; neither changes routing, status, or any printed form.

- **Approve remarks:** the Approve confirm dialog has an optional "Remarks"
  field (max 1000). It is stored as `comment` on the `Approved` transition,
  exactly like reject/return comments. `ApprovalEngine::approve()` takes an
  optional `$comment`; the mobile API passes none and is unchanged.
- **Add remark (after approving):** an append-only `document_remarks` table
  (`document_id`, `user_id`, `body` max 1000, `created_at`; no edit, no
  delete, never a fake transition). `POST /documents/{document}/remarks`
  (throttled) is gated by its own `DocumentPolicy::remark()` ability, enforced
  server-side: never derive it from `view()` or `review()`. SDAO members can
  always remark on a non-draft document; any other approver only while the
  document is In Review/Returned and only if they resolve as the approver of a
  step it has already PASSED; nobody but SDAO once it is terminal; officers
  never. `DocumentViewData` merges remarks into the history newest-first (a
  tie puts the remark first) and exposes `view.remark.canAdd`. Adding one
  notifies the org's active officers (fallback: submitter) with
  `DocumentRemarkNotification` (mail + database), worded as a remark, not a
  status change. Remarks use the same timestamp display as every other History
  entry (the viewer's local time via `formatDateTime`), and the bell shows them
  with a message icon.

### Registration/Renewal required attachments — no conditionals
Every attachment listed on the physical form for the relevant application type is
REQUIRED regardless of Organization Type — submission is blocked if any required
attachment is missing. This is the real attachments upload pipeline, no longer
schema-only/deferred for this form type.
- **New:** Letter of Intent, Application Form, By-Laws, Updated List of
  Officers/Founders, Letter from College Dean endorsing the Faculty Adviser,
  List of Proposed Projects with Budget.
- **Renewal:** the same as New, plus List of Past Projects, Financial Statement,
  Summary of Evaluation.

### Activity Proposal step 1 required attachments
Sourced from the physical Activity Request Form's own instruction block (a
single note attached to the request-letter line, not a per-field checklist).
Collected at step 1, alongside the rest of that form's exact fields — none of
these are step-2/narrative attachments.
- **Request Letter** — required. One upload; the physical form's instruction
  is that this letter must itself contain Rationale, Objectives, and Program
  as its content. There is no per-content validation — this app cannot check
  what a PDF contains — the requirement is guidance text to the student, not
  three separate upload fields.
- **Resume of Resource Person(s)** — optional. The physical form calls this
  "Resume of the speaker (for workshops, seminars, etc.)" — same document,
  kept under its existing digital-system key and label (moved here from
  step 2, not duplicated).
- **Sample Post-Survey Form** — required.

### Current period is a global, admin-controlled setting
Not a per-submission dropdown. SDAO/admin sets BOTH the current term AND the
current academic year system-wide, together, via one setting
(`App\Support\CurrentPeriod`) — the academic year is no longer derived from the
wall clock and can be corrected directly. New calendar/proposal submissions
always use the current period automatically; existing, already-submitted
documents retain the period they were submitted (or, for registrations,
approved) under, and are never changed by a later period update.

**Setting the term to 3rd opens organization renewal season.** This is the
signal that renewal is now due: it notifies the active officers of every
organization whose renewal is genuinely due (see the renewal-cadence rule
above), once per academic year — an admin correcting 3rd → 2nd → 3rd within
the same year must not re-notify. Advancing to 1st term (a new academic year)
closes the window again until 3rd term comes round the following year.

### Real names for provisioned roles
SDAO members are Carl Justin Magpantay and Zaira Joy Enayo (already used).
Additionally seed: Pia Jasmin I. Quizon (Assistant Director of Academic
Services), Bernie S. Fabito (Academic Director), Avelino D. Palupit (Executive
Director).

### Exact field corrections per form
Sourced from the client's real physical/template forms. Do not rephrase or
approximate these.
- **Registration/Renewal:** Organization Name, Contact Person, Contact No.,
  Email Address, Date Organized, Purpose of Organization, Type of Organization
  (Co-Curricular / Extra Curricular-Interest Clubs), College.
- **Activity Request Form (proposal step 1):** Name of RSO, Title of Activity,
  Nature of Activity (Co-Curricular / Non-curricular / Community Extension /
  Others), Type of Activity (Seminar/Workshop, General Assembly, Orientation,
  Competition, Recruitment/Audition, Donation Drive/Fundraising Activity,
  Outreach, Off-campus Activity, Others), Partner Organization(s)/School(s)/RSO,
  Target SDG (multi-select — one or more goals per proposal), Proposed Budget,
  Budget Source (RSO Fund / RSO Savings / External), Date of Activity, Venue.
- **Proposal narrative (step 2):** Project/Activity Title, Proposed Date(s),
  Proposed Time, Venue, Objectives (a single field, with both the original
  paper form's "overall goal" and "specific objectives" hint phrases shown
  as placeholder text — briefly split into two columns, then collapsed back),
  Activity Description (the primary field for this section — restored as a
  real, intentional field under this name; NOT a revival of the literal
  `narrative` column dropped earlier, which stays gone), with
  Criteria/Mechanics and Program Flow underneath it as subordinate detail
  (visually secondary, not equal siblings), Proposed Budget, Expenses
  (itemized: Material, Quantity, Unit Price, with an auto-calculated row
  total and grand total — was a flat label/amount row), Resume of Resource
  Person(s) if applicable, Responsible Person(s) (a repeatable list of names
  — typed in directly by the submitting officer, President or Secretary; NOT
  a picker sourced from org membership, since the system only ever tracks
  those two as "members"). There is no separate Source of Funding field at
  step 2 — it duplicated step 1's Budget Source, so step 2 now just displays
  that value read-only instead of asking again.
- **Activity Calendar:** RSO Name, Date, Activity Name, SDG (multi-select —
  one or more goals per activity), Venue, Participant/Program Assigned,
  Budget. Status and Date Received are NOT user-input fields — derive Status
  from the document's actual approval status and Date Received from its
  actual submission timestamp.
- **After-Activity Report:** Name of Event, Date and Time of Event, Activity
  Chair/s, Prepared By, Date Submitted, Summary, Program, Photos (attachment),
  Activity Evaluation Report (% target participants + sample eval form,
  attachment), Attendance Sheet (attachment).

## Commands

- **Run tests:** `php artisan test --compact --exclude-testsuite=Browser`
  (filter: `--filter=testName`) — the Browser suite hangs on
  `NotificationBellClickTest.php`, a known issue.
- **Run dev server:** `composer run dev` (runs Vite + PHP server together)
- **Format PHP:** `vendor/bin/pint --dirty --format agent`
- **Lint JS:** `npm run lint`
- **Page component tests** live in `resources/js/components/__tests__/`, never inside `resources/js/pages/`: the Inertia vite plugin globs `./pages/**/*.tsx` with no test exclusion, so a test file there is bundled as a page chunk and is resolvable as a page name.
- Never commit secrets or `.env` files.

## Production logging (Railway)

- **Production errors reach Railway's logs only through `php-fpm.conf`** (repo
  root). Nixpacks' stock FPM config has no `[global]` section, so FPM
  daemonizes with no `error_log` and discards worker stderr. Ours sets
  `daemonize = no`, `error_log = /proc/self/fd/2`, `log_limit = 8192`,
  `catch_workers_output = yes` and `decorate_workers_output = no`.
- **It is wired in by the Railway dashboard's Custom Start Command, not by
  `nixpacks.toml`** (a dashboard start command overrides any `[start]` block,
  so there is none). The command runs the queue worker and the web server
  together: `php artisan queue:work ... &` then Nixpacks' prestart script,
  then `php-fpm -y /app/php-fpm.conf & nginx -c /nginx.conf`. Keep
  `-y /app/php-fpm.conf` in it; without it logging silently reverts to the
  stock config.
- **Migrations run in the Railway Pre-deploy Command, not on container
  start.** A failing migration fails the deploy before the new container
  takes traffic.
- **Variables:** `LOG_CHANNEL=stack`, `LOG_STACK=single,stderr`,
  `LOG_LEVEL=error`. `storage/logs/laravel.log` is still written.
- Local Laragon never reads `php-fpm.conf`.

## Build order (vertical slices — see PLAN.md)

stub identity → engine core (tested) → registration → calendar →
activity proposal → remaining short-chain forms → real email/password auth

- Use plan mode for any non-trivial feature; get the plan approved before edits.
- Keep commits small and scoped to one slice/feature.
- Never run `git checkout -- .`, `git restore .`, `git clean`, or
  `git reset --hard`: they destroy uncommitted work with no recovery. To
  discard a change, name the single file and look at its diff first.
- After a slice, review the diff in a fresh subagent against PLAN.md before
  considering it done. Validate with tests, not just "it runs."
- When something here turns out wrong or incomplete, update this file.
