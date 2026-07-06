<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_template_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('role');
            $table->unsignedTinyInteger('required_approvals')->default(1);
            $table->timestamps();

            $table->unique(['workflow_template_id', 'position']);
            $table->index('workflow_template_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_steps');
    }
};
