<x-mail::message>
# New remark on your document

Hi {{ $recipientName }},

{{ $authorName }} left a remark on your **{{ $formTypeLabel }}** for **{{ $organizationName }}**:

> {{ $documentTitle }}

**Remark:**

> {{ $remark }}

This is a note only. It does not change the status of your document.

<x-mail::button :url="$documentUrl">
View Document
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
