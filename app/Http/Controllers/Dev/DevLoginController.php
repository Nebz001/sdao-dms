<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Identity\Contracts\IdentityProvider;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dev-only: lets a developer log in as any seeded user without a password.
 * Never registered in production (see routes/web.php).
 */
class DevLoginController extends Controller
{
    public function __construct(private readonly IdentityProvider $identity) {}

    public function index(): Response
    {
        $users = User::query()
            ->with(['roleAssignments.school', 'roleAssignments.program', 'roleAssignments.organization'])
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roleAssignments->map(fn (RoleAssignment $ra) => [
                    'role' => $ra->role->value,
                    'label' => $ra->role->label(),
                    'scope' => $this->scopeLabel($ra),
                ]),
            ]);

        return Inertia::render('dev/login', ['users' => $users]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);

        $user = User::findOrFail($validated['user_id']);
        $this->identity->login($user);

        return redirect()->route('dashboard');
    }

    public function destroy(): RedirectResponse
    {
        $this->identity->logout();

        return redirect()->route('dev.login');
    }

    /**
     * Builds a human-readable scope label for display in the picker.
     */
    private function scopeLabel(RoleAssignment $ra): string
    {
        if ($ra->organization_id !== null) {
            return $ra->organization?->name ?? 'org #'.$ra->organization_id;
        }

        if ($ra->program_id !== null) {
            return $ra->program?->name ?? 'program #'.$ra->program_id;
        }

        if ($ra->school_id !== null) {
            return $ra->school?->name ?? 'school #'.$ra->school_id;
        }

        return 'Global';
    }
}
