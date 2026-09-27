<?php

namespace App\Http\Resources\Mobile;

use App\Attachments\AttachmentSlots;
use App\Enums\FormType;
use App\Models\DocumentAttachment;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Metadata only — never a download URL or the file itself. Storage
 * (Supabase) is never touched here.
 *
 * @property-read DocumentAttachment $resource
 */
class ProposalAttachmentResource extends JsonResource
{
    private const array MIME_LABELS = [
        'application/pdf' => 'PDF',
        'image/jpeg' => 'JPG',
        'image/jpg' => 'JPG',
        'image/png' => 'PNG',
        'image/webp' => 'WEBP',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => "attachment-{$this->resource->id}",
            'file_name' => $this->fileName(),
            'file_type' => $this->fileType(),
            'size_label' => $this->sizeLabel(),
            'description' => $this->description(),
        ];
    }

    private function fileName(): string
    {
        $name = $this->resource->original_filename;

        return $name !== '' ? $name : basename((string) $this->resource->path);
    }

    private function fileType(): string
    {
        $mime = $this->resource->mime_type;

        if (isset(self::MIME_LABELS[$mime])) {
            return self::MIME_LABELS[$mime];
        }

        $extension = strtoupper((string) pathinfo($this->fileName(), PATHINFO_EXTENSION));

        return $extension !== '' ? $extension : 'FILE';
    }

    private function sizeLabel(): string
    {
        $bytes = $this->resource->size;

        if ($bytes === null) {
            return 'Unknown size';
        }

        if ($bytes < 1024) {
            return "{$bytes} B";
        }

        $units = ['KB', 'MB', 'GB'];
        $value = $bytes / 1024;
        $unitIndex = 0;

        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        $formatted = rtrim(rtrim(number_format($value, 1), '0'), '.');

        return "{$formatted} {$units[$unitIndex]}";
    }

    private function description(): string
    {
        $slot = collect(AttachmentSlots::for(FormType::ActivityProposal))
            ->first(fn ($s) => $s->key === $this->resource->slot_key);

        return $slot?->label ?? 'Attachment';
    }
}
