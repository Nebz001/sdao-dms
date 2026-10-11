<?php

namespace App\Http\Controllers;

use App\Approval\FixDraft;
use App\Approval\ReturnedEditLock;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * "Save draft" on the Fix and Resend page (plain fetch, JSON). Same
 * authorization as the edit page itself; saves flagged items only.
 */
class FixDraftController extends Controller
{
    public function store(Request $request, Document $document): JsonResponse
    {
        Gate::authorize('edit', $document);

        $lock = ReturnedEditLock::for($document);

        // The web app renders validation failures as redirects (only api/* gets
        // JSON), which a fetch would follow and report as success; answer with a
        // real 422 instead.
        try {
            if ($lock === null) {
                throw ValidationException::withMessages(['draft' => 'This document has no flagged items to save.']);
            }

            $data = $request->validate(['sections' => ['present', 'array']]);

            FixDraft::put($document, $lock, $data['sections']);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json(['saved' => true, 'savedAt' => now()->toIso8601String()]);
    }
}
