<x-mail::message>
# Your request was closed

Your request to change **{{ $organizationName }}**'s **{{ $positionLabel }}** to
**{{ $nomineeName }}** was closed, because {{ $nomineeName }} can no longer be
considered for this seat. SDAO did not decide it.

You can file a new request naming someone else.

<x-mail::button :url="$requestUrl">
Request Officer Change
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
