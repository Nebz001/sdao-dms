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
   conflicts.

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

## Short chains

Registration, renewal, activity calendar, and after-activity report use:
SDAO → final status.

## Key model facts

- Four schools exist: three regular schools plus the Senior High School department.
- A regular school has multiple programs; each program has one program chair;
  each school has exactly one dean. Senior High School has no programs and no
  dean — it has a single principal.
- An organization belongs to one program within a regular school, OR directly
  to Senior High School (which has no programs).
- Organization renewal happens at most once per academic year per org.
- Activity proposal is a SINGLE submission in TWO steps: (1) request form,
  (2) proposal narrative + attachments. Routing begins after step 2.
  Step 1: student picks an approved-calendar activity (on-calendar) OR creates
  one outside it (off-calendar).
- Attachments are stored in separate locations per document type.
- Probation status is intentionally NOT modeled. Do not add it.

## To settle before the CALENDAR slice (not blocking earlier slices)

- A canonical list of venues, so a venue can be identified and hard-blocked.
- How an approver is notified it is their turn (delivers "no follow-ups").

## Identity & accounts (foundational)

- **SSO is deferred.** Identity is a SEEDED STUB during development — fake
  users with roles plus a dev login to act as any of them — behind an auth
  interface/boundary. Build every feature against that boundary, NOT against
  SSO. Swapping the stub for SSO must be a localized change.
- Eventual real system: authenticate via school SSO over school emails; do not
  build custom password storage; restrict logins to the school domain(s).
- Authentication ≠ authorization. A valid login grants no power until a role
  is assigned.
- Staff/approver roles are assigned by SDAO/admin and are never self-claimed.
- A student's claim to represent an org is verified (by adviser or SDAO) before
  they can submit on that org's behalf.

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

## Commands

- **Run tests:** `php artisan test --compact` (filter: `--filter=testName`)
- **Run dev server:** `composer run dev` (runs Vite + PHP server together)
- **Format PHP:** `vendor/bin/pint --dirty --format agent`
- **Lint JS:** `npm run lint`
- Never commit secrets or `.env` files.

## Build order (vertical slices — see PLAN.md)

stub identity → engine core (tested) → registration → calendar →
activity proposal → remaining short-chain forms → real SSO

- Use plan mode for any non-trivial feature; get the plan approved before edits.
- Keep commits small and scoped to one slice/feature.
- After a slice, review the diff in a fresh subagent against PLAN.md before
  considering it done. Validate with tests, not just "it runs."
- When something here turns out wrong or incomplete, update this file.
