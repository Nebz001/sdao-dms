<x-mail::message>
@if ($variant === 'sdao_overdue')
# Overdue: review time has passed

Hi {{ $recipientName }},

A **{{ $formTypeLabel }}** from **{{ $organizationName }}** has been waiting {{ $idleText }} on **{{ $waitingOn }}**.
That is {{ $overdueText }} past the {{ $leadTimeLabel }} allotted for this step.
The approver has already been sent an overdue alert.

> {{ $documentTitle }}

<x-mail::button :url="$documentUrl">
Open Document
</x-mail::button>
@elseif ($variant === 'overdue')
# Overdue: your review is late

Hi {{ $recipientName }},

A **{{ $formTypeLabel }}** from **{{ $organizationName }}** has passed the {{ $leadTimeLabel }} allotted for your review.
It is now {{ $overdueText }} overdue, and the officers cannot move on until you decide:

> {{ $documentTitle }}

<x-mail::button :url="$documentUrl">
Review Now
</x-mail::button>
@elseif ($toApprover)
# Reminder: review needed

Hi {{ $recipientName }},

SDAO is following up on a **{{ $formTypeLabel }}** from **{{ $organizationName }}**.
It has been waiting for your review for {{ $idleText }}:

> {{ $documentTitle }}

<x-mail::button :url="$documentUrl">
Review Now
</x-mail::button>
@else
# Reminder: changes needed

Hi {{ $recipientName }},

SDAO is following up on a **{{ $formTypeLabel }}** from **{{ $organizationName }}**.
It was returned for revision {{ $idleText }} ago and is still waiting for your changes:

> {{ $documentTitle }}

Please make the requested changes and resubmit it.

<x-mail::button :url="$documentUrl">
Open Document
</x-mail::button>
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
