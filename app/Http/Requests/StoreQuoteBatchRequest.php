<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreQuoteBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quotes' => ['required', 'array', 'min:1', 'max:500'],
            'quotes.*' => ['required', 'array'],
            ...QuoteValidationRules::make('quotes.*'),
        ];
    }
}
