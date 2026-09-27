<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_notifications', function (Blueprint $table) {
            $table->foreignId('transition_id')
                ->nullable()
                ->after('document_id')
                ->constrained('document_transitions')
                ->cascadeOnDelete();
            $table->unique(
                ['transition_id', 'user_id'],
                'approval_notifications_transition_user_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('approval_notifications', function (Blueprint $table) {
            $table->dropUnique('approval_notifications_transition_user_unique');
            $table->dropConstrainedForeignId('transition_id');
        });
    }
};
