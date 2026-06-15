<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'url' => [
                'required',
                'url',
                function ($attribute, $value, $fail) {
                    // Normalize and check if it's a valid Yandex Maps URL
                    $parsedUrl = parse_url($value);
                    $host = $parsedUrl['host'] ?? '';
                    $path = $parsedUrl['path'] ?? '';

                    $isYandex = str_contains($host, 'yandex.ru') ||
                                str_contains($host, 'yandex.com') ||
                                str_contains($host, 'yandex.kz') ||
                                str_contains($host, 'yandex.by') ||
                                str_contains($host, 'yandex.ua');

                    $isMaps = str_contains($path, '/maps/');

                    if (!$isYandex || !$isMaps) {
                        $fail('The URL must be a valid Yandex Maps organization card link.');
                    }
                },
            ],
        ];
    }
}
