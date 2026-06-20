# PLAN.md — Build Roadmap (feature-first)

Goal: take the SDAO student-org paperwork process online — digital submission,
configurable approval chains, live status, a shared venue calendar — to
eliminate lost documents, manual follow-ups, and scheduling conflicts.

Real SSO is DEFERRED (no school IT contact yet). We build the core features
against a seeded identity stub and integrate SSO last. Domain rules live in
CLAUDE.md; follow them, do not restate them.

Build in vertical slices: prove each piece before moving on.

---

## Slice 0 — Seeded dev identity (stub)

Unblocks every feature without real auth.

- Minimal users + roles, seeded: a couple of students, two SDAO members, an
  adviser, a chair and dean per school, the three directors.
- A simple dev login to act as any seeded user.
- Put identity behind an auth interface/boundary so features depend on the
  boundary, not on how identity is provided. SSO swaps in later (Slice 6).

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

## Slice 6 — Real SSO + account provisioning (when IT is available)

- Replace the identity stub with school SSO; restrict to the school domain(s).
- Staff/approver roles assigned by SDAO/admin (never self-claimed).
- Students auto-provisioned on first login; org affiliation verified by adviser
  or SDAO before they can submit for that org.

**Done when:** real school accounts log in, roles are governed as above, and no
feature code needed changing beyond the auth boundary.

---

## Working agreement

- Plan mode before each slice; approve the plan before any code.
- One slice per branch; small, scoped commits.
- Before marking a slice done, a fresh subagent reviews the diff against this
  file and the relevant CLAUDE.md invariants.
