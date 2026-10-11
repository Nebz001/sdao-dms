<?php

namespace App\Http\Requests\Concerns;

use App\Approval\ReturnedEditLock;
use App\Models\Document;

/**
 * For the resubmit requests of a Returned document: the Fix and Resend page
 * only posts the fields the approver flagged, so the locked ones are filled
 * from what is stored before validation runs. A locked value the request sent
 * anyway is not touched here; the resubmit action compares it and refuses it.
 */
trait FillsLockedFields
{
    protected function prepareForValidation(): void
    {
        $document = $this->route('document');

        if (! $document instanceof Document) {
            return;
        }

        $lock = ReturnedEditLock::for($document);

        if ($lock !== null) {
            $this->merge($lock->lockedInputDefaults($this->all()));
        }
    }
}
