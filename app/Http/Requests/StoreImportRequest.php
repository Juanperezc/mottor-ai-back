<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required_without:quotes', 'file', 'max:51200'],
            'quotes' => ['required_without:file', 'array', 'min:1', 'max:10000'],
            'quotes.*' => ['array'],
        ];
    }
}
