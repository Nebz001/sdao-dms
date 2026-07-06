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
 * @property string $description
 * @property string $contact_person
 * @property string $contact_number
 * @property string $contact_email
 * @property CarbonInterface $date_organized
 * @property int|null $adviser_id
 * @property array<int, string>|null $roster
 */
#[Fillable(['document_id', 'organization_type', 'description', 'contact_person', 'contact_number', 'contact_email', 'date_organized', 'adviser_id', 'roster'])]
class OrganizationRegistrationDetail extends Model
{
    /** @use HasFactory<OrganizationRegistrationDetailFactory> */
    use HasFactory;

    protected $casts = [
        'organization_type' => OrganizationType::class,
        'date_organized' => 'date',
        'roster' => 'array',
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
