<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The allotted time an approver has for one step, in days. Editable
        // per step by SDAO; the clock starts when the document reaches the
        // step (its latest transition), see App\Approval\StepDeadline.
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->unsignedSmallInteger('lead_time_days')->default(3)->after('required_approvals');
        });

        // document_reminders becomes the one reminder log: manual Remind
        // clicks, the hourly scheduler's reminders and overdue alerts all
        // write here. A row belongs to one visit to a step, identified by the
        // transition that put the document there (arrival_transition_id).
        Schema::table('document_reminders', function (Blueprint $table) {
            // manual | automatic | overdue | sdao_overdue
            $table->string('kind', 20)->default('manual')->after('sent_by');
            $table->unsignedSmallInteger('step_position')->nullable()->after('kind');
            $table->foreignId('arrival_transition_id')->nullable()->after('step_position')
                ->constrained('document_transitions')->nullOnDelete();
            // Which 24 hour mark since arrival an automatic reminder is for
            // (1 = first day). 0 is the one-off SDAO overdue alert. Null for
            // a manual reminder, which never takes a slot.
            $table->unsignedSmallInteger('slot')->nullable()->after('arrival_transition_id');

            // A restart or a double run cannot send the same slot twice.
            $table->unique(['arrival_transition_id', 'slot']);
            $table->index(['document_id', 'arrival_transition_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('document_reminders', function (Blueprint $table) {
            $table->dropIndex(['document_id', 'arrival_transition_id', 'created_at']);
            $table->dropUnique(['arrival_transition_id', 'slot']);
            $table->dropConstrainedForeignId('arrival_transition_id');
            $table->dropColumn(['kind', 'step_position', 'slot']);
        });

        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropColumn('lead_time_days');
        });
    }
};
