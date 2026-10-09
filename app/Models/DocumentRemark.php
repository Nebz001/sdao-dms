<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\DocumentRemarkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only note an approver leaves on a document without changing its
 * state. Never updated or deleted by the app, same spirit as
 * DocumentTransition (invariant #7); deliberately not a transition because it
 * moves nothing.
 *
 * @property int $id
 * @property int $document_id
 * @property int|null $user_id
 * @property string $body
 * @property CarbonInterface $created_at
 */
#[Fillable(['document_id', 'user_id', 'body', 'created_at'])]
class DocumentRemark extends Model
{
    /** @use HasFactory<DocumentRemarkFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
