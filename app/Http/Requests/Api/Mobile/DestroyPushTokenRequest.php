<?php

namespace App\Http\Requests\Api\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class DestroyPushTokenRequest extends FormRequest
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
            'device_id' => ['nullable', 'uuid'],
        ];
    }
}
