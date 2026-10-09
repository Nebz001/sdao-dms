<?php

namespace App\Http\Controllers;

use App\Approval\AddDocumentRemark;
use App\Http\Requests\StoreDocumentRemarkRequest;
use App\Models\Document;
use App\Support\FlashToast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class DocumentRemarkController extends Controller
{
    /**
     * Add a remark to a document. Its own `remark` ability gates this, never
     * `view` or `review`: seeing a document and being allowed to comment on
     * it are different questions.
     */
    public function store(StoreDocumentRemarkRequest $request, Document $document, AddDocumentRemark $action): RedirectResponse
    {
        Gate::authorize('remark', $document);

        $action->execute($document, Auth::user(), $request->validated('body'));

        return back()->with('flash', FlashToast::make('Remark added', 'The organization\'s officers were notified. The document\'s status is unchanged.'));
    }
}
