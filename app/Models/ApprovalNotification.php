<?php

namespace App\Models;

use Database\Factories\ApprovalNotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hand-off trigger record written when the engine activates a step (invariant #9).
 * Contains no delivery-channel data — email sending is deferred to the SSO slice.
 *
 * @property int $id
 * @property int $document_id
 * @property int $user_id
 * @property int $step_position
 */
#[Fillable(['document_id', 'user_id', 'step_position', 'created_at'])]
class ApprovalNotification extends Model
{
    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /** @use HasFactory<ApprovalNotificationFactory> */
    use HasFactory;

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
