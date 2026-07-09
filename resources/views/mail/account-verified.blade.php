<x-mail::message>
# You're verified

Hi {{ $accountName }},

Good news — SDAO has verified your account. You can now log in, be bound as
an organization officer, and submit documents.

<x-mail::button :url="$loginUrl">
Log In
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
