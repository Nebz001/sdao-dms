<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A current president/secretary's request to change their org's holder
     * of a seat — additive alongside the adviser's existing direct-bind path
     * (App\Organizations\BindOrganizationOfficer), which is unchanged. Only
     * an SDAO admin can finalize (App\Organizations\Admin\ApproveOfficerChange /
     * DeclineOfficerChange). Mirrors organization_join_requests' shape.
     */
    public function up(): void
    {
        Schema::create('officer_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('position'); // OfficerPosition
            $table->foreignId('nominee_id')->constrained('users')->cascadeOnDelete();
            // Submit-time snapshot of who held the seat when filed (nullable
            // — the seat may be vacant). Never trusted by the finalize
            // action, which always re-resolves the live holder; this is
            // display-only, so the admin queue can show drift ("filed to
            // replace X — X no longer holds this seat").
            $table->foreignId('outgoing_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->string('status')->default('pending'); // OfficerChangeRequestStatus
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_comment')->nullable();
            $table->timestamps();

            // No DB unique constraint on (organization, position, pending) —
            // "one pending request per seat" is enforced in
            // RequestOfficerChange, same pattern as organization_join_requests.
            $table->index(['organization_id', 'status']);
            $table->index('requested_by');
            $table->index('nominee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('officer_change_requests');
    }
};
