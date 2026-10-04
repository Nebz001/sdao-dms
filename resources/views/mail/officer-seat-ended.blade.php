<x-mail::message>
# Your officer seat has ended

You no longer hold the **{{ $positionLabel }}** seat of
**{{ $organizationName }}**. {{ $reasonSentence }}

You can no longer submit or act on documents for this organization. Your
past submissions stay in the document history.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
