<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable — no org has a logo yet, no upload UI exists yet. Two
     * columns, not one, mirroring document_attachments' disk-per-row
     * convention (see App\Attachments\AttachmentStorage): each org records
     * the disk its logo actually landed on, so a row stays resolvable even
     * if ATTACHMENTS_DISK is later reconfigured to point elsewhere.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('program_id');
            $table->string('logo_disk')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['logo_path', 'logo_disk']);
        });
    }
};
