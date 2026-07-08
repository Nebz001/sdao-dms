<?php

namespace App\Models;

use Database\Factories\AfterActivityReportAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $after_activity_report_id
 * @property string $original_filename
 * @property string $path
 * @property string $disk
 * @property string $mime_type
 * @property int|null $size
 */
#[Fillable(['after_activity_report_id', 'original_filename', 'path', 'disk', 'mime_type', 'size'])]
class AfterActivityReportAttachment extends Model
{
    /** @use HasFactory<AfterActivityReportAttachmentFactory> */
    use HasFactory;

    /** @return BelongsTo<AfterActivityReport, $this> */
    public function report(): BelongsTo
    {
        return $this->belongsTo(AfterActivityReport::class, 'after_activity_report_id');
    }
}
