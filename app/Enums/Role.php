<?php

namespace App\Enums;

enum Role: string
{
    case Student = 'student';
    case Adviser = 'adviser';
    case ProgramChair = 'program_chair';
    case Dean = 'dean';
    case Principal = 'principal';
    case SdaoMember = 'sdao_member';
    case AssistantDirectorAcademicServices = 'assistant_director_academic_services';
    case AcademicDirector = 'academic_director';
    case ExecutiveDirector = 'executive_director';

    /**
     * Returns the scope type that governs role assignment for this role.
     */
    public function scopeType(): ScopeType
    {
        return match ($this) {
            self::Student, self::Adviser => ScopeType::Organization,
            self::ProgramChair => ScopeType::Program,
            self::Dean, self::Principal => ScopeType::School,
            self::SdaoMember,
            self::AssistantDirectorAcademicServices,
            self::AcademicDirector,
            self::ExecutiveDirector => ScopeType::Global,
        };
    }

    /**
     * True for a "global" role that must have at most one holder at a time
     * (Assistant/Academic/Executive Director). False for SdaoMember — the
     * one global role that legitimately has multiple simultaneous holders
     * (both members must approve; see RoleDirectory::sdaoMembers()).
     */
    public function hasSingleGlobalHolder(): bool
    {
        return $this->scopeType() === ScopeType::Global && $this !== self::SdaoMember;
    }

    /**
     * True for a scope-bound role that must have at most one holder per
     * (role, scope) pair: one adviser per organization, one chair per
     * program, one dean/principal per school. This is the scoped sibling of
     * hasSingleGlobalHolder() — see ProvisionApprover::retireIncumbent() and
     * RoleDirectory::resolveScoped(), which both rely on this to know which
     * roles must retire a previous holder before a new one takes the seat.
     *
     * Deliberately excludes Student, which shares ScopeType::Organization
     * with Adviser but has many legitimate holders per organization.
     *
     * A false result for a role does not mean it is scope-less — it may
     * still be single-holder GLOBALLY (see hasSingleGlobalHolder()) or
     * intentionally multi-holder within its scope (SdaoMember).
     *
     * Note this describes the ROLE, not a particular assignment: an Adviser
     * row with organization_id = null is not a contested "seat" at all — it
     * is the unassigned pool (see ProvisionApprover::guardScopeMatchesRole())
     * — so callers must only retire an incumbent when the scope VALUE being
     * assigned is non-null.
     */
    public function hasSingleScopedHolder(): bool
    {
        return match ($this) {
            self::Adviser, self::ProgramChair, self::Dean, self::Principal => true,
            self::Student, self::SdaoMember,
            self::AssistantDirectorAcademicServices,
            self::AcademicDirector,
            self::ExecutiveDirector => false,
        };
    }

    /**
     * The role_assignments column that carries this role's scope value, or
     * null for a global role. Single source of truth for the role→column
     * mapping — reused by ProvisionApprover's scope validation and its
     * incumbent-retirement logic so they can never drift apart.
     */
    public function scopeColumn(): ?string
    {
        return match ($this->scopeType()) {
            ScopeType::Organization => 'organization_id',
            ScopeType::Program => 'program_id',
            ScopeType::School => 'school_id',
            ScopeType::Global => null,
        };
    }

    /**
     * Human-readable label for display (approver provisioning, badges, etc).
     */
    public function label(): string
    {
        return match ($this) {
            self::Student => 'Student',
            self::Adviser => 'Adviser',
            self::ProgramChair => 'Program Chair',
            self::Dean => 'Dean',
            self::Principal => 'Principal',
            self::SdaoMember => 'SDAO Member',
            self::AssistantDirectorAcademicServices => 'Asst. Director of Academic Services',
            self::AcademicDirector => 'Academic Director',
            self::ExecutiveDirector => 'Executive Director',
        };
    }
}
