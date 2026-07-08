<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProvisionApproverRequest;
use App\Identity\Admin\ProvisionApprover;
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
                'roles' => $u->roleAssignments->map(fn (RoleAssignment $ra) => [
                    'role' => $ra->role->value,
                    'label' => $ra->role->label(),
                    'scope' => $this->scopeLabel($ra),
                ]),
            ]);

        return Inertia::render('admin/approvers/index', ['approvers' => $approvers]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/approvers/create', [
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
        $action->execute(
            actor: Auth::user(),
            name: $request->string('name')->toString(),
            email: $request->string('email')->toString(),
            role: Role::from($request->string('role')->toString()),
            scope: [
                'school_id' => $request->integer('school_id') ?: null,
                'program_id' => $request->integer('program_id') ?: null,
                'organization_id' => $request->integer('organization_id') ?: null,
            ],
        );

        return redirect()->route('admin.approvers.index')
            ->with('flash', ['message' => 'Approver created — a password-reset link has been sent to their email.']);
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
