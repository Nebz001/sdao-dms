<?php

namespace App\Http\Middleware;

use App\Models\RoleAssignment;
use Illuminate\Http\Request;
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
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
