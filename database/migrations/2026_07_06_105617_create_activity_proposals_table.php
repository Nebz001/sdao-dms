<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('calendar_mode');
            $table->foreignId('calendar_activity_id')->nullable()->constrained('calendar_activities')->nullOnDelete();
            $table->string('title');
            $table->text('objectives')->nullable();
            $table->text('narrative')->nullable();
            $table->decimal('estimated_budget', 10, 2)->nullable();
            $table->unsignedTinyInteger('form_step')->default(2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_proposals');
    }
};
