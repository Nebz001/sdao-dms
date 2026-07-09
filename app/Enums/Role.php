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
