<laravel-boost-guidelines>
=== .ai/sdao rules ===

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
activity proposal → remaining short-chain forms → real email/password auth

- Use plan mode for any non-trivial feature; get the plan approved before edits.
- Keep commits small and scoped to one slice/feature.
- After a slice, review the diff in a fresh subagent against PLAN.md before
  considering it done. Validate with tests, not just "it runs."
- When something here turns out wrong or incomplete, update this file.

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v3
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/wayfinder (WAYFINDER) - v0
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/react (INERTIA_REACT) - v3
- react (REACT) - v19
- tailwindcss (TAILWINDCSS) - v4
- @laravel/vite-plugin-wayfinder (WAYFINDER_VITE) - v0
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>
