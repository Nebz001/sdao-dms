<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_templates', function (Blueprint $table) {
            $table->id();
            $table->string('form_type');
            $table->string('variant')->nullable();
            $table->string('name');
            $table->timestamps();

            $table->unique(['form_type', 'variant']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_templates');
    }
};
