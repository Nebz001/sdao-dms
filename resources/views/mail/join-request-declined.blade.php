<x-mail::message>
# Request update

Hi {{ $studentName }},

Your request to join **{{ $organizationName }}** was not approved this time.

@if ($comment)
**Reason given:**

> {{ $comment }}
@endif

If you believe this was a mistake, reach out to the organization's adviser
directly, or file a new request once you've sorted it out.

<x-mail::button :url="$dashboardUrl">
Go to Dashboard
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
