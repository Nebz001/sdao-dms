<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only, like document_remarks and document_transitions: one row
        // per acceptance (every login adds one), never updated or deleted by the
        // app, so there is no updated_at to imply otherwise.
        Schema::create('privacy_notice_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notice_version', 32);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('accepted_at');

            $table->index(['user_id', 'accepted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_notice_acceptances');
    }
};
