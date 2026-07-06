<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hand-off trigger record (invariant #9). No delivery channel columns —
        // that is the SSO slice's responsibility.
        Schema::create('approval_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('step_position');
            $table->timestamp('created_at');

            $table->index(['document_id', 'step_position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_notifications');
    }
};
