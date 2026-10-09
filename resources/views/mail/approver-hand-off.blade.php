<x-mail::message>
@if ($returnedByYou)
# Resubmitted for your review

Hi {{ $approverName }},

You previously returned this **{{ $formTypeLabel }}** from **{{ $organizationName }}** for
revision. It has now been resubmitted and is ready for your review again:

> {{ $documentTitle }}
@else
# Action needed

Hi {{ $approverName }},

@if ($isRevised)
A **{{ $formTypeLabel }}** from **{{ $organizationName }}** was revised after being returned
for changes and is waiting for your review:
@else
A **{{ $formTypeLabel }}** from **{{ $organizationName }}** is waiting for your review:
@endif

> {{ $documentTitle }}
@endif

<x-mail::button :url="$reviewUrl">
Review Now
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
