<?php

namespace App\Models;

use App\Enums\OrganizationType;
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
 * @property string|null $academic_year
 */
#[Fillable(['document_id', 'organization_type', 'purpose_of_organization', 'contact_person', 'contact_no', 'email_address', 'date_organized', 'adviser_id', 'academic_year'])]
class OrganizationRegistrationDetail extends Model
{
    /** @use HasFactory<OrganizationRegistrationDetailFactory> */
    use HasFactory;

    protected $casts = [
        'organization_type' => OrganizationType::class,
        'date_organized' => 'date',
    ];

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
