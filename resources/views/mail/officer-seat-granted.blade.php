<x-mail::message>
# You're now an officer

You have been made **{{ $positionLabel }}** of **{{ $organizationName }}**.
You can now submit documents and receive returned documents for this
organization.

<x-mail::button :url="$dashboardUrl">
Open Dashboard
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
