<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Phase 2 item 8: retiring these two form-specific attachment tables in
     * favor of the single generic `document_attachments` table. Both were
     * schema-only scaffolding — zero controllers, actions, routes, seeders,
     * or tests ever referenced them beyond their own model/factory files —
     * so this is a clean removal, not a data migration.
     */
    public function up(): void
    {
        Schema::dropIfExists('activity_proposal_attachments');
        Schema::dropIfExists('after_activity_report_attachments');
    }

    /**
     * Reverse the migrations. Recreates both tables verbatim from their
     * original migrations for reversibility.
     */
    public function down(): void
    {
        Schema::create('activity_proposal_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_proposal_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('path');
            $table->string('disk');
            $table->string('mime_type');
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });

        Schema::create('after_activity_report_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('after_activity_report_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('path');
            $table->string('disk');
            $table->string('mime_type');
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });
    }
};
