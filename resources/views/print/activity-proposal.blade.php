@extends('print.layout', ['title' => 'Student Organization Activity Request Form'])

@section('content')

    {{-- ============================================================
         Page 1 — Student Organization Activity Request Form
         ============================================================ --}}

    <table class="bordered-table section">
        <colgroup>
            <col>
            <col style="width: 22%">
        </colgroup>
        <tr>
            <td class="title-bar-cell">Student Organization Activity Request Form</td>
            <td class="form-code-cell">{{ $form_number }}</td>
        </tr>
    </table>

    <table class="bordered-table section">
        <colgroup>
            <col style="width: 30%">
            <col>
        </colgroup>
        <tr>
            <td class="label-col">Name of RSO</td>
            <td>{{ $rso_name }}</td>
        </tr>
        <tr>
            <td class="label-col">Title of Activity</td>
            <td>{{ $title_of_activity }}</td>
        </tr>
        <tr>
            <td class="label-col">Nature of Activity</td>
            <td>
                @foreach ($nature_checklist as $row)
                    <div class="opt-row"><x-print.checkbox :checked="$row['checked']" :label="$row['label']" /></div>
                @endforeach
            </td>
        </tr>
        <tr>
            <td class="label-col">Type of Activity</td>
            <td>
                @foreach ($type_checklist as $row)
                    <div class="opt-row"><x-print.checkbox :checked="$row['checked']" :label="$row['label']" /></div>
                @endforeach
            </td>
        </tr>
        <tr>
            <td class="label-col">Partner Organization(s)/School(s) / RSO</td>
            <td>{{ implode(', ', $partner_organizations) }}</td>
        </tr>
        <tr>
            <td class="label-col">Target SDG</td>
            <td>{{ $target_sdg }}</td>
        </tr>
        <tr>
            <td class="label-col">Proposed Budget</td>
            <td>{{ $proposed_budget }}</td>
        </tr>
        <tr>
            <td class="label-col">Budget Source</td>
            <td>{{ $budget_source }}</td>
        </tr>
        <tr>
            <td class="label-col">Date of Activity</td>
            <td>{{ $date_of_activity }}</td>
        </tr>
        <tr>
            <td class="label-col">Venue</td>
            <td>{{ $venue }}</td>
        </tr>
    </table>

    {{-- To be accomplished by the NU Unit --}}
    <table class="bordered-table section">
        <colgroup>
            <col style="width: 34%"><col style="width: 33%"><col style="width: 33%">
        </colgroup>
        <tr>
            <td colspan="1" class="bar">Reviewed by:</td>
            <td colspan="2" class="bar">Approved by:</td>
        </tr>
        <tr>
            <td class="sig-cell">
                @foreach ($tail_signatures['sdao']->names as $name)
                    {{ $name }}<div class="rule"></div>
                @endforeach
                @if (empty($tail_signatures['sdao']->names))
                    <div class="rule"></div>
                @endif
                <div class="muted-note">{{ $tail_signatures['sdao']->roleLabel }}</div>
                @foreach ($tail_signatures['asst_director']->names as $name)
                    {{ $name }}<div class="rule"></div>
                @endforeach
                @if (empty($tail_signatures['asst_director']->names))
                    <div class="rule"></div>
                @endif
                <div class="muted-note">{{ $tail_signatures['asst_director']->roleLabel }}</div>
            </td>
            <td class="sig-cell">
                @foreach ($tail_signatures['academic_director']->names as $name)
                    {{ $name }}<div class="rule"></div>
                @endforeach
                @if (empty($tail_signatures['academic_director']->names))
                    <div class="rule"></div>
                @endif
                <div class="muted-note">{{ $tail_signatures['academic_director']->roleLabel }}</div>
            </td>
            <td class="sig-cell">
                @foreach ($tail_signatures['executive_director']->names as $name)
                    {{ $name }}<div class="rule"></div>
                @endforeach
                @if (empty($tail_signatures['executive_director']->names))
                    <div class="rule"></div>
                @endif
                <div class="muted-note">{{ $tail_signatures['executive_director']->roleLabel }}</div>
            </td>
        </tr>
        <tr>
            <td>Date: {{ $tail_signatures['sdao']->date }}</td>
            <td>Date: {{ $tail_signatures['academic_director']->date }}</td>
            <td>Date: {{ $tail_signatures['executive_director']->date }}</td>
        </tr>
    </table>

    <table class="bordered-table">
        <colgroup><col></colgroup>
        <tr>
            <td class="instructions">
                <em>Instruction: Attach the approved request letter containing the following</em>
                <ol>
                    <li>Rationale</li>
                    <li>Objectives</li>
                    <li>Program</li>
                    <li>Resume of the speaker (for workshops, seminars, etc.)</li>
                    <li>Sample Post-survey form</li>
                </ol>
            </td>
        </tr>
    </table>

    {{-- ============================================================
         Page 2+ — Proposal narrative
         ============================================================ --}}

    <table class="letterhead page-break">
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
                <div class="org-line" style="text-transform:none">NU Lipa</div>
                <div class="org-line" style="text-transform:none">{{ $rso_name }}</div>
                <div class="org-line" style="text-transform:none">{{ $school_name }}</div>
                @if ($program_name)
                    <div class="org-line" style="text-transform:none">{{ $program_name }}</div>
                @endif
                <div class="org-line" style="text-transform:none">AY {{ $academic_year }}</div>
            </td>
        </tr>
    </table>

    <div class="h2">I. Project/Activity Title</div>
    <div>{{ $title_of_activity }}</div>

    <div class="h2">II. Date and Time</div>
    <div>Proposed Date(s): {{ $date_of_activity }}</div>
    <div>Proposed Time: {{ $proposed_time }}</div>

    <div class="h2">III. Venue</div>
    <div>{{ $venue }}</div>

    <div class="h2">IV. Objectives</div>
    <div>{{ $objectives }}</div>

    <div class="h2">V. Activity Description</div>
    <div>{{ $narrative }}</div>
    <div class="indent"><strong>a. Criteria/Mechanics</strong><br>{{ $criteria_mechanics }}</div>
    <div class="indent"><strong>b. Program Flow</strong><br>{{ $program_flow }}</div>
    <div class="indent">
        <strong>c. Proposed Budget</strong><br>
        Source of Funding: {{ $source_of_funding }}<br>
        <strong>Expenses:</strong>
        @if (! empty($expense_items))
            <table class="bordered-table" style="margin-top: 3pt;">
                <colgroup>
                    <col>
                    <col style="width: 22%">
                </colgroup>
                <tr>
                    <td class="bar">Item</td>
                    <td class="bar" style="text-align: right;">Amount</td>
                </tr>
                @foreach ($expense_items as $item)
                    <tr>
                        <td>{{ $item['label'] }}</td>
                        <td style="text-align: right;">{{ $item['amount'] }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td class="label-col">TOTAL</td>
                    <td class="label-col" style="text-align: right;">{{ $expense_items_total }}</td>
                </tr>
            </table>
        @else
            {{ $expenses }}
        @endif
    </div>
    <div class="indent">
        <strong>d. Resume of Resource Person/s (if applicable)</strong><br>
        {{ $has_resource_person_resume ? 'Attached.' : '' }}
    </div>

    <div class="h2">VI. Responsible Person/s</div>
    {{-- No backing field — DEFERRED, see ActivityProposalForm::data() docblock. --}}
    <div class="rule"></div>

    <table class="bordered-table section" style="margin-top: 8pt;">
        <colgroup>
            <col style="width: 30%">
            <col>
        </colgroup>
        <tr>
            <td class="label-col">Prepared by:</td>
            <td>
                {{ $prepared_by_president }}
                <div class="rule"></div>
                <div class="muted-note">President, {{ $rso_name }}</div>
            </td>
        </tr>
        <tr>
            <td class="label-col">Approved by:</td>
            <td>
                @foreach ($narrative_signatures['adviser']->names as $name)
                    {{ $name }}
                @endforeach
                <div class="rule"></div>
                <div class="muted-note">Adviser, {{ $rso_name }} — Date: {{ $narrative_signatures['adviser']->date }}</div>
            </td>
        </tr>
        <tr>
            <td class="label-col">Reviewed by:</td>
            <td>
                @foreach ($narrative_signatures['reviewed_by']->names as $name)
                    {{ $name }}
                @endforeach
                <div class="rule"></div>
                <div class="muted-note">{{ $narrative_signatures['reviewed_by']->roleLabel }}, {{ $school_name }} — Date: {{ $narrative_signatures['reviewed_by']->date }}</div>
            </td>
        </tr>
        @if ($narrative_signatures['noted_by'])
            <tr>
                <td class="label-col">Noted by:</td>
                <td>
                    @foreach ($narrative_signatures['noted_by']->names as $name)
                        {{ $name }}
                    @endforeach
                    <div class="rule"></div>
                    <div class="muted-note">{{ $narrative_signatures['noted_by']->roleLabel }}, {{ $school_name }} — Date: {{ $narrative_signatures['noted_by']->date }}</div>
                </td>
            </tr>
        @endif
    </table>

@endsection
