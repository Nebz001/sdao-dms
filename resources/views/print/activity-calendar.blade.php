@extends('print.layout', ['title' => 'Calendar of Activities'])

@section('content')

    <table class="bordered-table section">
        <colgroup><col></colgroup>
        <tr><td class="bar" style="font-size: 10pt; text-align: center;">{{ $title }}</td></tr>
    </table>

    <table class="bordered-table section">
        <colgroup>
            <col style="width: 12%">
            <col style="width: 8%">
            <col style="width: 18%">
            <col style="width: 14%">
            <col style="width: 12%">
            <col style="width: 14%">
            <col style="width: 8%">
            <col style="width: 6%">
            <col style="width: 8%">
        </colgroup>
        <tr>
            <td class="label-col">RSO NAME</td>
            <td class="label-col">DATE</td>
            <td class="label-col">ACTIVITY NAME</td>
            <td class="label-col">SDG</td>
            <td class="label-col">VENUE</td>
            <td class="label-col">PARTICIPANT/PROGRAM ASSIGNED</td>
            <td class="label-col">BUDGET</td>
            <td class="label-col">STATUS</td>
            {{-- Kept misspelled verbatim to match the source spreadsheet —
                 see ActivityCalendarForm's class docblock. --}}
            <td class="label-col">DATE RECIEVED</td>
        </tr>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['rso_name'] }}</td>
                <td>{{ $row['date'] }}</td>
                <td>{{ $row['activity_name'] }}</td>
                <td>{{ $row['sdg'] }}</td>
                <td>{{ $row['venue'] }}</td>
                <td>{{ $row['participant_program_assigned'] }}</td>
                <td>{{ $row['budget'] }}</td>
                <td>{{ $row['status'] }}</td>
                <td>{{ $row['date_received'] }}</td>
            </tr>
        @endforeach
    </table>

@endsection
