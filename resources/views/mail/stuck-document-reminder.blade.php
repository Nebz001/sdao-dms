<x-mail::message>
@if ($toApprover)
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
