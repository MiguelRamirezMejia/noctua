<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Opciones de impresión más el PDF subido.
 */
class ImposeRequest extends PrintSettingsRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:512000'],
            ...parent::rules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['pdf' => 'PDF', ...parent::attributes()];
    }
}
