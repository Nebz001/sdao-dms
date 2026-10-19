<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateStepLeadTimesRequest;
use App\Models\WorkflowStep;
use App\Models\WorkflowTemplate;
use App\Support\FlashToast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * SDAO's screen for how long each approval step is given (lead time, in days).
 * It is a column on the step, so it is configuration like the chain itself
 * (invariant #1). A change applies at once to documents already waiting: their
 * due time is always the arrival time plus the step's current lead time.
 */
class StepLeadTimeController extends Controller
{
    public function edit(): Response
    {
        $templates = WorkflowTemplate::query()
            ->active()
            ->with('steps')
            ->orderBy('form_type')
            ->orderBy('id')
            ->get()
            ->map(fn (WorkflowTemplate $template) => [
                'id' => $template->id,
                'formType' => $template->form_type->label(),
                'name' => $template->variant?->label() ?? $template->form_type->label(),
                'hasVariant' => $template->variant !== null,
                'steps' => $template->steps->map(fn (WorkflowStep $step) => [
                    'id' => $step->id,
                    'position' => $step->position,
                    'role' => $step->role === Role::SdaoMember ? 'SDAO (both members)' : $step->role->label(),
                    'leadTimeDays' => $step->lead_time_days,
                ])->values(),
            ])
            ->values();

        return Inertia::render('admin/settings/lead-times', [
            'templates' => $templates,
            'defaultDays' => WorkflowStep::DEFAULT_LEAD_TIME_DAYS,
            'maxDays' => WorkflowStep::MAX_LEAD_TIME_DAYS,
        ]);
    }

    public function update(UpdateStepLeadTimesRequest $request): RedirectResponse
    {
        /** @var array<int|string, int|string> $submitted */
        $submitted = $request->validated('lead_times');
        $steps = WorkflowStep::query()->whereIn('id', array_keys($submitted))->get()->keyBy('id');

        $changed = DB::transaction(function () use ($submitted, $steps): int {
            $count = 0;

            foreach ($submitted as $id => $days) {
                $step = $steps->get((int) $id);

                if ($step === null || $step->lead_time_days === (int) $days) {
                    continue;
                }

                $step->update(['lead_time_days' => (int) $days]);
                $count++;
            }

            return $count;
        });

        $message = $changed === 0
            ? 'No lead times were different, so nothing changed.'
            : $changed.' '.($changed === 1 ? 'step now has' : 'steps now have').' a new lead time. Documents already waiting use it from now on.';

        return redirect()->route('admin.settings.lead-times.edit')
            ->with('flash', FlashToast::make('Lead times saved', $message));
    }
}
