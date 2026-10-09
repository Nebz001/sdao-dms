<?php

namespace App\Http\Controllers\Admin;

use App\Dashboard\AdminAttentionData;
use App\Enums\AdviserTermOutcome;
use App\Enums\Role;
use App\Enums\ScopeType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProvisionApproverRequest;
use App\Identity\Admin\ProvisionApprover;
use App\Identity\RoleDirectory;
use App\Models\Organization;
use App\Models\Program;
use App\Models\RoleAssignment;
use App\Models\School;
use App\Models\User;
use App\Support\FlashToast;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ApproverController extends Controller
{
    /**
     * The role groups of the Approver Accounts table, in display order. A user
     * holding several roles lands in the first group they qualify for. Program
     * chairs get their own group (no other tab covers them), and principals
     * sit with deans, since a principal is the school head in Senior High.
     *
     * @var array<string, array<int, Role>>
     */
    private const array GROUPS = [
        'adviser' => [Role::Adviser],
        'program_chair' => [Role::ProgramChair],
        'dean' => [Role::Dean, Role::Principal],
        'sdao' => [Role::SdaoMember],
        'director' => [Role::AssistantDirectorAcademicServices, Role::AcademicDirector, Role::ExecutiveDirector],
    ];

    public function index(Request $request, AdminAttentionData $attention): Response
    {
        // Role holders, plus every deactivated account: a deactivated account
        // often has no role left (the SDAO replacement removes it), but must
        // stay visible so it can be reviewed and reactivated.
        $approvers = User::query()
            ->where(fn ($q) => $q
                ->whereHas('roleAssignments', fn ($r) => $r->where('role', '!=', Role::Student->value))
                ->orWhereNotNull('deactivated_at'))
            ->orderBy('name')
            ->get();

        $rows = $this->rows($approvers);

        return Inertia::render('admin/approvers/index', [
            'approvers' => $rows,
            'stats' => $this->stats($rows, $attention),
            'initialFilters' => $this->initialFilters($request),
            'schools' => School::query()->inRankOrder()->get(['id', 'name'])
                ->map(fn (School $s) => ['id' => $s->id, 'name' => $s->name])
                ->values(),
        ]);
    }

    /**
     * Filters a link can preselect (the "Advisers not assigned yet" card).
     * Anything that is not a known value is ignored.
     *
     * @return array{role: string|null, scope: string|null}
     */
    private function initialFilters(Request $request): array
    {
        $role = $request->query('role');
        $scope = $request->query('scope');

        return [
            'role' => is_string($role) && array_key_exists($role, self::GROUPS) ? $role : null,
            'scope' => is_string($scope) && preg_match('/^(global|none|unassigned|school:\d+)$/', $scope) === 1 ? $scope : null,
        ];
    }

    /**
     * The stat cards. Role counts are of ACTIVE approvers only; the
     * missing-adviser figure reuses the dashboard tile's rule
     * (AdminAttentionData::approvedOrganizationsWithoutAdviser), so the two
     * can never disagree.
     *
     * @param  SupportCollection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function stats(SupportCollection $rows, AdminAttentionData $attention): array
    {
        $active = $rows->whereNull('deactivated_at');
        $deactivated = $rows->whereNotNull('deactivated_at');
        $latest = $deactivated->sortByDesc('deactivated_at')->first();
        $missing = $attention->approvedOrganizationsWithoutAdviser();

        return [
            'active' => [
                'total' => $active->count(),
                'byGroup' => collect(array_keys(self::GROUPS))
                    ->mapWithKeys(fn (string $group) => [$group => $active->where('group', $group)->count()])
                    ->all(),
            ],
            'unassignedAdvisers' => [
                'count' => $attention->unassignedAdviserCount(),
                'href' => route('admin.approvers.index', ['role' => 'adviser', 'scope' => 'unassigned']),
            ],
            'missingAdviser' => [
                'count' => $missing->count(),
                'organizations' => $missing->map(fn (Organization $o) => ['id' => $o->id, 'name' => $o->name])->values(),
                'href' => route('admin.organizations.index', ['adviser' => 'none']),
            ],
            'deactivated' => [
                'count' => $deactivated->count(),
                'latest' => $latest === null ? null : ['name' => $latest['name'], 'at' => $latest['deactivated_at']],
            ],
        ];
    }

    /**
     * The row shape shared with AccountController::search(), so a found
     * account renders exactly like a listed one.
     *
     * @param  Collection<int, User>  $users
     * @return SupportCollection<int, array<string, mixed>>
     */
    public function rows(Collection $users): SupportCollection
    {
        $users->load([
            'deactivatedBy:id,name',
            'roleAssignments' => fn ($q) => $q
                ->where('role', '!=', Role::Student->value)
                ->with(['school', 'program.school', 'organization.school']),
        ]);

        $organizationsPerSchool = Organization::query()
            ->selectRaw('school_id, count(*) as total')
            ->groupBy('school_id')
            ->pluck('total', 'school_id');
        $organizationTotal = (int) $organizationsPerSchool->sum();

        return $users->map(function (User $u) use ($organizationsPerSchool, $organizationTotal) {
            $primary = $this->primaryAssignment($u);

            return [
                'id' => $u->id,
                'name' => $u->name,
                'first_name' => $u->first_name,
                'last_name' => $u->last_name,
                'email' => $u->email,
                'id_number' => $u->id_number,
                'is_self' => $u->id === Auth::id(),
                'deactivated_at' => $u->deactivated_at?->toIso8601String(),
                'deactivated_reason' => $u->deactivated_reason,
                'deactivated_by' => $u->deactivated_at !== null ? ($u->deactivatedBy?->name ?? 'Unknown') : null,
                'group' => $primary === null ? null : $this->groupOf($primary->role),
                'role_label' => $primary?->role->label(),
                'approves_for' => $primary === null ? null : $this->approvesFor($primary, $organizationsPerSchool, $organizationTotal),
                'scope_key' => $primary === null ? null : $this->scopeKey($primary),
                'roles' => $u->roleAssignments->map(fn (RoleAssignment $ra) => [
                    'role' => $ra->role->value,
                    'label' => $ra->role->label(),
                    'scope' => $this->scopeLabel($ra),
                ])->values(),
            ];
        })->values();
    }

    private function primaryAssignment(User $user): ?RoleAssignment
    {
        foreach (self::GROUPS as $roles) {
            $match = $user->roleAssignments->first(fn (RoleAssignment $ra) => in_array($ra->role, $roles, true));

            if ($match !== null) {
                return $match;
            }
        }

        return null;
    }

    private function groupOf(Role $role): ?string
    {
        foreach (self::GROUPS as $group => $roles) {
            if (in_array($role, $roles, true)) {
                return $group;
            }
        }

        return null;
    }

    /**
     * What the account approves for: a line plus a quieter detail line. An
     * adviser names the organization and its college; a dean or chair names
     * the school (or program); the global roles cover every organization.
     *
     * @param  SupportCollection<int|string, mixed>  $organizationsPerSchool
     * @return array{primary: string, secondary: string|null}
     */
    private function approvesFor(RoleAssignment $ra, SupportCollection $organizationsPerSchool, int $organizationTotal): array
    {
        $count = fn (int $n) => $n.' '.Str::plural('organization', $n);

        return match ($ra->role->scopeType()) {
            ScopeType::Organization => $ra->organization === null
                ? ['primary' => 'Not assigned yet', 'secondary' => null]
                : ['primary' => $ra->organization->name, 'secondary' => $ra->organization->school?->name ?? School::NONE_LABEL],
            ScopeType::Program => [
                'primary' => $ra->program?->name ?? 'Unknown program',
                'secondary' => $ra->program?->school?->name,
            ],
            ScopeType::School => [
                'primary' => $ra->school?->name ?? 'Unknown school',
                'secondary' => $count((int) ($organizationsPerSchool[$ra->school_id] ?? 0)),
            ],
            ScopeType::Global => ['primary' => 'Whole school', 'secondary' => $count($organizationTotal)],
        };
    }

    /** "school:{id}", "none" (organization with no college), "unassigned" or "global", for the Scope filter. */
    private function scopeKey(RoleAssignment $ra): string
    {
        $schoolId = match ($ra->role->scopeType()) {
            ScopeType::Global => 'global',
            ScopeType::Organization => $ra->organization === null ? null : ($ra->organization->school_id ?? 'none'),
            ScopeType::Program => $ra->program?->school_id,
            ScopeType::School => $ra->school_id,
        };

        return match (true) {
            $schoolId === null => 'unassigned',
            is_string($schoolId) => $schoolId,
            default => "school:{$schoolId}",
        };
    }

    public function create(Request $request, RoleDirectory $directory): Response
    {
        return Inertia::render('admin/approvers/create', [
            // "Create adviser account" on an organization page links here with
            // the role and organization already chosen. Anything that is not a
            // real, provisionable role or an existing organization is ignored.
            'preset' => [
                'role' => Role::tryFrom($request->string('role')->toString()) === Role::Adviser ? Role::Adviser->value : null,
                'organization_id' => Organization::query()->whereKey($request->integer('organization_id'))->value('id'),
            ],
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
            'schools' => School::query()->inRankOrder()->get(['id', 'name'])
                ->map(fn (School $s) => ['id' => $s->id, 'name' => $s->name]),
            'programs' => Program::query()->orderBy('name')->get(['id', 'name', 'school_id'])
                ->map(fn (Program $p) => ['id' => $p->id, 'name' => $p->name, 'school_id' => $p->school_id]),
            // The current adviser rides along so the form can say who a new adviser
            // would replace, and send that id back for the stale-page check.
            'organizations' => Organization::query()->with('adviser.user:id,name')->orderBy('name')->get(['id', 'name'])
                ->map(fn (Organization $o) => [
                    'id' => $o->id,
                    'name' => $o->name,
                    'adviser' => $o->adviser?->user === null ? null : ['id' => $o->adviser->user->id, 'name' => $o->adviser->user->name],
                ]),
        ]);
    }

    public function store(ProvisionApproverRequest $request, ProvisionApprover $action): RedirectResponse
    {
        $replacesUserId = $request->integer('replaces_user_id') ?: null;
        $replaced = $replacesUserId !== null ? User::find($replacesUserId) : null;

        $action->execute(
            actor: Auth::user(),
            firstName: $request->string('first_name')->toString(),
            lastName: $request->string('last_name')->toString(),
            email: $request->string('email')->toString(),
            idNumber: $request->string('id_number')->toString() ?: null,
            role: Role::from($request->string('role')->toString()),
            scope: [
                'school_id' => $request->integer('school_id') ?: null,
                'program_id' => $request->integer('program_id') ?: null,
                'organization_id' => $request->integer('organization_id') ?: null,
            ],
            replacesUserId: $replacesUserId,
            deactivateReplaced: $request->boolean('deactivate_replaced', true),
            outgoingAdviser: AdviserTermOutcome::tryFrom($request->string('outgoing_adviser')->toString()) ?? AdviserTermOutcome::ReturnedToPool,
            verifyOutgoingAdviser: $request->has('current_adviser_id'),
            expectedOutgoingAdviserId: $request->integer('current_adviser_id') ?: null,
        );

        $message = 'Their one time password was emailed to them.';

        if ($replaced !== null) {
            $message .= $request->boolean('deactivate_replaced', true)
                ? " {$replaced->name} no longer has the SDAO role and has been deactivated."
                : " {$replaced->name} no longer has the SDAO role.";
        }

        $flash = FlashToast::make('Approver created', $message);

        if ($action->welcomeEmailFailed) {
            $flash = FlashToast::error('Approver created, email not sent', 'Ask them to use Forgot password on the login page.');
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
