<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Term;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCurrentTermRequest;
use App\Support\CurrentTerm;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin screen for the global "current term" setting (Phase 2 item 6). Only
 * this screen changes it; every other read goes through App\Support\CurrentTerm.
 */
class CurrentTermController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/settings/term', [
            'current' => CurrentTerm::get()->value,
            'terms' => collect(Term::cases())->map(fn (Term $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
        ]);
    }

    public function update(UpdateCurrentTermRequest $request): RedirectResponse
    {
        $term = Term::from($request->string('term')->toString());
        CurrentTerm::set($term);

        return redirect()->route('admin.settings.term.edit')
            ->with('flash', ['message' => "Current term updated to {$term->label()}. Already-submitted calendars are unchanged."]);
    }
}
