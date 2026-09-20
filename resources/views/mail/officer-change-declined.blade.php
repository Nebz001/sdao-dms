<x-mail::message>
# Request update

Your request to change **{{ $organizationName }}**'s **{{ $positionLabel }}**
to **{{ $nomineeName }}** was not approved this time.

@if ($comment)
**Reason given:**

> {{ $comment }}
@endif

You may file a new request once you've sorted it out.

<x-mail::button :url="$organizationUrl">
View Organization
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
