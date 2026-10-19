<?php

use App\Http\Requests\Organizations\BindOfficerRequest;
use App\Http\Requests\Organizations\RequestOfficerChangeRequest;
use App\Http\Requests\Proposals\StoreProposalStepOneRequest;
use App\Http\Requests\Proposals\SubmitProposalRequest;
use App\Http\Requests\Registrations\StoreRegistrationRequest;
use App\Http\Requests\Renewals\StoreRenewalRequest;
use App\Http\Requests\Reports\StoreReportRequest;
use App\Http\Requests\StoreDocumentRemarkRequest;
use Illuminate\Foundation\Http\FormRequest;

/*
 * The asterisks on the forms (FormField/Label `required`) mirror these rules.
 * If a field gains or loses `required` here, its asterisk has to follow.
 */
function requiredFieldsOf(FormRequest $request): array
{
    return collect($request->rules())
        ->filter(fn ($rules) => in_array('required', (array) $rules, true))
        ->keys()
        ->all();
}

dataset('required-field-contracts', [
    'registration' => [fn () => new StoreRegistrationRequest, ['name', 'adviser_id', 'purpose_of_organization', 'contact_person', 'contact_no', 'email_address', 'date_organized', 'attachments.letter_of_intent', 'attachments.by_laws']],
    'renewal' => [fn () => new StoreRenewalRequest, ['purpose_of_organization', 'contact_person', 'contact_no', 'email_address', 'date_organized', 'attachments.letter_of_intent']],
    'report' => [fn () => new StoreReportRequest, ['activity_proposal_id', 'summary', 'activity_chairs', 'prepared_by', 'event_program', 'target_participants_percentage']],
    'proposal narrative' => [fn () => new SubmitProposalRequest, ['objectives', 'activity_description', 'criteria_mechanics', 'program_flow', 'expense_items', 'responsible_persons']],
    'proposal request form' => [fn () => new StoreProposalStepOneRequest, ['calendar_mode', 'activity_nature', 'activity_type', 'partner_organizations', 'target_sdg', 'proposed_budget', 'budget_source']],
    'officer change' => [fn () => new RequestOfficerChangeRequest, ['position', 'nominee_id']],
    'bind officer' => [fn () => new BindOfficerRequest, ['user_id', 'position']],
    'remark' => [fn () => new StoreDocumentRemarkRequest, ['body']],
]);

it('requires the fields the forms mark with an asterisk', function (Closure $make, array $expected) {
    expect(requiredFieldsOf($make()))->toContain(...$expected);
})->with('required-field-contracts');

it('keeps the optional fields optional', function () {
    expect(requiredFieldsOf(new RequestOfficerChangeRequest))->not->toContain('reason');
    expect(requiredFieldsOf(new StoreReportRequest))->not->toContain('outcomes', 'participant_count');
});
