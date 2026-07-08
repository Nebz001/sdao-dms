<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('after_activity_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->unique()->constrained()->cascadeOnDelete();
            // Hard link to the specific APPROVED activity proposal being reported on
            // (invariant: a report cannot exist without a corresponding approved activity).
            $table->foreignId('activity_proposal_id')->constrained()->restrictOnDelete();
            $table->text('narrative');
            $table->text('outcomes')->nullable();
            $table->unsignedInteger('participant_count')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('after_activity_reports');
    }
};
