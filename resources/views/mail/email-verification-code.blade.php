<x-mail::message>
# Your verification code

<x-mail::verification-code :code="$code" />

{{-- $expiresAt is stored/cast in UTC; the recipient is Asia/Manila, so the
     printed clock time must be converted here (see App\Support\DisplayTimezone) --}}
This code expires at {{ \App\Support\DisplayTimezone::convert($expiresAt)->format('g:i A') }} ({{ $expiresAt->diffForHumans(['parts' => 1]) }}).

If you didn't request this, you can ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
