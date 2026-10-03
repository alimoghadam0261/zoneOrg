<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0', 'max:100000'],
            'speed' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'heading' => ['nullable', 'numeric', 'between:0,360'],
            'battery' => ['nullable', 'integer', 'between:0,100'],
            'captured_at' => ['nullable', 'date', 'before_or_equal:'.now()->addMinutes(5)->format('Y-m-d H:i:s')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lat.required' => 'عرض جغرافیایی (lat) الزامی است.',
            'lat.between' => 'عرض جغرافیایی باید بین ۹۰- و ۹۰+ باشد.',
            'lng.required' => 'طول جغرافیایی (lng) الزامی است.',
            'lng.between' => 'طول جغرافیایی باید بین ۱۸۰- و ۱۸۰+ باشد.',
            'accuracy.required' => 'دقت GPS (accuracy) الزامی است.',
            'accuracy.min' => 'دقت GPS نمی‌تواند منفی باشد.',
            'battery.between' => 'میزان شارژ باتری باید بین ۰ تا ۱۰۰ باشد.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedPayload(): array
    {
        $data = $this->validated();

        $data['lat'] = (float) $data['lat'];
        $data['lng'] = (float) $data['lng'];
        $data['accuracy'] = (float) $data['accuracy'];
        $data['speed'] = isset($data['speed']) ? (float) $data['speed'] : null;
        $data['heading'] = isset($data['heading']) ? (float) $data['heading'] : null;
        $data['battery'] = isset($data['battery']) ? (int) $data['battery'] : null;
        $data['captured_at'] = $data['captured_at'] ?? now()->toDateTimeString();

        return $data;
    }
}
