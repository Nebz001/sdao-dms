<x-mail::message>
# Your SDAO account is ready

Hi {{ $accountName }},

SDAO has created your account as **{{ $roleLabel }}**. Log in with:

- **Email:** {{ $email }}
- **One time password:** `{{ $temporaryPassword }}`

<x-mail::button :url="$loginUrl">
Log In
</x-mail::button>

This password works once. You must set a new password the first time you log in, and you will not be able to use the system until you do.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
