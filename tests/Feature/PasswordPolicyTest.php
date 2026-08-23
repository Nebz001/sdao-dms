<?php

use Illuminate\Validation\Rules\Password;

test('production password policy requires 8 characters, mixed case, and a symbol, without an uncompromised check', function () {
    $this->app['env'] = 'production';

    $rules = Password::default()->appliedRules();

    expect($rules['min'])->toBe(8);
    expect($rules['mixedCase'])->toBeTrue();
    expect($rules['symbols'])->toBeTrue();
    expect($rules['numbers'])->toBeFalse();
    expect($rules['uncompromised'])->toBeFalse();
});

test('non-production password policy only requires a minimum length', function () {
    $this->app['env'] = 'testing';

    $rules = Password::default()->appliedRules();

    expect($rules['min'])->toBe(8);
    expect($rules['mixedCase'])->toBeFalse();
    expect($rules['symbols'])->toBeFalse();
    expect($rules['uncompromised'])->toBeFalse();
});
