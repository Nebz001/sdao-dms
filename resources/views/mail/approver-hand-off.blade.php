<x-mail::message>
# Action needed

Hi {{ $approverName }},

A **{{ $formTypeLabel }}** from **{{ $organizationName }}** is waiting for your review:

> {{ $documentTitle }}

<x-mail::button :url="$reviewUrl">
Review Now
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
