<x-mail::message>
# An officer seat has ended

SDAO deactivated **{{ $officerName }}**'s account, so their **{{ $positionLabel }}**
seat of **{{ $organizationName }}** has ended.

@if ($remainingOfficers === 0)
**{{ $organizationName }} now has no active officers.** Nobody can submit
documents for it until you bind a new one.
@elseif ($remainingOfficers === 1)
One active officer remains. You can bind another to fill the empty seat.
@else
{{ $remainingOfficers }} active officers remain.
@endif

<x-mail::button :url="$officersUrl">
Manage Officers
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
