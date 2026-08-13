<x-mail::message>
# You're in

Hi {{ $studentName }},

Good news — your request to join **{{ $organizationName }}** has been
approved. You now have officer access: you can submit and act on documents
for this organization.

<x-mail::button :url="$dashboardUrl">
Go to Dashboard
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
