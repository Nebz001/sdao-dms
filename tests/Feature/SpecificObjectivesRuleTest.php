<?php

use App\Rules\AtLeastOneSpecificObjective;
use Illuminate\Support\Facades\Validator;

test('the rule is skipped when the field is absent or null, and passes real lines', function () {
    $rules = ['specific_objectives' => AtLeastOneSpecificObjective::rules()];

    expect(Validator::make([], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['specific_objectives' => null], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['specific_objectives' => "One\nTwo"], $rules)->passes())->toBeTrue();
});

test('a value with no real line is refused, even though it is only whitespace', function () {
    $blank = Validator::make(
        ['specific_objectives' => "   \n   "],
        ['specific_objectives' => AtLeastOneSpecificObjective::rules()],
    );

    expect($blank->fails())->toBeTrue();
    expect($blank->errors()->first('specific_objectives'))->toBe('Add at least one specific objective, one per line.');
});

test('the rule caps the length', function () {
    $rules = ['specific_objectives' => AtLeastOneSpecificObjective::rules()];

    expect(Validator::make(['specific_objectives' => str_repeat('a', AtLeastOneSpecificObjective::MAX_LENGTH)], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['specific_objectives' => str_repeat('a', AtLeastOneSpecificObjective::MAX_LENGTH + 1)], $rules)->fails())->toBeTrue();
});
