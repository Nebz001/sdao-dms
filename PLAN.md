# PLAN.md — Build Roadmap (feature-first)

Goal: take the SDAO student-org paperwork process online — digital submission,
configurable approval chains, live status, a shared venue calendar — to
eliminate lost documents, manual follow-ups, and scheduling conflicts.

Real email/password auth is DEFERRED. We build the core features against a
seeded identity stub and integrate real auth last. Domain rules live in
CLAUDE.md; follow them, do not restate them.

Build in vertical slices: prove each piece before moving on.

---

## Slice 0 — Seeded dev identity (stub)

Unblocks every feature without real auth.

- Minimal users + roles, seeded: a couple of students, two SDAO members, an
  adviser, a chair and dean per school, the three directors.
- A simple dev login to act as any seeded user.
- Put identity behind an auth interface/boundary so features depend on the
  boundary, not on how identity is provided. Real auth swaps in later (Slice 6).

**Done when:** you can log in as any role and the engine can resolve a role to
a person per school.

---

## Slice 1 — Approval engine core (THE main feature; build as tested logic)

The riskiest part, and pure logic — build and test it with no UI, no SSO.

- A configurable chain (template per form type/variant) driven by data.
- Transitions: approve advances; reject terminates permanently; return-for-
  revision goes to the requester and skips lower-ranked approvers (their
  approvals persist).
- SDAO step requires BOTH members; a split goes back.
- Full transition history.

**Done when:** unit tests cover every transition — advance, permanent reject,
return-to-requester-by-rank, dual-SDAO quorum, split-goes-back — and pass.

---

## Slice 2 — Organization registration end-to-end

Proves the engine inside the product, using the simplest form (short chain).

- Student submission form.
- SDAO review screen: approve / reject / return-for-revision.
- Live status updates (no refresh) and a visible revision history.
- Build the org-officer membership model here (student ↔ org link with role
  and active status, scoped to academic year) — this is where org membership
  first appears.

**Done when:** a registration can be submitted, dual-approved, rejected, or
returned-and-resubmitted, all reflected live, with full history and tests.

---

## Slice 3 — Activity calendar (term plan) + conflict logic

The other pillar; independent of the forms above.

- Org submits a term calendar; short chain (SDAO -> status).
- Each activity has venue + date + start/end time.
- Tentative entries warn only; confirmed entries hard-block.
- Conflict = same venue + date + overlapping time range (CLAUDE.md #6).
- Settle the canonical venue list and approver notifications here.

**Done when:** approved activities hard-block their venue/date/time range,
overlapping bookings are rejected, and same-time/different-venue is allowed.

---

## Slice 4 — Activity proposal (the hard one)

Builds on the engine and the calendar.

- Single submission, two steps: (1) request form, (2) narrative + attachments;
  routing starts after step 2.
- Step 1: pick an approved-calendar activity (on-calendar) OR create one
  outside it (off-calendar).
- Two workflow templates by variant (CLAUDE.md #8): off-calendar moves the
  single SDAO step to the front; both variants have SDAO exactly once.
- A confirmed proposal interacts with the calendar (tentative while under
  review, hard-block once approved).

**Done when:** both variants route correctly through their full chains, the
two-step submission and attachments work, and calendar holds behave correctly.

---

## Slice 5 — Remaining short-chain forms (mop-up)

Quick once the engine is solid.

- Renewal: short chain + at most one renewal per org per academic year.
- After-activity report: short chain, tied to a completed activity.

**Done when:** both route to a final status with history, and the renewal
uniqueness rule is enforced.

---

## Slice 6 — Real email/password auth + admin provisioning + email verification

- Email + password via Fortify; email verification required before an account
  is active.
- Approvers admin-provisioned (created or invited by SDAO admin; never
  self-registered).
- Students self-register into unaffiliated accounts; adviser binds the
  org-officer affiliation before any submission is possible.

**Done when:** real accounts authenticate, email verification gates access,
roles are governed as above, and no feature code needed changing beyond the
auth boundary.

---

## Phase 2 — Remediation

Discovered after manual testing of the completed Slices 0–6. These do not
renumber or replace any slice above; they are sequenced follow-on work.

1. **Account verification gate.** Self-registered students land in an
   Unverified state; SDAO reviews a "Pending Accounts" queue and marks
   Verified/Rejected. Only Verified accounts can submit or be adviser-bound.
2. **Remove dev login entirely.** Delete the dev-login routes, controller, and
   pages — real registration/login becomes the only path (all further testing
   uses real auth).
3. **Wire real notification delivery.** Actual email sending via a mail
   provider, fulfilling the previously-deferred invariant #9 delivery channel —
   the notification trigger already exists from Slice 1; this adds the actual
   send.
4. **One-org-per-student.** Enforce a single active org per student and a
   single in-flight (Draft/In Review/Returned) registration; Approved
   auto-binds the founding student as President and locks them to that org.
5. **Adviser exclusivity / binding-on-approval.** Enforce a single org per
   adviser; bind the adviser only at registration approval, with an
   approve-time re-check guard.
6. **Term-as-global-setting.** Move Activity Calendar term to an
   admin-controlled system-wide setting; new submissions inherit the current
   term, existing ones are frozen.
7. **Exact field corrections across all four form types.** Align every form's
   fields to the client's real physical/template forms (see sdao.md).
8. **Real attachments upload pipeline for registration/renewal.** Implement the
   real upload pipeline with all required attachments enforced (no conditionals).
9. **Section-based revision flagging (universal).** Structured per-section flags
   on every return-for-revision, highlighted for the student on resubmission.
10. **Activity Calendar submit-flow fix + add-multiple-then-submit UX.** Fix
    the submit flow and support adding multiple activities before submitting.
11. **Full UI/UX pass.** Apply the standing UI/UX rule across all screens:
    shadcn/ui consistency, spacing/density, loading & success/error feedback,
    confirmation modals for destructive actions.

---

## Working agreement

- Plan mode before each slice; approve the plan before any code.
- One slice per branch; small, scoped commits.
- Before marking a slice done, a fresh subagent reviews the diff against this
  file and the relevant CLAUDE.md invariants.
