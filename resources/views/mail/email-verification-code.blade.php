<x-mail::message>
# Your verification code

{{ $code }}

This code expires at {{ $expiresAt->format('g:i A') }} ({{ $expiresAt->diffForHumans(['parts' => 1]) }}).

If you didn't request this, you can ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
