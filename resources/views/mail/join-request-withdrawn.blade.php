<x-mail::message>
# Your join request was closed

You asked to join **{{ $organizationName }}**, but you have since become an
officer of an organization, so that request was closed automatically. You don't
need to do anything.

<x-mail::button :url="$dashboardUrl">
Open Dashboard
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
