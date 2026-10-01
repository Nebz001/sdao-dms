<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProvisionApproverRequest;
use App\Identity\Admin\ProvisionApprover;
use App\Identity\RoleDirectory;
use App\Models\Organization;
use App\Models\Program;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ApproverController extends Controller
{
    public function index(): Response
    {
        $approvers = User::query()
            ->whereHas('roleAssignments', fn ($q) => $q->where('role', '!=', Role::Student->value))
            ->with(['roleAssignments' => fn ($q) => $q
                ->where('role', '!=', Role::Student->value)
                ->with(['school', 'program', 'organization']),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'id_number' => $u->id_number,
                'roles' => $u->roleAssignments->map(fn (RoleAssignment $ra) => [
                    'role' => $ra->role->value,
                    'label' => $ra->role->label(),
                    'scope' => $this->scopeLabel($ra),
                ]),
            ]);

        return Inertia::render('admin/approvers/index', ['approvers' => $approvers]);
    }

    public function create(RoleDirectory $directory): Response
    {
        return Inertia::render('admin/approvers/create', [
            'sdaoMembers' => $directory->sdaoMembers()
                ->reject(fn (User $u) => $u->id === Auth::id())
                ->sortBy('name')
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])
                ->values(),
            'roles' => collect(Role::cases())
                ->reject(fn (Role $r) => $r === Role::Student)
                ->map(fn (Role $r) => [
                    'value' => $r->value,
                    'label' => $r->label(),
                    'scope_type' => $r->scopeType()->value,
                ])
                ->values(),
            'schools' => School::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (School $s) => ['id' => $s->id, 'name' => $s->name]),
            'programs' => Program::query()->orderBy('name')->get(['id', 'name', 'school_id'])
                ->map(fn (Program $p) => ['id' => $p->id, 'name' => $p->name, 'school_id' => $p->school_id]),
            'organizations' => Organization::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Organization $o) => ['id' => $o->id, 'name' => $o->name]),
        ]);
    }

    public function store(ProvisionApproverRequest $request, ProvisionApprover $action): RedirectResponse
    {
        $replacesUserId = $request->integer('replaces_user_id') ?: null;
        $replaced = $replacesUserId !== null ? User::find($replacesUserId) : null;

        $action->execute(
            actor: Auth::user(),
            name: $request->string('name')->toString(),
            email: $request->string('email')->toString(),
            idNumber: $request->string('id_number')->toString() ?: null,
            role: Role::from($request->string('role')->toString()),
            scope: [
                'school_id' => $request->integer('school_id') ?: null,
                'program_id' => $request->integer('program_id') ?: null,
                'organization_id' => $request->integer('organization_id') ?: null,
            ],
            replacesUserId: $replacesUserId,
        );

        $message = 'Approver created. Their one time password has been emailed to them.';

        if ($replaced !== null) {
            $message .= " {$replaced->name} no longer has the SDAO role.";
        }

        $flash = ['message' => $message];

        if ($action->welcomeEmailFailed) {
            $flash = [
                'type' => 'error',
                'message' => 'Approver created, but the email could not be sent. Ask them to use Forgot password on the login page.',
            ];
        }

        return redirect()->route('admin.approvers.index')->with('flash', $flash);
    }

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
