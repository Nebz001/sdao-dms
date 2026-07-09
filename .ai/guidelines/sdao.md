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

8. **Activity proposal chains vary by on/off-calendar flag AND by school
   structure.** SDAO appears exactly ONCE in every variant (both members
   required, per #3). Off-calendar moves the single SDAO step to the front — it
   is NOT an extra step, just relocated because the activity is outside the
   approved calendar. Each variant below is its own workflow template (chains
   are configuration), not a special-case branch in code.

   Regular school (program chair from the org's program, dean from its school):
   - *On-calendar*: adviser → program chair → dean → SDAO → asst. director of
     academic services → academic director → executive director
   - *Off-calendar*: SDAO → adviser → program chair → dean → asst. director of
     academic services → academic director → executive director

   Senior High School (single principal replaces both chair and dean steps):
   - *On-calendar*: adviser → principal → SDAO → asst. director of academic
     services → academic director → executive director
   - *Off-calendar*: SDAO → adviser → principal → asst. director of academic
     services → academic director → executive director

9. **Approver notification on every hand-off.** When the engine advances a
   document to the next approver, it fires a notification to that approver
   immediately — build this as part of the engine now, not later. Only the
   delivery channel (personal email via a transactional email provider) is
   deferred to the auth slice; the notification trigger itself is not deferred.
   This is how "no follow-ups" is enforced.

## Short chains

Registration, renewal, activity calendar, and after-activity report use:
SDAO → final status.

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
- Organization renewal happens at most once per academic year per org. Renewal
  does NOT start from scratch — it carries forward the organization's previous
  data. The prior year's record is preserved (one record per academic year),
  never deleted or overwritten.
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
  (consistent with the renewal "preserve per academic year" rule).

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
  (Nature/Type), Partner Orgs & SDG, Budget, Schedule & Venue, General
- **Proposal narrative (step 2):** Objectives, Activity Description, Budget,
  Resource Person, General
- **Activity Calendar:** each activity row is its own flaggable unit (no shared
  sections)
- **After-Activity Report:** Event Details, Summary/Program, Evaluation,
  Attachments, General

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

### Activity Calendar term is a global, admin-controlled setting
Not a per-submission dropdown. SDAO/admin sets the current term system-wide. New
calendar submissions always use the current term automatically; existing,
already-submitted calendars retain the term they were submitted under and are
never changed by a later term update.

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
  Target SDG, Proposed Budget, Budget Source, Date of Activity, Venue.
- **Proposal narrative (step 2):** Project/Activity Title, Proposed Date(s),
  Proposed Time, Venue, Objectives, Criteria/Mechanics, Program Flow, Proposed
  Budget, Source of Funding, Expenses, Resume of Resource Person(s) if
  applicable.
- **Activity Calendar:** RSO Name, Date, Activity Name, SDG, Venue,
  Participant/Program Assigned, Budget. Status and Date Received are NOT
  user-input fields — derive Status from the document's actual approval status
  and Date Received from its actual submission timestamp.
- **After-Activity Report:** Name of Event, Date and Time of Event, Activity
  Chair/s, Prepared By, Date Submitted, Summary, Program, Photos (attachment),
  Activity Evaluation Report (% target participants + sample eval form,
  attachment), Attendance Sheet (attachment).

## Commands

- **Run tests:** `php artisan test --compact` (filter: `--filter=testName`)
- **Run dev server:** `composer run dev` (runs Vite + PHP server together)
- **Format PHP:** `vendor/bin/pint --dirty --format agent`
- **Lint JS:** `npm run lint`
- Never commit secrets or `.env` files.

## Build order (vertical slices — see PLAN.md)

stub identity → engine core (tested) → registration → calendar →
activity proposal → remaining short-chain forms → real email/password auth

- Use plan mode for any non-trivial feature; get the plan approved before edits.
- Keep commits small and scoped to one slice/feature.
- After a slice, review the diff in a fresh subagent against PLAN.md before
  considering it done. Validate with tests, not just "it runs."
- When something here turns out wrong or incomplete, update this file.
