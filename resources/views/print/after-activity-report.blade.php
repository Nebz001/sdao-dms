@extends('print.layout', ['title' => 'After Activity Report'])

@section('content')

    <table class="letterhead">
        <colgroup>
            <col style="width: 11%">
            <col style="width: 89%">
        </colgroup>
        <tr>
            <td class="logo-cell">
                @if ($logo_path)
                    <img src="{{ $logo_path }}" alt="NU Lipa">
                @endif
            </td>
            <td>
                <div class="org-line" style="text-transform:none; font-size: 11pt;">After Activity Report</div>
                <div class="org-line" style="text-transform:none">{{ $name_of_event }}</div>
            </td>
        </tr>
    </table>

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
    @if (count($photo_filenames) > 0)
        <ul class="indent">
            @foreach ($photo_filenames as $filename)
                <li>{{ $filename }}</li>
            @endforeach
        </ul>
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
        Sample Evaluation Form: {{ $has_evaluation_form ? 'Attached.' : '' }}
    </div>

    <div class="h2">Attachment</div>
    <div>Attendance Sheet: {{ $has_attendance_sheet ? 'Attached.' : '' }}</div>

@endsection
