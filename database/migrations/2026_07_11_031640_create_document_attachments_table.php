<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Phase 2 item 8: a single generic attachment table keyed to Document
     * (the one thing common across all form types) rather than a separate
     * table per form. `slot_key` identifies which named attachment slot
     * (defined in App\Attachments\AttachmentSlots) this row fills. No unique
     * constraint on (document_id, slot_key) — some slots are multi-file
     * (e.g. Photos); single-file-slot replacement is enforced in the service
     * layer (App\Attachments\AttachmentStorage), not the schema.
     *
     * "Attachments are stored in separate locations per document type"
     * (sdao.md) is honored via the storage path convention
     * (attachments/{form_type}/{document_id}/...), not via separate tables.
     */
    public function up(): void
    {
        Schema::create('document_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('slot_key');
            $table->string('original_filename');
            $table->string('path');
            $table->string('disk');
            $table->string('mime_type');
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['document_id', 'slot_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_attachments');
    }
};
