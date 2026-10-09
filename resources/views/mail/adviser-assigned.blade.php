<x-mail::message>
# You are now the adviser of {{ $organizationName }}

SDAO assigned you as the adviser of **{{ $organizationName }}**.
{{ $waitingSentence }}

@if (count($waitingDocuments) > 0)
**Documents waiting at your step**

@foreach ($waitingDocuments as $document)
- {{ $document['title'] }}
@endforeach
@if ($moreDocuments > 0)
- …and {{ $moreDocuments }} more
@endif

@endif
@if ($pendingJoinRequestCount > 0)
Pending join requests are in your [join request queue]({{ $joinRequestsUrl }}).

@endif
<x-mail::button :url="$queueUrl">
Open your queue
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
