<?php

use App\Models\OfficerChangeRequest;
use App\Notifications\OfficerChangeDeclinedNotification;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * "One pending officer change request per seat" was enforced only in
     * RequestOfficerChange, so two officers filing at the same instant could
     * both create one. RequestOfficerChange now serializes filing under an
     * organization lock; this partial unique index is the database's own
     * backstop, same shape as org_memberships_one_active_per_seat.
     *
     * Any pre-existing duplicate pending rows (only possible through that
     * race) are resolved first, keeping the OLDEST and declining the rest
     * with a visible comment — the index can't be created while duplicates
     * exist, and silently deleting a request would lose history. Each
     * declined requester is told through the normal decline notification
     * (whose mail carries that comment), because from their side a pending
     * request would otherwise just vanish. A clean no-op when no duplicates
     * exist.
     */
    public function up(): void
    {
        $duplicateIds = DB::table('officer_change_requests as newer')
            ->join('officer_change_requests as older', function ($join) {
                $join->on('older.organization_id', '=', 'newer.organization_id')
                    ->on('older.position', '=', 'newer.position')
                    ->whereColumn('older.id', '<', 'newer.id');
            })
            ->where('newer.status', 'pending')
            ->where('older.status', 'pending')
            ->distinct()
            ->pluck('newer.id');

        if ($duplicateIds->isNotEmpty()) {
            DB::table('officer_change_requests')
                ->whereIn('id', $duplicateIds)
                ->update([
                    'status' => 'declined',
                    'decided_at' => now(),
                    'decision_comment' => 'Closed automatically: a duplicate pending request for the same seat already existed.',
                    'updated_at' => now(),
                ]);

            $this->notifyDeclinedRequesters($duplicateIds->all());
        }

        DB::statement("create unique index officer_change_requests_one_pending_per_seat on officer_change_requests (organization_id, position) where status = 'pending'");
    }

    public function down(): void
    {
        DB::statement('drop index if exists officer_change_requests_one_pending_per_seat');
    }

    /**
     * Best-effort — a mail or queue problem must never fail the migration.
     *
     * @param  array<int, int>  $declinedIds
     */
    private function notifyDeclinedRequesters(array $declinedIds): void
    {
        foreach (OfficerChangeRequest::query()->with('requester')->whereIn('id', $declinedIds)->get() as $request) {
            try {
                $request->requester->notify(new OfficerChangeDeclinedNotification($request));
            } catch (Throwable $e) {
                Log::error('Auto-declined duplicate officer change request: notification failed', [
                    'officer_change_request_id' => $request->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }
};
