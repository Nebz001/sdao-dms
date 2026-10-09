<x-mail::message>
# Your adviser role has ended

You are no longer the adviser of **{{ $organizationName }}**.

@if ($accountDeactivated)
Your account was also deactivated, so you can no longer sign in. If you weren't
expecting this, contact SDAO.
@else
You can no longer review documents or manage officers for this organization.
Documents you already reviewed stay in your history.
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
