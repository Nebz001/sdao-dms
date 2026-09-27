<?php

namespace App\Http\Requests\Api\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class StorePushTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255', 'regex:/\A(?:Exponent|Expo)PushToken\[[A-Za-z0-9_-]+\]\z/'],
            'device_id' => ['required', 'uuid'],
            'platform' => ['required', 'in:android,ios'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
