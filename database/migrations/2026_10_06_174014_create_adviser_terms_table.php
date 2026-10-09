<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dated adviser history, modeled on organization_memberships: one row per
     * stretch of time a user was an organization's adviser. The role_assignments
     * row stays the live source of "who is the adviser now" (RoleDirectory reads
     * it); this table is the record of who held the seat before, when, and who
     * made the change. A swap closes the old row and opens a new one — rows are
     * never deleted by the app (they only go with a deleted organization or
     * account).
     *
     * Backfill: one OPEN term for every adviser who is bound to an organization
     * today, started at the role assignment's created_at. That is the best date
     * the database has, because binding an adviser used to update the existing
     * row in place — so for an adviser bound at registration approval it is when
     * the account was provisioned, not when the binding happened. started_by is
     * left null on these rows: nobody recorded who made those changes.
     *
     * The partial unique indexes on open terms are created in the next
     * migration, after it has checked role_assignments for conflicting rows, so
     * a bad row there is reported by name instead of surfacing as a raw
     * constraint error out of this backfill.
     */
    public function up(): void
    {
        Schema::create('adviser_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('end_outcome')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'started_at']);
            $table->index(['user_id', 'started_at']);
        });

        $now = now();

        DB::table('role_assignments')
            ->where('role', 'adviser')
            ->whereNotNull('organization_id')
            ->orderBy('id')
            ->get(['user_id', 'organization_id', 'created_at'])
            ->each(function ($assignment) use ($now) {
                DB::table('adviser_terms')->insert([
                    'user_id' => $assignment->user_id,
                    'organization_id' => $assignment->organization_id,
                    'started_at' => $assignment->created_at ?? $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('adviser_terms');
    }
};
