<x-mail::message>
# New join request

Hi {{ $recipientName }},

**{{ $studentName }}** ({{ $studentEmail }}) has asked to join
**{{ $organizationName }}**. Review the request to approve or decline it.

<x-mail::button :url="$reviewUrl">
Review Request
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
