<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_step_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_step_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('step_position');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Each user may approve a given step of a document exactly once.
            $table->unique(['document_id', 'workflow_step_id', 'user_id']);
            $table->index(['document_id', 'step_position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_step_approvals');
    }
};
