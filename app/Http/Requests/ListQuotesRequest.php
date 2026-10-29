<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListQuotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency' => $this->input('currency', 'ARS'),
            'page' => $this->input('page', 1),
            'per_page' => $this->input('per_page', 50),
        ]);
    }

    public function rules(): array
    {
        return [
            'currency' => ['required', 'regex:/^[A-Z]{3}$/'],
            'page' => ['required', 'integer', 'min:1'],
            'per_page' => ['required', 'integer', 'between:1,200'],
            'client' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
