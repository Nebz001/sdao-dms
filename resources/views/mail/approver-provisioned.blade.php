<x-mail::message>
# Your SDAO account is ready

Hi {{ $accountName }},

SDAO has created your account as **{{ $roleLabel }}**. You can log in right away with:

- **Email:** {{ $email }}
- **Temporary password:** `{{ $temporaryPassword }}`

<x-mail::button :url="$loginUrl">
Log In
</x-mail::button>

For your security, please log in and change this password as soon as possible. You can do that anytime from **Settings → Security**:

{{ $securityUrl }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
