@extends('print.layout', ['title' => 'Calendar of Activities'])

@section('content')

    <x-print.document-logo />

    <table class="bordered-table section">
        <colgroup><col></colgroup>
        <tr><td class="bar" style="font-size: 10pt; text-align: center;">{{ $title }}</td></tr>
    </table>

    <table class="bordered-table section" style="word-wrap: break-word;">
        <colgroup>
            <col style="width: 11%">
            <col style="width: 8%">
            <col style="width: 17%">
            <col style="width: 14%">
            <col style="width: 11%">
            <col style="width: 17%">
            <col style="width: 8%">
            <col style="width: 6%">
            <col style="width: 8%">
        </colgroup>
        <tr>
            <td class="label-col" style="width: 11%">RSO NAME</td>
            <td class="label-col" style="width: 8%">DATE</td>
            <td class="label-col" style="width: 17%">ACTIVITY NAME</td>
            <td class="label-col" style="width: 14%">SDG</td>
            <td class="label-col" style="width: 11%">VENUE</td>
            <td class="label-col" style="width: 17%">PARTICIPANT/PROGRAM ASSIGNED</td>
            <td class="label-col" style="width: 8%">BUDGET</td>
            <td class="label-col" style="width: 6%">STATUS</td>
            {{-- Kept misspelled verbatim to match the source spreadsheet —
                 see ActivityCalendarForm's class docblock. --}}
            <td class="label-col" style="width: 8%">DATE RECIEVED</td>
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

    <x-print.document-footer-note />

@endsection
