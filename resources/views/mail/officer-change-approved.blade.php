<x-mail::message>
# Officer change approved

The change to **{{ $organizationName }}**'s **{{ $positionLabel }}** has
been approved — **{{ $nomineeName }}** now holds this position.

<x-mail::button :url="$organizationUrl">
View Organization
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
