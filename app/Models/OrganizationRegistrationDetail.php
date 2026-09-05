<?php

namespace App\Models;

use App\Enums\OrganizationType;
use App\Enums\Term;
use App\Support\AcademicPeriod;
use Carbon\CarbonInterface;
use Database\Factories\OrganizationRegistrationDetailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $document_id
 * @property OrganizationType $organization_type
 * @property string $purpose_of_organization
 * @property string $contact_person
 * @property string $contact_no
 * @property string $email_address
 * @property CarbonInterface $date_organized
 * @property int|null $adviser_id
 * @property string|null $academic_year The year of the period this record was stamped under.
 * @property Term|null $term The term of the period this record was stamped under.
 * @property string|null $covers_academic_year The academic year this record makes the organization active for — see period().
 */
#[Fillable(['document_id', 'organization_type', 'purpose_of_organization', 'contact_person', 'contact_no', 'email_address', 'date_organized', 'adviser_id', 'academic_year', 'term', 'covers_academic_year'])]
class OrganizationRegistrationDetail extends Model
{
    /** @use HasFactory<OrganizationRegistrationDetailFactory> */
    use HasFactory;

    protected $casts = [
        'organization_type' => OrganizationType::class,
        'date_organized' => 'date',
        'term' => Term::class,
    ];

    /**
     * The (academic_year, term) this record was stamped under, or null unless
     * BOTH columns are set — the drift guard against the two columns ever
     * disagreeing about whether a period exists.
     */
    public function period(): ?AcademicPeriod
    {
        if ($this->academic_year === null || $this->term === null) {
            return null;
        }

        return new AcademicPeriod($this->academic_year, $this->term);
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<User, $this> */
    public function adviser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adviser_id');
    }
}
