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
        Schema::create('role_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['role', 'school_id']);
            $table->index(['role', 'program_id']);
            $table->index(['role', 'organization_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_assignments');
    }
};
