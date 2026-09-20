<x-mail::message>
# Officer change requested

Hi {{ $recipientName }},

**{{ $requesterName }}** has requested a change to **{{ $organizationName }}**'s
**{{ $positionLabel }}**, nominating **{{ $nomineeName }}**.

@if ($reason)
**Reason given:**

> {{ $reason }}
@endif

Review the request to approve or decline it.

<x-mail::button :url="$reviewUrl">
Review Request
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
