<x-mail::message>
# Organization renewal is now open

Hi {{ $recipientName }},

SDAO has opened the 3rd Term renewal season. Your organization's registration
must be renewed to remain active for **{{ $coveredYear }}**.

<x-mail::button :url="$renewUrl">
Submit Renewal
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
