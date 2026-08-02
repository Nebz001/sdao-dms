<x-mail::message>
@if ($isResubmission)
# Resubmitted for your review

Hi {{ $approverName }},

You previously returned this **{{ $formTypeLabel }}** from **{{ $organizationName }}** for
revision. It has now been resubmitted and is ready for your review again:

> {{ $documentTitle }}
@else
# Action needed

Hi {{ $approverName }},

A **{{ $formTypeLabel }}** from **{{ $organizationName }}** is waiting for your review:

> {{ $documentTitle }}
@endif

<x-mail::button :url="$reviewUrl">
Review Now
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
