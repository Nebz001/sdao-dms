<?php

namespace App\Printing;

use App\Models\DocumentAttachment;
use GdImage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Throwable;

/**
 * Turns an uploaded attachment into something a printed form can embed.
 *
 * Attachments live on the S3/Supabase disk, so bytes are always read through
 * Laravel Storage and handed to dompdf as base64 data URIs — dompdf's remote
 * fetching stays off. Nothing here throws: an unreadable file comes back as
 * kind "missing" (and is logged) so one bad upload never breaks the print.
 *
 * Result shape (always has `kind` and `filename`):
 *  - image:       src (data URI), width/height in pt (fitted to the box)
 *  - pdf:         bytes, pages (verified mergeable)
 *  - unsupported: a type that cannot be rendered (e.g. .docx) or a PDF that
 *                 cannot be merged
 *  - missing:     could not be read or decoded
 */
class PrintAttachmentLoader
{
    /** Longest side, in pixels, an embedded image is downscaled to. */
    public const int MAX_IMAGE_PX = 1400;

    /** Refuse to decode anything larger than this many pixels (GD memory). */
    private const int MAX_DECODE_PIXELS = 60_000_000;

    private const float PT_PER_MM = 2.834645669;

    /**
     * @return array{kind: string, filename: string, src?: string, width?: float, height?: float, bytes?: string, pages?: int}
     */
    public function resolve(DocumentAttachment $attachment, float $maxWidthMm, float $maxHeightMm, bool $upscale = false): array
    {
        $filename = $attachment->original_filename;
        $bytes = $this->read($attachment);

        if ($bytes === null) {
            return ['kind' => 'missing', 'filename' => $filename];
        }

        if (str_starts_with($bytes, '%PDF')) {
            $pages = $this->pdfPageCount($bytes, $attachment);

            return $pages === null
                ? ['kind' => 'unsupported', 'filename' => $filename]
                : ['kind' => 'pdf', 'filename' => $filename, 'bytes' => $bytes, 'pages' => $pages];
        }

        if ($this->isRasterImage($bytes)) {
            $image = $this->embedImage($bytes, $maxWidthMm, $maxHeightMm, $upscale, $attachment);

            return $image === null
                ? ['kind' => 'missing', 'filename' => $filename]
                : ['kind' => 'image', 'filename' => $filename] + $image;
        }

        return ['kind' => 'unsupported', 'filename' => $filename];
    }

    private function read(DocumentAttachment $attachment): ?string
    {
        try {
            $bytes = Storage::disk($attachment->disk)->get($attachment->path);
        } catch (Throwable $e) {
            Log::warning('Print: attachment could not be read.', [
                'attachment_id' => $attachment->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! is_string($bytes) || $bytes === '') {
            Log::warning('Print: attachment is missing or empty.', ['attachment_id' => $attachment->id]);

            return null;
        }

        return $bytes;
    }

    private function isRasterImage(string $bytes): bool
    {
        $info = @getimagesizefromstring($bytes);

        return $info !== false && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true);
    }

    private function pdfPageCount(string $bytes, DocumentAttachment $attachment): ?int
    {
        try {
            $count = (new Fpdi)->setSourceFile(StreamReader::createByString($bytes));
        } catch (Throwable $e) {
            // Encrypted or compressed-xref (PDF 1.5+) files are outside the
            // free FPDI parser; they fall back to the "view in the system" note.
            Log::warning('Print: PDF attachment cannot be merged.', [
                'attachment_id' => $attachment->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        return $count > 0 ? $count : null;
    }

    /**
     * Decodes, applies EXIF orientation, downscales and re-encodes as JPEG
     * (flattened on white) so a 12 MP phone photo cannot blow up dompdf.
     *
     * @return array{src: string, width: float, height: float}|null
     */
    private function embedImage(string $bytes, float $maxWidthMm, float $maxHeightMm, bool $upscale, DocumentAttachment $attachment): ?array
    {
        if (! function_exists('imagecreatefromstring')) {
            Log::warning('Print: GD is not available, cannot embed images.');

            return null;
        }

        $info = @getimagesizefromstring($bytes);

        if ($info === false || $info[0] * $info[1] > self::MAX_DECODE_PIXELS) {
            Log::warning('Print: image too large or unreadable.', ['attachment_id' => $attachment->id]);

            return null;
        }

        $source = @imagecreatefromstring($bytes);

        if ($source === false) {
            Log::warning('Print: image could not be decoded.', ['attachment_id' => $attachment->id]);

            return null;
        }

        if ($info[2] === IMAGETYPE_JPEG) {
            $source = $this->orient($source, $bytes);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1.0, self::MAX_IMAGE_PX / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagejpeg($canvas, null, 85);
        $jpeg = (string) ob_get_clean();

        $fit = min($maxWidthMm / $targetWidth, $maxHeightMm / $targetHeight);

        if (! $upscale) {
            // 96 dpi natural size: never print a small image larger than it is.
            $fit = min($fit, 25.4 / 96);
        }

        return [
            'src' => 'data:image/jpeg;base64,'.base64_encode($jpeg),
            'width' => round($targetWidth * $fit * self::PT_PER_MM, 2),
            'height' => round($targetHeight * $fit * self::PT_PER_MM, 2),
        ];
    }

    private function orient(GdImage $image, string $bytes): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $bytes);
        rewind($stream);
        $exif = @exif_read_data($stream);
        fclose($stream);

        $angle = match ($exif['Orientation'] ?? 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        return imagerotate($image, $angle, 0) ?: $image;
    }
}
