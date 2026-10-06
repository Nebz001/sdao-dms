<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per "Remind" an admin sends from the Stuck Documents page.
        // Append-only: the newest row per document drives the 24 hour limit.
        Schema::create('document_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('recipient_count');
            $table->timestamp('created_at');

            $table->index(['document_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_reminders');
    }
};
