<x-mail::message>
# Your document has been {{ $outcomeLabel }}

Hi {{ $submitterName }},

Your **{{ $formTypeLabel }}** for **{{ $organizationName }}** has been {{ $outcomeLabel }}:

> {{ $documentTitle }}

@if ($comment)
**Reviewer comment:**

> {{ $comment }}
@endif

<x-mail::button :url="$documentUrl">
View Document
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
