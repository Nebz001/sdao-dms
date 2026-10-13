<x-mail::message>
# Organization renewal is now open

Hi {{ $recipientName }},

SDAO has opened renewal for **{{ $termLabel }}**. Your organization's registration
must be renewed to remain active.

<x-mail::button :url="$renewUrl">
Submit Renewal
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
