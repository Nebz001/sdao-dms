<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\RoleAssignment;
use App\Support\AcademicYear;
use App\Support\CurrentTerm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var array{message?: string, type?: string, warnings?: array<int, mixed>}|null $flash */
        $flash = $request->session()->get('flash');

        // useDocumentUpdates() polls every 5s via router.reload({ only: [...],
        // async: true }) so it can't interrupt an in-flight approve/reject/
        // return submit. But a poll tick can land between that submit's
        // redirect and the browser's own follow-up GET of the redirect
        // target — an ordinary partial-reload request that never asks for
        // `flash`. Session flash data ages on every request regardless of
        // whether it's rendered, so without this the toast is silently
        // discarded before the page it belongs to ever loads. Reflash it
        // here so it survives to that next request instead.
        if ($flash && $request->header('X-Inertia-Partial-Data')) {
            $request->session()->reflash();
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            // Normalizes the uniform ['message' => ..., 'warnings' => ...] shape
            // that every controller already flashes into a toast-ready prop, so
            // no controller needs to change to emit a { type, message } pair.
            'flash' => $flash ? [
                'toast' => [
                    'type' => $flash['type'] ?? 'success',
                    'message' => $flash['message'] ?? '',
                ],
                'warnings' => $flash['warnings'] ?? null,
                'message' => $flash['message'] ?? null,
            ] : null,
            'auth' => [
                'user' => $request->user(),
                'roles' => $request->user()?->roleAssignments->map(fn (RoleAssignment $ra) => [
                    'role' => $ra->role->value,
                    'school_id' => $ra->school_id,
                    'program_id' => $ra->program_id,
                    'organization_id' => $ra->organization_id,
                ]),
                // The real source of truth for "is a currently active student
                // officer" — OrganizationMembership.is_active is deactivated
                // on turnover, unlike the role_assignments table (no status
                // column at all, never updated once created).
                'isActiveOfficer' => $request->user()?->organizationMemberships()->active()->exists() ?? false,
                // Drives the "Submit Registration" founding-flow CTA (sidebar
                // + dashboard empty state) for a verified student with no
                // organization yet — reuses DocumentPolicy::propose(), the
                // same Gate RegistrationController::create()/store() already
                // authorize against, so eligibility is computed once, in
                // Laravel, and never re-derived on the client.
                'canProposeOrganization' => Gate::allows('propose', Organization::class),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Persistent navbar context (admin dashboard header). Both are
            // cheap: AcademicYear::current() is a pure date computation, and
            // CurrentTerm::get() is cached (see its docblock) so this adds no
            // per-request DB cost beyond the first call after a term change.
            'currentTerm' => CurrentTerm::get()->label(),
            'academicYear' => AcademicYear::current(),
            // A closure, not a resolved value — Inertia only evaluates
            // callable props when they're actually included in the
            // response. The notification bell fetches this key by name on
            // open (`only: ['notifications']`); left as a raw array, this
            // query would also run on every 5s useDocumentUpdates() poll
            // (`only: ['document','history','queue']`) even though that
            // poll never asks for it.
            'notifications' => fn () => $this->notificationsFor($request),
        ];
    }

    /**
     * @return array{unreadCount: int, items: array<int, array<string, mixed>>}|null
     */
    private function notificationsFor(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        return [
            'unreadCount' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn ($notification) => [
                    'id' => $notification->id,
                    'kind' => $notification->data['kind'] ?? null,
                    'title' => $notification->data['title'] ?? '',
                    'body' => $notification->data['body'] ?? '',
                    'url' => $notification->data['url'] ?? null,
                    'readAt' => $notification->read_at?->toIso8601String(),
                    'createdAt' => $notification->created_at->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }
}
