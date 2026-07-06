<?php

use App\Enums\Term;

test('has exactly three cases', function () {
    expect(Term::cases())->toHaveCount(3);
});

test('cases have correct values', function () {
    expect(Term::FirstTerm->value)->toBe('first_term');
    expect(Term::SecondTerm->value)->toBe('second_term');
    expect(Term::ThirdTerm->value)->toBe('third_term');
});

test('labels are human-readable', function () {
    expect(Term::FirstTerm->label())->toBe('1st Term');
    expect(Term::SecondTerm->label())->toBe('2nd Term');
    expect(Term::ThirdTerm->label())->toBe('3rd Term');
});

test('can be constructed from string value', function () {
    expect(Term::from('first_term'))->toBe(Term::FirstTerm);
    expect(Term::from('second_term'))->toBe(Term::SecondTerm);
    expect(Term::from('third_term'))->toBe(Term::ThirdTerm);
});
