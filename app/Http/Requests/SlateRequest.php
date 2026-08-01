<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Enforces the privacy seam described in spec 2.1: inbound requests carry exactly
 * three parameters (category, zip/metro_id, optional life_stage) plus paging.
 * No user ID, insight ID, or health signal is ever accepted here.
 */
class SlateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', 'string', 'exists:provider_categories,slug'],
            'zip' => ['required_without:metro_id', 'nullable', 'digits:5'],
            'metro_id' => ['required_without:zip', 'nullable', 'integer', 'exists:metros,id'],
            'life_stage' => ['nullable', 'string', 'exists:marketplace_life_stages,slug'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:5'],
        ];
    }
}
