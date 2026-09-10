<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Group B item 5 — "Others" (ActivityNature::Others / ActivityType::Others)
     * has never had a follow-up free-text field to say what "Others" means.
     * Two nullable columns, purely additive — nothing to backfill.
     */
    public function up(): void
    {
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->string('activity_nature_other')->nullable()->after('activity_nature');
            $table->string('activity_type_other')->nullable()->after('activity_type');
        });
    }

    public function down(): void
    {
        Schema::table('activity_proposals', function (Blueprint $table) {
            $table->dropColumn(['activity_nature_other', 'activity_type_other']);
        });
    }
};
