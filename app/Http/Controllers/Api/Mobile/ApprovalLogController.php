<?php

namespace App\Http\Controllers\Api\Mobile;

use App\ActivityProposals\ProposalReference;
use App\Enums\FormType;
use App\Enums\TransitionAction;
use App\Enums\TransitionSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Mobile\ApprovalLogIndexRequest;
use App\Http\Resources\Mobile\ApprovalLogResource;
use App\Identity\RoleDirectory;
use App\Models\DocumentTransition;
use App\Models\User;
use App\Support\DisplayTimezone;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class ApprovalLogController extends Controller
{
    private const int PER_PAGE = 20;

    private const array ACTIONS = [
        TransitionAction::Approved,
        TransitionAction::Returned,
        TransitionAction::Rejected,
    ];

    public function index(ApprovalLogIndexRequest $request): JsonResponse
    {
        $viewer = $request->user();
        abort_unless($viewer instanceof User, 401);

        $validated = $request->validated();
        $search = trim((string) ($validated['search'] ?? ''));
        $referenceDocument = $search === '' ? null : ProposalReference::resolve($search);

        $query = DocumentTransition::query()
            ->where('source', TransitionSource::Mobile->value)
            ->whereIn('action', array_map(fn (TransitionAction $action) => $action->value, self::ACTIONS))
            ->whereHas('document', fn ($documents) => $documents->where('form_type', FormType::ActivityProposal->value))
            ->when($validated['action'] ?? null, fn ($transitions, string $action) => $transitions->where('action', $action))
            ->when($validated['date_from'] ?? null, fn ($transitions, string $date) => $transitions
                ->where('created_at', '>=', Carbon::parse($date, DisplayTimezone::ASIA_MANILA)->startOfDay()->utc()))
            ->when($validated['date_to'] ?? null, fn ($transitions, string $date) => $transitions
                ->where('created_at', '<', Carbon::parse($date, DisplayTimezone::ASIA_MANILA)->addDay()->startOfDay()->utc()))
            ->when($search !== '', function ($transitions) use ($search, $referenceDocument) {
                $transitions->where(function ($matches) use ($search, $referenceDocument) {
                    $matches
                        ->whereHas('document', fn ($documents) => $documents
                            ->where('title', 'like', "%{$search}%")
                            ->orWhereHas('activityProposal', fn ($proposals) => $proposals->where('title', 'like', "%{$search}%")))
                        ->orWhereHas('actor', fn ($actors) => $actors->whereNameMatches($search));

                    if ($referenceDocument !== null) {
                        $matches->orWhere('document_id', $referenceDocument->id);
                    }
                });
            })
            ->with([
                'actor:id,name',
                'document.activityProposal',
                'document.organization',
                'document.workflowTemplate.steps',
                'document.transitions',
                'document.stepApprovals',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        // reviewView is the existing, authoritative read boundary used by the
        // mobile queue and proposal detail. Filtering before pagination keeps
        // both response rows and totals free of unauthorized proposal data.
        $visible = app(RoleDirectory::class)->remembering(
            fn () => $query->get()
                ->filter(fn (DocumentTransition $transition) => Gate::forUser($viewer)
                    ->allows('reviewView', $transition->document))
                ->values(),
        );

        $page = Paginator::resolveCurrentPage('page');
        $paginator = new LengthAwarePaginator(
            $visible->forPage($page, self::PER_PAGE)->values(),
            $visible->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return response()->json([
            'data' => collect($paginator->items())
                ->map(fn (DocumentTransition $transition) => (new ApprovalLogResource($transition))->resolve($request))
                ->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'links' => [
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }
}
