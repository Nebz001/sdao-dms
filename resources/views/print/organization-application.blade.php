@extends('print.layout', ['title' => 'Student Organization Application Form'])

@section('content')

    {{-- Letterhead --}}
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
                <div class="org-line">NU Lipa</div>
                <div class="org-line">Student Development</div>
                <div class="org-line">and Activities Office</div>
            </td>
        </tr>
    </table>

    {{-- Title bar --}}
    <table class="bordered-table section">
        <colgroup>
            <col>
            <col style="width: 16.5%">
        </colgroup>
        <tr>
            <td class="title-bar-cell">Student Organization Application Form</td>
            <td class="form-code-cell">{{ $form_code }}<br>{{ $form_version }}</td>
        </tr>
    </table>

    {{-- Application for / Academic Year --}}
    <table class="bordered-table">
        <colgroup>
            <col style="width: 19%">
            <col style="width: 43%">
            <col style="width: 16%">
            <col style="width: 22%">
        </colgroup>
        <tr>
            <td class="label-col">APPLICATION FOR</td>
            <td>
                <x-print.checkbox :checked="$is_new" label="NEW" />
                <x-print.checkbox :checked="$is_renewal" label="RENEWAL" />
            </td>
            <td class="label-col">Academic Year</td>
            <td>{{ $academic_year }}</td>
        </tr>
    </table>

    {{-- 1. Contact Information --}}
    <table class="bordered-table section">
        <colgroup>
            <col style="width: 23%">
            <col>
        </colgroup>
        <tr><td colspan="2" class="bar">1. Contact Information</td></tr>
        <tr>
            <td class="label-col">Organization Name:</td>
            <td>{{ $organization_name }}</td>
        </tr>
        <tr>
            <td class="label-col">Contact Person:</td>
            <td>{{ $contact_person }}</td>
        </tr>
        <tr>
            <td class="label-col">Contact No.:</td>
            <td>{{ $contact_no }}</td>
        </tr>
        <tr>
            <td class="label-col">Email Address:</td>
            <td>{{ $email_address }}</td>
        </tr>
    </table>

    {{-- 2. Details of Organization --}}
    <table class="bordered-table section">
        <colgroup>
            <col style="width: 23%">
            <col>
        </colgroup>
        <tr><td colspan="2" class="bar">2. DETAILS OF ORGANIZATION</td></tr>
        <tr>
            <td class="label-col">Date Organized</td>
            <td>{{ $date_organized }}</td>
        </tr>
        <tr>
            <td class="label-col">Purpose of Organization</td>
            <td>{{ $purpose }}</td>
        </tr>
        <tr>
            <td class="label-col">Type of Organization</td>
            <td>
                <x-print.checkbox :checked="$is_co_curricular" label="Co-Curricular Organization" />
                <x-print.checkbox :checked="$is_extra_curricular" label="Extra Curricular Organization / Interest Clubs" />
            </td>
        </tr>
        <tr>
            <td class="label-col">College</td>
            <td>{{ $college }}</td>
        </tr>
    </table>

    {{-- 3. Endorsements / 4. Received by --}}
    <table class="bordered-table section">
        <colgroup>
            <col style="width: 25%"><col style="width: 25%"><col style="width: 25%"><col style="width: 25%">
        </colgroup>
        <tr>
            <td colspan="2" class="bar">3. ENDORSEMENTS</td>
            <td colspan="2" class="bar">4. Received by:</td>
        </tr>
        <tr>
            <td class="col-head">Faculty Adviser</td>
            <td class="col-head">College Dean</td>
            <td class="col-head">SDAO</td>
            <td class="col-head">CRSO</td>
        </tr>
        <tr>
            <td class="sig-cell">
                @foreach ($endorsements['faculty_adviser']->names as $name)
                    {{ $name }}<div class="rule"></div>
                @endforeach
                @if (empty($endorsements['faculty_adviser']->names))
                    <div class="rule"></div>
                @endif
            </td>
            <td class="sig-cell"><div class="rule"></div></td>
            <td class="sig-cell">
                @foreach ($endorsements['sdao']->names as $name)
                    {{ $name }}<div class="rule"></div>
                @endforeach
                @if (empty($endorsements['sdao']->names))
                    <div class="rule"></div>
                @endif
            </td>
            <td class="sig-cell"><div class="rule"></div></td>
        </tr>
        <tr>
            <td>Date: {{ $endorsements['faculty_adviser']->date }}</td>
            <td>Date: {{ $endorsements['college_dean']->date }}</td>
            <td>Date: {{ $endorsements['sdao']->date }}</td>
            <td>Date: {{ $endorsements['crso']->date }}</td>
        </tr>
        <tr>
            <td>Time: {{ $endorsements['faculty_adviser']->time }}</td>
            <td>Time: {{ $endorsements['college_dean']->time }}</td>
            <td>Time: {{ $endorsements['sdao']->time }}</td>
            <td>Time: {{ $endorsements['crso']->time }}</td>
        </tr>
    </table>

    {{-- 5. Approval --}}
    <table class="bordered-table section">
        <colgroup>
            <col style="width: 36%"><col style="width: 33%"><col style="width: 31%">
        </colgroup>
        <tr><td colspan="3" class="bar">5. APPROVAL</td></tr>
        <tr>
            <td rowspan="2">
                <x-print.checkbox :checked="$approval['is_approved']" label="APPROVED" /><br>
                <x-print.checkbox :checked="$approval['is_probation']" label="PROBATION" />
            </td>
            <td>Student Development and Activities Office</td>
            <td>CRSO</td>
        </tr>
        <tr>
            <td>Date: {{ $approval['sdao_date'] }}</td>
            <td>Date: {{ $approval['crso_date'] }}</td>
        </tr>
    </table>

    {{-- 6. Additional Remarks --}}
    <table class="bordered-table section">
        <colgroup><col></colgroup>
        <tr><td class="bar">6. Additional Remarks from SDAO / CRSO</td></tr>
        <tr><td class="remarks-cell">{{ $remarks }}</td></tr>
    </table>

    {{-- Instructions --}}
    <table class="bordered-table">
        <colgroup><col></colgroup>
        <tr>
            <td class="instructions">
                <strong><em>Instructions:</em></strong>
                <ol>
                    <li>Prepare two (2) copies: (1) Applicant; and (2) Student Development and Activities office</li>
                    <li>Please give about 5 to 15 days for the processing of application.</li>
                    <li>Please route this form and submit the signed form within fifteen (15) days from the start of every term for renewal: (a) Your College Dean; and (b) Student Development and Activities office.</li>
                </ol>
            </td>
        </tr>
    </table>

    {{-- 7. Requirements Attached --}}
    <table class="bordered-table section">
        <colgroup><col></colgroup>
        <tr><td class="bar">7. REQUIREMENTS ATTACHED:</td></tr>
    </table>
    <table class="bordered-table section">
        <colgroup>
            <col style="width: 50%">
            <col style="width: 50%">
        </colgroup>
        <tr>
            <td>
                <strong><em>NEW APPLICATION</em></strong><br>
                @foreach ($checklist['new'] as $row)
                    <div class="opt-row"><x-print.checkbox :checked="$row['checked']" :label="$row['label']" /></div>
                    {{-- The NEW column's "Others" row carries a blank underscore
                         rule on the paper form; the RENEWAL column's does not. --}}
                    @if ($row['label'] === 'Others')
                        <div class="rule-short"></div>
                    @endif
                @endforeach
            </td>
            <td>
                <strong><em>RENEWAL APPLICATION</em></strong><br>
                @foreach ($checklist['renewal'] as $row)
                    <div class="opt-row"><x-print.checkbox :checked="$row['checked']" :label="$row['label']" /></div>
                @endforeach
            </td>
        </tr>
    </table>

@endsection
