<x-mail::message>
# Your organization has a new adviser

SDAO assigned **{{ $adviserName }}** as the adviser of **{{ $organizationName }}**.
They now review your documents at the adviser step and manage your officers.
Anything already waiting on your adviser goes to them.

<x-mail::button :url="$organizationUrl">
View your organization
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
