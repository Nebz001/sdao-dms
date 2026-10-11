@extends('print.layout', ['title' => 'After Activity Report'])

@section('content')

    <x-print.document-logo />

    <div class="org-line" style="text-transform:none; font-size: 11pt;">After Activity Report</div>
    <div class="org-line" style="text-transform:none; margin-bottom: 4pt;">{{ $name_of_event }}</div>

    <table class="bordered-table section">
        <colgroup>
            <col style="width: 30%">
            <col>
        </colgroup>
        <tr>
            <td class="label-col">Name of Event:</td>
            <td>{{ $name_of_event }}</td>
        </tr>
        <tr>
            <td class="label-col">Date and Time of Event:</td>
            <td>{{ $date_and_time_of_event }}</td>
        </tr>
        <tr>
            <td class="label-col">Activity Chair/s:</td>
            <td>{{ $activity_chairs }}</td>
        </tr>
        <tr>
            <td class="label-col">Prepared By:</td>
            <td>{{ $prepared_by }}</td>
        </tr>
        <tr>
            <td class="label-col">Date Submitted:</td>
            <td>{{ $date_submitted }}</td>
        </tr>
    </table>

    <div class="h2">Summary</div>
    <div>{{ $summary }}</div>

    <div class="h2">Program</div>
    <div>{{ $program }}</div>

    <div class="h2">Photos</div>
    @if (count($photos) > 0)
        <table style="margin-top: 3pt;">
            @foreach (array_chunk($photos, 2) as $row)
                <tr style="page-break-inside: avoid;">
                    @foreach ($row as $photo)
                        <td style="width: 50%; text-align: center; padding: 4pt;">
                            @if ($photo['kind'] === 'image')
                                <img src="{{ $photo['src'] }}" style="width: {{ $photo['width'] }}pt; height: {{ $photo['height'] }}pt;">
                                <div class="muted-note" style="font-size: 6.5pt; margin-top: 2pt;">{{ $photo['filename'] }}</div>
                            @else
                                <div class="muted-note">File could not be loaded: {{ $photo['filename'] }}</div>
                            @endif
                        </td>
                    @endforeach
                    @if (count($row) < 2)
                        <td style="width: 50%;"></td>
                    @endif
                </tr>
            @endforeach
        </table>
    @else
        {{-- No attachment slot backs a pasted poster/photo printout — blank
             area for a physically pasted printout, matching the template's
             own blank layout. --}}
        <div class="rule"></div>
    @endif

    <div class="h2">Activity Evaluation Report</div>
    <div class="muted-note">To include the percentage of the target participants and sample evaluation form used.</div>
    <div>
        % Target Participants: {{ $target_participants_percentage !== null ? $target_participants_percentage.'%' : '' }}<br>
        Sample Evaluation Form: {{ $has_evaluation_form ? 'Attached (see Appendix A).' : '' }}
    </div>

    <div class="h2">Attachment</div>
    <div>Attendance Sheet: {{ $has_attendance_sheet ? 'Attached (see Appendix B).' : '' }}</div>

    <x-print.document-footer-note />

@endsection
