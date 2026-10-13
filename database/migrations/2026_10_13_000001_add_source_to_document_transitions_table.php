<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_transitions', function (Blueprint $table) {
            // Nullable preserves historical honesty: transitions written before
            // this migration cannot reliably be classified as web or mobile.
            $table->string('source')->nullable()->after('action');
            $table->index(['source', 'action', 'created_at'], 'document_transitions_source_action_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('document_transitions', function (Blueprint $table) {
            $table->dropIndex('document_transitions_source_action_created_index');
            $table->dropColumn('source');
        });
    }
};
