<x-mail::message>
# Your officer seat has ended

You no longer hold the **{{ $positionLabel }}** seat of
**{{ $organizationName }}**. {{ $reasonSentence }}

@if ($accountDeactivated)
You can no longer sign in. If you weren't expecting this, contact SDAO right
away — someone else may have been using your account.
@else
You can no longer submit or act on documents for this organization. Your
past submissions stay in the document history.
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
