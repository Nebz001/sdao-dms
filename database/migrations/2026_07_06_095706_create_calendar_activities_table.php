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
        Schema::create('calendar_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_calendar_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('venue'); // free-text; exact-match for conflict detection
            $table->date('activity_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            // Hot path for conflict detection: filter by venue + date first
            $table->index(['venue', 'activity_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_activities');
    }
};
