<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('form_type');
            $table->string('variant')->nullable();
            $table->string('title');
            $table->string('status')->default('draft');
            $table->unsignedInteger('current_step_position')->nullable();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('workflow_template_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
